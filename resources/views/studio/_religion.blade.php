<div style="max-width:360px;text-align:left">
  <label class="lbl" for="religion">{{ __('Agama untuk naskah doa') }}</label>
  <select class="inp" id="religion" name="religion" required>
    <option value="" disabled @selected(!old('religion'))>{{ __('Pilih agama') }}</option>
    @foreach($prayerReligions as $value => $label)
      <option value="{{ $value }}" @selected(old('religion') === $value)>{{ __($label) }}</option>
    @endforeach
  </select>
  @error('religion')<div class="errtxt">{{ $message }}</div>@enderror
</div>
