<?php
// modules/rendezvous_dg/disponibilites.php — Gestion planning DG
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/rendezvous_dg.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission(RDV_DG_MODULE, 'VOIR');
rdv_dg_assurer_schema();

if (!rdv_dg_peut_gerer()) {
    set_flash('error', 'Accès réservé au DG et aux personnes habilitées.');
    redirect('/modules/rendezvous_dg/index.php');
}

$dispos = rdv_dg_dispo_lister(false);
$exceptions = rdv_dg_exceptions_lister();
$semaine = rdv_dg_creneaux_semaine(null, 1);
$edit_id = (int)($_GET['edit'] ?? 0);
$bloquer_id = (int)($_GET['bloquer'] ?? 0);
$edit_dispo = $edit_id > 0 ? rdv_dg_dispo_par_id($edit_id) : null;
$bloquer_dispo = $bloquer_id > 0 ? rdv_dg_dispo_par_id($bloquer_id) : null;
if ($edit_id > 0 && !$edit_dispo) {
    set_flash('error', 'Créneau introuvable.');
    redirect('/modules/rendezvous_dg/disponibilites.php');
}
if ($bloquer_id > 0) {
    if (!$bloquer_dispo || $bloquer_dispo['type_dispo'] !== 'RECURRENT' || !(int)$bloquer_dispo['actif']) {
        set_flash('error', 'Ce créneau ne peut pas être bloqué sur une date.');
        redirect('/modules/rendezvous_dg/disponibilites.php');
    }
}

$page_titre = 'Disponibilités DG';
$page_breadcrumb = 'Rendez-vous DG · Disponibilités';
$module_actif = 'RENDEZVOUS_DG';

include __DIR__ . '/../../views/layout/header.php';
rdv_dg_ui_styles();
?>

<div class="rdv-wrap">
  <div class="rdv-toolbar">
    <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Demandes</a>
    <div class="spacer"></div>
  </div>

  <div class="rdv-card">
    <h3><i class="fa-solid fa-calendar-week" style="color:var(--blue-500);"></i> Aperçu — créneaux de la semaine</h3>
    <p class="rdv-hint" style="margin-bottom:12px;">Passés, déjà pris ou bloqués apparaissent grisés.</p>
    <?php rdv_dg_render_calendrier($semaine, 'disponibilites.php', null, false); ?>
  </div>

  <div class="rdv-card" style="max-width:520px;">
      <h3><i class="fa-solid fa-rotate" style="color:var(--blue-500);"></i> Ajouter un créneau récurrent</h3>
      <p class="rdv-hint">Ex. tous les lundis de 14h00 à 14h30.</p>
      <form method="post" action="actions.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="dispo_ajouter">
        <input type="hidden" name="type_dispo" value="RECURRENT">
        <div class="form-group">
          <label class="form-label">Jour *</label>
          <select name="jour_semaine" class="form-control" required>
            <?php foreach (RDV_DG_JOURS as $n => $lbl): if ($n > 5) continue; ?>
            <option value="<?= $n ?>"><?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="rdv-grid2">
          <div class="form-group">
            <label class="form-label">Début *</label>
            <input type="time" name="heure_debut" class="form-control" required value="14:00" step="1800">
          </div>
          <div class="form-group">
            <label class="form-label">Fin *</label>
            <input type="time" name="heure_fin" class="form-control" required value="14:30" step="1800">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Note (facultatif)</label>
          <input type="text" name="note" class="form-control" maxlength="255">
        </div>
        <button type="submit" class="btn btn-primary">Ajouter le créneau hebdomadaire</button>
      </form>
  </div>

  <div class="rdv-card">
    <h3>Créneaux configurés</h3>
    <?php if (!$dispos): ?>
      <div class="rdv-empty">Aucun créneau. Ajoutez des disponibilités ci-dessus.</div>
    <?php else: ?>
    <table class="rdv-table">
      <thead>
        <tr><th>Type</th><th>Quand</th><th>Horaire</th><th>Statut</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($dispos as $d):
          $did = (int)$d['id'];
          $en_edit = $edit_id === $did;
          $en_bloquer = $bloquer_id === $did;
          $row_open = $en_edit || $en_bloquer;
      ?>
        <tr<?= $row_open ? ' style="background:var(--blue-50,#eef4ff);"' : '' ?>>
          <td><?= $d['type_dispo'] === 'RECURRENT' ? 'Récurrent' : 'Ponctuel' ?></td>
          <td>
            <?php if ($d['type_dispo'] === 'RECURRENT'): ?>
              Chaque <?= e(RDV_DG_JOURS[(int)$d['jour_semaine']] ?? '') ?>
            <?php else: ?>
              <?= e(rdv_dg_fmt_date($d['date_ponctuelle'])) ?>
            <?php endif; ?>
            <?php if ($d['note']): ?><div class="rdv-meta"><?= e($d['note']) ?></div><?php endif; ?>
          </td>
          <td><?= e(rdv_dg_fmt_heure($d['heure_debut'])) ?> – <?= e(rdv_dg_fmt_heure($d['heure_fin'])) ?></td>
          <td><?= (int)$d['actif'] ? '<span class="rdv-badge CONFIRME">Actif</span>' : '<span class="rdv-badge ANNULE">Suspendu</span>' ?></td>
          <td>
            <div class="rdv-actions-row">
              <?php if ($en_edit): ?>
              <a href="disponibilites.php" class="btn btn-secondary btn-sm">Fermer</a>
              <?php elseif (!$en_bloquer): ?>
              <a href="disponibilites.php?edit=<?= $did ?>" class="btn btn-secondary btn-icon btn-sm" title="Modifier">
                <i class="fa-solid fa-eye"></i>
              </a>
              <?php endif; ?>

              <?php if ($d['type_dispo'] === 'RECURRENT' && (int)$d['actif']): ?>
                <?php if ($en_bloquer): ?>
                <a href="disponibilites.php" class="btn btn-warning btn-sm">Fermer</a>
                <?php elseif (!$en_edit): ?>
                <a href="disponibilites.php?bloquer=<?= $did ?>" class="btn btn-warning btn-sm" title="Bloquer une date précise">Bloquer</a>
                <?php endif; ?>
              <?php endif; ?>

              <?php if (!$en_edit && !$en_bloquer): ?>
              <form method="post" action="actions.php" style="display:inline;" onsubmit="return confirm('Supprimer définitivement ce créneau ?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="dispo_supprimer">
                <input type="hidden" name="id" value="<?= $did ?>">
                <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
              </form>
              <?php endif; ?>
            </div>

            <?php if ($en_bloquer):
                $occurrences = rdv_dg_prochaines_occurrences($d, 12);
            ?>
            <div class="rdv-edit-panel rdv-block-panel">
              <?php if (!$occurrences): ?>
              <p class="rdv-block-hint" style="margin:0;">Aucune occurrence disponible à bloquer (créneau passé ou dates déjà bloquées).</p>
              <?php else: ?>
              <form method="post" action="actions.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="dispo_exception">
                <input type="hidden" name="dispo_id" value="<?= $did ?>">
                <div class="form-group">
                  <label class="form-label">Jour</label>
                  <input type="text" class="form-control" value="<?= e(RDV_DG_JOURS[(int)$d['jour_semaine']] ?? '') ?>" readonly disabled>
                </div>
                <div class="rdv-grid2">
                  <div class="form-group">
                    <label class="form-label">Début</label>
                    <input type="time" class="form-control" value="<?= e(substr($d['heure_debut'], 0, 5)) ?>" disabled readonly>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Fin</label>
                    <input type="time" class="form-control" value="<?= e(substr($d['heure_fin'], 0, 5)) ?>" disabled readonly>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Date à bloquer *</label>
                  <select name="date_exception" class="form-control" required>
                    <?php foreach ($occurrences as $i => $occ): ?>
                    <option value="<?= e($occ['date']) ?>"<?= $i === 0 ? ' selected' : '' ?>><?= e(date('d/m/Y', strtotime($occ['date']))) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="rdv-hint" style="margin-top:6px;">Prochaines occurrences de ce créneau, une par semaine.</p>
                </div>
                <div class="form-group">
                  <label class="form-label">Motif (facultatif)</label>
                  <input type="text" name="motif" class="form-control" maxlength="255" placeholder="Ex. déplacement, indisponibilité…">
                </div>
                <button type="submit" class="btn btn-warning btn-sm"><i class="fa-solid fa-ban"></i> Enregistrer le blocage</button>
                <a href="disponibilites.php" class="btn btn-secondary btn-sm" style="margin-left:6px;">Annuler</a>
              </form>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($en_edit): ?>
            <div class="rdv-edit-panel">
              <form method="post" action="actions.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="dispo_modifier">
                <input type="hidden" name="id" value="<?= $did ?>">
                <?php if ($d['type_dispo'] === 'RECURRENT'): ?>
                <input type="hidden" name="jour_semaine" value="<?= (int)$d['jour_semaine'] ?>">
                <div class="form-group">
                  <label class="form-label">Jour</label>
                  <input type="text" class="form-control" value="<?= e(RDV_DG_JOURS[(int)$d['jour_semaine']] ?? '') ?>" readonly disabled>
                </div>
                <?php else: ?>
                <p class="rdv-hint">Créneau ponctuel (ancien) : préférez le supprimer. Seuls les créneaux récurrents sont modifiables.</p>
                <?php endif; ?>
                <?php if ($d['type_dispo'] === 'RECURRENT'): ?>
                <div class="rdv-grid2">
                  <div class="form-group">
                    <label class="form-label">Début *</label>
                    <input type="time" name="heure_debut" class="form-control" required
                           value="<?= e(substr($d['heure_debut'], 0, 5)) ?>" step="1800">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Fin *</label>
                    <input type="time" name="heure_fin" class="form-control" required
                           value="<?= e(substr($d['heure_fin'], 0, 5)) ?>" step="1800">
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Note (facultatif)</label>
                  <input type="text" name="note" class="form-control" maxlength="255" value="<?= e($d['note'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Enregistrer</button>
                <?php endif; ?>
              </form>
            </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <?php if ($exceptions): ?>
  <div class="rdv-card">
    <h3>Exceptions (créneaux récurrents bloqués)</h3>
    <table class="rdv-table">
      <thead><tr><th>Date</th><th>Créneau</th><th>Motif</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($exceptions as $ex): ?>
        <tr>
          <td><?= e(rdv_dg_fmt_date($ex['date_exception'])) ?></td>
          <td><?= e(RDV_DG_JOURS[(int)$ex['jour_semaine']] ?? '') ?>
            <?= e(rdv_dg_fmt_heure($ex['heure_debut'])) ?>–<?= e(rdv_dg_fmt_heure($ex['heure_fin'])) ?></td>
          <td><?= e($ex['motif'] ?: '—') ?></td>
          <td>
            <form method="post" action="actions.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="dispo_exception_suppr">
              <input type="hidden" name="id" value="<?= (int)$ex['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm">Rétablir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php';
