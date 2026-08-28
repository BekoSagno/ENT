<?php
// modules/fournisseurs/inscription.php — Auto-inscription publique des fournisseurs
// Page accessible SANS authentification. Le compte est créé au statut « en_attente »
// puis validé par un administrateur depuis le module Fournisseurs.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/fournisseur.php';

session_demarrer();

$erreurs = [];
$succes  = false;
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Anti-spam : honeypot (champ invisible qui doit rester vide)
    if (!empty($_POST['site_officiel'])) {
        // Bot probable → on fait comme si tout s'était bien passé, sans rien créer.
        $succes = true;
    } elseif (!csrf_verify()) {
        $erreurs[] = 'Session expirée, veuillez réessayer.';
    } else {
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
            'nif'              => clean($_POST['nif']              ?? '')
        ];
        $old = $data;

        // Validation
        if ($data['nom_entreprise'] === '')   $erreurs[] = 'Le nom de l\'entreprise est obligatoire.';
        if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
                                              $erreurs[] = 'Une adresse e-mail valide est obligatoire.';
        if ($data['type_fournisseur'] === '') $erreurs[] = 'Le type de fournisseur est obligatoire.';
        if ($data['categorie'] === '')        $erreurs[] = 'La catégorie est obligatoire.';
        if ($data['nom_gerant'] === '')        $erreurs[] = 'Le nom du gérant est obligatoire.';
        if ($data['telephone'] === '')         $erreurs[] = 'Le téléphone de l\'entreprise est obligatoire.';
        if ($data['telephone_gerant'] === '')  $erreurs[] = 'Le téléphone du gérant est obligatoire.';
        if ($data['nom_utilisateur'] === '')   $erreurs[] = 'Le nom d\'utilisateur est obligatoire.';
        if (strlen($data['mot_de_passe']) < 8) $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        if (($_POST['mot_de_passe'] ?? '') !== ($_POST['mot_de_passe_confirm'] ?? ''))
                                              $erreurs[] = 'Les deux mots de passe ne correspondent pas.';

        if (empty($erreurs)) {
            $res = fournisseur_creer($data);
            if ($res['succes']) {
                $fid = (int)$res['id'];

                // Documents facultatifs (plusieurs fichiers autorisés)
                if (!empty($_FILES['docs']['name'][0])) {
                    foreach ($_FILES['docs']['name'] as $k => $nom) {
                        if (($_FILES['docs']['error'][$k] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                        $fk = [
                            'name'     => $nom,
                            'tmp_name' => $_FILES['docs']['tmp_name'][$k],
                            'error'    => $_FILES['docs']['error'][$k],
                            'size'     => $_FILES['docs']['size'][$k],
                        ];
                        fournisseur_doc_ajouter($fid, $fk, 'autre');
                    }
                }
                $succes = true;
                $old    = [];
            } else {
                $erreurs[] = $res['message'] ?? 'Une erreur est survenue.';
            }
        }
    }
}

$v = fn(string $k) => e($old[$k] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Inscription fournisseur — CRICT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --blue-950:#03144a;--blue-900:#0a2158;--blue-800:#0e3278;--blue-700:#1347a8;
  --blue-600:#1a5ccc;--blue-500:#2570e8;--blue-400:#4d8ef5;--blue-300:#85b3fa;--blue-100:#ddeaff;
  --accent:#00c6a2;--danger:#dc2626;--text:#1a2540;--muted:#6b7280;--border:#e5e7eb;
  --font:'Plus Jakarta Sans',sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--font);background:linear-gradient(135deg,var(--blue-950),var(--blue-900) 55%,var(--blue-800));min-height:100vh;padding:28px 16px;color:var(--text);}
.bg-grid{position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);background-size:50px 50px;}
.wrap{position:relative;z-index:10;max-width:760px;margin:0 auto;}
.card{background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 30px 80px rgba(3,20,74,.45);}
.top{background:linear-gradient(135deg,var(--blue-900),var(--blue-700));padding:30px 36px;text-align:center;}
.logo{width:54px;height:54px;background:linear-gradient(135deg,var(--blue-500),var(--accent));border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:1.3rem;color:#fff;font-weight:900;box-shadow:0 8px 24px rgba(0,198,162,.3);}
.top h1{color:#fff;font-size:1.25rem;font-weight:800;}
.top p{color:var(--blue-300);font-size:.82rem;margin-top:5px;}
.body{padding:30px 36px;}
@media(max-width:560px){.body,.top{padding-left:20px;padding-right:20px;}}

.alert{padding:12px 15px;border-radius:10px;font-size:.85rem;margin-bottom:20px;display:flex;gap:9px;align-items:flex-start;}
.alert ul{margin:0;padding-left:18px;}
.alert-err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}

.sec-title{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--blue-700);margin:22px 0 12px;display:flex;align-items:center;gap:8px;}
.sec-title:first-of-type{margin-top:0;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:15px;}
@media(max-width:560px){.grid2{grid-template-columns:1fr;}}
.fg{margin-bottom:4px;}
.lbl{display:block;font-size:.78rem;font-weight:700;color:#374151;margin-bottom:6px;}
.opt{font-size:.7rem;color:var(--muted);font-weight:600;}
.req{color:var(--danger);}
.inp{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:10px;font-size:.88rem;font-family:var(--font);color:var(--text);background:#f9fafb;transition:all .15s;outline:none;}
.inp:focus{border-color:var(--blue-500);background:#fff;box-shadow:0 0 0 3px rgba(37,112,232,.12);}
.hint{font-size:.7rem;color:var(--muted);margin-top:4px;}
.honey{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;}

.upzone{border:2px dashed var(--blue-300);border-radius:10px;padding:16px;background:var(--blue-100);text-align:center;cursor:pointer;}
.upzone input{display:none;}
.upzone .ic{font-size:1.3rem;color:var(--blue-500);}
.up-prev{font-size:.78rem;color:var(--blue-700);font-weight:600;margin-top:6px;}

.btn{width:100%;margin-top:26px;padding:13px;background:linear-gradient(135deg,var(--blue-600),var(--blue-700));color:#fff;border:none;border-radius:11px;font-size:.95rem;font-weight:700;font-family:var(--font);cursor:pointer;transition:all .2s;box-shadow:0 4px 14px rgba(26,92,204,.35);display:flex;align-items:center;justify-content:center;gap:8px;}
.btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(26,92,204,.45);}
.foot{text-align:center;margin-top:22px;font-size:.74rem;color:var(--muted);}

.ok-box{text-align:center;padding:18px 6px;}
.ok-ic{width:72px;height:72px;border-radius:50%;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 18px;}
.ok-box h2{font-size:1.25rem;color:var(--text);margin-bottom:10px;}
.ok-box p{font-size:.9rem;color:var(--muted);line-height:1.55;max-width:460px;margin:0 auto;}
</style>
</head>
<body>
<div class="bg-grid"></div>
<div class="wrap">
  <div class="card">
    <div class="top">
      <div class="logo">CR</div>
      <h1>Inscription fournisseur</h1>
      <p>Créez votre compte partenaire du CRICT</p>
    </div>
    <div class="body">

    <?php if ($succes): ?>
      <div class="ok-box">
        <div class="ok-ic"><i class="fa-solid fa-check"></i></div>
        <h2>Demande enregistrée</h2>
        <p>
          Merci ! Votre demande d'inscription a bien été reçue. Votre compte sera
          <strong>activé après validation</strong> par notre équipe. Vous pourrez ensuite
          vous connecter avec vos identifiants.
        </p>
      </div>
    <?php else: ?>

      <?php if (!empty($erreurs)): ?>
      <div class="alert alert-err">
        <i class="fa-solid fa-circle-exclamation" style="margin-top:2px;"></i>
        <ul><?php foreach ($erreurs as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul>
      </div>
      <?php endif; ?>

      <form method="POST" action="" enctype="multipart/form-data" id="formInscr">
        <?= csrf_field() ?>
        <div class="honey"><label>Ne pas remplir<input type="text" name="site_officiel" tabindex="-1" autocomplete="off"></label></div>

        <div class="sec-title"><i class="fa-solid fa-building"></i> Entreprise</div>
        <div class="fg">
          <label class="lbl">Nom de l'entreprise <span class="req">*</span></label>
          <input type="text" name="nom_entreprise" class="inp" required value="<?= $v('nom_entreprise') ?>" placeholder="Ex : SARL TECH GUINEE">
        </div>
        <div class="grid2" style="margin-top:15px;">
          <div class="fg">
            <label class="lbl">Type de fournisseur <span class="req">*</span></label>
            <?php
              $type_val   = $old['type_fournisseur'] ?? '';
              $type_opts  = ['Biens', 'Services', 'Biens et services'];
              $type_known = in_array($type_val, $type_opts, true);
              $type_autre = ($type_val !== '' && !$type_known);
            ?>
            <select class="inp" id="typeSel" onchange="toggleManuel('typeSel','typeInput')">
              <option value="">— Choisir —</option>
              <?php foreach ($type_opts as $o): ?>
                <option value="<?= e($o) ?>" <?= $type_val===$o?'selected':'' ?>><?= e($o) ?></option>
              <?php endforeach; ?>
              <option value="__autre__" <?= $type_autre?'selected':'' ?>>Autre (saisir manuellement)…</option>
            </select>
            <input type="text" name="type_fournisseur" id="typeInput" class="inp"
                   style="margin-top:8px;<?= ($type_autre ? '' : 'display:none;') ?>"
                   value="<?= $v('type_fournisseur') ?>" placeholder="Saisissez le type de fournisseur">
          </div>
          <div class="fg">
            <label class="lbl">Catégorie <span class="req">*</span></label>
            <?php
              $cat_val   = $old['categorie'] ?? '';
              $cat_opts  = ['Informatique & Télécoms','Fournitures de bureau','BTP & Travaux',
                            'Transport & Logistique','Alimentation & Restauration','Santé & Médical',
                            'Consulting & Formation','Énergie & Électricité'];
              $cat_known = in_array($cat_val, $cat_opts, true);
              $cat_autre = ($cat_val !== '' && !$cat_known);
            ?>
            <select class="inp" id="catSel" onchange="toggleManuel('catSel','catInput')">
              <option value="">— Choisir —</option>
              <?php foreach ($cat_opts as $o): ?>
                <option value="<?= e($o) ?>" <?= $cat_val===$o?'selected':'' ?>><?= e($o) ?></option>
              <?php endforeach; ?>
              <option value="__autre__" <?= $cat_autre?'selected':'' ?>>Autre (saisir manuellement)…</option>
            </select>
            <input type="text" name="categorie" id="catInput" class="inp"
                   style="margin-top:8px;<?= ($cat_autre ? '' : 'display:none;') ?>"
                   value="<?= $v('categorie') ?>" placeholder="Saisissez la catégorie">
          </div>
        </div>
        <div class="grid2" style="margin-top:15px;">
          <div class="fg">
            <label class="lbl">Téléphone entreprise <span class="req">*</span></label>
            <input type="tel" name="telephone" class="inp" required value="<?= $v('telephone') ?>" placeholder="+224 6XX XXX XXX">
          </div>
          <div class="fg">
            <label class="lbl">E-mail <span class="req">*</span></label>
            <input type="email" name="email" class="inp" required value="<?= $v('email') ?>" placeholder="contact@entreprise.com">
          </div>
        </div>
        <div class="fg" style="margin-top:15px;">
          <label class="lbl">Site web <span class="opt">(facultatif)</span></label>
          <input type="url" name="site_web" class="inp" value="<?= $v('site_web') ?>" placeholder="https://…">
        </div>

        <div class="sec-title"><i class="fa-solid fa-user-tie"></i> Gérant / Responsable</div>
        <div class="grid2">
          <div class="fg">
            <label class="lbl">Nom complet du gérant <span class="req">*</span></label>
            <input type="text" name="nom_gerant" class="inp" required value="<?= $v('nom_gerant') ?>" placeholder="Prénom NOM">
          </div>
          <div class="fg">
            <label class="lbl">Téléphone du gérant <span class="req">*</span></label>
            <input type="tel" name="telephone_gerant" class="inp" required value="<?= $v('telephone_gerant') ?>" placeholder="+224 6XX XXX XXX">
          </div>
        </div>

        <div class="sec-title"><i class="fa-solid fa-id-card"></i> Identifiants légaux <span class="opt" style="text-transform:none;letter-spacing:0;">— facultatifs</span></div>
        <div class="grid2">
          <div class="fg">
            <label class="lbl">N° RCCM <span class="opt">(facultatif)</span></label>
            <input type="text" name="numero_rccm" class="inp" value="<?= $v('numero_rccm') ?>" placeholder="RCCM/GN/…">
          </div>
          <div class="fg">
            <label class="lbl">NIF <span class="opt">(facultatif)</span></label>
            <input type="text" name="nif" class="inp" value="<?= $v('nif') ?>" placeholder="NIF XXXXXXXXX">
          </div>
        </div>
        <div class="fg" style="margin-top:15px;">
          <label class="lbl">Documents <span class="opt">(facultatif — PDF, Word, image, max 10 Mo/fichier)</span></label>
          <label class="upzone" id="upzone">
            <input type="file" name="docs[]" id="docs" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
            <div class="ic"><i class="fa-solid fa-cloud-arrow-up"></i></div>
            <div style="font-size:.82rem;font-weight:600;color:var(--blue-700);">Ajouter des documents (RCCM, NIF, etc.)</div>
            <div class="up-prev" id="upPrev"></div>
          </label>
        </div>

       

        <button type="submit" class="btn"><i class="fa-solid fa-paper-plane"></i> Envoyer ma demande d'inscription</button>
      </form>

    <?php endif; ?>

      <div class="foot">
        <p><?= date('Y') ?> © <strong>CRICT</strong> · <a href="<?= APP_URL ?>/login.php" style="color:var(--blue-600);text-decoration:none;">Déjà inscrit ? Se connecter</a></p>
      </div>
    </div>
  </div>
</div>

<script>
function toggleManuel(selId, inpId) {
  const sel = document.getElementById(selId);
  const inp = document.getElementById(inpId);
  if (!sel || !inp) return;
  if (sel.value === '__autre__') {
    inp.style.display = '';
    inp.value = '';
    inp.focus();
  } else {
    inp.value = sel.value;
    inp.style.display = 'none';
  }
}
(function(){
  const z = document.getElementById('upzone');
  const inp = document.getElementById('docs');
  const prev = document.getElementById('upPrev');
  if (inp) {
    inp.addEventListener('change', function(){
      prev.textContent = inp.files.length
        ? '✓ ' + inp.files.length + ' fichier' + (inp.files.length>1?'s':'') + ' sélectionné' + (inp.files.length>1?'s':'')
        : '';
    });
  }
})();
</script>
</body>
</html>
