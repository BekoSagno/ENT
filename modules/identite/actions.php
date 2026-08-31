<?php
// modules/identite/actions.php — Actions POST (droit MODIFIER, délégable par le Super Admin)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/identite.php';
require_once __DIR__ . '/../../models/auto_anaq.php';

session_demarrer();
exiger_connexion();

$action  = clean($_POST['action'] ?? '');
$is_ajax = ($action === 'sauver_auto_point_ajax');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Session expirée. Reconnectez-vous.']);
        exit;
    }
    set_flash('error', 'Requête invalide.');
    redirect('/modules/identite/index.php');
}

exiger_permission('IDENTITE_CRICT', 'MODIFIER');

$champ_id = (int)($_POST['champ_id'] ?? 1);

switch ($action) {

    case 'sauver_identification':
        $cles = ['denomination', 'domaines', 'axes_recherche', 'adresse', 'site_web', 'contact'];
        $data = [];
        foreach ($cles as $cle) {
            $data[$cle] = $_POST[$cle] ?? '';
        }
        if (identite_identification_sauver($data)) {
            set_flash('success', 'Identification institutionnelle mise à jour.');
        } else {
            set_flash('error', 'Erreur lors de l\'enregistrement.');
        }
        redirect('/modules/identite/identification.php');
        break;

    case 'sauver_observations':
        $ok = identite_observations_sauver(
            $champ_id,
            $_POST['forces'] ?? '',
            $_POST['faiblesses'] ?? '',
            $_POST['niveau_global'] ?? ''
        );
        if ($ok) {
            log_activite(user_id(), 'IDENTITE_CRICT', 'MODIFICATION', 'Synthèse champ ' . $champ_id);
            set_flash('success', 'Synthèse institutionnelle enregistrée.');
        } else {
            set_flash('error', 'Erreur lors de l\'enregistrement.');
        }
        redirect('/modules/identite/champ.php?id=' . $champ_id . '&edit=1');
        break;

    case 'sauver_critere':
        $critere_id = (int)($_POST['critere_id'] ?? 0);
        $reponse    = $_POST['reponse_officielle'] ?? '';
        $preuves    = $_POST['preuves_disponibles'] ?? '';
        $score_raw  = $_POST['score'] ?? '';
        $score      = ($score_raw !== '' && $score_raw !== null) ? (int)$score_raw : null;
        $val_raw    = $_POST['valeur_numerique'] ?? '';
        $val_num    = ($val_raw !== '' && $val_raw !== null) ? (float)$val_raw : null;

        if ($critere_id <= 0) {
            set_flash('error', 'Critère invalide.');
            break;
        }

        if (!identite_contenu_sauver($critere_id, $reponse, $score, $preuves, $val_num)) {
            set_flash('error', 'Erreur lors de l\'enregistrement du critère.');
            redirect('/modules/identite/champ.php?id=' . $champ_id . '&edit=1#critere-' . $critere_id);
            break;
        }

        if (!empty($_FILES['piece']['name'][0])) {
            $erreurs_up = [];
            $nb_files   = count($_FILES['piece']['name']);
            for ($i = 0; $i < $nb_files; $i++) {
                if (($_FILES['piece']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                $f = [
                    'name'     => $_FILES['piece']['name'][$i],
                    'type'     => $_FILES['piece']['type'][$i] ?? null,
                    'tmp_name' => $_FILES['piece']['tmp_name'][$i],
                    'error'    => $_FILES['piece']['error'][$i],
                    'size'     => $_FILES['piece']['size'][$i],
                ];
                $res = identite_piece_ajouter($critere_id, $f);
                if (!$res['succes']) {
                    $erreurs_up[] = $f['name'] . ' : ' . ($res['message'] ?? 'erreur');
                }
            }
            if ($erreurs_up) {
                set_flash('error', 'Certains fichiers non ajoutés — ' . implode(' ; ', $erreurs_up));
                redirect('/modules/identite/champ.php?id=' . $champ_id . '&edit=1#critere-' . $critere_id);
                break;
            }
        }

        set_flash('success', 'Critère ' . ($critere_id) . ' enregistré.');
        redirect('/modules/identite/champ.php?id=' . $champ_id . '&edit=1&open=' . $critere_id . '#critere-' . $critere_id);
        break;

    case 'sauver_referentiel':
        $ref = clean($_POST['referentiel'] ?? '');
        if (!identite_referentiel($ref)) {
            set_flash('error', 'Référentiel inconnu.');
            redirect('/modules/identite/index.php');
            break;
        }
        $champ_id_post = (int)($_POST['champ_id'] ?? 0);
        $scores   = is_array($_POST['score'] ?? null) ? $_POST['score'] : [];
        $comments = is_array($_POST['commentaire'] ?? null) ? $_POST['commentaire'] : [];
        $cibles = array_unique(array_merge(array_keys($scores), array_keys($comments)));
        foreach ($cibles as $cid) {
            $cid = (int)$cid;
            if ($cid <= 0) continue;
            $sval = $scores[$cid] ?? '';
            $sc   = ($sval !== '' && $sval !== null) ? (int)$sval : null;
            $cm   = $comments[$cid] ?? '';
            identite_referentiel_sauver($ref, $cid, $sc, $cm);
        }
        log_activite(user_id(), 'IDENTITE_CRICT', 'MODIFICATION',
            'Évaluation ' . $ref . ' — champ ' . $champ_id_post);
        set_flash('success', 'Évaluation enregistrée.');
        redirect('/modules/identite/referentiel.php?ref=' . urlencode($ref) . '&champ=' . $champ_id_post);
        break;

    case 'supprimer_piece':
        $piece_id = (int)($_POST['piece_id'] ?? 0);
        if (identite_piece_supprimer($piece_id)) {
            set_flash('success', 'Document supprimé.');
        } else {
            set_flash('error', 'Impossible de supprimer le document.');
        }
        redirect('/modules/identite/champ.php?id=' . $champ_id . '&edit=1');
        break;

    case 'sauver_indicateurs':
        $data = [];
        foreach ($_POST as $k => $v) {
            if (strpos($k, 'ind_') === 0) {
                $data[substr($k, 4)] = $v;
            }
        }
        if (identite_indicateurs_sauver($data)) {
            set_flash('success', 'Indicateurs quantitatifs enregistrés.');
        } else {
            set_flash('error', 'Erreur lors de l\'enregistrement des indicateurs.');
        }
        redirect('/modules/identite/champ.php?id=' . $champ_id . '&edit=1');
        break;

    case 'sauver_swot':
        if (identite_swot_sauver($_POST['swot'] ?? [])) {
            set_flash('success', 'Analyse SWOT enregistrée.');
        } else {
            set_flash('error', 'Erreur lors de l\'enregistrement du SWOT.');
        }
        redirect('/modules/identite/swot.php?edit=1');
        break;

    case 'sauver_recommandations':
        if (identite_recommandations_sauver($_POST['contenu'] ?? '')) {
            set_flash('success', 'Recommandations enregistrées.');
        } else {
            set_flash('error', 'Erreur lors de l\'enregistrement.');
        }
        redirect('/modules/identite/swot.php?edit=1');
        break;

    case 'sauver_auto_point':
        $critere_id = (int)($_POST['critere_id'] ?? 0);
        $en_edit    = !empty($_POST['en_mode_edit']);
        $suffix     = $en_edit ? '&edit=1' : '';

        $res = auto_anaq_point_sauver($critere_id, true, true);
        if (!$res['ok']) {
            set_flash('error', $res['message']);
            redirect('/modules/identite/auto_point.php?id=' . max(1, $critere_id) . $suffix);
            break;
        }
        if (!empty($res['upload_errs'])) {
            set_flash('error', 'Certains fichiers non ajoutés — ' . implode(' ; ', $res['upload_errs']));
        } else {
            set_flash('success', $res['message']);
        }
        redirect('/modules/identite/auto_point.php?id=' . $critere_id . $suffix);
        break;

    case 'sauver_auto_point_ajax':
        header('Content-Type: application/json; charset=utf-8');
        $critere_id = (int)($_POST['critere_id'] ?? 0);
        $res = auto_anaq_point_sauver($critere_id, false, false);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit;

    case 'sauver_auto_swot':
        $champ_id_a = (int)($_POST['champ_id'] ?? 0);
        $en_edit    = !empty($_POST['en_mode_edit']);
        $suffix     = $en_edit ? '&edit=1' : '';
        if (auto_anaq_swot_sauver($champ_id_a, [
            'forces' => $_POST['forces'] ?? '',
            'faiblesses' => $_POST['faiblesses'] ?? '',
            'opportunites' => $_POST['opportunites'] ?? '',
            'menaces' => $_POST['menaces'] ?? '',
        ])) {
            set_flash('success', 'Analyse SWOT enregistrée.');
        } else {
            set_flash('error', 'Erreur lors de l\'enregistrement.');
        }
        redirect('/modules/identite/auto_champ.php?id=' . $champ_id_a . $suffix);
        break;

    case 'supprimer_auto_piece':
        $piece_id = (int)($_POST['piece_id'] ?? 0);
        $critere_id = (int)($_POST['critere_id'] ?? 0);
        $en_edit = !empty($_POST['en_mode_edit']);
        if (auto_anaq_piece_supprimer($piece_id)) {
            set_flash('success', 'Document supprimé.');
        } else {
            set_flash('error', 'Impossible de supprimer.');
        }
        redirect('/modules/identite/auto_point.php?id=' . $critere_id . ($en_edit ? '&edit=1' : ''));
        break;

    case 'auto_canevas_ajouter':
        if (!identite_peut_modifier()) {
            set_flash('error', 'Action non autorisée.');
            redirect('/modules/identite/auto_canevas.php');
            break;
        }
        $r = auto_anaq_canevas_ajouter($_POST);
        if (!$r['succes']) {
            set_flash('error', $r['message'] ?? 'Erreur.');
            redirect('/modules/identite/auto_canevas.php');
            break;
        }
        $cid = (int)($r['id'] ?? 0);
        $mode = clean($_POST['import_mode'] ?? 'fichier');
        $texte = trim((string)($_POST['referentiel_texte'] ?? ''));
        if ($cid > 0 && $mode === 'coller' && $texte !== '') {
            $imp = auto_anaq_importer_texte_referentiel($cid, $texte);
            set_flash($imp['succes'] ? 'success' : 'error',
                $imp['succes']
                    ? ('Canevas créé. ' . ($imp['message'] ?? ''))
                    : ('Canevas créé, mais import échoué : ' . ($imp['message'] ?? '')));
            redirect('/modules/identite/auto_canevas.php');
            break;
        }
        if ($cid > 0 && !empty($_FILES['fichier_guide']['name'])) {
            $up = auto_anaq_canevas_upload_guide($cid, $_FILES['fichier_guide']);
            set_flash($up['succes'] ? 'success' : 'error', $up['message'] ?? ($up['succes'] ? 'Canevas créé.' : 'Erreur.'));
            redirect('/modules/identite/auto_canevas.php');
            break;
        }
        set_flash('success', 'Canevas créé. Importez un fichier Word ou collez le contenu pour remplir la grille.');
        redirect('/modules/identite/auto_canevas.php');
        break;

    case 'auto_canevas_modifier':
        if (!identite_peut_modifier()) {
            set_flash('error', 'Action non autorisée.');
            redirect('/modules/identite/auto_canevas.php');
            break;
        }
        $cid = (int)($_POST['id'] ?? 0);
        $r = auto_anaq_canevas_modifier($cid, $_POST);
        if (!$r['succes']) {
            set_flash('error', $r['message'] ?? 'Erreur.');
            redirect('/modules/identite/auto_canevas.php?edit=' . $cid);
            break;
        }
        $mode = clean($_POST['import_mode'] ?? 'fichier');
        $texte = trim((string)($_POST['referentiel_texte'] ?? ''));
        if ($mode === 'coller' && $texte !== '') {
            $imp = auto_anaq_importer_texte_referentiel($cid, $texte);
            set_flash($imp['succes'] ? 'success' : 'error',
                $imp['succes']
                    ? ('Canevas mis à jour. ' . ($imp['message'] ?? ''))
                    : ('Canevas enregistré, mais import échoué : ' . ($imp['message'] ?? '')));
            redirect('/modules/identite/auto_canevas.php');
            break;
        }
        if (!empty($_FILES['fichier_guide']['name'])) {
            $up = auto_anaq_canevas_upload_guide($cid, $_FILES['fichier_guide']);
            set_flash($up['succes'] ? 'success' : 'error', $up['message'] ?? 'Mise à jour effectuée.');
            redirect('/modules/identite/auto_canevas.php');
            break;
        }
        set_flash('success', 'Canevas mis à jour.');
        redirect('/modules/identite/auto_canevas.php');
        break;

    case 'auto_canevas_utiliser':
        $r = auto_anaq_canevas_utiliser((int)($_POST['id'] ?? 0));
        set_flash($r['succes'] ? 'success' : 'error', $r['message'] ?? ($r['succes'] ? 'OK.' : 'Erreur.'));
        redirect($r['succes'] ? '/modules/identite/referentiel.php' : '/modules/identite/auto_canevas.php');
        break;

    case 'auto_canevas_supprimer':
        $r = auto_anaq_canevas_supprimer((int)($_POST['id'] ?? 0));
        set_flash($r['succes'] ? 'success' : 'error', $r['message'] ?? ($r['succes'] ? 'Supprimé.' : 'Erreur.'));
        redirect('/modules/identite/auto_canevas.php');
        break;

    // Anciens noms (liens / formulaires en cache)
    case 'auto_canevas_activer':
        $r = auto_anaq_canevas_utiliser((int)($_POST['id'] ?? 0));
        set_flash($r['succes'] ? 'success' : 'error', $r['message'] ?? 'Erreur.');
        redirect($r['succes'] ? '/modules/identite/referentiel.php' : '/modules/identite/auto_canevas.php');
        break;

    case 'auto_canevas_desactiver':
        set_flash('error', 'La désactivation a été retirée. Utilisez « Utiliser » sur un autre référentiel, ou « Supprimer ».');
        redirect('/modules/identite/auto_canevas.php');
        break;

    default:
        set_flash('error', 'Action non reconnue.');
        redirect('/modules/identite/index.php');
}

redirect('/modules/identite/index.php');
