<?php
// modules/rendezvous_dg/voir.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/rendezvous_dg.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission(RDV_DG_MODULE, 'VOIR');
rdv_dg_assurer_schema();

$id = (int)($_GET['id'] ?? 0);
$r = rdv_dg_par_id($id);
if (!$r || !rdv_dg_peut_voir($r)) {
    set_flash('error', 'Demande introuvable ou accès refusé.');
    redirect('/modules/rendezvous_dg/index.php');
}

$uid = (int)user_id();
$notif_id = (int)($_GET['notif'] ?? 0);
if ($notif_id > 0 && $uid > 0) {
    rdv_dg_notif_marquer_lu($notif_id, $uid);
}

$peut_gerer = rdv_dg_peut_gerer();
$est_auteur = $uid > 0 && (int)($r['demandeur_utilisateur_id'] ?? 0) === $uid;

$nom_complet = trim(($r['prenoms'] ?? '') . ' ' . ($r['nom'] ?? ''));
$initiales = '';
foreach (preg_split('/\s+/', $nom_complet) as $part) {
    if ($part !== '') $initiales .= mb_strtoupper(mb_substr($part, 0, 1));
    if (mb_strlen($initiales) >= 2) break;
}
if ($initiales === '') $initiales = 'RD';

$page_titre = $r['reference'];
$page_breadcrumb = 'Rendez-vous DG · Détail';
$module_actif = 'RENDEZVOUS_DG';

include __DIR__ . '/../../views/layout/header.php';
rdv_dg_ui_styles();
?>

<div class="rdv-detail-shell">
  <div class="rdv-toolbar" style="margin-bottom:14px;">
    <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>
  </div>

  <div class="rdv-detail-hero">
    <div class="rdv-detail-hero-top">
      <div>
        <div class="rdv-detail-ref"><?= e($r['reference']) ?></div>
        <h2><?= e(rdv_dg_libelle_type($r)) ?></h2>
        <p>
          <?= e($nom_complet) ?>
          <?php if (!empty($r['organisation'])): ?> · <?= e($r['organisation']) ?><?php endif; ?>
          — déposée le <?= e(format_datetime($r['cree_le'] ?? '')) ?>
        </p>
      </div>
      <div class="rdv-detail-hero-actions">
        <?= rdv_dg_badge($r['statut']) ?>
      </div>
    </div>
  </div>

  <div class="rdv-detail-grid">
    <div class="rdv-detail-card">
      <h3><span class="ic"><i class="fa-solid fa-clock"></i></span> Créneau réservé</h3>
      <div class="rdv-detail-rows">
        <div class="rdv-detail-row">
          <div class="lbl">Date</div>
          <div class="val"><?= e(rdv_dg_fmt_date($r['date_confirmee'])) ?></div>
        </div>
        <div class="rdv-detail-row">
          <div class="lbl">Heure</div>
          <div class="val">
            <?= e(rdv_dg_fmt_heure($r['heure_debut'])) ?> – <?= e(rdv_dg_fmt_heure($r['heure_fin'])) ?>
            <span class="muted"> · <?= (int)$r['duree_minutes'] ?> min</span>
          </div>
        </div>
        <div class="rdv-detail-row">
          <div class="lbl">Lieu</div>
          <div class="val"><?= e($r['lieu'] ?: 'CRICT — Direction Générale, Conakry') ?></div>
        </div>
      </div>
      <?php if ($r['statut'] === 'EN_ATTENTE'): ?>
      <div class="rdv-detail-hint">
        <i class="fa-solid fa-lock"></i>
        Ce créneau est bloqué pour les autres utilisateurs tant que la demande n’est pas refusée ou annulée.
      </div>
      <?php endif; ?>
    </div>

    <div class="rdv-detail-card">
      <h3><span class="ic"><i class="fa-solid fa-user"></i></span> Demandeur</h3>
      <div class="rdv-detail-person">
        <div class="rdv-detail-avatar"><?= e($initiales) ?></div>
        <div class="rdv-detail-person-main">
          <strong><?= e($nom_complet) ?></strong>
          <div class="meta">
            <?= e($r['fonction'] ?: '—') ?>
            <?php if (!empty($r['organisation'])): ?> · <?= e($r['organisation']) ?><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="rdv-detail-chips">
        <span class="rdv-detail-chip"><i class="fa-regular fa-envelope"></i> <?= e($r['email']) ?></span>
        <?php if (!empty($r['telephone'])): ?>
        <span class="rdv-detail-chip"><i class="fa-solid fa-phone"></i> <?= e($r['telephone']) ?></span>
        <?php endif; ?>
        <span class="rdv-detail-chip">
          <i class="fa-solid fa-building-user"></i>
          <?= $r['source'] === 'PUBLIC' ? 'Externe' : 'Personnel CRICT' ?>
        </span>
      </div>
    </div>
  </div>

  <div class="rdv-detail-card" style="margin-bottom:16px;">
    <h3><span class="ic"><i class="fa-solid fa-clipboard-list"></i></span> Demande</h3>
    <div class="rdv-detail-rows">
      <div class="rdv-detail-row">
        <div class="lbl">Type</div>
        <div class="val"><?= e(rdv_dg_libelle_type($r)) ?></div>
      </div>
      <div class="rdv-detail-row">
        <div class="lbl">Objet</div>
        <div class="val">
          <div class="rdv-detail-objet"><?= nl2br(e($r['objet'])) ?></div>
        </div>
      </div>
      <?php if (!empty($r['note_demandeur'])): ?>
      <div class="rdv-detail-row">
        <div class="lbl">Note</div>
        <div class="val muted"><?= nl2br(e($r['note_demandeur'])) ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php if (!empty($r['piece_jointe'])): ?>
    <div class="rdv-detail-chips">
      <a href="<?= e(rtrim(APP_URL, '/') . '/' . ltrim($r['piece_jointe'], '/')) ?>" target="_blank" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-paperclip"></i> Pièce jointe
      </a>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($r['statut'] === 'REFUSE' && $r['motif_refus']): ?>
  <div class="rdv-detail-card" style="margin-bottom:16px;border-color:#fecaca;background:#fffafa;">
    <h3><span class="ic" style="background:#fee2e2;color:#991b1b;"><i class="fa-solid fa-ban"></i></span> Motif du refus</h3>
    <div class="rdv-detail-objet" style="background:#fff;border-color:#fecaca;"><?= nl2br(e($r['motif_refus'])) ?></div>
  </div>
  <?php endif; ?>

  <?php if ($r['statut'] === 'ANNULE' && !empty($r['motif_annulation'])): ?>
  <div class="rdv-detail-card" style="margin-bottom:16px;border-color:#e2e8f0;background:#f8fafc;">
    <h3><span class="ic" style="background:#e2e8f0;color:#475569;"><i class="fa-solid fa-circle-xmark"></i></span> Motif de l'annulation</h3>
    <div class="rdv-detail-objet" style="background:#fff;border-color:#e2e8f0;"><?= nl2br(e($r['motif_annulation'])) ?></div>
  </div>
  <?php endif; ?>

  <?php if ($peut_gerer && $r['statut'] === 'EN_ATTENTE'): ?>
  <div class="rdv-detail-treat">
    <h3><span class="ic" style="width:34px;height:34px;border-radius:10px;background:#dbeafe;color:#1d4ed8;display:inline-flex;align-items:center;justify-content:center;"><i class="fa-solid fa-check-double"></i></span> Traiter la demande</h3>
    <div class="rdv-split">
      <div class="rdv-detail-treat-box">
        <form method="post" action="actions.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="confirmer">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <div class="form-group">
            <label class="form-label">Lieu</label>
            <input type="text" name="lieu" class="form-control" value="<?= e($r['lieu'] ?: 'CRICT — Direction Générale, Conakry') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Commentaire (facultatif)</label>
            <textarea name="commentaire_gestion" class="form-control" rows="2"></textarea>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Confirmer</button>
        </form>
      </div>
      <div class="rdv-detail-treat-box refuse">
        <form method="post" action="actions.php" onsubmit="return confirm('Refuser et libérer le créneau ?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="refuser">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <div class="form-group">
            <label class="form-label">Motif du refus *</label>
            <textarea name="motif_refus" class="form-control" rows="2" required></textarea>
          </div>
          <button type="submit" class="btn btn-secondary" style="color:#991b1b;border-color:#fecaca;">
            <i class="fa-solid fa-xmark"></i> Refuser
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (in_array($r['statut'], ['EN_ATTENTE', 'CONFIRME'], true) && ($peut_gerer || ($est_auteur && $r['statut'] === 'EN_ATTENTE'))): ?>
  <div class="rdv-detail-footer-actions" style="flex-direction:column;align-items:stretch;gap:12px;">
    <div class="rdv-meta" style="margin:0;">Annuler libère immédiatement le créneau pour d’autres demandes.</div>
    <form method="post" action="actions.php" onsubmit="return confirm('Annuler et libérer le créneau ?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="annuler">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <div class="form-group" style="margin-bottom:10px;">
        <label class="form-label">Motif de l'annulation *</label>
        <textarea name="motif" class="form-control" rows="2" required placeholder="Ex. indisponibilité du DG, report, demande du demandeur…"></textarea>
      </div>
      <button type="submit" class="btn btn-secondary">Annuler le rendez-vous</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php';
