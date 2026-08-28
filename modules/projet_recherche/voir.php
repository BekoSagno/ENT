<?php
// modules/projet_recherche/voir.php — Aperçu complet du dossier
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
if (!recherche_peut_gerer() && (int)$d['cree_par'] !== (int)user_id()) {
    http_response_code(403);
    exit('Accès refusé.');
}

$peut_mod = recherche_peut_modifier($d);
$c = $d['contenu_arr'];
$etapes = recherche_etapes();

$page_titre = 'Aperçu';
$page_breadcrumb = 'Projets · ' . $d['code'];
$module_actif = 'PROJETS';
$projet_sous_actif = 'RECHERCHE';

include __DIR__ . '/../../views/layout/header.php';
recherche_ui_styles();

function _pr_bloc(string $label, $val): void {
    echo '<div class="pr-voir-bloc"><label>' . e($label) . '</label>' . recherche_ui_txt(is_string($val) ? $val : '') . '</div>';
}
?>

<div class="pr-wrap">
  <?php recherche_ui_topbar($d, 0, 'index.php', false); ?>

  <div class="pr-voir-actions">
    <?php if ($peut_mod && $d['statut'] !== 'FINALISE'): ?>
    <form method="post" action="actions.php" style="display:inline;" onsubmit="return confirm('Marquer ce projet comme finalisé ?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="statut">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="statut" value="FINALISE">
      <button type="submit" class="btn btn-secondary btn-sm">Finaliser</button>
    </form>
    <?php elseif ($peut_mod && $d['statut'] === 'FINALISE'): ?>
    <form method="post" action="actions.php" style="display:inline;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="statut">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="statut" value="EN_COURS">
      <button type="submit" class="btn btn-secondary btn-sm">Rouvrir</button>
    </form>
    <?php endif; ?>
    <?php if ($peut_mod): ?>
    <form method="post" action="actions.php" style="display:inline;margin-left:auto;" onsubmit="return confirm('Supprimer définitivement ce projet ?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="supprimer">
      <input type="hidden" name="id" value="<?= $id ?>">
      <button type="submit" class="btn btn-secondary btn-sm" style="color:#dc2626;border-color:#fecaca;">Supprimer</button>
    </form>
    <?php endif; ?>
  </div>

  <?php
  recherche_ui_voir_section(1, $etapes[1]['titre'], function () use ($c) {
      $p = $c['presentation'];
      _pr_bloc('Institution porteuse', $p['institution_porteuse'] ?? '');
      _pr_bloc('Ancrage institutionnel', $p['ancrage'] ?? '');
      echo '<div class="pr-voir-bloc"><label>Équipe de recherche</label>';
      echo recherche_ui_tableau_voir([
          'membre' => 'Membre', 'grade' => 'Grade / spécialité', 'institution' => 'Institution',
          'responsabilites' => 'Responsabilités', 'temps' => 'Temps',
      ], $p['equipe'] ?? []);
      echo '</div>';
  }, true);

  recherche_ui_voir_section(2, $etapes[2]['titre'], function () use ($c) {
      $p = $c['contexte'];
      _pr_bloc('Situation actuelle', $p['situation_actuelle'] ?? '');
      _pr_bloc('Justification scientifique', $p['justification_scientifique'] ?? '');
      _pr_bloc('Justification socioéconomique', $p['justification_socio'] ?? '');
  });

  recherche_ui_voir_section(3, $etapes[3]['titre'], function () use ($c) {
      $p = $c['litterature'];
      _pr_bloc('Revue critique', $p['revue_critique'] ?? '');
      _pr_bloc('Lacune scientifique', $p['lacune'] ?? '');
      _pr_bloc('Cadre théorique', $p['cadre_theorique'] ?? '');
      _pr_bloc('Cadre conceptuel', $p['cadre_conceptuel'] ?? '');
  });

  recherche_ui_voir_section(4, $etapes[4]['titre'], function () use ($c) {
      $p = $c['problematique'];
      _pr_bloc('Constat de départ', $p['constat'] ?? '');
      _pr_bloc('Problème scientifique', $p['probleme_scientifique'] ?? '');
      _pr_bloc('Question principale', $p['question_principale'] ?? '');
      _pr_bloc('Questions spécifiques', $p['questions_specifiques'] ?? '');
      _pr_bloc('Hypothèse générale', $p['hypothese_generale'] ?? '');
      _pr_bloc('Hypothèses spécifiques', $p['hypotheses_specifiques'] ?? '');
      _pr_bloc('Délimitation', $p['delimitation'] ?? '');
  });

  recherche_ui_voir_section(5, $etapes[5]['titre'], function () use ($c) {
      $p = $c['objectifs'];
      _pr_bloc('Objectif général', $p['objectif_general'] ?? '');
      _pr_bloc('Objectifs spécifiques', $p['objectifs_specifiques'] ?? '');
      echo '<div class="pr-voir-bloc"><label>Matrice de cohérence</label>';
      echo recherche_ui_tableau_voir([
          'question' => 'Question', 'hypothese' => 'Hypothèse', 'objectif' => 'Objectif',
          'methode' => 'Méthode', 'resultat' => 'Résultat attendu',
      ], $p['matrice'] ?? []);
      echo '</div>';
  });

  recherche_ui_voir_section(6, $etapes[6]['titre'], function () use ($c) {
      $p = $c['methode'];
      _pr_bloc('Originalité', $p['originalite'] ?? '');
      _pr_bloc('Contribution théorique', $p['contribution_theorique'] ?? '');
      _pr_bloc('Contribution méthodologique', $p['contribution_methodo'] ?? '');
      _pr_bloc('Contribution pratique', $p['contribution_pratique'] ?? '');
      _pr_bloc('Type et approche', $p['type_approche'] ?? '');
      _pr_bloc('Site et cadre', $p['site_cadre'] ?? '');
      _pr_bloc('Population', $p['population'] ?? '');
      _pr_bloc('Échantillonnage', $p['echantillonnage'] ?? '');
      echo '<div class="pr-voir-bloc"><label>Variables et indicateurs</label>';
      echo recherche_ui_tableau_voir([
          'variable' => 'Variable', 'definition' => 'Définition', 'indicateur' => 'Indicateur', 'instrument' => 'Instrument',
      ], $p['variables'] ?? []);
      echo '</div>';
      _pr_bloc('Techniques de collecte', $p['techniques_collecte'] ?? '');
      _pr_bloc('Procédure de collecte', $p['procedure_collecte'] ?? '');
      _pr_bloc('Méthodes d\'analyse', $p['methodes_analyse'] ?? '');
      _pr_bloc('Validité / fiabilité', $p['validite'] ?? '');
      _pr_bloc('Limites', $p['limites'] ?? '');
      _pr_bloc('Éthique et données', $p['ethique'] ?? '');
  });

  recherche_ui_voir_section(7, $etapes[7]['titre'], function () use ($c) {
      $p = $c['mise_en_oeuvre'];
      _pr_bloc('Résultats scientifiques', $p['resultats_scientifiques'] ?? '');
      _pr_bloc('Livrables', $p['livrables'] ?? '');
      _pr_bloc('Effets attendus', $p['effets_attendus'] ?? '');
      _pr_bloc('Impact attendu', $p['impact_attendu'] ?? '');
      echo '<div class="pr-voir-bloc"><label>Chronogramme</label>';
      echo recherche_ui_tableau_voir([
          'activite' => 'Activités', 'responsable' => 'Responsable', 'livrables' => 'Livrables',
          'debut' => 'Début', 'fin' => 'Fin', 'indicateurs' => 'Indicateurs',
      ], $p['chronogramme'] ?? []);
      echo '</div>';
      _pr_bloc('Organisation', $p['organisation'] ?? '');
      _pr_bloc('Coordination', $p['coordination'] ?? '');
      _pr_bloc('Partenariats', $p['partenariats'] ?? '');
      echo '<div class="pr-voir-bloc"><label>Suivi-évaluation</label>';
      echo recherche_ui_tableau_voir([
          'resultats' => 'Résultats', 'indicateurs' => 'Indicateurs', 'initial' => 'Situation initiale',
          'cible' => 'Cible', 'source' => 'Source', 'frequence' => 'Fréquence',
      ], $p['suivi_eval'] ?? []);
      echo '</div>';
      echo '<div class="pr-voir-bloc"><label>Risques</label>';
      echo recherche_ui_tableau_voir([
          'risque' => 'Risque', 'proba' => 'Probabilité', 'impact' => 'Impact',
          'attenuation' => 'Atténuation', 'responsable' => 'Responsable',
      ], $p['risques'] ?? []);
      echo '</div>';
  });

  recherche_ui_voir_section(8, $etapes[8]['titre'], function () use ($c, $id) {
      $p = $c['budget_fin'];
      echo '<div class="pr-voir-bloc"><label>Budget détaillé</label>';
      echo recherche_ui_tableau_voir([
          'activite' => 'Activités', 'quantite' => 'Quantité', 'cout_unitaire' => 'Coût unitaire',
          'cout_total' => 'Coût total', 'justification' => 'Justification',
      ], $p['budget'] ?? []);
      echo '</div>';
      _pr_bloc('Publications', $p['publications'] ?? '');
      _pr_bloc('Communications', $p['communications'] ?? '');
      _pr_bloc('Vulgarisation', $p['vulgarisation'] ?? '');
      _pr_bloc('Valorisation / PI', $p['valorisation_pi'] ?? '');
      _pr_bloc('Durabilité', $p['durabilite'] ?? '');
      _pr_bloc('Références bibliographiques', $p['references'] ?? '');
      echo '<div class="pr-voir-bloc"><label>Annexes</label>';
      $files = $p['annexes_fichiers'] ?? [];
      if (!$files) {
          echo recherche_ui_txt('');
      } else {
          echo '<ul style="margin:0;padding-left:18px;">';
          foreach ($files as $af) {
              $fn = (string)($af['fichier'] ?? '');
              $nm = (string)($af['nom'] ?? $fn);
              echo '<li><a href="download_annexe.php?id=' . (int)$id . '&f=' . rawurlencode($fn) . '" target="_blank">' . e($nm) . '</a></li>';
          }
          echo '</ul>';
      }
      echo '</div>';
  });
  ?>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
