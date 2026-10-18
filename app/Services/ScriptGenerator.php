<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Script;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Membuat naskah (sambutan, pidato, MC, doa). Bila OPENROUTER_API_KEY kosong,
 * dipakai templat bawaan agar aplikasi tetap dapat dicoba tanpa layanan AI.
 */
class ScriptGenerator
{
    public function usesAi(): bool
    {
        return filled(config('services.openrouter.key'));
    }

    public function generate(Event $event, string $type, ?string $religion = null): string
    {
        abort_unless(array_key_exists($type, Script::TYPES), 404);
        if ($type === 'doa' && ! in_array($religion, array_keys(config('eventflow.prayer_religions')), true)) {
            throw new RuntimeException('Agama untuk naskah doa belum dipilih atau tidak tersedia.');
        }

        $content = $this->usesAi()
            ? $this->callAi($event, $type, $religion)
            : $this->template($event, $type, $religion);

        return Script::cleanDraftContent($content);
    }

    private function callAi(Event $event, string $type, ?string $religion): string
    {
        $model = config('services.openrouter.model');
        if (! filled($model)) {
            throw new RuntimeException('Model OpenRouter belum diatur.');
        }

        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => 'Bearer '.config('services.openrouter.key'),
            ])
            ->post(config('services.openrouter.url'), [
                'model' => $model,
                'max_tokens' => $type === 'doa' ? 3000 : 6000,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Anda penulis naskah acara berbahasa Indonesia yang jelas dan sopan. '
                            .'Ikuti gaya yang diminta untuk setiap bagian dan tulis hanya naskah, tanpa pengantar atau penutup di luar naskah.',
                    ],
                    ['role' => 'user', 'content' => $this->prompt($event, $type, $religion)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Layanan AI tidak merespons ('.$response->status().').');
        }

        $text = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($text === '') {
            throw new RuntimeException('Layanan AI mengembalikan naskah kosong.');
        }

        return $text;
    }

    private function context(Event $event): string
    {
        $lines = [
            'Nama acara: '.$event->name,
            'Tema: '.($event->theme ?: '-'),
            'Tanggal: '.$event->event_date->translatedFormat('d F Y'),
            'Waktu: '.$event->start_time.' - '.$event->endTime().' WIB',
            'Lokasi: '.($event->location ?: '-'),
        ];
        foreach ($event->speakers as $s) {
            $lines[] = ucfirst($s->kind).': '.$s->name.($s->role ? " ({$s->role})" : '').($s->topic ? ", topik {$s->topic}" : '');
        }

        return implode("\n", $lines);
    }

    private function prompt(Event $event, string $type, ?string $religion): string
    {
        $ask = match ($type) {
            'sambutan' => $this->sambutanPrompt($event),
            'pidato' => $this->presenterPrompt($event),
            'doa' => $this->prayerPrompt($religion),
            'mc' => "Tulis naskah MC lengkap berurutan mengikuti rundown berikut:\n".$event->sessions
                ->map(fn ($s) => "{$s->start_time}-{$s->endTime()} {$s->title} (PIC: {$s->pic})")->implode("\n"),
        };

        return $ask."\n\nData acara:\n".$this->context($event);
    }

    private function prayerPrompt(?string $religion): string
    {
        $label = config("eventflow.prayer_religions.{$religion}");
        if (! filled($label)) {
            throw new RuntimeException('Agama untuk naskah doa belum dipilih atau tidak tersedia.');
        }

        return "Tulis naskah doa pembuka acara yang panjang dalam bahasa Indonesia, sesuai agama {$label}. "
            .'Buat sekitar 5-7 paragraf yang khidmat dan layak dibacakan, mencakup pembukaan doa sesuai tradisi agama tersebut, rasa syukur, permohonan kelancaran acara, harapan agar ilmu dan pertemuan membawa manfaat, serta penutup doa yang sesuai. '
            .'Gunakan sapaan dan ungkapan yang selaras dengan agama yang dipilih. Jangan mencampur ajaran, istilah ibadah, atau penutup dari agama lain. '
            .'Jangan mengarang kutipan kitab suci atau fakta keagamaan; tulis doa yang tulus, santun, inklusif terhadap hadirin, dan tidak terlalu bertele-tele.';
    }

    private function sambutanPrompt(Event $event): string
    {
        $sessions = $this->welcomeSessions($event);
        $schedule = collect($sessions)->map(fn (array $session, int $index) => sprintf(
            '%d. %s | PIC: %s | Waktu: %s-%s',
            $index + 1,
            $session['title'],
            $session['pic'],
            $session['start_time'],
            $session['end_time'],
        ))->implode("\n");

        return 'Tulis tepat '.count($sessions).' naskah sambutan, satu untuk setiap sesi sambutan pada rundown di bawah. '
            .'Untuk setiap sambutan, buat dua pilihan berlabel persis "VERSI FORMAL" dan "VERSI NONFORMAL", masing-masing 2-3 paragraf. '
            .'Cantumkan judul "SAMBUTAN [nomor] — [nama sesi/PIC]" sebelum dua versinya. '
            .'Sesuaikan isi dan penyebutan pembicara dengan PIC sesi masing-masing, serta ikuti urutan rundown. '
            .'Versi formal harus resmi dan khidmat. Versi nonformal harus hangat, akrab, ringan, dan tidak kaku agar suasana acara tidak tegang; tetap sopan dan pantas, gunakan bahasa percakapan yang wajar, hindari slang berlebihan. '
            ."Jangan menambah sambutan di luar jumlah sesi atau mengarang fakta.\n\nRundown sambutan:\n".$schedule;
    }

    /**
     * @return array<int, array{title:string,pic:string,start_time:string,end_time:string}>
     */
    private function welcomeSessions(Event $event): array
    {
        $sessions = $event->sessions->where('type', 'sambutan')->values();

        if ($sessions->isEmpty()) {
            return [[
                'title' => 'Sambutan Ketua Panitia',
                'pic' => 'Ketua Panitia',
                'start_time' => '-',
                'end_time' => '-',
            ]];
        }

        return $sessions->map(fn ($session) => [
            'title' => $session->title,
            'pic' => $session->pic ?: 'Pembicara sambutan',
            'start_time' => $session->start_time,
            'end_time' => $session->endTime(),
        ])->all();
    }

    private function presenterPrompt(Event $event): string
    {
        $sessions = $this->presenterSessions($event);
        if ($sessions === []) {
            throw new RuntimeException('Rundown belum memiliki sesi pemateri.');
        }

        $schedule = collect($sessions)->map(fn (array $session, int $index) => sprintf(
            '%d. %s | Pemateri: %s | Waktu: %s-%s',
            $index + 1,
            $session['title'],
            $session['pic'],
            $session['start_time'],
            $session['end_time'],
        ))->implode("\n");

        return 'Tulis tepat '.count($sessions).' naskah pemateri, satu untuk setiap sesi pemateri di rundown berikut. '
            .'Untuk setiap sesi, buat dua pilihan naskah lengkap berlabel persis "VERSI FORMAL" dan "VERSI NONFORMAL", masing-masing 3-5 paragraf. '
            .'Cantumkan judul "PEMATERI [nomor] — [judul sesi]" dan nama pemateri sesuai PIC. '
            .'Naskah harus disampaikan oleh pemateri, membahas topik sesuai judul sesi, memiliki pembukaan, isi yang runtut, dan penutup. '
            .'Versi formal harus profesional, jelas, dan tertata. Versi nonformal harus lebih hangat, ringan, dan mudah didengarkan agar suasana tidak tegang, tetapi tetap sopan dan informatif. '
            ."Jangan mengarang fakta spesifik yang tidak ada di data acara dan jangan menambah sesi pemateri.\n\nRundown sesi pemateri:\n".$schedule;
    }

    /**
     * @return array<int, array{title:string,pic:string,start_time:string,end_time:string}>
     */
    private function presenterSessions(Event $event): array
    {
        return $event->sessions
            ->filter(fn ($session) => in_array($session->type, ['sesi', 'pidato'], true))
            ->values()
            ->map(fn ($session) => [
                'title' => $session->title,
                'pic' => $session->pic ?: 'Pemateri',
                'start_time' => $session->start_time,
                'end_time' => $session->endTime(),
            ])
            ->all();
    }

    private function template(Event $event, string $type, ?string $religion): string
    {
        $date = $event->event_date->translatedFormat('d F Y');
        $theme = $event->theme ?: $event->name;

        return match ($type) {
            'sambutan' => $this->sambutanTemplate($event, $date, $theme),
            'pidato' => $this->presenterTemplate($event, $theme),
            'doa' => $this->prayerTemplate($event, $religion),
            'mc' => $this->mcTemplate($event),
        };
    }

    private function prayerTemplate(Event $event, ?string $religion): string
    {
        return match ($religion) {
            'islam' => "DOA PEMBUKA ACARA — ISLAM\n\n"
                ."Bismillahirrahmanirrahim. Alhamdulillahi rabbil 'alamin. Segala puji bagi Allah SWT, Tuhan semesta alam, atas rahmat dan karunia-Nya sehingga pada hari ini kita dapat berkumpul dalam acara {$event->name} dalam keadaan sehat dan penuh harapan.\n\n"
                ."Ya Allah Yang Maha Pengasih lagi Maha Penyayang, kami bersyukur atas kesempatan, waktu, dan kebersamaan yang Engkau berikan. Limpahkanlah ketenangan dan kebaikan kepada seluruh hadirin yang hadir, serta kepada keluarga dan rekan yang mendukung terselenggaranya kegiatan ini.\n\n"
                ."Ya Allah, mudahkanlah setiap rangkaian acara {$event->name}. Bimbinglah panitia dalam menjalankan tugasnya, berikan kelancaran kepada para pembicara dalam menyampaikan ilmu, dan bukakanlah hati serta pikiran kami agar dapat menerima setiap pengetahuan dengan baik.\n\n"
                ."Jadikanlah pertemuan ini sebagai jalan untuk menambah wawasan, mempererat persaudaraan, dan menumbuhkan niat baik. Berkahilah ilmu yang kami peroleh agar dapat diamalkan dan membawa manfaat bagi diri kami, masyarakat, dan lingkungan sekitar.\n\n"
                ."Ya Allah, jauhkanlah kami dari kesulitan dan hal-hal yang menghambat kebaikan. Karuniakanlah kepada kami sikap saling menghormati, kesabaran, dan semangat untuk bekerja sama. Semoga acara ini berjalan dengan tertib dan berakhir dengan membawa kebaikan bagi kita semua.\n\n"
                ."Rabbana atina fid-dunya hasanah, wa fil-akhirati hasanah, wa qina 'adzaban-nar. Subhana rabbika rabbil 'izzati 'amma yashifun, wassalamun 'alal mursalin, walhamdulillahi rabbil 'alamin. Aamiin ya rabbal 'alamin.",
            'kristen' => "DOA PEMBUKA ACARA — KRISTEN\n\n"
                ."Bapa kami yang ada di surga, kami datang ke hadirat-Mu dengan hati yang penuh syukur. Terima kasih atas penyertaan dan kasih-Mu sehingga kami dapat berkumpul dalam acara {$event->name} pada hari ini. Kami menyerahkan waktu dan kebersamaan ini ke dalam tangan-Mu.\n\n"
                ."Tuhan, kami bersyukur untuk setiap orang yang hadir, untuk panitia yang telah mempersiapkan acara ini, dan untuk para pembicara yang bersedia membagikan pengetahuan serta pengalaman. Kiranya Engkau memberkati setiap usaha dan pelayanan yang telah dilakukan.\n\n"
                ."Kami memohon agar seluruh rangkaian acara dapat berjalan dengan baik dan tertib. Berikanlah hikmat, kesehatan, dan ketenangan kepada para pembicara. Tolong kami semua agar dapat menyimak dengan pikiran terbuka, saling menghargai, dan menangkap hal-hal baik yang dibagikan.\n\n"
                ."Kiranya pertemuan ini menambah pengetahuan, mempererat persaudaraan, dan mendorong kami untuk melakukan hal-hal yang bermanfaat bagi sesama. Sertailah setiap percakapan dan keputusan yang lahir dari acara ini, agar semuanya membawa kebaikan dan pengharapan.\n\n"
                ."Kami menyerahkan acara ini dari awal hingga akhir ke dalam penyertaan-Mu. Berkatilah setiap peserta, panitia, dan keluarga yang mendukung kami. Kiranya damai sejahtera-Mu tinggal di tengah-tengah kami dan menuntun langkah kami setelah acara ini selesai.\n\n"
                ."Dalam nama Tuhan Yesus Kristus kami berdoa dan mengucap syukur. Amin.",
            'katolik' => "DOA PEMBUKA ACARA — KATOLIK\n\n"
                ."Dalam nama Bapa dan Putra dan Roh Kudus. Amin. Allah Bapa Yang Mahabaik, kami bersyukur atas kasih dan penyertaan-Mu sehingga kami dapat berkumpul dalam acara {$event->name}. Engkau menghimpun kami dalam kebersamaan dan memberi kesempatan untuk belajar serta saling menguatkan.\n\n"
                ."Kami bersyukur atas panitia yang telah mempersiapkan kegiatan ini, atas para pembicara yang akan berbagi pengetahuan, dan atas setiap peserta yang hadir. Terimalah niat baik serta usaha semua orang yang terlibat dalam acara ini.\n\n"
                ."Ya Tuhan, berkatilah seluruh rangkaian kegiatan kami. Anugerahkanlah kesehatan, kebijaksanaan, dan ketenangan kepada para pembicara. Bukalah hati dan pikiran kami agar mampu mendengarkan dengan sungguh-sungguh, memahami hal-hal yang baik, dan menghargai satu sama lain.\n\n"
                ."Semoga apa yang kami pelajari dalam pertemuan ini menumbuhkan semangat untuk berkarya, melayani, serta membawa manfaat bagi sesama. Bimbinglah kami agar menggunakan pengetahuan dengan tanggung jawab dan selalu mengutamakan kebaikan bersama.\n\n"
                ."Sertailah acara ini dari awal sampai akhir. Lindungilah setiap orang yang hadir dan mereka yang mendukung kami dari tempat lain. Semoga kebersamaan ini menjadi berkat, menghadirkan damai, dan menguatkan kami untuk melanjutkan tugas sehari-hari.\n\n"
                ."Semua doa dan harapan ini kami sampaikan kepada-Mu dengan perantaraan Kristus, Tuhan kami. Amin.",
            'hindu' => "DOA PEMBUKA ACARA — HINDU\n\n"
                ."Om Swastyastu. Om Awighnam Astu Namo Siddham. Kehadapan Ida Sang Hyang Widhi Wasa, kami menghaturkan puji dan syukur atas anugerah kesehatan, kesempatan, dan kerahayuan sehingga kami dapat berkumpul dalam acara {$event->name}.\n\n"
                ."Kami memohon tuntunan dan perlindungan-Mu bagi seluruh peserta, panitia, serta para pembicara. Semoga setiap orang yang hadir diliputi ketenangan, kejernihan pikiran, dan niat yang baik dalam mengikuti kegiatan ini.\n\n"
                ."Ya Ida Sang Hyang Widhi Wasa, tuntunlah agar seluruh rangkaian acara berjalan lancar, tertib, dan membawa manfaat. Berikanlah kelancaran kepada para pembicara dalam menyampaikan pengetahuan, serta kemampuan kepada kami untuk menerima dan memahami hal-hal yang berguna.\n\n"
                ."Semoga ilmu yang dibagikan dalam pertemuan ini menumbuhkan kebijaksanaan, memperkuat rasa saling menghormati, dan mendorong kami untuk menjaga keharmonisan dengan sesama serta lingkungan. Jauhkanlah kami dari kesalahpahaman dan tuntunlah kami untuk menggunakan pengetahuan demi kebaikan.\n\n"
                ."Kami juga memohon agar seluruh pihak yang telah membantu terselenggaranya acara ini senantiasa memperoleh kesehatan dan kekuatan. Semoga kebersamaan ini mempererat persaudaraan dan menjadi awal dari karya-karya yang bermanfaat bagi masyarakat.\n\n"
                ."Semoga kedamaian, keselamatan, dan kesejahteraan menyertai kita semua. Om Shanti, Shanti, Shanti Om. Om Santih, Santih, Santih Om. Om Swastyastu.",
            'buddha' => "DOA PEMBUKA ACARA — BUDDHA\n\n"
                ."Namo tassa bhagavato arahato samma sambuddhassa. Kami menghaturkan penghormatan kepada Sang Bhagava, Yang Mahasuci, Yang telah mencapai Penerangan Sempurna. Dengan pikiran yang tenang dan penuh kesadaran, marilah kita bersyukur atas kesempatan untuk berkumpul dalam acara {$event->name}.\n\n"
                ."Semoga semua peserta, panitia, dan pembicara berada dalam keadaan sehat, aman, dan damai. Semoga niat baik yang mendasari pertemuan ini menjadi sebab bagi tumbuhnya kebijaksanaan, perhatian, dan kepedulian kepada sesama.\n\n"
                ."Semoga seluruh rangkaian acara berlangsung dengan lancar. Semoga para pembicara dapat menyampaikan pengetahuan dengan jelas, dan semoga para peserta mampu mendengarkan dengan penuh perhatian, pikiran terbuka, serta sikap saling menghormati.\n\n"
                ."Semoga pengetahuan yang kita peroleh tidak berhenti sebagai pemahaman, tetapi mendorong tindakan yang bermanfaat. Semoga kita mampu mengembangkan kebijaksanaan, cinta kasih, dan welas asih, serta menghindari perkataan dan perbuatan yang dapat menyakiti makhluk lain.\n\n"
                ."Semoga kebersamaan ini mempererat hubungan baik dan memberi manfaat bagi masyarakat. Semoga semua pihak yang telah membantu acara ini memperoleh kebahagiaan dan kekuatan untuk terus berbuat baik.\n\n"
                ."Semoga semua makhluk berbahagia. Semoga semua makhluk terbebas dari penderitaan dan sebab-sebab penderitaan. Sadhu, sadhu, sadhu.",
            'konghucu' => "DOA PEMBUKA ACARA — KONGHUCU\n\n"
                ."Kehadirat Tian, Tuhan Yang Maha Esa, kami menyampaikan puji dan syukur atas rahmat, kehidupan, dan kesempatan yang diberikan kepada kami untuk berkumpul dalam acara {$event->name}. Semoga hati kami dipenuhi rasa hormat, ketulusan, dan semangat untuk belajar.\n\n"
                ."Kami bersyukur atas kerja keras panitia, kesediaan para pembicara untuk berbagi pengetahuan, dan kehadiran seluruh peserta. Semoga setiap niat baik dan usaha yang dilakukan dalam mempersiapkan kegiatan ini mendapat bimbingan dan membawa hasil yang berguna.\n\n"
                ."Kami memohon agar seluruh rangkaian acara dapat berlangsung dengan tertib dan lancar. Bimbinglah para pembicara agar menyampaikan pemikiran dengan jelas dan bijaksana. Bimbinglah pula kami semua agar mendengarkan dengan sungguh-sungguh, bersikap rendah hati, serta menghargai pandangan satu sama lain.\n\n"
                ."Semoga ilmu dan pengalaman yang dibagikan menumbuhkan kebajikan, memperkuat tanggung jawab, dan mendorong kami untuk berbuat nyata bagi sesama. Jadikanlah pertemuan ini kesempatan untuk mempererat persaudaraan dan membangun kehidupan bersama yang harmonis.\n\n"
                ."Semoga setiap peserta memperoleh manfaat dan dapat menerapkan hal-hal baik yang dipelajari. Semoga panitia dan semua pihak yang mendukung kegiatan ini senantiasa diberi kesehatan, ketekunan, dan kebijaksanaan dalam menjalankan tugas.\n\n"
                ."Dengan penuh hormat, kami menyerahkan kegiatan ini ke dalam bimbingan Tian. Semoga acara ini berlangsung dengan baik dan membawa kebaikan bagi semua. Wei De Dong Tian.",
            default => throw new RuntimeException('Agama untuk naskah doa belum dipilih atau tidak tersedia.'),
        };
    }

    private function sambutanTemplate(Event $event, string $date, string $theme): string
    {
        $drafts = [];
        foreach ($this->welcomeSessions($event) as $index => $session) {
            $number = $index + 1;
            $speaker = $session['pic'];
            $drafts[] = "SAMBUTAN {$number} — {$session['title']} (PIC: {$speaker})\n\n"
                ."VERSI FORMAL\n\n"
                ."Assalamualaikum warahmatullahi wabarakatuh.\n\n"
                ."Yang kami hormati para tamu undangan dan seluruh peserta {$event->name}. Saya {$speaker}, mewakili panitia, mengucapkan terima kasih atas kehadiran Bapak/Ibu pada {$date}. Puji syukur kita panjatkan kepada Tuhan Yang Maha Esa atas terselenggaranya acara ini.\n\n"
                ."Melalui tema \"{$theme}\", kami berharap kegiatan ini memberi manfaat nyata bagi seluruh peserta. Terima kasih kepada semua pihak yang telah mendukung acara ini. Selamat mengikuti seluruh rangkaian kegiatan. Wassalamualaikum warahmatullahi wabarakatuh.\n\n"
                ."VERSI NONFORMAL\n\n"
                ."Selamat datang, Bapak/Ibu dan teman-teman semua. Saya {$speaker}, senang sekali bisa menyambut Anda di {$event->name}. Terima kasih sudah meluangkan waktu untuk hadir dan meramaikan acara ini.\n\n"
                ."Hari ini kita akan berbagi pengalaman seputar \"{$theme}\". Semoga acaranya terasa menyenangkan, kita mendapat ide baru, dan suasananya tetap santai. Yuk, ikuti rangkaian kegiatan dengan nyaman dan aktif. Selamat menikmati acara!";
        }

        return implode("\n\n", $drafts);
    }

    private function presenterTemplate(Event $event, string $theme): string
    {
        $sessions = $this->presenterSessions($event);
        if ($sessions === []) {
            throw new RuntimeException('Rundown belum memiliki sesi pemateri.');
        }

        $drafts = [];
        foreach ($sessions as $index => $session) {
            $number = $index + 1;
            $speaker = $session['pic'];
            $topic = $session['title'];
            $drafts[] = "PEMATERI {$number} — {$topic} (PIC: {$speaker}, {$session['start_time']}-{$session['end_time']})\n\n"
                ."VERSI FORMAL\n\n"
                ."Selamat pagi/siang/sore. Yang saya hormati para tamu undangan dan peserta {$event->name}. Perkenalkan, saya {$speaker}. Pada kesempatan ini, saya akan membahas {$topic}.\n\n"
                ."Topik tersebut penting untuk kita pahami sebagai bagian dari tema \"{$theme}\". Melalui sesi ini, mari kita melihat pokok-pokok bahasan secara terstruktur, bertukar pandangan, dan mengaitkannya dengan pengalaman serta kebutuhan kita.\n\n"
                ."Demikian pemaparan dari saya. Semoga sesi ini bermanfaat dan dapat menjadi bahan diskusi bersama. Terima kasih atas perhatian hadirin.\n\n"
                ."VERSI NONFORMAL\n\n"
                ."Halo, teman-teman dan Bapak/Ibu semua. Saya {$speaker}, dan kali ini kita akan ngobrol bersama tentang {$topic}. Semoga kita bisa menikmati sesi ini sambil membawa pulang beberapa ide yang berguna.\n\n"
                ."Kita akan mulai dengan hal-hal utama seputar topik ini, lalu melihat bagaimana kaitannya dengan tema \"{$theme}\". Silakan simak dengan santai; semoga ada bagian yang terasa dekat dengan pengalaman teman-teman.\n\n"
                ."Itu dulu yang ingin saya bagikan. Terima kasih sudah menyimak—mari kita lanjutkan dengan diskusi dan pertanyaan.";
        }

        return implode("\n\n", $drafts);
    }

    private function mcTemplate(Event $event): string
    {
        $out = ["Selamat datang para hadirin di {$event->name}.", ''];
        foreach ($event->sessions as $s) {
            $out[] = "[{$s->start_time}] ".$s->title;
            $out[] = $s->is_ishoma
                ? 'MC: Hadirin sekalian, kita memasuki waktu Ishoma. Silakan beristirahat, menunaikan salat, dan menikmati hidangan.'
                : "MC: Acara selanjutnya adalah {$s->title}".($s->pic ? ", yang dibawakan oleh {$s->pic}." : '.');
            $out[] = '';
        }
        $out[] = 'Demikian rangkaian acara kita. Terima kasih atas kehadiran Bapak/Ibu sekalian.';

        return implode("\n", $out);
    }
}
