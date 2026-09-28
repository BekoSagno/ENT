<?php
// modules/demandes/demandes.php — Mes demandes (congés, absences, missions)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/personnel.php';
require_once __DIR__ . '/../../models/demandes.php';

session_demarrer();
exiger_connexion();
demandes_assurer_schema();

$uid     = (int)user_id();
$mon_pid = personnel_assurer_profil($uid);
$peut_rh = a_permission('PERSONNEL', 'VALIDER');
$est_dg  = demandes_est_dg();
$annee   = (int)date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Session expirée.');
        redirect('/modules/demandes/demandes.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'demander_conge') {
        $r = demandes_creer_conge($mon_pid, clean($_POST['type_conge'] ?? ''),
            clean($_POST['date_debut'] ?? ''), clean($_POST['date_fin'] ?? ''), clean($_POST['motif'] ?? ''),
            clean($_POST['type_conge_autre'] ?? ''));
        set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Demande de congé envoyée.' : ($r['message'] ?? 'Erreur.'));
        redirect('/modules/demandes/demandes.php?cat=conge');
    }

    if ($action === 'demander_absence') {
        $r = demandes_creer_absence($mon_pid, clean($_POST['type_absence'] ?? ''),
            clean($_POST['date_debut'] ?? ''), clean($_POST['date_fin'] ?? ''), clean($_POST['motif'] ?? ''),
            clean($_POST['type_absence_autre'] ?? ''));
        set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Demande d\'absence envoyée.' : ($r['message'] ?? 'Erreur.'));
        redirect('/modules/demandes/demandes.php?cat=absence');
    }

    if ($action === 'demander_mission') {
        $r = demandes_creer_mission($mon_pid, $_POST);
        set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Ordre de mission soumis au circuit de validation.' : ($r['message'] ?? 'Erreur.'));
        redirect('/modules/demandes/demandes.php?cat=mission');
    }

    if ($action === 'annuler') {
        $r = demandes_annuler((int)($_POST['did'] ?? 0), $mon_pid);
        set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Demande annulée.' : ($r['message'] ?? 'Erreur.'));
        redirect('/modules/demandes/demandes.php');
    }

    if ($action === 'valider_resp') {
        $did = (int)($_POST['did'] ?? 0);
        $d   = demandes_par_id($did);
        if (!$d || !demandes_peut_valider_resp($d, $mon_pid, $peut_rh)) {
            set_flash('error', 'Action non autorisée.');
        } else {
            $r = demandes_valider_resp($did, clean($_POST['commentaire'] ?? ''));
            set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? ($r['message'] ?? 'Demande traitée.') : ($r['message'] ?? 'Erreur.'));
        }
        redirect('/modules/demandes/demandes.php?tab=valider');
    }

    if ($action === 'valider_dg') {
        $did = (int)($_POST['did'] ?? 0);
        $d   = demandes_par_id($did);
        if (!$d || !demandes_peut_valider_dg($d)) {
            set_flash('error', 'Seul le DG peut valider cet ordre de mission.');
        } else {
            $r = demandes_valider_dg($did, clean($_POST['commentaire'] ?? ''));
            set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Ordre de mission approuvé — document disponible.' : ($r['message'] ?? 'Erreur.'));
        }
        redirect('/modules/demandes/demandes.php?tab=valider');
    }

    if ($action === 'refuser') {
        $did = (int)($_POST['did'] ?? 0);
        $d   = demandes_par_id($did);
        $ok  = $d && (
            demandes_peut_valider_resp($d, $mon_pid, $peut_rh) ||
            demandes_peut_valider_dg($d)
        );
        if (!$ok) {
            set_flash('error', 'Action non autorisée.');
        } else {
            $r = demandes_refuser($did, clean($_POST['commentaire'] ?? ''));
            set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Demande refusée.' : ($r['message'] ?? 'Erreur.'));
        }
        redirect('/modules/demandes/demandes.php?tab=valider');
    }
    redirect('/modules/demandes/demandes.php');
}

$a_valider = demandes_a_valider($mon_pid, $peut_rh, $est_dg);
$peut_voir_validation = $peut_rh || $est_dg || count($a_valider) > 0;
$traitees  = $peut_voir_validation ? demandes_lister_traitees_par($uid) : [];

// Option A : clic module avec alerte → pointer vers le message (pas d'onglet Notifications)
if (!empty($_GET['voir_alerte']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $cible = demandes_alerte_cible($uid, $mon_pid, $peut_rh, $est_dg);
    if (!empty($cible['demande_id'])) {
        demandes_notif_marquer_demande_lue($uid, (int)$cible['demande_id']);
    } elseif (!empty($cible['notif'])) {
        demandes_notif_tout_lu($uid);
    }
    if (($cible['url'] ?? '') !== '' && ($cible['url'] !== '/modules/demandes/demandes.php' || !empty($cible['demande_id']) || !empty($cible['notif']))) {
        redirect($cible['url']);
    }
}

$tab = $_GET['tab'] ?? 'mes';
$cat = $_GET['cat'] ?? 'toutes';
$focus_id = (int)($_GET['focus'] ?? 0);
$focus_demande = $focus_id > 0 ? demandes_par_id($focus_id) : null;
if ($focus_id > 0) {
    demandes_notif_marquer_demande_lue($uid, $focus_id);
}

if ($tab === 'valider' && !$peut_voir_validation) $tab = 'mes';
if ($tab === 'historique_val' && !$peut_voir_validation) $tab = 'mes';
if (!in_array($cat, ['toutes', 'conge', 'absence', 'mission', 'historique'], true)) $cat = 'toutes';
if (!in_array($tab, ['mes', 'valider', 'historique_val'], true)) $tab = 'mes';

// Si focus sur une demande traitée et onglet mes sans historique → forcer historique
if ($focus_demande && $tab === 'mes' && $cat !== 'historique'
    && in_array($focus_demande['statut'] ?? '', ['APPROUVE', 'REFUSE', 'ANNULE'], true)
) {
    $cat = 'historique';
}

// Focus demandé mais plus dans « À valider » → basculer vers l'historique
if ($focus_id > 0 && $tab === 'valider' && $focus_demande) {
    $encore = false;
    foreach ($a_valider as $av) {
        if ((int)$av['id'] === $focus_id) { $encore = true; break; }
    }
    if (!$encore) {
        if ($peut_voir_validation && in_array($focus_demande['statut'] ?? '', ['APPROUVE', 'REFUSE'], true)) {
            $tab = 'historique_val';
        } else {
            $tab = 'mes';
            $cat = 'historique';
        }
    }
}

$solde_repos = demandes_solde_repos($mon_pid, $annee);
$cat_filtre  = in_array($cat, ['conge', 'absence', 'mission'], true) ? strtoupper($cat) : null;
$mes_dem     = demandes_lister_personnel($mon_pid, $cat_filtre);
$perso_mission = ($cat === 'mission') ? demandes_personnel_actifs_pour_mission() : [];
$moi_profil  = ($cat === 'mission') ? personnel_par_id($mon_pid) : null;

$cat_titres = [
    'conge'      => 'Congé',
    'absence'    => 'Absence',
    'mission'    => 'Mission',
    'historique' => 'Toutes mes demandes',
];

$stc = [
    'EN_ATTENTE'    => ['En attente', '#fef3c7', '#92400e'],
    'EN_ATTENTE_DG' => ['Attente DG', '#dbeafe', '#1e40af'],
    'APPROUVE'      => ['Approuvé', '#dcfce7', '#166534'],
    'REFUSE'        => ['Refusé', '#fee2e2', '#991b1b'],
    'ANNULE'        => ['Annulé', '#f3f4f6', '#6b7280'],
];

$cat_icons = [
    'CONGE'   => 'fa-umbrella-beach',
    'ABSENCE' => 'fa-clock',
    'MISSION' => 'fa-plane-departure',
];

$page_titre      = 'Mes demandes';
$page_breadcrumb = 'RH · Mes demandes';
$module_actif    = 'PERSONNEL_DEMANDES';
include __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/_demandes_ui.php';
demandes_ui_styles();
?>

<div class="dm-tabs">
  <a href="?tab=mes" class="dm-tab <?= $tab === 'mes' ? 'active' : '' ?>" data-tab="mes">
    <i class="fa-solid fa-inbox"></i> Mes demandes
  </a>
  <?php if ($peut_voir_validation): ?>
  <a href="?tab=valider" class="dm-tab <?= $tab === 'valider' ? 'active' : '' ?>" data-tab="valider">
    <i class="fa-solid fa-clipboard-check"></i> À valider
    <?php if ($a_valider): ?><span class="dm-badge-warn"><?= count($a_valider) ?></span><?php endif; ?>
  </a>
  <?php endif; ?>
</div>

<?php if ($focus_demande):
    $fb = $stc[$focus_demande['statut']] ?? $stc['EN_ATTENTE'];
    $is_refus = ($focus_demande['statut'] ?? '') === 'REFUSE';
    $is_ok    = ($focus_demande['statut'] ?? '') === 'APPROUVE';
?>
<div class="dm-alerte-focus <?= $is_refus ? 'refus' : ($is_ok ? 'ok' : 'info') ?>">
  <div class="dm-alerte-focus-ic">
    <i class="fa-solid <?= $is_refus ? 'fa-circle-xmark' : ($is_ok ? 'fa-circle-check' : 'fa-bell') ?>"></i>
  </div>
  <div class="dm-alerte-focus-body">
    <strong><?= e(demandes_libelle_statut($focus_demande['statut'] ?? '')) ?>
      <?php if (!empty($focus_demande['reference'])): ?> — <?= e($focus_demande['reference']) ?><?php endif; ?>
    </strong>
    <?php if (!empty($focus_demande['commentaire_validation'])): ?>
    <div><?= e($focus_demande['commentaire_validation']) ?></div>
    <?php else: ?>
    <div><?= e(demandes_type_libelle($focus_demande)) ?> · <?= e(format_date($focus_demande['date_debut'])) ?> → <?= e(format_date($focus_demande['date_fin'])) ?></div>
    <?php endif; ?>
  </div>
  <span class="dm-statut" style="background:<?= $fb[1] ?>;color:<?= $fb[2] ?>;"><?= e($fb[0] ?? demandes_libelle_statut($focus_demande['statut'] ?? '')) ?></span>
</div>
<?php endif; ?>

<!-- ===== MES DEMANDES ===== -->
<div class="dm-pane <?= $tab === 'mes' ? 'active' : '' ?>" id="pane-mes">

<?php if ($cat === 'toutes'): ?>
  <div class="dm-wrap">
    <p class="dm-choix-title">Quel type de demande&nbsp;?</p>
    <div class="dm-choices">
      <a href="?tab=mes&cat=conge" class="dm-choice">
        <div class="dm-choice-ic" style="background:#dbeafe;color:#1d4ed8;"><i class="fa-solid fa-umbrella-beach"></i></div>
        <h3>Congé</h3>
        <p>Repos, congé annuel, exceptionnel… Décompté sur le quota de repos.</p>
      </a>
      <a href="?tab=mes&cat=absence" class="dm-choice">
        <div class="dm-choice-ic" style="background:#fef3c7;color:#b45309;"><i class="fa-solid fa-clock"></i></div>
        <h3>Absence</h3>
        <p>Rendez-vous médical, démarche administrative, courte absence…</p>
      </a>
      <a href="?tab=mes&cat=mission" class="dm-choice">
        <div class="dm-choice-ic" style="background:#dcfce7;color:#166534;"><i class="fa-solid fa-plane-departure"></i></div>
        <h3>Mission</h3>
        <p>Déplacement professionnel — circuit responsable puis validation DG.</p>
      </a>
    </div>
    <div class="dm-link-hist-wrap">
      <?php if ($peut_voir_validation): ?>
      <a href="?tab=historique_val" class="dm-link-hist">
        <i class="fa-solid fa-clock-rotate-left"></i> Voir l'historique des validations
      </a>
      <?php else: ?>
      <a href="?tab=mes&cat=historique" class="dm-link-hist">
        <i class="fa-solid fa-clock-rotate-left"></i> Voir tout l'historique
      </a>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>
  <div class="dm-wrap">
    <div class="dm-back">
      <a href="?tab=mes" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour au choix</a>
    </div>

  <?php if (in_array($cat, ['conge', 'absence'], true)): ?>
  <h3 class="dm-section-title">Quota de repos <?= $annee ?></h3>
  <div class="dm-soldes">
    <div class="dm-solde dm-solde-repos">
      <div class="dm-solde-lbl">Jours restants</div>
      <div class="dm-solde-val"><?= demandes_fmt_jours($solde_repos['restants']) ?> <span>/ <?= demandes_fmt_jours($solde_repos['droit']) ?> j.</span></div>
      <div class="dm-solde-sub">pris <?= demandes_fmt_jours($solde_repos['pris']) ?> j. · congés + absences</div>
    </div>
  </div>
  <?php endif; ?>

  <div class="dm-grid2<?= $cat === 'historique' ? ' dm-grid1' : '' ?>">

    <?php if (in_array($cat, ['conge', 'absence', 'mission'], true)): ?>
    <div class="card">
      <div class="card-header"><h2>Nouvelle demande — <?= e($cat_titres[$cat] ?? '') ?></h2></div>
      <div class="card-body">

        <?php if ($cat === 'conge'): ?>
        <form method="post" id="form-conge">
          <?= csrf_field() ?><input type="hidden" name="action" value="demander_conge">
          <div class="form-group"><label class="form-label">Type de congé *</label>
            <select name="type_conge" id="type_conge_select" class="form-control" required>
              <?php foreach (DEMANDES_TYPES_CONGE as $code => $lbl): ?>
              <option value="<?= e($code) ?>"><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="form-group" id="conge-autre-wrap" style="display:none;">
            <label class="form-label">Précisez le type *</label>
            <input type="text" name="type_conge_autre" id="type_conge_autre" class="form-control"
                   maxlength="150" placeholder="Ex. congé sans solde, événement familial…">
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Du *</label><input type="date" name="date_debut" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Au *</label><input type="date" name="date_fin" class="form-control" required></div>
          </div>
          <div class="form-group"><label class="form-label">Motif</label><textarea name="motif" class="form-control" rows="2"></textarea></div>
          <p class="dm-hint"><i class="fa-solid fa-info-circle"></i> Décompté sur le quota de 30 j. (week-ends exclus).</p>
          <button class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
        </form>
        <?php endif; ?>

        <?php if ($cat === 'absence'): ?>
        <form method="post" id="form-absence">
          <?= csrf_field() ?><input type="hidden" name="action" value="demander_absence">
          <div class="form-group"><label class="form-label">Type d'absence *</label>
            <select name="type_absence" id="type_absence_select" class="form-control" required>
              <?php foreach (DEMANDES_TYPES_ABSENCE as $code => $lbl): ?>
              <option value="<?= e($code) ?>"><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="form-group dm-absence-autre" id="absence-autre-wrap" style="display:none;">
            <label class="form-label">Précisez le type *</label>
            <input type="text" name="type_absence_autre" id="type_absence_autre" class="form-control"
                   maxlength="150" placeholder="Ex. réunion externe, démarche administrative…">
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Du *</label><input type="date" name="date_debut" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Au *</label><input type="date" name="date_fin" class="form-control" required></div>
          </div>
          <div class="form-group"><label class="form-label">Motif / précisions</label><textarea name="motif" class="form-control" rows="2"></textarea></div>
          <button class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
        </form>
        <?php endif; ?>

        <?php if ($cat === 'mission'): ?>
        <form method="post" id="form-mission">
          <?= csrf_field() ?><input type="hidden" name="action" value="demander_mission">

          <div class="form-group">
            <label class="form-label">Agent(s) missionné(s) *</label>
            <div class="dm-agent-me">
              <strong>Vous :</strong>
              <?= e(trim(($moi_profil['prenoms'] ?? '') . ' ' . ($moi_profil['nom'] ?? ''))) ?>
              <?php if (!empty($moi_profil['fonction'])): ?> — <?= e($moi_profil['fonction']) ?><?php endif; ?>
              <span class="dm-agent-me-note">(toujours inclus)</span>
            </div>
            <?php
              $collegues = array_values(array_filter($perso_mission, static fn($p) => (int)$p['id'] !== $mon_pid));
            ?>
            <?php if (!$collegues): ?>
              <p class="dm-hint" style="margin-top:8px;">Aucun autre agent éligible pour l’instant.</p>
            <?php else: ?>
              <div class="dm-agent-picker" id="mission-agents-picker">
                <div class="dm-agent-chips" id="mission-agents-chips" hidden></div>
                <button type="button" class="dm-agent-toggle form-control" id="mission-agents-toggle"
                        aria-expanded="false" aria-controls="mission-agents-dropdown">
                  <span class="dm-agent-toggle-label">Choisir un collègue…</span>
                  <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="dm-agent-dropdown" id="mission-agents-dropdown" hidden>
                  <input type="search" id="mission-agents-search" class="form-control dm-agent-search"
                         placeholder="Rechercher…" autocomplete="off" aria-label="Rechercher un collègue">
                  <div class="dm-agent-list" role="group" aria-label="Collègues à missionner">
                    <?php foreach ($collegues as $pers):
                      $lbl = trim(($pers['prenoms'] ?? '') . ' ' . ($pers['nom'] ?? ''));
                      $fonc = trim((string)($pers['fonction'] ?? ''));
                      $cid = 'agent-' . (int)$pers['id'];
                    ?>
                    <label class="dm-agent-item" for="<?= e($cid) ?>"
                           data-label="<?= e(mb_strtolower($lbl . ' ' . $fonc)) ?>"
                           data-name="<?= e($lbl) ?>">
                      <input type="checkbox" name="agents[]" id="<?= e($cid) ?>" value="<?= (int)$pers['id'] ?>">
                      <span class="dm-agent-item-text">
                        <span class="dm-agent-name"><?= e($lbl) ?></span>
                        <?php if ($fonc !== ''): ?><span class="dm-agent-fonc"><?= e($fonc) ?></span><?php endif; ?>
                      </span>
                    </label>
                    <?php endforeach; ?>
                  </div>
                  <p class="dm-agent-empty" id="mission-agents-empty" hidden>Aucun collègue ne correspond à la recherche.</p>
                </div>
              </div>
              <p class="dm-hint" style="margin-top:6px;">Ouvrez la liste pour ajouter un ou plusieurs collègues à l’ordre de mission.</p>
            <?php endif; ?>
          </div>

          <div class="form-group"><label class="form-label">De se rendre à (destination) *</label>
            <input type="text" name="destination" class="form-control" required placeholder="Ex. Kindia, Kankan…"></div>
          <div class="form-group"><label class="form-label">Objet *</label>
            <textarea name="objet_mission" id="objet_mission" class="form-control" rows="7"
                      aria-required="true" placeholder="Objet de la mission…"></textarea>
            <p class="dm-hint" style="margin-top:6px;">
              Vous pouvez coller un texte depuis Word, puis appliquer le gras, l’italique,
              les listes ou l’alignement justifié.
            </p>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Date de départ *</label><input type="date" name="date_debut" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Date de retour *</label><input type="date" name="date_fin" class="form-control" required></div>
          </div>
          <div class="form-group"><label class="form-label">Moyen de transport</label>
            <input type="text" name="moyen_transport" class="form-control" placeholder="Taxi, véhicule, avion…"></div>
          <div class="form-group"><label class="form-label">Frais estimés <span style="font-weight:400;color:var(--text-muted);">(interne, non imprimé)</span></label>
            <input type="text" name="frais_estimes" class="form-control"></div>
          <div class="form-group"><label class="form-label">Prise en charge <span style="font-weight:400;color:var(--text-muted);">(interne)</span></label>
            <textarea name="prise_en_charge" class="form-control" rows="2" placeholder="Hébergement, per diem…"></textarea></div>
          <div class="form-group"><label class="form-label">Observations <span style="font-weight:400;color:var(--text-muted);">(interne)</span></label>
            <textarea name="motif" class="form-control" rows="2"></textarea></div>
          <p class="dm-hint"><i class="fa-solid fa-info-circle"></i> Circuit : responsable → DG → document officiel (Réf N° xxx/CRICT/DG/AAAA).</p>
          <button class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Soumettre l'ordre de mission</button>
        </form>
        <?php endif; ?>

      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header"><h2><?= $cat === 'historique' ? 'Historique complet' : 'Historique' ?></h2></div>
      <div class="card-body">
        <?php if (empty($mes_dem)): ?>
          <p class="dm-empty">Aucune demande<?= $cat_filtre ? ' dans cette catégorie' : '' ?>.</p>
        <?php endif; ?>
        <?php foreach ($mes_dem as $d):
            $b = $stc[$d['statut']] ?? $stc['EN_ATTENTE'];
            $icon = $cat_icons[$d['categorie']] ?? 'fa-file';
        ?>
        <div class="dm-card<?= $focus_id === (int)$d['id'] ? ' dm-card-focus' : '' ?>" id="demande-<?= (int)$d['id'] ?>">
          <div class="dm-card-head">
            <div>
              <div class="dm-card-title">
                <i class="fa-solid <?= $icon ?>"></i>
                <?= e($d['type_libelle']) ?>
                <?php if (!empty($d['reference'])): ?><span class="dm-ref"><?= e($d['reference']) ?></span><?php endif; ?>
              </div>
              <div class="dm-card-meta"><?= e(format_date($d['date_debut'])) ?> → <?= e(format_date($d['date_fin'])) ?> · <?= demandes_fmt_jours($d['nombre_jours']) ?> j.</div>
              <?php if ($d['categorie'] === 'MISSION' && !empty($d['destination'])): ?>
              <div class="dm-card-meta"><i class="fa-solid fa-location-dot"></i> <?= e($d['destination']) ?></div>
              <?php
                $ag = demandes_mission_agents((int)$d['id']);
                if (count($ag) > 1):
                  $noms = array_map(fn($x) => trim(($x['prenoms'] ?? '') . ' ' . ($x['nom'] ?? '')), $ag);
              ?>
              <div class="dm-card-meta"><i class="fa-solid fa-users"></i> <?= e(implode(', ', $noms)) ?></div>
              <?php endif; ?>
              <?php if (!empty($d['ordre_mission_numero'])): ?>
              <div class="dm-card-meta"><strong>Réf :</strong> <?= e($d['ordre_mission_numero']) ?></div>
              <?php endif; ?>
              <?php endif; ?>
            </div>
            <span class="dm-statut" style="background:<?= $b[1] ?>;color:<?= $b[2] ?>;"><?= demandes_libelle_statut($d['statut']) ?></span>
          </div>
          <?php if (!empty($d['commentaire_validation'])): ?>
          <div class="dm-comment"><i class="fa-solid fa-comment"></i> <?= e($d['commentaire_validation']) ?></div>
          <?php endif; ?>
          <div class="dm-card-actions">
            <?php if ($d['categorie'] === 'MISSION' && $d['statut'] === 'APPROUVE'): ?>
            <a href="mission_print.php?id=<?= (int)$d['id'] ?>" class="btn btn-primary btn-sm" target="_blank">
              <i class="fa-solid fa-print"></i> Ordre de mission (PDF)
            </a>
            <?php endif; ?>
            <?php if (in_array($d['statut'], ['EN_ATTENTE', 'EN_ATTENTE_DG'], true) || ($d['statut'] === 'APPROUVE' && $d['date_debut'] > date('Y-m-d'))): ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Annuler cette demande ?');">
              <?= csrf_field() ?><input type="hidden" name="action" value="annuler"><input type="hidden" name="did" value="<?= (int)$d['id'] ?>">
              <button class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Annuler</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
  </div>
<?php endif; ?>

</div>

<!-- ===== À VALIDER ===== -->
<?php if ($peut_voir_validation): ?>
<div class="dm-pane <?= $tab === 'valider' ? 'active' : '' ?>" id="pane-valider">
  <div class="dm-wrap">
  <div class="card"><div class="card-body">
    <?php if (empty($a_valider)): ?><p class="dm-empty">Aucune demande en attente.</p><?php endif; ?>
    <?php foreach ($a_valider as $d):
        $icon = $cat_icons[$d['categorie']] ?? 'fa-file';
    ?>
    <div class="dm-card<?= $focus_id === (int)$d['id'] ? ' dm-card-focus' : '' ?>" id="demande-<?= (int)$d['id'] ?>">
      <div class="dm-card-head">
        <div>
          <div class="dm-card-title">
            <i class="fa-solid <?= $icon ?>"></i>
            <?= e($d['employe_nom']) ?> — <?= e($d['type_libelle']) ?>
          </div>
          <div class="dm-card-meta"><?= e(format_date($d['date_debut'])) ?> → <?= e(format_date($d['date_fin'])) ?> · <?= demandes_fmt_jours($d['nombre_jours']) ?> j.</div>
          <?php if (!empty($d['motif'])): ?><div class="dm-card-meta"><?= e($d['motif']) ?></div><?php endif; ?>
          <?php if ($d['categorie'] === 'MISSION' && !empty($d['destination'])): ?>
          <div class="dm-card-meta"><strong>Destination :</strong> <?= e($d['destination']) ?> — <?= e(demandes_texte_depuis_html((string)($d['objet_mission'] ?? ''))) ?></div>
          <?php
            $ag = demandes_mission_agents((int)$d['id']);
            if ($ag):
              $noms = array_map(fn($x) => trim(($x['prenoms'] ?? '') . ' ' . ($x['nom'] ?? '')), $ag);
          ?>
          <div class="dm-card-meta"><strong>Agents :</strong> <?= e(implode(', ', $noms)) ?></div>
          <?php endif; ?>
          <?php endif; ?>
          <span class="dm-action-tag"><?= $d['action_requise'] === 'DG' ? 'Validation DG requise' : 'Visa responsable' ?></span>
        </div>
      </div>
      <form method="post" class="dm-val-form">
        <?= csrf_field() ?><input type="hidden" name="did" value="<?= (int)$d['id'] ?>">
        <input type="text" name="commentaire" class="form-control" placeholder="Commentaire (optionnel)">
        <?php if ($d['action_requise'] === 'DG'): ?>
        <button class="btn btn-primary btn-sm" name="action" value="valider_dg"><i class="fa-solid fa-stamp"></i> Valider (DG)</button>
        <?php else: ?>
        <button class="btn btn-primary btn-sm" name="action" value="valider_resp"><i class="fa-solid fa-check"></i> Approuver</button>
        <?php endif; ?>
        <button class="btn btn-danger btn-sm" name="action" value="refuser"><i class="fa-solid fa-xmark"></i> Refuser</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div></div>
  </div>
</div>

<!-- ===== HISTORIQUE VALIDATIONS (DG / RH / responsables) ===== -->
<div class="dm-pane <?= $tab === 'historique_val' ? 'active' : '' ?>" id="pane-historique_val">
  <div class="dm-wrap">
    <div class="card">
      <div class="card-header"><h2>Demandes que vous avez traitées</h2></div>
      <div class="card-body">
        <?php if (empty($traitees)): ?>
          <p class="dm-empty">Aucune demande validée ou refusée pour le moment.</p>
        <?php endif; ?>
        <?php foreach ($traitees as $d):
            $b = $stc[$d['statut']] ?? $stc['EN_ATTENTE'];
            $icon = $cat_icons[$d['categorie']] ?? 'fa-file';
            $quand = $d['valide_dg_le'] ?? $d['valide_resp_le'] ?? $d['cree_le'] ?? null;
        ?>
        <div class="dm-card<?= $focus_id === (int)$d['id'] ? ' dm-card-focus' : '' ?>" id="demande-<?= (int)$d['id'] ?>">
          <div class="dm-card-head">
            <div>
              <div class="dm-card-title">
                <i class="fa-solid <?= $icon ?>"></i>
                <?= e($d['employe_nom'] ?? '') ?> — <?= e($d['type_libelle']) ?>
                <?php if (!empty($d['reference'])): ?><span class="dm-ref"><?= e($d['reference']) ?></span><?php endif; ?>
              </div>
              <div class="dm-card-meta"><?= e(format_date($d['date_debut'])) ?> → <?= e(format_date($d['date_fin'])) ?> · <?= demandes_fmt_jours($d['nombre_jours']) ?> j.</div>
              <?php if ($d['categorie'] === 'MISSION' && !empty($d['destination'])): ?>
              <div class="dm-card-meta"><i class="fa-solid fa-location-dot"></i> <?= e($d['destination']) ?></div>
              <?php if (!empty($d['ordre_mission_numero'])): ?>
              <div class="dm-card-meta"><strong>Réf. officielle :</strong> <?= e($d['ordre_mission_numero']) ?></div>
              <?php endif; ?>
              <?php endif; ?>
              <?php if ($quand): ?>
              <div class="dm-card-meta"><i class="fa-regular fa-clock"></i> Traité le <?= e(format_datetime($quand)) ?></div>
              <?php endif; ?>
            </div>
            <span class="dm-statut" style="background:<?= $b[1] ?>;color:<?= $b[2] ?>;"><?= demandes_libelle_statut($d['statut']) ?></span>
          </div>
          <?php if (!empty($d['commentaire_validation'])): ?>
          <div class="dm-comment"><i class="fa-solid fa-comment"></i> <?= e($d['commentaire_validation']) ?></div>
          <?php endif; ?>
          <div class="dm-card-actions">
            <a href="voir.php?id=<?= (int)$d['id'] ?>" class="btn btn-secondary btn-sm">
              <i class="fa-solid fa-eye"></i> Voir détails
            </a>
            <?php if ($d['categorie'] === 'MISSION' && $d['statut'] === 'APPROUVE'): ?>
            <a href="mission_print.php?id=<?= (int)$d['id'] ?>" class="btn btn-primary btn-sm" target="_blank">
              <i class="fa-solid fa-print"></i> Ordre de mission (PDF)
            </a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$extra_scripts = '<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
<script>
document.querySelectorAll(".dm-tab[data-tab]").forEach(function(b){
  b.addEventListener("click",function(e){
    e.preventDefault();
    var t=this.dataset.tab;
    document.querySelectorAll(".dm-tab").forEach(function(x){x.classList.remove("active");});
    document.querySelectorAll(".dm-pane").forEach(function(x){x.classList.remove("active");});
    this.classList.add("active");
    var pane=document.getElementById("pane-"+t);
    if(pane) pane.classList.add("active");
    history.replaceState(null,"","?tab="+t);
  });
});
(function(){
  var el=document.querySelector(".dm-card-focus");
  if(el){ setTimeout(function(){ el.scrollIntoView({behavior:"smooth",block:"center"}); }, 120); }
})();
function dmAutreToggle(selId,wrapId,inpId,formId,msg){
  var sel=document.getElementById(selId);
  var wrap=document.getElementById(wrapId);
  var inp=document.getElementById(inpId);
  var form=document.getElementById(formId);
  function toggle(){
    if(!sel||!wrap||!inp)return;
    var autre=sel.value==="AUTRE";
    wrap.style.display=autre?"block":"none";
    inp.required=autre;
    if(!autre)inp.value="";
  }
  if(sel){sel.addEventListener("change",toggle);toggle();}
  if(form){
    form.addEventListener("submit",function(e){
      if(sel&&sel.value==="AUTRE"&&inp&&!inp.value.trim()){
        e.preventDefault();inp.focus();alert(msg);
      }
    });
  }
}
dmAutreToggle("type_absence_select","absence-autre-wrap","type_absence_autre","form-absence","Veuillez préciser le type d\'absence.");
dmAutreToggle("type_conge_select","conge-autre-wrap","type_conge_autre","form-conge","Veuillez préciser le type de congé.");

(function(){
  var picker = document.getElementById("mission-agents-picker");
  if (!picker) return;
  var toggle = document.getElementById("mission-agents-toggle");
  var dropdown = document.getElementById("mission-agents-dropdown");
  var search = document.getElementById("mission-agents-search");
  var list = picker.querySelector(".dm-agent-list");
  var empty = document.getElementById("mission-agents-empty");
  var chips = document.getElementById("mission-agents-chips");
  var toggleLabel = toggle ? toggle.querySelector(".dm-agent-toggle-label") : null;
  if (!toggle || !dropdown || !list) return;

  function setOpen(open) {
    dropdown.hidden = !open;
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    if (open && search) {
      search.value = "";
      filterList("");
      setTimeout(function(){ search.focus(); }, 0);
    }
  }

  function filterList(q) {
    var visible = 0;
    list.querySelectorAll(".dm-agent-item").forEach(function(item){
      var ok = !q || (item.getAttribute("data-label") || "").indexOf(q) !== -1;
      item.hidden = !ok;
      if (ok) visible++;
    });
    if (empty) empty.hidden = visible > 0;
  }

  function syncChips() {
    if (!chips) return;
    chips.innerHTML = "";
    var selected = [];
    list.querySelectorAll(".dm-agent-item input:checked").forEach(function(cb){
      var item = cb.closest(".dm-agent-item");
      var name = item ? (item.getAttribute("data-name") || cb.value) : cb.value;
      selected.push({ id: cb.id, name: name, value: cb.value });
    });
    if (!selected.length) {
      chips.hidden = true;
      if (toggleLabel) toggleLabel.textContent = "Choisir un collègue…";
      return;
    }
    chips.hidden = false;
    if (toggleLabel) {
      toggleLabel.textContent = selected.length === 1
        ? "1 collègue sélectionné"
        : selected.length + " collègues sélectionnés";
    }
    selected.forEach(function(s){
      var chip = document.createElement("span");
      chip.className = "dm-agent-chip";
      chip.textContent = s.name + " ";
      var btn = document.createElement("button");
      btn.type = "button";
      btn.setAttribute("aria-label", "Retirer " + s.name);
      btn.innerHTML = "&times;";
      btn.addEventListener("click", function(){
        var cb = document.getElementById(s.id);
        if (cb) { cb.checked = false; syncChips(); }
      });
      chip.appendChild(btn);
      chips.appendChild(chip);
    });
  }

  toggle.addEventListener("click", function(e){
    e.preventDefault();
    setOpen(dropdown.hidden);
  });

  if (search) {
    search.addEventListener("input", function(){
      filterList((search.value || "").trim().toLowerCase());
    });
  }

  list.addEventListener("change", function(e){
    if (e.target && e.target.matches("input[type=checkbox]")) syncChips();
  });

  document.addEventListener("click", function(e){
    if (!picker.contains(e.target)) setOpen(false);
  });

  document.addEventListener("keydown", function(e){
    if (e.key === "Escape") setOpen(false);
  });

  syncChips();
})();

if (document.getElementById("objet_mission") && window.tinymce) {
  tinymce.init({
    selector: "#objet_mission",
    language: "fr_FR",
    language_url: "https://cdn.jsdelivr.net/npm/tinymce-i18n/langs6/fr_FR.js",
    height: 300,
    menubar: false,
    branding: false,
    promotion: false,
    statusbar: true,
    resize: true,
    plugins: "lists",
    toolbar: "undo redo | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | removeformat",
    toolbar_mode: "wrap",
    paste_data_images: false,
    paste_as_text: false,
    paste_webkit_styles: "font-weight font-style text-decoration text-align",
    valid_elements: "p[style],br,strong,b,em,i,u,ul,ol,li,blockquote[style],span[style]",
    content_style: "body{font-family:Avenir,Avenir Next,Segoe UI,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1a2540;} p{margin:0 0 10px;} ",
    setup: function(editor) {
      editor.on("change input undo redo paste", function(){ editor.save(); });
    }
  });

  var missionForm = document.getElementById("form-mission");
  if (missionForm) {
    missionForm.addEventListener("submit", function(e) {
      tinymce.triggerSave();
      var editor = tinymce.get("objet_mission");
      var texte = editor ? editor.getContent({format:"text"}).trim() : document.getElementById("objet_mission").value.trim();
      if (!texte) {
        e.preventDefault();
        if (editor) editor.focus();
        alert("Veuillez renseigner l’objet de la mission.");
      }
    });
  }
}
</script>';
include __DIR__ . '/../../views/layout/footer.php';