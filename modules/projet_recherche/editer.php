<?php
// modules/projet_recherche/editer.php — Parcours guidé 6 étapes (UI)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet_recherche.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission(RECHERCHE_MODULE, 'VOIR');
recherche_assurer_schema();

$id = (int)($_GET['id'] ?? 0);
$d = recherche_charger($id);
if (!$d) {
    set_flash('error', 'Projet introuvable.');
    redirect('/modules/projet_recherche/index.php');
}
if (!recherche_peut_modifier($d) && (int)$d['cree_par'] !== (int)user_id() && !recherche_peut_gerer()) {
    http_response_code(403);
    exit('Accès refusé.');
}

$peut_mod = recherche_peut_modifier($d);
$etape = max(1, min(6, (int)($_GET['etape'] ?? 1)));
$parcours = recherche_parcours_ui();
$info = $parcours[$etape];
$all = $d['contenu_arr'];

$page_titre = $info['titre'];
$page_breadcrumb = 'Projets · ' . $d['code'] . ' · Étape ' . $etape . '/6';
$module_actif = 'PROJETS';
$projet_sous_actif = 'RECHERCHE';

include __DIR__ . '/../../views/layout/header.php';
recherche_ui_styles();
?>

<div class="pr-page">
  <?php recherche_ui_stepper($id, $etape, $all); ?>

  <?php if (!$peut_mod): ?>
  <div class="pr-card" style="background:#fef3c7;border-color:#fcd34d;color:#92400e;">Lecture seule — projet finalisé ou droits insuffisants.</div>
  <?php recherche_ui_synthese_a4($d); ?>
  <?php else: ?>

  <?php if (!empty($info['synthese'])): ?>
    <div class="pr-a4-toolbar">
      <a href="editer.php?id=<?= $id ?>&etape=5" class="pr-btn pr-btn-ghost"><i class="fa-solid fa-arrow-left"></i> Précédent</a>
      <div class="spacer" style="flex:1;"></div>
      <button type="button" class="pr-btn pr-btn-ghost" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer / PDF</button>
    </div>
    <?php recherche_ui_synthese_a4($d); ?>

  <?php else: ?>
  <form method="post" action="actions.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="sauver_etape">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="etape" value="<?= $etape ?>">

    <?php if ($etape === 1):
        $p = $all['presentation'];
    ?>
      <?php recherche_ui_sec_open('1', 'Présentation institutionnelle du projet'); ?>
        <?php recherche_ui_field('titre', 'Titre du projet de recherche', $d['titre'], '', 1, true); ?>
        <?php recherche_ui_list_open(); ?>
          <?php recherche_ui_field('institution_porteuse', 'Identification de l\'institution porteuse', $p['institution_porteuse'] ?? '', '', 2, true); ?>
          <?php recherche_ui_field('ancrage', 'Ancrage institutionnel', $p['ancrage'] ?? '', '', 4, true); ?>
          <div class="pr-item">
            <?php recherche_ui_tableau('equipe', [
                'membre' => 'Membres',
                'grade' => 'Grade et spécialité',
                'institution' => 'Institution',
                'responsabilites' => 'Responsabilités dans le projet',
                'temps' => 'Temps consacré',
            ], $p['equipe'] ?? [], 'Équipe de recherche'); ?>
          </div>
        <?php recherche_ui_list_close(); ?>
      <?php recherche_ui_sec_close(); ?>

    <?php elseif ($etape === 2):
        $cx = $all['contexte']; $li = $all['litterature']; $pr = $all['problematique']; $ob = $all['objectifs'];
    ?>
      <?php recherche_ui_sec_open('2', 'Contexte et justification'); ?>
        <?php recherche_ui_sub_open('Contexte général'); ?>
          <?php recherche_ui_field('situation_actuelle', 'Description de la situation actuelle', $cx['situation_actuelle'] ?? '', '', 4); ?>
          <?php recherche_ui_field('justification_scientifique', 'Justification scientifique', $cx['justification_scientifique'] ?? '', '', 4); ?>
          <?php recherche_ui_field('justification_socio', 'Justification socioéconomique et institutionnelle', $cx['justification_socio'] ?? '', '', 4); ?>
        <?php recherche_ui_sub_close(); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('3', 'Revue de la littérature et cadre de référence'); ?>
        <?php recherche_ui_list_open(); ?>
          <?php recherche_ui_field('revue_critique', 'Revue critique de la littérature', $li['revue_critique'] ?? '', '', 5); ?>
          <?php recherche_ui_field('lacune', 'Lacune scientifique', $li['lacune'] ?? '', '', 3); ?>
          <?php recherche_ui_field('cadre_theorique', 'Cadre théorique', $li['cadre_theorique'] ?? '', '', 3); ?>
          <?php recherche_ui_field('cadre_conceptuel', 'Cadre conceptuel', $li['cadre_conceptuel'] ?? '', '', 3); ?>
        <?php recherche_ui_list_close(); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('4', 'Problématique de la recherche'); ?>
        <?php recherche_ui_list_open(); ?>
          <?php recherche_ui_field('constat', 'Constat de départ', $pr['constat'] ?? '', '', 3); ?>
          <?php recherche_ui_field('probleme_scientifique', 'Problème scientifique', $pr['probleme_scientifique'] ?? '', '', 3); ?>
          <?php recherche_ui_field('question_principale', 'Question principale de recherche', $pr['question_principale'] ?? '', '', 2); ?>
          <?php recherche_ui_field('questions_specifiques', 'Questions spécifiques', $pr['questions_specifiques'] ?? '', '', 3); ?>
          <?php recherche_ui_field('hypothese_generale', 'Hypothèse générale', $pr['hypothese_generale'] ?? '', '', 2); ?>
          <?php recherche_ui_field('hypotheses_specifiques', 'Hypothèses spécifiques', $pr['hypotheses_specifiques'] ?? '', '', 3); ?>
          <?php recherche_ui_field('delimitation', 'Délimitation de l\'étude', $pr['delimitation'] ?? '', '', 3); ?>
        <?php recherche_ui_list_close(); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('5', 'Objectifs de la recherche'); ?>
        <?php recherche_ui_list_open(); ?>
          <?php recherche_ui_field('objectif_general', 'Objectif général', $ob['objectif_general'] ?? '', '', 3); ?>
          <?php recherche_ui_field('objectifs_specifiques', 'Objectifs spécifiques', $ob['objectifs_specifiques'] ?? '', '', 3); ?>
          <div class="pr-item">
            <?php recherche_ui_tableau('matrice', [
                'question' => 'Questions de recherche',
                'hypothese' => 'Hypothèses',
                'objectif' => 'Objectifs spécifiques',
                'methode' => 'Méthodes',
                'resultat' => 'Résultats attendus',
            ], $ob['matrice'] ?? [], 'Matrice de cohérence scientifique'); ?>
          </div>
        <?php recherche_ui_list_close(); ?>
      <?php recherche_ui_sec_close(); ?>

    <?php elseif ($etape === 3):
        $m = $all['methode'];
    ?>
      <?php recherche_ui_sec_open('6', 'Originalité et contribution scientifique'); ?>
        <?php recherche_ui_list_open(); ?>
          <?php recherche_ui_field('originalite', 'Originalité du projet (ce qui distingue le projet des travaux existants)', $m['originalite'] ?? '', '', 3); ?>
          <?php recherche_ui_field('contribution_theorique', 'Contribution théorique', $m['contribution_theorique'] ?? '', '', 3); ?>
          <?php recherche_ui_field('contribution_methodo', 'Contribution méthodologique', $m['contribution_methodo'] ?? '', '', 3); ?>
          <?php recherche_ui_field('contribution_pratique', 'Contribution pratique', $m['contribution_pratique'] ?? '', '', 3); ?>
        <?php recherche_ui_list_close(); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('7', 'Méthodologie de recherche'); ?>
        <?php recherche_ui_list_open(); ?>
          <?php recherche_ui_field('type_approche', 'Type et approche de recherche', $m['type_approche'] ?? '', '', 3); ?>
          <?php recherche_ui_field('site_cadre', 'Site et cadre de l\'étude', $m['site_cadre'] ?? '', '', 3); ?>
          <?php recherche_ui_field('population', 'Population ou unités d\'analyse', $m['population'] ?? '', '', 3); ?>
          <?php recherche_ui_field('echantillonnage', 'Échantillonnage', $m['echantillonnage'] ?? '', '', 3); ?>
          <div class="pr-item">
            <?php recherche_ui_tableau('variables', [
                'variable' => 'Variable ou dimension',
                'definition' => 'Définition opérationnelle',
                'indicateur' => 'Indicateur',
                'instrument' => 'Instrument de mesure',
            ], $m['variables'] ?? [], 'Variables et indicateurs'); ?>
          </div>
          <?php recherche_ui_field('techniques_collecte', 'Techniques et instruments de collecte', $m['techniques_collecte'] ?? '', '', 3); ?>
          <?php recherche_ui_field('procedure_collecte', 'Procédure de collecte', $m['procedure_collecte'] ?? '', '', 3); ?>
          <?php recherche_ui_field('methodes_analyse', 'Méthodes d\'analyse', $m['methodes_analyse'] ?? '', '', 3); ?>
          <?php recherche_ui_field('validite', 'Validité, fiabilité et reproductibilité', $m['validite'] ?? '', '', 3); ?>
          <?php recherche_ui_field('limites', 'Limites méthodologiques', $m['limites'] ?? '', '', 3); ?>
        <?php recherche_ui_list_close(); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('8', 'Considérations éthiques et protection des données'); ?>
        <?php recherche_ui_field('ethique', '', $m['ethique'] ?? '', '', 4, false, true); ?>
      <?php recherche_ui_sec_close(); ?>

    <?php elseif ($etape === 4):
        $me = $all['mise_en_oeuvre'];
    ?>
      <?php recherche_ui_sec_open('9', 'Résultats, livrables et effets attendus'); ?>
        <?php recherche_ui_list_open(); ?>
          <?php recherche_ui_field('resultats_scientifiques', 'Résultats scientifiques', $me['resultats_scientifiques'] ?? '', '', 3); ?>
          <?php recherche_ui_field('livrables', 'Livrables', $me['livrables'] ?? '', '', 3); ?>
          <?php recherche_ui_field('effets_attendus', 'Effets attendus', $me['effets_attendus'] ?? '', '', 3); ?>
          <?php recherche_ui_field('impact_attendu', 'Impact attendu', $me['impact_attendu'] ?? '', '', 3); ?>
        <?php recherche_ui_list_close(); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('10', 'Plan de travail et chronogramme'); ?>
        <?php recherche_ui_tableau('chronogramme', [
            'activite' => 'Activités',
            'responsable' => 'Responsable',
            'livrables' => 'Livrables',
            'debut' => 'Début',
            'fin' => 'Fin',
            'indicateurs' => 'Indicateurs',
        ], $me['chronogramme'] ?? [], 'Chronogramme'); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('11', 'Gouvernance et gestion du projet'); ?>
        <?php recherche_ui_list_open(); ?>
          <?php recherche_ui_field('organisation', 'Organisation', $me['organisation'] ?? '', '', 3); ?>
          <?php recherche_ui_field('coordination', 'Mécanismes de coordination', $me['coordination'] ?? '', '', 3); ?>
          <?php recherche_ui_field('partenariats', 'Partenariats scientifiques', $me['partenariats'] ?? '', '', 3); ?>
        <?php recherche_ui_list_close(); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('12', 'Suivi-évaluation et assurance qualité'); ?>
        <?php recherche_ui_tableau('suivi_eval', [
            'resultats' => 'Résultats',
            'indicateurs' => 'Indicateurs',
            'initial' => 'Situation initiale',
            'cible' => 'Cible',
            'source' => 'Source de vérification',
            'frequence' => 'Fréquence',
        ], $me['suivi_eval'] ?? [], 'Cadre de suivi-évaluation'); ?>
      <?php recherche_ui_sec_close(); ?>

    <?php else: /* étape 5 */
        $me = $all['mise_en_oeuvre'];
        $b = $all['budget_fin'];
    ?>
      <?php recherche_ui_sec_open('13', 'Analyse des risques'); ?>
        <?php recherche_ui_tableau('risques', [
            'risque' => 'Risques',
            'proba' => 'Probabilité',
            'impact' => 'Impact',
            'attenuation' => 'Mesures d\'atténuation',
            'responsable' => 'Responsable',
        ], $me['risques'] ?? [], 'Matrice des risques'); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('14', 'Diffusion, publication et valorisation'); ?>
        <?php recherche_ui_list_open(); ?>
          <?php recherche_ui_field('publications', 'Publications scientifiques', $b['publications'] ?? '', '', 3); ?>
          <?php recherche_ui_field('communications', 'Communications scientifiques', $b['communications'] ?? '', '', 3); ?>
          <?php recherche_ui_field('vulgarisation', 'Vulgarisation', $b['vulgarisation'] ?? '', '', 3); ?>
          <?php recherche_ui_field('valorisation_pi', 'Valorisation et propriété intellectuelle', $b['valorisation_pi'] ?? '', '', 3); ?>
        <?php recherche_ui_list_close(); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('15', 'Durabilité du projet'); ?>
        <?php recherche_ui_field('durabilite', '', $b['durabilite'] ?? '', '', 4, false, true); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('16', 'Budget détaillé'); ?>
        <?php recherche_ui_tableau('budget', [
            'activite' => 'Activités',
            'quantite' => 'Quantité',
            'cout_unitaire' => 'Coût unitaire',
            'cout_total' => 'Coût total',
            'justification' => 'Justification',
        ], $b['budget'] ?? [], 'Budget', true); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('17', 'Références bibliographiques'); ?>
        <?php recherche_ui_field('references', '', $b['references'] ?? '', '', 8, false, true); ?>
      <?php recherche_ui_sec_close(); ?>

      <?php recherche_ui_sec_open('18', 'Annexes'); ?>
        <?php
        $annexes = $b['annexes_fichiers'] ?? [];
        if ($annexes):
        ?>
        <div class="pr-field" style="margin-bottom:14px;">
          <div class="pr-label">Fichiers déjà joints</div>
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
        <div class="pr-field" id="prAnnexesBox">
          <div class="pr-label">Ajouter des fichiers</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px;">
            <button type="button" class="pr-btn-add" id="prAnnexesAddBtn">
              <i class="fa-solid fa-plus"></i> Ajouter un fichier
            </button>
            <span style="font-size:.75rem;color:#64748b;">Un par un, ou plusieurs d’un coup — autant de fois que vous voulez</span>
          </div>
          <input type="file" id="annexesPicker" style="display:none;" multiple
                 accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.txt,.csv,.jpg,.jpeg,.png,.webp,.gif">
          <input type="file" name="annexes[]" id="annexes" multiple style="display:none;"
                 accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.txt,.csv,.jpg,.jpeg,.png,.webp,.gif">
          <ul id="prAnnexesPending" style="list-style:none;margin:0;padding:0;"></ul>
        </div>
      <?php recherche_ui_sec_close(); ?>
    <?php endif; ?>

    <div class="pr-nav">
      <?php if ($etape > 1): ?>
      <button type="submit" name="_precedent" value="1" class="pr-btn pr-btn-ghost">
        <i class="fa-solid fa-arrow-left"></i> Précédent
      </button>
      <?php endif; ?>
      <div class="spacer"></div>
      <button type="submit" name="_suivant" value="1" class="pr-btn pr-btn-primary">
        Suivant <i class="fa-solid fa-arrow-right"></i>
      </button>
    </div>
  </form>
  <?php recherche_ui_tableau_script(); ?>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
