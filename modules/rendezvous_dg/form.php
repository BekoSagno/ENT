<?php
// modules/rendezvous_dg/form.php — Personnel : choisir un créneau puis renseigner
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/personnel.php';
require_once __DIR__ . '/../../models/rendezvous_dg.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission(RDV_DG_MODULE, 'CREER');
rdv_dg_assurer_schema();
rdv_dg_marquer_termines();

$u = user_courant();
$semaine_param = clean($_GET['semaine'] ?? '');
$semaine = rdv_dg_creneaux_semaine(null, 1);

$dispo_id = (int)($_GET['dispo'] ?? 0);
$date = clean($_GET['date'] ?? '');
$heure = clean($_GET['heure'] ?? '');
$creneau_ok = $dispo_id > 0 && $date !== '' && $heure !== ''
    && rdv_dg_creneau_est_libre($dispo_id, $date, $heure);
$selected_key = $creneau_ok ? ($dispo_id . '|' . $date . '|' . substr($heure, 0, 5)) : null;

$page_titre = 'Nouvelle demande';
$page_breadcrumb = 'Rendez-vous DG · Nouvelle demande';
$module_actif = 'RENDEZVOUS_DG';

include __DIR__ . '/../../views/layout/header.php';
rdv_dg_ui_styles();
?>

<div class="rdv-wrap" style="max-width:960px;">
  <div class="rdv-toolbar">
    <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>
  </div>

  <div class="rdv-card">
    <h3><i class="fa-solid fa-clock" style="color:var(--blue-500);"></i> 1. Choisissez un créneau disponible</h3>
    <p class="rdv-hint" style="margin-bottom:12px;">Semaine en cours : les créneaux passés ou déjà pris sont grisés et non sélectionnables.</p>
    <?php if (empty($semaine['tous'])): ?>
      <div class="rdv-empty">Aucun créneau programmé cette semaine. Revenez plus tard ou contactez l’assistante du DG.</div>
    <?php else: ?>
      <?php rdv_dg_render_calendrier($semaine, 'form.php', $selected_key); ?>
    <?php endif; ?>
  </div>

  <?php if ($creneau_ok):
    $dispo = rdv_dg_dispo_par_id($dispo_id);
  ?>
  <div class="rdv-card">
    <h3><i class="fa-solid fa-user-pen" style="color:var(--blue-500);"></i> 2. Vos informations</h3>
    <div class="rdv-creneau-box">
      <span class="ic"><i class="fa-solid fa-calendar-check"></i></span>
      <div>
        <strong><?= e(rdv_dg_fmt_date($date)) ?></strong><br>
        <?= e(rdv_dg_fmt_heure($heure)) ?> – <?= e(rdv_dg_fmt_heure($dispo['heure_fin'] ?? '')) ?>
        · Direction Générale CRICT
      </div>
    </div>

    <form method="post" action="actions.php" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="creer">
      <input type="hidden" name="disponibilite_id" value="<?= (int)$dispo_id ?>">
      <input type="hidden" name="date_confirmee" value="<?= e($date) ?>">
      <input type="hidden" name="heure_debut" value="<?= e(substr($heure, 0, 5)) ?>">

      <div class="rdv-grid2">
        <div class="form-group">
          <label class="form-label">Nom *</label>
          <input type="text" name="nom" class="form-control" required value="<?= e($u['nom'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Prénoms *</label>
          <input type="text" name="prenoms" class="form-control" required value="<?= e($u['prenoms'] ?? '') ?>">
        </div>
      </div>
      <div class="rdv-grid2">
        <div class="form-group">
          <label class="form-label">E-mail *</label>
          <input type="email" name="email" class="form-control" required value="<?= e($u['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone</label>
          <input type="text" name="telephone" class="form-control" value="<?= e($u['telephone'] ?? '') ?>">
        </div>
      </div>
      <div class="rdv-grid2">
        <div class="form-group">
          <label class="form-label">Organisation</label>
          <input type="text" name="organisation" class="form-control" value="CRICT">
        </div>
        <div class="form-group">
          <label class="form-label">Fonction</label>
          <input type="text" name="fonction" class="form-control" value="<?= e($u['groupe_libelle'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Type *</label>
        <select name="type_rdv" id="type_rdv" class="form-control" required>
          <?php foreach (RDV_DG_TYPES as $k => $l): ?>
          <option value="<?= e($k) ?>"><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" id="type_autre_wrap" style="display:none;">
        <label class="form-label">Précisez le type *</label>
        <input type="text" name="type_autre" id="type_autre" class="form-control" maxlength="150">
      </div>
      <div class="form-group">
        <label class="form-label">Objet *</label>
        <textarea name="objet" class="form-control" rows="3" required></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Note / précisions</label>
        <textarea name="note_demandeur" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Pièce jointe</label>
        <input type="file" name="piece_jointe" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
      </div>
      <div class="rdv-actions">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Réserver ce créneau</button>
        <a href="form.php<?= $semaine_param ? '?semaine=' . e($semaine_param) : '' ?>" class="btn btn-secondary">Changer de créneau</a>
      </div>
    </form>
  </div>
  <?php elseif ($dispo_id > 0): ?>
  <div class="rdv-card">
    <div class="alert alert-error" style="margin:0;">Ce créneau n’est plus disponible. Choisissez-en un autre ci-dessus.</div>
  </div>
  <?php endif; ?>
</div>

<script>
(function(){
  var sel=document.getElementById('type_rdv'); if(!sel)return;
  var wrap=document.getElementById('type_autre_wrap');
  var inp=document.getElementById('type_autre');
  function sync(){var a=sel.value==='AUTRE';wrap.style.display=a?'block':'none';inp.required=a;if(!a)inp.value='';}
  sel.addEventListener('change',sync);sync();
})();
</script>

<?php include __DIR__ . '/../../views/layout/footer.php';
