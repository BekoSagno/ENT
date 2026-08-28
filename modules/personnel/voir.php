<?php
// modules/personnel/voir.php — Fiche personnel (RH + self-service)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/personnel.php';
require_once __DIR__ . '/../../models/organigramme.php';
require_once __DIR__ . '/../../models/contrats.php';

session_demarrer();
exiger_connexion();

$id = (int)($_GET['id'] ?? 0);
$p  = $id ? personnel_par_id($id) : null;
if (!$p) {
    set_flash('error', 'Profil introuvable.');
    redirect('/modules/personnel/index.php');
}

// Accès : RH (VOIR) OU propriétaire du dossier (self-service)
$est_moi = (int)$p['utilisateur_id'] === (int)user_id();
if (!$est_moi) {
    exiger_permission('PERSONNEL', 'VOIR');
}

$peut_modif    = a_permission('PERSONNEL', 'MODIFIER');
$peut_sensible = a_permission('PERSONNEL', 'MODIFIER_SENSIBLE');
$peut_editer   = $peut_modif || $est_moi;   // satellites + CV

// ============================================================
//  TRAITEMENT POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Session expirée.');
        redirect('/modules/personnel/voir.php?id=' . $id);
    }
    $action = $_POST['action'] ?? '';
    $tab    = clean($_POST['tab'] ?? 'infos');

    // ---- Données RH (RH uniquement) ----
    if ($action === 'infos_rh' && $peut_modif) {
        $data = [
            'lieu_naissance'         => clean($_POST['lieu_naissance'] ?? ''),
            'nationalite'            => clean($_POST['nationalite'] ?? ''),
            'situation_matrimoniale' => clean($_POST['situation_matrimoniale'] ?? ''),
            'nombre_enfants'         => (int)($_POST['nombre_enfants'] ?? 0),
            'fonction'               => clean($_POST['fonction'] ?? ''),
        ];
        // Champs sensibles : conservés tels quels si pas le droit
        if ($peut_sensible) {
            $data['matricule_rh']  = clean($_POST['matricule_rh'] ?? '');
            $data['date_embauche'] = clean($_POST['date_embauche'] ?? '');
            $data['type_contrat']  = clean($_POST['type_contrat'] ?? '');
        } else {
            $data['matricule_rh']  = $p['matricule_rh'];
            $data['date_embauche'] = $p['date_embauche'];
            $data['type_contrat']  = $p['type_contrat'];
        }
        $r = personnel_modifier($id, $data);
        set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Données RH mises à jour.' : $r['message']);
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=infos');
    }

    // ---- Changement de statut (sensible) ----
    if ($action === 'statut' && $peut_sensible) {
        $r = personnel_changer_statut($id, clean($_POST['statut_rh'] ?? ''));
        set_flash($r['succes'] ? 'success' : 'error',
            $r['succes'] ? 'Statut mis à jour (compte : ' . $r['statut_compte'] . ').' : $r['message']);
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=infos');
    }

    // ---- Affectation : poste / grade / responsable (sensible) ----
    if ($action === 'affectation' && $peut_sensible) {
        $r = personnel_affecter(
            $id,
            (int)($_POST['poste_id'] ?? 0) ?: null,
            (int)($_POST['grade_id'] ?? 0) ?: null,
            (int)($_POST['responsable_id'] ?? 0) ?: null
        );
        set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Affectation enregistrée.' : $r['message']);
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=infos');
    }

    // ---- Contrats (sensible) ----
    if ($action === 'contrat_add' && $peut_sensible) {
        if (clean($_POST['date_debut'] ?? '') === '') {
            set_flash('error', 'La date de début est obligatoire.');
        } else {
            contrat_creer($id, [
                'reference'    => clean($_POST['reference'] ?? ''),
                'type_contrat' => clean($_POST['type_contrat'] ?? 'CDD'),
                'intitule'     => clean($_POST['intitule'] ?? ''),
                'date_debut'   => clean($_POST['date_debut'] ?? ''),
                'date_fin'     => clean($_POST['date_fin'] ?? ''),
                'notes'        => clean($_POST['notes'] ?? ''),
            ]);
            set_flash('success', 'Contrat ajouté.');
        }
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=contrats');
    }
    if ($action === 'contrat_del' && $peut_sensible) {
        contrat_supprimer((int)($_POST['ctid'] ?? 0), $id);
        set_flash('success', 'Contrat supprimé.');
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=contrats');
    }

    // ---- CV (RH ou self) ----
    if ($action === 'cv' && $peut_editer) {
        $resume  = clean($_POST['resume_professionnel'] ?? '');
        $fichier = null;
        if (!empty($_FILES['fichier_cv']['name'])) {
            $up = personnel_upload_piece($_FILES['fichier_cv'], 'cv');
            if (!$up['succes']) { set_flash('error', $up['message']); redirect('/modules/personnel/voir.php?id=' . $id . '&tab=cv'); }
            $fichier = $up['chemin'];
        }
        personnel_cv_sauvegarder($id, $resume, $fichier);
        set_flash('success', 'CV mis à jour.');
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=cv');
    }

    // ---- Formations ----
    if ($action === 'formation_add' && $peut_editer) {
        $fich = null;
        if (!empty($_FILES['fichier_diplome']['name'])) {
            $up = personnel_upload_piece($_FILES['fichier_diplome'], 'diplomes');
            if (!$up['succes']) { set_flash('error', $up['message']); redirect('/modules/personnel/voir.php?id=' . $id . '&tab=formations'); }
            $fich = $up['chemin'];
        }
        personnel_formation_ajouter($id, [
            'diplome'         => clean($_POST['diplome'] ?? ''),
            'etablissement'   => clean($_POST['etablissement'] ?? ''),
            'pays'            => clean($_POST['pays'] ?? ''),
            'date_debut'      => clean($_POST['date_debut'] ?? ''),
            'date_fin'        => clean($_POST['date_fin'] ?? ''),
            'fichier_diplome' => $fich,
        ]);
        set_flash('success', 'Formation ajoutée.');
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=formations');
    }
    if ($action === 'formation_del' && $peut_editer) {
        personnel_formation_supprimer((int)($_POST['fid'] ?? 0), $id);
        set_flash('success', 'Formation supprimée.');
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=formations');
    }

    // ---- Expériences ----
    if ($action === 'experience_add' && $peut_editer) {
        personnel_experience_ajouter($id, [
            'organisation' => clean($_POST['organisation'] ?? ''),
            'poste'        => clean($_POST['poste'] ?? ''),
            'date_debut'   => clean($_POST['date_debut'] ?? ''),
            'date_fin'     => clean($_POST['date_fin'] ?? ''),
            'description'  => clean($_POST['description'] ?? ''),
        ]);
        set_flash('success', 'Expérience ajoutée.');
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=experiences');
    }
    if ($action === 'experience_del' && $peut_editer) {
        personnel_experience_supprimer((int)($_POST['xid'] ?? 0), $id);
        set_flash('success', 'Expérience supprimée.');
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=experiences');
    }

    // ---- Certifications ----
    if ($action === 'certif_add' && $peut_editer) {
        $doc = null;
        if (!empty($_FILES['document']['name'])) {
            $up = personnel_upload_piece($_FILES['document'], 'certifications');
            if (!$up['succes']) { set_flash('error', $up['message']); redirect('/modules/personnel/voir.php?id=' . $id . '&tab=certifs'); }
            $doc = $up['chemin'];
        }
        personnel_certification_ajouter($id, [
            'certification'   => clean($_POST['certification'] ?? ''),
            'organisme'       => clean($_POST['organisme'] ?? ''),
            'date_obtention'  => clean($_POST['date_obtention'] ?? ''),
            'date_expiration' => clean($_POST['date_expiration'] ?? ''),
            'document'        => $doc,
        ]);
        set_flash('success', 'Certification ajoutée.');
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=certifs');
    }
    if ($action === 'certif_del' && $peut_editer) {
        personnel_certification_supprimer((int)($_POST['cid'] ?? 0), $id);
        set_flash('success', 'Certification supprimée.');
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=certifs');
    }

    // ---- Compétences ----
    if ($action === 'competence_set' && $peut_editer) {
        personnel_competence_definir($id, (int)($_POST['competence_id'] ?? 0), clean($_POST['niveau'] ?? 'INTERMEDIAIRE'));
        set_flash('success', 'Compétence enregistrée.');
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=competences');
    }
    if ($action === 'competence_del' && $peut_editer) {
        personnel_competence_retirer($id, (int)($_POST['competence_id'] ?? 0));
        set_flash('success', 'Compétence retirée.');
        redirect('/modules/personnel/voir.php?id=' . $id . '&tab=competences');
    }

    redirect('/modules/personnel/voir.php?id=' . $id);
}

// ============================================================
//  DONNÉES D'AFFICHAGE
// ============================================================
$tab = $_GET['tab'] ?? 'infos';
if (!in_array($tab, ['infos','cv','formations','experiences','certifs','competences','contrats'], true)) $tab = 'infos';

$formations   = personnel_formations_lister($id);
$experiences  = personnel_experiences_lister($id);
$certifs       = personnel_certifications_lister($id);
$cv            = personnel_cv_get($id);
$comp_perso    = personnel_competences_lister($id);
$comp_referentiel = competences_lister();
$postes_all       = postes_lister(true);
$grades_all       = grades_lister(true);
$responsables     = responsables_candidats($id);
$liste_contrats   = contrats_personnel($id);

$init = strtoupper(mb_substr($p['prenoms'] ?? 'U',0,1) . mb_substr($p['nom'] ?? 'S',0,1));

$statut_cfg = [
    'ACTIF'=>['Actif','#dcfce7','#166534'],'CONGE'=>['En congé','#dbeafe','#1e40af'],
    'MISSION'=>['En mission','#e0e7ff','#3730a3'],'SUSPENDU'=>['Suspendu','#fee2e2','#991b1b'],
    'DEMISSION'=>['Démission','#f3f4f6','#6b7280'],'RETRAITE'=>['Retraite','#fef3c7','#92400e'],
];
$sc = $statut_cfg[$p['statut_rh']] ?? $statut_cfg['ACTIF'];
$contrats = ['CDD'=>'CDD','CDI'=>'CDI','STAGIAIRE'=>'Stagiaire','CONSULTANT'=>'Consultant','VACATAIRE'=>'Vacataire'];
$situations = ['CELIBATAIRE'=>'Célibataire','MARIE'=>'Marié(e)','DIVORCE'=>'Divorcé(e)','VEUF'=>'Veuf(ve)'];
$niveaux = ['DEBUTANT'=>'Débutant','INTERMEDIAIRE'=>'Intermédiaire','AVANCE'=>'Avancé','EXPERT'=>'Expert'];

$page_titre      = $p['prenoms'] . ' ' . $p['nom'];
$page_breadcrumb = 'RH · Personnel · Fiche';
$module_actif    = 'PERSONNEL';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.fiche-head{background:linear-gradient(135deg,var(--blue-800),var(--blue-500));border-radius:var(--radius);padding:24px 26px;display:flex;align-items:center;gap:20px;color:#fff;box-shadow:0 8px 24px rgba(26,92,204,.28);margin-bottom:22px;flex-wrap:wrap;}
.fiche-av{width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.18);border:3px solid rgba(255,255,255,.5);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800;overflow:hidden;flex-shrink:0;}
.fiche-av img{width:100%;height:100%;object-fit:cover;}
.fiche-id{flex:1;min-width:200px;}
.fiche-id h2{font-size:1.4rem;font-weight:800;}
.fiche-id .r{font-size:.86rem;color:var(--blue-100);}
.fiche-tags{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;}
.ftag{background:rgba(255,255,255,.16);padding:4px 12px;border-radius:20px;font-size:.74rem;font-weight:600;}
.tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:22px;flex-wrap:wrap;}
.tab-btn{padding:10px 16px;font-size:.85rem;font-weight:600;color:var(--text-muted);background:none;border:none;cursor:pointer;border-bottom:2px solid transparent;display:inline-flex;align-items:center;gap:7px;text-decoration:none;}
.tab-btn:hover{color:var(--blue-600);}
.tab-btn.active{color:var(--blue-600);border-bottom-color:var(--blue-600);}
.tab-pane{display:none;}.tab-pane.active{display:block;}
.info-row{display:flex;gap:14px;padding:10px 0;border-bottom:1px solid var(--border);font-size:.86rem;}
.info-row:last-child{border-bottom:none;}
.info-row .k{width:170px;flex-shrink:0;color:var(--text-muted);font-weight:600;}
.sat-item{border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:10px;display:flex;gap:12px;align-items:flex-start;}
.sat-item .b{flex:1;}
.sat-item .t{font-weight:700;font-size:.9rem;}
.sat-item .s{font-size:.78rem;color:var(--text-muted);margin-top:2px;}
.comp-chip{display:inline-flex;align-items:center;gap:8px;background:var(--blue-50);border:1px solid var(--blue-200);border-radius:20px;padding:5px 12px;font-size:.8rem;margin:0 6px 8px 0;}
.comp-chip .lvl{font-size:.68rem;font-weight:700;color:var(--blue-700);background:#fff;border-radius:12px;padding:1px 8px;}
</style>

<div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Liste</a>
  <?php if (a_permission('PERSONNEL', 'GERER_REMUNERATION')): ?>
    <a href="remuneration.php?id=<?= $id ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-lock"></i> Rémunération</a>
  <?php endif; ?>
</div>

<div class="fiche-head">
  <div class="fiche-av">
    <?php if (!empty($p['photo'])): ?><img src="<?= e($p['photo']) ?>" alt=""><?php else: ?><?= e($init) ?><?php endif; ?>
  </div>
  <div class="fiche-id">
    <h2><?= e($p['prenoms'] . ' ' . $p['nom']) ?></h2>
    <div class="r"><?= e($p['fonction'] ?: ($p['groupe_libelle'] ?? '')) ?></div>
    <div class="fiche-tags">
      <span class="ftag"><i class="fa-solid fa-id-badge"></i> <?= e($p['matricule']) ?></span>
      <span class="ftag"><i class="fa-solid fa-envelope"></i> <?= e($p['email']) ?></span>
      <span class="ftag" style="background:<?= $sc[1] ?>;color:<?= $sc[2] ?>;"><?= $sc[0] ?></span>
    </div>
  </div>
</div>

<!-- Onglets -->
<div class="tabs">
  <?php
  $onglets = ['infos'=>['fa-circle-info','Informations'],'cv'=>['fa-file-lines','CV'],
              'formations'=>['fa-graduation-cap','Formations'],'experiences'=>['fa-briefcase','Expériences'],
              'certifs'=>['fa-certificate','Certifications'],'competences'=>['fa-bolt','Compétences'],
              'contrats'=>['fa-file-contract','Contrats']];
  foreach ($onglets as $k=>$o): ?>
    <a href="?id=<?= $id ?>&tab=<?= $k ?>" class="tab-btn <?= $tab===$k?'active':'' ?>" data-tab="<?= $k ?>"><i class="fa-solid <?= $o[0] ?>"></i> <?= $o[1] ?></a>
  <?php endforeach; ?>
</div>

<!-- ===== INFOS ===== -->
<div class="tab-pane <?= $tab==='infos'?'active':'' ?>" id="pane-infos">
  <?php if ($peut_sensible): ?>
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header"><h2><i class="fa-solid fa-sitemap" style="color:var(--blue-500);"></i>&nbsp; Affectation</h2></div>
    <div class="card-body">
      <form method="post" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <?= csrf_field() ?><input type="hidden" name="action" value="affectation"><input type="hidden" name="tab" value="infos">
        <div class="form-group" style="margin:0;flex:1;min-width:200px;">
          <label class="form-label">Poste</label>
          <select name="poste_id" class="form-control"><option value="">—</option>
            <?php foreach ($postes_all as $po): ?>
              <option value="<?= (int)$po['id'] ?>" <?= (int)($p['poste_id']??0)===(int)$po['id']?'selected':'' ?>><?= e($po['intitule']) ?><?= !empty($po['unite_nom'])?' — '.e($po['unite_nom']):'' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:140px;">
          <label class="form-label">Grade</label>
          <select name="grade_id" class="form-control"><option value="">—</option>
            <?php foreach ($grades_all as $gr): ?>
              <option value="<?= (int)$gr['id'] ?>" <?= (int)($p['grade_id']??0)===(int)$gr['id']?'selected':'' ?>><?= e($gr['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:180px;">
          <label class="form-label">Responsable hiérarchique</label>
          <select name="responsable_id" class="form-control"><option value="">—</option>
            <?php foreach ($responsables as $r): ?>
              <option value="<?= (int)$r['id'] ?>" <?= (int)($p['responsable_id']??0)===(int)$r['id']?'selected':'' ?>><?= e($r['nom']) ?><?= !empty($r['poste'])?' ('.e($r['poste']).')':'' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Affecter</button>
      </form>
      <div style="font-size:.74rem;color:var(--text-muted);margin-top:8px;"><i class="fa-solid fa-circle-info"></i> Le responsable hiérarchique servira aux validations (congés, Phase 2).</div>
    </div>
  </div>
  <?php endif; ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;" class="grid2">
    <div class="card">
      <div class="card-header"><h2>Données RH</h2></div>
      <div class="card-body">
        <?php if ($peut_modif): ?>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="infos_rh"><input type="hidden" name="tab" value="infos">
          <div class="form-row">
            <div class="form-group"><label class="form-label">Fonction</label><input type="text" name="fonction" class="form-control" value="<?= e($p['fonction'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Nationalité</label><input type="text" name="nationalite" class="form-control" value="<?= e($p['nationalite'] ?? '') ?>"></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Lieu de naissance</label><input type="text" name="lieu_naissance" class="form-control" value="<?= e($p['lieu_naissance'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Situation matrimoniale</label>
              <select name="situation_matrimoniale" class="form-control"><option value="">—</option>
                <?php foreach ($situations as $k=>$v): ?><option value="<?= $k ?>" <?= ($p['situation_matrimoniale']??'')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
              </select></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Nombre d'enfants</label><input type="number" name="nombre_enfants" class="form-control" min="0" value="<?= (int)($p['nombre_enfants'] ?? 0) ?>"></div>
            <div class="form-group"><label class="form-label">Matricule RH <?= $peut_sensible?'':'(verrouillé)' ?></label><input type="text" name="matricule_rh" class="form-control" value="<?= e($p['matricule_rh'] ?? '') ?>" <?= $peut_sensible?'':'disabled' ?>></div>
          </div>
          <?php if ($peut_sensible): ?>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Type de contrat</label>
              <select name="type_contrat" class="form-control"><option value="">—</option>
                <?php foreach ($contrats as $k=>$v): ?><option value="<?= $k ?>" <?= ($p['type_contrat']??'')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
              </select></div>
            <div class="form-group"><label class="form-label">Date d'embauche</label><input type="date" name="date_embauche" class="form-control" value="<?= e($p['date_embauche'] ?? '') ?>"></div>
          </div>
          <?php endif; ?>
          <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
        </form>
        <?php else: ?>
          <div class="info-row"><span class="k">Fonction</span><span><?= e($p['fonction'] ?: '—') ?></span></div>
          <div class="info-row"><span class="k">Contrat</span><span><?= e($contrats[$p['type_contrat']] ?? '—') ?></span></div>
          <div class="info-row"><span class="k">Date d'embauche</span><span><?= !empty($p['date_embauche'])?e(format_date($p['date_embauche'])):'—' ?></span></div>
          <div class="info-row"><span class="k">Nationalité</span><span><?= e($p['nationalite'] ?: '—') ?></span></div>
          <div class="info-row"><span class="k">Situation</span><span><?= e($situations[$p['situation_matrimoniale']] ?? '—') ?></span></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2>Compte & statut</h2></div>
      <div class="card-body">
        <div class="info-row"><span class="k">Groupe</span><span><?= e($p['groupe_libelle'] ?? '—') ?></span></div>
        <div class="info-row"><span class="k">Grade</span><span><?= e($p['grade'] ?: '—') ?></span></div>
        <div class="info-row"><span class="k">Spécialité</span><span><?= e($p['specialite'] ?: '—') ?></span></div>
        <div class="info-row"><span class="k">Téléphone</span><span><?= e($p['telephone'] ?: '—') ?></span></div>
        <div class="info-row"><span class="k">Statut du compte</span><span><?= e($p['statut_compte']) ?></span></div>
        <div class="info-row"><span class="k">Dernière connexion</span><span><?= !empty($p['derniere_connexion'])?e(format_datetime($p['derniere_connexion'])):'—' ?></span></div>

        <?php if ($peut_sensible): ?>
        <form method="post" style="margin-top:16px;display:flex;gap:8px;align-items:flex-end;">
          <?= csrf_field() ?><input type="hidden" name="action" value="statut"><input type="hidden" name="tab" value="infos">
          <div class="form-group" style="margin:0;flex:1;">
            <label class="form-label">Changer le statut RH</label>
            <select name="statut_rh" class="form-control">
              <?php foreach ($statut_cfg as $k=>$v): ?><option value="<?= $k ?>" <?= $p['statut_rh']===$k?'selected':'' ?>><?= $v[0] ?></option><?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-primary"><i class="fa-solid fa-rotate"></i> Appliquer</button>
        </form>
        <div style="font-size:.74rem;color:var(--text-muted);margin-top:6px;"><i class="fa-solid fa-circle-info"></i> Démission / Retraite désactivent automatiquement le compte.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ===== CV ===== -->
<div class="tab-pane <?= $tab==='cv'?'active':'' ?>" id="pane-cv">
  <div class="card"><div class="card-header"><h2>Curriculum Vitae</h2></div><div class="card-body">
    <?php if ($peut_editer): ?>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="action" value="cv"><input type="hidden" name="tab" value="cv">
      <div class="form-group"><label class="form-label">Résumé professionnel</label>
        <textarea name="resume_professionnel" class="form-control" rows="5"><?= e($cv['resume_professionnel'] ?? '') ?></textarea></div>
      <div class="form-group"><label class="form-label">Fichier CV (PDF, image — 5 Mo max)</label>
        <input type="file" name="fichier_cv" class="form-control" accept="application/pdf,image/*"></div>
      <?php if (!empty($cv['fichier_cv'])): ?>
        <p style="font-size:.82rem;margin-bottom:12px;"><i class="fa-solid fa-paperclip"></i>
          <a href="telecharger.php?type=cv&id=<?= (int)$cv['id'] ?>" target="_blank">CV actuel</a></p>
      <?php endif; ?>
      <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer le CV</button>
    </form>
    <?php else: ?>
      <p style="font-size:.88rem;"><?= nl2br(e($cv['resume_professionnel'] ?? 'Aucun CV renseigné.')) ?></p>
      <?php if (!empty($cv['fichier_cv'])): ?><p style="margin-top:12px;"><a class="btn btn-secondary btn-sm" href="telecharger.php?type=cv&id=<?= (int)$cv['id'] ?>" target="_blank"><i class="fa-solid fa-download"></i> Télécharger le CV</a></p><?php endif; ?>
    <?php endif; ?>
  </div></div>
</div>

<!-- ===== FORMATIONS ===== -->
<div class="tab-pane <?= $tab==='formations'?'active':'' ?>" id="pane-formations">
  <div class="card"><div class="card-header"><h2>Formations</h2></div><div class="card-body">
    <?php if (empty($formations)): ?><p style="font-size:.85rem;color:var(--text-muted);">Aucune formation.</p><?php endif; ?>
    <?php foreach ($formations as $fo): ?>
    <div class="sat-item">
      <div class="b">
        <div class="t"><?= e($fo['diplome']) ?></div>
        <div class="s"><?= e($fo['etablissement'] ?? '') ?><?= !empty($fo['pays'])?' · '.e($fo['pays']):'' ?>
          <?= !empty($fo['date_debut'])?' · '.e(format_date($fo['date_debut'])):'' ?><?= !empty($fo['date_fin'])?' → '.e(format_date($fo['date_fin'])):'' ?></div>
        <?php if (!empty($fo['fichier_diplome'])): ?><div style="margin-top:6px;"><a href="telecharger.php?type=diplome&id=<?= (int)$fo['id'] ?>" target="_blank" style="font-size:.8rem;"><i class="fa-solid fa-paperclip"></i> Diplôme</a></div><?php endif; ?>
      </div>
      <?php if ($peut_editer): ?>
      <form method="post" onsubmit="return confirm('Supprimer ?');"><?= csrf_field() ?><input type="hidden" name="action" value="formation_del"><input type="hidden" name="tab" value="formations"><input type="hidden" name="fid" value="<?= (int)$fo['id'] ?>"><button class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-trash"></i></button></form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($peut_editer): ?>
    <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="action" value="formation_add"><input type="hidden" name="tab" value="formations">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Diplôme *</label><input type="text" name="diplome" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Établissement</label><input type="text" name="etablissement" class="form-control"></div>
      </div>
      <div class="form-row-3">
        <div class="form-group"><label class="form-label">Pays</label><input type="text" name="pays" class="form-control"></div>
        <div class="form-group"><label class="form-label">Début</label><input type="date" name="date_debut" class="form-control"></div>
        <div class="form-group"><label class="form-label">Fin</label><input type="date" name="date_fin" class="form-control"></div>
      </div>
      <div class="form-group"><label class="form-label">Diplôme (fichier)</label><input type="file" name="fichier_diplome" class="form-control" accept="application/pdf,image/*"></div>
      <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> Ajouter</button>
    </form>
    <?php endif; ?>
  </div></div>
</div>

<!-- ===== EXPERIENCES ===== -->
<div class="tab-pane <?= $tab==='experiences'?'active':'' ?>" id="pane-experiences">
  <div class="card"><div class="card-header"><h2>Expériences professionnelles</h2></div><div class="card-body">
    <?php if (empty($experiences)): ?><p style="font-size:.85rem;color:var(--text-muted);">Aucune expérience.</p><?php endif; ?>
    <?php foreach ($experiences as $x): ?>
    <div class="sat-item">
      <div class="b">
        <div class="t"><?= e($x['poste'] ?: 'Poste') ?> — <?= e($x['organisation']) ?></div>
        <div class="s"><?= !empty($x['date_debut'])?e(format_date($x['date_debut'])):'' ?><?= !empty($x['date_fin'])?' → '.e(format_date($x['date_fin'])):' → en cours' ?></div>
        <?php if (!empty($x['description'])): ?><div style="font-size:.82rem;margin-top:6px;"><?= nl2br(e($x['description'])) ?></div><?php endif; ?>
      </div>
      <?php if ($peut_editer): ?>
      <form method="post" onsubmit="return confirm('Supprimer ?');"><?= csrf_field() ?><input type="hidden" name="action" value="experience_del"><input type="hidden" name="tab" value="experiences"><input type="hidden" name="xid" value="<?= (int)$x['id'] ?>"><button class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-trash"></i></button></form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($peut_editer): ?>
    <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="experience_add"><input type="hidden" name="tab" value="experiences">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Organisation *</label><input type="text" name="organisation" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Poste</label><input type="text" name="poste" class="form-control"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Début</label><input type="date" name="date_debut" class="form-control"></div>
        <div class="form-group"><label class="form-label">Fin</label><input type="date" name="date_fin" class="form-control"></div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
      <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> Ajouter</button>
    </form>
    <?php endif; ?>
  </div></div>
</div>

<!-- ===== CERTIFICATIONS ===== -->
<div class="tab-pane <?= $tab==='certifs'?'active':'' ?>" id="pane-certifs">
  <div class="card"><div class="card-header"><h2>Certifications</h2></div><div class="card-body">
    <?php if (empty($certifs)): ?><p style="font-size:.85rem;color:var(--text-muted);">Aucune certification.</p><?php endif; ?>
    <?php foreach ($certifs as $c): ?>
    <div class="sat-item">
      <div class="b">
        <div class="t"><?= e($c['certification']) ?></div>
        <div class="s"><?= e($c['organisme'] ?? '') ?><?= !empty($c['date_obtention'])?' · obtenu '.e(format_date($c['date_obtention'])):'' ?><?= !empty($c['date_expiration'])?' · expire '.e(format_date($c['date_expiration'])):'' ?></div>
        <?php if (!empty($c['document'])): ?><div style="margin-top:6px;"><a href="telecharger.php?type=certif&id=<?= (int)$c['id'] ?>" target="_blank" style="font-size:.8rem;"><i class="fa-solid fa-paperclip"></i> Document</a></div><?php endif; ?>
      </div>
      <?php if ($peut_editer): ?>
      <form method="post" onsubmit="return confirm('Supprimer ?');"><?= csrf_field() ?><input type="hidden" name="action" value="certif_del"><input type="hidden" name="tab" value="certifs"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><button class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-trash"></i></button></form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($peut_editer): ?>
    <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="action" value="certif_add"><input type="hidden" name="tab" value="certifs">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Certification *</label><input type="text" name="certification" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Organisme</label><input type="text" name="organisme" class="form-control"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Date d'obtention</label><input type="date" name="date_obtention" class="form-control"></div>
        <div class="form-group"><label class="form-label">Date d'expiration</label><input type="date" name="date_expiration" class="form-control"></div>
      </div>
      <div class="form-group"><label class="form-label">Document</label><input type="file" name="document" class="form-control" accept="application/pdf,image/*"></div>
      <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> Ajouter</button>
    </form>
    <?php endif; ?>
  </div></div>
</div>

<!-- ===== COMPETENCES ===== -->
<div class="tab-pane <?= $tab==='competences'?'active':'' ?>" id="pane-competences">
  <div class="card"><div class="card-header"><h2>Compétences</h2></div><div class="card-body">
    <?php if (empty($comp_perso)): ?><p style="font-size:.85rem;color:var(--text-muted);margin-bottom:14px;">Aucune compétence.</p><?php endif; ?>
    <div style="margin-bottom:16px;">
      <?php foreach ($comp_perso as $cp): ?>
      <span class="comp-chip">
        <?= e($cp['nom']) ?> <span class="lvl"><?= e($niveaux[$cp['niveau']] ?? $cp['niveau']) ?></span>
        <?php if ($peut_editer): ?>
        <form method="post" style="display:inline;" onsubmit="return confirm('Retirer ?');"><?= csrf_field() ?><input type="hidden" name="action" value="competence_del"><input type="hidden" name="tab" value="competences"><input type="hidden" name="competence_id" value="<?= (int)$cp['competence_id'] ?>"><button style="border:none;background:none;color:var(--danger);cursor:pointer;padding:0;"><i class="fa-solid fa-xmark"></i></button></form>
        <?php endif; ?>
      </span>
      <?php endforeach; ?>
    </div>

    <?php if ($peut_editer): ?>
    <hr style="border:none;border-top:1px solid var(--border);margin:14px 0;">
    <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
      <?= csrf_field() ?><input type="hidden" name="action" value="competence_set"><input type="hidden" name="tab" value="competences">
      <div class="form-group" style="margin:0;flex:2;min-width:180px;">
        <label class="form-label">Compétence</label>
        <select name="competence_id" class="form-control" required>
          <?php foreach ($comp_referentiel as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nom']) ?><?= !empty($c['categorie'])?' ('.e($c['categorie']).')':'' ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:120px;">
        <label class="form-label">Niveau</label>
        <select name="niveau" class="form-control">
          <?php foreach ($niveaux as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> Ajouter / Mettre à jour</button>
    </form>
    <?php endif; ?>
  </div></div>
</div>

<!-- ===== CONTRATS ===== -->
<div class="tab-pane <?= $tab==='contrats'?'active':'' ?>" id="pane-contrats">
  <div class="card"><div class="card-header"><h2>Contrats</h2></div><div class="card-body">
    <?php if (empty($liste_contrats)): ?><p style="font-size:.85rem;color:var(--text-muted);">Aucun contrat.</p><?php endif; ?>
    <?php foreach ($liste_contrats as $ct): $eff = contrat_statut_effectif($ct);
      $eff_lbl = ['EN_COURS'=>'En cours','EXPIRE'=>'Expiré','RESILIE'=>'Résilié','RENOUVELE'=>'Renouvelé'][$eff] ?? $eff;
      $eff_col = $eff==='EN_COURS' ? '#166534' : ($eff==='EXPIRE' ? '#991b1b' : '#6b7280');
    ?>
    <div class="sat-item">
      <div class="b">
        <div class="t"><?= e($contrats[$ct['type_contrat']] ?? $ct['type_contrat']) ?><?= !empty($ct['intitule'])?' — '.e($ct['intitule']):'' ?><?= !empty($ct['reference'])?' <span style="color:var(--text-muted);font-weight:400;">('.e($ct['reference']).')</span>':'' ?></div>
        <div class="s"><?= e(format_date($ct['date_debut'])) ?> → <?= !empty($ct['date_fin'])?e(format_date($ct['date_fin'])):'indéterminé' ?> · <span style="color:<?= $eff_col ?>;font-weight:700;"><?= $eff_lbl ?></span></div>
        <?php if (!empty($ct['notes'])): ?><div style="font-size:.8rem;margin-top:4px;"><?= nl2br(e($ct['notes'])) ?></div><?php endif; ?>
      </div>
      <?php if ($peut_sensible): ?>
      <form method="post" onsubmit="return confirm('Supprimer ce contrat ?');"><?= csrf_field() ?><input type="hidden" name="action" value="contrat_del"><input type="hidden" name="tab" value="contrats"><input type="hidden" name="ctid" value="<?= (int)$ct['id'] ?>"><button class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-trash"></i></button></form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($peut_sensible): ?>
    <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="contrat_add"><input type="hidden" name="tab" value="contrats">
      <div class="form-row-3">
        <div class="form-group"><label class="form-label">Type</label>
          <select name="type_contrat" class="form-control"><?php foreach ($contrats as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label class="form-label">Référence</label><input type="text" name="reference" class="form-control"></div>
        <div class="form-group"><label class="form-label">Intitulé</label><input type="text" name="intitule" class="form-control"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Début *</label><input type="date" name="date_debut" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Fin <span style="color:var(--text-muted);">(vide = CDI)</span></label><input type="date" name="date_fin" class="form-control"></div>
      </div>
      <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
      <button class="btn btn-primary"><i class="fa-solid fa-plus"></i> Ajouter le contrat</button>
    </form>
    <?php endif; ?>
  </div></div>
</div>

<?php
$extra_scripts = '<script>
document.querySelectorAll(".tab-btn").forEach(b=>b.addEventListener("click",function(e){
  e.preventDefault();const t=this.dataset.tab;
  document.querySelectorAll(".tab-btn").forEach(x=>x.classList.remove("active"));
  document.querySelectorAll(".tab-pane").forEach(x=>x.classList.remove("active"));
  this.classList.add("active");document.getElementById("pane-"+t).classList.add("active");
  history.replaceState(null,"","?id=' . $id . '&tab="+t);
}));
</script>
<style>@media(max-width:880px){.grid2{grid-template-columns:1fr !important;}}</style>';
include __DIR__ . '/../../views/layout/footer.php';
?>
