<?php
// modules/marches/wizard.php — Wizard DAF 6 étapes (adapté au template CRICT)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/marche.php';

session_demarrer();
exiger_permission('MARCHES', 'CREER');

$user    = user_courant();
$uid     = (int)$user['id'];
$is_chef = in_array($user['groupe_code'] ?? '', ['SUPER_ADMIN','DG','DAF','PRMP']);

// ── Flash message ──
$flash = $_SESSION['marche_flash'] ?? null;
unset($_SESSION['marche_flash']);

// ── Traitement POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['marche_flash'] = ['type'=>'error','msg'=>'Jeton de sécurité invalide.'];
        header('Location: wizard.php'); exit;
    }
    $action = clean($_POST['workflow_action'] ?? '');
    $did    = (int)($_POST['dossier_id'] ?? 0);
    $nav    = clean($_POST['nav'] ?? 'stay');

    if ($did < 1) {
        $_SESSION['marche_flash'] = ['type'=>'error','msg'=>'Dossier invalide.'];
        header('Location: wizard.php'); exit;
    }

    $next_step = 1;

    switch ($action) {
        case 'step1':
            $cat_post = clean($_POST['categorie_id'] ?? '0');
            if ($cat_post === '__autre__') {
                require_once __DIR__ . '/../../models/depense.php';
                $cat_post = (string)((int)depense_categorie_trouver_ou_creer(clean($_POST['categorie_libre'] ?? '')));
            }
            $r = marche_save_step1($did, $uid, clean($_POST['libelle']??''), $cat_post);
            $_SESSION['marche_flash'] = ['type'=>$r['succes']?'success':'error','msg'=>$r['message']];
            $next_step = ($r['succes'] && $nav==='next') ? 2 : 1;
            break;

        case 'step2':
            $ids   = [1=>(int)($_POST['fournisseur_1_id']??0), 2=>(int)($_POST['fournisseur_2_id']??0), 3=>(int)($_POST['fournisseur_3_id']??0)];
            $files = ['facture_1'=>$_FILES['facture_1']??null,'facture_2'=>$_FILES['facture_2']??null,'facture_3'=>$_FILES['facture_3']??null];
            $r = marche_save_step2($did, $uid, $ids, $files);
            $_SESSION['marche_flash'] = ['type'=>$r['succes']?'success':'error','msg'=>$r['message']];
            $next_step = ($r['succes'] && $nav==='next') ? 3 : 2;
            break;

        case 'step3':
            $r = marche_save_step3($did, $uid, (int)($_POST['fournisseur_favori']??0));
            $_SESSION['marche_flash'] = ['type'=>$r['succes']?'success':'error','msg'=>$r['message']];
            $next_step = ($r['succes'] && $nav==='next') ? 4 : 3;
            break;

        case 'step4':
            $fav   = marche_get_fav($did);
            $files = [
                'f'.$fav.'_rccm'      => $_FILES['f'.$fav.'_rccm']     ?? null,
                'f'.$fav.'_nif'       => $_FILES['f'.$fav.'_nif']      ?? null,
                'f'.$fav.'_quittance' => $_FILES['f'.$fav.'_quittance']?? null,
                'f'.$fav.'_quitus'    => $_FILES['f'.$fav.'_quitus']   ?? null,
            ];
            $r = marche_save_step4($did, $uid, $fav, $files, clean($_POST['motif_f'.$fav]??''));
            $_SESSION['marche_flash'] = ['type'=>$r['succes']?'success':'error','msg'=>$r['message']];
            $next_step = ($r['succes'] && $nav==='next') ? 5 : 4;
            break;

        case 'step5':
            $ht_raw = preg_replace('/[^\d.]/', '', str_replace([' ',"\xc2\xa0",','],['','','.'], clean($_POST['montant_ht']??'0')));
            $r = marche_save_step5($did, $uid, (float)$ht_raw, clean($_POST['regime_fiscal']??''));
            $_SESSION['marche_flash'] = ['type'=>$r['succes']?'success':'error','msg'=>$r['message']];
            $next_step = ($r['succes'] && $nav==='next') ? 6 : 5;
            break;

        case 'save_exec_pieces':
            $files = ['exec_lettre_paiement'=>$_FILES['exec_lettre_paiement']??null,'exec_bon_engagement'=>$_FILES['exec_bon_engagement']??null,'exec_mandat_paiement'=>$_FILES['exec_mandat_paiement']??null,'exec_liquidation'=>$_FILES['exec_liquidation']??null];
            $r = marche_save_exec_pieces($did, $uid, $files);
            $_SESSION['marche_flash'] = ['type'=>$r['succes']?'success':'error','msg'=>$r['message']];
            header('Location: wizard.php?id='.$did.'&step=6'); exit;

        case 'execute':
            $files = ['exec_lettre_paiement'=>$_FILES['exec_lettre_paiement']??null,'exec_bon_engagement'=>$_FILES['exec_bon_engagement']??null,'exec_mandat_paiement'=>$_FILES['exec_mandat_paiement']??null,'exec_liquidation'=>$_FILES['exec_liquidation']??null];
            marche_save_exec_pieces($did, $uid, $files);
            $r = marche_executer($did, $uid, (int)($_POST['annee_execution']??0), (int)($_POST['trimestre_execution']??0));
            $_SESSION['marche_flash'] = ['type'=>$r['succes']?'success':'error','msg'=>$r['message']];
            header('Location: wizard.php?id='.$did); exit;
    }
    header('Location: wizard.php?id='.$did.'&step='.$next_step); exit;
}

// ── Charger ou créer le dossier ──
$did = (int)($_GET['id'] ?? 0);
if ($did < 1) {
    $did = marche_creer_brouillon($uid);
    header('Location: wizard.php?id='.$did.'&step=1'); exit;
}

$d = marche_get($did);
if (!$d || (!$is_chef && (int)$d['cree_par'] !== $uid)) {
    set_flash('error','Dossier introuvable.'); redirect('/modules/marches/index.php');
}

$execute = $d['statut'] === 'execute';
$statut  = $d['statut'];

// Étape initiale
$step = (int)($_GET['step'] ?? 0);
if ($step < 1 || $step > 6) {
    $step = $execute ? 6 : marche_etape_a_reprendre($d);
}

// Données pour le formulaire
$fournisseurs  = marche_annuaire_fournisseurs();
$periodes      = marche_periodes_eligibles();
$categories    = marche_categories();
$numero        = $d['numero_dossier'] ?? '';
$fav           = (int)($d['fournisseur_favori'] ?? 0);
$nom_f         = [1=>trim($d['fournisseur_1_nom']??'')?:'Fournisseur 1', 2=>trim($d['fournisseur_2_nom']??'')?:'Fournisseur 2', 3=>trim($d['fournisseur_3_nom']??'')?:'Fournisseur 3'];

// Préparer les periodes par année
$eligible_by_year = [];
foreach ($periodes as $p) {
    $y = (int)$p['annee']; $q = (int)$p['trimestre'];
    $eligible_by_year[$y][] = $q;
}
$annees_elig  = array_keys($eligible_by_year);
rsort($annees_elig);
$has_periode  = !empty($annees_elig);
$annee_def    = $has_periode ? (int)$annees_elig[0] : (int)date('Y');
$trim_def     = $has_periode ? (int)($eligible_by_year[$annee_def][0] ?? 1) : 1;

$page_titre      = 'Dossier de dépense DAF — '.$numero;
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Marchés</a> · '.e($numero);
$module_actif    = 'MARCHES';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
/* ── Wizard container ── */
.daf-wizard { max-width: 760px; margin: 0 auto; }

/* ── Stepper ── */
.stepper {
  display: flex; align-items: center; gap: 0;
  background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 14px; padding: 14px 20px; margin-bottom: 22px;
  overflow-x: auto;
}
.stepper-item {
  display: flex; flex-direction: column; align-items: center; gap: 5px;
  flex: 1; min-width: 68px; cursor: pointer; position: relative;
}
.stepper-item:not(:last-child)::after {
  content: ''; position: absolute;
  top: 16px; left: 50%; width: 100%;
  height: 2px; background: var(--border);
  z-index: 0;
}
.stepper-item.done::after  { background: var(--blue-400); }
.stepper-item.active::after{ background: var(--blue-200); }
.step-dot {
  width: 32px; height: 32px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; font-weight: 800;
  background: var(--bg); border: 2px solid var(--border); color: var(--text-muted);
  position: relative; z-index: 1; flex-shrink: 0;
  transition: all .2s;
}
.stepper-item.done   .step-dot { background: var(--blue-600); border-color: var(--blue-600); color: #fff; }
.stepper-item.active .step-dot { background: var(--blue-500); border-color: var(--blue-500); color: #fff; box-shadow: 0 0 0 3px rgba(37,112,232,.18); }
.step-label { font-size: .68rem; font-weight: 600; color: var(--text-muted); text-align: center; }
.stepper-item.active .step-label { color: var(--blue-600); }
.stepper-item.done   .step-label { color: var(--blue-500); }

/* Barre progression */
.prog-bar { height: 4px; background: var(--border); border-radius: 20px; overflow: hidden; margin-bottom: 18px; }
.prog-fill { height: 100%; background: linear-gradient(90deg,var(--blue-500),var(--accent)); border-radius: 20px; transition: width .35s ease; }

/* Recap chips */
.recap-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
.chip { display: inline-flex; align-items: center; gap: 4px; padding: 4px 11px; border-radius: 20px; font-size: .74rem; font-weight: 600; background: var(--bg); border: 1.5px solid var(--border); color: var(--text-muted); }
.chip.blue   { background: var(--blue-50);  border-color: var(--blue-300);  color: var(--blue-700); }
.chip.green  { background: #dcfce7;          border-color: #86efac;           color: #166534; }
.chip.warn   { background: #fef3c7;          border-color: #fde68a;           color: #92400e; }
.chip.purple { background: #ede9fe;          border-color: #c4b5fd;           color: #5b21b6; }

/* Panels */
.daf-panel { display: none; }
.daf-panel.on { display: block; }

/* Section card */
.daf-card {
  background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 14px; overflow: hidden; margin-bottom: 18px;
}
.daf-card-hd {
  display: flex; align-items: center; gap: 10px;
  padding: 13px 20px; background: var(--bg); border-bottom: 1px solid var(--border);
}
.daf-card-hd-ico { width: 30px; height: 30px; border-radius: 7px; background: var(--blue-100); color: var(--blue-600); display: flex; align-items: center; justify-content: center; font-size: .82rem; flex-shrink: 0; }
.daf-card-hd h3 { font-size: .9rem; font-weight: 700; margin: 0; }
.daf-card-bd { padding: 20px 22px; }

/* Champs */
.fg2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.fg3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
@media(max-width:640px){ .fg2,.fg3{ grid-template-columns:1fr; } }

/* Upload custom */
.up-zone { display: flex; align-items: center; gap: 8px; }
.up-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 7px 12px; border: 1.5px solid var(--border); border-radius: 8px;
  background: var(--surface); font-size: .8rem; font-weight: 600; color: var(--text);
  cursor: pointer; white-space: nowrap; flex-shrink: 0; transition: all .14s;
}
.up-btn:hover { border-color: var(--blue-400); color: var(--blue-600); }
.up-input { position: absolute; left: -9999px; width: 1px; height: 1px; }
.up-name { font-size: .8rem; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.up-name a { color: var(--blue-500); font-weight: 600; text-decoration: underline; }
.up-name .nd { color: var(--text-muted); font-style: italic; }

/* Boutons navigation */
.daf-btns { display: flex; gap: 9px; margin-top: 18px; flex-wrap: wrap; }
.daf-btns .btn { min-width: 110px; }

/* Montants affichage */
.montants-recap {
  background: var(--blue-50); border: 1.5px solid var(--blue-200);
  border-radius: 10px; padding: 14px 18px; margin-top: 12px; display: none;
}
.montants-recap.show { display: block; }
.montants-recap .mr-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: .87rem; }
.montants-recap .mr-row.total { font-weight: 800; font-size: .95rem; border-top: 1px solid var(--blue-200); margin-top: 6px; padding-top: 8px; color: var(--blue-700); }

/* Boutons régime fiscal */
.regime-btns { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.regime-btn {
  padding: 9px 16px; border-radius: 8px; border: 2px solid var(--border);
  background: var(--surface); font-size: .84rem; font-weight: 700; cursor: pointer;
  transition: all .15s; color: var(--text-muted);
}
.regime-btn.tva.on   { background: #dcfce7; border-color: #22c55e; color: #166534; }
.regime-btn.pre.on   { background: #ffedd5; border-color: #f97316; color: #9a3412; }
.regime-btn.sans.on  { background: var(--blue-50); border-color: var(--blue-400); color: var(--blue-700); }

/* Fournisseur retenu badge */
.fav-badge {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 6px 14px; border-radius: 8px;
  background: #dcfce7; border: 1.5px solid #86efac; color: #166534;
  font-size: .83rem; font-weight: 700; margin-bottom: 12px;
}

/* Pièces d'exécution */
.pieces-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media(max-width:540px){ .pieces-grid{ grid-template-columns:1fr; } }

/* Modal */
.exec-modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
.exec-modal-bg.show { display: flex; }
.exec-modal { background: var(--surface); border-radius: 16px; max-width: 500px; width: 100%; padding: 24px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
.exec-modal h3 { margin: 0 0 12px; font-size: 1rem; font-weight: 800; }

/* Dossier exécuté */
.exec-success {
  background: linear-gradient(135deg, #dcfce7, #f0fdf4);
  border: 2px solid #22c55e; border-radius: 14px; padding: 24px;
  text-align: center; margin-bottom: 18px;
}
.exec-success .es-icon { font-size: 2.5rem; color: #22c55e; margin-bottom: 10px; }
.exec-success h2 { font-size: 1.1rem; font-weight: 800; margin: 0 0 6px; }
</style>

<div class="daf-wizard">

  <?php if ($flash): ?>
  <div class="alert alert-<?= $flash['type']==='success'?'success':($flash['type']==='error'?'error':'info') ?>" style="margin-bottom:16px;">
    <i class="fa-solid <?= $flash['type']==='success'?'fa-circle-check':($flash['type']==='error'?'fa-circle-xmark':'fa-circle-info') ?>"></i>
    <?= e($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <?php if ($execute): ?>
  <!-- ── DOSSIER EXÉCUTÉ ── -->
  <div class="exec-success">
    <div class="es-icon"><i class="fa-solid fa-circle-check"></i></div>
    <h2>Dossier exécuté</h2>
    <div style="font-size:.88rem;color:var(--text-muted);margin-bottom:12px;">
      <strong><?= e($numero) ?></strong> — <?= e($d['libelle']??'') ?><br>
      Montant TTC défalqué : <strong style="color:var(--blue-700);"><?= marche_fmt((float)($d['montant_ttc']??0)) ?></strong>
      <?php if ($d['execute_par']): ?>
        <br><span style="font-size:.8rem;">Exécuté le <?= format_datetime($d['date_execution']??'') ?></span>
      <?php endif; ?>
    </div>
    <?php if (!marche_pieces_completes($d)): ?>
    <div class="alert alert-info" style="text-align:left;margin:12px 0;">
      <i class="fa-solid fa-triangle-exclamation"></i>
      Pièces d'exécution manquantes. Vous pouvez les compléter maintenant.
    </div>
    <?php endif; ?>
    <div style="display:flex;gap:9px;justify-content:center;flex-wrap:wrap;margin-top:10px;">
      <a href="wizard.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle exécution</a>
      <?php if (!marche_pieces_completes($d)): ?>
        <button type="button" class="btn btn-secondary" id="btnOpenModalExec"><i class="fa-solid fa-paperclip"></i> Compléter les pièces</button>
      <?php endif; ?>
      <a href="voir.php?id=<?= $did ?>" class="btn btn-secondary"><i class="fa-solid fa-eye"></i> Voir le dossier</a>
      <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-list"></i> Liste</a>
    </div>
  </div>

  <?php else: ?>
  <!-- ── WIZARD ACTIF ── -->

  <!-- Stepper -->
  <?php
  $steps_labels = ['1'=>'Dossier','2'=>'Factures','3'=>'Favori','4'=>'Pièces','5'=>'Montants','6'=>'Exécuter'];
  $steps_icons  = ['1'=>'fa-file','2'=>'fa-receipt','3'=>'fa-star','4'=>'fa-paperclip','5'=>'fa-coins','6'=>'fa-bolt'];
  ?>
  <div class="stepper" id="stepper">
    <?php foreach ($steps_labels as $s => $lbl): ?>
      <div class="stepper-item <?= $s < $step?'done':($s==$step?'active':'') ?>"
           onclick="goStep(<?= $s ?>)"
           data-step="<?= $s ?>">
        <div class="step-dot">
          <?php if ($s < $step): ?>
            <i class="fa-solid fa-check" style="font-size:.7rem;"></i>
          <?php else: ?>
            <?= $s ?>
          <?php endif; ?>
        </div>
        <span class="step-label"><?= $lbl ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Barre de progression -->
  <div class="prog-bar"><div class="prog-fill" id="progFill" style="width:<?= round($step/6*100) ?>%;"></div></div>

  <!-- Chips récap -->
  <div class="recap-bar" id="recapBar">
    <span class="chip purple"><i class="fa-solid fa-star"></i> Retenu : <b id="recFav">—</b></span>
    <span class="chip blue"><i class="fa-solid fa-coins"></i> HT : <b id="recHt">—</b></span>
    <span class="chip blue">TTC : <b id="recTtc">—</b></span>
    <span class="chip warn" id="recPieces"><i class="fa-solid fa-clock"></i> Pièces à compléter</span>
  </div>

  <!-- ════ ÉTAPE 1 ════ -->
  <div class="daf-panel <?= $step===1?'on':'' ?>" data-step="1" id="panel1">
    <div class="daf-card">
      <div class="daf-card-hd">
        <div class="daf-card-hd-ico"><i class="fa-solid fa-file-pen"></i></div>
        <h3>Étape 1 — Identité du dossier</h3>
      </div>
      <div class="daf-card-bd">
        <form method="POST" action="">
          <?= csrf_field() ?>
          <input type="hidden" name="workflow_action" value="step1">
          <input type="hidden" name="dossier_id"      value="<?= $did ?>">

          <div class="form-group">
            <label class="form-label">N° dossier <span style="color:var(--text-muted);font-weight:400;">(automatique)</span></label>
            <div style="background:var(--bg);padding:10px 14px;border-radius:9px;font-weight:700;font-size:.9rem;color:var(--blue-700);border:1.5px solid var(--border);">
              <?= e($numero) ?>
            </div>
          </div>

          <div class="form-group" style="margin-top:14px;">
            <label class="form-label">Libellé du dossier <span style="color:var(--danger);">*</span></label>
            <input type="text" name="libelle" class="form-control" maxlength="300"
                   value="<?= e($d['libelle']??'') ?>"
                   placeholder="Ex : Achat équipements informatiques — Lot 1">
          </div>

          <div class="form-group" style="margin-top:14px;">
            <label class="form-label">Catégorie de dépense</label>
            <select name="categorie_id" class="form-control"
                    onchange="(function(s){var i=document.getElementById('catLibreW');if(!i)return;if(s.value==='__autre__'){i.style.display='';i.focus();}else{i.style.display='none';i.value='';}})(this)">
              <option value="">— Non catégorisé —</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($d['categorie_id']??0)===$cat['id']?'selected':'' ?>>
                  <?= e($cat['code_categorie']) ?> — <?= e($cat['libelle']) ?>
                </option>
              <?php endforeach; ?>
              <option value="__autre__">Autre (saisir manuellement)…</option>
            </select>
            <input type="text" name="categorie_libre" id="catLibreW" class="form-control"
                   style="margin-top:8px;display:none;" placeholder="Saisissez la catégorie de dépense">
          </div>

          <div class="daf-btns">
            <button type="submit" name="nav" value="next"  class="btn btn-primary"><i class="fa-solid fa-arrow-right"></i> Suivant</button>
            <button type="submit" name="nav" value="stay"  class="btn btn-secondary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ════ ÉTAPE 2 ════ -->
  <div class="daf-panel <?= $step===2?'on':'' ?>" data-step="2" id="panel2">
    <div class="daf-card">
      <div class="daf-card-hd">
        <div class="daf-card-hd-ico"><i class="fa-solid fa-receipt"></i></div>
        <h3>Étape 2 — Trois fournisseurs et leurs factures</h3>
      </div>
      <div class="daf-card-bd">
        <form method="POST" enctype="multipart/form-data" action="">
          <?= csrf_field() ?>
          <input type="hidden" name="workflow_action" value="step2">
          <input type="hidden" name="dossier_id"      value="<?= $did ?>">

          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
          <?php for ($i=1; $i<=3; $i++):
            $f_path = trim($d['facture_'.$i.'_path']??'');
            $f_sel  = (int)($d['fournisseur_'.$i.'_id']??0);
          ?>
          <div style="background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:14px;">
            <div style="font-size:.8rem;font-weight:700;color:var(--blue-600);margin-bottom:10px;text-transform:uppercase;letter-spacing:.07em;">
              <i class="fa-solid fa-building"></i> Fournisseur <?= $i ?>
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size:.75rem;">Entreprise</label>
              <select name="fournisseur_<?= $i ?>_id" class="form-control" style="font-size:.83rem;">
                <option value="">— Choisir —</option>
                <?php foreach ($fournisseurs as $ff): ?>
                  <option value="<?= $ff['id'] ?>" <?= $f_sel===$ff['id']?'selected':'' ?>><?= e($ff['nom_entreprise']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin-top:10px;">
              <label class="form-label" style="font-size:.75rem;">Facture (PDF)</label>
              <div class="up-zone">
                <label class="up-btn" for="facture_<?= $i ?>_input">
                  <i class="fa-solid fa-cloud-arrow-up"></i> Choisir
                </label>
                <div class="up-name" id="fact<?= $i ?>_name">
                  <?php if ($f_path): ?>
                    <a href="file.php?id=<?= $did ?>&doc=facture_<?= $i ?>" target="_blank">Voir</a>
                  <?php else: ?>
                    <span class="nd">Aucun fichier</span>
                  <?php endif; ?>
                </div>
                <input id="facture_<?= $i ?>_input" class="up-input" type="file"
                       name="facture_<?= $i ?>" accept=".pdf,application/pdf"
                       data-target="fact<?= $i ?>_name"
                       <?php if ($f_path): ?>data-existing="file.php?id=<?= $did ?>&doc=facture_<?= $i ?>"<?php endif; ?>>
              </div>
            </div>
          </div>
          <?php endfor; ?>
          </div>

          <div style="font-size:.72rem;color:var(--text-muted);margin-top:8px;">Format PDF uniquement — max 10 Mo par fichier.</div>

          <div class="daf-btns">
            <button type="button" class="btn btn-secondary" onclick="goStep(1)"><i class="fa-solid fa-arrow-left"></i> Précédent</button>
            <button type="submit" name="nav" value="next"  class="btn btn-primary"><i class="fa-solid fa-arrow-right"></i> Suivant</button>
            <button type="submit" name="nav" value="stay"  class="btn btn-secondary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ════ ÉTAPE 3 ════ -->
  <div class="daf-panel <?= $step===3?'on':'' ?>" data-step="3" id="panel3">
    <div class="daf-card">
      <div class="daf-card-hd">
        <div class="daf-card-hd-ico"><i class="fa-solid fa-star"></i></div>
        <h3>Étape 3 — Fournisseur retenu</h3>
      </div>
      <div class="daf-card-bd">
        <form method="POST" action="">
          <?= csrf_field() ?>
          <input type="hidden" name="workflow_action" value="step3">
          <input type="hidden" name="dossier_id"      value="<?= $did ?>">

          <div class="form-group">
            <label class="form-label">Sélectionner le fournisseur retenu</label>
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px;">
              <?php for ($i=1;$i<=3;$i++): ?>
              <label style="display:flex;align-items:center;gap:10px;padding:11px 14px;background:var(--bg);border:1.5px solid var(--border);border-radius:9px;cursor:pointer;transition:all .14s;"
                     id="favLabel<?= $i ?>">
                <input type="radio" name="fournisseur_favori" value="<?= $i ?>"
                       style="accent-color:var(--blue-600);width:16px;height:16px;"
                       <?= $fav===$i?'checked':'' ?>>
                <div>
                  <div style="font-weight:700;font-size:.87rem;"><?= e($nom_f[$i]) ?></div>
                  <?php $fp = trim($d['facture_'.$i.'_path']??''); if ($fp): ?>
                    <a href="file.php?id=<?= $did ?>&doc=facture_<?= $i ?>" target="_blank"
                       style="font-size:.72rem;color:var(--blue-500);">Voir la facture</a>
                  <?php endif; ?>
                </div>
              </label>
              <?php endfor; ?>
            </div>
          </div>

          <div class="daf-btns">
            <button type="button" class="btn btn-secondary" onclick="goStep(2)"><i class="fa-solid fa-arrow-left"></i> Précédent</button>
            <button type="submit" name="nav" value="next"  class="btn btn-primary"><i class="fa-solid fa-arrow-right"></i> Suivant</button>
            <button type="submit" name="nav" value="stay"  class="btn btn-secondary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ════ ÉTAPE 4 ════ -->
  <div class="daf-panel <?= $step===4?'on':'' ?>" data-step="4" id="panel4">
    <div class="daf-card">
      <div class="daf-card-hd">
        <div class="daf-card-hd-ico"><i class="fa-solid fa-paperclip"></i></div>
        <h3>Étape 4 — Pièces du fournisseur retenu</h3>
      </div>
      <div class="daf-card-bd">
        <?php if ($fav < 1 || $fav > 3): ?>
        <div class="alert alert-error">
          <i class="fa-solid fa-circle-xmark"></i>
          Sélectionnez d'abord le fournisseur retenu à l'étape 3.
        </div>
        <?php else:
          $nom_fav = e($nom_f[$fav]);
          $pieces4 = ['rccm'=>['RCCM','fa-building'], 'nif'=>['NIF','fa-file-invoice'], 'quittance'=>['Quittance','fa-receipt'], 'quitus'=>['Quitus','fa-stamp']];
        ?>
        <div class="fav-badge"><i class="fa-solid fa-star"></i> Fournisseur retenu : <?= $nom_fav ?></div>

        <form method="POST" enctype="multipart/form-data" action="">
          <?= csrf_field() ?>
          <input type="hidden" name="workflow_action" value="step4">
          <input type="hidden" name="dossier_id"      value="<?= $did ?>">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <?php foreach ($pieces4 as $key => [$lbl,$ico]):
            $p_path = trim($d['f'.$fav.'_'.$key.'_path']??'');
          ?>
          <div class="form-group">
            <label class="form-label"><i class="fa-solid <?= $ico ?>" style="color:var(--blue-400);margin-right:4px;"></i> <?= $lbl ?></label>
            <div class="up-zone">
              <label class="up-btn" for="f<?= $fav ?>_<?= $key ?>_input">
                <i class="fa-solid fa-cloud-arrow-up"></i> Choisir
              </label>
              <div class="up-name" id="p<?= $fav ?>_<?= $key ?>_name">
                <?php if ($p_path): ?>
                  <a href="file.php?id=<?= $did ?>&doc=f<?= $fav ?>_<?= $key ?>" target="_blank">Voir</a>
                <?php else: ?>
                  <span class="nd">Aucun fichier</span>
                <?php endif; ?>
              </div>
              <input id="f<?= $fav ?>_<?= $key ?>_input" class="up-input" type="file"
                     name="f<?= $fav ?>_<?= $key ?>" accept=".pdf,application/pdf"
                     data-target="p<?= $fav ?>_<?= $key ?>_name"
                     <?php if ($p_path): ?>data-existing="file.php?id=<?= $did ?>&doc=f<?= $fav ?>_<?= $key ?>"<?php endif; ?>>
            </div>
          </div>
          <?php endforeach; ?>
          </div>

          <div class="form-group" style="margin-top:14px;">
            <label class="form-label">Motif de sélection <span style="color:var(--danger);">*</span></label>
            <input type="text" name="motif_f<?= $fav ?>" class="form-control"
                   value="<?= e($d['motif_f'.$fav]??'') ?>"
                   placeholder="Ex : Meilleur rapport qualité/prix — offre la plus compétitive">
          </div>

          <div class="daf-btns">
            <button type="button" class="btn btn-secondary" onclick="goStep(3)"><i class="fa-solid fa-arrow-left"></i> Précédent</button>
            <button type="submit" name="nav" value="next"  class="btn btn-primary"><i class="fa-solid fa-arrow-right"></i> Suivant</button>
            <button type="submit" name="nav" value="stay"  class="btn btn-secondary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
          </div>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ════ ÉTAPE 5 ════ -->
  <div class="daf-panel <?= $step===5?'on':'' ?>" data-step="5" id="panel5">
    <div class="daf-card">
      <div class="daf-card-hd">
        <div class="daf-card-hd-ico"><i class="fa-solid fa-coins"></i></div>
        <h3>Étape 5 — Montant HT et régime fiscal</h3>
      </div>
      <div class="daf-card-bd">
        <form method="POST" action="" id="formStep5">
          <?= csrf_field() ?>
          <input type="hidden" name="workflow_action" value="step5">
          <input type="hidden" name="dossier_id"      value="<?= $did ?>">
          <input type="hidden" name="regime_fiscal"   id="regimeFiscal" value="<?= e($d['regime_fiscal']??'') ?>">

          <div class="form-group">
            <label class="form-label">Montant total hors taxes (HT) <span style="color:var(--danger);">*</span></label>
            <input type="text" id="montantHt" name="montant_ht" class="form-control"
                   inputmode="numeric" autocomplete="off"
                   value="<?= isset($d['montant_ht'])&&$d['montant_ht']!==''&&$d['montant_ht']!==null ? e(number_format((float)$d['montant_ht'],0,',','&nbsp;')) : '' ?>"
                   placeholder="Ex : 2 000 000"
                   style="font-size:1.05rem;font-weight:700;max-width:280px;">
            <div style="font-size:.72rem;color:var(--text-muted);margin-top:4px;">En <?= defined('CURRENCY')?e(CURRENCY):'GNF' ?> — espaces acceptés comme séparateurs de milliers</div>
          </div>

          <div class="form-group" style="margin-top:14px;">
            <label class="form-label">Régime fiscal</label>
            <div class="regime-btns">
              <button type="button" class="regime-btn tva <?= ($d['regime_fiscal']??'')==='tva'?'on':'' ?>"     id="btnTva">TVA (18 %)</button>
              <button type="button" class="regime-btn pre <?= ($d['regime_fiscal']??'')==='precompte'?'on':'' ?>" id="btnPre">Précompte (20 %)</button>
              <button type="button" class="regime-btn sans <?= ($d['regime_fiscal']??'')==='sans_tva'?'on':'' ?>" id="btnSans">Sans taxe</button>
            </div>
          </div>

          <div class="montants-recap <?= !empty($d['regime_fiscal'])?'show':'' ?>" id="montantsRecap">
            <div class="mr-row"><span>Montant HT</span><strong id="dispHt">—</strong></div>
            <div class="mr-row"><span id="labelTaxe">TVA (18 %)</span><strong id="dispTaxe">—</strong></div>
            <div class="mr-row total"><span>Total TTC</span><strong id="dispTtc">—</strong></div>
          </div>

          <div class="daf-btns">
            <button type="button" class="btn btn-secondary" onclick="goStep(4)"><i class="fa-solid fa-arrow-left"></i> Précédent</button>
            <button type="submit" name="nav" value="next"  class="btn btn-primary" id="btnStep5Next"><i class="fa-solid fa-arrow-right"></i> Suivant</button>
            <button type="submit" name="nav" value="stay"  class="btn btn-secondary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ════ ÉTAPE 6 ════ -->
  <div class="daf-panel <?= $step===6?'on':'' ?>" data-step="6" id="panel6">
    <div class="daf-card">
      <div class="daf-card-hd">
        <div class="daf-card-hd-ico"><i class="fa-solid fa-bolt"></i></div>
        <h3>Étape 6 — Exécution et défalcation</h3>
      </div>
      <div class="daf-card-bd">

        <div style="background:var(--blue-50);border:1.5px solid var(--blue-200);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:.87rem;">
          <i class="fa-solid fa-info-circle" style="color:var(--blue-500);margin-right:6px;"></i>
          Montant TTC à défalquer :
          <strong style="color:var(--blue-700);font-size:1rem;"><?= marche_fmt((float)($d['montant_ttc']??0)) ?></strong>
        </div>

        <?php if (!$has_periode): ?>
        <div class="alert alert-error">
          <i class="fa-solid fa-circle-xmark"></i>
          Aucune caisse active disponible. Créez ou activez une caisse de subvention avant d'exécuter.
        </div>
        <?php endif; ?>

        <!-- Sélection période -->
        <div class="fg2" style="margin-bottom:16px;">
          <div class="form-group">
            <label class="form-label">Année d'exécution</label>
            <select id="anneeExec" class="form-control" <?= !$has_periode?'disabled':'' ?>>
              <?php if ($has_periode): foreach ($annees_elig as $ay): ?>
                <option value="<?= $ay ?>" <?= $ay===$annee_def?'selected':'' ?>><?= $ay ?></option>
              <?php endforeach; else: ?>
                <option value="">Aucune</option>
              <?php endif; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Trimestre d'exécution</label>
            <select id="trimExec" class="form-control" <?= !$has_periode?'disabled':'' ?>>
              <?php foreach (($eligible_by_year[$annee_def]??[]) as $q): ?>
                <option value="<?= $q ?>" <?= $q===$trim_def?'selected':'' ?>>T<?= $q ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Pièces d'exécution (facultatif) -->
        <div style="margin-bottom:16px;">
          <div style="font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px;">
            Pièces d'exécution (optionnelles, complétables après)
          </div>
          <div class="pieces-grid" id="piecesGrid">
          <?php
          $exec_pieces = ['exec_lettre_paiement'=>['Lettre de paiement','fa-envelope'], 'exec_bon_engagement'=>['Bon d\'engagement','fa-file-signature'], 'exec_mandat_paiement'=>['Mandat de paiement','fa-file-contract'], 'exec_liquidation'=>['Liquidation','fa-calculator']];
          foreach ($exec_pieces as $key => [$lbl,$ico]):
            $ep = trim($d[$key.'_path']??'');
          ?>
          <div class="form-group">
            <label class="form-label" style="font-size:.75rem;"><i class="fa-solid <?= $ico ?>" style="color:var(--blue-400);margin-right:4px;"></i> <?= $lbl ?></label>
            <div class="up-zone">
              <label class="up-btn" for="<?= $key ?>_input" style="font-size:.75rem;padding:5px 9px;">
                <i class="fa-solid fa-cloud-arrow-up"></i> Choisir
              </label>
              <div class="up-name" id="<?= $key ?>_name" style="font-size:.78rem;">
                <?php if ($ep): ?>
                  <a href="file.php?id=<?= $did ?>&doc=<?= $key ?>" target="_blank">Voir</a>
                <?php else: ?>
                  <span class="nd">—</span>
                <?php endif; ?>
              </div>
              <input id="<?= $key ?>_input" class="up-input exec-piece-input" type="file"
                     name="<?= $key ?>" accept=".pdf,application/pdf"
                     data-target="<?= $key ?>_name"
                     data-key="<?= $key ?>">
            </div>
          </div>
          <?php endforeach; ?>
          </div>
        </div>

        <div class="daf-btns">
          <button type="button" class="btn btn-secondary" onclick="goStep(5)"><i class="fa-solid fa-arrow-left"></i> Précédent</button>
          <button type="button" class="btn btn-primary" id="btnExecOuvrir" <?= !$has_periode?'disabled':'' ?>>
            <i class="fa-solid fa-bolt"></i> Exécuter la dépense
          </button>
        </div>
      </div>
    </div>
  </div>

  <?php endif; // $execute ?>

</div><!-- /daf-wizard -->

<!-- ── MODAL D'EXÉCUTION ── -->
<div class="exec-modal-bg" id="execModal">
  <div class="exec-modal">
    <h3><i class="fa-solid fa-bolt" style="color:var(--blue-500);"></i> Confirmer l'exécution</h3>
    <div style="font-size:.84rem;color:var(--text-muted);margin-bottom:16px;">
      Cette action va défalquer le montant TTC sur la caisse sélectionnée et clôturer le dossier.
      Le dépôt des pièces reste optionnel et peut être complété ultérieurement.
    </div>
    <div style="background:var(--blue-50);border:1.5px solid var(--blue-200);border-radius:9px;padding:12px 14px;margin-bottom:16px;font-size:.85rem;">
      <div style="display:flex;justify-content:space-between;">
        <span>Dossier :</span><strong><?= e($numero) ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:4px;">
        <span>Montant TTC :</span><strong style="color:var(--blue-700);"><?= marche_fmt((float)($d['montant_ttc']??0)) ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:4px;">
        <span>Période :</span><strong id="modalPeriode">—</strong>
      </div>
    </div>
    <form method="POST" enctype="multipart/form-data" action="" id="formExec">
      <?= csrf_field() ?>
      <input type="hidden" name="workflow_action"    value="execute">
      <input type="hidden" name="dossier_id"         value="<?= $did ?>">
      <input type="hidden" name="annee_execution"    id="modalAnnee">
      <input type="hidden" name="trimestre_execution" id="modalTrim">
      <!-- Les fichiers pièces seront copiés depuis le formulaire parent -->
    </form>
    <div style="display:flex;gap:9px;justify-content:flex-end;margin-top:16px;">
      <button type="button" class="btn btn-secondary" id="btnModalClose">Annuler</button>
      <button type="button" class="btn btn-primary"   id="btnModalConfirm">
        <i class="fa-solid fa-check"></i> Confirmer l'exécution
      </button>
    </div>
  </div>
</div>

<?php
$eligible_json = json_encode($eligible_by_year, JSON_UNESCAPED_UNICODE);
$state_json = json_encode([
    'f1'     => trim($d['fournisseur_1_nom']??'') ?: 'Fournisseur 1',
    'f2'     => trim($d['fournisseur_2_nom']??'') ?: 'Fournisseur 2',
    'f3'     => trim($d['fournisseur_3_nom']??'') ?: 'Fournisseur 3',
    'fav'    => $fav,
    'ht'     => isset($d['montant_ht']) && $d['montant_ht'] !== null ? (float)$d['montant_ht'] : 0,
    'regime' => $d['regime_fiscal'] ?? '',
    'statut' => $statut,
], JSON_UNESCAPED_UNICODE);

$extra_scripts = '<script>
const S = '.$state_json.';
const eligByYear = '.$eligible_json.';
let currentStep = '.$step.';
const totalSteps = 6;

// ── Navigation ──
function goStep(n) {
  n = Math.max(1, Math.min(totalSteps, n));
  document.querySelectorAll(".daf-panel[data-step]").forEach(p => {
    p.classList.toggle("on", parseInt(p.dataset.step) === n);
  });
  document.querySelectorAll(".stepper-item").forEach(s => {
    const sn = parseInt(s.dataset.step);
    s.classList.toggle("done",   sn <  n);
    s.classList.toggle("active", sn === n);
  });
  const fill = document.getElementById("progFill");
  if (fill) fill.style.width = Math.round(n / totalSteps * 100) + "%";
  currentStep = n;
  updateRecap();
}
goStep(currentStep);

// ── Upload custom : preview ──
document.querySelectorAll(".up-input").forEach(inp => {
  inp.addEventListener("change", () => {
    const tid = inp.dataset.target;
    const el  = document.getElementById(tid);
    if (!el) return;
    const f = inp.files && inp.files[0];
    if (!f) return;
    const existing = inp.dataset.existing;
    el.innerHTML = "";
    const a = document.createElement("a");
    a.target = "_blank"; a.rel = "noopener noreferrer";
    a.textContent = "Voir";
    if (existing) { a.href = existing; }
    else {
      const blob = URL.createObjectURL(f);
      a.href = blob;
      a.addEventListener("click", () => setTimeout(() => URL.revokeObjectURL(blob), 30000), {once:true});
    }
    el.appendChild(a);
  });
});

// ── Récap chips ──
function fmt(n) { return new Intl.NumberFormat("fr-FR").format(Math.round(n)); }
function calcTtc(ht, reg) {
  if (reg === "tva")       return {tax: Math.round(ht*0.18*100)/100, ttc: Math.round(ht*1.18*100)/100};
  if (reg === "precompte") return {tax: Math.round(ht*0.20*100)/100, ttc: Math.round(ht*1.20*100)/100};
  return {tax: 0, ttc: ht};
}
function updateRecap() {
  const fav = S.fav;
  const nom = fav>=1&&fav<=3 ? S["f"+fav] : "—";
  const el = document.getElementById("recFav"); if (el) el.textContent = nom;
  const ht = parseFloat(document.getElementById("montantHt")?.value?.replace(/[^0-9,.]/g,"").replace(",",".") || S.ht || 0);
  const reg = document.getElementById("regimeFiscal")?.value || S.regime;
  const {tax, ttc} = calcTtc(ht, reg);
  const elHt  = document.getElementById("recHt");  if (elHt)  elHt.textContent  = ht>0?fmt(ht)+"  GNF":"—";
  const elTtc = document.getElementById("recTtc"); if (elTtc) elTtc.textContent = ht>0?fmt(ttc)+" GNF":"—";
  const elPieces = document.getElementById("recPieces");
  if (elPieces) {
    const ok = S.statut === "en_attente_execution" || S.statut === "execute";
    elPieces.className = "chip " + (ok ? "green" : "warn");
    elPieces.innerHTML = ok
      ? "<i class=\"fa-solid fa-check\"></i> Pièces OK"
      : "<i class=\"fa-solid fa-clock\"></i> Pièces à compléter";
  }
}

// ── Montants étape 5 ──
const regimeInput = document.getElementById("regimeFiscal");
const montantHtEl = document.getElementById("montantHt");
const blocMontants = document.getElementById("montantsRecap");

function parseMoney(v) { return parseFloat(String(v).replace(/[^0-9.,]/g,"").replace(",",".") || "0") || 0; }

function calcMontants() {
  const ht = parseMoney(montantHtEl?.value || "");
  const reg = regimeInput?.value || "";
  const {tax, ttc} = calcTtc(ht, reg);
  const currency = "GNF";
  const dHt   = document.getElementById("dispHt");   if (dHt)   dHt.textContent   = fmt(ht)+" "+currency;
  const dTax  = document.getElementById("dispTaxe");  if (dTax)  dTax.textContent  = fmt(tax)+" "+currency;
  const dTtc  = document.getElementById("dispTtc");   if (dTtc)  dTtc.textContent  = fmt(ttc)+" "+currency;
  const lTax  = document.getElementById("labelTaxe"); if (lTax)  lTax.textContent  = reg==="precompte"?"Précompte (20 %)":reg==="sans_tva"?"Taxe (0 %)":"TVA (18 %)";
  if (blocMontants) blocMontants.classList.toggle("show", reg !== "");
  document.getElementById("recapTtc")?.textContent && (document.getElementById("recapTtc").textContent = fmt(ttc)+" "+currency);
  updateRecap();
}

function setRegime(r) {
  if (regimeInput) regimeInput.value = r;
  ["btnTva","btnPre","btnSans"].forEach(id => document.getElementById(id)?.classList.remove("on"));
  const map = {tva:"btnTva",precompte:"btnPre",sans_tva:"btnSans"};
  if (map[r]) document.getElementById(map[r])?.classList.add("on");
  calcMontants();
}
document.getElementById("btnTva")?.addEventListener("click",  () => setRegime("tva"));
document.getElementById("btnPre")?.addEventListener("click",  () => setRegime("precompte"));
document.getElementById("btnSans")?.addEventListener("click", () => setRegime("sans_tva"));
if (montantHtEl) {
  montantHtEl.addEventListener("input", calcMontants);
  montantHtEl.addEventListener("blur", () => {
    const n = parseMoney(montantHtEl.value);
    if (n > 0) montantHtEl.value = fmt(n);
  });
}
if (S.regime) setRegime(S.regime);
calcMontants();

// ── Étape 6 : trimestres dynamiques ──
const anneeExec = document.getElementById("anneeExec");
const trimExec  = document.getElementById("trimExec");
function refreshTrims() {
  if (!anneeExec || !trimExec) return;
  const y = anneeExec.value;
  const arr = eligByYear[y] || [];
  trimExec.innerHTML = "";
  arr.forEach(q => {
    const o = document.createElement("option"); o.value = q; o.textContent = "T"+q;
    trimExec.appendChild(o);
  });
}
anneeExec?.addEventListener("change", refreshTrims);
refreshTrims();

// ── Modal exécution ──
const modal     = document.getElementById("execModal");
const btnOpen   = document.getElementById("btnExecOuvrir");
const btnClose  = document.getElementById("btnModalClose");
const btnConfirm= document.getElementById("btnModalConfirm");

btnOpen?.addEventListener("click", () => {
  if (!anneeExec?.value) { alert("Sélectionnez une année."); return; }
  if (!trimExec?.value)  { alert("Sélectionnez un trimestre."); return; }
  document.getElementById("modalAnnee").value = anneeExec.value;
  document.getElementById("modalTrim").value  = trimExec.value;
  document.getElementById("modalPeriode").textContent = "T" + trimExec.value + " / " + anneeExec.value;
  modal.classList.add("show");
});
btnClose?.addEventListener("click", () => modal.classList.remove("show"));
modal?.addEventListener("click", e => { if (e.target === modal) modal.classList.remove("show"); });

btnConfirm?.addEventListener("click", () => {
  // Copier les fichiers pièces dans le formulaire de la modal
  const formExec = document.getElementById("formExec");
  document.querySelectorAll(".exec-piece-input").forEach(inp => {
    if (inp.files && inp.files[0]) {
      const clone = document.createElement("input");
      clone.type = "file";
      clone.name = inp.name;
      const dt = new DataTransfer();
      dt.items.add(inp.files[0]);
      clone.files = dt.files;
      formExec.appendChild(clone);
    }
  });
  formExec.submit();
});

// ── Modal pièces rattrapage (dossier déjà exécuté) ──
document.getElementById("btnOpenModalExec")?.addEventListener("click", () => {
  modal.classList.add("show");
});

updateRecap();
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
