<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

session_demarrer();

// Déjà connecté
if (est_connecte()) { redirect('/dashboard.php'); }

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $erreur = 'Requête invalide. Veuillez réessayer.';
    } else {
        $email    = clean($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $erreur = 'Veuillez renseigner votre email et votre mot de passe.';
        } else {
            $result = auth_connecter($email, $password);
            if ($result['succes']) {
                if ($result['force_mdp']) {
                    redirect('/changer-mdp.php?first=1');
                }
                redirect('/dashboard.php');
            } else {
                $erreur = $result['message'];
            }
        }
    }
}

$expire = !empty($_GET['expire']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>ENT CRICT · Portail Recherche & Cyber-Technologie</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    font-family: 'Inter', sans-serif;
    background: radial-gradient(ellipse at 20% 30%, #0a1128, #03071e);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow-x: hidden;
  }

  /* décoration scientifique / tech */
  .science-bg {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
  }

  .science-bg .orbital-ring {
    position: absolute;
    border: 1px solid rgba(0, 198, 162, 0.12);
    border-radius: 50%;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
  }

  .science-bg .ring1 { width: 900px; height: 900px; border-width: 1px; animation: rotateSlow 45s linear infinite; }
  .science-bg .ring2 { width: 1400px; height: 1400px; border-width: 1px; border-color: rgba(77, 142, 245, 0.08); animation: rotateSlowRev 60s linear infinite; }
  .science-bg .ring3 { width: 1900px; height: 1900px; border-width: 1px; border-color: rgba(0, 198, 162, 0.05); animation: rotateSlow 80s linear infinite; }

  @keyframes rotateSlow {
    from { transform: translate(-50%, -50%) rotate(0deg); }
    to { transform: translate(-50%, -50%) rotate(360deg); }
  }
  @keyframes rotateSlowRev {
    from { transform: translate(-50%, -50%) rotate(0deg); }
    to { transform: translate(-50%, -50%) rotate(-360deg); }
  }

  .glow-dots {
    position: absolute;
    width: 100%;
    height: 100%;
    background-image: radial-gradient(circle at 2px 2px, rgba(0,198,162,0.2) 1px, transparent 1px);
    background-size: 40px 40px;
    opacity: 0.3;
  }

  /* carte principale */
  .portal-container {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 1200px;
    margin: 2rem;
    display: flex;
    flex-wrap: wrap;
    backdrop-filter: blur(2px);
  }

  /* section héros / branding scientifique */
  .hero-brand {
    flex: 1.2;
    background: rgba(3, 20, 74, 0.55);
    backdrop-filter: blur(12px);
    border-radius: 2rem 0 0 2rem;
    padding: 2.5rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    border-right: 1px solid rgba(255,255,255,0.08);
    box-shadow: 0 25px 45px rgba(0,0,0,0.3);
  }

  .logo-area {
    display: flex;
    align-items: center;
    gap: 1.2rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
  }

  .logo-circle {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #1e3a8a, #00c6a2);
    border-radius: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 15px 30px -8px rgba(0,198,162,0.4);
    transition: 0.2s;
  }

  .logo-circle i {
    font-size: 2.8rem;
    color: white;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,0.2));
  }

  /* emplacement upload logo - zone pour logo institutionnel */
  .custom-logo-zone {
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(255,255,255,0.05);
    padding: 8px 16px 8px 12px;
    border-radius: 60px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.15);
  }

  .logo-preview {
    width: 48px;
    height: 48px;
    background: white;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #f0f4fe;
  }

  .logo-preview img, .logo-preview span {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    font-size: 1.8rem;
  }

  .logo-upload-btn {
    background: rgba(0,198,162,0.2);
    border: 1px solid rgba(0,198,162,0.6);
    border-radius: 40px;
    padding: 6px 14px;
    font-size: 0.7rem;
    font-weight: 500;
    color: #cafdf2;
    cursor: pointer;
    transition: 0.2s;
  }

  .logo-upload-btn:hover {
    background: #00c6a2;
    color: #021a3b;
  }

  .hero-text h1 {
    font-size: 2.2rem;
    font-weight: 800;
    background: linear-gradient(120deg, #fff, #9bc5ff);
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
    letter-spacing: -0.02em;
    margin-bottom: 0.75rem;
  }

  .hero-text .sous-titre {
    color: #b9d0ff;
    font-weight: 500;
    font-size: 0.9rem;
    border-left: 3px solid #00c6a2;
    padding-left: 12px;
    margin-top: 8px;
  }

  .scientific-badge {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
  }

  .badge-item {
    background: rgba(255,255,255,0.03);
    border-radius: 40px;
    padding: 6px 14px;
    font-size: 0.7rem;
    font-weight: 500;
    color: #b4ccff;
    backdrop-filter: blur(2px);
    border: 0.5px solid rgba(255,255,255,0.1);
  }

  .badge-item i {
    margin-right: 6px;
    color: #00c6a2;
  }

  /* formulaire connexion */
  .login-card-modern {
    flex: 0.9;
    background: rgba(255, 255, 255, 0.98);
    border-radius: 0 2rem 2rem 0;
    padding: 2.5rem;
    box-shadow: 0 30px 55px rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(0px);
    transition: all 0.2s;
  }

  @media (max-width: 860px) {
    .portal-container { flex-direction: column; margin: 1rem; }
    .hero-brand { border-radius: 2rem 2rem 0 0; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: center; align-items: center; }
    .login-card-modern { border-radius: 0 0 2rem 2rem; }
    .logo-area { justify-content: center; }
    .hero-text h1 { font-size: 1.8rem; text-align: center; }
    .scientific-badge { justify-content: center; }
  }

  .form-header {
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #eef2ff;
    padding-bottom: 0.75rem;
  }

  .form-header h2 {
    font-size: 1.6rem;
    font-weight: 700;
    color: #0a2540;
    letter-spacing: -0.3px;
  }

  .form-header p {
    font-size: 0.8rem;
    color: #5c6f87;
    margin-top: 5px;
  }

  .alert-box, .alert-info-box {
    padding: 12px 16px;
    border-radius: 14px;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    font-weight: 500;
  }

  .alert-box { background: #fff0f0; border-left: 5px solid #dc2626; color: #a1220a; }
  .alert-info-box { background: #ecfdf9; border-left: 5px solid #00c6a2; color: #0d5b4a; }

  .form-group { margin-bottom: 1.5rem; }
  .form-label {
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #1f3a5f;
    margin-bottom: 8px;
    display: block;
  }

  .input-wrap {
    position: relative;
    display: flex;
    align-items: center;
  }
  .input-wrap i:first-child {
    position: absolute;
    left: 14px;
    color: #8b9ab0;
    font-size: 1rem;
    pointer-events: none;
  }

  .form-input {
    width: 100%;
    padding: 14px 14px 14px 42px;
    border: 1.5px solid #e2e8f0;
    border-radius: 16px;
    font-size: 0.9rem;
    font-weight: 500;
    background: #ffffff;
    transition: 0.2s;
    font-family: 'Inter', sans-serif;
  }

  .form-input:focus {
    border-color: #1a5ccc;
    box-shadow: 0 0 0 3px rgba(26, 92, 204, 0.2);
    outline: none;
  }

  .toggle-pass {
    position: absolute;
    right: 12px;
    background: transparent;
    border: none;
    color: #8196b0;
    cursor: pointer;
    font-size: 1rem;
    padding: 6px;
  }

  .btn-login {
    width: 100%;
    background: linear-gradient(105deg, #0f2b5e, #133c7a);
    border: none;
    padding: 14px;
    border-radius: 40px;
    font-weight: 700;
    font-size: 0.9rem;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    transition: 0.2s;
    box-shadow: 0 5px 12px rgba(0, 0, 0, 0.1);
    margin-top: 8px;
  }

  .btn-login:hover {
    background: linear-gradient(105deg, #1e3e72, #1a5ccc);
    transform: translateY(-2px);
    box-shadow: 0 12px 20px -10px #0f2b5e;
  }

  .login-footer {
    margin-top: 2rem;
    text-align: center;
    font-size: 0.7rem;
    color: #6b7c93;
    border-top: 1px solid #ecf3fa;
    padding-top: 1rem;
  }

  .login-footer strong { color: #0f2b5e; }
  .version-badge { font-family: monospace; background: #f1f5f9; display: inline-block; padding: 2px 8px; border-radius: 30px; font-size: 0.65rem; }

  /* animation subtile */
  @keyframes fadeSlide {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .login-card-modern, .hero-brand { animation: fadeSlide 0.5s ease-out; }
</style>
</head>
<body>

<div class="science-bg">
  <div class="orbital-ring ring1"></div>
  <div class="orbital-ring ring2"></div>
  <div class="orbital-ring ring3"></div>
  <div class="glow-dots"></div>
</div>

<div class="portal-container">
  <!-- PARTIE GAUCHE : BRANDING & LOGO -->
  <div class="hero-brand">
    <div class="logo-area">
      <!-- espace logo dynamique avec preview et upload (fonctionnalité front) -->
      <div class="logo-circle">
        <img src="logo.png">
      </div>
      
    </div>
    <div class="hero-text">
      <h1>ENT CRICT<br><span style="font-size: 1rem;">Espace Numérique de Travail · Recherche & Cyber-Technologie</span></h1>
      <div class="sous-titre">Digitalisation complète · Marchés publics · GED · Workflow budgétaire</div>
    </div>
    <div class="scientific-badge">
      <span class="badge-item"><i class="fas fa-handshake"></i> Fournisseurs</span>
      <span class="badge-item"><i class="fas fa-gavel"></i> Marchés publics</span>
      <span class="badge-item"><i class="fas fa-chart-line"></i> Budget T1→T4</span>
      <span class="badge-item"><i class="fas fa-file-alt"></i> GED & Collaboration</span>
      <span class="badge-item"><i class="fas fa-star"></i> Notation</span>
      <span class="badge-item"><i class="fas fa-truck"></i> Suivi livraisons</span>
    </div>
    <div style="margin-top: 2rem; font-size:0.7rem; color:#7f9bdd;">
      <i class="fas fa-fingerprint"></i> Authentification sécurisée · Chiffrement de bout en bout
    </div>
  </div>

  <!-- PARTIE FORMULAIRE CONNEXION -->
  <div class="login-card-modern">
    <div class="form-header">
      <h2>Accès portail chercheur & opérateur</h2>
      <p>Identifiez-vous pour accéder aux modules : fournisseurs, marchés, budget, GED, notation, paiements</p>
    </div>

    <!-- affichage des erreurs / session expiree -->
    <?php if ($expire): ?>
      <div class="alert-info-box">
        <i class="fa-regular fa-clock"></i> Votre session de travail a expiré. Veuillez vous reconnecter.
      </div>
    <?php endif; ?>

    <?php if ($erreur): ?>
      <div class="alert-box">
        <i class="fa-solid fa-circle-exclamation"></i> <?= e($erreur) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label"><i class="fas fa-at"></i> Adresse électronique institutionnelle</label>
        <div class="input-wrap">
          <i class="fa-regular fa-envelope"></i>
          <input type="email" name="email" class="form-input" 
                 placeholder="prenom.nom@crict.edu.gn" 
                 value="<?= e($_POST['email'] ?? '') ?>" 
                 autocomplete="email" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label"><i class="fas fa-key"></i> Mot de passe sécurisé</label>
        <div class="input-wrap">
          <i class="fa-solid fa-lock"></i>
          <input type="password" name="password" id="modernPassword" class="form-input" 
                 placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" class="toggle-pass" onclick="toggleModernPassword()">
            <i class="fa-regular fa-eye" id="modernEyeIcon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login">
        <i class="fas fa-arrow-right-to-bracket"></i> Se connecter à l'ENT
      </button>
    </form>

    <div class="login-footer">
      <p>© <?= date('Y') ?> <strong>CRICT</strong> – Centre de Recherche en Informatique et Cyber-Technologie · Guinée</p>
      <p class="version-badge">Espace Numérique de Travrail CRICT</p>
    </div>
  </div>
</div>

<script>
  // Fonction pour afficher/masquer le mot de passe
  function toggleModernPassword() {
    const pwdInput = document.getElementById('modernPassword');
    const eyeIcon = document.getElementById('modernEyeIcon');
    if (pwdInput.type === 'password') {
      pwdInput.type = 'text';
      eyeIcon.className = 'fa-regular fa-eye-slash';
    } else {
      pwdInput.type = 'password';
      eyeIcon.className = 'fa-regular fa-eye';
    }
  }

  // ========== GESTION DU LOGO PERSONNALISABLE (FRONT-END) ==========
  // Cette partie permet à l'utilisateur d'uploader un logo (local storage / temporaire)
  // Améliore l'aspect institutionnel : on peut définir le logo du centre.
  
  const logoUpload = document.getElementById('logoUploadInput');
  const logoPreviewDiv = document.getElementById('logoPreview');
  const resetBtn = document.getElementById('resetLogoBtn');

  // clé pour stocker le logo en local storage (persistance démo)
  const STORAGE_KEY = 'crict_institution_logo';

  function displayLogoFromData(dataUrl) {
    if (!dataUrl) return;
    logoPreviewDiv.innerHTML = `<img src="${dataUrl}" alt="Logo institutionnel" style="width:100%; height:100%; object-fit:cover; border-radius:12px;">`;
    // on sauvegarde en localStorage
    localStorage.setItem(STORAGE_KEY, dataUrl);
  }

  function resetToDefaultLogo() {
    logoPreviewDiv.innerHTML = `<span>⚛️</span>`;
    localStorage.removeItem(STORAGE_KEY);
  }

  // chargement du logo si existant
  function loadSavedLogo() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved && saved.startsWith('data:image')) {
      logoPreviewDiv.innerHTML = `<img src="${saved}" alt="Logo institutionnel" style="width:100%; height:100%; object-fit:cover; border-radius:12px;">`;
    } else {
      // logo par défaut avec un symbole atome/science
      logoPreviewDiv.innerHTML = `<span>⚛️</span>`;
    }
  }

  if (logoUpload) {
    logoUpload.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (!file) return;
      // vérifier type d'image
      if (!file.type.match('image.*')) {
        alert('Veuillez sélectionner un fichier image (PNG, JPEG, SVG, WEBP)');
        return;
      }
      const reader = new FileReader();
      reader.onload = function(ev) {
        const dataUrl = ev.target.result;
        displayLogoFromData(dataUrl);
      };
      reader.readAsDataURL(file);
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', function() {
      resetToDefaultLogo();
      if (logoUpload) logoUpload.value = ''; // reset input file
    });
  }

  loadSavedLogo();

  // Option supplémentaire: amélioration interactive pour le formulaire, alerte si champs vides (front-end)
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', function(e) {
      const email = form.querySelector('input[name="email"]')?.value.trim();
      const password = form.querySelector('input[name="password"]')?.value;
      if (!email || !password) {
        // On n'empêche pas l'envoi, mais on peut prévenir (ne casse pas la logique serveur)
        // Dans un contexte réel php, l'erreur sera gérée, mais pour l'UX on laisse faire.
        if (!document.querySelector('.custom-front-alert')) {
          const errDiv = document.createElement('div');
          errDiv.className = 'alert-box';
          errDiv.style.marginTop = '12px';
          errDiv.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Veuillez renseigner tous les champs requis.';
          form.prepend(errDiv);
          setTimeout(() => errDiv.remove(), 3000);
          if (!email || !password) e.preventDefault();
        }
      }
    });
  }

  // Ajout d'un gestionnaire élégant pour l'aperçu du logo drag & drop optionnel simplifié
  const customZone = document.getElementById('customLogoZone');
  if (customZone) {
    customZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      customZone.style.opacity = '0.8';
    });
    customZone.addEventListener('dragleave', () => {
      customZone.style.opacity = '1';
    });
    customZone.addEventListener('drop', (e) => {
      e.preventDefault();
      customZone.style.opacity = '1';
      const file = e.dataTransfer.files[0];
      if (file && file.type.match('image.*')) {
        const reader = new FileReader();
        reader.onload = (ev) => displayLogoFromData(ev.target.result);
        reader.readAsDataURL(file);
      } else if (file) {
        alert("Format non supporté pour le logo. Utilisez une image.");
      }
    });
  }
</script>

</body>
</html>