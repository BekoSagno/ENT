<!-- ──────────────────────────────────────────────────────────
     SNIPPET À INSÉRER DANS : views/layout/header.php
     Emplacement suggéré : juste après le bloc "Projets & Tâches"
     (avant la section "Administration"), ou dans une section
     "Documents". Le module n'apparaît que si l'utilisateur a la
     permission GED/VOIR.
────────────────────────────────────────────────────────────── -->

    <?php if (in_array('GED', $modules_acces)): ?>
    <div class="nav-section-label">Documents</div>
    <a href="<?= APP_URL ?>/modules/ged/index.php"
       class="nav-item <?= $module_actif === 'GED' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-folder-tree"></i></span>
      <span class="label">Documents (GED)</span>
    </a>
    <?php endif; ?>
