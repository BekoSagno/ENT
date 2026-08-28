<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

session_demarrer();
exiger_connexion();

$user      = user_courant();
$first     = !empty($_GET['first']);
$erreur    = '';
$succes    = false;

// Règles de validation
function valider_mdp(string $mdp): array {
    $erreurs = [];
    if (strlen($mdp) < 8)                          $erreurs[] = 'Au moins 8 caractères';
    if (!preg_match('/[A-Z]/', $mdp))              $erreurs[] = 'Au moins une majuscule';
    if (!preg_match('/[a-z]/', $mdp))              $erreurs[] = 'Au moins une minuscule';
    if (!preg_match('/[0-9]/', $mdp))              $erreurs[] = 'Au moins un chiffre';
    if (!preg_match('/[\W_]/', $mdp))              $erreurs[] = 'Au moins un caractère spécial (@, #, !, …)';
    return $erreurs;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $erreur = 'Requête invalide. Veuillez réessayer.';
    } else {
        $ancien   = $_POST['ancien_mdp']    ?? '';
        $nouveau  = $_POST['nouveau_mdp']   ?? '';
        $confirm  = $_POST['confirmer_mdp'] ?? '';

        // Vérifier l'ancien mot de passe (sauf première connexion si force_mdp)
        $db   = get_db();
        $stmt = $db->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id = ?");
        $stmt->execute([$user['id']]);
        $row  = $stmt->fetch();

        if (!$user['force_mdp'] && !verify_password($ancien, $row['mot_de_passe'])) {
            $erreur = 'Votre mot de passe actuel est incorrect.';
        } elseif ($nouveau !== $confirm) {
            $erreur = 'Les deux nouveaux mots de passe ne correspondent pas.';
        } elseif ($nouveau === $ancien) {
            $erreur = 'Le nouveau mot de passe doit être différent de l\'ancien.';
        } else {
            $regles = valider_mdp($nouveau);
            if (!empty($regles)) {
                $erreur = 'Le mot de passe ne respecte pas les règles de sécurité.';
            } else {
                // Tout bon — mettre à jour
                $hash = hash_password($nouveau);
                $sel  = bin2hex(random_bytes(16));
                $db->prepare("UPDATE utilisateurs SET mot_de_passe=?, sel=?, force_mdp=0 WHERE id=?")
                   ->execute([$hash, $sel, $user['id']]);

                // Mettre à jour la session
                $_SESSION['user']['force_mdp'] = 0;

                log_activite($user['id'], 'AUTH', 'CHANGEMENT_MDP', 'Changement de mot de passe réussi');
                $succes = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $first ? 'Bienvenue – Définir mon mot de passe' : 'Changer mon mot de passe' ?> — CRICT</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --blue-950: #03144a;
  --blue-900: #0a2158;
  --blue-800: #0e3278;
  --blue-700: #1347a8;
  --blue-600: #1a5ccc;
  --blue-500: #2570e8;
  --blue-400: #4d8ef5;
  --blue-300: #85b3fa;
  --blue-200: #bdd3fd;
  --blue-100: #ddeaff;
  --accent:   #00c6a2;
  --success:  #22c55e;
  --danger:   #ef4444;
  --warn:     #f59e0b;
  --font: 'Plus Jakarta Sans', sans-serif;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: var(--font);
  background: linear-gradient(140deg, var(--blue-950) 0%, var(--blue-900) 55%, #0b2d6e 100%);
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  padding: 24px;
  position: relative; overflow: hidden;
}

/* Déco */
.bg-orbs { position: fixed; inset: 0; pointer-events: none; overflow: hidden; }
.orb {
  position: absolute; border-radius: 50%;
  filter: blur(80px); opacity: .18;
}
.orb-1 { width: 520px; height: 520px; background: var(--blue-500); top: -140px; right: -80px; }
.orb-2 { width: 380px; height: 380px; background: var(--accent);   bottom: -100px; left: -60px; }
.orb-3 { width: 260px; height: 260px; background: var(--blue-400); top: 50%; left: 30%; }

.bg-grid {
  position: fixed; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 44px 44px;
}

/* Card */
.card {
  position: relative; z-index: 10;
  width: 100%; max-width: 480px;
  background: rgba(255,255,255,.97);
  border-radius: 22px;
  box-shadow: 0 32px 80px rgba(3,20,74,.55), 0 0 0 1px rgba(255,255,255,.06);
  overflow: hidden;
}

/* Header */
.card-top {
  background: linear-gradient(135deg, var(--blue-900) 0%, var(--blue-700) 100%);
  padding: 30px 34px 26px;
  position: relative;
}
.card-top::after {
  content: ''; position: absolute;
  bottom: -14px; left: 0; right: 0; height: 14px;
  background: rgba(255,255,255,.97);
  border-radius: 22px 22px 0 0;
}
/* Cercle déco header */
.card-top::before {
  content: ''; position: absolute;
  width: 180px; height: 180px; border-radius: 50%;
  border: 1px solid rgba(255,255,255,.08);
  top: -50px; right: -40px;
}

.top-row {
  display: flex; align-items: center; gap: 14px;
}
.icon-wrap {
  width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
  background: linear-gradient(135deg, var(--blue-500), var(--accent));
  display: flex; align-items: center; justify-content: center;
  font-size: 1.25rem; color: #fff;
  box-shadow: 0 6px 20px rgba(0,198,162,.3);
}
.top-text h1 {
  color: #fff; font-size: 1.1rem; font-weight: 800; line-height: 1.2;
}
.top-text p {
  color: var(--blue-300); font-size: .78rem; margin-top: 3px; line-height: 1.4;
}

/* First-login banner */
.first-banner {
  display: flex; align-items: flex-start; gap: 10px;
  background: rgba(0,198,162,.12);
  border: 1px solid rgba(0,198,162,.3);
  border-radius: 10px;
  padding: 11px 14px;
  margin-top: 18px;
  color: #b2f5ea;
  font-size: .8rem; line-height: 1.5;
}
.first-banner i { margin-top: 1px; flex-shrink: 0; color: var(--accent); }

/* Body */
.card-body { padding: 30px 34px; }

/* Alert */
.alert {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 14px; border-radius: 10px;
  font-size: .84rem; margin-bottom: 22px;
  animation: slideIn .2s ease;
}
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
@keyframes slideIn { from { opacity:0; transform: translateY(-6px); } }

/* Form */
.form-group { margin-bottom: 20px; }
.form-label {
  display: flex; align-items: center; gap: 6px;
  font-size: .8rem; font-weight: 700; color: #374151;
  margin-bottom: 7px; letter-spacing: .01em;
}
.input-wrap { position: relative; }
.input-wrap .prefix-icon {
  position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
  color: #9ca3af; font-size: .85rem; pointer-events: none;
}
.form-input {
  width: 100%;
  padding: 11px 42px 11px 38px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  font-size: .9rem; font-family: var(--font); color: #1a2540;
  background: #f9fafb;
  transition: border-color .15s, box-shadow .15s, background .15s;
  outline: none;
}
.form-input:focus {
  border-color: var(--blue-500);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(37,112,232,.12);
}
.form-input.valid   { border-color: var(--success); }
.form-input.invalid { border-color: var(--danger); }
.toggle-eye {
  position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: #9ca3af; padding: 5px; border-radius: 5px;
  transition: color .12s;
}
.toggle-eye:hover { color: var(--blue-600); }

/* Strength bar */
.strength-wrap { margin-top: 10px; }
.strength-bar {
  height: 5px; border-radius: 20px;
  background: #e5e7eb; overflow: hidden; margin-bottom: 6px;
}
.strength-fill {
  height: 100%; border-radius: 20px;
  transition: width .35s ease, background .35s ease;
  width: 0%;
}
.strength-label {
  font-size: .72rem; font-weight: 600; color: var(--text-muted);
}

/* Règles */
.rules-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 5px 12px; margin-top: 10px;
}
.rule {
  display: flex; align-items: center; gap: 6px;
  font-size: .75rem; color: #9ca3af;
  transition: color .2s;
}
.rule.ok { color: var(--success); }
.rule .dot {
  width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0;
  border: 1.5px solid #d1d5db;
  display: flex; align-items: center; justify-content: center;
  font-size: .6rem; transition: all .2s;
}
.rule.ok .dot { border-color: var(--success); background: var(--success); color: #fff; }

/* Match indicator */
.match-hint {
  font-size: .73rem; margin-top: 5px; display: flex; align-items: center; gap: 5px;
  min-height: 18px;
}
.match-hint.ok     { color: var(--success); }
.match-hint.no     { color: var(--danger); }

/* Submit */
.btn-submit {
  width: 100%; padding: 13px;
  background: linear-gradient(135deg, var(--blue-600), var(--blue-700));
  color: #fff; border: none; border-radius: 11px;
  font-size: .95rem; font-weight: 700; font-family: var(--font);
  cursor: pointer; transition: all .2s;
  box-shadow: 0 4px 16px rgba(26,92,204,.35);
  display: flex; align-items: center; justify-content: center; gap: 9px;
  margin-top: 24px;
}
.btn-submit:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 7px 22px rgba(26,92,204,.45);
}
.btn-submit:disabled { opacity: .55; cursor: not-allowed; }

/* Success screen */
.success-screen {
  text-align: center; padding: 10px 0 6px;
}
.success-anim {
  width: 72px; height: 72px; border-radius: 50%;
  background: linear-gradient(135deg, var(--success), #16a34a);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.9rem; color: #fff;
  margin: 0 auto 20px;
  box-shadow: 0 8px 24px rgba(34,197,94,.35);
  animation: pop .4s cubic-bezier(.34,1.56,.64,1);
}
@keyframes pop { from { transform: scale(0); opacity:0; } }
.success-screen h2 { font-size: 1.15rem; font-weight: 800; color: #1a2540; margin-bottom: 8px; }
.success-screen p  { font-size: .85rem; color: #6b7fa3; margin-bottom: 24px; line-height: 1.5; }
.btn-dashboard {
  display: inline-flex; align-items: center; gap: 8px;
  background: linear-gradient(135deg, var(--blue-600), var(--blue-700));
  color: #fff; text-decoration: none;
  padding: 12px 28px; border-radius: 10px;
  font-size: .9rem; font-weight: 700;
  box-shadow: 0 4px 14px rgba(26,92,204,.3);
  transition: all .2s;
}
.btn-dashboard:hover { transform: translateY(-1px); box-shadow: 0 7px 20px rgba(26,92,204,.4); }

/* Footer */
.card-footer {
  padding: 14px 34px 22px;
  text-align: center;
  font-size: .73rem; color: #9ca3af;
  border-top: 1px solid #f3f4f6;
}
.card-footer a { color: var(--blue-600); text-decoration: none; font-weight: 500; }
.card-footer a:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="bg-orbs">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>
<div class="bg-grid"></div>

<div class="card">

  <!-- Header -->
  <div class="card-top">
    <div class="top-row">
      <div class="icon-wrap">
        <i class="fa-solid <?= $first ? 'fa-key' : 'fa-lock' ?>"></i>
      </div>
      <div class="top-text">
        <h1><?= $first ? 'Définir mon mot de passe' : 'Changer mon mot de passe' ?></h1>
        <p>
          <?= $first
            ? 'Bonjour ' . e($user['prenoms']) . ' — première connexion détectée'
            : e($user['prenoms'] . ' ' . $user['nom']) . ' · ' . e($user['groupe_libelle'] ?? '') ?>
        </p>
      </div>
    </div>

    <?php if ($first): ?>
    <div class="first-banner">
      <i class="fa-solid fa-circle-info"></i>
      <div>
        Votre compte a été créé avec un mot de passe temporaire.<br>
        Vous devez définir un mot de passe personnel et sécurisé avant de continuer.
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Body -->
  <div class="card-body">

    <?php if ($succes): ?>
    <!-- Écran de succès -->
    <div class="success-screen">
      <div class="success-anim"><i class="fa-solid fa-check"></i></div>
      <h2>Mot de passe mis à jour !</h2>
      <p>
        Votre nouveau mot de passe est actif.<br>
        Vous pouvez maintenant accéder à votre espace de travail.
      </p>
      <a href="<?= APP_URL ?>/dashboard.php" class="btn-dashboard">
        <i class="fa-solid fa-gauge-high"></i>
        Accéder au tableau de bord
      </a>
    </div>

    <?php else: ?>

    <?php if ($erreur): ?>
    <div class="alert alert-error">
      <i class="fa-solid fa-circle-exclamation" style="margin-top:1px;flex-shrink:0;"></i>
      <?= e($erreur) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" id="mdpForm" novalidate>
      <?= csrf_field() ?>

      <!-- Ancien MDP (masqué si force_mdp) -->
      <?php if (!$user['force_mdp']): ?>
      <div class="form-group">
        <label class="form-label">
          <i class="fa-solid fa-lock-open" style="color:var(--blue-400);font-size:.85rem;"></i>
          Mot de passe actuel
        </label>
        <div class="input-wrap">
          <i class="fa-solid fa-lock prefix-icon"></i>
          <input type="password" name="ancien_mdp" id="ancienMdp"
                 class="form-input" placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" class="toggle-eye" onclick="toggleVis('ancienMdp', this)">
            <i class="fa-regular fa-eye"></i>
          </button>
        </div>
      </div>
      <?php endif; ?>

      <!-- Nouveau MDP -->
      <div class="form-group">
        <label class="form-label">
          <i class="fa-solid fa-shield-halved" style="color:var(--blue-400);font-size:.85rem;"></i>
          Nouveau mot de passe
        </label>
        <div class="input-wrap">
          <i class="fa-solid fa-key prefix-icon"></i>
          <input type="password" name="nouveau_mdp" id="nouveauMdp"
                 class="form-input" placeholder="Choisir un mot de passe fort…"
                 autocomplete="new-password"
                 oninput="evaluerForce(this.value); verifierMatch();" required>
          <button type="button" class="toggle-eye" onclick="toggleVis('nouveauMdp', this)">
            <i class="fa-regular fa-eye"></i>
          </button>
        </div>

        <!-- Barre de force -->
        <div class="strength-wrap">
          <div class="strength-bar">
            <div class="strength-fill" id="strengthFill"></div>
          </div>
          <div class="strength-label" id="strengthLabel">Saisissez un mot de passe</div>
        </div>

        <!-- Règles -->
        <div class="rules-grid" style="margin-top:10px;">
          <div class="rule" id="r-len">
            <div class="dot"><i class="fa-solid fa-check"></i></div>
            8 caractères minimum
          </div>
          <div class="rule" id="r-maj">
            <div class="dot"><i class="fa-solid fa-check"></i></div>
            Une majuscule
          </div>
          <div class="rule" id="r-min">
            <div class="dot"><i class="fa-solid fa-check"></i></div>
            Une minuscule
          </div>
          <div class="rule" id="r-num">
            <div class="dot"><i class="fa-solid fa-check"></i></div>
            Un chiffre
          </div>
          <div class="rule" id="r-spe">
            <div class="dot"><i class="fa-solid fa-check"></i></div>
            Un caractère spécial
          </div>
        </div>
      </div>

      <!-- Confirmer MDP -->
      <div class="form-group">
        <label class="form-label">
          <i class="fa-solid fa-check-double" style="color:var(--blue-400);font-size:.85rem;"></i>
          Confirmer le mot de passe
        </label>
        <div class="input-wrap">
          <i class="fa-solid fa-lock prefix-icon"></i>
          <input type="password" name="confirmer_mdp" id="confirmerMdp"
                 class="form-input" placeholder="Répéter le mot de passe…"
                 autocomplete="new-password"
                 oninput="verifierMatch()" required>
          <button type="button" class="toggle-eye" onclick="toggleVis('confirmerMdp', this)">
            <i class="fa-regular fa-eye"></i>
          </button>
        </div>
        <div class="match-hint" id="matchHint"></div>
      </div>

      <button type="submit" class="btn-submit" id="btnSubmit" disabled>
        <i class="fa-solid fa-shield-check"></i>
        Enregistrer le nouveau mot de passe
      </button>
    </form>

    <?php endif; ?>
  </div>

  <!-- Footer -->
  <?php if (!$succes): ?>
  <div class="card-footer">
    <?php if (!$first): ?>
      <a href="<?= APP_URL ?>/dashboard.php"><i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord</a>
    <?php else: ?>
      <a href="<?= APP_URL ?>/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Se déconnecter</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div><!-- /card -->

<script>
// Afficher / masquer mot de passe
function toggleVis(inputId, btn) {
  const input = document.getElementById(inputId);
  const icon  = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'fa-regular fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'fa-regular fa-eye';
  }
}

// Vérification des règles
const regles = {
  len: v => v.length >= 8,
  maj: v => /[A-Z]/.test(v),
  min: v => /[a-z]/.test(v),
  num: v => /[0-9]/.test(v),
  spe: v => /[\W_]/.test(v),
};

function evaluerForce(val) {
  const fill  = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');

  // Mettre à jour chaque règle
  let ok = 0;
  for (const [key, fn] of Object.entries(regles)) {
    const el = document.getElementById('r-' + key);
    if (fn(val)) { el.classList.add('ok');  ok++; }
    else         { el.classList.remove('ok'); }
  }

  // Calcul du score
  const score = ok / 5;
  const pct   = Math.round(score * 100);

  const niveaux = [
    { min: 0,   max: 0.2, color: '#ef4444', txt: 'Très faible' },
    { min: 0.2, max: 0.4, color: '#f97316', txt: 'Faible'      },
    { min: 0.4, max: 0.6, color: '#eab308', txt: 'Moyen'       },
    { min: 0.6, max: 0.8, color: '#84cc16', txt: 'Fort'        },
    { min: 0.8, max: 1.1, color: '#22c55e', txt: 'Très fort'   },
  ];

  const niveau = val.length === 0
    ? null
    : niveaux.find(n => score >= n.min && score < n.max) || niveaux[4];

  fill.style.width    = val.length ? pct + '%' : '0%';
  fill.style.background = niveau ? niveau.color : '#e5e7eb';
  label.style.color     = niveau ? niveau.color : '#9ca3af';
  label.textContent     = val.length ? niveau.txt : 'Saisissez un mot de passe';

  // Input style
  const input = document.getElementById('nouveauMdp');
  input.classList.toggle('valid',   ok === 5 && val.length > 0);
  input.classList.toggle('invalid', ok < 5  && val.length > 0);

  majBouton();
}

function verifierMatch() {
  const v1   = document.getElementById('nouveauMdp').value;
  const v2   = document.getElementById('confirmerMdp').value;
  const hint = document.getElementById('matchHint');
  const inp  = document.getElementById('confirmerMdp');

  if (v2.length === 0) {
    hint.textContent = '';
    hint.className   = 'match-hint';
    inp.classList.remove('valid', 'invalid');
  } else if (v1 === v2) {
    hint.innerHTML   = '<i class="fa-solid fa-circle-check"></i> Les mots de passe correspondent';
    hint.className   = 'match-hint ok';
    inp.classList.add('valid');
    inp.classList.remove('invalid');
  } else {
    hint.innerHTML   = '<i class="fa-solid fa-circle-xmark"></i> Les mots de passe ne correspondent pas';
    hint.className   = 'match-hint no';
    inp.classList.add('invalid');
    inp.classList.remove('valid');
  }
  majBouton();
}

function toutesReglesOk() {
  const val = document.getElementById('nouveauMdp').value;
  return Object.values(regles).every(fn => fn(val));
}

function majBouton() {
  const v1  = document.getElementById('nouveauMdp').value;
  const v2  = document.getElementById('confirmerMdp').value;
  const btn = document.getElementById('btnSubmit');
  btn.disabled = !(toutesReglesOk() && v1 === v2 && v2.length > 0);
}
</script>
</body>
</html>