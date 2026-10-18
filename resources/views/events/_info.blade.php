@php
  $val = fn ($k, $d = '') => old($k, $v[$k] ?? $d);
  $feat = old('features', $v['features'] ?? ['ishoma','sambutan','pidato','mc','doa']);
  $customFeatures = old('custom_features', array_map(
    fn ($feature) => substr($feature, strlen('custom:')),
    array_filter($v['features'] ?? [], fn ($feature) => is_string($feature) && str_starts_with($feature, 'custom:')),
  ));
@endphp
<div class="sec">
  <div class="t18">{{ __('Informasi acara') }}</div>
  <div><label class="lbl" for="name">{{ __('Nama acara') }}</label>
    <input class="inp {{ $errors->has('name')?'err':'' }}" id="name" name="name" value="{{ $val('name') }}" placeholder="Seminar Nasional Teknologi 2026">
    @error('name')<div class="errtxt">{{ $message }}</div>@enderror</div>
  <div><label class="lbl" for="theme">{{ __('Tema') }} <span class="muted">({{ __('opsional') }})</span></label>
    <input class="inp" id="theme" name="theme" value="{{ $val('theme') }}"></div>
  <div class="g4">
    <div><label class="lbl" for="event_date">{{ __('Tanggal') }}</label>
      <input class="inp {{ $errors->has('event_date')?'err':'' }}" type="date" id="event_date" name="event_date" value="{{ $val('event_date') }}">
      @error('event_date')<div class="errtxt">{{ $message }}</div>@enderror</div>
    <div><label class="lbl" for="start_time">{{ __('Jam mulai') }}</label>
      <input class="inp {{ $errors->has('start_time')?'err':'' }}" type="time" id="start_time" name="start_time" value="{{ $val('start_time','08:00') }}">
      @error('start_time')<div class="errtxt">{{ $message }}</div>@enderror</div>
    <div><label class="lbl" for="duration_hours">{{ __('Durasi (jam)') }}</label>
      <input class="inp {{ $errors->has('duration_hours')?'err':'' }}" type="number" step="0.5" min="0.5" max="24" id="duration_hours" name="duration_hours" value="{{ $val('duration_hours', 6) }}">
      @error('duration_hours')<div class="errtxt">{{ $message }}</div>@enderror</div>
    <div><label class="lbl" for="location">{{ __('Lokasi') }}</label>
      <input class="inp" id="location" name="location" value="{{ $val('location') }}"></div>
  </div>
</div>
<div class="sec">
  <div class="row sp">
    <div class="t18">{{ __('Fitur yang dipakai') }}</div>
    <button type="button" id="add-feature" class="btn btn-s"><i data-lucide="plus" class="i"></i> {{ __('Tambah fitur') }}</button>
  </div>
  <div class="g3">
    @foreach(config('eventflow.features') as $key => $label)
      <label class="opt {{ in_array($key, $feat) ? 'on' : '' }}"><input type="checkbox" name="features[]" value="{{ $key }}" {{ in_array($key, $feat) ? 'checked' : '' }}> {{ __($label) }}</label>
    @endforeach
  </div>
  <div id="custom-features" class="col">
    @foreach($customFeatures as $feature)
      <div class="custom-feature-row">
        <input class="inp sm" name="custom_features[]" value="{{ $feature }}" maxlength="100" placeholder="{{ __('Nama fitur') }}">
        <button type="button" class="btn btn-s btn-ic remove-feature" aria-label="{{ __('Hapus fitur') }}"><i data-lucide="x" class="i"></i></button>
      </div>
    @endforeach
  </div>
  @error('custom_features')<div class="errtxt">{{ $message }}</div>@enderror
  @foreach($errors->get('custom_features.*') as $messages)
    @foreach($messages as $message)<div class="errtxt">{{ $message }}</div>@endforeach
  @endforeach
  <p class="t12 muted">{{ __('Kegiatan tambahan otomatis berbagi sisa durasi dengan sesi pemateri.') }}</p>
  <template id="custom-feature-template">
    <div class="custom-feature-row">
      <input class="inp sm" name="custom_features[]" maxlength="100" placeholder="{{ __('Nama fitur') }}">
      <button type="button" class="btn btn-s btn-ic remove-feature" aria-label="{{ __('Hapus fitur') }}"><i data-lucide="x" class="i"></i></button>
    </div>
  </template>
  <div class="card pad col ishoma-card" style="gap:8px;box-shadow:none">
    <div class="row b"><i data-lucide="moon" class="i"></i> {{ __('Ishoma otomatis') }}</div>
    <p class="t14 muted">{{ __('Jika durasi acara lebih dari 4 jam, baris Ishoma disisipkan otomatis pada waktu salat terdekat.') }}</p>
    <div class="row" style="flex-wrap:wrap">
      @foreach($prayerTimes as $n => $t)<span class="badge warn"><span class="dot"></span>{{ $n }} {{ $t }}</span>@endforeach
    </div>
  </div>
</div>
@push('scripts')
<script>
(function(){
  document.querySelectorAll('.opt input').forEach(function(input){
    input.addEventListener('change',function(){input.closest('.opt').classList.toggle('on',input.checked)});
  });
  var list=document.getElementById('custom-features');
  function bindRemoveButtons(){
    list.querySelectorAll('.remove-feature').forEach(function(button){
      button.onclick=function(){button.closest('.custom-feature-row').remove()};
    });
  }
  document.getElementById('add-feature').onclick=function(){
    list.appendChild(document.getElementById('custom-feature-template').content.cloneNode(true));
    bindRemoveButtons();
    if(window.lucide)lucide.createIcons();
    list.lastElementChild.querySelector('input').focus();
  };
  bindRemoveButtons();
})();
</script>
@endpush
