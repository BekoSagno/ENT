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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — CRICT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --blue-950: #03144a; --blue-900: #0a2158; --blue-800: #0e3278;
  --blue-700: #1347a8; --blue-600: #1a5ccc; --blue-500: #2570e8;
  --blue-400: #4d8ef5; --blue-300: #85b3fa; --blue-100: #ddeaff;
  --accent: #00c6a2;
  --font: 'Plus Jakarta Sans', sans-serif;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: var(--font);
  background: linear-gradient(135deg, var(--blue-950) 0%, var(--blue-900) 50%, var(--blue-800) 100%);
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
  position: relative; overflow: hidden;
}

/* Cercles décoratifs */
body::before, body::after {
  content: ''; position: absolute; border-radius: 50%;
  pointer-events: none;
}
body::before {
  width: 600px; height: 600px;
  background: radial-gradient(circle, rgba(37,112,232,.15) 0%, transparent 70%);
  top: -200px; right: -100px;
}
body::after {
  width: 400px; height: 400px;
  background: radial-gradient(circle, rgba(0,198,162,.1) 0%, transparent 70%);
  bottom: -100px; left: -80px;
}

/* Grid lines décoratifs */
.bg-grid {
  position: absolute; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
  background-size: 50px 50px;
}

.login-card {
  position: relative; z-index: 10;
  background: rgba(255,255,255,.97);
  border-radius: 20px;
  width: 100%; max-width: 440px;
  box-shadow: 0 30px 80px rgba(3,20,74,.5);
  overflow: hidden;
}

.login-top {
  background: linear-gradient(135deg, var(--blue-900), var(--blue-700));
  padding: 32px 36px 28px;
  text-align: center; position: relative;
}
.login-top::after {
  content: '';
  position: absolute; bottom: -12px; left: 0; right: 0; height: 12px;
  background: rgba(255,255,255,.97);
  border-radius: 20px 20px 0 0;
}
.login-logo {
  width: 58px; height: 58px;
  background: linear-gradient(135deg, var(--blue-500), var(--accent));
  border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 14px;
  font-size: 1.4rem; color: #fff; font-weight: 900;
  box-shadow: 0 8px 24px rgba(0,198,162,.3);
}
.login-top h1 {
  color: #fff; font-size: 1.3rem; font-weight: 800; letter-spacing: .02em;
}
.login-top p {
  color: var(--blue-300); font-size: .8rem; margin-top: 4px;
}

.login-body { padding: 32px 36px; }

.alert-box {
  background: #fee2e2; color: #991b1b;
  border: 1px solid #fecaca;
  padding: 11px 14px; border-radius: 8px;
  font-size: .84rem; margin-bottom: 20px;
  display: flex; align-items: center; gap: 8px;
}
.alert-info-box {
  background: var(--blue-100); color: var(--blue-800);
  border: 1px solid #bdd3fd;
  padding: 11px 14px; border-radius: 8px;
  font-size: .84rem; margin-bottom: 20px;
  display: flex; align-items: center; gap: 8px;
}

.form-group { margin-bottom: 18px; }
.form-label {
  display: block; font-size: .8rem; font-weight: 700;
  color: #374151; margin-bottom: 7px; letter-spacing: .01em;
}
.input-wrap { position: relative; }
.input-wrap i {
  position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
  color: #9ca3af; font-size: .85rem;
}
.form-input {
  width: 100%; padding: 11px 14px 11px 38px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  font-size: .9rem; font-family: var(--font); color: #1a2540;
  background: #f9fafb;
  transition: all .15s; outline: none;
}
.form-input:focus {
  border-color: var(--blue-500);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(37,112,232,.12);
}
.toggle-pass {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: #9ca3af; font-size: .85rem; padding: 4px;
}
.toggle-pass:hover { color: var(--blue-600); }

.btn-login {
  width: 100%; padding: 12px;
  background: linear-gradient(135deg, var(--blue-600), var(--blue-700));
  color: #fff; border: none; border-radius: 10px;
  font-size: .95rem; font-weight: 700; font-family: var(--font);
  cursor: pointer; transition: all .2s;
  box-shadow: 0 4px 14px rgba(26,92,204,.35);
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-login:hover {
  background: linear-gradient(135deg, var(--blue-700), var(--blue-800));
  box-shadow: 0 6px 20px rgba(26,92,204,.45);
  transform: translateY(-1px);
}
.btn-login:active { transform: translateY(0); }

.login-footer {
  text-align: center; margin-top: 24px;
  font-size: .75rem; color: #9ca3af;
}
.login-footer strong { color: var(--blue-700); }
</style>
</head>
<body>
<div class="bg-grid"></div>

<div class="login-card">
  <div class="login-top">
    <!--<div class="login-logo">CI</div> -->
    <h1>CRICT</h1>
    <p>Centre de recherche en Informatique & Cyber-Technologie</p>
  </div>

  <div class="login-body">
    <?php if ($expire): ?>
      <div class="alert-info-box">
        <i class="fa-solid fa-clock"></i>
        Votre session a expiré. Veuillez vous reconnecter.
      </div>
    <?php endif; ?>

    <?php if ($erreur): ?>
      <div class="alert-box">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= e($erreur) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label">Adresse e-mail</label>
        <div class="input-wrap">
          <i class="fa-regular fa-envelope"></i>
          <input type="email" name="email" class="form-input"
                 placeholder="votre.email@crict.edu.gn"
                 value="<?= e($_POST['email'] ?? '') ?>"
                 autocomplete="email" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Mot de passe</label>
        <div class="input-wrap">
          <i class="fa-solid fa-lock"></i>
          <input type="password" name="password" id="password" class="form-input"
                 placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" class="toggle-pass" onclick="togglePassword()">
            <i class="fa-regular fa-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login">
        <i class="fa-solid fa-right-to-bracket"></i>
        Se connecter
      </button>
    </form>

    <div class="login-footer">
      <p><?= date('Y') ?> © <strong>CRICT</strong> – Centre de Recherche en Informatique et Cyber-Technologie</p>
      <p style="margin-top:4px;">Guinée · v<?= APP_VERSION ?></p>
    </div>
  </div>
</div>

<script>
function togglePassword() {
  const input = document.getElementById('password');
  const icon  = document.getElementById('eyeIcon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'fa-regular fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'fa-regular fa-eye';
  }
}
</script>
</body>
</html>
