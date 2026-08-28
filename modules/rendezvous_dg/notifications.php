<?php
// modules/rendezvous_dg/notifications.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/rendezvous_dg.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_connexion();
rdv_dg_assurer_schema();

$uid = (int)user_id();
if (isset($_GET['lu'])) {
    rdv_dg_notif_marquer_lu((int)$_GET['lu'], $uid);
    redirect('/modules/rendezvous_dg/notifications.php');
}

$notifs = rdv_dg_notifs_lister($uid, 40);
$non_lues = 0;
foreach ($notifs as $n) {
    if (!(int)($n['lu'] ?? 0)) $non_lues++;
}

$page_titre = 'Notifications RDV';
$page_breadcrumb = 'Rendez-vous DG · Notifications';
$module_actif = 'RENDEZVOUS_DG';

include __DIR__ . '/../../views/layout/header.php';
rdv_dg_ui_styles();
?>

<div class="rdv-notif-shell">
  <div class="rdv-notif-hero">
    <h2>Notifications rendez-vous</h2>
    <p>Suivez les nouvelles demandes, les validations et les rappels du module dans une vue claire et professionnelle.</p>
  </div>

  <div class="rdv-notif-stats">
    <div class="rdv-notif-stat">
      <span class="k">Total</span>
      <span class="v"><?= count($notifs) ?></span>
    </div>
    <div class="rdv-notif-stat">
      <span class="k">Non lues</span>
      <span class="v"><?= (int)$non_lues ?></span>
    </div>
    <div class="rdv-notif-stat">
      <span class="k">Lues</span>
      <span class="v"><?= max(count($notifs) - $non_lues, 0) ?></span>
    </div>
  </div>

  <div class="rdv-toolbar rdv-notif-toolbar">
    <a href="index.php" class="btn btn-secondary">Retour</a>
    <div class="spacer"></div>
    <form method="post" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="notif_tout_lu">
      <button type="submit" class="btn btn-secondary btn-sm"<?= $non_lues === 0 ? ' disabled' : '' ?>>Tout marquer comme lu</button>
    </form>
  </div>

  <div class="rdv-notif-panel">
    <?php if (!$notifs): ?>
      <div class="rdv-empty">Aucune notification.</div>
    <?php else: foreach ($notifs as $n): ?>
      <div class="rdv-notif-item<?= !(int)$n['lu'] ? ' unread' : '' ?>">
        <div class="rdv-notif-head">
          <div class="rdv-notif-title">
            <span class="rdv-notif-dot"></span>
            <div>
              <?php if (!(int)$n['lu']): ?><div class="rdv-notif-tag">Nouveau</div><?php endif; ?>
              <strong><?= e($n['titre']) ?></strong>
            </div>
          </div>
          <span class="rdv-notif-date"><?= e(format_datetime($n['cree_le'])) ?></span>
        </div>
        <div class="rdv-notif-msg"><?= e($n['message']) ?></div>
        <div class="rdv-notif-actions">
          <?php if (!empty($n['rendezvous_id'])): ?>
          <a href="voir.php?id=<?= (int)$n['rendezvous_id'] ?>&notif=<?= (int)$n['id'] ?>" class="btn btn-secondary btn-sm">Voir</a>
          <?php endif; ?>
          <?php if (!(int)$n['lu']): ?>
          <a href="?lu=<?= (int)$n['id'] ?>" class="btn btn-secondary btn-sm">Marquer lu</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php';
