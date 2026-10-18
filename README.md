# EventFlow (Laravel)

Aplikasi web untuk panitia acara: membuat **rundown otomatis** (dengan baris **Ishoma** bila durasi > 4 jam) dan **naskah AI** (sambutan, pidato utama, naskah MC, doa), lalu mengekspornya ke PDF. Tampilan mengikuti mockup EventFlow (sidebar + konten di desktop, bilah tab bawah di mobile, toggle ID | EN).

Formulir acara mendukung fitur tambahan buatan pengguna; kegiatan tersebut otomatis dimasukkan ke rundown dan berbagi sisa durasi dengan sesi pemateri.

Dibuat di atas Laravel 13 (PHP 8.3+). Bagian yang ditambahkan ke kerangka Laravel:

| Lokasi | Isi |
|---|---|
| `database/migrations/` | users (username), events, event_sessions, speakers, scripts |
| `app/Models/` | User, Event, EventSession, Speaker, Script |
| `app/Services/RundownGenerator.php` | Algoritma rundown + aturan Ishoma (PHP murni, mudah diuji) |
| `app/Services/ScriptGenerator.php` | Naskah via OpenRouter Chat Completions API; templat bawaan bila tanpa kunci API |
| `app/Http/Controllers/` | Auth, Event, EventWizard (3 langkah), Rundown, Script, Export |
| `resources/views/` | Blade: layouts, auth, dashboard, wizard, rundown, studio, export |
| `public/css/eventflow.css` | Token + komponen desain dari mockup |
| `lang/en.json` | Terjemahan EN (bahasa bawaan: Indonesia) |
| `config/eventflow.php` | Ambang Ishoma dan jadwal salat (data contoh statis) |

## Menjalankan

```bash
composer install            # termasuk barryvdh/laravel-dompdf untuk PDF
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # DB_CONNECTION=sqlite (bawaan); atau atur MySQL di .env
php artisan migrate --seed       # seed: akun demo + 1 acara contoh
php artisan serve
```

Buka http://localhost:8000 dan masuk dengan **demo / password123** (atau daftar akun baru).

### Naskah AI
Isi `OPENROUTER_API_KEY` dan `OPENROUTER_MODEL` di `.env` untuk memakai layanan AI. Gunakan model ID yang tersedia di OpenRouter (format `provider/model`). Tanpa kunci, studio memakai templat bawaan (ada banner "Mode contoh").

### Pengujian
```bash
php artisan test
```

## Catatan
- Jadwal salat di `config/eventflow.php` adalah data contoh; sesuaikan atau hubungkan ke layanan jadwal salat.
- Ishoma: bila durasi > 240 menit dan fitur Ishoma dipilih, baris Ishoma (default 60 menit) disisipkan pada waktu salat terdekat dalam rentang acara; sesi yang melintasinya dipecah menjadi Bagian 1 dan 2.
- Mode gelap/terang tersedia melalui tombol tema dan preferensinya disimpan di browser.
