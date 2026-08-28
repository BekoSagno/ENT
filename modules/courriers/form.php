<?php
// modules/courriers/form.php — Enregistrer / modifier un courrier
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/courrier.php';

session_demarrer();

$cid  = (int)($_GET['id'] ?? 0);
$cour = $cid ? courrier_charger($cid) : null;
$mode = $cour ? 'modifier' : 'creer';

// Permission : CREER pour un nouveau, MODIFIER pour l'édition
exiger_permission('COURRIERS', $mode === 'creer' ? 'CREER' : 'MODIFIER');

// Sens par défaut (depuis l'URL pour la création)
$sens_defaut = $cour['sens'] ?? (in_array($_GET['sens'] ?? '', ['ENTRANT','SORTANT'], true) ? $_GET['sens'] : 'ENTRANT');

$services     = courrier_services();
$utilisateurs = courrier_utilisateurs();
$TYPES        = courrier_types();
$CANAUX       = courrier_canaux();
$PRIOS        = courrier_priorites();
$CONFS        = courrier_confidentialites();

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $data = [
        'sens'                       => clean($_POST['sens']                       ?? 'ENTRANT'),
        'canal'                      => clean($_POST['canal']                      ?? 'PHYSIQUE'),
        'type_courrier'              => clean($_POST['type_courrier']              ?? 'LETTRE'),
        'objet'                      => clean($_POST['objet']                      ?? ''),
        'contenu'                    => trim($_POST['contenu']                     ?? ''),
        'correspondant_nom'          => clean($_POST['correspondant_nom']          ?? ''),
        'correspondant_organisation' => clean($_POST['correspondant_organisation'] ?? ''),
        'correspondant_email'        => clean($_POST['correspondant_email']        ?? ''),
        'correspondant_telephone'    => clean($_POST['correspondant_telephone']    ?? ''),
        'correspondant_adresse'      => clean($_POST['correspondant_adresse']      ?? ''),
        'date_courrier'              => clean($_POST['date_courrier']              ?? ''),
        'date_reception'             => clean($_POST['date_reception']             ?? ''),
        'date_echeance'              => clean($_POST['date_echeance']              ?? ''),
        'priorite'                   => clean($_POST['priorite']                   ?? 'NORMALE'),
        'confidentialite'            => clean($_POST['confidentialite']            ?? 'NORMAL'),
        'service_id'                 => clean($_POST['service_id']                 ?? ''),
        'affecte_a'                  => clean($_POST['affecte_a']                  ?? ''),
        'instruction'                => trim($_POST['instruction']                 ?? ''),
    ];
    // date_reception : datetime-local → 'Y-m-d H:i:s'
    if ($data['date_reception']) $data['date_reception'] = str_replace('T', ' ', $data['date_reception']) . ':00';

    $erreurs = [];
    if (empty($data['objet']))               $erreurs[] = 'L\'objet du courrier est obligatoire.';
    if (empty($data['correspondant_nom']) && empty($data['correspondant_organisation']))
        $erreurs[] = 'Indiquez au moins le nom ou l\'organisation du correspondant.';
    if (!empty($data['correspondant_email']) && !filter_var($data['correspondant_email'], FILTER_VALIDATE_EMAIL))
        $erreurs[] = 'L\'adresse email du correspondant est invalide.';

    if (empty($erreurs)) {
        $res = $mode === 'creer'
            ? courrier_creer($data)
            : courrier_modifier($cid, $data);

        if ($res['succes']) {
            $id_cible = $mode === 'creer' ? $res['id'] : $cid;

            // Upload des scans / pièces jointes
            if (!empty($_FILES['documents']['name'][0])) {
                foreach ($_FILES['documents']['name'] as $k => $nom) {
                    if ($_FILES['documents']['error'][$k] !== UPLOAD_ERR_OK) continue;
                    $fk = [
                        'name'     => $nom,
                        'tmp_name' => $_FILES['documents']['tmp_name'][$k],
                        'error'    => $_FILES['documents']['error'][$k],
                        'size'     => $_FILES['documents']['size'][$k],
                    ];
                    $type_doc = ($data['sens'] === 'ENTRANT') ? 'SCAN' : 'PIECE_JOINTE';
                    $r = courrier_doc_ajouter($id_cible, $fk, $type_doc);
                    if (!$r['succes']) set_flash('error', 'Pièce « '.$nom.' » : '.$r['message']);
                }
            }

            set_flash('success', $mode === 'creer'
                ? 'Courrier enregistré sous la référence '.$res['reference'].'.'
                : 'Courrier mis à jour.');
            header('Location: voir.php?id=' . $id_cible);
            exit;
        }
        $erreurs[] = $res['message'] ?? 'Erreur inconnue.';
    }
    foreach ($erreurs as $e_msg) set_flash('error', $e_msg);
    // Conserver les valeurs saisies
    $cour = array_merge($cour ?? [], $data);
}

$page_titre      = $mode === 'creer' ? 'Nouveau courrier' : 'Modifier le courrier';
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Courriers</a> · '
                 . ($mode === 'creer' ? 'Enregistrement' : e($cour['reference'] ?? ''));
$module_actif    = 'COURRIERS';
include __DIR__ . '/../../views/layout/header.php';

$v = fn($k, $def='') => e((string)($cour[$k] ?? $def));
?>
<style>
.fsec{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px;}
.fsec-hd{display:flex;align-items:center;gap:10px;padding:13px 20px;background:var(--bg);border-bottom:1px solid var(--border);}
.fsec-ico{width:32px;height:32px;border-radius:8px;background:var(--blue-100);color:var(--blue-600);display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;}
.fsec-hd h3{font-size:.88rem;font-weight:700;margin:0;}
.fsec-hd .sub{font-size:.71rem;color:var(--text-muted);}
.fsec-bd{padding:20px 22px;}
.fg2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.fg3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
@media(max-width:640px){.fg2,.fg3{grid-template-columns:1fr;}}

.sens-switch{display:flex;gap:10px;flex-wrap:wrap;}
.sens-opt{flex:1;min-width:160px;cursor:pointer;border:2px solid var(--border);border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:12px;transition:all .15s;}
.sens-opt input{display:none;}
.sens-opt .ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:var(--bg);color:var(--text-muted);}
.sens-opt.on{border-color:var(--blue-500);background:var(--blue-50);}
.sens-opt.on .ico{background:var(--blue-600);color:#fff;}
.sens-opt strong{font-size:.86rem;}.sens-opt small{font-size:.7rem;color:var(--text-muted);display:block;}

.up-zone{border:2px dashed var(--blue-300);border-radius:10px;padding:16px;background:var(--blue-50);cursor:pointer;transition:all .14s;}
.up-zone:hover{background:var(--blue-100);border-color:var(--blue-500);}
.up-zone input[type=file]{display:none;}
.up-zone .up-label{display:flex;align-items:center;gap:8px;font-size:.84rem;font-weight:600;color:var(--blue-600);}
.up-zone .up-hint{font-size:.7rem;color:var(--text-muted);margin-top:4px;}
.up-preview{font-size:.75rem;margin-top:8px;color:var(--success);font-weight:600;}

.form-actions{position:sticky;bottom:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-top:1px solid var(--border);padding:13px 0;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;}
.corresp-title{font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px;}
</style>

<div style="margin-bottom:14px;">
  <a href="<?= $cour && $mode==='modifier' ? 'voir.php?id='.$cid : 'index.php' ?>" class="btn btn-secondary btn-sm">
    <i class="fa-solid fa-arrow-left"></i> Retour
  </a>
</div>

<form method="POST" enctype="multipart/form-data" id="courForm">
  <?= csrf_field() ?>

  <!-- ① Nature du courrier -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-right-left"></i></div>
      <div><h3>Nature du courrier</h3><div class="sub">Sens, canal et type</div></div>
    </div>
    <div class="fsec-bd">
      <div class="sens-switch" style="margin-bottom:18px;">
        <label class="sens-opt <?= $sens_defaut==='ENTRANT'?'on':'' ?>" data-sens="ENTRANT">
          <input type="radio" name="sens" value="ENTRANT" <?= $sens_defaut==='ENTRANT'?'checked':'' ?>>
          <div class="ico"><i class="fa-solid fa-arrow-down-long"></i></div>
          <div><strong>Courrier entrant</strong><small>Reçu par le CRICT</small></div>
        </label>
        <label class="sens-opt <?= $sens_defaut==='SORTANT'?'on':'' ?>" data-sens="SORTANT">
          <input type="radio" name="sens" value="SORTANT" <?= $sens_defaut==='SORTANT'?'checked':'' ?>>
          <div class="ico"><i class="fa-solid fa-arrow-up-long"></i></div>
          <div><strong>Courrier sortant</strong><small>Émis par le CRICT</small></div>
        </label>
      </div>

      <div class="fg3">
        <div class="form-group">
          <label class="form-label">Canal de réception/envoi</label>
          <select name="canal" class="form-control">
            <?php foreach ($CANAUX as $k=>$vv): ?>
              <option value="<?= $k ?>" <?= ($cour['canal']??'PHYSIQUE')===$k?'selected':'' ?>><?= $vv['label'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Type de courrier</label>
          <select name="type_courrier" class="form-control">
            <?php foreach ($TYPES as $k=>$lbl): ?>
              <option value="<?= $k ?>" <?= ($cour['type_courrier']??'LETTRE')===$k?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Confidentialité</label>
          <select name="confidentialite" class="form-control">
            <?php foreach ($CONFS as $k=>$vv): ?>
              <option value="<?= $k ?>" <?= ($cour['confidentialite']??'NORMAL')===$k?'selected':'' ?>><?= $vv['label'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- ② Objet & contenu -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-file-lines"></i></div>
      <div><h3>Objet & contenu</h3><div class="sub">Description du courrier</div></div>
    </div>
    <div class="fsec-bd">
      <div class="form-group">
        <label class="form-label">Objet <span style="color:var(--danger);">*</span></label>
        <input type="text" name="objet" class="form-control" required maxlength="255"
               placeholder="Ex : Demande de partenariat, Convocation réunion…" value="<?= $v('objet') ?>">
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Contenu / résumé</label>
        <textarea name="contenu" class="form-control" rows="4"
                  placeholder="Résumé du courrier, ou corps de l'email reçu/à envoyer…"><?= e($cour['contenu'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <!-- ③ Correspondant -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-user-tie"></i></div>
      <div><h3>Correspondant</h3><div class="sub" id="correspHint">Expéditeur du courrier</div></div>
    </div>
    <div class="fsec-bd">
      <div class="fg2">
        <div class="form-group">
          <label class="form-label">Nom / Contact</label>
          <input type="text" name="correspondant_nom" class="form-control" placeholder="Nom de la personne" value="<?= $v('correspondant_nom') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Organisation</label>
          <input type="text" name="correspondant_organisation" class="form-control" placeholder="Entreprise, ministère, institution…" value="<?= $v('correspondant_organisation') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="correspondant_email" class="form-control" placeholder="adresse@exemple.com" value="<?= $v('correspondant_email') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone</label>
          <input type="text" name="correspondant_telephone" class="form-control" placeholder="+224…" value="<?= $v('correspondant_telephone') ?>">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
          <label class="form-label">Adresse</label>
          <input type="text" name="correspondant_adresse" class="form-control" placeholder="Adresse postale" value="<?= $v('correspondant_adresse') ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- ④ Dates & priorité -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-regular fa-calendar"></i></div>
      <div><h3>Dates & priorité</h3><div class="sub">Réception et délais de traitement</div></div>
    </div>
    <div class="fsec-bd">
      <div class="fg3">
        <div class="form-group">
          <label class="form-label">Date du courrier</label>
          <input type="date" name="date_courrier" class="form-control" value="<?= $v('date_courrier') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Date de réception</label>
          <input type="datetime-local" name="date_reception" class="form-control"
                 value="<?= e($cour['date_reception'] ? str_replace(' ', 'T', substr($cour['date_reception'],0,16)) : date('Y-m-d\TH:i')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Échéance de traitement</label>
          <input type="date" name="date_echeance" class="form-control" value="<?= $v('date_echeance') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Priorité</label>
          <select name="priorite" class="form-control">
            <?php foreach ($PRIOS as $k=>$vv): ?>
              <option value="<?= $k ?>" <?= ($cour['priorite']??'NORMALE')===$k?'selected':'' ?>><?= $vv['label'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- ⑤ Orientation -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-share-from-square"></i></div>
      <div><h3>Orientation & affectation</h3><div class="sub">Service concerné et agent en charge</div></div>
    </div>
    <div class="fsec-bd">
      <div class="fg2">
        <div class="form-group">
          <label class="form-label">Service concerné</label>
          <select name="service_id" class="form-control">
            <option value="">— Aucun —</option>
            <?php foreach ($services as $sv): ?>
              <option value="<?= $sv['id'] ?>" <?= (string)($cour['service_id']??'')===(string)$sv['id']?'selected':'' ?>><?= e($sv['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Affecter à (agent)</label>
          <select name="affecte_a" class="form-control">
            <option value="">— Non affecté —</option>
            <?php foreach ($utilisateurs as $u): ?>
              <option value="<?= $u['id'] ?>" <?= (string)($cour['affecte_a']??'')===(string)$u['id']?'selected':'' ?>>
                <?= e($u['nom_complet']) ?> — <?= e($u['groupe']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Instruction / annotation</label>
        <textarea name="instruction" class="form-control" rows="2"
                  placeholder="Consigne de traitement, à l'attention de l'agent affecté…"><?= e($cour['instruction'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <!-- ⑥ Numérisation / pièces jointes -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-paperclip"></i></div>
      <div><h3>Numérisation & pièces jointes</h3><div class="sub">Scans et fichiers — PDF, Word, Excel, images — max 15 Mo</div></div>
    </div>
    <div class="fsec-bd">
      <label class="up-zone">
        <input type="file" name="documents[]" multiple
               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.tif,.tiff"
               onchange="previewMultiple(this)">
        <div class="up-label"><i class="fa-solid fa-cloud-arrow-up"></i> Joindre des fichiers (scan du courrier, annexes…)</div>
        <div class="up-hint">Plusieurs fichiers acceptés — PDF, Word, Excel, JPEG, PNG, WebP, TIFF — max 15 Mo chacun</div>
        <div class="up-preview" id="docs_prev"></div>
      </label>
      <?php if ($mode === 'modifier'): ?>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:8px;">
          <i class="fa-solid fa-circle-info"></i> Les pièces déjà enregistrées restent visibles sur la fiche du courrier.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-actions">
    <a href="<?= $cour && $mode==='modifier' ? 'voir.php?id='.$cid : 'index.php' ?>" class="btn btn-secondary">
      <i class="fa-solid fa-xmark"></i> Annuler
    </a>
    <button type="submit" class="btn btn-primary">
      <i class="fa-solid <?= $mode==='creer'?'fa-floppy-disk':'fa-floppy-disk' ?>"></i>
      <?= $mode==='creer' ? 'Enregistrer le courrier' : 'Enregistrer les modifications' ?>
    </button>
  </div>
</form>

<?php
$extra_scripts = '<script>
// Bascule sens
document.querySelectorAll(".sens-opt").forEach(opt => {
  opt.addEventListener("click", () => {
    document.querySelectorAll(".sens-opt").forEach(o => o.classList.remove("on"));
    opt.classList.add("on");
    opt.querySelector("input").checked = true;
    const sens = opt.dataset.sens;
    document.getElementById("correspHint").textContent =
      sens === "ENTRANT" ? "Expéditeur du courrier" : "Destinataire du courrier";
  });
});
function previewMultiple(input) {
  const el = document.getElementById("docs_prev");
  if (!input.files.length) { el.textContent = ""; return; }
  const names = Array.from(input.files).map(f => f.name).join(", ");
  el.textContent = "✓ " + input.files.length + " fichier" + (input.files.length>1?"s":"") + " : " + names;
}
document.querySelectorAll(".up-zone").forEach(z => {
  z.addEventListener("click", () => z.querySelector("input[type=file]").click());
});
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
