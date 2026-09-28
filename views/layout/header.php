<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_titre ?? 'Tableau de bord') ?> — CRICT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  /* Palette bleue institutionnelle CRICT */
  --blue-950:  #03144a;
  --blue-900:  #0a2158;
  --blue-800:  #0e3278;
  --blue-700:  #1347a8;
  --blue-600:  #1a5ccc;
  --blue-500:  #2570e8;
  --blue-400:  #4d8ef5;
  --blue-300:  #85b3fa;
  --blue-200:  #bdd3fd;
  --blue-100:  #ddeaff;
  --blue-50:   #f0f6ff;

  --accent:    #00c6a2;       /* Teal accent */
  --accent-dk: #009e82;
  --warn:      #f59e0b;
  --danger:    #ef4444;
  --success:   #22c55e;

  --sidebar-w: 270px;
  --sidebar-collapsed: 68px;
  --header-h:  64px;

  --surface:   #ffffff;
  --bg:        #f3f7ff;
  --border:    #dde6f5;
  --text:      #1a2540;
  --text-muted:#6b7fa3;
  --shadow:    0 2px 16px rgba(13,50,120,.08);
  --radius:    12px;

  --font:      'Plus Jakarta Sans', sans-serif;
  --mono:      'JetBrains Mono', monospace;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 15px; }
body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
  overflow-x: hidden;
}

/* ======================================================
   SIDEBAR
====================================================== */
.sidebar {
  width: var(--sidebar-w);
  min-height: 100vh;
  background: linear-gradient(170deg, var(--blue-950) 0%, var(--blue-900) 60%, var(--blue-800) 100%);
  display: flex;
  flex-direction: column;
  position: fixed;
  left: 0; top: 0; bottom: 0;
  z-index: 100;
  transition: width .25s cubic-bezier(.4,0,.2,1);
  overflow: hidden;
}
.sidebar.collapsed { width: var(--sidebar-collapsed); }

/* Logo */
.sidebar-logo {
  height: var(--header-h);
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 18px;
  border-bottom: 1px solid rgba(255,255,255,.08);
  flex-shrink: 0;
}
.logo-icon {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, var(--blue-500), var(--accent));
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: .95rem; color: #fff; font-weight: 800;
  flex-shrink: 0;
  box-shadow: 0 4px 14px rgba(0,198,162,.3);
}
.logo-text { overflow: hidden; white-space: nowrap; }
.logo-text strong {
  display: block; color: #fff; font-size: .95rem; font-weight: 800; letter-spacing: .02em;
}
.logo-text span {
  font-size: .7rem; color: var(--blue-300); font-weight: 400;
}

/* User info */
.sidebar-user {
  padding: 16px 18px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  display: flex; align-items: center; gap: 10px;
  min-width: 0;
}
.user-avatar {
  width: 38px; height: 38px; border-radius: 50%;
  background: var(--blue-700);
  border: 2px solid var(--blue-500);
  display: flex; align-items: center; justify-content: center;
  font-size: .85rem; font-weight: 700; color: #fff;
  flex-shrink: 0; overflow: hidden;
}
.user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.user-info { overflow: hidden; }
.user-info .name {
  font-size: .82rem; font-weight: 600; color: #fff;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.user-info .role {
  font-size: .7rem; color: var(--blue-300); white-space: nowrap;
}

/* Nav */
.sidebar-nav { flex: 1; overflow-y: auto; padding: 10px 0; }
.sidebar-nav::-webkit-scrollbar { width: 3px; }
.sidebar-nav::-webkit-scrollbar-thumb { background: var(--blue-700); border-radius: 2px; }

.nav-section-label {
  font-size: .62rem; font-weight: 700; letter-spacing: .12em;
  color: var(--blue-400); text-transform: uppercase;
  padding: 14px 20px 4px;
  white-space: nowrap; overflow: hidden;
}
.collapsed .nav-section-label { opacity: 0; }

.nav-item {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 18px;
  color: rgba(255,255,255,.65);
  text-decoration: none;
  font-size: .84rem; font-weight: 500;
  border-radius: 0 30px 30px 0;
  margin-right: 10px;
  transition: all .18s;
  white-space: nowrap;
  position: relative;
}
.nav-item:hover {
  color: #fff;
  background: rgba(255,255,255,.07);
}
.nav-item.active {
  color: #fff;
  background: linear-gradient(90deg, var(--blue-600), var(--blue-500));
  box-shadow: 0 4px 16px rgba(37,112,232,.35);
}
.nav-item .icon {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  font-size: .9rem;
  flex-shrink: 0;
  border-radius: 8px;
  background: rgba(255,255,255,.07);
  transition: background .18s;
}
.nav-item.active .icon { background: rgba(255,255,255,.15); }
.nav-item:hover .icon { background: rgba(255,255,255,.12); }
.nav-item .label { overflow: hidden; }

/* Sous-menu déroulant (groupe de navigation) */
.nav-parent { position: relative; }
.nav-caret {
  position: absolute; top: 50%; right: 16px; transform: translateY(-50%);
  background: transparent; border: none; cursor: pointer;
  color: rgba(255,255,255,.5);
  padding: 6px; border-radius: 6px; z-index: 2;
  transition: transform .25s, color .18s, background .18s;
}
.nav-caret:hover { color: #fff; background: rgba(255,255,255,.12); }
.nav-group.open .nav-caret { transform: translateY(-50%) rotate(180deg); }
.nav-sub {
  max-height: 0; overflow: hidden;
  transition: max-height .28s ease;
}
.nav-group.open .nav-sub { max-height: 320px; }
.nav-subitem {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 18px 8px 46px;
  color: rgba(255,255,255,.55);
  text-decoration: none;
  font-size: .8rem; font-weight: 500;
  border-radius: 0 30px 30px 0;
  margin-right: 10px;
  white-space: nowrap;
  transition: all .18s;
}
.nav-subitem:hover { color: #fff; background: rgba(255,255,255,.07); }
.nav-subitem.active { color: #fff; background: rgba(255,255,255,.13); font-weight: 600; }
.nav-subitem i { width: 18px; text-align: center; font-size: .8rem; opacity: .85; }
.collapsed .nav-caret { display: none; }
.collapsed .nav-sub { display: none; }

/* Badge (ex. Rapport Quotidien, Mes demandes) */
.nav-badge {
  margin-left: auto;
  background: var(--accent);
  color: #fff;
  font-size: .65rem; font-weight: 800;
  border-radius: 20px;
  padding: 2px 7px;
  flex-shrink: 0;
}
.collapsed .nav-badge { display: none; }

/* Sidebar footer */
.sidebar-footer {
  padding: 12px 18px;
  border-top: 1px solid rgba(255,255,255,.08);
}
.sidebar-footer a {
  display: flex; align-items: center; gap: 10px;
  color: rgba(255,255,255,.5);
  text-decoration: none;
  font-size: .82rem;
  padding: 8px;
  border-radius: 8px;
  transition: all .18s;
  white-space: nowrap;
}
.sidebar-footer a:hover { color: #fff; background: rgba(255,255,255,.07); }

/* Toggle button */
.sidebar-toggle {
  position: fixed; left: calc(var(--sidebar-w) - 14px); top: 20px;
  width: 28px; height: 28px;
  background: var(--blue-600); border: none; border-radius: 50%;
  color: #fff; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  z-index: 200; transition: left .25s cubic-bezier(.4,0,.2,1);
  box-shadow: 0 2px 8px rgba(0,0,0,.25);
  font-size: .72rem;
}
.sidebar.collapsed ~ .sidebar-toggle { left: calc(var(--sidebar-collapsed) - 14px); }

/* ======================================================
   MAIN
====================================================== */
.main-wrap {
  margin-left: var(--sidebar-w);
  flex: 1;
  display: flex; flex-direction: column;
  min-height: 100vh;
  transition: margin-left .25s cubic-bezier(.4,0,.2,1);
}
.sidebar.collapsed ~ .main-wrap { margin-left: var(--sidebar-collapsed); }

/* Header */
.topbar {
  height: var(--header-h);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center;
  padding: 0 28px;
  gap: 16px;
  position: sticky; top: 0; z-index: 90;
  box-shadow: 0 1px 8px rgba(13,50,120,.06);
}
.topbar-breadcrumb {
  flex: 1;
}
.topbar-breadcrumb h1 {
  font-size: 1rem; font-weight: 700; color: var(--text);
}
.topbar-breadcrumb .crumb {
  font-size: .75rem; color: var(--text-muted);
}
.topbar-actions { display: flex; align-items: center; gap: 10px; }
.topbar-btn {
  width: 36px; height: 36px;
  border: 1px solid var(--border);
  background: var(--bg);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: var(--text-muted); cursor: pointer;
  transition: all .15s; text-decoration: none; font-size: .85rem;
  position: relative;
}
.topbar-btn:hover { border-color: var(--blue-400); color: var(--blue-600); background: var(--blue-50); }
.topbar-btn .rdv-notif-dot {
  position: absolute; top: -5px; right: -5px;
  min-width: 16px; height: 16px; padding: 0 4px;
  border-radius: 10px; background: #dc2626; color: #fff;
  font-size: .62rem; font-weight: 800; line-height: 16px; text-align: center;
}
.topbar-date {
  font-size: .78rem; color: var(--text-muted);
  background: var(--bg);
  border: 1px solid var(--border);
  padding: 5px 12px; border-radius: 20px;
  font-family: var(--mono);
}

/* Content */
.page-content {
  padding: 28px;
  flex: 1;
}

/* ======================================================
   COMPOSANTS
====================================================== */

/* Cards */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.card-header {
  padding: 18px 22px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 12px;
}
.card-header h2 {
  font-size: .95rem; font-weight: 700; color: var(--text); flex: 1;
}
.card-body { padding: 22px; }

/* Stat Cards */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}
.stat-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 20px;
  display: flex; align-items: flex-start; gap: 14px;
  box-shadow: var(--shadow);
  transition: transform .2s, box-shadow .2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(13,50,120,.12); }
.stat-icon {
  width: 46px; height: 46px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; flex-shrink: 0;
}
.stat-icon.blue   { background: var(--blue-100); color: var(--blue-600); }
.stat-icon.green  { background: #dcfce7; color: #16a34a; }
.stat-icon.yellow { background: #fef3c7; color: #d97706; }
.stat-icon.red    { background: #fee2e2; color: #dc2626; }
.stat-icon.teal   { background: #ccfbf1; color: #0d9488; }
.stat-value { font-size: 1.7rem; font-weight: 800; color: var(--text); line-height: 1; }
.stat-label { font-size: .78rem; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

/* Table */
.table-wrap { overflow-x: auto; }
table.crict-table {
  width: 100%; border-collapse: collapse;
  font-size: .84rem;
}
.crict-table thead th {
  background: var(--bg);
  color: var(--text-muted);
  font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
  padding: 10px 16px;
  border-bottom: 2px solid var(--border);
  text-align: left; white-space: nowrap;
}
.crict-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .12s;
}
.crict-table tbody tr:hover { background: var(--blue-50); }
.crict-table tbody td { padding: 12px 16px; color: var(--text); vertical-align: middle; }

/* Buttons */
.btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 8px 16px; border-radius: 8px; font-size: .84rem; font-weight: 600;
  border: none; cursor: pointer; text-decoration: none;
  transition: all .15s; white-space: nowrap;
  font-family: var(--font);
}
.btn-primary { background: var(--blue-600); color: #fff; }
.btn-primary:hover { background: var(--blue-700); box-shadow: 0 4px 12px rgba(26,92,204,.3); }
.btn-secondary { background: var(--bg); color: var(--text); border: 1px solid var(--border); }
.btn-secondary:hover { border-color: var(--blue-400); color: var(--blue-600); }
.btn-success { background: var(--success); color: #fff; }
.btn-success:hover { background: #16a34a; }
.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { background: #dc2626; }
.btn-warn { background: var(--warn); color: #fff; }
.btn-sm { padding: 5px 11px; font-size: .78rem; border-radius: 6px; }
.btn-icon { padding: 7px; width: 34px; height: 34px; justify-content: center; }

/* Forms */
.form-group { margin-bottom: 18px; }
.form-label { display: block; font-size: .8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
.form-control {
  width: 100%;
  padding: 9px 14px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-size: .87rem; font-family: var(--font); color: var(--text);
  background: var(--surface);
  transition: border-color .15s, box-shadow .15s;
  outline: none;
}
.form-control:focus {
  border-color: var(--blue-500);
  box-shadow: 0 0 0 3px rgba(37,112,232,.12);
}
.form-control::placeholder { color: #aab8d0; }
select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236b7fa3'%3E%3Cpath d='M7 10l5 5 5-5H7z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; background-size: 20px; padding-right: 36px; }
textarea.form-control { resize: vertical; min-height: 80px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }

/* Alert / Flash */
.alert {
  padding: 12px 16px; border-radius: 8px; font-size: .85rem;
  margin-bottom: 18px; display: flex; align-items: flex-start; gap: 10px;
}
.alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.alert-info    { background: var(--blue-100); color: var(--blue-800); border: 1px solid var(--blue-200); }
.alert-warn    { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

/* Pagination */
.pagination {
  display: flex; align-items: center; gap: 4px;
  padding: 16px 22px; border-top: 1px solid var(--border);
}
.page-btn {
  width: 32px; height: 32px; border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--text-muted); text-decoration: none; font-size: .82rem;
  transition: all .12s;
}
.page-btn:hover, .page-btn.active {
  background: var(--blue-600); color: #fff; border-color: var(--blue-600);
}
.pagination-info { flex: 1; font-size: .78rem; color: var(--text-muted); text-align: right; }

/* Search bar */
.search-bar {
  display: flex; gap: 10px; align-items: center;
  padding: 16px 22px; border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}
.search-input-wrap {
  position: relative; flex: 1; min-width: 200px;
}
.search-input-wrap .fa-search {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  color: var(--text-muted); font-size: .8rem;
}
.search-input-wrap .form-control { padding-left: 34px; }

/* Modal */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(3,20,74,.55); backdrop-filter: blur(4px);
  z-index: 500; align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal {
  background: var(--surface); border-radius: 16px;
  width: 90%; max-width: 620px; max-height: 90vh;
  overflow-y: auto; box-shadow: 0 20px 60px rgba(3,20,74,.3);
  animation: modalIn .2s ease;
}
.modal-lg { max-width: 860px; }
@keyframes modalIn { from { opacity:0; transform: scale(.95) translateY(10px); } }
.modal-header {
  padding: 20px 24px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 12px;
}
.modal-header h3 { flex: 1; font-size: .97rem; font-weight: 700; }
.modal-close {
  width: 30px; height: 30px; border: none; background: var(--bg);
  border-radius: 7px; cursor: pointer; color: var(--text-muted);
  display: flex; align-items: center; justify-content: center;
  transition: all .15s;
}
.modal-close:hover { background: #fee2e2; color: var(--danger); }
.modal-body { padding: 24px; }
.modal-footer {
  padding: 16px 24px; border-top: 1px solid var(--border);
  display: flex; gap: 10px; justify-content: flex-end;
}

/* Avatar initiales */
.avatar-initials {
  display: inline-flex; align-items: center; justify-content: center;
  width: 34px; height: 34px; border-radius: 50%;
  background: var(--blue-100); color: var(--blue-700);
  font-size: .75rem; font-weight: 700;
}

/* ======================================================
   RESPONSIVE
====================================================== */
@media (max-width: 900px) {
  .sidebar { width: var(--sidebar-collapsed); }
  .main-wrap { margin-left: var(--sidebar-collapsed); }
  .sidebar-toggle { display: none; }
  .form-row, .form-row-3 { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<?php
$user   = user_courant();
$initiales = strtoupper(
    mb_substr($user['prenoms'] ?? 'U', 0, 1) .
    mb_substr($user['nom'] ?? 'S', 0, 1)
);
$modules_acces = charger_permissions_utilisateur();
$module_actif  = $module_actif ?? '';
?>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <!--<div class="logo-icon">CI</div>-->
    <div class="logo-text">
      <strong>CRICT</strong>
      <span style="color:#fff">C.Informatique & Cyber-Technologie</span>
    </div>
  </div>

  <div class="sidebar-user">
    <div class="user-avatar">
      <?php if (!empty($user['photo'])): ?>
        <img src="<?= e($user['photo']) ?>" alt="">
      <?php else: ?>
        <?= e($initiales) ?>
      <?php endif; ?>
    </div>
    <div class="user-info">
      <div class="name"><?= e($user['prenoms'] . ' ' . $user['nom']) ?></div>
      <div class="role"><?= e($user['groupe_libelle'] ?? '') ?></div>
    </div>
  </div>

  <nav class="sidebar-nav">

    <!-- ── PRINCIPAL ── -->
    <div class="nav-section-label">Principal</div>

    <a href="<?= APP_URL ?>/dashboard.php"
       class="nav-item <?= $module_actif === 'DASHBOARD' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-gauge-high"></i></span>
      <span class="label">Tableau de bord</span>
    </a>

    <?php if (in_array('SUIVI_QUOTIDIEN', $modules_acces)): ?>
    <?php
      $badge_valider = 0;
      if (a_permission('SUIVI_QUOTIDIEN','VALIDER') && function_exists('suivi_fiches_a_valider')) {
          $raw = suivi_fiches_a_valider();
          $badge_valider = is_array($raw) ? count($raw) : 0;
      }
    ?>
    <a href="<?= APP_URL ?>/modules/suivi/index.php"
       class="nav-item <?= $module_actif === 'SUIVI_QUOTIDIEN' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-calendar-check"></i></span>
      <span class="label">Rapport Quotidien</span>
      <?php if ($badge_valider > 0): ?>
        <span class="nav-badge"><?= $badge_valider ?></span>
      <?php endif; ?>
    </a>
    <?php endif; ?>
        <?php
      // Statistiques de suivi — réservées à la Direction (DG) et au Super Admin
      $voir_stats_suivi = in_array($user['groupe_code'] ?? '', ['SUPER_ADMIN','DG'], true)
                          && in_array('SUIVI_QUOTIDIEN', $modules_acces);
    ?>
    <?php if ($voir_stats_suivi): ?>
    <a href="<?= APP_URL ?>/modules/suivi/statistiques.php"
       class="nav-item <?= $module_actif === 'STATS_SUIVI' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-chart-pie"></i></span>
      <span class="label">Statistiques rapports</span>
    </a>
    <?php endif; ?>

    <?php if (in_array('PROJETS', $modules_acces)):
        $proj_actif = ($module_actif === 'PROJETS');
        $proj_sous  = $projet_sous_actif ?? ($proj_actif ? 'TACHES' : '');
    ?>
    <div class="nav-group <?= $proj_actif ? 'open' : '' ?>">
      <div class="nav-parent">
        <a href="<?= APP_URL ?>/modules/projets/index.php"
           class="nav-item <?= $proj_actif ? 'active' : '' ?>"
           onclick="if(!document.getElementById('sidebar').classList.contains('collapsed')){event.preventDefault();this.closest('.nav-group').classList.toggle('open');}">
          <span class="icon"><i class="fa-solid fa-diagram-project"></i></span>
          <span class="label">Projets</span>
        </a>
        <button type="button" class="nav-caret" aria-label="Afficher les sous-menus"
                onclick="this.closest('.nav-group').classList.toggle('open')">
          <i class="fa-solid fa-chevron-down"></i>
        </button>
      </div>
      <div class="nav-sub">
        <a href="<?= APP_URL ?>/modules/projets/index.php"
           class="nav-subitem <?= $proj_sous === 'TACHES' ? 'active' : '' ?>">
          <i class="fa-solid fa-list-check"></i> Projets & Tâches
        </a>
        <a href="<?= APP_URL ?>/modules/projet_recherche/index.php"
           class="nav-subitem <?= $proj_sous === 'RECHERCHE' ? 'active' : '' ?>">
          <i class="fa-solid fa-file-lines"></i> Projet de recherche
        </a>
        <a href="<?= APP_URL ?>/modules/projet_article/index.php"
           class="nav-subitem <?= $proj_sous === 'ARTICLE' ? 'active' : '' ?>">
          <i class="fa-solid fa-newspaper"></i> Article scientifique
        </a>
      </div>
    </div>
    <?php endif; ?>
    
    <?php if (in_array('INSTRUCTIONS', $modules_acces)): ?>
    <a href="<?= APP_URL ?>/modules/instructions/index.php"
       class="nav-item <?= $module_actif === 'INSTRUCTIONS' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-bullhorn"></i></span>
      <span class="label">Instructions</span>
    </a>
    <?php endif; ?>

    <?php if (in_array('IDENTITE_CRICT', $modules_acces)):
        $eval_actif = ($module_actif === 'IDENTITE_CRICT');
        $eval_sous  = $evaluation_sous_actif ?? ($eval_actif ? 'DGRS' : '');
    ?>
    <div class="nav-group <?= $eval_actif ? 'open' : '' ?>">
      <div class="nav-parent">
        <a href="<?= APP_URL ?>/modules/identite/index.php"
           class="nav-item <?= $eval_actif ? 'active' : '' ?>"
           onclick="if(!document.getElementById('sidebar').classList.contains('collapsed')){event.preventDefault();this.closest('.nav-group').classList.toggle('open');}">
          <span class="icon"><i class="fa-solid fa-landmark"></i></span>
          <span class="label">Évaluation</span>
        </a>
        <button type="button" class="nav-caret" aria-label="Afficher les sous-menus"
                onclick="this.closest('.nav-group').classList.toggle('open')">
          <i class="fa-solid fa-chevron-down"></i>
        </button>
      </div>
      <div class="nav-sub">
        <a href="<?= APP_URL ?>/modules/identite/index.php"
           class="nav-subitem <?= $eval_sous === 'DGRS' ? 'active' : '' ?>">
          <i class="fa-solid fa-building-columns"></i> DGRS
        </a>
        <a href="<?= APP_URL ?>/modules/identite/referentiel.php"
           class="nav-subitem <?= $eval_sous === 'AUTO_EVALUATION_ANAQ' ? 'active' : '' ?>">
          <i class="fa-solid fa-shield-halved"></i> Auto-évaluation (CIAQ)
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── ADMINISTRATION ── -->
    <?php if (in_array('UTILISATEURS', $modules_acces)): ?>
    <div class="nav-section-label">Administration</div>
    <a href="<?= APP_URL ?>/modules/utilisateurs/index.php"
       class="nav-item <?= $module_actif === 'UTILISATEURS' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-users"></i></span>
      <span class="label">Utilisateurs</span>
    </a>
    <?php endif; ?>

    <!-- ── FINANCES ── -->
    <?php
      $show_finances = in_array('MARCHES', $modules_acces)
                    || in_array('SUBVENTIONS', $modules_acces)
                    || in_array('FOURNISSEURS', $modules_acces);
    ?>
    <?php if ($show_finances): ?>
    <div class="nav-section-label">Finances</div>
    <?php endif; ?>

    <?php if (in_array('MARCHES', $modules_acces)): ?>
    <a href="<?= APP_URL ?>/modules/marches/index.php"
       class="nav-item <?= $module_actif === 'MARCHES' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-file-contract"></i></span>
      <span class="label">Marchés Publics</span>
    </a>
    <?php endif; ?>

    <?php if (in_array('SUBVENTIONS', $modules_acces)):
        $sub_actif    = ($module_actif === 'SUBVENTIONS');
        $sub_sous     = $subvention_sous_actif ?? ($sub_actif ? 'CAISSES' : '');
        $sub_peut_exec= a_permission('SUBVENTIONS', 'EXECUTER');
        $sub_peut_rap = a_permission('SUBVENTIONS', 'RAPPORT');
    ?>
    <div class="nav-group <?= $sub_actif ? 'open' : '' ?>">
      <div class="nav-parent">
        <a href="<?= APP_URL ?>/modules/subventions/index.php"
           class="nav-item <?= $sub_actif ? 'active' : '' ?>"
           onclick="if(!document.getElementById('sidebar').classList.contains('collapsed')){event.preventDefault();this.closest('.nav-group').classList.toggle('open');}">
          <span class="icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
          <span class="label">Subventions</span>
        </a>
        <button type="button" class="nav-caret" aria-label="Afficher les sous-menus"
                onclick="this.closest('.nav-group').classList.toggle('open')">
          <i class="fa-solid fa-chevron-down"></i>
        </button>
      </div>
      <div class="nav-sub">
        <a href="<?= APP_URL ?>/modules/subventions/index.php"
           class="nav-subitem <?= $sub_sous === 'CAISSES' ? 'active' : '' ?>">
          <i class="fa-solid fa-piggy-bank"></i> Caisses
        </a>
        <?php if ($sub_peut_exec): ?>
        <a href="<?= APP_URL ?>/modules/subventions/executer.php"
           class="nav-subitem <?= $sub_sous === 'EXECUTER' ? 'active' : '' ?>">
          <i class="fa-solid fa-money-bill-transfer"></i> Exécuter une dépense
        </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/modules/subventions/depenses.php"
           class="nav-subitem <?= $sub_sous === 'HISTORIQUE' ? 'active' : '' ?>">
          <i class="fa-solid fa-clock-rotate-left"></i> Historique
        </a>
        <?php if ($sub_peut_rap): ?>
        <a href="<?= APP_URL ?>/modules/subventions/rdb/index.php"
           class="nav-subitem <?= $sub_sous === 'RDB' ? 'active' : '' ?>">
          <i class="fa-solid fa-file-invoice"></i> RDB
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (in_array('FOURNISSEURS', $modules_acces)): ?>
    <a href="<?= APP_URL ?>/modules/fournisseurs/index.php"
       class="nav-item <?= $module_actif === 'FOURNISSEURS' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-truck"></i></span>
      <span class="label">Fournisseurs</span>
    </a>
    <?php endif; ?>

    <!-- ── DIRECTION / RDV DG ── -->
    <?php if (in_array('RENDEZVOUS_DG', $modules_acces)): ?>
    <div class="nav-section-label">Direction</div>
    <a href="<?= APP_URL ?>/modules/rendezvous_dg/index.php"
      class="nav-item <?= $module_actif === 'RENDEZVOUS_DG' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-calendar-check"></i></span>
      <span class="label">Rendez-vous DG</span>
    </a>
    <?php endif; ?>

    <!-- ── RH & LOGISTIQUE ── -->
    <?php if (in_array('PERSONNEL', $modules_acces)): ?>
    <div class="nav-section-label">Gestion RH</div>
    <a href="<?= APP_URL ?>/modules/personnel/index.php"
      class="nav-item <?= $module_actif === 'PERSONNEL' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-id-card"></i></span>
      <span class="label">Personnel</span>
    </a>
    <?php endif; ?>

    <?php if (user_id()): ?>
    <div class="nav-section-label">Mes demandes, profil RH</div>
    <?php
      // Badge comme « Rapport Quotidien » : nombre de demandes en attente (côté DG),
      // sinon notifications non lues (retour DG → demandeur).
      $dm_nav_n = 0;
      if (is_file(APP_ROOT . '/models/demandes.php')) {
          require_once APP_ROOT . '/models/personnel.php';
          require_once APP_ROOT . '/models/demandes.php';
          try {
              $dm_uid = (int)user_id();
              $dm_pid = personnel_assurer_profil($dm_uid);
              $dm_peut_rh = a_permission('PERSONNEL', 'VALIDER');
              $dm_est_dg  = demandes_est_dg();
              $dm_nav_n   = demandes_compte_a_valider($dm_pid, $dm_peut_rh, $dm_est_dg);
              if ($dm_nav_n <= 0) {
                  $dm_nav_n = demandes_notifs_non_lues($dm_uid);
              }
          } catch (Throwable $e) {
              $dm_nav_n = 0;
          }
      }
    ?>
    <a href="<?= APP_URL ?>/modules/demandes/demandes.php<?= $dm_nav_n > 0 ? '?voir_alerte=1' : '' ?>"
       class="nav-item <?= ($module_actif ?? '') === 'PERSONNEL_DEMANDES' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-inbox"></i></span>
      <span class="label">Mes demandes</span>
      <?php if ($dm_nav_n > 0): ?>
        <span class="nav-badge"><?= (int)$dm_nav_n ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/profil.php"
       class="nav-item">
      <span class="icon"><i class="fa-solid fa-user-gear"></i></span>
      <span class="label">Mon profil</span>
    </a>
    <?php endif; ?>
    
   <?php
      $show_documents = in_array('GED', $modules_acces)
                     || in_array('COURRIERS', $modules_acces);
    ?>
    <?php if ($show_documents): ?>
    <div class="nav-section-label">Documents</div>

      <?php if (in_array('GED', $modules_acces)): ?>
      <a href="<?= APP_URL ?>/modules/ged/index.php"
         class="nav-item <?= $module_actif === 'GED' ? 'active' : '' ?>">
        <span class="icon"><i class="fa-solid fa-folder-tree"></i></span>
        <span class="label">Documents (GED)</span>
      </a>
      <?php endif; ?>

      <?php if (in_array('COURRIERS', $modules_acces)): ?>
      <a href="<?= APP_URL ?>/modules/courriers/index.php"
         class="nav-item <?= $module_actif === 'COURRIERS' ? 'active' : '' ?>">
        <span class="icon"><i class="fa-solid fa-envelopes-bulk"></i></span>
        <span class="label">Courriers</span>
      </a>
      <?php endif; ?>

    <?php endif; ?>

    <!-- ── ANALYTIQUE ── --
    <?php if (in_array('RAPPORTS', $modules_acces)): ?>
    <div class="nav-section-label">Analytique</div>
    <a href="#" class="nav-item" style="opacity:.45;pointer-events:none;">
      <span class="icon"><i class="fa-solid fa-chart-bar"></i></span>
      <span class="label">Rapports</span>
      <span class="nav-badge" style="background:var(--warn);color:#fff;">Bientôt</span>
    </a>
    <?php endif; ?>
    -->



    <!-- ── SYSTÈME ── -->
    <?php if (a_permission('PARAMETRES','VOIR')): ?>
    <a href="<?= APP_URL ?>/modules/parametres/index.php"
      class="nav-item <?= $module_actif === 'PARAMETRES' ? 'active' : '' ?>">
      <span class="icon"><i class="fa-solid fa-gear"></i></span>
      <span class="label">Paramètres</span>
    </a>
    <?php endif; ?>
   


  </nav>

  <div class="sidebar-footer">
    <a href="<?= APP_URL ?>/logout.php">
      <i class="fa-solid fa-right-from-bracket"></i>
      <span>Déconnexion</span>
    </a>
  </div>
</aside>

<button class="sidebar-toggle" id="sidebarToggle" title="Réduire le menu">
  <i class="fa-solid fa-chevron-left" id="toggleIcon"></i>
</button>

<!-- MAIN -->
<div class="main-wrap" id="mainWrap">
  <header class="topbar">
    <div class="topbar-breadcrumb">
      <h1><?= e($page_titre ?? 'Tableau de bord') ?></h1>
      <?php if (!empty($page_breadcrumb)): ?>
        <div class="crumb"><?= $page_breadcrumb ?></div>
      <?php endif; ?>
    </div>
    <div class="topbar-actions">
      <div class="topbar-date">
        <i class="fa-regular fa-calendar"></i>
        <?= strftime_fr(date('Y-m-d')) ?>
      </div>
      <a href="<?= APP_URL ?>/modules/suivi/index.php" class="topbar-btn" title="Ma fiche du jour">
        <i class="fa-solid fa-calendar-check"></i>
      </a>
      <a href="<?= APP_URL ?>/profil.php" class="topbar-btn" title="Mon profil">
        <i class="fa-solid fa-user-circle"></i>
      </a>
    </div>
  </header>

  <!-- FLASH MESSAGES -->
  <?php foreach (get_flash() as $flash): ?>
    <div style="padding: 0 28px; margin-top: 16px;">
      <div class="alert alert-<?= e($flash['type']) ?>">
        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
        <?= e($flash['message']) ?>
      </div>
    </div>
  <?php endforeach; ?>

  <main class="page-content">