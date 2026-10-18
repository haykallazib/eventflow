<script>
const savedTheme = localStorage.getItem('eventflow-theme');
if (savedTheme === 'dark' || savedTheme === 'light') {
  document.documentElement.dataset.theme = savedTheme;
}
</script>
