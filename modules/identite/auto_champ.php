<?php

// modules/identite/auto_champ.php — Thème ANAQ (liste des références)

require_once __DIR__ . '/../../config/config.php';

require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../includes/helpers.php';

require_once __DIR__ . '/../../includes/auth.php';

require_once __DIR__ . '/../../models/identite.php';

require_once __DIR__ . '/../../models/auto_anaq.php';

require_once __DIR__ . '/_ui.php';



session_demarrer();

exiger_permission('IDENTITE_CRICT', 'VOIR');



auto_anaq_assurer_donnees();



$champ_id = (int)($_GET['id'] ?? 0);

$champ    = auto_anaq_champ_par_id($champ_id);

if (!$champ) {

    set_flash('error', 'Thème introuvable.');

    redirect('/modules/identite/referentiel.php');

}



$peut_modifier = identite_peut_modifier();

$mode_edit     = $peut_modifier && !empty($_GET['edit']);

$references    = auto_anaq_references_par_champ($champ_id);

$swot          = auto_anaq_swot_lire($champ_id);

$comp          = auto_anaq_completude_champ($champ_id);

$edit_q        = auto_anaq_query_edit($mode_edit);



$ref_anaq              = identite_referentiel('AUTO_EVALUATION_ANAQ');

$page_titre            = $champ['libelle_court'];

$page_breadcrumb       = 'Évaluation · ' . ($ref_anaq['libelle'] ?? 'Auto-évaluation (CIAQ)') . ' · ' . $champ['libelle_court'];

$module_actif          = 'IDENTITE_CRICT';

$evaluation_sous_actif = 'AUTO_EVALUATION_ANAQ';



include __DIR__ . '/../../views/layout/header.php';

identite_ui_styles();

auto_anaq_ui_styles();

?>



<div class="id-topbar">

  <a href="referentiel.php<?= auto_anaq_query_edit($mode_edit, true) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Portail</a>

  <div class="spacer"></div>

  <span class="aa-prog-lbl" style="font-size:.8rem;"><?= (int)$comp['faits'] ?> / <?= (int)$comp['total'] ?> renseignés</span>

  <?php if ($peut_modifier && !$mode_edit): ?>

  <a href="?id=<?= $champ_id ?>&edit=1" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen"></i> Modifier</a>

  <?php elseif ($mode_edit): ?>

  <a href="auto_champ.php?id=<?= $champ_id ?>" class="btn btn-secondary btn-sm">Annuler</a>

  <?php endif; ?>

</div>



<div class="card" style="margin-bottom:20px;">

  <div class="card-header">

    <i class="fa-solid <?= e($champ['icone']) ?>" style="color:var(--blue-500);"></i>

    <h2><?= e($champ['libelle_court']) ?></h2>

  </div>

  <div class="card-body">

    <p style="font-size:.85rem;color:var(--text-muted);"><?= e($champ['libelle']) ?></p>

    <div class="aa-prog" style="margin-top:12px;max-width:320px;">

      <div class="aa-prog-bar"><div class="aa-prog-fill" style="width:<?= (int)$comp['pct'] ?>%;"></div></div>

    </div>

    <?php if (!empty($swot['modifie_le'])): ?>

    <p style="margin-top:10px;font-size:.75rem;color:var(--text-muted);">

      <i class="fa-regular fa-clock"></i> SWOT mis à jour le <?= format_datetime($swot['modifie_le']) ?>

    </p>

    <?php endif; ?>

  </div>

</div>



<?php foreach ($references as $ref):

    $criteres = auto_anaq_criteres_par_reference((int)$ref['id']);

?>

<div class="aa-ref-block">

  <div class="aa-ref-hd">

    <span class="aa-ref-code">Réf. <?= e($ref['code']) ?></span>

    <h3><?= e($ref['libelle']) ?></h3>

  </div>

  <div class="aa-point-list">

    <?php foreach ($criteres as $cr):

        $st = auto_anaq_statut_critere($cr);

        $bd = auto_anaq_badge_statut($st);

    ?>

    <a href="auto_point.php?id=<?= (int)$cr['id'] ?><?= $edit_q ?>" class="aa-point-row">

      <span class="aa-point-code"><?= e($cr['code']) ?></span>

      <span class="aa-point-titre"><?= e(auto_anaq_titre_court(auto_anaq_libelle_critere($cr), 90)) ?></span>

      <span class="aa-badge" style="background:<?= $bd[1] ?>;color:<?= $bd[2] ?>;"><?= e($bd[0]) ?></span>

      <i class="fa-solid fa-chevron-right aa-point-arrow"></i>

    </a>

    <?php endforeach; ?>

  </div>

</div>

<?php endforeach; ?>



<div class="card aa-swot-card">

  <div class="card-header">

    <h2><i class="fa-solid fa-table-columns" style="color:var(--blue-500);"></i> Analyse SWOT du thème</h2>

  </div>

  <div class="card-body" id="swot">

    <?php if ($mode_edit): ?>

    <form method="post" action="actions.php">

      <?= csrf_field() ?>

      <input type="hidden" name="action" value="sauver_auto_swot">

      <input type="hidden" name="champ_id" value="<?= $champ_id ?>">

      <input type="hidden" name="en_mode_edit" value="1">

      <div class="aa-swot-grid">

        <div class="aa-swot-box f"><label>Forces</label><textarea name="forces" class="form-control" rows="3"><?= e($swot['forces'] ?? '') ?></textarea></div>

        <div class="aa-swot-box w"><label>Faiblesses</label><textarea name="faiblesses" class="form-control" rows="3"><?= e($swot['faiblesses'] ?? '') ?></textarea></div>

        <div class="aa-swot-box o"><label>Opportunités</label><textarea name="opportunites" class="form-control" rows="3"><?= e($swot['opportunites'] ?? '') ?></textarea></div>

        <div class="aa-swot-box m"><label>Menaces</label><textarea name="menaces" class="form-control" rows="3"><?= e($swot['menaces'] ?? '') ?></textarea></div>

      </div>

      <button type="submit" class="btn btn-primary" style="margin-top:14px;"><i class="fa-solid fa-save"></i> Enregistrer le SWOT</button>

    </form>

    <?php else: ?>

    <div class="aa-swot-grid">

      <?php foreach (['forces'=>'Forces','faiblesses'=>'Faiblesses','opportunites'=>'Opportunités','menaces'=>'Menaces'] as $k => $lbl): ?>

      <div class="aa-swot-box <?= substr($k,0,1) ?>">

        <label><?= $lbl ?></label>

        <div class="aa-swot-val"><?= identite_est_complete($swot[$k] ?? '') ? nl2br(e($swot[$k])) : '<span class="id-swot-vide-inline">À compléter</span>' ?></div>

      </div>

      <?php endforeach; ?>

    </div>

    <?php endif; ?>

  </div>

</div>



<?php include __DIR__ . '/../../views/layout/footer.php'; ?>

