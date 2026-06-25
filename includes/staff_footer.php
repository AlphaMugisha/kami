    </main><!-- /.kami-content -->
  </div><!-- /.kami-main -->

  <?php if (($staff_area ?? '') === 'cashier'): ?>
  <audio id="global-order-chime" preload="auto">
    <source src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" type="audio/mpeg">
  </audio>
  <?php endif; ?>

  <script src="../assets/js/ozone.js"></script>
  <script>
  /* Light / dark toggle — persisted, mirrors the pre-paint check in the header */
  function kamiToggleTheme() {
    var isLight = document.documentElement.classList.toggle('light-mode');
    localStorage.setItem('kami_theme_mode', isLight ? 'light' : 'dark');
    var icon = document.querySelector('#kamiThemeToggle i');
    if (icon) icon.className = isLight ? 'ph ph-moon' : 'ph ph-sun';
  }
  (function () {
    var icon = document.querySelector('#kamiThemeToggle i');
    if (icon && document.documentElement.classList.contains('light-mode')) icon.className = 'ph ph-moon';
  })();
  </script>

  <?php if (($staff_area ?? '') === 'cashier'): ?>
  <script>
  /* Global live-order monitor + chime (cashier shell only) */
  document.addEventListener('DOMContentLoaded', function () {
    async function checkGlobalOrders() {
      try {
        var response = await fetch('live_orders.php?ajax=fetch');
        if (!response.ok) return;
        var data = await response.json();
        if (!data.success || !data.orders || data.orders.length === 0) return;
        var latestOrderId = Math.max.apply(null, data.orders.map(function (o) { return parseInt(o.id); }));
        var savedOrderId = parseInt(localStorage.getItem('kami_last_seen_order') || 0);
        if (latestOrderId > savedOrderId) {
          localStorage.setItem('kami_last_seen_order', latestOrderId);
          if (savedOrderId !== 0) {
            var chime = document.getElementById('global-order-chime');
            if (chime) chime.play().catch(function () {});
            var newest = data.orders.find(function (o) { return parseInt(o.id) === latestOrderId; });
            if (window.triggerDynamicIsland && newest) {
              window.triggerDynamicIsland('New VIP Request', 'Lounge/Table ' + newest.table + ' requires service.', 'success');
            }
          }
        }
      } catch (err) { console.error('Global monitor error:', err); }
    }
    setInterval(checkGlobalOrders, 4000);
    checkGlobalOrders();
  });
  </script>
  <?php endif; ?>

</body>
</html>
