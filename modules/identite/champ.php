<?php

// modules/identite/champ.php — Détail d'un champ thématique

require_once __DIR__ . '/../../config/config.php';

require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../includes/helpers.php';

require_once __DIR__ . '/../../includes/auth.php';

require_once __DIR__ . '/../../models/identite.php';

require_once __DIR__ . '/_ui.php';



session_demarrer();

exiger_permission('IDENTITE_CRICT', 'VOIR');



identite_appliquer_preremplissage();



$champ_id = (int)($_GET['id'] ?? 1);

$champ    = identite_champ_par_id($champ_id);



if (!$champ || !$champ['actif']) {

    set_flash('error', 'Ce champ n\'est pas encore disponible.');

    redirect('/modules/identite/index.php');

}



$peut_modifier = identite_peut_modifier();

$mode_edit     = $peut_modifier && !empty($_GET['edit']);

$criteres      = identite_criteres_par_champ($champ_id);

$obs           = identite_observations_lire($champ_id);

$moyenne       = identite_champ_score_moyen($champ_id);

$open_id       = (int)($_GET['open'] ?? 0);

$indicateurs   = ($champ_id === 3) ? identite_indicateurs_lister() : [];

$pieces_map = [];

$echelles_cache = [];

foreach ($criteres as $c) {

    $pieces_map[$c['id']] = identite_pieces_lister((int)$c['id']);

    $type_ec = identite_echelle_type_critere($c, $champ);

    if ($type_ec !== 'NOMBRE' && !isset($echelles_cache[$type_ec])) {

        $echelles_cache[$type_ec] = identite_echelles_par_type($type_ec);

    }

}



$page_titre      = $champ['libelle'];

$page_breadcrumb = 'Évaluation · DGRS · ' . $champ['libelle'];

$module_actif    = 'IDENTITE_CRICT';

$evaluation_sous_actif = 'DGRS';



include __DIR__ . '/../../views/layout/header.php';

identite_ui_styles();

?>



<div class="id-topbar">

  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Portail</a>

  <div class="spacer"></div>

  <?php if ($moyenne !== null): ?>

    <span class="id-score" style="color:<?= identite_score_couleur((int)round($moyenne)) ?>">

      <?= identite_ui_score_etoiles((int)round($moyenne)) ?> Moyenne <?= $moyenne ?>/5

    </span>

  <?php endif; ?>

  <?php if ($peut_modifier && !$mode_edit): ?>

    <a href="?id=<?= $champ_id ?>&edit=1" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen"></i> Modifier</a>

  <?php elseif ($mode_edit): ?>

    <a href="champ.php?id=<?= $champ_id ?>" class="btn btn-secondary btn-sm">Annuler</a>

  <?php endif; ?>

</div>



<div class="card" style="margin-bottom:20px;">

  <div class="card-header">

    <i class="fa-solid <?= e($champ['icone']) ?>" style="color:var(--blue-500);"></i>

    <h2><?= e($champ['libelle']) ?></h2>

  </div>

  <div class="card-body">

    <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:16px;"><?= e($champ['description'] ?? '') ?></p>



    <?php if ($champ_id === 3 && !empty($indicateurs)): ?>

    <div class="id-section-label">Indicateurs quantitatifs suggérés</div>

    <?php if ($mode_edit): ?>

    <form method="POST" action="actions.php" style="margin-bottom:24px;">

      <?= csrf_field() ?>

      <input type="hidden" name="action" value="sauver_indicateurs">

      <input type="hidden" name="champ_id" value="<?= $champ_id ?>">

      <div class="id-id-grid" style="margin-bottom:12px;">

        <?php foreach ($indicateurs as $ind): ?>

        <div class="id-id-item">

          <label><?= e($ind['libelle']) ?></label>

          <textarea name="ind_<?= e($ind['cle']) ?>" class="form-control" rows="3"
                    placeholder="<?= e(IDENTITE_A_COMPLETER) ?>"><?= e($ind['valeur'] ?? '') ?></textarea>

        </div>

        <?php endforeach; ?>

      </div>

      <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-save"></i> Enregistrer les indicateurs</button>

    </form>

    <?php else: ?>

    <div class="id-id-grid" style="margin-bottom:24px;">

      <?php foreach ($indicateurs as $ind): ?>

      <div class="id-id-item">

        <label><?= e($ind['libelle']) ?></label>

        <div class="id-id-val <?= identite_est_complete($ind['valeur'] ?? '') ? '' : 'vide' ?>">

          <?= identite_est_complete($ind['valeur'] ?? '') ? nl2br(e($ind['valeur'])) : IDENTITE_A_COMPLETER ?>

        </div>

      </div>

      <?php endforeach; ?>

    </div>

    <?php endif; ?>

    <?php endif; ?>



    <div class="id-section-label">Synthèse institutionnelle</div>
    <p class="id-hint">Bilan qualitatif du champ : forces, faiblesses et niveau global.</p>

    <?php if ($mode_edit): ?>

    <form method="POST" action="actions.php" style="margin-bottom:24px;">

      <?= csrf_field() ?>

      <input type="hidden" name="action" value="sauver_observations">

      <input type="hidden" name="champ_id" value="<?= $champ_id ?>">

      <div class="id-synth">

<div class="id-synth-box">
  <strong>Forces</strong>
  <textarea name="forces" class="form-control" rows="3" placeholder="<?= e(IDENTITE_A_COMPLETER) ?>"><?= e($obs['forces'] ?? '') ?></textarea>
</div>

<div class="id-synth-box">
  <strong>Faiblesses</strong>
  <textarea name="faiblesses" class="form-control" rows="3" placeholder="<?= e(IDENTITE_A_COMPLETER) ?>"><?= e($obs['faiblesses'] ?? '') ?></textarea>
</div>

<div class="id-synth-box">
  <strong>Niveau global</strong>
  <textarea name="niveau_global" class="form-control" rows="3" placeholder="<?= e(IDENTITE_A_COMPLETER) ?>"><?= e($obs['niveau_global'] ?? '') ?></textarea>
</div>

</div>

      <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-save"></i> Enregistrer la synthèse</button>

    </form>

    <?php else: ?>

    <div class="id-synth" style="margin-bottom:20px;">

      <?php foreach (['forces' => 'Forces', 'faiblesses' => 'Faiblesses', 'niveau_global' => 'Niveau global'] as $k => $lbl): ?>

      <div class="id-synth-box">

        <strong><?= $lbl ?></strong>

        <div class="<?= identite_est_complete($obs[$k] ?? '') ? '' : 'vide' ?>" style="font-size:.85rem;line-height:1.5;">

          <?= identite_ui_afficher_texte($obs[$k] ?? '') ?>

        </div>

      </div>

      <?php endforeach; ?>

    </div>

    <?php endif; ?>



    <div class="id-section-label" style="margin-top:8px;">Critères d'évaluation (<?= count($criteres) ?>)</div>



    <?php foreach ($criteres as $idx => $c):

        $is_nombre = ($c['type_saisie'] ?? 'SCORE') === 'NOMBRE';

        $type_ec   = identite_echelle_type_critere($c, $champ);

        $echelles  = $echelles_cache[$type_ec] ?? [];

        $score     = $c['score'] !== null ? (int)$c['score'] : null;

        $echelle   = (!$is_nombre && $score !== null) ? identite_echelle_libelle($type_ec, $score) : null;

        $is_open   = $open_id > 0 ? ($open_id === (int)$c['id']) : ($idx === 0);

        $rep_vide  = !identite_est_complete($c['reponse_officielle'] ?? '');

        $preuves_vide = !identite_est_complete($c['preuves_disponibles'] ?? '');

        $pieces    = $pieces_map[$c['id']] ?? [];

        $nb_pieces = count($pieces);

        $val_num   = $c['valeur_numerique'] !== null ? $c['valeur_numerique'] : null;

        $crit_complet = !$rep_vide && ($is_nombre || $score !== null);

    ?>

    <div class="id-critere <?= $is_open ? 'open' : '' ?>" id="critere-<?= (int)$c['id'] ?>">

      <div class="id-critere-h" onclick="this.parentElement.classList.toggle('open')">

        <span class="id-critere-code"><?= e($c['code']) ?></span>

        <span class="id-critere-titre"><?= e($c['libelle']) ?></span>

        <?php if (!$is_nombre): ?>

          <span class="id-score"><?= identite_ui_score_etoiles($score) ?><?php if ($score !== null): ?> <span style="color:<?= identite_score_couleur($score) ?>"><?= $score ?>/5</span><?php endif; ?></span>

        <?php endif; ?>

        <?php if ($nb_pieces > 0): ?>
          <span class="id-doc-pill" title="<?= $nb_pieces ?> document<?= $nb_pieces > 1 ? 's' : '' ?> joint<?= $nb_pieces > 1 ? 's' : '' ?>"><i class="fa-solid fa-paperclip"></i> <?= $nb_pieces ?></span>
        <?php endif; ?>

        <span class="id-status <?= $crit_complet ? 'ok' : 'todo' ?>"><?= $crit_complet ? 'Complété' : 'À compléter' ?></span>

        <i class="fa-solid fa-chevron-down" style="color:var(--text-muted);font-size:.75rem;"></i>

      </div>

      <div class="id-critere-body">



        <div class="id-section">

          <div class="id-section-label">Référence (grille officielle)</div>

          <div class="id-ref">

            <strong>Indicateurs clés :</strong> <?= e($c['indicateurs_cles'] ?? '—') ?><br>

            <strong>Sources de vérification attendues :</strong> <?= e($c['sources_verification'] ?? '—') ?>

          </div>

        </div>



        <?php if ($mode_edit): ?>

        <form method="POST" action="actions.php" enctype="multipart/form-data" style="margin-top:16px;">

          <?= csrf_field() ?>

          <input type="hidden" name="action" value="sauver_critere">

          <input type="hidden" name="critere_id" value="<?= (int)$c['id'] ?>">

          <input type="hidden" name="champ_id" value="<?= $champ_id ?>">



          <div class="id-section">

            <div class="id-section-label">RÉPONSES</div>

            <textarea name="reponse_officielle" class="form-control" rows="4"

                      placeholder="Rédigez les réponses pour ce critère…"><?= e($c['reponse_officielle'] ?? '') ?></textarea>

          </div>



          <div class="id-section">

            <div class="id-section-label"><i class="fa-solid fa-paperclip"></i> Éléments de preuve</div>

            <textarea name="preuves_disponibles" class="form-control" rows="2"

                      placeholder="Listez les documents, liens ou éléments de preuve…"><?= e($c['preuves_disponibles'] ?? '') ?></textarea>

            <div class="id-upzone" data-upzone style="margin-top:12px;">
              <input type="file" name="piece[]" multiple class="id-upinput"
                     accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.ppt,.pptx,.odt,.ods,.jpg,.jpeg,.png,.webp,.gif">
              <i class="fa-solid fa-cloud-arrow-up"></i>
              <div class="id-upzone-main">Cliquez ou glissez vos fichiers ici</div>
              <div class="id-upzone-sub">PDF, Word, Excel, texte, images · 20 Mo max par fichier · ajoutez-en autant que nécessaire</div>
            </div>

            <div class="id-upzone-list" data-upzone-list></div>

          </div>



          <?php if (!$is_nombre): ?>

          <div class="id-section">

            <div class="id-section-label">Niveau de maturité (0 à 5)</div>

            <div style="margin-bottom:8px;"><?= identite_ui_score_etoiles($score) ?></div>

            <select name="score" class="form-control" style="max-width:420px;" onchange="identitePreviewScore(this)">

              <option value="">— Non renseigné —</option>

              <?php foreach ($echelles as $e): ?>

              <option value="<?= (int)$e['score'] ?>" data-sig="<?= e($e['signification']) ?>"

                <?= $score === (int)$e['score'] ? 'selected' : '' ?>>

                <?= (int)$e['score'] ?> — <?= e($e['libelle']) ?>

              </option>

              <?php endforeach; ?>

            </select>

            <div class="score-preview" style="font-size:.78rem;color:var(--text-muted);margin-top:6px;"></div>

          </div>

          <?php endif; ?>



          <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Enregistrer ce critère</button>

        </form>

        <?php else: ?>

        <div class="id-section">

          <div class="id-section-label"><i class="fa-solid fa-comment-dots"></i> RÉPONSES</div>

          <div class="id-reponse <?= $rep_vide ? 'vide' : '' ?>" id="rep-<?= (int)$c['id'] ?>">

            <?= identite_ui_afficher_texte($c['reponse_officielle'] ?? '') ?>

          </div>

        </div>



        <div class="id-section">

          <div class="id-section-label"><i class="fa-solid fa-paperclip"></i> Éléments de preuve</div>

          <div class="id-preuve-box <?= $preuves_vide ? 'vide' : '' ?>">

            <?= identite_ui_afficher_texte($c['preuves_disponibles'] ?? '') ?>

          </div>

        </div>



        <?php if (!$is_nombre): ?>

        <div class="id-section">

          <div class="id-section-label">Niveau de maturité (0 à 5)</div>

          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">

            <span class="id-score" style="font-size:1.1rem;">

              <?= identite_ui_score_etoiles($score) ?>

              <?php if ($score !== null): ?>

              <strong style="color:<?= identite_score_couleur($score) ?>"><?= $score ?>/5<?= $echelle ? ' — ' . e($echelle['libelle']) : '' ?></strong>

              <?php else: ?>

              <span style="color:#94a3b8;font-size:.85rem;"><?= e(IDENTITE_A_COMPLETER) ?></span>

              <?php endif; ?>

            </span>

          </div>

          <?php if ($echelle): ?>

          <p style="font-size:.8rem;color:var(--text-muted);margin-top:6px;"><?= e($echelle['signification']) ?></p>

          <?php endif; ?>

        </div>

        <?php endif; ?>

        <?php endif; ?>



        <div class="id-section">

          <?php if ($mode_edit): ?>
          <div class="id-section-label">Documents déjà joints</div>
          <?php endif; ?>

          <?php if (empty($pieces)): ?>

            <p style="font-size:.82rem;color:var(--text-muted);font-style:italic;">Aucun document joint.</p>

          <?php else: ?>

            <div class="id-pieces">

              <?php foreach ($pieces as $p): ?>

              <a href="download.php?id=<?= (int)$p['id'] ?>" class="id-piece" target="_blank">

                <i class="fa-solid fa-file-pdf"></i> <?= e($p['nom_original']) ?>

              </a>

              <a href="download.php?id=<?= (int)$p['id'] ?>&dl=1" class="id-piece" title="Télécharger">

                <i class="fa-solid fa-download"></i>

              </a>

              <?php if ($mode_edit): ?>

              <form method="POST" action="actions.php" style="display:inline;" onsubmit="return confirm('Supprimer ce fichier ?');">

                <?= csrf_field() ?>

                <input type="hidden" name="action" value="supprimer_piece">

                <input type="hidden" name="piece_id" value="<?= (int)$p['id'] ?>">

                <input type="hidden" name="champ_id" value="<?= $champ_id ?>">

                <button type="submit" class="btn btn-icon btn-sm" style="color:var(--danger);border:1px solid #fecaca;background:#fee2e2;">

                  <i class="fa-solid fa-trash"></i>

                </button>

              </form>

              <?php endif; ?>

              <?php endforeach; ?>

            </div>

          <?php endif; ?>

        </div>



        <?php if (!empty($c['contenu_modifie_le'])): ?>

        <div style="font-size:.7rem;color:var(--text-muted);margin-top:12px;">

          <i class="fa-regular fa-clock"></i> Mis à jour le <?= format_datetime($c['contenu_modifie_le']) ?>

        </div>

        <?php endif; ?>

      </div>

    </div>

    <?php endforeach; ?>

  </div>

</div>



<?php

$extra_scripts = <<<'JS'

<script>

function identitePreviewScore(sel) {

  const opt = sel.options[sel.selectedIndex];

  const prev = sel.closest('form').querySelector('.score-preview');

  if (prev) prev.textContent = opt.dataset.sig || '';

}

document.querySelectorAll('select[name="score"]').forEach(sel => {

  if (sel.value) identitePreviewScore(sel);

});

const hash = window.location.hash;

if (hash && hash.startsWith('#critere-')) {

  const el = document.querySelector(hash);

  if (el) el.classList.add('open');

}

// Zones de téléversement : accumulation des fichiers (clic répété ou glisser-déposer)
document.querySelectorAll('[data-upzone]').forEach(function (zone) {
  var input = zone.querySelector('.id-upinput');
  var list  = zone.parentElement.querySelector('[data-upzone-list]');
  var store = new DataTransfer();
  var MAX   = 20 * 1024 * 1024;

  function human(n) {
    if (n >= 1048576) return (n / 1048576).toFixed(1) + ' Mo';
    if (n >= 1024) return Math.round(n / 1024) + ' Ko';
    return n + ' o';
  }
  function iconFor(name) {
    var ext = (name.split('.').pop() || '').toLowerCase();
    if (ext === 'pdf') return 'fa-file-pdf';
    if (ext === 'doc' || ext === 'docx' || ext === 'odt') return 'fa-file-word';
    if (ext === 'xls' || ext === 'xlsx' || ext === 'csv' || ext === 'ods') return 'fa-file-excel';
    if (ext === 'ppt' || ext === 'pptx') return 'fa-file-powerpoint';
    if (ext === 'jpg' || ext === 'jpeg' || ext === 'png' || ext === 'webp' || ext === 'gif') return 'fa-file-image';
    if (ext === 'txt') return 'fa-file-lines';
    return 'fa-file';
  }
  function esc(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function render() {
    list.innerHTML = '';
    Array.prototype.forEach.call(store.files, function (f, i) {
      var tooBig = f.size > MAX;
      var row = document.createElement('div');
      row.className = 'id-upfile' + (tooBig ? ' err' : '');
      row.innerHTML =
        '<i class="fa-solid ' + iconFor(f.name) + '"></i>' +
        '<span class="id-upfile-name">' + esc(f.name) + '</span>' +
        '<span class="id-upfile-size">' + human(f.size) + (tooBig ? ' · trop volumineux' : '') + '</span>' +
        '<button type="button" class="id-upfile-x" data-i="' + i + '" title="Retirer"><i class="fa-solid fa-xmark"></i></button>';
      list.appendChild(row);
    });
    input.files = store.files;
  }
  function addFiles(files) {
    Array.prototype.forEach.call(files, function (f) {
      var dup = false;
      Array.prototype.forEach.call(store.files, function (g) {
        if (g.name === f.name && g.size === f.size) dup = true;
      });
      if (!dup) store.items.add(f);
    });
    render();
  }
  input.addEventListener('change', function () {
    var picked = [];
    Array.prototype.forEach.call(input.files, function (f) { picked.push(f); });
    addFiles(picked);
  });
  list.addEventListener('click', function (e) {
    var btn = e.target.closest('.id-upfile-x');
    if (!btn) return;
    var idx = parseInt(btn.dataset.i, 10);
    var tmp = new DataTransfer();
    Array.prototype.forEach.call(store.files, function (f, i) { if (i !== idx) tmp.items.add(f); });
    store = tmp;
    render();
  });
  ['dragenter', 'dragover'].forEach(function (ev) {
    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('drag'); });
  });
  ['dragleave', 'dragend', 'drop'].forEach(function (ev) {
    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('drag'); });
  });
  zone.addEventListener('drop', function (e) {
    if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files);
  });
});

</script>

JS;

include __DIR__ . '/../../views/layout/footer.php';

?>

