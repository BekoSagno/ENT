<?php
// modules/identite/auto_point.php — Fiche d'un point d'évaluation ANAQ
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

$critere_id = (int)($_GET['id'] ?? 0);
$critere    = auto_anaq_critere_par_id($critere_id);
if (!$critere) {
    set_flash('error', 'Point d\'évaluation introuvable.');
    redirect('/modules/identite/referentiel.php');
}

$peut_modifier = identite_peut_modifier();
$mode_edit     = $peut_modifier && !empty($_GET['edit']);
$voisins       = auto_anaq_critere_voisins($critere_id);
$pieces        = auto_anaq_pieces_lister($critere_id);
$preuves_liste = auto_anaq_preuves_liste($critere['elements_preuves'] ?? '');
$score_labels  = auto_anaq_score_labels();
$is_nombre     = auto_anaq_critere_type_nombre($critere);
$score         = $critere['score'] !== null && $critere['score'] !== '' ? (int)$critere['score'] : null;
$st            = auto_anaq_statut_critere($critere);
$bd            = auto_anaq_badge_statut($st);
$edit_q        = auto_anaq_query_edit($mode_edit);

$ref_anaq              = identite_referentiel('AUTO_EVALUATION_ANAQ');
$page_titre            = $critere['code'];
$page_breadcrumb       = 'Évaluation · ' . ($ref_anaq['libelle'] ?? 'Auto-évaluation (CIAQ)') . ' · ' . e($critere['champ_libelle']) . ' · ' . $critere['code'];
$module_actif          = 'IDENTITE_CRICT';
$evaluation_sous_actif = 'AUTO_EVALUATION_ANAQ';

include __DIR__ . '/../../views/layout/header.php';
identite_ui_styles();
auto_anaq_ui_styles();
?>

<div class="id-topbar">
  <a href="auto_champ.php?id=<?= (int)$critere['champ_id'] ?><?= $edit_q ?>" class="btn btn-secondary btn-sm<?= $mode_edit ? ' aa-nav-link' : '' ?>"<?= $mode_edit ? ' data-aa-nav="1"' : '' ?>>
    <i class="fa-solid fa-arrow-left"></i> Retour au thème
  </a>
  <div class="spacer"></div>
  <span class="id-status <?= e($bd[1]) ?>" id="aa-statut-badge"><?= e($bd[0]) ?></span>
  <?php if ($peut_modifier && !$mode_edit): ?>
  <a href="?id=<?= $critere_id ?>&edit=1" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen"></i> Modifier</a>
  <?php elseif ($mode_edit): ?>
  <a href="auto_point.php?id=<?= $critere_id ?>" class="btn btn-secondary btn-sm">Annuler</a>
  <?php endif; ?>
</div>

<div class="aa-point-hero">
  <div class="aa-point-code-lg"><?= e($critere['code']) ?></div>
  <div class="aa-point-ref">Référence <?= e($critere['ref_code']) ?> — <?= e($critere['champ_libelle']) ?></div>
  <h1><?= e(auto_anaq_libelle_critere($critere) ?: $critere['code']) ?></h1>
  <?php if (!empty($critere['modifie_le'])): ?>
  <p style="margin-top:10px;font-size:.78rem;color:var(--text-muted);">
    <i class="fa-regular fa-clock"></i> Mis à jour le <?= format_datetime($critere['modifie_le']) ?>
  </p>
  <?php endif; ?>
</div>

<?php if (!$mode_edit): ?>
<div class="aa-zone aa-zone-read">
  <div class="aa-zone-lbl"><i class="fa-solid fa-circle-question"></i> La question posée</div>
  <div class="aa-zone-body"><?= nl2br(e($critere['question'])) ?></div>
</div>
<?php endif; ?>

<div class="aa-zone aa-zone-read">
  <div class="aa-zone-lbl">
    <i class="fa-solid fa-file-lines"></i> Documents et preuves attendus
    <?php if (count($preuves_liste) > 1): ?>
    <button type="button" class="aa-toggle-preuves btn btn-secondary btn-sm" id="btn-preuves">
      Voir la liste (<?= count($preuves_liste) ?> éléments)
    </button>
    <?php endif; ?>
  </div>
  <div class="aa-zone-body aa-preuves-collapsed" id="preuves-body">
    <?php if (count($preuves_liste) > 1): ?>
    <ul class="aa-preuves-list">
      <?php foreach ($preuves_liste as $p): ?><li><?= e($p) ?></li><?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p><?= nl2br(e($critere['elements_preuves'] ?? '—')) ?></p>
    <?php endif; ?>
  </div>
</div>

<?php if ($mode_edit): ?>
<form method="post" action="actions.php" enctype="multipart/form-data" class="aa-form" id="aa-form-point">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="sauver_auto_point">
  <input type="hidden" name="critere_id" value="<?= $critere_id ?>">
  <input type="hidden" name="champ_id" value="<?= (int)$critere['champ_id'] ?>">
  <input type="hidden" name="en_mode_edit" value="1">

  <div class="aa-zone">
    <div class="aa-zone-lbl"><i class="fa-solid fa-circle-question"></i> La question posée</div>
    <textarea name="question" class="form-control aa-autosave-field" rows="4" placeholder="Saisissez la question d'évaluation pour ce point…"><?= e($critere['question'] ?? '') ?></textarea>
  </div>

  <div class="aa-zone">
    <div class="aa-zone-lbl"><i class="fa-solid fa-pen"></i> Réponses</div>
    <textarea name="reponse" class="form-control aa-autosave-field" rows="6" placeholder="Décrivez la situation du centre pour ce point…"><?= e($critere['reponse'] ?? '') ?></textarea>
  </div>

  <div class="aa-zone">
    <div class="aa-zone-lbl"><i class="fa-solid fa-paperclip"></i> Éléments de preuve</div>
    <textarea name="preuves_disponibles" class="form-control aa-autosave-field" rows="4" placeholder="Listez les documents, liens ou éléments de preuve…"><?= e($critere['preuves_disponibles'] ?? '') ?></textarea>

    <div class="id-upzone" data-upzone style="margin-top:12px;">
      <input type="file" name="pieces[]" multiple class="id-upinput"
             accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.ppt,.pptx,.odt,.ods,.jpg,.jpeg,.png,.webp,.gif">
      <i class="fa-solid fa-cloud-arrow-up"></i>
      <div class="id-upzone-main">Cliquez ou glissez vos fichiers ici</div>
      <div class="id-upzone-sub">PDF, Word, Excel, texte, images · 20 Mo max par fichier · ajoutez-en autant que nécessaire</div>
    </div>
    <div class="id-upzone-list" data-upzone-list></div>
  </div>

  <?php if (!$is_nombre): ?>
  <div class="aa-zone">
    <div class="aa-zone-lbl"><i class="fa-solid fa-star"></i> Niveau d'évaluation (0 à 5)</div>
    <div class="aa-score-row">
      <?php for ($s = 0; $s <= 5; $s++): ?>
      <label class="aa-score-opt">
        <input type="radio" name="score" class="aa-autosave-field" value="<?= $s ?>" <?= $score === $s ? 'checked' : '' ?>>
        <span class="aa-score-num"><?= $s ?></span>
        <span class="aa-score-lbl"><?= e($score_labels[$s]) ?></span>
      </label>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="aa-save-row">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Enregistrer</button>
    <span class="aa-autosave-status" id="aa-autosave-status" aria-live="polite"></span>
  </div>
</form>

<?php if ($pieces): ?>
<div class="aa-zone">
  <div class="id-section-label">Documents déjà joints</div>
  <div class="id-pieces">
    <?php foreach ($pieces as $p): ?>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
      <a href="auto_download.php?id=<?= (int)$p['id'] ?>" class="id-piece" target="_blank">
        <i class="fa-solid fa-file"></i> <?= e($p['nom_original']) ?>
      </a>
      <form method="post" action="actions.php" style="display:inline;" onsubmit="return confirm('Supprimer ce document ?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="supprimer_auto_piece">
        <input type="hidden" name="piece_id" value="<?= (int)$p['id'] ?>">
        <input type="hidden" name="critere_id" value="<?= $critere_id ?>">
        <input type="hidden" name="en_mode_edit" value="1">
        <button type="submit" class="btn btn-danger btn-sm" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php else: ?>

<div class="aa-zone">
  <div class="aa-zone-lbl"><i class="fa-solid fa-pen"></i> Réponses</div>
  <div class="aa-zone-body <?= identite_est_complete($critere['reponse'] ?? '') ? '' : 'vide' ?>">
    <?= identite_est_complete($critere['reponse'] ?? '') ? nl2br(e($critere['reponse'])) : 'À compléter' ?>
  </div>
</div>

<div class="aa-zone">
  <div class="aa-zone-lbl"><i class="fa-solid fa-paperclip"></i> Éléments de preuve</div>
  <div class="aa-zone-body <?= identite_est_complete($critere['preuves_disponibles'] ?? '') ? '' : 'vide' ?>">
    <?= identite_est_complete($critere['preuves_disponibles'] ?? '') ? nl2br(e($critere['preuves_disponibles'])) : 'À compléter' ?>
  </div>
  <?php if ($pieces): ?>
  <div class="aa-pieces">
    <?php foreach ($pieces as $p): ?>
    <a href="auto_download.php?id=<?= (int)$p['id'] ?>" class="id-piece" target="_blank">
      <i class="fa-solid fa-file"></i> <?= e($p['nom_original']) ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php if (!$is_nombre): ?>
<div class="aa-zone">
  <div class="aa-zone-lbl"><i class="fa-solid fa-star"></i> Niveau de maturité (0 à 5)</div>
  <div class="aa-zone-body" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <span class="id-score" style="font-size:1.1rem;">
      <?= identite_ui_score_etoiles($score) ?>
      <?php if ($score !== null): ?>
      <strong style="color:<?= identite_score_couleur($score) ?>"><?= $score ?>/5 — <?= e($score_labels[$score] ?? '') ?></strong>
      <?php else: ?>
      <span style="color:#94a3b8;font-size:.85rem;"><?= e(IDENTITE_A_COMPLETER) ?></span>
      <?php endif; ?>
    </span>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<div class="aa-nav-points">
  <?php if ($voisins['prev']): ?>
  <a href="auto_point.php?id=<?= (int)$voisins['prev']['id'] ?><?= $edit_q ?>" class="btn btn-secondary aa-nav-link" data-aa-nav="1">
    <i class="fa-solid fa-arrow-left"></i> <?= e($voisins['prev']['code']) ?>
  </a>
  <?php else: ?><span></span><?php endif; ?>
  <?php if ($voisins['next']): ?>
  <a href="auto_point.php?id=<?= (int)$voisins['next']['id'] ?><?= $edit_q ?>" class="btn btn-secondary aa-nav-link" data-aa-nav="1">
    <?= e($voisins['next']['code']) ?> <i class="fa-solid fa-arrow-right"></i>
  </a>
  <?php endif; ?>
</div>

<script>
(function(){
  const btn = document.getElementById('btn-preuves');
  const body = document.getElementById('preuves-body');
  if (btn && body) {
    body.classList.add('aa-preuves-hidden');
    btn.addEventListener('click', function(){
      body.classList.toggle('aa-preuves-hidden');
      btn.textContent = body.classList.contains('aa-preuves-hidden')
        ? 'Voir la liste (<?= count($preuves_liste) ?> éléments)'
        : 'Masquer la liste';
    });
  }
})();
</script>
<?php if ($mode_edit): identite_ui_script_upzone(); identite_ui_script_autosave_point(); endif; ?>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
