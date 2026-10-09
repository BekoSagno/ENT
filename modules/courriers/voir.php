<?php
// modules/courriers/voir.php — Fiche détaillée d'un courrier
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/courrier.php';

session_demarrer();
exiger_connexion();

$cid  = (int)($_GET['id'] ?? 0);
$cour = $cid ? courrier_charger($cid) : null;
if (!$cour || !courrier_peut_voir($cour)) {
    set_flash('error', $cour ? 'Ce courrier est confidentiel : vous n\'y avez pas accès.' : 'Courrier introuvable.');
    redirect('/modules/courriers/index.php');
}

$uid          = (int)user_id();
$est_dg       = courrier_est_dg();
$affectations = courrier_affectations($cid);
$echanges     = courrier_echanges($cid);
$ma_part      = null;
$pilote       = null;
$copies       = [];
foreach ($affectations as $a) {
    if ((int)$a['utilisateur_id'] === $uid) $ma_part = $a;
    if ($a['role'] === 'PILOTE') $pilote = $a; else $copies[] = $a;
}
$est_pilote = $ma_part && $ma_part['role'] === 'PILOTE';
if ($ma_part) courrier_marquer_vu($cid, $uid);
courrier_notif_marquer_lues($uid, $cid);

$entrant        = $cour['sens'] === 'ENTRANT';
$peut_modifier  = a_permission('COURRIERS', 'MODIFIER') && !courrier_sortant_verrouille($cour);
$peut_supprimer = a_permission('COURRIERS', 'SUPPRIMER');
$peut_affecter  = $entrant && $est_dg && in_array($cour['statut'], ['RECU', 'AFFECTE', 'EN_TRAITEMENT'], true);
$peut_archiver  = ($est_dg || a_permission('COURRIERS', 'VALIDER'))
                  && in_array($cour['statut'], $entrant ? ['TRAITE', 'CLOTURE'] : ['CLOTURE'], true);
$peut_cloturer  = $entrant && $est_dg && !in_array($cour['statut'], ['CLOTURE', 'ARCHIVE'], true);
$peut_ecrire    = courrier_peut_ecrire($cour);
// Sortant : validation du DG puis envoi
$sortant_a_valider = !$entrant && in_array($cour['statut'], ['RECU', 'AFFECTE', 'EN_TRAITEMENT'], true);
$peut_valider_s    = !$entrant && $est_dg && $sortant_a_valider;
$peut_renvoyer_s   = !$entrant && $est_dg && in_array($cour['statut'], ['RECU', 'AFFECTE', 'TRAITE'], true);
$peut_resoumettre  = courrier_peut_resoumettre($cour);
$peut_expedier     = courrier_peut_expedier($cour);
$envoi_email       = $cour['canal'] === 'EMAIL';
$dernier_envoi     = $entrant ? null : courrier_dernier_envoi($cour);
$pilote_statut  = $pilote['statut'] ?? null;
$focus_fil      = ($_GET['focus'] ?? '') === 'echanges';

$STATUTS = courrier_statuts();
$PRIOS   = courrier_priorites();
$CANAUX  = courrier_canaux();
$TYPES   = courrier_types();
$CONFS   = courrier_confidentialites();

$st = courrier_statut_info($cour['statut'], $cour['sens']);
$pr = $PRIOS[$cour['priorite']]          ?? $PRIOS['NORMALE'];
$cn = $CANAUX[$cour['canal']]            ?? $CANAUX['AUTRE'];
$cf = $CONFS[$cour['confidentialite']]   ?? $CONFS['NORMAL'];
$en_retard = !empty($cour['date_echeance']) && $cour['date_echeance'] < date('Y-m-d')
             && !in_array($cour['statut'], courrier_statuts_termines($cour['sens']), true);
$delai_depasse = $pilote && !empty($pilote['date_limite']) && $pilote['date_limite'] < date('Y-m-d')
                 && in_array($pilote_statut, ['A_TRAITER', 'EN_COURS'], true);

$services     = $peut_affecter ? courrier_services_arbre() : [];
$utilisateurs = $peut_affecter ? courrier_utilisateurs()   : [];
$copies_ids   = array_map(fn($a) => (int)$a['utilisateur_id'], $copies);
$delai_defaut = max(date('Y-m-d'), $pilote['date_limite'] ?? ($cour['date_echeance'] ?: date('Y-m-d', strtotime('+7 days'))));

$doc_types = [
    'SCAN'         => ['Scan du courrier', 'fa-file-image',  '#dbeafe', '#1d4ed8'],
    'PIECE_JOINTE' => ['Pièce jointe',     'fa-paperclip',   '#fef3c7', '#92400e'],
    'REPONSE'      => ['Réponse',          'fa-reply',       '#dcfce7', '#166534'],
    'AUTRE'        => ['Autre',            'fa-file',        '#f3f4f6', '#6b7280'],
];
$docs_par_type = [];
foreach ($cour['documents'] as $d) { $docs_par_type[$d['type_document']][] = $d; }

// Étape du circuit et message du panneau « Traitement »
$etapes_circuit = ['RECU' => 'Reçu', 'AFFECTE' => 'Affecté', 'EN_TRAITEMENT' => 'En traitement', 'SOUMIS' => 'Soumis au DG', 'TRAITE' => 'Traité', 'CLOTURE' => 'Clôturé'];
$etape = $cour['statut'];
if ($pilote_statut === 'SOUMIS' && in_array($etape, ['AFFECTE', 'EN_TRAITEMENT'], true)) $etape = 'SOUMIS';
if ($etape === 'ARCHIVE') $etape = 'CLOTURE';
$idx_etape = $etape === 'CLOTURE' ? count($etapes_circuit) : array_search($etape, array_keys($etapes_circuit), true);

$nom_pilote = $pilote['nom_complet'] ?? ($cour['affecte_nom'] ?? '');
if ($cour['statut'] === 'RECU') {
    $etat = $est_dg ? 'Ce courrier attend votre orientation : choisissez le service et l\'agent pilote.' : 'En attente d\'orientation par le DG.';
} elseif ($cour['statut'] === 'ARCHIVE') {
    $etat = 'Courrier archivé.';
} elseif ($cour['statut'] === 'CLOTURE') {
    $etat = 'Courrier clôturé.';
} elseif ($cour['statut'] === 'TRAITE') {
    $etat = 'Traitement validé par le DG' . (!empty($cour['date_traitement']) ? ' le ' . format_datetime($cour['date_traitement']) : '') . '.';
} elseif ($pilote_statut === 'SOUMIS') {
    $etat = e($nom_pilote) . ' a soumis son traitement le ' . format_datetime($pilote['soumis_le'])
          . ($est_dg ? ' : il attend votre décision.' : ' : en attente de la validation du DG.');
} elseif ($pilote) {
    $etat = $est_pilote
        ? ($pilote_statut === 'A_TRAITER' ? 'Ce courrier vous est confié : démarrez le traitement puis soumettez-le au DG.' : 'Traitement en cours : soumettez-le au DG une fois terminé.')
        : 'Confié à ' . e($nom_pilote) . ($pilote_statut === 'A_TRAITER'
            ? ($pilote['vue_le'] ? ' (consulté le ' . format_datetime($pilote['vue_le']) . ', pas encore démarré).' : ' (pas encore consulté).')
            : ' — traitement en cours.');
} else {
    $etat = 'Affecté à ' . e($nom_pilote ?: '—') . '.' . ($est_dg ? ' Réaffectez-le pour désigner un agent pilote et des agents en copie.' : '');
}
$etat_classe = $pilote_statut === 'SOUMIS' && $est_dg ? 'attente' : (in_array($cour['statut'], ['TRAITE','CLOTURE','ARCHIVE'], true) ? 'ok' : '');

// Sortant : Enregistré → Validation du DG → Envoi → Envoyé
if (!$entrant) {
    $mot_envoye     = $envoi_email ? 'Envoyé' : 'Expédié';
    $etapes_circuit = ['ENR' => 'Enregistré', 'VALID' => 'Validation du DG', 'ENVOI' => $envoi_email ? 'Envoi par email' : 'Expédition', 'FAIT' => $mot_envoye];
    $idx_etape      = match (true) {
        $sortant_a_valider            => 1,
        $cour['statut'] === 'TRAITE'  => 2,
        default                       => count($etapes_circuit),
    };
    $dernier_renvoi = null;
    foreach ($echanges as $m) if ($m['type'] === 'REJET') $dernier_renvoi = $m;
    $date_valid = !empty($cour['date_traitement']) ? ' le ' . format_datetime($cour['date_traitement']) : '';

    if ($cour['statut'] === 'EN_TRAITEMENT') {
        $etat = 'Renvoyé pour correction par le DG' . ($dernier_renvoi ? ' : « ' . e($dernier_renvoi['message']) . ' »' : '.')
              . ($peut_resoumettre ? '<br>Modifiez le courrier ou ses pièces jointes, puis soumettez-le à nouveau.' : '');
        $etat_classe = 'attente';
    } elseif ($sortant_a_valider) {
        $etat = $est_dg ? 'Ce courrier sortant attend votre validation : vérifiez la lettre et les pièces jointes avant l\'envoi.'
                        : 'En attente de la validation du DG avant l\'envoi.';
        $etat_classe = $est_dg ? 'attente' : '';
    } elseif ($cour['statut'] === 'TRAITE') {
        $etat = 'Validé par le DG' . $date_valid . '. ';
        if ($peut_expedier) {
            $etat .= $envoi_email ? 'Vous pouvez l\'envoyer à ' . e($cour['correspondant_email'] ?: 'son destinataire') . '.'
                                  : 'Une fois la lettre remise ou postée, marquez-la comme expédiée.';
        } else {
            $etat .= 'En attente d\'envoi par la personne qui l\'a enregistré.';
        }
        $etat_classe = 'ok';
    } else {
        $etat = $dernier_envoi
            ? e($dernier_envoi['commentaire']) . ' (' . format_datetime($dernier_envoi['cree_le']) . (!empty($dernier_envoi['auteur']) ? ', par ' . e($dernier_envoi['auteur']) : '') . ').'
            : $mot_envoye . '.';
        if ($cour['statut'] === 'ARCHIVE') $etat .= ' Courrier archivé.';
        $etat_classe = 'ok';
    }
}

$page_titre      = 'Courrier ' . e($cour['reference']);
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Courriers</a> · ' . e($cour['reference']);
$module_actif    = 'COURRIERS';
include __DIR__ . '/../../views/layout/header.php';

$val = fn($v) => ($v !== null && $v !== '') ? e((string)$v) : '<span style="color:var(--text-muted);font-weight:400;">—</span>';
$initiales = fn(string $n) => mb_strtoupper(implode('', array_map(fn($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/', trim($n)) ?: [], 0, 2))));
?>
<style>
.ch{background:var(--surface);border:1.5px solid var(--border);border-left:5px solid <?= $st['bord'] ?>;border-radius:14px;padding:20px 24px;margin-bottom:20px;}
.ch-top{display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;}
.ch-ico{width:56px;height:56px;border-radius:12px;background:<?= $entrant ? '#dcfce7' : '#dbeafe' ?>;color:<?= $entrant ? '#166534' : '#1d4ed8' ?>;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
.ch-ref{font-family:var(--mono);font-size:.82rem;font-weight:700;color:var(--blue-600);}
.ch-titre{font-size:1.1rem;font-weight:800;color:var(--text);margin:3px 0 5px;}
.ch-sub{font-size:.78rem;color:var(--text-muted);}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}

.info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:16px;}
.info-block{background:var(--bg);border-radius:9px;padding:11px 13px;}
.ib-lbl{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;}
.ib-val{font-size:.85rem;font-weight:600;color:var(--text);word-break:break-word;}
.ib-sub{font-size:.7rem;color:var(--text-muted);font-weight:400;margin-top:2px;}

.txt-bloc{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;margin-bottom:16px;}
.txt-bloc h4{font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin:0 0 8px;}
.txt-bloc div{font-size:.86rem;line-height:1.6;color:var(--text);}
.instr-bloc{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px 18px;margin-bottom:16px;}
.instr-bloc h4{font-size:.75rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em;margin:0 0 6px;}
.instr-bloc div{font-size:.86rem;line-height:1.6;color:#7c5e10;}

.trt{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;padding:16px 20px;margin-bottom:20px;}
.trt-hd{display:flex;align-items:center;gap:8px;font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px;}
.circuit{display:flex;align-items:center;margin-bottom:14px;overflow-x:auto;padding-bottom:2px;}
.circuit .pt{display:flex;flex-direction:column;align-items:center;gap:5px;min-width:86px;font-size:.7rem;font-weight:600;color:var(--text-muted);text-align:center;}
.circuit .dot{width:24px;height:24px;border-radius:50%;border:2px solid var(--border);background:var(--surface);display:flex;align-items:center;justify-content:center;font-size:.62rem;}
.circuit .pt.fait .dot{border-color:#16a34a;background:#dcfce7;color:#16a34a;}
.circuit .pt.cur{color:var(--blue-700);}
.circuit .pt.cur .dot{border-color:var(--blue-600);background:var(--blue-600);color:#fff;box-shadow:0 0 0 4px var(--blue-100);}
.circuit .ln{flex:1;min-width:16px;height:2px;background:var(--border);margin-bottom:20px;}
.circuit .ln.fait{background:#16a34a;}
.trt-etat{display:flex;gap:10px;align-items:flex-start;background:var(--bg);border-radius:10px;padding:11px 14px;font-size:.84rem;color:var(--text);margin-bottom:12px;}
.trt-etat.attente{background:#fef3c7;color:#92400e;}
.trt-etat.ok{background:#dcfce7;color:#166534;}
.trt-btns{display:flex;gap:8px;flex-wrap:wrap;}
.btn-ok{background:#16a34a!important;border-color:#16a34a!important;color:#fff!important;}
.btn-warn{background:#fff7ed!important;border:1px solid #fed7aa!important;color:#c2410c!important;}

.tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:18px;border-bottom:2px solid var(--border);}
.tab{padding:9px 17px;border-radius:8px 8px 0 0;font-size:.82rem;font-weight:600;cursor:pointer;background:none;border:1.5px solid transparent;border-bottom:none;color:var(--text-muted);transition:all .14s;}
.tab:hover{color:var(--blue-600);background:var(--blue-50);}
.tab.on{color:var(--blue-600);background:var(--surface);border-color:var(--border);border-bottom:2px solid var(--surface);margin-bottom:-2px;}
.tp{display:none;}.tp.on{display:block;}

.doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:9px;}
.doc-card{background:var(--surface);border:1.5px solid var(--border);border-radius:10px;padding:11px 13px;display:flex;gap:9px;align-items:center;transition:box-shadow .14s;}
.doc-card:hover{box-shadow:0 3px 12px rgba(13,50,120,.08);}
.doc-ico{width:34px;height:34px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0;}

.suivi-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);}
.suivi-item:last-child{border-bottom:none;}
.suivi-dot{width:30px;height:30px;border-radius:50%;background:var(--blue-100);color:var(--blue-600);display:flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0;}

.fil{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;overflow:hidden;}
.fil-list{padding:6px 18px;max-height:520px;overflow-y:auto;}
.msg{display:flex;gap:12px;padding:13px 0;border-bottom:1px solid var(--border);}
.msg:last-child{border-bottom:none;}
.msg.focus{background:linear-gradient(90deg,var(--blue-50),transparent);border-radius:8px;}
.msg-av{width:34px;height:34px;border-radius:50%;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;flex-shrink:0;}
.msg-av.dg{background:#1e3a8a;color:#fff;}
.msg-hd{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:.8rem;}
.msg-hd strong{color:var(--text);}
.msg-hd time{font-size:.7rem;color:var(--text-muted);}
.msg-type{font-size:.64rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:2px 8px;border-radius:99px;}
.msg-txt{font-size:.84rem;line-height:1.6;color:var(--text);margin-top:4px;white-space:pre-line;word-break:break-word;}
.fil-form{border-top:1px solid var(--border);padding:12px 18px;background:var(--bg);display:flex;gap:10px;align-items:flex-end;}
.fil-form textarea{flex:1;resize:vertical;min-height:44px;}
.fil-vide{padding:30px;text-align:center;color:var(--text-muted);font-size:.84rem;}

.resp-picker{position:relative;}
.resp-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;}
.resp-chip{display:inline-flex;align-items:center;gap:6px;padding:4px 8px 4px 10px;background:var(--blue-50);border:1px solid var(--blue-200);border-radius:999px;font-size:.78rem;font-weight:600;color:var(--blue-900);}
.resp-chip button{border:0;background:transparent;color:var(--text-muted);cursor:pointer;padding:0 2px;line-height:1;font-size:.9rem;}
.resp-chip button:hover{color:#dc2626;}
.resp-toggle{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;text-align:left;cursor:pointer;background:#fff;}
.resp-toggle-label{color:var(--text-muted);font-weight:500;}
.resp-toggle.has-sel .resp-toggle-label{color:var(--text);font-weight:600;}
.resp-toggle .fa-chevron-down{transition:transform .15s;color:var(--text-muted);font-size:.75rem;}
.resp-toggle[aria-expanded="true"] .fa-chevron-down{transform:rotate(180deg);}
.resp-dropdown{margin-top:4px;border:1px solid var(--border);border-radius:10px;background:var(--surface,#fff);box-shadow:0 10px 28px rgba(15,23,42,.12);overflow:hidden;}
.resp-search{border:0;border-bottom:1px solid var(--border);border-radius:0;box-shadow:none;}
.resp-search:focus{outline:none;box-shadow:none;}
.resp-list{max-height:200px;overflow:auto;padding:4px 0;}
.resp-item{display:flex;align-items:flex-start;gap:10px;padding:8px 12px;margin:0;cursor:pointer;font-weight:400;border-bottom:1px solid var(--border);}
.resp-item:last-child{border-bottom:0;}
.resp-item[hidden],.resp-chips[hidden],.resp-dropdown[hidden]{display:none;}
.resp-item:hover{background:var(--blue-50);}
.resp-item input{margin-top:3px;flex-shrink:0;}
.resp-item-text{display:flex;flex-direction:column;gap:2px;min-width:0;}
.resp-name{font-size:.84rem;font-weight:600;color:var(--text);}
.resp-fonc{font-size:.72rem;color:var(--text-muted);}
.resp-empty{padding:12px;margin:0;font-size:.8rem;color:var(--text-muted);text-align:center;}
.hint{font-size:.72rem;color:var(--text-muted);margin-top:5px;}
.pj-list{border:1px solid var(--border);border-radius:10px;overflow:hidden;}
.pj-item{display:flex;align-items:center;gap:9px;padding:8px 12px;margin:0;border-bottom:1px solid var(--border);cursor:pointer;font-size:.82rem;font-weight:500;}
.pj-item:last-child{border-bottom:0;}
.pj-item:hover{background:var(--blue-50);}
.pj-item i{color:var(--text-muted);font-size:.75rem;}
.pj-nom{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pj-taille{font-size:.72rem;color:var(--text-muted);}
#pjTotal.trop{color:var(--danger);font-weight:700;}
.req{color:var(--danger);}
.row-2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px;}
@media(max-width:600px){.row-2{grid-template-columns:1fr;}}
</style>

<!-- Barre d'actions -->
<div style="margin-bottom:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
  <?php if ($peut_modifier): ?>
    <a href="form.php?id=<?= $cid ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Modifier</a>
  <?php endif; ?>
  <?php if ($peut_supprimer): ?>
    <button type="button" class="btn btn-sm"
            style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;"
            onclick="confirmerSuppr('<?= e(addslashes($cour['reference'])) ?>')">
      <i class="fa-solid fa-trash"></i> Supprimer
    </button>
  <?php endif; ?>
</div>

<!-- En-tête du courrier -->
<div class="ch">
  <div class="ch-top">
    <div class="ch-ico"><i class="fa-solid <?= $entrant ? 'fa-arrow-down-long' : 'fa-arrow-up-long' ?>"></i></div>
    <div style="flex:1;min-width:0;">
      <div class="ch-ref"><?= e($cour['reference']) ?></div>
      <div class="ch-titre"><?= e($cour['objet']) ?></div>
      <div class="ch-sub">
        Courrier <?= $entrant ? 'entrant' : 'sortant' ?>
        · <?= e($TYPES[$cour['type_courrier']] ?? $cour['type_courrier']) ?>
        · <i class="fa-solid <?= $cn['icon'] ?>"></i> <?= e($cn['label']) ?>
      </div>
      <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
        <span class="badge" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;">
          <i class="fa-solid <?= $st['icon'] ?>"></i> <?= $st['label'] ?>
        </span>
        <span class="badge" style="background:<?= $pr['bg'] ?>;color:<?= $pr['color'] ?>;">Priorité : <?= $pr['label'] ?></span>
        <?php if ($cour['confidentialite'] !== 'NORMAL'): ?>
          <span class="badge" style="background:<?= $cf['bg'] ?>;color:<?= $cf['color'] ?>;">
            <i class="fa-solid <?= $cf['icon'] ?>"></i> <?= $cf['label'] ?>
          </span>
        <?php endif; ?>
        <?php if ($en_retard): ?>
          <span class="badge" style="background:#fee2e2;color:#991b1b;"><i class="fa-solid fa-clock"></i> Échéance dépassée</span>
        <?php endif; ?>
        <?php if ($ma_part): ?>
          <span class="badge" style="background:#ede9fe;color:#6d28d9;"><i class="fa-solid fa-user-check"></i> <?= $est_pilote ? 'Vous êtes l\'agent pilote' : 'Vous êtes en copie' ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div style="text-align:right;flex-shrink:0;">
      <div style="font-size:.68rem;color:var(--text-muted);margin-bottom:3px;">Enregistré le</div>
      <div style="font-size:.85rem;font-weight:700;color:var(--blue-700);"><?= format_datetime($cour['cree_le']) ?></div>
      <?php if (!empty($cour['enregistreur_nom'])): ?>
        <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px;">par <?= e($cour['enregistreur_nom']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="info-grid">
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-user-tie"></i> <?= $entrant ? 'Expéditeur' : 'Destinataire' ?></div>
      <div class="ib-val"><?= $val($cour['correspondant_nom']) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-building"></i> Organisation</div>
      <div class="ib-val"><?= $val($cour['correspondant_organisation']) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-regular fa-envelope"></i> Email</div>
      <div class="ib-val">
        <?php if (!empty($cour['correspondant_email'])): ?>
          <a href="mailto:<?= e($cour['correspondant_email']) ?>" style="color:var(--blue-600);text-decoration:none;"><?= e($cour['correspondant_email']) ?></a>
        <?php else: ?><?= $val(null) ?><?php endif; ?>
      </div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-phone"></i> Téléphone</div>
      <div class="ib-val"><?= $val($cour['correspondant_telephone']) ?></div>
    </div>
    <?php if (!empty($cour['correspondant_adresse'])): ?>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-location-dot"></i> Adresse</div>
      <div class="ib-val"><?= e($cour['correspondant_adresse']) ?></div>
    </div>
    <?php endif; ?>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-regular fa-calendar"></i> Date du courrier</div>
      <div class="ib-val"><?= !empty($cour['date_courrier']) ? format_date($cour['date_courrier']) : $val(null) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-inbox"></i> <?= $entrant ? 'Date de réception' : 'Date d\'enregistrement' ?></div>
      <div class="ib-val"><?= !empty($cour['date_reception']) ? format_datetime($cour['date_reception']) : $val(null) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-hourglass-half"></i> <?= $entrant ? 'Échéance' : 'Date limite d\'envoi' ?></div>
      <div class="ib-val" <?= $en_retard ? 'style="color:var(--danger);"' : '' ?>>
        <?= !empty($cour['date_echeance']) ? format_date($cour['date_echeance']) : $val(null) ?>
      </div>
    </div>
    <?php if ($entrant || !empty($cour['service_nom'])): ?>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-sitemap"></i> Service concerné</div>
      <div class="ib-val"><?= $val($cour['service_nom']) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($entrant || $affectations): ?>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-user-gear"></i> Agent pilote</div>
      <div class="ib-val"><?= $val($nom_pilote) ?></div>
      <?php if (!empty($pilote['date_limite'])): ?>
        <div class="ib-sub" <?= $delai_depasse ? 'style="color:var(--danger);font-weight:700;"' : '' ?>>
          Délai : <?= format_date($pilote['date_limite']) ?><?= $delai_depasse ? ' — dépassé' : '' ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-users"></i> En copie</div>
      <div class="ib-val"><?= $copies ? e(implode(', ', array_column($copies, 'nom_complet'))) : $val(null) ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($cour['traiteur_nom'])): ?>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-circle-check"></i> <?= $entrant ? 'Traité par' : 'Validé par' ?></div>
      <div class="ib-val">
        <?= e($cour['traiteur_nom']) ?>
        <?php if (!empty($cour['date_traitement'])): ?>
          <div class="ib-sub">le <?= format_datetime($cour['date_traitement']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (trim((string)$cour['contenu']) !== ''): ?>
<div class="txt-bloc">
  <h4><i class="fa-solid fa-file-lines"></i> Contenu / résumé</h4>
  <div><?= nl2br(e($cour['contenu'])) ?></div>
</div>
<?php endif; ?>

<?php if (trim((string)$cour['instruction']) !== ''): ?>
<div class="instr-bloc">
  <h4><i class="fa-solid fa-pen-nib"></i> Instruction du DG</h4>
  <div><?= nl2br(e($cour['instruction'])) ?></div>
</div>
<?php endif; ?>

<!-- Traitement -->
<div class="trt">
  <div class="trt-hd"><i class="fa-solid fa-route" style="color:var(--blue-500);"></i> <?= $entrant ? 'Traitement' : 'Validation et envoi' ?></div>
  <div class="circuit">
    <?php $i = 0; foreach ($etapes_circuit as $k => $lbl):
      $cls = $idx_etape === false ? '' : ($i < $idx_etape ? 'fait' : ($i === $idx_etape ? 'cur' : '')); ?>
      <?php if ($i > 0): ?><div class="ln <?= $idx_etape !== false && $i <= $idx_etape ? 'fait' : '' ?>"></div><?php endif; ?>
      <div class="pt <?= $cls ?>"><span class="dot"><?= $cls === 'fait' ? '<i class="fa-solid fa-check"></i>' : $i + 1 ?></span><?= e($lbl) ?></div>
    <?php $i++; endforeach; ?>
  </div>
  <div class="trt-etat <?= $etat_classe ?>">
    <i class="fa-solid <?= $etat_classe === 'attente' ? 'fa-hourglass-half' : ($etat_classe === 'ok' ? 'fa-circle-check' : 'fa-circle-info') ?>" style="margin-top:3px;"></i>
    <span><?= $etat ?></span>
  </div>
  <div class="trt-btns">
    <?php if ($peut_affecter): ?>
      <button type="button" class="btn btn-primary btn-sm" onclick="ouvrirModal('modalAffecter')">
        <i class="fa-solid fa-share-from-square"></i> <?= $affectations ? 'Réaffecter' : 'Affecter' ?>
      </button>
    <?php endif; ?>
    <?php if ($peut_valider_s): ?>
      <button type="button" class="btn btn-sm btn-ok" onclick="ouvrirModal('modalValider')"><i class="fa-solid fa-check"></i> Valider pour envoi</button>
    <?php endif; ?>
    <?php if ($peut_renvoyer_s): ?>
      <button type="button" class="btn btn-sm btn-warn" onclick="ouvrirModal('modalRejeter')"><i class="fa-solid fa-rotate-left"></i> Renvoyer pour correction</button>
    <?php endif; ?>
    <?php if ($peut_resoumettre): ?>
      <?php if ($peut_modifier): ?>
        <a href="form.php?id=<?= $cid ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Corriger le courrier</a>
      <?php endif; ?>
      <button type="button" class="btn btn-sm btn-ok" onclick="ouvrirModal('modalResoumettre')"><i class="fa-solid fa-paper-plane"></i> Soumettre à nouveau au DG</button>
    <?php endif; ?>
    <?php if ($peut_expedier): ?>
      <?php if ($envoi_email): ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="ouvrirModal('modalEnvoyer')"><i class="fa-solid fa-paper-plane"></i> Envoyer au destinataire</button>
      <?php else: ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="ouvrirModal('modalExpedier')"><i class="fa-solid fa-truck-fast"></i> Marquer comme expédié</button>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($entrant && $est_pilote && $pilote_statut === 'A_TRAITER' && !in_array($cour['statut'], ['TRAITE','CLOTURE','ARCHIVE'], true)): ?>
      <form method="POST" action="actions.php" style="display:inline;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="commencer">
        <input type="hidden" name="courrier_id" value="<?= $cid ?>">
        <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-play"></i> Commencer le traitement</button>
      </form>
    <?php endif; ?>
    <?php if ($entrant && $est_pilote && in_array($pilote_statut, ['A_TRAITER','EN_COURS'], true) && !in_array($cour['statut'], ['TRAITE','CLOTURE','ARCHIVE'], true)): ?>
      <button type="button" class="btn btn-sm btn-ok" onclick="ouvrirModal('modalSoumettre')"><i class="fa-solid fa-paper-plane"></i> Soumettre au DG</button>
    <?php endif; ?>
    <?php if ($entrant && $est_dg && $pilote_statut === 'SOUMIS'): ?>
      <button type="button" class="btn btn-sm btn-ok" onclick="ouvrirModal('modalValider')"><i class="fa-solid fa-check"></i> Valider le traitement</button>
      <button type="button" class="btn btn-sm btn-warn" onclick="ouvrirModal('modalRejeter')"><i class="fa-solid fa-rotate-left"></i> Renvoyer au pilote</button>
    <?php endif; ?>
    <?php if ($peut_cloturer): ?>
      <button type="button" class="btn btn-secondary btn-sm" onclick="ouvrirModal('modalCloturer')"><i class="fa-solid fa-flag-checkered"></i> Clôturer</button>
    <?php endif; ?>
    <?php if ($peut_archiver): ?>
      <form method="POST" action="actions.php" style="display:inline;" onsubmit="return confirm('Archiver ce courrier ?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="archiver">
        <input type="hidden" name="courrier_id" value="<?= $cid ?>">
        <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-box-archive"></i> Archiver</button>
      </form>
    <?php endif; ?>
    <?php if ($peut_ecrire): ?>
      <button type="button" class="btn btn-secondary btn-sm" onclick="allerFil()"><i class="fa-regular fa-comments"></i> Écrire un message</button>
    <?php endif; ?>
  </div>
</div>

<!-- Onglets -->
<?php $onglet_fil = $peut_ecrire || !empty($echanges); $fil_actif = $onglet_fil && ($focus_fil || $peut_ecrire); ?>
<div class="tabs">
  <?php if ($onglet_fil): ?>
  <button class="tab <?= $fil_actif ? 'on' : '' ?>" id="tab-echanges" onclick="sw('echanges',this)">
    <i class="fa-regular fa-comments"></i> Échanges (<?= count($echanges) ?>)
  </button>
  <?php endif; ?>
  <button class="tab <?= $fil_actif ? '' : 'on' ?>" onclick="sw('docs',this)">
    <i class="fa-solid fa-paperclip"></i> Documents (<?= count($cour['documents']) ?>)
  </button>
  <button class="tab" onclick="sw('suivi',this)">
    <i class="fa-solid fa-clock-rotate-left"></i> Historique (<?= count($cour['suivi']) ?>)
  </button>
</div>

<?php if ($onglet_fil): ?>
<!-- Échanges -->
<div class="tp <?= $fil_actif ? 'on' : '' ?>" id="tp-echanges">
  <div class="fil">
    <?php if (empty($echanges)): ?>
      <div class="fil-vide"><i class="fa-regular fa-comments" style="font-size:1.6rem;display:block;margin-bottom:8px;opacity:.4;"></i>
        Aucun échange pour l'instant. <?= $entrant ? 'Le DG, l\'agent pilote et les agents en copie peuvent échanger ici.'
                                                  : 'Le DG et la personne qui a enregistré le courrier peuvent échanger ici.' ?></div>
    <?php else: ?>
    <div class="fil-list" id="filList">
      <?php
      $TYPES_MSG = [
          'AFFECTATION' => ['Affectation', '#ede9fe', '#6d28d9'],
          'SOUMISSION'  => ['Soumis au DG', '#dbeafe', '#1d4ed8'],
          'VALIDATION'  => ['Validé',       '#dcfce7', '#166534'],
          'REJET'       => ['Renvoyé',      '#ffedd5', '#c2410c'],
          'CLOTURE'     => ['Clôture',      '#f3f4f6', '#374151'],
      ];
      foreach ($echanges as $m):
        $tm = $TYPES_MSG[$m['type']] ?? null;
        $est_dg_msg = in_array($m['auteur_groupe'] ?? '', ['DG', 'SUPER_ADMIN'], true); ?>
      <div class="msg" id="m<?= (int)$m['id'] ?>">
        <div class="msg-av <?= $est_dg_msg ? 'dg' : '' ?>"><?= e($initiales($m['auteur_nom'] ?? '?')) ?></div>
        <div style="flex:1;min-width:0;">
          <div class="msg-hd">
            <strong><?= e($m['auteur_nom'] ?? 'Système') ?></strong>
            <?php if ($est_dg_msg): ?><span class="msg-type" style="background:#1e3a8a;color:#fff;">DG</span><?php endif; ?>
            <?php if ($tm): ?><span class="msg-type" style="background:<?= $tm[1] ?>;color:<?= $tm[2] ?>;"><?= $tm[0] ?></span><?php endif; ?>
            <time><?= format_datetime($m['cree_le']) ?></time>
          </div>
          <?php if (trim((string)$m['message']) !== ''): ?>
            <div class="msg-txt"><?= e($m['message']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($peut_ecrire): ?>
    <form method="POST" action="actions.php" class="fil-form" id="filForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="message">
      <input type="hidden" name="courrier_id" value="<?= $cid ?>">
      <textarea name="message" id="filMsg" class="form-control" rows="2" maxlength="3000" required
                placeholder="Écrire un message (question, précision, information)…"></textarea>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Documents -->
<div class="tp <?= $fil_actif ? '' : 'on' ?>" id="tp-docs">
  <?php if (empty($cour['documents'])): ?>
    <div style="text-align:center;padding:40px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);color:var(--text-muted);">
      <i class="fa-solid fa-folder-open" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.4;"></i>
      Aucun document joint.
      <?php if ($peut_modifier): ?>
        <div style="margin-top:10px;"><a href="form.php?id=<?= $cid ?>">Ajouter des documents</a></div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php foreach ($doc_types as $type => [$lbl, $ico, $bg, $co]):
      if (empty($docs_par_type[$type])) continue;
    ?>
    <div style="margin-bottom:18px;">
      <div style="font-size:.73rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px;">
        <i class="fa-solid <?= $ico ?>" style="color:<?= $co ?>;"></i> <?= $lbl ?>
      </div>
      <div class="doc-grid">
        <?php foreach ($docs_par_type[$type] as $doc):
          $ext = strtolower($doc['extension'] ?? '');
          $d_ico = in_array($ext, ['doc','docx'], true)                         ? 'fa-file-word'
                 : (in_array($ext, ['xls','xlsx'], true)                        ? 'fa-file-excel'
                 : (in_array($ext, ['jpg','jpeg','png','webp','tif','tiff'], true) ? 'fa-image' : 'fa-file-pdf'));
          $apercu = in_array($ext, ['pdf','jpg','jpeg','png','webp'], true);
        ?>
        <div class="doc-card">
          <div class="doc-ico" style="background:<?= $bg ?>;color:<?= $co ?>;"><i class="fa-solid <?= $d_ico ?>"></i></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.81rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                 title="<?= e($doc['nom_original']) ?>"><?= e($doc['nom_original']) ?></div>
            <div style="font-size:.68rem;color:var(--text-muted);">
              <?= e($doc['deposant'] ?? '') ?>
              <?= $doc['taille_fichier'] ? ' · ' . round($doc['taille_fichier'] / 1024, 1) . ' Ko' : '' ?>
              · <?= format_datetime($doc['date_upload']) ?>
            </div>
          </div>
          <?php if ($apercu): ?>
            <a href="download.php?id=<?= $doc['id'] ?>" target="_blank" rel="noopener"
               class="btn btn-secondary btn-sm btn-icon" title="Voir">
              <i class="fa-solid fa-eye"></i>
            </a>
          <?php endif; ?>
          <a href="download.php?id=<?= $doc['id'] ?>&amp;dl=1"
             class="btn btn-secondary btn-sm btn-icon" title="Télécharger">
            <i class="fa-solid fa-download"></i>
          </a>
          <?php if ($peut_modifier): ?>
          <form method="POST" action="actions.php" style="display:inline;"
                onsubmit="return confirm('Supprimer ce document ?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action"      value="supprimer_doc">
            <input type="hidden" name="doc_id"      value="<?= $doc['id'] ?>">
            <input type="hidden" name="courrier_id" value="<?= $cid ?>">
            <button type="submit" title="Supprimer"
                    style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;border-radius:6px;width:26px;height:26px;cursor:pointer;font-size:.72rem;padding:0;">
              <i class="fa-solid fa-trash"></i>
            </button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if ($peut_modifier): ?>
    <div style="margin-top:10px;">
      <a href="form.php?id=<?= $cid ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-plus"></i> Ajouter des documents
      </a>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Historique -->
<div class="tp" id="tp-suivi">
  <div class="card">
    <div class="card-body" style="padding:6px 20px;">
      <?php if (empty($cour['suivi'])): ?>
        <div style="padding:24px 0;text-align:center;color:var(--text-muted);font-size:.85rem;">Aucun événement enregistré.</div>
      <?php else: foreach ($cour['suivi'] as $s): ?>
        <div class="suivi-item">
          <div class="suivi-dot"><i class="fa-solid fa-circle-dot"></i></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.82rem;font-weight:700;color:var(--text);">
              <?= e(ucfirst(strtolower(str_replace('_', ' ', $s['action'])))) ?>
              <?php if (!empty($s['nouveau_statut']) && $s['nouveau_statut'] !== $s['ancien_statut']): ?>
                <span style="font-weight:500;color:var(--text-muted);">
                  → <?= e(courrier_statut_info($s['nouveau_statut'], $cour['sens'])['label']) ?>
                </span>
              <?php endif; ?>
            </div>
            <?php if (!empty($s['commentaire'])): ?>
              <div style="font-size:.8rem;color:var(--text);margin-top:2px;"><?= nl2br(e($s['commentaire'])) ?></div>
            <?php endif; ?>
            <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px;">
              <?= format_datetime($s['cree_le']) ?><?= !empty($s['auteur']) ? ' · ' . e($s['auteur']) : '' ?>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<?php if ($peut_affecter): ?>
<!-- Modal affectation (DG / super admin) -->
<div class="modal-overlay" id="modalAffecter">
  <div class="modal" style="max-width:560px;">
    <div class="modal-header">
      <i class="fa-solid fa-share-from-square" style="color:var(--blue-500);"></i>
      <h3><?= $affectations ? 'Réaffecter le courrier' : 'Affecter le courrier' ?></h3>
      <button class="modal-close" type="button" onclick="fermerModal('modalAffecter')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="actions.php" id="formAffecter">
      <?= csrf_field() ?>
      <input type="hidden" name="action"      value="affecter">
      <input type="hidden" name="courrier_id" value="<?= $cid ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Service concerné</label>
          <select name="service_id" id="affService" class="form-control">
            <option value="">— Aucun —</option>
            <?php foreach ($services as $sv): ?>
              <option value="<?= $sv['id'] ?>" data-resp="<?= (int)($sv['resp_user_id'] ?? 0) ?>" data-resp-nom="<?= e($sv['resp_nom'] ?? '') ?>"
                <?= (string)($cour['service_id'] ?? '') === (string)$sv['id'] ? 'selected' : '' ?>>
                <?= str_repeat('— ', (int)$sv['niveau']) . e($sv['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="hint" id="affRespHint">Issu de l'organigramme. Son responsable est proposé comme agent pilote.</div>
        </div>
        <div class="row-2">
          <div class="form-group">
            <label class="form-label">Agent pilote <span class="req">*</span></label>
            <select name="pilote" id="affPilote" class="form-control" required>
              <option value="">— Choisir —</option>
              <?php foreach ($utilisateurs as $u): ?>
                <option value="<?= $u['id'] ?>" <?= (int)($pilote['utilisateur_id'] ?? $cour['affecte_a'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                  <?= e($u['nom_complet']) ?> — <?= e($u['groupe']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="hint">Traite le courrier et le soumet au DG.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Délai de traitement <span class="req">*</span></label>
            <input type="date" name="date_limite" class="form-control" required min="<?= date('Y-m-d') ?>" value="<?= e($delai_defaut) ?>">
            <div class="hint">Date à laquelle le pilote doit avoir soumis.</div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Agents en copie <span style="font-weight:400;color:var(--text-muted);">(facultatif)</span></label>
          <div class="resp-picker" id="cpPicker">
            <div class="resp-chips" id="cpChips" hidden></div>
            <button type="button" class="resp-toggle form-control" id="cpToggle" aria-expanded="false" aria-controls="cpDropdown">
              <span class="resp-toggle-label">Choisir les agents en copie…</span>
              <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="resp-dropdown" id="cpDropdown" hidden>
              <input type="search" id="cpSearch" class="form-control resp-search" placeholder="Rechercher un nom, une fonction…" autocomplete="off" aria-label="Rechercher un agent">
              <div class="resp-list" role="group" aria-label="Agents en copie">
                <?php foreach ($utilisateurs as $u): $rid = 'cp-' . (int)$u['id']; ?>
                <label class="resp-item" for="<?= $rid ?>" data-id="<?= (int)$u['id'] ?>"
                       data-label="<?= e(mb_strtolower($u['nom_complet'] . ' ' . $u['groupe'])) ?>" data-name="<?= e($u['nom_complet']) ?>">
                  <input type="checkbox" name="copies[]" id="<?= $rid ?>" value="<?= (int)$u['id'] ?>" <?= in_array((int)$u['id'], $copies_ids, true) ? 'checked' : '' ?>>
                  <span class="resp-item-text">
                    <span class="resp-name"><?= e($u['nom_complet']) ?></span>
                    <span class="resp-fonc"><?= e($u['groupe']) ?></span>
                  </span>
                </label>
                <?php endforeach; ?>
              </div>
              <p class="resp-empty" id="cpEmpty" hidden>Aucune personne ne correspond à la recherche.</p>
            </div>
          </div>
          <div class="hint">Informés, ils peuvent consulter le courrier et écrire dans les échanges, sans délai ni soumission.</div>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Instruction</label>
          <textarea name="instruction" class="form-control" rows="3" placeholder="Consigne de traitement pour l'agent pilote…"><?= e($cour['instruction'] ?? '') ?></textarea>
          <div class="hint">Les agents désignés sont prévenus dans l'ENT et par e-mail.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalAffecter')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Enregistrer l'affectation</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($est_pilote): ?>
<!-- Modal soumission (agent pilote) -->
<div class="modal-overlay" id="modalSoumettre">
  <div class="modal" style="max-width:540px;">
    <div class="modal-header">
      <i class="fa-solid fa-paper-plane" style="color:#16a34a;"></i>
      <h3>Soumettre au DG</h3>
      <button class="modal-close" type="button" onclick="fermerModal('modalSoumettre')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="actions.php" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action"      value="soumettre">
      <input type="hidden" name="courrier_id" value="<?= $cid ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Compte rendu du traitement <span class="req">*</span></label>
          <textarea name="message" class="form-control" rows="5" required maxlength="5000"
                    placeholder="Ce qui a été fait, la réponse proposée, les points à signaler…"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Projet de réponse / pièces <span style="font-weight:400;color:var(--text-muted);">(facultatif)</span></label>
          <input type="file" name="reponses[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp">
          <div class="hint">PDF, Word, Excel ou images — 15 Mo max par fichier. Ils apparaîtront dans « Documents » (Réponse).</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalSoumettre')">Annuler</button>
        <button type="submit" class="btn btn-ok"><i class="fa-solid fa-paper-plane"></i> Soumettre</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($est_dg): ?>
<!-- Modals décision du DG -->
<div class="modal-overlay" id="modalValider">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <i class="fa-solid fa-check" style="color:#16a34a;"></i>
      <h3><?= $entrant ? 'Valider le traitement' : 'Valider le courrier pour envoi' ?></h3>
      <button class="modal-close" type="button" onclick="fermerModal('modalValider')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="valider">
      <input type="hidden" name="courrier_id" value="<?= $cid ?>">
      <div class="modal-body">
        <?php if ($entrant): ?>
          <p style="font-size:.84rem;margin:0 0 12px;">Le courrier passera au statut <strong>« Traité »</strong> et l'agent pilote sera prévenu.</p>
        <?php else: ?>
          <p style="font-size:.84rem;margin:0 0 12px;">Vous approuvez <?= count($cour['documents']) ? 'la lettre et ses ' . count($cour['documents']) . ' pièce(s) jointe(s)' : 'ce courrier' ?>. Le courrier passera au statut <strong>« Validé »</strong> et
            <?= $envoi_email ? 'pourra être envoyé par email à <strong>' . e($cour['correspondant_email'] ?: '—') . '</strong>.' : 'pourra être expédié.' ?>
            Il ne sera plus modifiable.</p>
        <?php endif; ?>
        <label class="form-label">Commentaire <span style="font-weight:400;color:var(--text-muted);">(facultatif)</span></label>
        <textarea name="message" class="form-control" rows="3" maxlength="2000" placeholder="Félicitations, remarques…"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalValider')">Annuler</button>
        <button type="submit" class="btn btn-ok"><i class="fa-solid fa-check"></i> Valider</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modalRejeter">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <i class="fa-solid fa-rotate-left" style="color:#c2410c;"></i>
      <h3><?= $entrant ? 'Renvoyer au pilote' : 'Renvoyer pour correction' ?></h3>
      <button class="modal-close" type="button" onclick="fermerModal('modalRejeter')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="rejeter">
      <input type="hidden" name="courrier_id" value="<?= $cid ?>">
      <div class="modal-body">
        <label class="form-label"><?= $entrant ? 'Ce qui doit être revu' : 'Corrections attendues' ?> <span class="req">*</span></label>
        <textarea name="motif" class="form-control" rows="4" required maxlength="2000"
                  placeholder="<?= $entrant ? 'Précisez les compléments attendus…' : 'Ex. : corriger la date, joindre l\'annexe signée…' ?>"></textarea>
        <div class="hint"><?= $entrant ? 'Le traitement repasse « en cours » et l\'agent pilote est prévenu.'
                                       : 'Le courrier repasse « À corriger » et ' . e($cour['enregistreur_nom'] ?: 'la personne qui l\'a enregistré') . ' est prévenu(e).' ?></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalRejeter')">Annuler</button>
        <button type="submit" class="btn btn-warn"><i class="fa-solid fa-rotate-left"></i> Renvoyer</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modalCloturer">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <i class="fa-solid fa-flag-checkered" style="color:var(--blue-500);"></i>
      <h3>Clôturer le courrier</h3>
      <button class="modal-close" type="button" onclick="fermerModal('modalCloturer')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="cloturer">
      <input type="hidden" name="courrier_id" value="<?= $cid ?>">
      <div class="modal-body">
        <p style="font-size:.84rem;margin:0 0 12px;">Le dossier est terminé : plus aucune action de traitement ne sera possible.</p>
        <label class="form-label">Commentaire <span style="font-weight:400;color:var(--text-muted);">(facultatif)</span></label>
        <textarea name="commentaire" class="form-control" rows="3" maxlength="2000" placeholder="Ex. : pour information, classé sans suite…"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalCloturer')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-flag-checkered"></i> Clôturer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($peut_expedier && $envoi_email):
  $exp_adresse = defined('MAIL_INFO_ADDRESS') && MAIL_INFO_ADDRESS !== '' ? MAIL_INFO_ADDRESS : (defined('MAIL_FROM') ? MAIL_FROM : '');
  $msg_defaut  = "Veuillez trouver ci-joint notre courrier référencé " . $cour['reference'] . ", relatif à : " . $cour['objet'] . ".\n\n"
               . "Nous restons à votre disposition pour tout complément d'information."; ?>
<!-- Modal envoi par email (sortant validé) -->
<div class="modal-overlay" id="modalEnvoyer">
  <div class="modal" style="max-width:600px;">
    <div class="modal-header">
      <i class="fa-solid fa-paper-plane" style="color:var(--blue-500);"></i>
      <h3>Envoyer au destinataire</h3>
      <button class="modal-close" type="button" onclick="fermerModal('modalEnvoyer')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="actions.php" id="formEnvoyer">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="envoyer">
      <input type="hidden" name="courrier_id" value="<?= $cid ?>">
      <div class="modal-body">
        <div class="row-2">
          <div class="form-group">
            <label class="form-label">De</label>
            <input type="text" class="form-control" value="<?= e($exp_adresse) ?>" disabled>
          </div>
          <div class="form-group">
            <label class="form-label">À <span class="req">*</span></label>
            <input type="email" name="destinataire" class="form-control" required value="<?= e($cour['correspondant_email'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Objet de l'email <span class="req">*</span></label>
          <input type="text" name="sujet" class="form-control" required maxlength="250" value="<?= e($cour['objet'] . ' — Réf. ' . $cour['reference']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Message <span class="req">*</span></label>
          <textarea name="message" class="form-control" rows="5" required maxlength="5000"><?= e($msg_defaut) ?></textarea>
          <div class="hint">Précédé de « Bonjour <?= e($cour['correspondant_nom'] ?: 'Madame, Monsieur') ?>, » et suivi de la référence du courrier et de la signature du Secrétariat de Direction.</div>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Pièces jointes</label>
          <?php if (empty($cour['documents'])): ?>
            <div class="trt-etat attente" style="margin:0;"><i class="fa-solid fa-triangle-exclamation" style="margin-top:3px;"></i>
              <span>Aucun document sur la fiche : l'email partira sans la lettre.</span></div>
          <?php else: ?>
            <div class="pj-list">
              <?php foreach ($cour['documents'] as $d): ?>
                <label class="pj-item">
                  <input type="checkbox" name="docs[]" value="<?= (int)$d['id'] ?>" data-taille="<?= (int)$d['taille_fichier'] ?>" checked>
                  <i class="fa-solid fa-paperclip"></i>
                  <span class="pj-nom"><?= e($d['nom_original']) ?></span>
                  <span class="pj-taille"><?= $d['taille_fichier'] ? round($d['taille_fichier'] / 1024, 1) . ' Ko' : '' ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="hint" id="pjTotal"></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalEnvoyer')">Annuler</button>
        <button type="submit" class="btn btn-primary" id="btnEnvoyer"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($peut_expedier && !$envoi_email): ?>
<!-- Modal expédition papier (sortant validé) -->
<div class="modal-overlay" id="modalExpedier">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <i class="fa-solid fa-truck-fast" style="color:var(--blue-500);"></i>
      <h3>Marquer comme expédié</h3>
      <button class="modal-close" type="button" onclick="fermerModal('modalExpedier')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="expedier">
      <input type="hidden" name="courrier_id" value="<?= $cid ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Date d'expédition <span class="req">*</span></label>
          <input type="date" name="date_expedition" class="form-control" required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Précision <span style="font-weight:400;color:var(--text-muted);">(facultatif)</span></label>
          <input type="text" name="commentaire" class="form-control" maxlength="250" placeholder="Ex. : remis en main propre, envoyé par la poste, coursier…">
          <div class="hint">Le courrier passera au statut « Envoyé ».</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalExpedier')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-truck-fast"></i> Confirmer l'expédition</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($peut_resoumettre): ?>
<!-- Modal nouvelle soumission après correction -->
<div class="modal-overlay" id="modalResoumettre">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <i class="fa-solid fa-paper-plane" style="color:#16a34a;"></i>
      <h3>Soumettre à nouveau au DG</h3>
      <button class="modal-close" type="button" onclick="fermerModal('modalResoumettre')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resoumettre">
      <input type="hidden" name="courrier_id" value="<?= $cid ?>">
      <div class="modal-body">
        <label class="form-label">Corrections apportées <span style="font-weight:400;color:var(--text-muted);">(facultatif)</span></label>
        <textarea name="message" class="form-control" rows="3" maxlength="2000" placeholder="Ex. : date corrigée, annexe signée ajoutée…"></textarea>
        <div class="hint">Le DG est prévenu pour valider à nouveau le courrier.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalResoumettre')">Annuler</button>
        <button type="submit" class="btn btn-ok"><i class="fa-solid fa-paper-plane"></i> Soumettre</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Form actions caché -->
<form method="POST" action="actions.php" id="formAct" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="action"      id="faAct">
  <input type="hidden" name="courrier_id" value="<?= $cid ?>">
</form>

<script>
function sw(id, btn) {
  document.querySelectorAll(".tp").forEach(p => p.classList.remove("on"));
  document.querySelectorAll(".tab").forEach(b => b.classList.remove("on"));
  document.getElementById("tp-" + id).classList.add("on");
  btn.classList.add("on");
}
function ouvrirModal(id) { document.getElementById(id).classList.add("open"); }
function fermerModal(id) { document.getElementById(id).classList.remove("open"); }
function confirmerSuppr(ref) {
  if (!confirm("Supprimer définitivement le courrier \"" + ref + "\" et toutes ses pièces ?")) return;
  document.getElementById("faAct").value = "supprimer";
  document.getElementById("formAct").submit();
}
function allerFil() {
  const t = document.getElementById("tab-echanges");
  if (!t) return;
  sw("echanges", t);
  t.scrollIntoView({behavior: "smooth", block: "start"});
  setTimeout(() => document.getElementById("filMsg")?.focus(), 350);
}
(function () {
  // Arrivée depuis une notification : ouvrir le fil sur le dernier message
  const liste = document.getElementById("filList");
  if (liste) liste.scrollTop = liste.scrollHeight;
  if (<?= $focus_fil ? 'true' : 'false' ?> && liste) {
    const dernier = liste.lastElementChild;
    if (dernier) { dernier.classList.add("focus"); document.getElementById("tab-echanges").scrollIntoView({behavior: "smooth", block: "start"}); }
  }

  // Envoi par email : poids total des pièces jointes (20 Mo max) et pas de double envoi
  const fEnv = document.getElementById("formEnvoyer");
  if (fEnv) {
    const total = document.getElementById("pjTotal"), btn = document.getElementById("btnEnvoyer");
    const majTotal = () => {
      if (!total) return;
      const cases = [...fEnv.querySelectorAll('input[name="docs[]"]:checked')];
      const mo = cases.reduce((s, c) => s + (+c.dataset.taille || 0), 0) / 1048576;
      total.textContent = cases.length + " pièce(s) jointe(s) — " + mo.toFixed(1).replace(".", ",") + " Mo" + (mo > 20 ? " : au-delà de 20 Mo, l'email sera refusé." : "");
      total.classList.toggle("trop", mo > 20);
      btn.disabled = mo > 20;
    };
    fEnv.addEventListener("change", majTotal);
    majTotal();
    fEnv.addEventListener("submit", () => { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Envoi en cours…'; });
  }

  // Affectation : responsable du service proposé comme pilote, pilote exclu des copies
  const svc = document.getElementById("affService"), pil = document.getElementById("affPilote");
  if (!svc || !pil) return;
  const hint = document.getElementById("affRespHint");
  const hintDefaut = hint.textContent;
  svc.addEventListener("change", () => {
    const o = svc.options[svc.selectedIndex];
    const resp = o ? o.dataset.resp : "";
    if (resp && resp !== "0" && pil.querySelector('option[value="' + resp + '"]')) {
      pil.value = resp;
      hint.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Responsable proposé : <strong>' + o.dataset.respNom.replace(/[<>&]/g, "") + '</strong> (modifiable).';
      majCopies();
    } else {
      hint.textContent = o && o.value ? "Aucun responsable renseigné pour ce service dans l'organigramme : choisissez le pilote." : hintDefaut;
    }
  });

  const picker = document.getElementById("cpPicker"), toggle = document.getElementById("cpToggle"),
        drop = document.getElementById("cpDropdown"), search = document.getElementById("cpSearch"),
        list = picker.querySelector(".resp-list"), empty = document.getElementById("cpEmpty"),
        chips = document.getElementById("cpChips"), label = toggle.querySelector(".resp-toggle-label");
  function setOpen(open) {
    drop.hidden = !open;
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) { search.value = ""; filtrer(""); setTimeout(() => search.focus(), 0); }
  }
  function filtrer(q) {
    let n = 0;
    list.querySelectorAll(".resp-item").forEach(it => {
      const ok = it.dataset.id !== pil.value && (!q || it.dataset.label.includes(q));
      it.hidden = !ok; if (ok) n++;
    });
    empty.hidden = n > 0;
  }
  function majCopies() {
    const cb = list.querySelector('.resp-item[data-id="' + pil.value + '"] input');
    if (cb) cb.checked = false;
    syncChips();
  }
  function syncChips() {
    const coches = [...list.querySelectorAll("input:checked")];
    chips.innerHTML = ""; chips.hidden = coches.length === 0;
    toggle.classList.toggle("has-sel", coches.length > 0);
    label.textContent = coches.length === 0 ? "Choisir les agents en copie…" : coches.length === 1 ? "1 agent en copie" : coches.length + " agents en copie";
    coches.forEach(cb => {
      const nom = cb.closest(".resp-item").dataset.name;
      const chip = document.createElement("span");
      chip.className = "resp-chip"; chip.textContent = nom + " ";
      const b = document.createElement("button");
      b.type = "button"; b.setAttribute("aria-label", "Retirer " + nom); b.innerHTML = "&times;";
      b.addEventListener("click", () => { cb.checked = false; syncChips(); });
      chip.appendChild(b); chips.appendChild(chip);
    });
  }
  toggle.addEventListener("click", e => { e.preventDefault(); setOpen(drop.hidden); });
  search.addEventListener("input", () => filtrer(search.value.trim().toLowerCase()));
  search.addEventListener("keydown", e => { if (e.key === "Enter") e.preventDefault(); });
  list.addEventListener("change", syncChips);
  pil.addEventListener("change", majCopies);
  document.addEventListener("click", e => { if (!picker.contains(e.target)) setOpen(false); });
  majCopies();
})();
</script>
<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
