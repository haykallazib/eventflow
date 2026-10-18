<script>
(function(){
  var root=document.documentElement;
  var toggles=document.querySelectorAll('[data-theme-toggle]');
  function sync(){
    var isDark=root.dataset.theme==='dark';
    toggles.forEach(function(button){
      button.setAttribute('aria-pressed',String(isDark));
      button.title=isDark?'{{ __('Aktifkan mode terang') }}':'{{ __('Aktifkan mode gelap') }}';
    });
  }
  toggles.forEach(function(button){
    button.addEventListener('click',function(){
      root.dataset.theme=root.dataset.theme==='dark'?'light':'dark';
      localStorage.setItem('eventflow-theme',root.dataset.theme);
      sync();
    });
  });
  sync();
})();
</script>
