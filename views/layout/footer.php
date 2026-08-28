  </main><!-- /page-content -->
</div><!-- /main-wrap -->

<script>
// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebarToggle');
const toggleIcon = document.getElementById('toggleIcon');

function applyCollapsed(collapsed) {
  if (collapsed) {
    sidebar.classList.add('collapsed');
    toggleIcon.className = 'fa-solid fa-chevron-right';
  } else {
    sidebar.classList.remove('collapsed');
    toggleIcon.className = 'fa-solid fa-chevron-left';
  }
}

// Restaurer depuis localStorage
applyCollapsed(localStorage.getItem('crict_sidebar_collapsed') === '1');

toggleBtn.addEventListener('click', () => {
  const isCollapsed = sidebar.classList.toggle('collapsed');
  localStorage.setItem('crict_sidebar_collapsed', isCollapsed ? '1' : '0');
  toggleIcon.className = isCollapsed ? 'fa-solid fa-chevron-right' : 'fa-solid fa-chevron-left';
});

// Fermer modals sur clic overlay
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('open');
  });
});

// Auto-dismiss alerts après 5s
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(a => {
    a.style.transition = 'opacity .4s';
    a.style.opacity = '0';
    setTimeout(() => a.remove(), 400);
  });
}, 5000);
</script>
<?php if (!empty($extra_scripts)): ?>
  <?= $extra_scripts ?>
<?php endif; ?>
</body>
</html>
