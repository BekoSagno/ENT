<?php
// modules/fournisseurs/form.php — Créer / modifier un fournisseur
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/fournisseur.php';

session_demarrer();
exiger_permission('FOURNISSEURS', 'CREER');

$fid   = (int)($_GET['id'] ?? 0);
$four  = $fid ? fournisseur_charger($fid) : null;
$mode  = $four ? 'modifier' : 'creer';

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $data = [
        'nom_entreprise'   => clean($_POST['nom_entreprise']   ?? ''),
        'telephone'        => clean($_POST['telephone']        ?? ''),
        'site_web'         => clean($_POST['site_web']         ?? ''),
        'email'            => clean($_POST['email']            ?? ''),
        'type_fournisseur' => clean($_POST['type_fournisseur'] ?? ''),
        'categorie'        => clean($_POST['categorie']        ?? ''),
        'nom_gerant'       => clean($_POST['nom_gerant']       ?? ''),
        'telephone_gerant' => clean($_POST['telephone_gerant'] ?? ''),
        'numero_rccm'      => clean($_POST['numero_rccm']      ?? ''),
        'quittance'        => clean($_POST['quittance']        ?? ''),
        'nif'              => clean($_POST['nif']              ?? ''),
        'nom_utilisateur'  => clean($_POST['nom_utilisateur']  ?? ''),
        'mot_de_passe'     => $_POST['mot_de_passe']           ?? '',
        'statut'           => clean($_POST['statut']           ?? 'en_attente'),
    ];

    // Validation minimale
    $erreurs = [];
    if (empty($data['nom_entreprise']))   $erreurs[] = 'Nom de l\'entreprise obligatoire.';
    if (empty($data['email']))            $erreurs[] = 'Email obligatoire.';
    if (empty($data['type_fournisseur'])) $erreurs[] = 'Type de fournisseur obligatoire.';
    if (empty($data['categorie']))        $erreurs[] = 'Catégorie obligatoire.';
    if (empty($data['nom_gerant']))       $erreurs[] = 'Nom du gérant obligatoire.';
    // NIF et RCCM facultatifs : ils ne bloquent plus la création.
    if ($mode === 'creer') {
        if (empty($data['nom_utilisateur'])) $erreurs[] = 'Nom d\'utilisateur obligatoire.';
        if (empty($data['mot_de_passe']))    $erreurs[] = 'Mot de passe obligatoire.';
    }

    if (empty($erreurs)) {
        $res = $mode === 'creer'
            ? fournisseur_creer($data)
            : fournisseur_modifier($fid, $data);

        if ($res['succes']) {
            $id_cible = $mode === 'creer' ? $res['id'] : $fid;

            // Upload documents
            $types_docs = ['doc_piece_identite'=>'piece_identite','doc_rccm'=>'rccm',
                           'doc_nif'=>'nif','doc_quittance'=>'quittance','doc_autre'=>'autre'];
            foreach ($types_docs as $champ => $type_doc) {
                if (!empty($_FILES[$champ]['name']) && $_FILES[$champ]['error'] === UPLOAD_ERR_OK) {
                    $r = fournisseur_doc_ajouter($id_cible, $_FILES[$champ], $type_doc);
                    if (!$r['succes']) set_flash('error', 'Doc '.$type_doc.' : '.$r['message']);
                }
            }
            // Documents multiples (autre)
            if (!empty($_FILES['docs_autres']['name'][0])) {
                foreach ($_FILES['docs_autres']['name'] as $k => $nom) {
                    if ($_FILES['docs_autres']['error'][$k] !== UPLOAD_ERR_OK) continue;
                    $fk = ['name'=>$nom,'tmp_name'=>$_FILES['docs_autres']['tmp_name'][$k],
                           'error'=>$_FILES['docs_autres']['error'][$k],
                           'size' =>$_FILES['docs_autres']['size'][$k]];
                    fournisseur_doc_ajouter($id_cible, $fk, 'autre');
                }
            }

            set_flash('success', $mode === 'creer' ? 'Fournisseur créé avec succès.' : 'Fournisseur modifié.');
            header('Location: voir.php?id=' . $id_cible);
            exit;
        }
        $erreurs[] = $res['message'] ?? 'Erreur inconnue.';
    }
    foreach ($erreurs as $e_msg) set_flash('error', $e_msg);
}

$page_titre      = $mode === 'creer' ? 'Nouveau fournisseur' : 'Modifier le fournisseur';
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Fournisseurs</a> · '
                 . ($mode === 'creer' ? 'Création' : e($four['nom_entreprise']??''));
$module_actif    = 'FOURNISSEURS';
include __DIR__ . '/../../views/layout/header.php';

// Docs existants groupés par type
$docs_existants = [];
if ($four) {
    foreach ($four['documents'] ?? [] as $d) {
        $docs_existants[$d['type_document']][] = $d;
    }
}
$types_labels = [
    'piece_identite' => 'Pièce d\'identité',
    'rccm'           => 'RCCM',
    'nif'            => 'NIF',
    'quittance'      => 'Quittance de paiement',
    'autre'          => 'Autre document',
];
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

/* Upload zone */
.up-zone{border:2px dashed var(--blue-300);border-radius:10px;padding:14px 16px;background:var(--blue-50);cursor:pointer;transition:all .14s;}
.up-zone:hover{background:var(--blue-100);border-color:var(--blue-500);}
.up-zone input[type=file]{display:none;}
.up-zone .up-label{display:flex;align-items:center;gap:8px;font-size:.82rem;font-weight:600;color:var(--blue-600);}
.up-zone .up-hint{font-size:.7rem;color:var(--text-muted);margin-top:3px;}
.up-preview{font-size:.75rem;margin-top:6px;color:var(--success);font-weight:600;}

/* Docs existants */
.doc-exist{display:flex;align-items:center;gap:8px;padding:7px 10px;background:var(--bg);border-radius:8px;margin-bottom:5px;}
.doc-exist-ico{width:26px;height:26px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.78rem;flex-shrink:0;background:#fee2e2;color:#dc2626;}
.doc-exist-ico.word{background:#dbeafe;color:#1d4ed8;}
.doc-exist-ico.img{background:#fce7f3;color:#9d174d;}

/* Sticky footer */
.form-actions{position:sticky;bottom:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-top:1px solid var(--border);padding:13px 0;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;}
</style>

<div style="margin-bottom:14px;">
  <a href="<?= $four ? 'voir.php?id='.$fid : 'index.php' ?>" class="btn btn-secondary btn-sm">
    <i class="fa-solid fa-arrow-left"></i> Retour
  </a>
</div>

<form method="POST" enctype="multipart/form-data" id="fourForm">
  <?= csrf_field() ?>

  <!-- ① Informations générales -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-building"></i></div>
      <div><h3>Informations générales</h3><div class="sub">Identité de l'entreprise</div></div>
    </div>
    <div class="fsec-bd">
      <div class="form-group">
        <label class="form-label">Nom de l'entreprise <span style="color:var(--danger);">*</span></label>
        <input type="text" name="nom_entreprise" class="form-control" required
               value="<?= e($four['nom_entreprise'] ?? '') ?>"
               placeholder="Ex : SARL TECH GUINEE">
      </div>
      <div class="fg3" style="margin-top:14px;">
        <div class="form-group">
          <label class="form-label">Type de fournisseur <span style="color:var(--danger);">*</span></label>
          <?php
            $type_val   = $four['type_fournisseur'] ?? '';
            $type_opts  = ['Biens', 'Services', 'Biens et services'];
            $type_known = in_array($type_val, $type_opts, true);
            $type_autre = ($type_val !== '' && !$type_known);
          ?>
          <select class="form-control" id="typeSel" onchange="toggleManuel('typeSel','typeInput')">
            <option value="">— Choisir —</option>
            <?php foreach ($type_opts as $o): ?>
              <option value="<?= e($o) ?>" <?= $type_val===$o?'selected':'' ?>><?= e($o) ?></option>
            <?php endforeach; ?>
            <option value="__autre__" <?= $type_autre?'selected':'' ?>>Autre (saisir manuellement)…</option>
          </select>
          <input type="text" name="type_fournisseur" id="typeInput" class="form-control"
                 style="margin-top:8px;<?= ($type_autre ? '' : 'display:none;') ?>"
                 value="<?= e($type_val) ?>" placeholder="Saisissez le type de fournisseur">
        </div>
        <div class="form-group">
          <label class="form-label">Catégorie <span style="color:var(--danger);">*</span></label>
          <?php
            $cat_val   = $four['categorie'] ?? '';
            $cat_opts  = ['Informatique & Télécoms','Fournitures de bureau','BTP & Travaux',
                          'Transport & Logistique','Alimentation & Restauration','Santé & Médical',
                          'Consulting & Formation','Énergie & Électricité'];
            $cat_known = in_array($cat_val, $cat_opts, true);
            $cat_autre = ($cat_val !== '' && !$cat_known);
          ?>
          <select class="form-control" id="catSel" onchange="toggleManuel('catSel','catInput')">
            <option value="">— Choisir —</option>
            <?php foreach ($cat_opts as $o): ?>
              <option value="<?= e($o) ?>" <?= $cat_val===$o?'selected':'' ?>><?= e($o) ?></option>
            <?php endforeach; ?>
            <option value="__autre__" <?= $cat_autre?'selected':'' ?>>Autre (saisir manuellement)…</option>
          </select>
          <input type="text" name="categorie" id="catInput" class="form-control"
                 style="margin-top:8px;<?= ($cat_autre ? '' : 'display:none;') ?>"
                 value="<?= e($cat_val) ?>" placeholder="Saisissez la catégorie">
        </div>
        <div class="form-group">
          <label class="form-label">Statut</label>
          <select name="statut" class="form-control">
            <option value="en_attente" <?= ($four['statut']??'en_attente')==='en_attente'?'selected':'' ?>>En attente</option>
            <option value="valide"     <?= ($four['statut']??'')==='valide'    ?'selected':'' ?>>Validé</option>
            <option value="suspendu"   <?= ($four['statut']??'')==='suspendu'  ?'selected':'' ?>>Suspendu</option>
          </select>
        </div>
      </div>
      <div class="fg3" style="margin-top:14px;">
        <div class="form-group">
          <label class="form-label">Téléphone entreprise <span style="color:var(--danger);">*</span></label>
          <input type="tel" name="telephone" class="form-control" required
                 value="<?= e($four['telephone'] ?? '') ?>" placeholder="+224 6XX XXX XXX">
        </div>
        <div class="form-group">
          <label class="form-label">Email <span style="color:var(--danger);">*</span></label>
          <input type="email" name="email" class="form-control" required
                 value="<?= e($four['email'] ?? '') ?>" placeholder="contact@entreprise.com">
        </div>
        <div class="form-group">
          <label class="form-label">Site web</label>
          <input type="url" name="site_web" class="form-control"
                 value="<?= e($four['site_web'] ?? '') ?>" placeholder="https://…">
        </div>
      </div>
    </div>
  </div>

  <!-- ② Gérant -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-user-tie"></i></div>
      <div><h3>Gérant / Responsable</h3><div class="sub">Contact principal de l'entreprise</div></div>
    </div>
    <div class="fsec-bd">
      <div class="fg2">
        <div class="form-group">
          <label class="form-label">Nom complet du gérant <span style="color:var(--danger);">*</span></label>
          <input type="text" name="nom_gerant" class="form-control" required
                 value="<?= e($four['nom_gerant'] ?? '') ?>" placeholder="Prénom NOM">
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone gérant <span style="color:var(--danger);">*</span></label>
          <input type="tel" name="telephone_gerant" class="form-control" required
                 value="<?= e($four['telephone_gerant'] ?? '') ?>" placeholder="+224 6XX XXX XXX">
        </div>
      </div>
    </div>
  </div>

  <!-- ③ Identifiants légaux -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-id-card"></i></div>
      <div><h3>Identifiants légaux</h3><div class="sub">RCCM, NIF, Quittance</div></div>
    </div>
    <div class="fsec-bd">
      <div class="fg3">
        <div class="form-group">
          <label class="form-label">N° RCCM <span style="font-size:.7rem;color:var(--text-muted);font-weight:600;">(facultatif)</span></label>
          <input type="text" name="numero_rccm" class="form-control"
                 value="<?= e($four['numero_rccm'] ?? '') ?>" placeholder="RCCM/GN/…">
        </div>
        <div class="form-group">
          <label class="form-label">NIF <span style="font-size:.7rem;color:var(--text-muted);font-weight:600;">(facultatif)</span></label>
          <input type="text" name="nif" class="form-control"
                 value="<?= e($four['nif'] ?? '') ?>" placeholder="NIF XXXXXXXXX">
        </div>
        <div class="form-group">
          <label class="form-label">Quittance</label>
          <input type="text" name="quittance" class="form-control"
                 value="<?= e($four['quittance'] ?? '') ?>" placeholder="N° quittance">
        </div>
      </div>
    </div>
  </div>

  <!-- ④ Accès compte -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-key"></i></div>
      <div>
        <h3>Accès compte</h3>
        <div class="sub"><?= $mode==='modifier' ? 'Laisser le mot de passe vide pour ne pas le changer' : 'Identifiants de connexion fournisseur' ?></div>
      </div>
    </div>
    <div class="fsec-bd">
      <div class="fg2">
        <div class="form-group">
          <label class="form-label">Nom d'utilisateur <?= $mode==='creer'?'<span style="color:var(--danger);">*</span>':'' ?></label>
          <input type="text" name="nom_utilisateur" class="form-control"
                 value="<?= e($four['nom_utilisateur'] ?? '') ?>"
                 placeholder="identifiant_unique"
                 <?= $mode==='creer'?'required':'' ?>
                 <?= $mode==='modifier'?'readonly style="background:var(--bg);color:var(--text-muted);"':'' ?>>
          <?php if ($mode==='modifier'): ?>
            <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px;">Le nom d'utilisateur ne peut pas être modifié.</div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">Mot de passe <?= $mode==='creer'?'<span style="color:var(--danger);">*</span>':'' ?></label>
          <div style="position:relative;">
            <input type="password" name="mot_de_passe" id="mdp" class="form-control"
                   placeholder="<?= $mode==='modifier'?'Laisser vide pour ne pas changer':'Minimum 8 caractères' ?>"
                   style="padding-right:40px;"
                   <?= $mode==='creer'?'required':'' ?>>
            <button type="button" onclick="toggleMdp()"
                    style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:.85rem;">
              <i class="fa-regular fa-eye" id="eyeMdp"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ⑤ Documents -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-paperclip"></i></div>
      <div><h3>Documents justificatifs</h3><div class="sub">PDF, Word, JPEG, PNG — max 10 Mo par fichier</div></div>
    </div>
    <div class="fsec-bd">

      <?php if (!empty($docs_existants)): ?>
      <div style="margin-bottom:18px;">
        <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px;">Documents existants</div>
        <?php foreach ($docs_existants as $type => $docs): ?>
        <div style="margin-bottom:10px;">
          <div style="font-size:.75rem;font-weight:700;color:var(--blue-600);margin-bottom:4px;"><?= $types_labels[$type] ?? $type ?></div>
          <?php foreach ($docs as $doc):
            $ext = strtolower($doc['extension'] ?? '');
            $ico_cls = in_array($ext,['doc','docx']) ? 'word' : (in_array($ext,['jpg','jpeg','png','webp']) ? 'img' : '');
            $ico_fa  = in_array($ext,['doc','docx']) ? 'fa-file-word' : (in_array($ext,['jpg','jpeg','png','webp']) ? 'fa-image' : 'fa-file-pdf');
          ?>
          <div class="doc-exist">
            <div class="doc-exist-ico <?= $ico_cls ?>"><i class="fa-solid <?= $ico_fa ?>"></i></div>
            <span style="flex:1;font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($doc['nom_original']) ?></span>
            <span style="font-size:.7rem;color:var(--text-muted);"><?= $doc['taille_fichier'] ? round($doc['taille_fichier']/1024,1).' Ko' : '' ?></span>
            <a href="download.php?id=<?= $doc['id'] ?>" target="_blank" class="btn btn-secondary btn-sm btn-icon" title="Télécharger">
              <i class="fa-solid fa-download"></i>
            </a>
            <?php if (a_permission('FOURNISSEURS','MODIFIER')): ?>
            <form method="POST" action="actions.php" style="display:inline;"
                  onsubmit="return confirm('Supprimer ce document ?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action"  value="supprimer_doc">
              <input type="hidden" name="doc_id"  value="<?= $doc['id'] ?>">
              <input type="hidden" name="retour_id" value="<?= $fid ?>">
              <button type="submit"
                      style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;border-radius:6px;width:26px;height:26px;cursor:pointer;font-size:.72rem;padding:0;">
                <i class="fa-solid fa-trash"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px;">
        <?= $mode==='modifier' ? 'Ajouter de nouveaux documents' : 'Joindre les documents' ?>
      </div>

      <?php
      $champs_docs = [
        'doc_piece_identite' => ['label'=>'Pièce d\'identité (CNI/Passeport)', 'icon'=>'fa-id-card'],
        'doc_rccm'           => ['label'=>'RCCM (Registre du Commerce)',        'icon'=>'fa-building'],
        'doc_nif'            => ['label'=>'NIF (Numéro d\'Identification Fiscal)', 'icon'=>'fa-file-invoice'],
        'doc_quittance'      => ['label'=>'Quittance de paiement',              'icon'=>'fa-receipt'],
      ];
      foreach ($champs_docs as $champ => $doc_info):
        $lbl = $doc_info['label']; $ico = $doc_info['icon']; ?>
      <div style="margin-bottom:12px;">
        <label class="form-label" style="margin-bottom:6px;">
          <i class="fa-solid <?= $ico ?>" style="color:var(--blue-400);margin-right:5px;"></i> <?= $lbl ?>
        </label>
        <label class="up-zone">
          <input type="file" name="<?= $champ ?>" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                 onchange="previewFile(this, '<?= $champ ?>_prev')">
          <div class="up-label"><i class="fa-solid fa-cloud-arrow-up"></i> Choisir un fichier</div>
          <div class="up-hint">PDF, Word, JPEG, PNG — max 10 Mo</div>
          <div class="up-preview" id="<?= $champ ?>_prev"></div>
        </label>
      </div>
      <?php endforeach; ?>

      <!-- Documents supplémentaires -->
      <div style="margin-top:16px;">
        <label class="form-label" style="margin-bottom:6px;">
          <i class="fa-solid fa-paperclip" style="color:var(--blue-400);margin-right:5px;"></i>
          Autres documents (plusieurs fichiers acceptés)
        </label>
        <label class="up-zone">
          <input type="file" name="docs_autres[]" multiple
                 accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                 onchange="previewMultiple(this)">
          <div class="up-label"><i class="fa-solid fa-cloud-arrow-up"></i> Choisir des fichiers</div>
          <div class="up-hint">PDF, Word, JPEG, PNG — max 10 Mo par fichier</div>
          <div class="up-preview" id="autres_prev"></div>
        </label>
      </div>
    </div>
  </div>

  <!-- Actions sticky -->
  <div class="form-actions">
    <a href="<?= $four ? 'voir.php?id='.$fid : 'index.php' ?>" class="btn btn-secondary">
      <i class="fa-solid fa-xmark"></i> Annuler
    </a>
    <button type="submit" class="btn btn-primary">
      <i class="fa-solid <?= $mode==='creer'?'fa-plus':'fa-floppy-disk' ?>"></i>
      <?= $mode==='creer' ? 'Enregistrer le fournisseur' : 'Enregistrer les modifications' ?>
    </button>
  </div>
</form>

<?php
$extra_scripts = '<script>
function toggleManuel(selId, inpId) {
  const sel = document.getElementById(selId);
  const inp = document.getElementById(inpId);
  if (sel.value === "__autre__") {
    inp.style.display = "";
    inp.value = "";
    inp.focus();
  } else {
    inp.value = sel.value;
    inp.style.display = "none";
  }
}
function toggleMdp() {
  const inp  = document.getElementById("mdp");
  const icon = document.getElementById("eyeMdp");
  inp.type  = inp.type === "password" ? "text" : "password";
  icon.className = inp.type === "text" ? "fa-regular fa-eye-slash" : "fa-regular fa-eye";
}
function previewFile(input, targetId) {
  const el = document.getElementById(targetId);
  el.textContent = input.files[0] ? "✓ " + input.files[0].name : "";
}
function previewMultiple(input) {
  const el = document.getElementById("autres_prev");
  if (!input.files.length) { el.textContent = ""; return; }
  el.textContent = "✓ " + input.files.length + " fichier" + (input.files.length>1?"s":"") + " sélectionné" + (input.files.length>1?"s":"");
}
// Activer le click sur la zone upload
document.querySelectorAll(".up-zone").forEach(z => {
  z.addEventListener("click", () => z.querySelector("input[type=file]").click());
});
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>