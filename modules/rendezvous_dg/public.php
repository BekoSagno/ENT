<?php
// modules/rendezvous_dg/public.php — Externes : créneaux puis formulaire
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/rendezvous_dg.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
rdv_dg_assurer_schema();
rdv_dg_marquer_termines();

$erreurs = [];
$succes = false;
$ref = '';
$old = [];

$semaine_param = clean($_GET['semaine'] ?? '');
$semaine = rdv_dg_creneaux_semaine(null, 1);

$dispo_id = (int)($_GET['dispo'] ?? $_POST['disponibilite_id'] ?? 0);
$date = clean($_GET['date'] ?? $_POST['date_confirmee'] ?? '');
$heure = clean($_GET['heure'] ?? $_POST['heure_debut'] ?? '');
$creneau_ok = $dispo_id > 0 && $date !== '' && $heure !== ''
    && rdv_dg_creneau_est_libre($dispo_id, $date, $heure);
$selected_key = ($dispo_id && $date && $heure)
    ? ($dispo_id . '|' . $date . '|' . substr($heure, 0, 5)) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['site_web_pro'])) {
        $succes = true;
    } elseif (!csrf_verify()) {
        $erreurs[] = 'Session expirée, veuillez réessayer.';
    } else {
        $old = [
            'nom' => clean($_POST['nom'] ?? ''),
            'prenoms' => clean($_POST['prenoms'] ?? ''),
            'email' => clean($_POST['email'] ?? ''),
            'telephone' => clean($_POST['telephone'] ?? ''),
            'organisation' => clean($_POST['organisation'] ?? ''),
            'fonction' => clean($_POST['fonction'] ?? ''),
            'type_rdv' => clean($_POST['type_rdv'] ?? ''),
            'type_autre' => clean($_POST['type_autre'] ?? ''),
            'objet' => clean($_POST['objet'] ?? ''),
            'note_demandeur' => clean($_POST['note_demandeur'] ?? ''),
        ];
        $dispo_id = (int)($_POST['disponibilite_id'] ?? 0);
        $date = clean($_POST['date_confirmee'] ?? '');
        $heure = clean($_POST['heure_debut'] ?? '');
        if ($old['organisation'] === '') $erreurs[] = 'Organisation obligatoire.';

        $piece = null;
        if (!empty($_FILES['piece_jointe']['name'])) {
            $up = rdv_dg_upload_piece($_FILES['piece_jointe']);
            if (!$up['succes']) $erreurs[] = $up['message'] ?? 'Erreur fichier.';
            else $piece = $up['chemin'];
        }
        if (!$erreurs) {
            $r = rdv_dg_creer(array_merge($old, [
                'disponibilite_id' => $dispo_id,
                'date_confirmee' => $date,
                'heure_debut' => $heure,
                'piece_jointe' => $piece,
            ]), 'PUBLIC');
            if ($r['succes']) {
                $succes = true;
                $ref = $r['reference'] ?? '';
                $old = [];
                $creneau_ok = false;
            } else {
                $erreurs[] = $r['message'] ?? 'Erreur.';
                $creneau_ok = rdv_dg_creneau_est_libre($dispo_id, $date, $heure);
            }
        }
    }
}

$v = fn(string $k) => e($old[$k] ?? '');
$base = 'public.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Prendre rendez-vous — Direction Générale CRICT</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --blue-950:#03144a;--blue-900:#0a2158;--blue-800:#0e3278;--blue-700:#1347a8;
  --blue-600:#1a5ccc;--blue-500:#2570e8;--blue-100:#dbe7ff;--blue-50:#eef4ff;
  --teal:#00c6a2;--bg:#eef3fb;--surface:#ffffff;
  --text:#3d5278;--text-soft:#6b7fa3;--text-muted:#8fa3c4;--border:#e2e8f5;
  --danger:#dc2626;--ok:#059669;--ok-bg:#ecfdf5;--ok-bd:#a7f3d0;
  --font:'Plus Jakarta Sans',system-ui,sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{
  font-family:var(--font);color:var(--text);min-height:100vh;
  background:var(--bg);font-weight:500;line-height:1.6;
  -webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;
}
.page{padding:32px 16px 48px;}
.shell{
  max-width:680px;margin:0 auto;
  background:var(--surface);border-radius:16px;overflow:hidden;
  box-shadow:0 8px 30px rgba(13,50,120,.12);
}

/* En-tête CRICT-ENT */
.shell-head{
  background:linear-gradient(135deg,var(--blue-950) 0%,var(--blue-800) 55%,var(--blue-600) 100%);
  padding:28px 32px 24px;color:#fff;
}
.shell-brand h1{font-size:1.3rem;font-weight:700;letter-spacing:.02em;line-height:1.25;}
.shell-brand .tag{font-size:.78rem;color:#c5d8f8;margin-top:6px;font-weight:400;line-height:1.55;opacity:.95;}
.shell-teal{height:4px;background:var(--teal);}

/* Corps */
.shell-body{padding:28px 32px 24px;}

/* Sections */
.section{margin-bottom:20px;}
.section-title{
  font-size:.95rem;font-weight:600;color:var(--blue-700);margin-bottom:16px;
  display:flex;align-items:center;gap:10px;
}
.section-title .ic{
  width:32px;height:32px;border-radius:9px;background:#f0f6ff;color:var(--blue-600);
  display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;
}

/* Form */
.form-group{margin-bottom:14px;}
.form-label{
  display:block;font-size:.7rem;font-weight:600;margin-bottom:5px;
  color:var(--text-soft);text-transform:uppercase;letter-spacing:.04em;
}
.form-control{
  width:100%;padding:11px 13px;border:1px solid var(--border);border-radius:10px;
  font:inherit;font-size:.9rem;font-weight:500;background:#fafbfd;color:var(--text);
  transition:border .15s,box-shadow .15s,background .15s;
}
.form-control:focus{outline:none;border-color:var(--blue-500);box-shadow:0 0 0 3px rgba(37,112,232,.12);background:#fff;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:560px){
  .shell-head,.shell-body{padding-left:20px;padding-right:20px;}
  .grid2{grid-template-columns:1fr;}
}

.btn{
  display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border:none;border-radius:10px;
  font:inherit;font-size:.88rem;font-weight:700;cursor:pointer;text-decoration:none;
  background:linear-gradient(135deg,var(--blue-600),var(--teal));color:#fff;
  box-shadow:0 6px 18px rgba(26,92,204,.28);transition:transform .12s,box-shadow .12s;
}
.btn:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(26,92,204,.35);}
.btn-secondary{
  background:#fff;color:var(--blue-700);border:1px solid var(--border);
  box-shadow:none;font-weight:600;
}
.btn-secondary:hover{background:#f0f6ff;box-shadow:none;transform:none;}
.btn-sm{padding:8px 14px;font-size:.78rem;border-radius:8px;}

.alert{padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:.86rem;line-height:1.5;}
.alert-error{background:#fef2f2;color:var(--danger);border:1px solid #fecaca;}
.hp{position:absolute;left:-9999px;opacity:0;height:0;width:0;}

/* Succès */
.success-box{text-align:center;padding:8px 4px;}
.success-ic{
  width:68px;height:68px;border-radius:50%;background:var(--ok-bg);color:var(--ok);
  display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 14px;
  border:2px solid var(--ok-bd);
}
.success-box h2{font-size:1.05rem;font-weight:600;color:var(--blue-700);margin-bottom:8px;}
.success-box .ref-chip{
  display:inline-block;background:#f0f6ff;color:var(--blue-700);
  font-weight:800;padding:5px 14px;border-radius:20px;font-size:.88rem;margin:4px 0 10px;
}
.success-box p{font-size:.86rem;color:var(--text-muted);line-height:1.65;max-width:440px;margin:0 auto;}

/* Calendrier */
.rdv-panel{
  background:#f0f6ff;border:1px solid var(--border);border-radius:12px;padding:18px 20px;
}
.rdv-week-nav{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
.rdv-week-nav strong{font-size:.86rem;color:var(--text-soft);font-weight:600;}
.rdv-days{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;}
.rdv-day{
  border:1px solid var(--border);border-radius:12px;background:#fff;
  overflow:hidden;display:flex;flex-direction:column;
}
.rdv-day-head{
  padding:8px 10px;background:#fff;border-bottom:1px solid var(--border);
  font-size:.76rem;font-weight:600;color:var(--blue-700);text-align:center;
}
.rdv-day-head .d{display:block;font-size:.68rem;font-weight:500;color:var(--text-muted);margin-top:1px;}
.rdv-day-body{padding:8px;display:flex;flex-direction:column;gap:6px;}
.rdv-slot{
  display:block;text-align:center;padding:9px 6px;border-radius:8px;
  background:#fff;color:var(--blue-600);font-size:.78rem;font-weight:600;text-decoration:none;
  border:1px solid var(--border);transition:all .13s;
}
.rdv-slot:hover{
  background:linear-gradient(135deg,var(--blue-600),var(--teal));
  border-color:transparent;color:#fff;
  box-shadow:0 4px 12px rgba(26,92,204,.25);
}
.rdv-slot-disabled,.rdv-slot-disabled:hover{
  background:#f1f5f9;border-color:#e2e8f0;color:#94a3b8;cursor:not-allowed;
  box-shadow:none;pointer-events:none;opacity:.9;
}
.rdv-slot-disabled .rdv-slot-etat{
  display:block;font-size:.62rem;font-weight:600;margin-top:2px;text-transform:uppercase;
}
.rdv-slot-selected,.rdv-slot-selected:hover{
  background:var(--ok);border-color:var(--ok);color:#fff;box-shadow:none;
}
.rdv-creneau-box{
  display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:10px;
  background:#f0f6ff;border:1px solid var(--border);margin-bottom:16px;font-size:.88rem;
}
.rdv-creneau-box .ic{
  width:36px;height:36px;border-radius:9px;background:#fff;color:var(--blue-600);
  display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;
  border:1px solid var(--border);
}
.rdv-empty{
  padding:32px 16px;text-align:center;color:var(--text-muted);font-size:.88rem;
  background:#fafbfd;border:1px dashed var(--border);border-radius:10px;
}
.rdv-empty i{display:block;font-size:1.4rem;margin-bottom:8px;color:#a8b8d4;}

/* Pied de page */
.shell-foot{
  background:#f6f8fc;border-top:1px solid var(--border);
  padding:16px 32px;font-size:.75rem;color:var(--text-muted);line-height:1.65;text-align:center;
}
.shell-foot a{color:var(--blue-600);text-decoration:none;font-weight:600;}
.shell-foot strong{color:var(--blue-700);font-weight:600;}
</style>
</head>
<body>
<div class="page">
<div class="shell">
  <div class="shell-head">
    <div class="shell-brand">
      <h1>CRICT</h1>
      <div class="tag">Centre de Recherche en Informatique &amp; CyberTechnologie<br>Prise de rendez-vous Direction Générale</div>
    </div>
  </div>
  <div class="shell-teal"></div>

  <div class="shell-body">

  <?php if ($succes): ?>
    <div class="success-box">
      <div class="success-ic"><i class="fa-solid fa-check"></i></div>
      <h2>Demande enregistrée</h2>
      <?php if ($ref): ?><div class="ref-chip"><?= e($ref) ?></div><?php endif; ?>
      <p>
        Votre créneau est réservé. Un accusé de réception vous a été envoyé par e-mail.
        Vous serez alerté(e) dès que votre demande sera traitée.
      </p>
    </div>
  <?php else: ?>

  <div class="section">
    <div class="section-title"><span class="ic"><i class="fa-solid fa-calendar-days"></i></span> Créneaux disponibles</div>
    <?php foreach ($erreurs as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <div class="rdv-panel">
    <?php if (empty($semaine['tous']) && !$creneau_ok): ?>
      <div class="rdv-empty">
        <i class="fa-regular fa-calendar-xmark"></i>
        Aucun créneau disponible pour le moment.
      </div>
    <?php else: ?>
      <?php rdv_dg_render_calendrier($semaine, $base, $selected_key); ?>
    <?php endif; ?>
    </div>
  </div>

  <?php if ($creneau_ok):
    $dispo = rdv_dg_dispo_par_id($dispo_id);
  ?>
  <div class="section" id="form-card">
    <div class="section-title"><span class="ic"><i class="fa-solid fa-user-pen"></i></span> Vos informations</div>
    <div class="rdv-creneau-box">
      <span class="ic"><i class="fa-solid fa-calendar-check"></i></span>
      <div>
        <strong><?= e(rdv_dg_fmt_date($date)) ?></strong><br>
        <span style="color:var(--text-muted);font-size:.84rem;">
          <?= e(rdv_dg_fmt_heure($heure)) ?> – <?= e(rdv_dg_fmt_heure($dispo['heure_fin'] ?? '')) ?>
          · Direction Générale, Conakry
        </span>
      </div>
    </div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="disponibilite_id" value="<?= (int)$dispo_id ?>">
      <input type="hidden" name="date_confirmee" value="<?= e($date) ?>">
      <input type="hidden" name="heure_debut" value="<?= e(substr($heure, 0, 5)) ?>">
      <input type="text" name="site_web_pro" class="hp" tabindex="-1" autocomplete="off">

      <div class="grid2">
        <div class="form-group"><label class="form-label">Nom *</label>
          <input type="text" name="nom" class="form-control" required value="<?= $v('nom') ?>"></div>
        <div class="form-group"><label class="form-label">Prénoms *</label>
          <input type="text" name="prenoms" class="form-control" required value="<?= $v('prenoms') ?>"></div>
      </div>
      <div class="grid2">
        <div class="form-group"><label class="form-label">E-mail *</label>
          <input type="email" name="email" class="form-control" required value="<?= $v('email') ?>"></div>
        <div class="form-group"><label class="form-label">Téléphone</label>
          <input type="text" name="telephone" class="form-control" value="<?= $v('telephone') ?>"></div>
      </div>
      <div class="grid2">
        <div class="form-group"><label class="form-label">Organisation *</label>
          <input type="text" name="organisation" class="form-control" required value="<?= $v('organisation') ?>"></div>
        <div class="form-group"><label class="form-label">Fonction</label>
          <input type="text" name="fonction" class="form-control" value="<?= $v('fonction') ?>"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Type *</label>
        <select name="type_rdv" id="type_rdv" class="form-control" required>
          <?php foreach (RDV_DG_TYPES as $k => $l): ?>
          <option value="<?= e($k) ?>" <?= ($old['type_rdv'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" id="type_autre_wrap" style="display:none;">
        <label class="form-label">Précisez le type *</label>
        <input type="text" name="type_autre" id="type_autre" class="form-control" maxlength="150" value="<?= $v('type_autre') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Objet *</label>
        <textarea name="objet" class="form-control" rows="3" required><?= $v('objet') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Note</label>
        <textarea name="note_demandeur" class="form-control" rows="2"><?= $v('note_demandeur') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Pièce jointe</label>
        <input type="file" name="piece_jointe" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:6px;">
        <button type="submit" class="btn"><i class="fa-solid fa-paper-plane"></i> Réserver ce créneau</button>
        <a href="<?= e($base) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Changer</a>
      </div>
    </form>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  </div><!-- /.shell-body -->

  <div class="shell-foot">
    <strong>Centre de Recherche en Informatique et Cyber-Technologie</strong><br>
    Conakry, République de Guinée · <a href="https://www.crict.edu.gn">www.crict.edu.gn</a>
  </div>
</div><!-- /.shell -->
</div><!-- /.page -->
<script>
(function(){
  var fc=document.getElementById('form-card');
  if(fc && location.search.indexOf('dispo=')>-1){fc.scrollIntoView({behavior:'smooth',block:'start'});}
  var sel=document.getElementById('type_rdv'); if(!sel)return;
  var wrap=document.getElementById('type_autre_wrap');
  var inp=document.getElementById('type_autre');
  function sync(){var a=sel.value==='AUTRE';wrap.style.display=a?'block':'none';inp.required=a;if(!a)inp.value='';}
  sel.addEventListener('change',sync);sync();
})();
</script>
</body>
</html>
