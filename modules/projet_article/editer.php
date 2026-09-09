<?php
// modules/projet_article/editer.php — Parcours guidé 5 étapes
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet_article.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission(ARTICLE_MODULE, 'VOIR');
article_assurer_schema();

$id = (int)($_GET['id'] ?? 0);
$d = article_charger($id);
if (!$d) {
    set_flash('error', 'Article introuvable.');
    redirect('/modules/projet_article/index.php');
}
if (!article_peut_modifier($d) && (int)$d['cree_par'] !== (int)user_id() && !article_peut_gerer()) {
    http_response_code(403);
    exit('Accès refusé.');
}

$peut_mod = article_peut_modifier($d);
$etape = max(1, min(5, (int)($_GET['etape'] ?? 1)));
$parcours = article_parcours_ui();
$info = $parcours[$etape];
$all = $d['contenu_arr'];

$page_titre = $info['titre'];
$page_breadcrumb = 'Projets · ' . $d['code'] . ' · Étape ' . $etape . '/5';
$module_actif = 'PROJETS';
$projet_sous_actif = 'ARTICLE';

include __DIR__ . '/../../views/layout/header.php';
article_ui_styles();
?>

<div class="pa-page">
  <?php article_ui_stepper($id, $etape, $all); ?>

  <?php if (!$peut_mod): ?>
  <div class="pa-card" style="background:#fef3c7;border-color:#fcd34d;color:#92400e;">Lecture seule — article finalisé ou droits insuffisants.</div>
  <?php article_ui_synthese_a4($d); ?>
  <?php else: ?>

  <?php if (!empty($info['synthese'])): ?>
    <div class="pa-a4-toolbar">
      <a href="editer.php?id=<?= $id ?>&etape=4" class="pa-btn pa-btn-ghost"><i class="fa-solid fa-arrow-left"></i> Précédent</a>
      <div class="spacer" style="flex:1;"></div>
      <form method="post" action="actions.php" style="display:inline;" onsubmit="return confirm('Marquer cet article comme finalisé ?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="statut">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="statut" value="FINALISE">
        <button type="submit" class="pa-btn pa-btn-ghost">Finaliser</button>
      </form>
      <button type="button" class="pa-btn pa-btn-ghost" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer / PDF</button>
      <?php if ($peut_mod): ?>
      <form method="post" action="actions.php" style="display:inline;" onsubmit="return confirm('Supprimer définitivement cet article ?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="supprimer">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="pa-btn pa-btn-ghost" style="color:#dc2626;">Supprimer</button>
      </form>
      <?php endif; ?>
    </div>
    <?php article_ui_synthese_a4($d); ?>

  <?php else: ?>
  <form method="post" action="actions.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="sauver_etape">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="etape" value="<?= $etape ?>">

    <?php if ($etape === 1):
        $p = $all['identite'];
    ?>
      <?php article_ui_sec_open('1', 'Titre'); ?>
        <?php article_ui_field('titre', 'Titre de l’article', $d['titre'], '', 2, true); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('2', 'Auteurs et affiliations'); ?>
        <?php article_ui_tableau('auteurs', [
            'nom'          => 'Nom et prénoms',
            'affiliations' => 'Affiliation(s)',
            'email'        => 'E-mail',
            'orcid'        => 'ORCID',
        ], $p['auteurs'] ?? [], 'Liste des auteurs'); ?>
      <?php article_ui_sec_close(); ?>

    <?php elseif ($etape === 2):
        $r = $all['resumes'];
    ?>
      <?php article_ui_sec_open('3', 'Résumé'); ?>
        <?php article_ui_field('resume', 'Résumé (français)', $r['resume'] ?? '', '', 6); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('4', 'Mots-clés'); ?>
        <?php article_ui_field('mots_cles', 'Mots-clés (séparés par des virgules)', $r['mots_cles'] ?? '', 'ex. santé, épidémiologie, Guinée', 2); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('5', 'Abstract'); ?>
        <?php article_ui_field('abstract', 'Abstract (English)', $r['abstract'] ?? '', '', 6); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('6', 'Keywords'); ?>
        <?php article_ui_field('keywords', 'Keywords (comma-separated)', $r['keywords'] ?? '', 'e.g. health, epidemiology', 2); ?>
      <?php article_ui_sec_close(); ?>

    <?php elseif ($etape === 3):
        $b = $all['corps'];
    ?>
      <?php article_ui_sec_open('7', 'Introduction'); ?>
        <?php article_ui_field('introduction', 'Introduction', $b['introduction'] ?? '', '', 8); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('8', 'Revue de littérature ou travaux connexes'); ?>
        <?php article_ui_field('litterature', 'Revue de littérature / travaux connexes', $b['litterature'] ?? '', '', 8); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('9', 'Matériels et méthodes'); ?>
        <?php article_ui_field('methodes', 'Matériels et méthodes', $b['methodes'] ?? '', '', 8); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('10', 'Résultats'); ?>
        <?php article_ui_field('resultats', 'Résultats', $b['resultats'] ?? '', '', 8); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('11', 'Discussion'); ?>
        <?php article_ui_field('discussion', 'Discussion', $b['discussion'] ?? '', '', 8); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('12', 'Conclusion et perspectives'); ?>
        <?php article_ui_field('conclusion', 'Conclusion et perspectives', $b['conclusion'] ?? '', '', 6); ?>
      <?php article_ui_sec_close(); ?>

    <?php elseif ($etape === 4):
        $m = $all['mentions'];
        $f = $all['fin'];
    ?>
      <?php article_ui_sec_open('13', 'Contributions des auteurs'); ?>
        <?php article_ui_field('contributions', 'Contributions des auteurs', $m['contributions'] ?? '', '', 4); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('14', 'Financement'); ?>
        <?php article_ui_field('financement', 'Financement', $m['financement'] ?? '', '', 3); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('15', 'Conflits d’intérêts'); ?>
        <?php article_ui_field('conflits', 'Conflits d’intérêts', $m['conflits'] ?? '', 'Déclarez l’absence ou la présence de conflits.', 3); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('16', 'Disponibilité des données'); ?>
        <?php article_ui_field('donnees', 'Disponibilité des données', $m['donnees'] ?? '', '', 3); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('17', 'Remerciements'); ?>
        <?php article_ui_field('remerciements', 'Remerciements', $m['remerciements'] ?? '', '', 3); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('18', 'Références bibliographiques'); ?>
        <?php article_ui_field('references', 'Références bibliographiques', $f['references'] ?? '', '', 10); ?>
      <?php article_ui_sec_close(); ?>

      <?php article_ui_sec_open('19', 'Annexes éventuelles'); ?>
        <?php
        $annexes = $f['annexes_fichiers'] ?? [];
        if ($annexes):
        ?>
        <div class="pa-field" style="margin-bottom:14px;">
          <div class="pa-label">Fichiers déjà joints</div>
          <?php foreach ($annexes as $af):
              $fn = (string)($af['fichier'] ?? '');
              $nm = (string)($af['nom'] ?? $fn);
          ?>
          <label style="display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:.88rem;">
            <input type="checkbox" name="annexes_suppr[]" value="<?= e($fn) ?>">
            <a href="download_annexe.php?id=<?= $id ?>&f=<?= rawurlencode($fn) ?>" target="_blank"><?= e($nm) ?></a>
            <span style="color:#94a3b8;font-size:.75rem;">(cocher pour supprimer)</span>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="pa-field" id="paAnnexesBox">
          <div class="pa-label">Ajouter des fichiers</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px;">
            <button type="button" class="pa-btn-add" id="paAnnexesAddBtn">
              <i class="fa-solid fa-plus"></i> Ajouter un fichier
            </button>
          </div>
          <input type="file" id="annexesPicker" style="display:none;" multiple
                 accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.txt,.csv,.jpg,.jpeg,.png,.webp,.gif">
          <input type="file" name="annexes[]" id="annexes" multiple style="display:none;"
                 accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.txt,.csv,.jpg,.jpeg,.png,.webp,.gif">
          <ul id="paAnnexesPending" style="list-style:none;margin:0;padding:0;"></ul>
        </div>
      <?php article_ui_sec_close(); ?>
    <?php endif; ?>

    <div class="pa-nav">
      <?php if ($etape > 1): ?>
      <button type="submit" name="_precedent" value="1" class="pa-btn pa-btn-ghost">
        <i class="fa-solid fa-arrow-left"></i> Précédent
      </button>
      <?php endif; ?>
      <div class="spacer"></div>
      <button type="submit" name="_suivant" value="1" class="pa-btn pa-btn-primary">
        Suivant <i class="fa-solid fa-arrow-right"></i>
      </button>
    </div>
  </form>
  <?php article_ui_tableau_script(); ?>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
