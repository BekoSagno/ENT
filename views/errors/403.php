<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Accès refusé — CRICT</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&display=swap" rel="stylesheet">
<style>
body { font-family:'Plus+Jakarta+Sans',sans-serif; background:#f0f6ff; display:flex; align-items:center; justify-content:center; min-height:100vh; }
.box { text-align:center; max-width:440px; padding:40px; background:#fff; border-radius:16px; box-shadow:0 8px 32px rgba(13,50,120,.1); }
.icon { font-size:3.5rem; margin-bottom:16px; }
h1 { font-size:1.5rem; font-weight:800; color:#0e3278; margin-bottom:8px; }
p { color:#6b7fa3; font-size:.9rem; margin-bottom:24px; }
a { background:#1a5ccc; color:#fff; padding:10px 24px; border-radius:8px; text-decoration:none; font-weight:600; font-size:.9rem; }
</style>
</head>
<body>
<div class="box">
  <div class="icon">🔒</div>
  <h1>Accès refusé</h1>
  <p>Vous n'avez pas les permissions nécessaires pour accéder à cette page. Contactez votre administrateur si vous pensez qu'il s'agit d'une erreur.</p>
  <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/dashboard.php">Retour au tableau de bord</a>
</div>
</body>
</html>
