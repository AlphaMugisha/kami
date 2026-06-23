<div id="preloader" class="preloader">
  <div class="preloader-content">
    <div class="preloader-logo">O</div>
    <div class="preloader-bar"><div class="preloader-bar-fill"></div></div>
  </div>
</div>
<script>
window.addEventListener('load', function() {
  setTimeout(function() {
    var p = document.getElementById('preloader');
    if (p) {
      p.classList.add('preloader-hidden');
      setTimeout(function() { p.remove(); }, 400);
    }
  }, 600);
});
</script>