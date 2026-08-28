<?php
// ============================================================
// profil.php — Profil de l'utilisateur connecté (CRICT)
//   À placer à la racine du projet, à côté de dashboard.php
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/models/utilisateur.php';
require_once __DIR__ . '/models/personnel.php';

session_demarrer();
exiger_connexion();

$uid = (int) user_id();
$me  = utilisateur_par_id($uid);
// Dossier RH de l'utilisateur (créé avec le compte ; filet de sécurité au cas où)
$mon_perso_id = personnel_assurer_profil($uid);

if (!$me) {
    auth_deconnecter();
    redirect('/login.php');
}

// ============================================================
//  TRAITEMENT DES FORMULAIRES (POST)  — pattern Post/Redirect/Get
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify()) {
        set_flash('error', 'Session expirée. Merci de réessayer.');
        redirect('/profil.php');
    }

    $action = $_POST['action'] ?? '';
    $db     = get_db();

    // ---- 1) Mise à jour des informations personnelles ----
    if ($action === 'infos') {
        $nom            = clean($_POST['nom']            ?? '');
        $prenoms        = clean($_POST['prenoms']        ?? '');
        $email          = strtolower(clean($_POST['email'] ?? ''));
        $telephone      = clean($_POST['telephone']      ?? '');
        $genre          = clean($_POST['genre']          ?? '');
        $date_naissance = clean($_POST['date_naissance'] ?? '');
        $adresse        = clean($_POST['adresse']        ?? '');

        $erreurs = [];
        if ($nom === '' || $prenoms === '') {
            $erreurs[] = 'Le nom et les prénoms sont obligatoires.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'Adresse e-mail invalide.';
        }
        if ($genre !== '' && !in_array($genre, ['M', 'F', 'AUTRE'], true)) {
            $genre = null;
        }

        // Unicité de l'e-mail (hors lui-même)
        if (!$erreurs) {
            $chk = $db->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
            $chk->execute([$email, $uid]);
            if ($chk->fetch()) {
                $erreurs[] = 'Cet e-mail est déjà utilisé par un autre compte.';
            }
        }

        if ($erreurs) {
            set_flash('error', implode(' ', $erreurs));
            redirect('/profil.php?tab=infos');
        }

        $avant = $me;
        $sql = "UPDATE utilisateurs
                   SET nom = ?, prenoms = ?, email = ?, telephone = ?,
                       genre = ?, date_naissance = ?, adresse = ?
                 WHERE id = ?";
        $db->prepare($sql)->execute([
            strtoupper($nom),
            ucwords(strtolower($prenoms)),
            $email,
            $telephone !== '' ? $telephone : null,
            $genre !== '' ? $genre : null,
            $date_naissance !== '' ? $date_naissance : null,
            $adresse !== '' ? $adresse : null,
            $uid,
        ]);

        // Rafraîchir la session pour que la sidebar / topbar reflètent les changements
        $_SESSION['user']['nom']     = strtoupper($nom);
        $_SESSION['user']['prenoms'] = ucwords(strtolower($prenoms));
        $_SESSION['user']['email']   = $email;

        log_activite($uid, 'PROFIL', 'MODIFICATION', 'Mise à jour des informations personnelles',
            'SUCCES', $avant, utilisateur_par_id($uid));
        set_flash('success', 'Vos informations ont été mises à jour.');
        redirect('/profil.php?tab=infos');
    }

    // ---- 2) Changement de photo de profil ----
    if ($action === 'photo') {
        $f = $_FILES['photo'] ?? null;

        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            set_flash('error', 'Aucun fichier sélectionné.');
            redirect('/profil.php?tab=infos');
        }
        if ($f['error'] !== UPLOAD_ERR_OK) {
            set_flash('error', 'Erreur lors du téléversement du fichier.');
            redirect('/profil.php?tab=infos');
        }
        if ($f['size'] > MAX_FILE_SIZE) {
            set_flash('error', 'La photo dépasse la taille maximale autorisée (5 Mo).');
            redirect('/profil.php?tab=infos');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($f['tmp_name']);
        if (!in_array($mime, ALLOWED_IMG_TYPES, true)) {
            set_flash('error', 'Format non autorisé. Utilisez JPEG, PNG ou WebP.');
            redirect('/profil.php?tab=infos');
        }

        $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $ext     = $ext_map[$mime];
        $dir     = UPLOAD_DIR . 'avatars/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $nom_fichier = 'avatar_' . $uid . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest        = $dir . $nom_fichier;

        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            set_flash('error', 'Impossible d\'enregistrer la photo sur le serveur.');
            redirect('/profil.php?tab=infos');
        }

        // Supprimer l'ancienne photo si elle est gérée par l'application
        if (!empty($me['photo']) && strpos($me['photo'], '/uploads/avatars/') !== false) {
            $ancien = UPLOAD_DIR . 'avatars/' . basename($me['photo']);
            if (is_file($ancien)) {
                @unlink($ancien);
            }
        }

        $url = APP_URL . '/uploads/avatars/' . $nom_fichier;
        $db->prepare("UPDATE utilisateurs SET photo = ? WHERE id = ?")->execute([$url, $uid]);
        $_SESSION['user']['photo'] = $url;

        log_activite($uid, 'PROFIL', 'PHOTO', 'Changement de la photo de profil');
        set_flash('success', 'Votre photo de profil a été mise à jour.');
        redirect('/profil.php?tab=infos');
    }

    // ---- 3) Suppression de la photo ----
    if ($action === 'photo_suppr') {
        if (!empty($me['photo']) && strpos($me['photo'], '/uploads/avatars/') !== false) {
            $ancien = UPLOAD_DIR . 'avatars/' . basename($me['photo']);
            if (is_file($ancien)) {
                @unlink($ancien);
            }
        }
        $db->prepare("UPDATE utilisateurs SET photo = NULL WHERE id = ?")->execute([$uid]);
        $_SESSION['user']['photo'] = null;
        log_activite($uid, 'PROFIL', 'PHOTO', 'Suppression de la photo de profil');
        set_flash('success', 'Photo de profil supprimée.');
        redirect('/profil.php?tab=infos');
    }

    // ---- 4) Changement de mot de passe ----
    if ($action === 'mdp') {
        $actuel  = $_POST['mdp_actuel']  ?? '';
        $nouveau = $_POST['mdp_nouveau'] ?? '';
        $confirm = $_POST['mdp_confirm'] ?? '';

        $erreurs = [];
        // Recharger le hash courant
        $row = $db->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id = ?");
        $row->execute([$uid]);
        $hash_actuel = (string) $row->fetchColumn();

        if (!verify_password($actuel, $hash_actuel)) {
            $erreurs[] = 'Le mot de passe actuel est incorrect.';
        }
        if (strlen($nouveau) < 8) {
            $erreurs[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        }
        if ($nouveau !== $confirm) {
            $erreurs[] = 'La confirmation ne correspond pas au nouveau mot de passe.';
        }

        if ($erreurs) {
            set_flash('error', implode(' ', $erreurs));
            redirect('/profil.php?tab=securite');
        }

        utilisateur_changer_mdp($uid, $nouveau);
        set_flash('success', 'Votre mot de passe a été modifié avec succès.');
        redirect('/profil.php?tab=securite');
    }

    redirect('/profil.php');
}

// ============================================================
//  PRÉPARATION DE L'AFFICHAGE
// ============================================================
$onglet_actif = $_GET['tab'] ?? 'infos';
if (!in_array($onglet_actif, ['infos', 'securite', 'activite'], true)) {
    $onglet_actif = 'infos';
}

$initiales = strtoupper(
    mb_substr($me['prenoms'] ?? 'U', 0, 1) .
    mb_substr($me['nom'] ?? 'S', 0, 1)
);

$genre_libelle = ['M' => 'Homme', 'F' => 'Femme', 'AUTRE' => 'Autre'][$me['genre'] ?? ''] ?? '—';

$dept_libelle = [
    'DIRECTION'              => 'Direction',
    'ADMIN_GENERALE'         => 'Administration générale',
    'ADMIN_FINANCIERE'       => 'Administration financière',
    'RECHERCHE_SCIENTIFIQUE' => 'Recherche scientifique',
    'SYSTEME'                => 'Système',
][$me['departement'] ?? ''] ?? '—';

// Activité récente de l'utilisateur
$logs = [];
try {
    $st = get_db()->prepare("
        SELECT module, action, description, statut, cree_le
        FROM logs_activites
        WHERE utilisateur_id = ?
        ORDER BY cree_le DESC
        LIMIT 12
    ");
    $st->execute([$uid]);
    $logs = $st->fetchAll();
} catch (Throwable $e) {
    $logs = [];
}

// Icône selon l'action de log
function profil_icone_log(string $action): string {
    if (str_contains($action, 'CONNEXION'))   return 'fa-right-to-bracket';
    if (str_contains($action, 'DECONNEXION'))  return 'fa-right-from-bracket';
    if (str_contains($action, 'MDP'))          return 'fa-key';
    if (str_contains($action, 'PHOTO'))        return 'fa-image';
    if (str_contains($action, 'CREATION'))     return 'fa-plus';
    if (str_contains($action, 'MODIFICATION')) return 'fa-pen';
    if (str_contains($action, 'SUPPRESSION'))  return 'fa-trash';
    if (str_contains($action, 'VALIDATION'))   return 'fa-circle-check';
    if (str_contains($action, 'SOUMISSION'))   return 'fa-paper-plane';
    return 'fa-clock-rotate-left';
}

$page_titre      = 'Mon profil';
$page_breadcrumb = 'Compte · Profil';
$module_actif    = '';   // pas de surbrillance dans la sidebar
include __DIR__ . '/views/layout/header.php';
?>
<style>
/* ── Bannière profil ── */
.profil-banner{
  background:linear-gradient(135deg,var(--blue-800),var(--blue-500));
  border-radius:var(--radius);
  padding:26px 28px;
  display:flex;align-items:center;gap:22px;
  color:#fff;box-shadow:0 8px 24px rgba(26,92,204,.28);
  margin-bottom:24px;flex-wrap:wrap;
}
.profil-avatar{
  width:90px;height:90px;border-radius:50%;
  background:rgba(255,255,255,.18);border:3px solid rgba(255,255,255,.5);
  display:flex;align-items:center;justify-content:center;
  font-size:2rem;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden;
}
.profil-avatar img{width:100%;height:100%;object-fit:cover;}
.profil-id{flex:1;min-width:200px;}
.profil-id h2{font-size:1.5rem;font-weight:800;line-height:1.15;margin-bottom:4px;}
.profil-id .role{font-size:.9rem;color:var(--blue-100);font-weight:500;}
.profil-tags{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;}
.profil-tag{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(255,255,255,.16);
  padding:4px 12px;border-radius:20px;
  font-size:.76rem;font-weight:600;
}

/* ── Onglets ── */
.tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:22px;flex-wrap:wrap;}
.tab-btn{
  padding:10px 18px;font-size:.86rem;font-weight:600;
  color:var(--text-muted);background:none;border:none;cursor:pointer;
  border-bottom:2px solid transparent;display:inline-flex;align-items:center;gap:8px;
  transition:all .15s;text-decoration:none;
}
.tab-btn:hover{color:var(--blue-600);}
.tab-btn.active{color:var(--blue-600);border-bottom-color:var(--blue-600);}
.tab-pane{display:none;}
.tab-pane.active{display:block;animation:fadeIn .2s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(4px);}}

/* ── Grille profil ── */
.profil-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;}
@media(max-width:880px){.profil-grid{grid-template-columns:1fr;}}

/* ── Liste d'infos ── */
.info-list{display:flex;flex-direction:column;}
.info-row{display:flex;gap:14px;padding:11px 0;border-bottom:1px solid var(--border);font-size:.86rem;}
.info-row:last-child{border-bottom:none;}
.info-row .k{width:150px;flex-shrink:0;color:var(--text-muted);font-weight:600;display:flex;align-items:center;gap:8px;}
.info-row .k i{width:16px;text-align:center;color:var(--blue-400);}
.info-row .v{color:var(--text);font-weight:500;word-break:break-word;}

/* ── Activité ── */
.act-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);}
.act-item:last-child{border-bottom:none;}
.act-ic{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;
  background:var(--blue-100);color:var(--blue-600);flex-shrink:0;font-size:.82rem;}
.act-ic.echec{background:#fee2e2;color:#dc2626;}
.act-body{flex:1;min-width:0;}
.act-body .t{font-size:.85rem;font-weight:600;color:var(--text);}
.act-body .d{font-size:.76rem;color:var(--text-muted);margin-top:2px;}
.act-time{font-size:.72rem;color:var(--text-muted);white-space:nowrap;font-family:var(--mono);}

.photo-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px;}
.hint{font-size:.76rem;color:var(--text-muted);margin-top:6px;}
</style>

<!-- BANNIÈRE -->
<div class="profil-banner">
  <div class="profil-avatar">
    <?php if (!empty($me['photo'])): ?>
      <img src="<?= e($me['photo']) ?>" alt="Photo de profil">
    <?php else: ?>
      <?= e($initiales) ?>
    <?php endif; ?>
  </div>
  <div class="profil-id">
    <h2><?= e($me['prenoms'] . ' ' . $me['nom']) ?></h2>
    <div class="role"><?= e($me['groupe_libelle'] ?? '') ?></div>
    <div class="profil-tags">
      <span class="profil-tag"><i class="fa-solid fa-id-badge"></i> <?= e($me['matricule']) ?></span>
      <span class="profil-tag"><i class="fa-solid fa-building"></i> <?= e($dept_libelle) ?></span>
      <span class="profil-tag">
        <i class="fa-solid fa-circle" style="font-size:.55rem;color:<?= $me['statut']==='ACTIF' ? '#4ade80' : '#fca5a5' ?>;"></i>
        <?= e($me['statut']) ?>
      </span>
    </div>
  </div>
</div>

<!-- ONGLETS -->
<div class="tabs">
  <a href="?tab=infos"    class="tab-btn <?= $onglet_actif==='infos'    ? 'active' : '' ?>" data-tab="infos"><i class="fa-solid fa-user"></i> Informations</a>
  <a href="?tab=securite" class="tab-btn <?= $onglet_actif==='securite' ? 'active' : '' ?>" data-tab="securite"><i class="fa-solid fa-shield-halved"></i> Sécurité</a>
  <a href="?tab=activite" class="tab-btn <?= $onglet_actif==='activite' ? 'active' : '' ?>" data-tab="activite"><i class="fa-solid fa-clock-rotate-left"></i> Activité récente</a>
  <a href="<?= APP_URL ?>/modules/demandes/demandes.php" class="tab-btn"><i class="fa-solid fa-inbox"></i> Mes demandes</a>
</div>

<!-- ========================= ONGLET INFORMATIONS ========================= -->
<div class="tab-pane <?= $onglet_actif==='infos' ? 'active' : '' ?>" id="pane-infos">
  <div class="profil-grid">

    <!-- Colonne gauche : récapitulatif + photo -->
    <div style="display:flex;flex-direction:column;gap:20px;">
      <div class="card">
        <div class="card-header"><h2><i class="fa-solid fa-address-card" style="color:var(--blue-500);"></i>&nbsp; Récapitulatif</h2></div>
        <div class="card-body">
          <div class="info-list">
            <div class="info-row"><span class="k"><i class="fa-solid fa-envelope"></i> E-mail</span><span class="v"><?= e($me['email']) ?></span></div>
            <div class="info-row"><span class="k"><i class="fa-solid fa-phone"></i> Téléphone</span><span class="v"><?= e($me['telephone'] ?: '—') ?></span></div>
            <div class="info-row"><span class="k"><i class="fa-solid fa-venus-mars"></i> Genre</span><span class="v"><?= e($genre_libelle) ?></span></div>
            <div class="info-row"><span class="k"><i class="fa-solid fa-graduation-cap"></i> Grade</span><span class="v"><?= e($me['grade'] ?: '—') ?></span></div>
            <div class="info-row"><span class="k"><i class="fa-solid fa-flask"></i> Spécialité</span><span class="v"><?= e($me['specialite'] ?: '—') ?></span></div>
            <div class="info-row"><span class="k"><i class="fa-solid fa-briefcase"></i> Prise de poste</span><span class="v"><?= !empty($me['date_prise_poste']) ? e(format_date($me['date_prise_poste'])) : '—' ?></span></div>
            <div class="info-row"><span class="k"><i class="fa-solid fa-right-to-bracket"></i> Dernière connexion</span><span class="v"><?= !empty($me['derniere_connexion']) ? e(format_datetime($me['derniere_connexion'])) : '—' ?></span></div>
            <div class="info-row"><span class="k"><i class="fa-solid fa-calendar-plus"></i> Membre depuis</span><span class="v"><?= !empty($me['cree_le']) ? e(format_datetime($me['cree_le'])) : '—' ?></span></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2><i class="fa-solid fa-id-card-clip" style="color:var(--blue-500);"></i>&nbsp; Mon dossier RH</h2></div>
        <div class="card-body">
          <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:14px;">
            Gérez votre CV, vos formations, expériences, certifications et compétences.
          </p>
          <a href="<?= APP_URL ?>/modules/personnel/voir.php?id=<?= (int)$mon_perso_id ?>" class="btn btn-primary">
            <i class="fa-solid fa-folder-open"></i> Ouvrir mon dossier RH
          </a>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2><i class="fa-solid fa-image" style="color:var(--blue-500);"></i>&nbsp; Photo de profil</h2></div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="photo">
            <div class="form-group" style="margin-bottom:8px;">
              <label class="form-label" for="photo">Choisir une image</label>
              <input type="file" id="photo" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp" required>
              <div class="hint">Formats acceptés : JPEG, PNG, WebP — 5 Mo maximum.</div>
            </div>
            <div class="photo-actions">
              <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Téléverser</button>
              <?php if (!empty($me['photo'])): ?>
                <button type="submit" class="btn btn-secondary"
                        formaction="profil.php" name="action" value="photo_suppr"
                        onclick="return confirm('Supprimer votre photo de profil ?');">
                  <i class="fa-solid fa-trash"></i> Supprimer
                </button>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Colonne droite : formulaire d'édition -->
    <div class="card">
      <div class="card-header"><h2><i class="fa-solid fa-pen-to-square" style="color:var(--blue-500);"></i>&nbsp; Modifier mes informations</h2></div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="infos">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="nom">Nom *</label>
              <input type="text" id="nom" name="nom" class="form-control" value="<?= e($me['nom']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="prenoms">Prénoms *</label>
              <input type="text" id="prenoms" name="prenoms" class="form-control" value="<?= e($me['prenoms']) ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="email">Adresse e-mail *</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= e($me['email']) ?>" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="telephone">Téléphone</label>
              <input type="text" id="telephone" name="telephone" class="form-control" value="<?= e($me['telephone'] ?? '') ?>" placeholder="+224…">
            </div>
            <div class="form-group">
              <label class="form-label" for="genre">Genre</label>
              <select id="genre" name="genre" class="form-control">
                <option value="">—</option>
                <option value="M"     <?= ($me['genre']??'')==='M'     ? 'selected' : '' ?>>Homme</option>
                <option value="F"     <?= ($me['genre']??'')==='F'     ? 'selected' : '' ?>>Femme</option>
                <option value="AUTRE" <?= ($me['genre']??'')==='AUTRE' ? 'selected' : '' ?>>Autre</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="date_naissance">Date de naissance</label>
            <input type="date" id="date_naissance" name="date_naissance" class="form-control" value="<?= e($me['date_naissance'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label class="form-label" for="adresse">Adresse</label>
            <textarea id="adresse" name="adresse" class="form-control" rows="3" placeholder="Adresse complète…"><?= e($me['adresse'] ?? '') ?></textarea>
          </div>

          <div class="hint" style="margin-bottom:14px;">
            <i class="fa-solid fa-circle-info"></i>
            Le groupe, le grade et la spécialité sont gérés par l'administration.
          </div>

          <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
            <a href="profil.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Annuler</a>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<!-- ========================= ONGLET SÉCURITÉ ========================= -->
<div class="tab-pane <?= $onglet_actif==='securite' ? 'active' : '' ?>" id="pane-securite">
  <div class="card" style="max-width:560px;">
    <div class="card-header"><h2><i class="fa-solid fa-key" style="color:var(--blue-500);"></i>&nbsp; Changer mon mot de passe</h2></div>
    <div class="card-body">
      <form method="post" id="formMdp">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mdp">

        <div class="form-group">
          <label class="form-label" for="mdp_actuel">Mot de passe actuel *</label>
          <input type="password" id="mdp_actuel" name="mdp_actuel" class="form-control" required autocomplete="current-password">
        </div>
        <div class="form-group">
          <label class="form-label" for="mdp_nouveau">Nouveau mot de passe *</label>
          <input type="password" id="mdp_nouveau" name="mdp_nouveau" class="form-control" required autocomplete="new-password" minlength="8">
          <div class="hint">Au moins 8 caractères.</div>
        </div>
        <div class="form-group">
          <label class="form-label" for="mdp_confirm">Confirmer le nouveau mot de passe *</label>
          <input type="password" id="mdp_confirm" name="mdp_confirm" class="form-control" required autocomplete="new-password" minlength="8">
        </div>

        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-shield-halved"></i> Mettre à jour le mot de passe</button>
      </form>
    </div>
  </div>
</div>

<!-- ========================= ONGLET ACTIVITÉ ========================= -->
<div class="tab-pane <?= $onglet_actif==='activite' ? 'active' : '' ?>" id="pane-activite">
  <div class="card">
    <div class="card-header"><h2><i class="fa-solid fa-clock-rotate-left" style="color:var(--blue-500);"></i>&nbsp; Mes 12 dernières actions</h2></div>
    <div class="card-body">
      <?php if (empty($logs)): ?>
        <p style="font-size:.85rem;color:var(--text-muted);text-align:center;padding:24px 0;">Aucune activité enregistrée.</p>
      <?php else: ?>
        <?php foreach ($logs as $l):
          $echec = ($l['statut'] ?? '') === 'ECHEC';
        ?>
        <div class="act-item">
          <div class="act-ic <?= $echec ? 'echec' : '' ?>"><i class="fa-solid <?= profil_icone_log($l['action']) ?>"></i></div>
          <div class="act-body">
            <div class="t"><?= e($l['module'] ?? '—') ?> · <?= e($l['action']) ?></div>
            <?php if (!empty($l['description'])): ?>
              <div class="d"><?= e($l['description']) ?></div>
            <?php endif; ?>
          </div>
          <div class="act-time"><?= e(format_datetime($l['cree_le'])) ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$extra_scripts = '<script>
// Onglets : maj de l\'URL sans recharger
document.querySelectorAll(".tab-btn[data-tab]").forEach(btn => {
  btn.addEventListener("click", function(e){
    e.preventDefault();
    const tab = this.dataset.tab;
    document.querySelectorAll(".tab-btn[data-tab]").forEach(b => b.classList.remove("active"));
    document.querySelectorAll(".tab-pane").forEach(p => p.classList.remove("active"));
    this.classList.add("active");
    document.getElementById("pane-"+tab).classList.add("active");
    history.replaceState(null, "", "?tab="+tab);
  });
});

// Vérification de la confirmation du mot de passe côté client
const fMdp = document.getElementById("formMdp");
if (fMdp) {
  fMdp.addEventListener("submit", function(e){
    const n = document.getElementById("mdp_nouveau").value;
    const c = document.getElementById("mdp_confirm").value;
    if (n !== c) {
      e.preventDefault();
      alert("La confirmation ne correspond pas au nouveau mot de passe.");
    }
  });
}
</script>';
include __DIR__ . '/views/layout/footer.php';
?>
