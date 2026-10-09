<?php
// modules/courriers/form.php — Enregistrer / modifier un courrier (assistant par étapes)
// Le sens est fixé par le bouton d'origine (?sens=) à la création, et par la fiche en modification.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/courrier.php';

session_demarrer();

$cid  = (int)($_GET['id'] ?? 0);
$cour = $cid ? courrier_charger($cid) : null;
$mode = $cour ? 'modifier' : 'creer';

// Permission : CREER pour un nouveau, MODIFIER pour l'édition
exiger_permission('COURRIERS', $mode === 'creer' ? 'CREER' : 'MODIFIER');

if ($cid && (!$cour || !courrier_peut_voir($cour))) {
    set_flash('error', $cour ? 'Ce courrier est confidentiel : vous n\'y avez pas accès.' : 'Courrier introuvable.');
    header('Location: index.php');
    exit;
}
if ($cour && courrier_sortant_verrouille($cour)) {
    set_flash('error', 'Ce courrier sortant a été validé par le DG : il ne peut plus être modifié. Le DG peut le renvoyer en correction avant l\'envoi.');
    header('Location: voir.php?id=' . $cid);
    exit;
}

$sens    = $cour['sens'] ?? (($_GET['sens'] ?? '') === 'SORTANT' ? 'SORTANT' : 'ENTRANT');
$entrant = $sens === 'ENTRANT';

$TYPES = courrier_types();
$PRIOS = courrier_priorites();
$CONFS = courrier_confidentialites();

// Canaux proposés : Physique et Email (une ancienne valeur reste affichée en modification)
$TOUS_CANAUX  = courrier_canaux();
$CANAUX       = array_intersect_key($TOUS_CANAUX, array_flip(['PHYSIQUE', 'EMAIL']));
$canal_actuel = $cour['canal'] ?? 'PHYSIQUE';
if (!isset($CANAUX[$canal_actuel]) && isset($TOUS_CANAUX[$canal_actuel])) {
    $CANAUX[$canal_actuel] = $TOUS_CANAUX[$canal_actuel];
}
$CANAL_DESC = $entrant
    ? ['PHYSIQUE' => 'Papier reçu (poste, dépôt, coursier)', 'EMAIL' => 'Reçu par courriel']
    : ['PHYSIQUE' => 'Envoi papier (poste, dépôt, coursier)', 'EMAIL' => 'Envoi par courriel depuis info@'];

// Libellés propres au sens
$S = $entrant ? [
    'titre'       => 'Courrier entrant',
    'sous_titre'  => 'Reçu par le CRICT',
    'icone'       => 'fa-inbox',
    'couleur'     => '#16a34a',
    'fond'        => '#dcfce7',
    'corresp'     => 'Expéditeur',
    'corresp_sub' => 'Qui a écrit au CRICT ?',
    'canal_lbl'   => 'Canal de réception',
    'docs_titre'  => 'Scan du courrier',
    'docs_sub'    => 'Numérisez le courrier reçu et ses annexes',
] : [
    'titre'       => 'Courrier sortant',
    'sous_titre'  => 'Émis par le CRICT',
    'icone'       => 'fa-paper-plane',
    'couleur'     => '#1d4ed8',
    'fond'        => '#dbeafe',
    'corresp'     => 'Destinataire',
    'corresp_sub' => 'À qui le CRICT écrit-il ?',
    'canal_lbl'   => 'Canal d\'envoi',
    'docs_titre'  => 'Lettre & annexes',
    'docs_sub'    => 'Joignez la lettre signée et ses annexes',
];
$ETAPES = [
    1 => ['lbl' => 'Le courrier',          'ico' => 'fa-file-lines'],
    2 => ['lbl' => $S['corresp'],           'ico' => 'fa-user-tie'],
    3 => ['lbl' => 'Dates & priorité',      'ico' => 'fa-regular fa-calendar'],
    4 => ['lbl' => 'Documents',             'ico' => 'fa-paperclip'],
    5 => ['lbl' => 'Vérification',          'ico' => 'fa-clipboard-check'],
];

// Limites d'envoi de fichiers (réglages serveur)
$octets = function (string $v): int {
    $v = trim($v);
    if ($v === '') return 0;
    $n = (int)$v;
    return match (strtolower(substr($v, -1))) {
        'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
    };
};
$mo          = fn(int $o): string => rtrim(rtrim(number_format($o / 1048576, 1, ',', ''), '0'), ',') . ' Mo';
$max_fichier = min(COURRIER_MAX_SIZE, $octets((string)ini_get('upload_max_filesize')) ?: COURRIER_MAX_SIZE);
$max_total   = $octets((string)ini_get('post_max_size'));
$max_nb      = (int)ini_get('max_file_uploads') ?: 20;

$etape_depart = 1;

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    // Requête plus lourde que post_max_size : PHP vide $_POST et $_FILES
    set_flash('error', 'Les fichiers joints dépassent la taille totale autorisée par le serveur (' . ini_get('post_max_size')
        . '). Le courrier n\'a pas été enregistré : réessayez avec moins de fichiers ou des fichiers plus légers.');
    $etape_depart = 4;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    set_flash('error', 'Votre session a expiré. Veuillez ressaisir le courrier.');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $canal = clean($_POST['canal'] ?? 'PHYSIQUE');
    $type  = clean($_POST['type_courrier'] ?? 'LETTRE');
    $prio  = clean($_POST['priorite'] ?? 'NORMALE');
    $conf  = clean($_POST['confidentialite'] ?? 'NORMAL');

    $date_reception = $cour['date_reception'] ?? '';
    if ($entrant) {
        $date_reception = clean($_POST['date_reception'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $date_reception)) {
            $date_reception = str_replace('T', ' ', $date_reception) . ':00';
        }
    }

    $data = [
        'sens'                       => $sens,
        'canal'                      => isset($CANAUX[$canal]) ? $canal : 'PHYSIQUE',
        'type_courrier'              => isset($TYPES[$type]) ? $type : 'LETTRE',
        'objet'                      => clean($_POST['objet']                      ?? ''),
        'contenu'                    => trim($_POST['contenu']                     ?? ''),
        'correspondant_nom'          => clean($_POST['correspondant_nom']          ?? ''),
        'correspondant_organisation' => clean($_POST['correspondant_organisation'] ?? ''),
        'correspondant_email'        => strtolower(clean($_POST['correspondant_email'] ?? '')),
        'correspondant_telephone'    => clean($_POST['correspondant_telephone']    ?? ''),
        'correspondant_adresse'      => clean($_POST['correspondant_adresse']      ?? ''),
        'date_courrier'              => clean($_POST['date_courrier']              ?? ''),
        'date_reception'             => $date_reception,
        'date_echeance'              => clean($_POST['date_echeance']              ?? ''),
        'priorite'                   => isset($PRIOS[$prio]) ? $prio : 'NORMALE',
        'confidentialite'            => isset($CONFS[$conf]) ? $conf : 'NORMAL',
        // L'orientation (service, agent, instruction) ne se saisit plus ici : valeurs existantes conservées
        'service_id'                 => $cour['service_id']  ?? null,
        'affecte_a'                  => $cour['affecte_a']   ?? null,
        'instruction'                => $cour['instruction'] ?? null,
    ];

    // Erreurs : [étape, message]
    $erreurs = [];
    $date_ok = fn(string $d): bool => $d === '' || (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

    if ($data['objet'] === '')
        $erreurs[] = [1, 'L\'objet du courrier est obligatoire.'];
    if ($data['correspondant_nom'] === '' && $data['correspondant_organisation'] === '')
        $erreurs[] = [2, 'Indiquez au moins le nom ou l\'organisation ' . ($entrant ? 'de l\'expéditeur.' : 'du destinataire.')];
    if ($data['correspondant_email'] !== '' && !filter_var($data['correspondant_email'], FILTER_VALIDATE_EMAIL))
        $erreurs[] = [2, 'L\'adresse email ' . ($entrant ? 'de l\'expéditeur' : 'du destinataire') . ' est invalide.'];
    if (!$entrant && $data['canal'] === 'EMAIL' && $data['correspondant_email'] === '')
        $erreurs[] = [2, 'Pour un envoi par email, l\'adresse email du destinataire est obligatoire.'];
    if (!$date_ok($data['date_courrier']) || !$date_ok($data['date_echeance']))
        $erreurs[] = [3, 'Une des dates saisies est invalide.'];
    if ($entrant && $data['date_courrier'] !== '' && $data['date_reception'] !== ''
        && $data['date_courrier'] > substr($data['date_reception'], 0, 10))
        $erreurs[] = [3, 'La date du courrier ne peut pas être postérieure à sa date de réception.'];

    if (empty($erreurs)) {
        $res = $mode === 'creer'
            ? courrier_creer($data)
            : courrier_modifier($cid, $data);

        if ($res['succes']) {
            $id_cible = $mode === 'creer' ? $res['id'] : $cid;

            // Documents : chaque échec est signalé avec sa raison
            $docs_ok = 0;
            $echecs  = [];
            if (!empty($_FILES['documents']['name']) && is_array($_FILES['documents']['name'])) {
                foreach ($_FILES['documents']['name'] as $k => $nom) {
                    $err = (int)($_FILES['documents']['error'][$k] ?? UPLOAD_ERR_NO_FILE);
                    if ($err === UPLOAD_ERR_NO_FILE) continue;
                    $r = courrier_doc_ajouter($id_cible, [
                        'name'     => $nom,
                        'tmp_name' => $_FILES['documents']['tmp_name'][$k],
                        'error'    => $err,
                        'size'     => $_FILES['documents']['size'][$k],
                    ], $entrant ? 'SCAN' : 'PIECE_JOINTE');
                    if ($r['succes']) $docs_ok++;
                    else $echecs[] = '« ' . $nom . ' » : ' . $r['message'];
                }
            }

            $msg = $mode === 'creer'
                ? 'Courrier ' . ($entrant ? 'entrant' : 'sortant') . ' enregistré sous la référence ' . $res['reference'] . '.'
                : 'Courrier mis à jour.';
            if ($docs_ok) $msg .= ' ' . $docs_ok . ' document' . ($docs_ok > 1 ? 's joints.' : ' joint.');
            set_flash('success', $msg);
            foreach ($echecs as $ech) {
                set_flash('error', 'Document non enregistré — ' . $ech . ' Vous pouvez le rajouter via « Modifier ».');
            }

            // Accusé de réception à l'expéditeur (entrant, création, sur demande)
            if ($mode === 'creer' && $entrant && !empty($_POST['accuse_reception'])
                && filter_var($data['correspondant_email'], FILTER_VALIDATE_EMAIL)) {
                require_once __DIR__ . '/../../includes/mailer_courriers.php';
                $acc = courrier_envoyer_accuse_reception(array_merge($data, [
                    'id'             => $id_cible,
                    'reference'      => $res['reference'],
                    'date_reception' => $data['date_reception'] ?: date('Y-m-d H:i:s'),
                ]));
                if ($acc['succes']) {
                    courrier_suivi_ajouter($id_cible, 'ACCUSE_RECEPTION', 'Accusé de réception envoyé à ' . $data['correspondant_email']);
                    set_flash('success', 'Accusé de réception envoyé à ' . $data['correspondant_email'] . '.');
                } else {
                    set_flash('warn', 'L\'accusé de réception n\'a pas pu être envoyé : ' . $acc['message']);
                }
            }

            // Le DG est prévenu de tout nouveau courrier entrant à orienter
            if ($mode === 'creer' && $entrant) {
                require_once __DIR__ . '/../../includes/mailer_courriers.php';
                courrier_notifier_nouveaux_entrants([array_merge($data, ['id' => $id_cible, 'reference' => $res['reference']])]);
            }
            // Sortant : validation du DG obligatoire avant l'envoi
            if ($mode === 'creer' && !$entrant) {
                require_once __DIR__ . '/../../includes/mailer_courriers.php';
                courrier_notifier_sortant_a_valider(array_merge($data, ['id' => $id_cible, 'reference' => $res['reference']]));
                set_flash('info', 'Le DG est prévenu : le courrier pourra être envoyé au destinataire dès qu\'il l\'aura validé.');
            }
            if ($mode === 'modifier' && !$entrant && ($cour['statut'] ?? '') === 'EN_TRAITEMENT' && courrier_pilote($id_cible) === null) {
                set_flash('info', 'Une fois les corrections terminées, cliquez sur « Soumettre à nouveau au DG » sur la fiche du courrier.');
            }

            header('Location: voir.php?id=' . $id_cible);
            exit;
        }
        $erreurs[] = [1, $res['message'] ?? 'Erreur inconnue.'];
    }
    foreach ($erreurs as [, $e_msg]) set_flash('error', $e_msg);
    $etape_depart = min(array_column($erreurs, 0));
    // Conserver les valeurs saisies (les fichiers, eux, doivent être re-sélectionnés)
    $cour = array_merge($cour ?? [], $data);
    if (!empty($_FILES['documents']['name'][0])) {
        set_flash('info', 'Pensez à sélectionner à nouveau vos documents à l\'étape « Documents ».');
    }
}

$page_titre      = $mode === 'creer' ? 'Nouveau ' . strtolower($S['titre']) : 'Modifier le courrier';
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Courriers</a> · '
                 . ($mode === 'creer' ? e($S['titre']) : e($cour['reference'] ?? ''));
$module_actif    = 'COURRIERS';
include __DIR__ . '/../../views/layout/header.php';

$v = fn($k, $def = '') => e((string)($cour[$k] ?? $def));
$url_retour = $mode === 'modifier' ? 'voir.php?id=' . $cid : 'index.php';

$date_recep_val = !empty($cour['date_reception'])
    ? str_replace(' ', 'T', substr($cour['date_reception'], 0, 16))
    : date('Y-m-d\TH:i');

// Bloc « canal » : étape 1 pour l'entrant, étape 2 (avec le destinataire) pour le sortant
$bloc_canal = function () use ($CANAUX, $CANAL_DESC, $S, $cour) {
    $actuel = $cour['canal'] ?? 'PHYSIQUE';
    ?>
    <div class="form-group">
      <label class="form-label"><?= e($S['canal_lbl']) ?> <span class="req">*</span></label>
      <div class="opt-cards">
        <?php foreach ($CANAUX as $k => $cv): ?>
          <label class="opt-card <?= $actuel === $k ? 'on' : '' ?>">
            <input type="radio" name="canal" value="<?= e($k) ?>" <?= $actuel === $k ? 'checked' : '' ?>>
            <span class="opt-ico"><i class="fa-solid <?= e($cv['icon']) ?>"></i></span>
            <span><strong><?= e($cv['label']) ?></strong><small><?= e($CANAL_DESC[$k] ?? 'Ancienne valeur') ?></small></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
};
?>
<style>
.wz{--sens:<?= $S['couleur'] ?>;--sens-bg:<?= $S['fond'] ?>;max-width:920px;}
.wz-hero{display:flex;align-items:center;gap:14px;background:var(--surface);border:1.5px solid var(--border);border-left:5px solid var(--sens);border-radius:14px;padding:14px 18px;margin-bottom:16px;}
.wz-hero-ico{width:44px;height:44px;border-radius:12px;background:var(--sens-bg);color:var(--sens);display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;}
.wz-hero h2{font-size:1.02rem;font-weight:800;margin:0;}
.wz-hero small{font-size:.74rem;color:var(--text-muted);}
.wz-hero .wz-ref{margin-left:auto;font-family:'Courier New',monospace;font-weight:700;font-size:.82rem;color:var(--sens);background:var(--sens-bg);padding:5px 10px;border-radius:8px;}

.wz-steps{display:flex;list-style:none;margin:0 0 8px;padding:0;gap:6px;}
.wz-st{flex:1;display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;cursor:pointer;color:var(--text-muted);font-size:.78rem;font-weight:600;transition:background .15s;min-width:0;}
.wz-st:hover{background:var(--bg);}
.wz-num{width:26px;height:26px;border-radius:50%;border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.74rem;font-weight:800;flex-shrink:0;background:var(--surface);transition:all .15s;}
.wz-lbl{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.wz-st.on{color:var(--sens);}
.wz-st.on .wz-num{border-color:var(--sens);background:var(--sens);color:#fff;}
.wz-st.done .wz-num{border-color:var(--sens);color:var(--sens);background:var(--sens-bg);}
.wz-st.done .wz-num span{display:none;}
.wz-st.done .wz-num::after{content:"\f00c";font-family:"Font Awesome 6 Free";font-weight:900;font-size:.68rem;}
.wz-st.err .wz-num{border-color:var(--danger);color:var(--danger);background:#fee2e2;}
.wz-bar{height:5px;background:var(--border);border-radius:99px;overflow:hidden;margin-bottom:18px;}
.wz-bar-fill{height:100%;background:var(--sens);border-radius:99px;transition:width .25s ease;}
@media(max-width:720px){.wz-lbl{display:none;}.wz-st{justify-content:center;}}

.wz-panel{display:none;background:var(--surface);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:18px;}
.wz-panel.on{display:block;animation:wzIn .2s ease;}
@keyframes wzIn{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}
.wz-panel-hd{display:flex;align-items:center;gap:10px;padding:13px 20px;background:var(--bg);border-bottom:1px solid var(--border);}
.wz-panel-hd .ico{width:32px;height:32px;border-radius:8px;background:var(--sens-bg);color:var(--sens);display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;}
.wz-panel-hd h3{font-size:.9rem;font-weight:700;margin:0;}
.wz-panel-hd .sub{font-size:.72rem;color:var(--text-muted);}
.wz-panel-hd .cpt{margin-left:auto;font-size:.72rem;color:var(--text-muted);font-weight:600;}
.wz-panel-bd{padding:20px 22px;}
.fg2{display:grid;grid-template-columns:1fr 1fr;gap:0 16px;}
.fg3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 16px;}
@media(max-width:640px){.fg2,.fg3{grid-template-columns:1fr;}}
.req{color:var(--danger);}
.hint{font-size:.71rem;color:var(--text-muted);margin-top:4px;}

.form-control.invalid{border-color:var(--danger);box-shadow:0 0 0 3px rgba(220,38,38,.12);}
.fld-err{display:none;font-size:.73rem;color:var(--danger);margin-top:5px;font-weight:600;}
.fld-err.on{display:block;}
.step-err{display:none;align-items:center;gap:8px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:10px;padding:10px 14px;font-size:.8rem;font-weight:600;margin-bottom:16px;}
.step-err.on{display:flex;}

.opt-cards{display:flex;gap:10px;flex-wrap:wrap;}
.opt-card{position:relative;flex:1;min-width:200px;cursor:pointer;border:2px solid var(--border);border-radius:12px;padding:12px 14px;display:flex;align-items:center;gap:12px;transition:all .15s;}
.opt-card:hover{border-color:var(--sens);}
.opt-card input{position:absolute;opacity:0;pointer-events:none;}
.opt-card .opt-ico{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:var(--bg);color:var(--text-muted);flex-shrink:0;}
.opt-card strong{font-size:.85rem;display:block;}
.opt-card small{font-size:.71rem;color:var(--text-muted);}
.opt-card.on{border-color:var(--sens);background:var(--sens-bg);}
.opt-card.on .opt-ico{background:var(--sens);color:#fff;}

.pills{display:flex;gap:8px;flex-wrap:wrap;}
.pill{position:relative;cursor:pointer;border:2px solid var(--border);border-radius:99px;padding:6px 14px;font-size:.8rem;font-weight:700;color:var(--text-muted);transition:all .15s;user-select:none;}
.pill input{position:absolute;opacity:0;pointer-events:none;}
.pill:hover{border-color:var(--pc);}
.pill.on{background:var(--pb);color:var(--pc);border-color:var(--pc);}

.up-zone{display:block;border:2px dashed var(--blue-300);border-radius:12px;padding:22px 16px;background:var(--blue-50);cursor:pointer;text-align:center;transition:all .14s;}
.up-zone:hover,.up-zone.drag{background:var(--blue-100);border-color:var(--blue-500);}
.up-zone input[type=file]{display:none;}
.up-zone .up-ico{font-size:1.6rem;color:var(--blue-500);margin-bottom:6px;}
.up-zone .up-label{font-size:.88rem;font-weight:700;color:var(--blue-600);}
.up-zone .up-hint{font-size:.72rem;color:var(--text-muted);margin-top:4px;}
.up-list{list-style:none;margin:12px 0 0;padding:0;display:flex;flex-direction:column;gap:6px;}
.up-item{display:flex;align-items:center;gap:10px;border:1px solid var(--border);border-radius:10px;padding:8px 12px;font-size:.8rem;}
.up-item .fi{color:var(--blue-500);}
.up-item .nm{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;}
.up-item .sz{color:var(--text-muted);font-size:.72rem;white-space:nowrap;}
.up-item .rm{border:none;background:none;color:var(--text-muted);cursor:pointer;padding:2px 6px;border-radius:6px;}
.up-item .rm:hover{background:#fee2e2;color:var(--danger);}
.up-item.bad{border-color:#fecaca;background:#fff5f5;}
.up-item.bad .why{color:var(--danger);font-size:.72rem;font-weight:600;}

.recap-sec{border:1px solid var(--border);border-radius:12px;margin-bottom:12px;overflow:hidden;}
.recap-hd{display:flex;align-items:center;justify-content:space-between;padding:9px 14px;background:var(--bg);font-size:.78rem;font-weight:700;}
.recap-hd a{font-size:.74rem;font-weight:600;color:var(--blue-600);cursor:pointer;text-decoration:none;}
.recap-bd{padding:6px 14px;}
.recap-row{display:flex;gap:12px;padding:6px 0;border-bottom:1px dashed var(--border);font-size:.8rem;}
.recap-row:last-child{border-bottom:none;}
.recap-row .k{width:170px;flex-shrink:0;color:var(--text-muted);}
.recap-row .val{flex:1;font-weight:600;word-break:break-word;}
.recap-row .val.vide{color:var(--text-muted);font-weight:400;font-style:italic;}
.recap-warn{display:flex;gap:8px;align-items:flex-start;background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;font-size:.78rem;margin-bottom:12px;}
.acc-box{display:flex;gap:12px;align-items:flex-start;border:2px solid var(--border);border-radius:12px;padding:12px 14px;cursor:pointer;margin-top:4px;}
.acc-box.dis{opacity:.55;cursor:not-allowed;}
.acc-box input{margin-top:3px;width:16px;height:16px;accent-color:var(--sens);}
.acc-box strong{font-size:.84rem;display:block;}
.acc-box small{font-size:.72rem;color:var(--text-muted);}
.circuit-info{display:flex;gap:12px;align-items:flex-start;background:var(--sens-bg);border-radius:12px;padding:12px 14px;margin-top:4px;font-size:.8rem;color:var(--text);}
.circuit-info i{color:var(--sens);margin-top:3px;}
.circuit-info strong{display:block;font-size:.84rem;margin-bottom:2px;}

.wz-actions{position:sticky;bottom:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-top:1px solid var(--border);padding:13px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.wz-actions .sp{flex:1;}
.wz-btn-next,.wz-btn-save{background:var(--sens)!important;border-color:var(--sens)!important;}
</style>

<div style="margin-bottom:14px;">
  <a href="<?= $url_retour ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
</div>

<form method="POST" enctype="multipart/form-data" id="courForm" class="wz" novalidate data-start="<?= (int)$etape_depart ?>">
  <?= csrf_field() ?>

  <div class="wz-hero">
    <div class="wz-hero-ico"><i class="fa-solid <?= $S['icone'] ?>"></i></div>
    <div>
      <h2><?= $mode === 'creer' ? 'Enregistrer un ' . strtolower(e($S['titre'])) : 'Modifier le ' . strtolower(e($S['titre'])) ?></h2>
      <small><?= e($S['sous_titre']) ?> — la référence <?= $entrant ? 'CR-A' : 'CR-D' ?>-<?= date('Y') ?>-xxxx est attribuée automatiquement</small>
    </div>
    <?php if ($mode === 'modifier'): ?><span class="wz-ref"><?= e($cour['reference'] ?? '') ?></span><?php endif; ?>
  </div>

  <ol class="wz-steps" id="wzSteps">
    <?php foreach ($ETAPES as $n => $et): ?>
      <li class="wz-st" data-go="<?= $n ?>"><span class="wz-num"><span><?= $n ?></span></span><span class="wz-lbl"><?= e($et['lbl']) ?></span></li>
    <?php endforeach; ?>
  </ol>
  <div class="wz-bar"><div class="wz-bar-fill" id="wzBar"></div></div>

  <!-- ① Le courrier -->
  <section class="wz-panel" data-step="1">
    <div class="wz-panel-hd">
      <div class="ico"><i class="fa-solid fa-file-lines"></i></div>
      <div><h3>Le courrier</h3><div class="sub"><?= $entrant ? 'De quoi s\'agit-il et comment est-il arrivé ?' : 'De quoi s\'agit-il ?' ?></div></div>
      <span class="cpt">Étape 1 / 5</span>
    </div>
    <div class="wz-panel-bd">
      <div class="step-err"><i class="fa-solid fa-triangle-exclamation"></i><span></span></div>
      <?php if ($entrant) $bloc_canal(); ?>
      <div class="fg2">
        <div class="form-group">
          <label class="form-label">Type de courrier</label>
          <select name="type_courrier" class="form-control" data-recap="Type">
            <?php foreach ($TYPES as $k => $lbl): ?>
              <option value="<?= $k ?>" <?= ($cour['type_courrier'] ?? 'LETTRE') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Confidentialité</label>
          <select name="confidentialite" class="form-control" data-recap="Confidentialité">
            <?php foreach ($CONFS as $k => $cv): ?>
              <option value="<?= $k ?>" <?= ($cour['confidentialite'] ?? 'NORMAL') === $k ? 'selected' : '' ?>><?= e($cv['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Objet <span class="req">*</span></label>
        <input type="text" name="objet" id="f_objet" class="form-control" maxlength="255" data-recap="Objet"
               placeholder="<?= $entrant ? 'Ex : Demande de partenariat, Convocation à une réunion…' : 'Ex : Réponse à la demande de partenariat, Invitation…' ?>"
               value="<?= $v('objet') ?>">
        <div class="fld-err" data-for="f_objet"></div>
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Résumé</label>
        <textarea name="contenu" class="form-control" rows="4" data-recap="Résumé"
                  placeholder="<?= $entrant ? 'Ce que demande ou annonce le courrier, en quelques lignes…' : 'Contenu de la lettre en quelques lignes…' ?>"><?= e($cour['contenu'] ?? '') ?></textarea>
      </div>
    </div>
  </section>

  <!-- ② Expéditeur / Destinataire -->
  <section class="wz-panel" data-step="2">
    <div class="wz-panel-hd">
      <div class="ico"><i class="fa-solid fa-user-tie"></i></div>
      <div><h3><?= e($S['corresp']) ?></h3><div class="sub"><?= e($S['corresp_sub']) ?></div></div>
      <span class="cpt">Étape 2 / 5</span>
    </div>
    <div class="wz-panel-bd">
      <div class="step-err"><i class="fa-solid fa-triangle-exclamation"></i><span></span></div>
      <?php if (!$entrant) $bloc_canal(); ?>
      <div class="fg2">
        <div class="form-group">
          <label class="form-label">Nom / contact</label>
          <input type="text" name="correspondant_nom" id="f_nom" class="form-control" data-recap="Nom / contact"
                 placeholder="Nom de la personne" value="<?= $v('correspondant_nom') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Organisation</label>
          <input type="text" name="correspondant_organisation" id="f_org" class="form-control" data-recap="Organisation"
                 placeholder="Entreprise, ministère, institution…" value="<?= $v('correspondant_organisation') ?>">
        </div>
      </div>
      <div class="fld-err" data-for="f_nom" style="margin:-8px 0 12px;"></div>
      <div class="hint" style="margin:-6px 0 14px;"><i class="fa-solid fa-circle-info"></i> Renseignez au moins le nom ou l'organisation.</div>
      <div class="fg2">
        <div class="form-group">
          <label class="form-label">Email <span class="req" id="emailReq" style="display:none;">*</span></label>
          <input type="email" name="correspondant_email" id="f_email" class="form-control" data-recap="Email"
                 placeholder="adresse@exemple.com" value="<?= $v('correspondant_email') ?>">
          <div class="fld-err" data-for="f_email"></div>
          <?php if ($entrant): ?>
            <div class="hint">Permet d'envoyer un accusé de réception à l'expéditeur.</div>
          <?php else: ?>
            <div class="hint" id="emailHint">Obligatoire pour un envoi par email.</div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone</label>
          <input type="text" name="correspondant_telephone" class="form-control" data-recap="Téléphone"
                 placeholder="+224…" value="<?= $v('correspondant_telephone') ?>">
        </div>
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Adresse</label>
        <input type="text" name="correspondant_adresse" class="form-control" data-recap="Adresse"
               placeholder="Adresse postale" value="<?= $v('correspondant_adresse') ?>">
      </div>
    </div>
  </section>

  <!-- ③ Dates & priorité -->
  <section class="wz-panel" data-step="3">
    <div class="wz-panel-hd">
      <div class="ico"><i class="fa-regular fa-calendar"></i></div>
      <div><h3>Dates & priorité</h3><div class="sub"><?= $entrant ? 'Quand est-il arrivé et pour quand faut-il le traiter ?' : 'Date de la lettre et délai d\'envoi' ?></div></div>
      <span class="cpt">Étape 3 / 5</span>
    </div>
    <div class="wz-panel-bd">
      <div class="step-err"><i class="fa-solid fa-triangle-exclamation"></i><span></span></div>
      <div class="<?= $entrant ? 'fg3' : 'fg2' ?>">
        <div class="form-group">
          <label class="form-label">Date du courrier</label>
          <input type="date" name="date_courrier" id="f_dcour" class="form-control" data-recap="Date du courrier" value="<?= $v('date_courrier') ?>">
          <div class="hint">Date inscrite sur <?= $entrant ? 'le courrier' : 'la lettre' ?>.</div>
          <div class="fld-err" data-for="f_dcour"></div>
        </div>
        <?php if ($entrant): ?>
        <div class="form-group">
          <label class="form-label">Date de réception</label>
          <input type="datetime-local" name="date_reception" id="f_drecep" class="form-control" data-recap="Date de réception" value="<?= e($date_recep_val) ?>">
          <div class="hint">Arrivée au CRICT (par défaut : maintenant).</div>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label class="form-label"><?= $entrant ? 'Échéance de traitement' : 'Date limite d\'envoi' ?></label>
          <input type="date" name="date_echeance" id="f_dech" class="form-control" data-recap="<?= $entrant ? 'Échéance' : 'Date limite d\'envoi' ?>" value="<?= $v('date_echeance') ?>">
          <div class="hint"><?= $entrant ? 'Date à laquelle une réponse est attendue.' : 'Facultatif.' ?></div>
          <div class="fld-err" data-for="f_dech"></div>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Priorité</label>
        <div class="pills" data-recap-group="Priorité">
          <?php foreach ($PRIOS as $k => $pv): $on = ($cour['priorite'] ?? 'NORMALE') === $k; ?>
            <label class="pill <?= $on ? 'on' : '' ?>" style="--pb:<?= $pv['bg'] ?>;--pc:<?= $pv['color'] ?>;">
              <input type="radio" name="priorite" value="<?= $k ?>" <?= $on ? 'checked' : '' ?>><?= e($pv['label']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ④ Documents -->
  <section class="wz-panel" data-step="4">
    <div class="wz-panel-hd">
      <div class="ico"><i class="fa-solid fa-paperclip"></i></div>
      <div><h3><?= e($S['docs_titre']) ?></h3><div class="sub"><?= e($S['docs_sub']) ?></div></div>
      <span class="cpt">Étape 4 / 5</span>
    </div>
    <div class="wz-panel-bd">
      <div class="step-err"><i class="fa-solid fa-triangle-exclamation"></i><span></span></div>
      <label class="up-zone" id="upZone" for="docsInput">
        <input type="file" name="documents[]" id="docsInput" multiple
               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.tif,.tiff">
        <div class="up-ico"><i class="fa-solid fa-cloud-arrow-up"></i></div>
        <div class="up-label">Cliquez ou glissez vos fichiers ici</div>
        <div class="up-hint">PDF, Word, Excel, JPEG, PNG, WebP, TIFF — <?= $mo($max_fichier) ?> max par fichier<?= $max_total ? ', ' . $mo($max_total) . ' au total' : '' ?></div>
      </label>
      <ul class="up-list" id="upList"></ul>
      <?php if ($mode === 'modifier'): ?>
        <div class="hint" style="margin-top:10px;">
          <i class="fa-solid fa-circle-info"></i> Les documents déjà enregistrés (<?= count($cour['documents'] ?? []) ?>) restent sur la fiche du courrier ; ceux ajoutés ici s'y ajouteront.
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ⑤ Vérification -->
  <section class="wz-panel" data-step="5">
    <div class="wz-panel-hd">
      <div class="ico"><i class="fa-solid fa-clipboard-check"></i></div>
      <div><h3>Vérification</h3><div class="sub">Relisez avant d'enregistrer</div></div>
      <span class="cpt">Étape 5 / 5</span>
    </div>
    <div class="wz-panel-bd">
      <div id="recap"></div>
      <?php if ($mode === 'creer' && $entrant): ?>
        <label class="acc-box" id="accBox">
          <input type="checkbox" name="accuse_reception" value="1" id="accChk">
          <span>
            <strong>Envoyer un accusé de réception à l'expéditeur</strong>
            <small id="accTxt">Un email confirmant la bonne réception, avec la référence du courrier, sera envoyé depuis l'adresse institutionnelle.</small>
          </span>
        </label>
      <?php elseif ($mode === 'creer'): ?>
        <div class="circuit-info">
          <i class="fa-solid fa-route"></i>
          <span>
            <strong>Validation du DG avant l'envoi</strong>
            Le DG est prévenu dès l'enregistrement. Une fois le courrier validé, il pourra être envoyé au destinataire depuis sa fiche
            (par email depuis l'adresse institutionnelle, ou marqué « expédié » pour un envoi papier).
          </span>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <div class="wz-actions">
    <a href="<?= $url_retour ?>" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Annuler</a>
    <span class="sp"></span>
    <button type="button" class="btn btn-secondary" id="btnPrev"><i class="fa-solid fa-arrow-left"></i> Précédent</button>
    <button type="button" class="btn btn-primary wz-btn-next" id="btnNext">Suivant <i class="fa-solid fa-arrow-right"></i></button>
    <button type="submit" class="btn btn-primary wz-btn-save" id="btnSave">
      <i class="fa-solid fa-floppy-disk"></i> <?= $mode === 'creer' ? 'Enregistrer le courrier' : 'Enregistrer les modifications' ?>
    </button>
  </div>
</form>

<script>
(function () {
  const form    = document.getElementById("courForm");
  const ENTRANT = <?= $entrant ? 'true' : 'false' ?>;
  const EDITION = <?= $mode === 'modifier' ? 'true' : 'false' ?>;
  const MAX_F   = <?= (int)$max_fichier ?>;
  const MAX_T   = <?= (int)$max_total ?>;
  const MAX_NB  = <?= (int)$max_nb ?>;
  const EXTS    = ["pdf","doc","docx","xls","xlsx","jpg","jpeg","png","webp","tif","tiff"];
  const NB      = 5;
  const panels  = form.querySelectorAll(".wz-panel");
  const steps   = form.querySelectorAll(".wz-st");
  const btnPrev = document.getElementById("btnPrev");
  const btnNext = document.getElementById("btnNext");
  const btnSave = document.getElementById("btnSave");
  let cur = 1, maxAtteint = 1;

  const $ = (id) => document.getElementById(id);
  const val = (name) => { const el = form.elements[name]; return el ? String(el.value || "").trim() : ""; };
  const emailOk = (s) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s);
  const esc = (s) => String(s).replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
  const taille = (o) => o >= 1048576 ? (o / 1048576).toFixed(1).replace(".", ",") + " Mo" : Math.max(1, Math.round(o / 1024)) + " Ko";

  // ── Cartes et pastilles radio ──
  function lierRadios(sel) {
    form.querySelectorAll(sel).forEach(lbl => {
      const inp = lbl.querySelector("input");
      inp.addEventListener("change", () => {
        form.querySelectorAll(sel + " input[name='" + inp.name + "']").forEach(i => i.closest(sel).classList.toggle("on", i.checked));
        majEmailRequis();
      });
    });
  }
  lierRadios(".opt-card");
  lierRadios(".pill");

  function canal() { const c = form.querySelector("input[name='canal']:checked"); return c ? c.value : "PHYSIQUE"; }
  function majEmailRequis() {
    if (ENTRANT) return;
    $("emailReq").style.display = canal() === "EMAIL" ? "" : "none";
  }
  majEmailRequis();

  // ── Erreurs ──
  function nettoyer(panel) {
    panel.querySelectorAll(".invalid").forEach(el => el.classList.remove("invalid"));
    panel.querySelectorAll(".fld-err").forEach(el => { el.classList.remove("on"); el.textContent = ""; });
    panel.querySelector(".step-err")?.classList.remove("on");
  }
  function erreurChamp(id, msg, liés) {
    [id].concat(liés || []).forEach(i => $(i)?.classList.add("invalid"));
    const box = form.querySelector(".fld-err[data-for='" + id + "']");
    if (box) { box.textContent = msg; box.classList.add("on"); }
  }
  function erreurEtape(panel, msg) {
    const b = panel.querySelector(".step-err");
    if (b) { b.querySelector("span").textContent = msg; b.classList.add("on"); }
  }

  // ── Validation par étape ──
  function valider(n) {
    const panel = form.querySelector(".wz-panel[data-step='" + n + "']");
    nettoyer(panel);
    let ok = true;
    if (n === 1) {
      if (!val("objet")) { erreurChamp("f_objet", "L'objet du courrier est obligatoire."); ok = false; }
    }
    if (n === 2) {
      if (!val("correspondant_nom") && !val("correspondant_organisation")) {
        erreurChamp("f_nom", "Indiquez au moins le nom ou l'organisation " + (ENTRANT ? "de l'expéditeur." : "du destinataire."), ["f_org"]);
        ok = false;
      }
      const em = val("correspondant_email");
      if (em && !emailOk(em)) { erreurChamp("f_email", "Cette adresse email n'est pas valide."); ok = false; }
      else if (!ENTRANT && canal() === "EMAIL" && !em) { erreurChamp("f_email", "Obligatoire : le courrier sera envoyé à cette adresse."); ok = false; }
    }
    if (n === 3) {
      const dc = val("date_courrier"), de = val("date_echeance");
      if (ENTRANT) {
        const dr = val("date_reception").slice(0, 10);
        if (dc && dr && dc > dr) { erreurChamp("f_dcour", "La date du courrier ne peut pas être postérieure à sa réception."); ok = false; }
        if (de && dr && de < dr) { erreurChamp("f_dech", "L'échéance ne peut pas précéder la date de réception."); ok = false; }
      } else if (dc && de && de < dc) {
        erreurChamp("f_dech", "La date limite ne peut pas précéder la date de la lettre."); ok = false;
      }
    }
    if (n === 4) {
      const pb = fichiers.filter(f => f.pb);
      let total = 0; fichiers.forEach(f => total += f.file.size);
      if (pb.length) { erreurEtape(panel, "Retirez les fichiers signalés en rouge avant de continuer."); ok = false; }
      else if (fichiers.length > MAX_NB) { erreurEtape(panel, "Le serveur accepte au maximum " + MAX_NB + " fichiers par envoi."); ok = false; }
      else if (MAX_T && total > MAX_T * 0.95) { erreurEtape(panel, "Le total des fichiers (" + taille(total) + ") dépasse la limite du serveur (" + taille(MAX_T) + ")."); ok = false; }
    }
    steps[n - 1].classList.toggle("err", !ok);
    if (!ok) {
      const premier = panel.querySelector(".invalid, .step-err.on");
      premier?.scrollIntoView({behavior: "smooth", block: "center"});
      if (premier && premier.focus && premier.tagName !== "DIV") premier.focus();
    }
    return ok;
  }

  // ── Navigation ──
  function aller(n) {
    cur = n; maxAtteint = Math.max(maxAtteint, n);
    panels.forEach(p => p.classList.toggle("on", +p.dataset.step === n));
    steps.forEach((s, i) => {
      s.classList.toggle("on", i + 1 === n);
      s.classList.toggle("done", i + 1 < n || (i + 1 <= maxAtteint && i + 1 !== n && !s.classList.contains("err")));
    });
    $("wzBar").style.width = ((n - 1) / (NB - 1) * 100) + "%";
    btnPrev.style.visibility = n === 1 ? "hidden" : "visible";
    btnNext.style.display = n === NB ? "none" : "";
    btnSave.style.display = (n === NB || EDITION) ? "" : "none";
    if (n === NB) construireRecap();
    window.scrollTo({top: 0, behavior: "smooth"});
  }
  btnNext.addEventListener("click", () => { if (valider(cur)) aller(cur + 1); });
  btnPrev.addEventListener("click", () => aller(Math.max(1, cur - 1)));
  steps.forEach(s => s.addEventListener("click", () => {
    const cible = +s.dataset.go;
    if (cible <= cur) { aller(cible); return; }
    for (let i = cur; i < cible; i++) { if (!valider(i)) { aller(i); return; } }
    aller(cible);
  }));
  // Entrée dans un champ = étape suivante (pas d'envoi prématuré)
  form.addEventListener("keydown", (ev) => {
    if (ev.key === "Enter" && ev.target.tagName === "INPUT" && cur < NB) { ev.preventDefault(); btnNext.click(); }
  });
  form.addEventListener("submit", (ev) => {
    for (let i = 1; i < NB; i++) { if (!valider(i)) { ev.preventDefault(); aller(i); valider(i); return; } }
    btnSave.disabled = true;
    btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement…';
  });

  // ── Documents (ajout cumulatif, retrait, glisser-déposer) ──
  const input = $("docsInput"), zone = $("upZone"), liste = $("upList");
  let fichiers = [];
  function analyser(file) {
    const ext = (file.name.split(".").pop() || "").toLowerCase();
    if (!EXTS.includes(ext)) return "type de fichier non accepté";
    if (file.size > MAX_F) return "trop volumineux (max " + taille(MAX_F) + ")";
    if (file.size === 0) return "fichier vide";
    return "";
  }
  function synchroniser() {
    try {
      const dt = new DataTransfer();
      fichiers.filter(f => !f.pb).forEach(f => dt.items.add(f.file));
      input.files = dt.files;
    } catch (e) { /* navigateur ancien : la sélection native est conservée */ }
    liste.innerHTML = fichiers.map((f, i) =>
      '<li class="up-item' + (f.pb ? ' bad' : '') + '">' +
        '<i class="fa-solid ' + (f.pb ? 'fa-circle-exclamation' : 'fa-file') + ' fi"></i>' +
        '<span class="nm" title="' + esc(f.file.name) + '">' + esc(f.file.name) + '</span>' +
        (f.pb ? '<span class="why">' + esc(f.pb) + '</span>' : '<span class="sz">' + taille(f.file.size) + '</span>') +
        '<button type="button" class="rm" data-i="' + i + '" title="Retirer"><i class="fa-solid fa-xmark"></i></button>' +
      '</li>').join("");
    if (cur === 4) nettoyer(form.querySelector(".wz-panel[data-step='4']"));
  }
  function ajouter(list) {
    Array.from(list).forEach(file => {
      if (!fichiers.some(f => f.file.name === file.name && f.file.size === file.size)) fichiers.push({file, pb: analyser(file)});
    });
    synchroniser();
  }
  input.addEventListener("change", () => { const sel = Array.from(input.files); fichiers = fichiers.filter(f => !sel.includes(f.file)); ajouter(sel); });
  liste.addEventListener("click", (ev) => {
    const b = ev.target.closest(".rm"); if (!b) return;
    fichiers.splice(+b.dataset.i, 1); synchroniser();
  });
  ["dragenter", "dragover"].forEach(t => zone.addEventListener(t, (ev) => { ev.preventDefault(); zone.classList.add("drag"); }));
  ["dragleave", "drop"].forEach(t => zone.addEventListener(t, (ev) => { ev.preventDefault(); zone.classList.remove("drag"); }));
  zone.addEventListener("drop", (ev) => ajouter(ev.dataTransfer.files));

  // ── Récapitulatif ──
  function ligne(k, v) {
    return '<div class="recap-row"><span class="k">' + esc(k) + '</span><span class="val' + (v ? '' : ' vide') + '">' + (v ? esc(v) : 'Non renseigné') + '</span></div>';
  }
  function texteChamp(el) {
    if (el.tagName === "SELECT") return el.options[el.selectedIndex]?.text || "";
    if (el.type === "date" && el.value) return el.value.split("-").reverse().join("/");
    if (el.type === "datetime-local" && el.value) { const [d, h] = el.value.split("T"); return d.split("-").reverse().join("/") + " à " + h; }
    return String(el.value || "").trim();
  }
  function section(n, titre, extra) {
    const panel = form.querySelector(".wz-panel[data-step='" + n + "']");
    let rows = extra || "";
    panel.querySelectorAll("[data-recap]").forEach(el => rows += ligne(el.dataset.recap, texteChamp(el)));
    panel.querySelectorAll("[data-recap-group]").forEach(g => {
      const c = g.querySelector("input:checked");
      rows += ligne(g.dataset.recapGroup, c ? c.closest("label").textContent.trim() : "");
    });
    return '<div class="recap-sec"><div class="recap-hd"><span>' + esc(titre) + '</span><a data-go="' + n + '"><i class="fa-solid fa-pen"></i> Modifier</a></div><div class="recap-bd">' + rows + '</div></div>';
  }
  function construireRecap() {
    const c = form.querySelector("input[name='canal']:checked");
    const canalTxt = c ? c.closest("label").querySelector("strong").textContent : "";
    const lc = ligne(<?= json_encode($S['canal_lbl']) ?>, canalTxt);
    const ok = fichiers.filter(f => !f.pb);
    const docs = ok.length ? ok.map(f => ligne("Fichier", f.file.name + " (" + taille(f.file.size) + ")")).join("") : ligne("Fichiers", "");
    let html = "";
    if (!ok.length) html += '<div class="recap-warn"><i class="fa-solid fa-triangle-exclamation"></i><span>Aucun document joint. ' + (ENTRANT ? "Pensez à joindre le scan du courrier reçu." : "Pensez à joindre la lettre signée.") + '</span></div>';
    html += section(1, "Le courrier", ENTRANT ? lc : "");
    html += section(2, <?= json_encode($S['corresp']) ?>, ENTRANT ? "" : lc);
    html += section(3, "Dates & priorité");
    html += '<div class="recap-sec"><div class="recap-hd"><span>Documents</span><a data-go="4"><i class="fa-solid fa-pen"></i> Modifier</a></div><div class="recap-bd">' + docs + '</div></div>';
    $("recap").innerHTML = html;
    $("recap").querySelectorAll("a[data-go]").forEach(a => a.addEventListener("click", () => aller(+a.dataset.go)));

    const box = $("accBox");
    if (box) {
      const em = val("correspondant_email"), dispo = emailOk(em);
      const chk = $("accChk");
      box.classList.toggle("dis", !dispo);
      chk.disabled = !dispo;
      if (!dispo) chk.checked = false;
      $("accTxt").textContent = dispo
        ? "Un email confirmant la bonne réception, avec la référence du courrier, sera envoyé à " + em + "."
        : "Indisponible : aucune adresse email n'a été saisie pour l'expéditeur (étape 2).";
    }
  }

  aller(Math.min(NB, Math.max(1, +form.dataset.start || 1)));
  if (+form.dataset.start > 1) { for (let i = 1; i < +form.dataset.start; i++) steps[i - 1].classList.add("done"); valider(+form.dataset.start); }
})();
</script>
<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
