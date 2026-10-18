@php
  $nav = 'create';
@endphp
@extends('layouts.app')
@section('title', __('Buat Acara'))
@section('heading', __('Buat Acara'))
@section('content')
@php $steps=[1=>'Informasi acara',2=>'Sambutan & pemateri',3=>'Tinjau & susun']; @endphp
<div class="stepper">
  @foreach($steps as $n=>$lb)
    <div class="step {{ $n===$step?'on':($n<$step?'done':'') }}"><span class="sn">{{ $n<$step ? '✓' : $n }}</span><span class="sl">{{ __($lb) }}</span></div>
    @if(!$loop->last)<span class="sline {{ $n<$step?'done':'' }}"></span>@endif
  @endforeach
</div>

@if($step===1)
<form method="post" action="{{ route('events.save', 1) }}" class="card pad col" style="gap:32px">
  @csrf
  @include('events._info', ['v' => $wizard['info'] ?? []])
  <div class="row" style="justify-content:flex-end"><button class="btn btn-p btn-lg btn-block-m">{{ __('Lanjut') }} <i data-lucide="arrow-right" class="i"></i></button></div>
</form>

@elseif($step===2)
@php
  $rows = old('speakers', $wizard['speakers'] ?? []);
  if (!$rows) $rows = [['kind'=>'sambutan','name'=>'','role'=>'','topic'=>''],['kind'=>'pemateri','name'=>'','role'=>'','topic'=>'']];
@endphp
<form method="post" action="{{ route('events.save', 2) }}" class="card pad col" style="gap:24px">
  @csrf
  <div class="col" style="gap:4px"><div class="t18">{{ __('Sambutan & pemateri') }}</div><p class="muted">{{ __('Tambahkan satu baris untuk setiap orang yang akan memberi sambutan. Setiap baris Sambutan akan muncul di rundown dan dibuatkan draf naskahnya. Nama boleh dikosongkan.') }}</p></div>
  <div class="col" id="spk-list" style="gap:12px">
    @foreach($rows as $i => $r)
      <div class="spk">
        <select class="inp sm" name="speakers[{{ $i }}][kind]">
          <option value="sambutan" @selected(($r['kind']??'')==='sambutan')>{{ __('Sambutan') }}</option>
          <option value="pemateri" @selected(($r['kind']??'')==='pemateri')>{{ __('Pemateri') }}</option>
        </select>
        <input class="inp sm" name="speakers[{{ $i }}][name]" value="{{ $r['name'] ?? '' }}" placeholder="{{ __('Nama') }}">
        <input class="inp sm" name="speakers[{{ $i }}][role]" value="{{ $r['role'] ?? '' }}" placeholder="{{ __('Jabatan') }}">
        <input class="inp sm" name="speakers[{{ $i }}][topic]" value="{{ $r['topic'] ?? '' }}" placeholder="{{ __('Topik') }}">
        <button type="button" class="btn btn-s btn-ic sm rm" title="{{ __('Hapus') }}"><i data-lucide="x" class="i"></i></button>
      </div>
    @endforeach
  </div>
  <template id="spk-template">
    <div class="spk">
      <select class="inp sm" name="speakers[__INDEX__][kind]">
        <option value="pemateri">{{ __('Pemateri') }}</option>
        <option value="sambutan">{{ __('Sambutan') }}</option>
      </select>
      <input class="inp sm" name="speakers[__INDEX__][name]" placeholder="{{ __('Nama') }}">
      <input class="inp sm" name="speakers[__INDEX__][role]" placeholder="{{ __('Jabatan') }}">
      <input class="inp sm" name="speakers[__INDEX__][topic]" placeholder="{{ __('Topik') }}">
      <button type="button" class="btn btn-s btn-ic sm rm"><i data-lucide="x" class="i"></i></button>
    </div>
  </template>
  <div class="row" style="flex-wrap:wrap">
    <button type="button" id="add-sambutan" class="btn btn-s"><i data-lucide="plus" class="i"></i> {{ __('Tambah sambutan') }}</button>
    <button type="button" id="add-spk" class="btn btn-s"><i data-lucide="plus" class="i"></i> {{ __('Tambah baris') }}</button>
  </div>
  <div class="row sp"><a class="btn btn-g" href="{{ route('events.create', 1) }}">{{ __('Kembali') }}</a>
    <button class="btn btn-p btn-lg">{{ __('Lanjut') }} <i data-lucide="arrow-right" class="i"></i></button></div>
</form>
@push('scripts')
<script>
(function(){
  var list=document.getElementById('spk-list');
  function bind(){list.querySelectorAll('.rm').forEach(function(b){b.onclick=function(){b.closest('.spk').remove()}})}
  function addRow(kind){
    var i=Date.now()+list.children.length;
    var d=document.getElementById('spk-template').content.cloneNode(true);
    d.querySelectorAll('[name]').forEach(function(input){input.name=input.name.replace('__INDEX__',i)});
    d.querySelector('select').value=kind;
    list.appendChild(d);bind();if(window.lucide)lucide.createIcons();
  }
  document.getElementById('add-sambutan').onclick=function(){addRow('sambutan')};
  document.getElementById('add-spk').onclick=function(){addRow('pemateri')};
  bind();
})();
</script>
@endpush

@else
<div class="card pad col" style="gap:16px">
  <div class="t18">{{ $info['name'] }}</div>
  <div class="row muted t14" style="flex-wrap:wrap;gap:16px">
    <span class="row"><i data-lucide="calendar" class="i"></i> {{ \Illuminate\Support\Carbon::parse($info['event_date'])->translatedFormat('d M Y') }}</span>
    <span class="row"><i data-lucide="clock" class="i"></i> {{ $info['start_time'] }} · {{ round($info['duration_minutes']/60,1) }} {{ __('jam') }}</span>
    @if($info['location'])<span class="row"><i data-lucide="map-pin" class="i"></i> {{ $info['location'] }}</span>@endif
  </div>
</div>
<div class="card">
  <div class="pad row sp" style="padding-bottom:16px"><div class="t18">{{ __('Pratinjau rundown otomatis') }}</div><span class="badge ai"><i data-lucide="sparkles" class="i"></i>{{ count($preview) }} {{ __('baris') }}</span></div>
  <div class="rv th"><span>{{ __('Waktu') }}</span><span></span><span>{{ __('Kegiatan') }}</span><span class="hide-m">{{ __('Durasi') }}</span><span class="hide-m">PIC</span></div>
  @foreach($preview as $r)
    <div class="rv {{ $r['is_ishoma']?'ish':'' }}">
      <span class="tm">{{ $r['start_time'] }}–{{ $r['end_time'] }}</span>
      <span class="rail"><i class="{{ $r['is_ishoma']?'w':'' }}"></i></span>
      <span><span class="b">{{ $r['title'] }}</span>@if($r['note'])<br><span class="t12 muted">{{ __($r['note']) }}</span>@endif</span>
      <span class="hide-m muted">{{ $r['duration_minutes'] }} {{ __('mnt') }}</span>
      <span class="hide-m muted">{{ $r['pic'] }}</span>
    </div>
  @endforeach
</div>
<form method="post" action="{{ route('events.save', 3) }}" class="row sp">
  @csrf
  <a class="btn btn-g" href="{{ route('events.create', 2) }}">{{ __('Kembali') }}</a>
  <button class="btn btn-p btn-lg"><i data-lucide="wand-2" class="i"></i> {{ __('Susun Rundown') }}</button>
</form>
@endif
@endsection
