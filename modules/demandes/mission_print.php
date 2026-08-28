<?php
// modules/demandes/mission_print.php — Ordre de mission (format officiel DG)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/demandes.php';
require_once __DIR__ . '/../../models/personnel.php';

session_demarrer();
exiger_connexion();
demandes_assurer_schema();

$id = (int)($_GET['id'] ?? 0);
$d  = demandes_par_id($id);

if (!$d || $d['categorie'] !== 'MISSION' || $d['statut'] !== 'APPROUVE') {
    http_response_code(404);
    exit('Ordre de mission introuvable ou non validé.');
}

$mon_pid = personnel_assurer_profil((int)user_id());
$peut_rh = a_permission('PERSONNEL', 'VALIDER');
$est_dg  = demandes_est_dg();
if (!demandes_mission_peut_acceder($d, $mon_pid, $peut_rh, $est_dg)) {
    http_response_code(403);
    exit('Accès refusé.');
}

$agents = demandes_mission_agents($id);
$num = $d['ordre_mission_numero'] ?: $d['reference'];
$nationalite = 'Guinéenne';
foreach ($agents as $a) {
    if (!empty($a['nationalite'])) {
        $nationalite = $a['nationalite'];
        break;
    }
}

$img_base = rtrim(APP_URL, '/') . '/img_ordre_mission/';
$entete  = $img_base . 'entete_crict_new.png';
$footer  = $img_base . 'footer.png';

$objet_source = trim((string)($d['objet_mission'] ?? ''));
if ($objet_source === strip_tags($objet_source)) {
    $objet_html = '<p>' . e((string)preg_replace('/\s+/u', ' ', $objet_source)) . '</p>';
} else {
    $objet_html = demandes_nettoyer_html_riche($objet_source);
}

$nb = count($agents);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Ordre de mission <?= e($num) ?></title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    @page{size:A4 portrait;margin:8mm 14mm 6mm 14mm;}
    body{
      font-family:"Avenir","Avenir Next","Helvetica Neue",Helvetica,Arial,sans-serif;
      color:#333;background:#e8eef5;font-size:11pt;line-height:1.4;
    }
    .sheet{
      max-width:210mm;min-height:277mm;margin:16px auto;padding:6mm 10mm 4mm;
      background:#fff;box-shadow:0 2px 18px rgba(0,0,0,.08);
      display:flex;flex-direction:column;
    }
    .no-print{
      display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px;
      padding:10px 12px;background:#f0f6ff;border:1px solid #bdd3fd;border-radius:8px;
      font-size:13px;
    }
    .no-print button{
      padding:9px 18px;background:#1347a8;color:#fff;border:none;border-radius:8px;
      cursor:pointer;font-weight:700;
    }
    .no-print a{color:#1347a8;font-weight:600;text-decoration:none;}

    .doc-band{width:100%;display:block;margin:0;padding:0;line-height:0;}
    .doc-band img{width:100%;height:auto;display:block;}

    .ref-line{
      font-size:10pt;font-weight:700;color:#111;margin:6px 0 4px;
    }
    .doc-title{
      text-align:center;font-size:11pt;font-weight:700;letter-spacing:.08em;
      text-transform:uppercase;margin:8px 0 18px;
    }

    .body{flex:1 1 auto;text-align:justify;}
    .intro{margin:0 0 8px;text-align:justify;font-weight:600;}
    .agents{margin:0 0 16px;padding:0;list-style:none;text-align:justify;}
    .agents li{
      margin:0 0 6px;padding:0;list-style:none;
      font-weight:400;line-height:1.3;text-align:justify;
    }
    .field{margin:0 0 12px;padding:0;line-height:1.4;text-align:justify;font-weight:400;}
    .field strong{font-weight:600;}
    .field-objet{
      text-align:justify;text-justify:inter-word;line-height:1.6;
      hyphens:auto;-webkit-hyphens:auto;
    }
    .field-objet .rich-text{display:inline;}
    .field-objet .rich-text p{margin:0 0 8px;line-height:inherit;}
    .field-objet .rich-text p:first-child{display:inline;}
    .field-objet .rich-text p:not(:first-child){display:block;}
    .field-objet .rich-text ul,
    .field-objet .rich-text ol{margin:8px 0 8px 24px;padding:0;}
    .field-objet .rich-text blockquote{
      margin:8px 0 8px 18px;padding-left:10px;border-left:2px solid #999;
    }
    .dg-comment{margin:10px 0 14px;font-size:10.5pt;line-height:1.5;}
    .dg-comment .value{font-style:italic;font-weight:400;}
    .clause{
      text-align:justify;margin:20px 0 14px;font-size:10.5pt;line-height:1.5;font-weight:400;
    }

    .doc-footer-band{
      margin-top:auto;width:100%;flex-shrink:0;page-break-inside:avoid;
    }
    .doc-footer-band img{width:100%;height:auto;display:block;}

    @media print{
      body{background:#fff;}
      .sheet{
        max-width:none;min-height:277mm;height:277mm;
        margin:0;padding:0;box-shadow:none;
      }
      .no-print{display:none!important;}
    }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="no-print">
      <button type="button" onclick="window.print()">Imprimer / Enregistrer en PDF</button>
      <a href="demandes.php?tab=mes&amp;cat=mission">← Retour aux demandes</a>
    </div>

    <div class="doc-band">
      <img src="<?= e($entete) ?>" alt="En-tête CRICT">
    </div>

    <div class="ref-line">Réf N° <?= e($num) ?></div>
    <div class="doc-title">ORDRE DE MISSION</div>

    <div class="body">
      <p class="intro">Il est ordonné à :</p>
      <ul class="agents">
        <?php foreach ($agents as $i => $a):
          $ligne = demandes_ligne_agent($a);
          $sep = ($i === $nb - 1) ? '.' : ' ;';
        ?>
        <li><?= e($ligne) . $sep ?></li>
        <?php endforeach; ?>
        <?php if (!$agents): ?>
        <li>—</li>
        <?php endif; ?>
      </ul>

      <?php
        $dest = trim((string)($d['destination'] ?? ''));
        $trans = trim((string)($d['moyen_transport'] ?? ''));
        if ($dest !== '') {
            $dest = mb_strtoupper(mb_substr($dest, 0, 1)) . mb_substr($dest, 1);
        }
        if ($trans !== '') {
            $trans = mb_strtoupper(mb_substr($trans, 0, 1)) . mb_substr($trans, 1);
        }
      ?>
      <p class="field"><strong>Nationalité :</strong> <?= e($nationalite) ?></p>
      <p class="field"><strong>De se rendre à :</strong> <?= e($dest !== '' ? $dest : '—') ?></p>
      <div class="field field-objet"><strong>Objet :</strong> <div class="rich-text"><?= $objet_html ?></div></div>
      <p class="field"><strong>Moyen de transport :</strong> <?= e($trans !== '' ? $trans : '—') ?></p>
      <p class="field"><strong>Date de départ :</strong> <?= e(demandes_fmt_date_long($d['date_debut'])) ?></p>
      <p class="field"><strong>Date de retour :</strong> <?= e(demandes_fmt_date_long($d['date_fin'])) ?></p>

      <?php if (trim((string)($d['commentaire_validation'] ?? '')) !== ''): ?>
      <p class="dg-comment">
        <strong>Observation du Directeur Général :</strong>
        <span class="value"><?= nl2br(e($d['commentaire_validation'])) ?></span>
      </p>
      <?php endif; ?>

      <p class="clause">
        Les autorités civiles et militaires des préfectures et communes traversées
        sont priées de faciliter l'accomplissement de la mission.
      </p>
    </div>

    <div class="doc-footer-band">
      <img src="<?= e($footer) ?>" alt="Pied de page CRICT">
    </div>
  </div>
</body>
</html>
