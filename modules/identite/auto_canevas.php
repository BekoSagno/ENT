<?php
// modules/identite/auto_canevas.php — Gestion des canevas / référentiels ANAQ
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

$peut_modifier = identite_peut_modifier();
$edit_id       = $peut_modifier ? (int)($_GET['edit'] ?? 0) : 0;
$edit_canevas  = $edit_id > 0 ? auto_anaq_canevas_par_id($edit_id) : null;
if ($edit_id > 0 && !$edit_canevas) {
    set_flash('error', 'Canevas introuvable.');
    redirect('/modules/identite/auto_canevas.php');
}

$canevas_liste = auto_anaq_canevas_lister(false);
$courant       = auto_anaq_canevas_courant();

$ref_anaq              = identite_referentiel('AUTO_EVALUATION_ANAQ');
$page_titre            = 'Canevas — Auto-évaluation ANAQ';
$page_breadcrumb       = 'Évaluation · ' . ($ref_anaq['libelle'] ?? 'Auto-évaluation (CIAQ)') . ' · Canevas';
$module_actif          = 'IDENTITE_CRICT';
$evaluation_sous_actif = 'AUTO_EVALUATION_ANAQ';

include __DIR__ . '/../../views/layout/header.php';
identite_ui_styles();
auto_anaq_ui_styles();
?>

<div class="id-topbar">
  <a href="referentiel.php" class="btn btn-secondary btn-sm">
    <i class="fa-solid fa-arrow-left"></i> Portail ANAQ
  </a>
  <div class="spacer"></div>
  <?php if ($courant): ?>
  <span class="aa-badge" style="background:#dbeafe;color:#1e40af;">
    En cours : <?= e($courant['titre']) ?><?= $courant['version'] ? ' (' . e($courant['version']) . ')' : '' ?>
  </span>
  <?php endif; ?>
</div>

<div class="id-hero" style="margin-bottom:18px;">
  <h2><i class="fa-solid fa-folder-open" style="margin-right:8px;opacity:.9;"></i>Canevas / référentiels</h2>
  <p>Importez un référentiel par <strong>fichier Word</strong> ou en <strong>collant son contenu</strong> : la grille se remplit automatiquement. <strong>Utiliser</strong> place ce référentiel sur le portail.</p>
</div>

<?php if ($peut_modifier): ?>
<div class="aa-zone" style="margin-bottom:18px;">
  <div class="aa-zone-lbl"><i class="fa-solid fa-plus"></i> <?= $edit_canevas ? 'Modifier le canevas' : 'Ajouter un canevas' ?></div>
  <form method="post" action="actions.php" enctype="multipart/form-data" class="aa-form" id="form-canevas">
    <?= csrf_field() ?>
    <?php if ($edit_canevas): ?>
    <input type="hidden" name="action" value="auto_canevas_modifier">
    <input type="hidden" name="id" value="<?= (int)$edit_canevas['id'] ?>">
    <?php else: ?>
    <input type="hidden" name="action" value="auto_canevas_ajouter">
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div class="form-group">
        <label class="form-label">Titre *</label>
        <input type="text" name="titre" class="form-control" required maxlength="255"
               value="<?= e($edit_canevas['titre'] ?? '') ?>"
               placeholder="Ex. Auto-évaluation ANAQ / CIAQ">
      </div>
      <div class="form-group">
        <label class="form-label">Code <?= $edit_canevas ? '(non modifiable)' : '' ?></label>
        <?php if ($edit_canevas): ?>
        <input type="text" class="form-control" value="<?= e($edit_canevas['code']) ?>" disabled>
        <?php else: ?>
        <input type="text" name="code" class="form-control" maxlength="40"
               placeholder="Ex. ANAQ_2025 (auto si vide)">
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label class="form-label">Version</label>
        <input type="text" name="version" class="form-control" maxlength="50"
               value="<?= e($edit_canevas['version'] ?? '') ?>" placeholder="Ex. 2025">
      </div>
      <div class="form-group">
        <label class="form-label">Date de publication</label>
        <input type="date" name="date_publication" class="form-control"
               value="<?= e($edit_canevas['date_publication'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="2"
                placeholder="Description du référentiel…"><?= e($edit_canevas['description'] ?? '') ?></textarea>
    </div>

    <div class="aa-zone" style="margin:16px 0 0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;">
      <div class="aa-zone-lbl" style="margin-bottom:10px;"><i class="fa-solid fa-file-import"></i> Importer le référentiel</div>
      <p style="font-size:.84rem;color:var(--text-muted);margin:0 0 12px;">
        Choisissez <strong>une</strong> des deux options. En un clic, la grille est remplie automatiquement.
      </p>

      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label">
          <input type="radio" name="import_mode" value="fichier" checked
                 onchange="document.getElementById('bloc-fichier').style.display='block';document.getElementById('bloc-coller').style.display='none';">
          Fichier Word (.docx)<?= $edit_canevas ? ' — remplacer et réimporter la grille' : '' ?>
        </label>
        <div id="bloc-fichier" style="margin-top:8px;">
          <input type="file" name="fichier_guide" class="form-control" accept=".pdf,.doc,.docx,.odt,.txt">
          <?php if (!empty($edit_canevas['fichier_nom'])): ?>
          <p style="margin-top:6px;font-size:.78rem;color:var(--text-muted);">
            Actuel : <strong><?= e($edit_canevas['fichier_nom']) ?></strong>
          </p>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">
          <input type="radio" name="import_mode" value="coller"
                 onchange="document.getElementById('bloc-fichier').style.display='none';document.getElementById('bloc-coller').style.display='block';">
          Coller le contenu du référentiel
        </label>
        <div id="bloc-coller" style="display:none;margin-top:8px;">
          <textarea name="referentiel_texte" class="form-control" rows="10"
                    placeholder="Ouvrez votre référentiel Word, sélectionnez tout (Ctrl+A), copiez (Ctrl+C), puis collez ici…"></textarea>
          <p style="margin-top:6px;font-size:.78rem;color:var(--text-muted);">
            Astuce : le copier-coller fonctionne très bien et remplit toute la grille d’un coup.
          </p>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-check"></i> <?= $edit_canevas ? 'Enregistrer' : 'Créer le canevas' ?>
      </button>
      <?php if ($edit_canevas): ?>
      <a href="auto_canevas.php" class="btn btn-secondary">Annuler</a>
      <?php endif; ?>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="aa-zone">
  <div class="aa-zone-lbl"><i class="fa-solid fa-list"></i> Canevas enregistrés</div>
  <?php if (!$canevas_liste): ?>
  <div class="aa-zone-body vide">Aucun canevas pour le moment.</div>
  <?php else: ?>
  <div style="overflow-x:auto;">
    <table class="rdv-table" style="width:100%;border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left;padding:10px;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);">Référentiel</th>
          <th style="text-align:left;padding:10px;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);">Grille</th>
          <th style="text-align:left;padding:10px;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);">Guide</th>
          <th style="text-align:left;padding:10px;font-size:.72rem;text-transform:uppercase;color:var(--text-muted);">Portail</th>
          <th style="padding:10px;"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($canevas_liste as $cv):
          $stats = auto_anaq_canevas_stats((int)$cv['id']);
          $est_courant = $courant && (int)$courant['id'] === (int)$cv['id'];
      ?>
        <tr style="border-top:1px solid var(--border);<?= $est_courant ? 'background:var(--blue-50,#eef4ff);' : '' ?>">
          <td style="padding:12px;vertical-align:top;">
            <strong><?= e($cv['titre']) ?></strong>
            <div style="font-size:.78rem;color:var(--text-muted);margin-top:3px;">
              <?= e($cv['code']) ?><?= $cv['version'] ? ' · v' . e($cv['version']) : '' ?>
            </div>
            <?php if ($cv['description']): ?>
            <div style="font-size:.8rem;color:var(--text-muted);margin-top:4px;"><?= e(mb_substr($cv['description'], 0, 120)) ?><?= mb_strlen($cv['description']) > 120 ? '…' : '' ?></div>
            <?php endif; ?>
          </td>
          <td style="padding:12px;font-size:.84rem;vertical-align:top;">
            <?= (int)$stats['n_champs'] ?> thèmes<br>
            <?= (int)$stats['n_refs'] ?> références<br>
            <?= (int)$stats['n_criteres'] ?> points
          </td>
          <td style="padding:12px;font-size:.84rem;vertical-align:top;">
            <?php if (!empty($cv['fichier_guide'])): ?>
            <a href="auto_canevas_download.php?id=<?= (int)$cv['id'] ?>" target="_blank" class="id-piece">
              <i class="fa-solid fa-file"></i> <?= e($cv['fichier_nom'] ?: 'Guide') ?>
            </a>
            <?php else: ?>
            <span style="color:var(--text-muted);">—</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px;vertical-align:top;">
            <?php if ((int)$cv['actif']): ?>
            <span class="aa-badge" style="background:#dcfce7;color:#166534;">En service</span>
            <?php else: ?>
            <span style="font-size:.8rem;color:var(--text-muted);">—</span>
            <?php endif; ?>
          </td>
          <td style="padding:12px;vertical-align:top;white-space:nowrap;">
            <div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end;">
              <?php if ($peut_modifier): ?>
              <?php if (!(int)$cv['actif']): ?>
              <form method="post" action="actions.php" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="auto_canevas_utiliser">
                <input type="hidden" name="id" value="<?= (int)$cv['id'] ?>">
                <button type="submit" class="btn btn-primary btn-sm" title="Mettre ce référentiel sur le portail pour tout le monde">
                  <i class="fa-solid fa-check"></i> Utiliser
                </button>
              </form>
              <?php else: ?>
              <a href="referentiel.php?canevas=<?= (int)$cv['id'] ?>" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-right"></i> Voir le portail
              </a>
              <?php endif; ?>
              <form method="post" action="actions.php" style="display:inline;"
                    onsubmit="return confirm('Supprimer définitivement ce référentiel et toute sa grille ?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="auto_canevas_supprimer">
                <input type="hidden" name="id" value="<?= (int)$cv['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" title="Supprimer">
                  <i class="fa-solid fa-trash"></i> Supprimer
                </button>
              </form>
              <?php else: ?>
              <a href="referentiel.php?canevas=<?= (int)$cv['id'] ?>" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-eye"></i> Voir
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php';
