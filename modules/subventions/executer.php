<?php
// modules/subventions/executer.php — Exécuter une dépense (assistant guidé + brouillon)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/subvention.php';
require_once __DIR__ . '/../../models/depense.php';

session_demarrer();
exiger_permission('SUBVENTIONS', 'EXECUTER');

// ─────────────── TRAITEMENT POST ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Session expirée, veuillez réessayer.');
        redirect('/modules/subventions/executer.php');
    }
    $action = clean($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'supprimer') {
        depense_brouillon_supprimer($id, user_id());
        set_flash('success', 'Brouillon supprimé.');
        redirect('/modules/subventions/depenses.php');
    }

    $d = [
        'caisse_id'         => (int)($_POST['caisse_id']       ?? 0),
        'categorie_id'      => (int)($_POST['categorie_id']    ?? 0),
        'categorie_libelle' => clean($_POST['categorie_libelle'] ?? ''),
        'montant'           => str_replace([' ', ' ', ','], ['', '', '.'], (string)($_POST['montant']    ?? '')),
        'montant_ht'        => str_replace([' ', ' ', ','], ['', '', '.'], (string)($_POST['montant_ht'] ?? '')),
        'regime_fiscal'     => clean($_POST['regime_fiscal']   ?? ''),
        'fournisseur_id'    => (int)($_POST['fournisseur_id']  ?? 0),
        'employe_id'        => (int)($_POST['employe_id']      ?? 0),
        'compte_credite'    => clean($_POST['compte_credite']  ?? ''),
        'motif'             => clean($_POST['motif']           ?? ''),
    ];
    $files = [];
    $cles_fichiers = array_merge(
        array_keys(depense_types_pieces_fournisseur()),
        array_keys(depense_types_pieces()),
        ['preuve']
    );
    foreach ($cles_fichiers as $k) {
        if (isset($_FILES[$k])) $files[$k] = $_FILES[$k];
    }

    if ($action === 'executer') {
        $res = depense_finaliser($id, $d, $files);
        if ($res['succes']) {
            set_flash('success', 'Paiement effectué (' . e($res['numero']) . '). L\'argent a été retiré de la caisse.');
            redirect('/modules/subventions/depenses.php');
        }
        // depense_finaliser a déjà sauvegardé la saisie avant de valider : on conserve tout.
        set_flash('error', $res['message'] ?? 'Erreur.');
        redirect('/modules/subventions/executer.php?id=' . $id);
    }

    // action = brouillon (par défaut)
    depense_sauver_brouillon($id, $d, $files);
    set_flash('success', 'Brouillon enregistré. Vous pourrez le reprendre plus tard.');
    redirect('/modules/subventions/executer.php?id=' . $id);
}

// ─────────────── PRÉPARATION (GET) ───────────────
$caisses    = depense_caisses_eligibles();
$categories = sub_categories();

$id    = (int)($_GET['id'] ?? 0);
$draft = null;
$type  = clean($_GET['type'] ?? '');

if ($id > 0) {
    $draft = depense_charger($id);
    if (!$draft || ($draft['statut'] ?? '') !== 'brouillon') {
        set_flash('error', 'Brouillon introuvable.');
        redirect('/modules/subventions/depenses.php');
    }
    $type = $draft['type_depense'];
} elseif (($type === 'biens_services' || $type === 'personnel') && !empty($caisses)) {
    // Création d'un brouillon puis redirection vers le formulaire
    $res = depense_creer_brouillon($type);
    if (!empty($res['succes'])) redirect('/modules/subventions/executer.php?id=' . $res['id']);
    set_flash('error', $res['message'] ?? 'Impossible de démarrer.');
    redirect('/modules/subventions/executer.php');
}

$fournisseurs = depense_fournisseurs();
$employes     = depense_employes_actifs();

// Documents déjà joints au brouillon, groupés par type
$docs_par_type = [];
if ($draft && !empty($draft['documents'])) {
    foreach ($draft['documents'] as $doc) $docs_par_type[$doc['type_document']][] = $doc;
}

$page_titre      = 'Exécuter une dépense';
$page_breadcrumb = 'Subventions · Exécuter une dépense';
$module_actif    = 'SUBVENTIONS';
$subvention_sous_actif = 'EXECUTER';
include __DIR__ . '/../../views/layout/header.php';

$caisse_label = fn(array $c) => $c['code_caisse'] . ' · T' . $c['trimestre'] . '-' . $c['annee']
              . ' · reste ' . sub_fmt((float)$c['montant_disponible']);

// Valeurs pré-remplies
$v = fn(string $k, $def = '') => $draft[$k] ?? $def;

// Reprise brouillon : déduire l'étape atteinte
$etape_init = 1;
if ($draft) {
    if ($type === 'biens_services') {
        if (!empty($draft['fournisseur_id'])) {
            $etape_init = 2;
            if (!empty($draft['montant_ht']) && !empty($draft['regime_fiscal']) && !empty($draft['caisse_id'])) {
                $etape_init = 3;
            }
        }
    } else {
        if (!empty($draft['employe_id']) && !empty($draft['montant'])
            && !empty($draft['caisse_id']) && !empty($draft['compte_credite'])) {
            $etape_init = 2;
        }
    }
}
?>
<style>
.ex-wrap{max-width:780px;margin:0 auto;}
.ex-back{margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;}

/* Choix du type */
.ex-choices{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:8px;}
@media(max-width:680px){.ex-choices{grid-template-columns:1fr;}}
.ex-choice{display:block;text-decoration:none;background:var(--surface);border:1.5px solid var(--border);border-radius:18px;padding:30px 26px;transition:all .15s;text-align:center;}
.ex-choice:hover{border-color:var(--blue-400);box-shadow:0 10px 30px rgba(13,50,120,.12);transform:translateY(-3px);}
.ex-choice-ic{width:64px;height:64px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:1.7rem;margin:0 auto 16px;}
.ex-choice h3{font-size:1.12rem;margin:0 0 8px;color:var(--text);}
.ex-choice p{font-size:.86rem;color:var(--text-muted);line-height:1.55;margin:0;}

/* Stepper */
.ex-steps{display:flex;align-items:flex-start;justify-content:space-between;margin:6px 0 26px;position:relative;}
.ex-steps::before{content:"";position:absolute;top:17px;left:8%;right:8%;height:3px;background:var(--border);z-index:0;border-radius:3px;}
.ex-step{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:7px;flex:1;}
.ex-step .dot{width:36px;height:36px;border-radius:50%;background:var(--surface);border:2.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem;color:var(--text-muted);transition:all .2s;}
.ex-step .nm{font-size:.74rem;font-weight:600;color:var(--text-muted);text-align:center;max-width:110px;line-height:1.25;}
.ex-step.active .dot{background:var(--blue-600);border-color:var(--blue-600);color:#fff;box-shadow:0 0 0 4px rgba(37,112,232,.18);}
.ex-step.done .dot{background:#16a34a;border-color:#16a34a;color:#fff;}
.ex-step.active .nm,.ex-step.done .nm{color:var(--text);}

/* Carte */
.ex-card{background:var(--surface);border:1.5px solid var(--border);border-radius:18px;overflow:hidden;}
.ex-card-hd{padding:18px 24px;background:var(--bg);border-bottom:1px solid var(--border);}
.ex-card-hd h2{font-size:1.06rem;margin:0 0 3px;}
.ex-card-hd p{font-size:.82rem;color:var(--text-muted);margin:0;}
.ex-bd{padding:24px;}
.ex-panel{display:none;}
.ex-panel.on{display:block;animation:exfade .25s ease;}
@keyframes exfade{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}

.ex-grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
@media(max-width:600px){.ex-grid2{grid-template-columns:1fr;}}
.ex-field{margin-bottom:18px;}
.ex-field:last-child{margin-bottom:0;}
.ex-label{display:block;font-size:.9rem;font-weight:700;color:var(--text);margin-bottom:7px;}
.ex-hint{font-size:.76rem;color:var(--text-muted);margin-top:5px;line-height:1.4;}
.ex-req{color:var(--danger);}
.form-control{font-size:.95rem;}

/* Régime fiscal */
.ex-reg{display:flex;gap:10px;flex-wrap:wrap;}
.ex-reg-btn{flex:1;min-width:130px;border:2px solid var(--border);background:var(--surface);border-radius:12px;padding:13px 14px;cursor:pointer;font-weight:700;font-size:.9rem;text-align:center;transition:all .15s;color:var(--text);}
.ex-reg-btn small{display:block;font-weight:500;font-size:.72rem;color:var(--text-muted);margin-top:2px;}
.ex-reg-btn.on{border-color:var(--blue-600);background:var(--blue-50);color:var(--blue-700);}
.ex-reg-btn.on small{color:var(--blue-600);}

/* Récap montant */
.ex-recap{background:var(--blue-50);border:1.5px solid var(--blue-100);border-radius:12px;padding:14px 18px;margin-top:16px;}
.ex-recap .r{display:flex;justify-content:space-between;font-size:.9rem;padding:4px 0;}
.ex-recap .r.tot{font-weight:800;font-size:1.05rem;color:var(--blue-700);border-top:1.5px solid var(--blue-200);margin-top:6px;padding-top:9px;}

/* Upload */
.up-grp{margin-bottom:14px;}
.up-zone{border:2px dashed var(--blue-300);border-radius:12px;padding:14px 16px;background:var(--blue-50);cursor:pointer;display:block;transition:background .15s;}
.up-zone:hover{background:var(--blue-100);}
.up-zone input{display:none;}
.up-zone .lab{display:flex;align-items:center;gap:9px;font-size:.86rem;font-weight:600;color:var(--blue-700);}
.up-zone .prev{font-size:.78rem;color:var(--success);font-weight:600;margin-top:6px;}
.up-have{font-size:.78rem;color:#15803d;font-weight:600;margin-top:6px;display:flex;align-items:center;gap:6px;}

/* Vérif */
.ex-verif{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;}
.ex-verif .vi{background:var(--bg);border:1px solid var(--border);border-radius:11px;padding:12px 15px;}
.ex-verif .vi .k{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:3px;}
.ex-verif .vi .val{font-weight:700;font-size:.92rem;}

.ex-actions{display:flex;gap:10px;justify-content:space-between;flex-wrap:wrap;margin-top:24px;}
.ex-actions .right{display:flex;gap:10px;flex-wrap:wrap;margin-left:auto;}
.ex-note{font-size:.78rem;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;margin-bottom:16px;display:flex;gap:8px;align-items:center;}
</style>

<div class="ex-wrap">
<div class="ex-back">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Subventions</a>
  <a href="depenses.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-clock-rotate-left"></i> Historique</a>
</div>

<?php if (empty($caisses)): ?>
<div style="text-align:center;padding:50px 24px;background:var(--surface);border:1px solid var(--border);border-radius:16px;">
  <i class="fa-solid fa-triangle-exclamation" style="font-size:1.8rem;color:#f59e0b;display:block;margin-bottom:10px;"></i>
  <div style="font-size:.92rem;color:var(--text-muted);">Aucune caisse n'a d'argent disponible.<br>Créez ou approvisionnez une caisse avant de payer une dépense.</div>
</div>

<?php elseif (!$draft): ?>
<!-- ========= CHOIX DU TYPE ========= -->
<div style="text-align:center;margin-bottom:8px;font-size:.95rem;color:var(--text);font-weight:600;">Que voulez-vous payer&nbsp;?</div>
<div class="ex-choices">
  <a href="?type=biens_services" class="ex-choice">
    <div class="ex-choice-ic" style="background:var(--blue-50);color:var(--blue-600);"><i class="fa-solid fa-truck-fast"></i></div>
    <h3>Un fournisseur</h3>
    <p>Payer une entreprise pour des biens ou des services (avec TVA ou précompte).</p>
  </a>
  <a href="?type=personnel" class="ex-choice">
    <div class="ex-choice-ic" style="background:#ecfdf5;color:#059669;"><i class="fa-solid fa-user-group"></i></div>
    <h3>Executer une depense du personnel</h3>
    <p>Verser de l'argent à un employé (salaire, prime, indemnité…) sur son compte.</p>
  </a>
</div>

<?php elseif ($type === 'biens_services'): ?>
<!-- ========= ASSISTANT BIENS & SERVICES ========= -->
<div class="ex-steps" id="steps">
  <div class="ex-step active" data-s="1"><div class="dot">1</div><div class="nm">Le fournisseur</div></div>
  <div class="ex-step" data-s="2"><div class="dot">2</div><div class="nm">Le montant</div></div>
  <div class="ex-step" data-s="3"><div class="dot">3</div><div class="nm">Les documents</div></div>
  <div class="ex-step" data-s="4"><div class="dot">4</div><div class="nm">Vérification</div></div>
</div>

<form method="POST" action="executer.php" enctype="multipart/form-data" id="exForm">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$draft['id'] ?>">
  <input type="hidden" name="action" id="exAction" value="brouillon">
  <input type="hidden" name="regime_fiscal" id="regVal" value="<?= e($v('regime_fiscal')) ?>">

  <div class="ex-card">
    <!-- Étape 1 : fournisseur -->
    <div class="ex-panel on" data-panel="1">
      <div class="ex-card-hd"><h2>Qui allez-vous payer&nbsp;?</h2><p>Choisissez l'entreprise concernée par ce paiement.</p></div>
      <div class="ex-bd">
        <div class="ex-field">
          <label class="ex-label">Le fournisseur <span class="ex-req">*</span></label>
          <select name="fournisseur_id" id="fSel" class="form-control">
            <option value="">— Choisir une entreprise —</option>
            <?php foreach ($fournisseurs as $fo): ?>
              <option value="<?= (int)$fo['id'] ?>" <?= (int)$v('fournisseur_id')===(int)$fo['id']?'selected':'' ?>>
                <?= e($fo['nom_entreprise']) ?> · <?= e($fo['type_fournisseur']) ?><?= $fo['statut']==='en_attente' ? ' (en attente)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="ex-hint">L'entreprise n'est pas dans la liste&nbsp;? Ajoutez-la d'abord dans le module Fournisseurs.</div>
        </div>
        <div class="ex-field">
          <label class="ex-label">À quoi sert ce paiement&nbsp;? <span style="font-weight:500;color:var(--text-muted);">(facultatif)</span></label>
          <input type="text" name="motif" class="form-control" value="<?= e($v('motif')) ?>" placeholder="Ex : Achat de matériel informatique">
        </div>
        <div class="ex-actions">
          <span></span>
          <div class="right">
            <button type="button" class="btn btn-primary" onclick="go(2)">Continuer <i class="fa-solid fa-arrow-right"></i></button>
          </div>
        </div>
      </div>
    </div>

    <!-- Étape 2 : montant -->
    <div class="ex-panel" data-panel="2">
      <div class="ex-card-hd"><h2>Combien&nbsp;?</h2><p>Saisissez le montant hors taxes, puis choisissez le calcul à appliquer.</p></div>
      <div class="ex-bd">
        <div class="ex-field">
          <label class="ex-label">Montant hors taxes (en francs guinéens) <span class="ex-req">*</span></label>
          <input type="text" name="montant_ht" id="htInp" class="form-control" inputmode="numeric" autocomplete="off"
                 value="<?= $v('montant_ht')!==null && $v('montant_ht')!=='' ? e(number_format((float)$v('montant_ht'),0,',',' ')) : '' ?>"
                 placeholder="Ex : 12 000 000" style="font-size:1.1rem;font-weight:700;max-width:300px;">
          <div class="ex-hint">Le montant avant taxe. Les espaces sont acceptés.</div>
        </div>
        <div class="ex-field">
          <label class="ex-label">Quel calcul appliquer&nbsp;? <span class="ex-req">*</span></label>
          <div class="ex-reg">
            <div class="ex-reg-btn" data-reg="tva" onclick="setReg('tva')">Avec TVA<small>+ 18 %</small></div>
            <div class="ex-reg-btn" data-reg="precompte" onclick="setReg('precompte')">Avec précompte<small>+ 20 %</small></div>
            <div class="ex-reg-btn" data-reg="sans_tva" onclick="setReg('sans_tva')">Sans taxe<small>+ 0 %</small></div>
          </div>
        </div>
        <div class="ex-recap" id="recapBox" style="display:none;">
          <div class="r"><span>Montant hors taxes</span><strong id="rHt">—</strong></div>
          <div class="r"><span id="rTaxeLbl">Taxe</span><strong id="rTaxe">—</strong></div>
          <div class="r tot"><span>Total à payer</span><strong id="rTtc">—</strong></div>
        </div>
        <div>
        </div>

        <div class="ex-field" style="margin-top:18px;">
          <label class="ex-label">Sur quelle caisse prendre l'argent&nbsp;? <span class="ex-req">*</span></label>
          <select name="caisse_id" id="cSel" class="form-control">
            <option value="">— Choisir une caisse —</option>
            <?php foreach ($caisses as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)$v('caisse_id')===(int)$c['id']?'selected':'' ?>><?= e($caisse_label($c)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="ex-actions">
          <button type="button" class="btn btn-secondary" onclick="go(1)"><i class="fa-solid fa-arrow-left"></i> Retour</button>
          <div class="right">
            <button type="button" class="btn btn-primary" onclick="go(3)">Continuer <i class="fa-solid fa-arrow-right"></i></button>
          </div>
        </div>
      </div>
    </div>

    <!-- Étape 3 : documents -->
    <div class="ex-panel" data-panel="3">
      <div class="ex-card-hd"><h2>Les documents</h2><p>Joignez les documents. Vous pouvez aussi valider sans : un rappel vous invitera à les ajouter plus tard.</p></div>
      <div class="ex-bd">
        <div style="font-size:.86rem;font-weight:700;color:var(--blue-700);margin-bottom:10px;"><i class="fa-solid fa-building"></i> Documents du fournisseur</div>
        <?php foreach (depense_types_pieces_fournisseur() as $k => $lbl):
          $have = $docs_par_type[$k][0] ?? null; ?>
        <div class="up-grp">
          <label class="ex-label" style="font-size:.84rem;"><?= e($lbl) ?></label>
          <label class="up-zone">
            <input type="file" name="<?= $k ?>" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.jpg,.jpeg,.png,.webp" onchange="prev(this,'pv_<?= $k ?>')">
            <div class="lab"><i class="fa-solid fa-cloud-arrow-up"></i> Choisir un fichier</div>
            <div class="prev" id="pv_<?= $k ?>"></div>
          </label>
          <?php if ($have): ?><div class="up-have"><i class="fa-solid fa-circle-check"></i> Déjà joint : <?= e($have['nom_original']) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div style="font-size:.86rem;font-weight:700;color:var(--blue-700);margin:18px 0 10px;"><i class="fa-solid fa-file-lines"></i> Documents du paiement</div>
        <?php foreach (depense_types_pieces() as $k => $lbl): if ($k==='autre') continue;
          $have = $docs_par_type[$k][0] ?? null; ?>
        <div class="up-grp">
          <label class="ex-label" style="font-size:.84rem;"><?= e($lbl) ?></label>
          <label class="up-zone">
            <input type="file" name="<?= $k ?>" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.jpg,.jpeg,.png,.webp" onchange="prev(this,'pv_<?= $k ?>')">
            <div class="lab"><i class="fa-solid fa-cloud-arrow-up"></i> Choisir un fichier</div>
            <div class="prev" id="pv_<?= $k ?>"></div>
          </label>
          <?php if ($have): ?><div class="up-have"><i class="fa-solid fa-circle-check"></i> Déjà joint : <?= e($have['nom_original']) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="ex-actions">
          <button type="button" class="btn btn-secondary" onclick="go(2)"><i class="fa-solid fa-arrow-left"></i> Retour</button>
          <div class="right">
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('exAction').value='brouillon';" formnovalidate><i class="fa-solid fa-floppy-disk"></i> Enregistrer et fermer</button>
            <button type="button" class="btn btn-primary" onclick="go(4)">Continuer <i class="fa-solid fa-arrow-right"></i></button>
          </div>
        </div>
      </div>
    </div>

    <!-- Étape 4 : vérification -->
    <div class="ex-panel" data-panel="4">
      <div class="ex-card-hd"><h2>Vérifiez avant de payer</h2><p>Vérifiez les informations. Le total sera retiré de la caisse choisie.</p></div>
      <div class="ex-bd">
        <div class="ex-verif">
          <div class="vi"><div class="k">Fournisseur</div><div class="val" id="sumFourn">—</div></div>
          <div class="vi"><div class="k">Caisse</div><div class="val" id="sumCaisse">—</div></div>
          <div class="vi"><div class="k">Montant hors taxes</div><div class="val" id="sumHt">—</div></div>
          <div class="vi"><div class="k">Calcul</div><div class="val" id="sumReg">—</div></div>
          <div class="vi" style="border-color:var(--blue-300);background:var(--blue-50);"><div class="k">Total à payer</div><div class="val" id="sumTtc" style="color:var(--blue-700);font-size:1.05rem;">—</div></div>
        </div>
        <div class="ex-actions">
          <button type="button" class="btn btn-secondary" onclick="go(3)"><i class="fa-solid fa-arrow-left"></i> Retour</button>
          <div class="right">
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('exAction').value='brouillon';" formnovalidate><i class="fa-solid fa-floppy-disk"></i> Enregistrer et fermer</button>
            <button type="submit" class="btn btn-primary" id="btnPay" onclick="return finaliser();"><i class="fa-solid fa-circle-check"></i> Effectuer le paiement</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>

<form method="POST" action="executer.php" id="delForm" style="margin-top:12px;text-align:center;">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$draft['id'] ?>">
  <input type="hidden" name="action" value="supprimer">
  <button type="submit" class="btn btn-link" style="color:var(--danger);font-size:.82rem;" onclick="return confirm('Abandonner et supprimer ce brouillon ?');"><i class="fa-solid fa-trash"></i> Abandonner ce brouillon</button>
</form>

<?php else: /* ========= PERSONNEL ========= */ ?>
<div class="ex-steps" id="steps">
  <div class="ex-step active" data-s="1"><div class="dot">1</div><div class="nm">Le paiement</div></div>
  <div class="ex-step" data-s="2"><div class="dot">2</div><div class="nm">Vérification</div></div>
</div>

<form method="POST" action="executer.php" enctype="multipart/form-data" id="exForm">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$draft['id'] ?>">
  <input type="hidden" name="action" id="exAction" value="brouillon">
  <?php $cat_perso = null; foreach ($categories as $c) { if ($c['code_categorie']==='I') { $cat_perso = (int)$c['id']; break; } } ?>
  <input type="hidden" name="categorie_id" value="<?= (int)$cat_perso ?>">

  <div class="ex-card">
    <div class="ex-panel on" data-panel="1">
      <div class="ex-card-hd"><h2>Exécuter une dépense du personnell</h2><p>Renseignez l'employé, le montant et le compte qui recevra l'argent.</p></div>
      <div class="ex-bd">
        <div class="ex-grid2">
          <div class="ex-field">
            <label class="ex-label">L'employé <span class="ex-req">*</span></label>
            <select name="employe_id" id="eSel" class="form-control">
              <option value="">— Choisir un employé —</option>
              <?php foreach ($employes as $emp): ?>
                <option value="<?= (int)$emp['id'] ?>" <?= (int)$v('employe_id')===(int)$emp['id']?'selected':'' ?>><?= e($emp['nom_complet']) ?><?= $emp['matricule'] ? ' ('.e($emp['matricule']).')' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ex-field">
            <label class="ex-label">Sur quelle caisse prendre l'argent&nbsp;? <span class="ex-req">*</span></label>
            <select name="caisse_id" id="cSel" class="form-control">
              <option value="">— Choisir une caisse —</option>
              <?php foreach ($caisses as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)$v('caisse_id')===(int)$c['id']?'selected':'' ?>><?= e($caisse_label($c)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="ex-grid2" style="margin-top:4px;">
          <div class="ex-field">
            <label class="ex-label">Montant à verser (en francs guinéens) <span class="ex-req">*</span></label>
            <input type="text" name="montant" id="pMontant" class="form-control" inputmode="numeric" autocomplete="off"
                   value="<?= $v('montant')!==null && $v('montant')!=='' ? e(number_format((float)$v('montant'),0,',',' ')) : '' ?>" placeholder="Ex : 1 500 000" style="font-weight:700;">
          </div>
          <div class="ex-field">
            <label class="ex-label">Numéro de compte qui reçoit l'argent <span class="ex-req">*</span></label>
            <input type="text" name="compte_credite" class="form-control" value="<?= e($v('compte_credite')) ?>" placeholder="N° de compte de l'employé">
          </div>
        </div>
        <div class="ex-field">
          <label class="ex-label">À quoi sert ce paiement&nbsp;? <span class="ex-req">*</span></label>
          <textarea name="motif" class="form-control" rows="3" placeholder="Ex : Salaire du mois, prime, indemnité…"><?= e($v('motif')) ?></textarea>
        </div>
        <div class="ex-field">
          <label class="ex-label">Élément de preuve <span style="font-weight:500;color:var(--text-muted);">(document justifiant ce paiement)</span></label>
          <?php $have_preuve = $docs_par_type['preuve'][0] ?? null; ?>
          <label class="up-zone">
            <input type="file" name="preuve" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.jpg,.jpeg,.png,.webp" onchange="prev(this,'pv_preuve')">
            <div class="lab"><i class="fa-solid fa-cloud-arrow-up"></i> Joindre un document</div>
            <div class="prev" id="pv_preuve"></div>
          </label>
          <?php if ($have_preuve): ?><div class="up-have"><i class="fa-solid fa-circle-check"></i> Déjà joint : <?= e($have_preuve['nom_original']) ?></div><?php endif; ?>
          <div class="ex-hint">Par exemple : décision, note de service, attestation… ce qui prouve que l'employé mérite cet argent.</div>
        </div>
        <div class="ex-actions">
          <span></span>
          <div class="right">
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('exAction').value='brouillon';" formnovalidate><i class="fa-solid fa-floppy-disk"></i> Enregistrer et fermer</button>
            <button type="button" class="btn btn-primary" onclick="go(2)">Continuer <i class="fa-solid fa-arrow-right"></i></button>
          </div>
        </div>
      </div>
    </div>

    <div class="ex-panel" data-panel="2">
      <div class="ex-card-hd"><h2>Vérifiez avant de payer</h2><p>Le montant sera retiré de la caisse choisie.</p></div>
      <div class="ex-bd">
        <div class="ex-verif">
          <div class="vi"><div class="k">Employé</div><div class="val" id="sumEmp">—</div></div>
          <div class="vi"><div class="k">Caisse</div><div class="val" id="sumCaisse">—</div></div>
          <div class="vi"><div class="k">Compte</div><div class="val" id="sumCompte">—</div></div>
          <div class="vi" style="border-color:var(--blue-300);background:var(--blue-50);"><div class="k">Montant à verser</div><div class="val" id="sumMnt" style="color:var(--blue-700);font-size:1.05rem;">—</div></div>
        </div>
        <div class="ex-actions">
          <button type="button" class="btn btn-secondary" onclick="go(1)"><i class="fa-solid fa-arrow-left"></i> Retour</button>
          <div class="right">
            <button type="submit" class="btn btn-primary" onclick="return finaliserPerso();"><i class="fa-solid fa-circle-check"></i> Effectuer le paiement</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>

<form method="POST" action="executer.php" style="margin-top:12px;text-align:center;">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$draft['id'] ?>">
  <input type="hidden" name="action" value="supprimer">
  <button type="submit" class="btn btn-link" style="color:var(--danger);font-size:.82rem;" onclick="return confirm('Abandonner et supprimer ce brouillon ?');"><i class="fa-solid fa-trash"></i> Abandonner ce brouillon</button>
</form>
<?php endif; ?>
</div>

<?php
$etape_js = ($draft ?? null) ? (int)$etape_init : 1;
$extra_scripts = "<script>\nconst ETAPE_INIT = {$etape_js};\n";
$extra_scripts .= <<<'JS'
function parseMoney(s){ return parseFloat(String(s||'').replace(/[^\d.]/g,'')) || 0; }
function fmt(n){ return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' GNF'; }

/** Formate un champ montant avec espaces (ex. 2 000 000) pendant la saisie. */
function formatMoneyInput(el) {
  const digits = String(el.value || '').replace(/\D/g, '');
  el.value = digits
    ? new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Number(digits))
    : '';
}

/** le formatage sur un champ montant. */
function bindMoneyInput(id, callback) {
  const el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('input', () => {
    formatMoneyInput(el);
    if (callback) callback();
  });
}

// ── Navigation stepper ──
function curMax(){ return document.querySelectorAll('.ex-panel').length; }

/** Restaure l'étape sans validation (reprise brouillon). */
function restoreStep(n) {
  document.querySelectorAll('.ex-panel').forEach(p =>
    p.classList.toggle('on', +p.dataset.panel === n)
  );
  document.querySelectorAll('.ex-step').forEach(s => {
    const sv = +s.dataset.s;
    s.classList.toggle('active', sv === n);
    s.classList.toggle('done', sv < n);
  });
  if (n === curMax()) buildRecap();
}

function go(n){
  if(n>1 && !validateUpTo(n)) return;
  document.querySelectorAll('.ex-panel').forEach(p=>p.classList.toggle('on', +p.dataset.panel===n));
  document.querySelectorAll('.ex-step').forEach(s=>{
    const sv=+s.dataset.s;
    s.classList.toggle('active', sv===n);
    s.classList.toggle('done', sv<n);
  });
  if(n===curMax()) buildRecap();
  window.scrollTo({top:0,behavior:'smooth'});
}

function validateUpTo(n){
  // Validation progressive selon le type
  const isBS = !!document.getElementById('htInp');
  if(isBS){
    if(n>=2 && !val('fSel','Veuillez choisir le fournisseur.')) return false;
    if(n>=3){
      if(parseMoney(document.getElementById('htInp').value)<=0){ alert('Veuillez saisir le montant hors taxes.'); return false; }
      if(!document.getElementById('regVal').value){ alert('Veuillez choisir le calcul (TVA, précompte ou sans taxe).'); return false; }
      if(!val('cSel','Veuillez choisir la caisse.')) return false;
    }
  } else {
    if(n>=2){
      if(!val('eSel','Veuillez choisir l\'employé.')) return false;
      if(!val('cSel','Veuillez choisir la caisse.')) return false;
      if(parseMoney(document.getElementById('pMontant').value)<=0){ alert('Veuillez saisir le montant.'); return false; }
    }
  }
  return true;
}
function val(id,msg){ const el=document.getElementById(id); if(el && !el.value){ alert(msg); el.focus(); return false; } return true; }


// ── Régime fiscal + récap ──
function setReg(r){
  document.getElementById('regVal').value=r;
  document.querySelectorAll('.ex-reg-btn').forEach(b=>b.classList.toggle('on', b.dataset.reg===r));
  calcRecap();
}
function calcFiscal(ht, reg){
  const taux = reg==='tva'?0.18:reg==='precompte'?0.20:0;
  const taxe = Math.round(ht*taux*100)/100;
  return {taxe, ttc: Math.round((ht+taxe)*100)/100, taux};
}
function calcRecap(){
  const htEl=document.getElementById('htInp'); if(!htEl) return;
  const ht=parseMoney(htEl.value); const reg=document.getElementById('regVal').value;
  const box=document.getElementById('recapBox');
  if(ht<=0 || !reg){ box.style.display='none'; return; }
  const {taxe,ttc}=calcFiscal(ht,reg);
  box.style.display='';
  document.getElementById('rHt').textContent=fmt(ht);
  document.getElementById('rTaxe').textContent=fmt(taxe);
  document.getElementById('rTaxeLbl').textContent = reg==='precompte'?'Précompte (20 %)':reg==='sans_tva'?'Taxe (0 %)':'TVA (18 %)';
  document.getElementById('rTtc').textContent=fmt(ttc);
}

// ── Récap final ──
function txt(sel){ const o=sel.options[sel.selectedIndex]; return o?o.textContent.trim():'—'; }
function buildRecap(){
  const isBS=!!document.getElementById('htInp');
  if(isBS){
    document.getElementById('sumFourn').textContent=txt(document.getElementById('fSel'));
    document.getElementById('sumCaisse').textContent=txt(document.getElementById('cSel'));
    const ht=parseMoney(document.getElementById('htInp').value);
    const reg=document.getElementById('regVal').value;
    const {ttc}=calcFiscal(ht,reg);
    document.getElementById('sumHt').textContent=fmt(ht);
    document.getElementById('sumReg').textContent= reg==='precompte'?'Précompte (20 %)':reg==='sans_tva'?'Sans taxe':'TVA (18 %)';
    document.getElementById('sumTtc').textContent=fmt(ttc);
  } else {
    document.getElementById('sumEmp').textContent=txt(document.getElementById('eSel'));
    document.getElementById('sumCaisse').textContent=txt(document.getElementById('cSel'));
    document.getElementById('sumCompte').textContent=document.querySelector('[name=compte_credite]').value||'—';
    document.getElementById('sumMnt').textContent=fmt(parseMoney(document.getElementById('pMontant').value));
  }
}

function finaliser(){
  if(!validateUpTo(3)) return false;
  if(!confirm('Confirmer le paiement ? Le total sera retiré de la caisse.')) return false;
  document.getElementById('exAction').value='executer';
  return true;
}
function finaliserPerso(){
  if(!validateUpTo(2)) return false;
  if(!confirm('Confirmer le paiement ? Le montant sera retiré de la caisse.')) return false;
  document.getElementById('exAction').value='executer';
  return true;
}

function prev(input,id){ const el=document.getElementById(id); el.textContent = input.files[0]?('✓ '+input.files[0].name):''; }

document.addEventListener('DOMContentLoaded', () => {
  const reg = document.getElementById('regVal');
  if (reg && reg.value) {
    document.querySelectorAll('.ex-reg-btn').forEach(b =>
      b.classList.toggle('on', b.dataset.reg === reg.value)
    );
  }

  bindMoneyInput('htInp', calcRecap);   // Montant HT (fournisseur)
  bindMoneyInput('pMontant');           // Montant personnel

  const ht = document.getElementById('htInp');
  if (ht) calcRecap();

  if (typeof ETAPE_INIT !== 'undefined' && ETAPE_INIT > 1) {
    restoreStep(ETAPE_INIT);
  }
});
</script>
JS;
include __DIR__ . '/../../views/layout/footer.php';
