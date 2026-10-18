<div class="lang">
  <a href="{{ route('lang','id') }}" class="{{ app()->getLocale()==='id'?'on':'' }}">ID</a>
  <a href="{{ route('lang','en') }}" class="{{ app()->getLocale()==='en'?'on':'' }}">EN</a>
</div>
