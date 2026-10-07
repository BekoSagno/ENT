<?php
// modules/instructions/voir.php — Détail & suivi d'une instruction
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/instruction.php';

session_demarrer();
exiger_permission('INSTRUCTIONS', 'VOIR');

$user = user_courant();
$id   = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/modules/instructions/index.php');

$instr = instruction_charger($id);
if (!$instr) { set_flash('error','Instruction introuvable.'); redirect('/modules/instructions/index.php'); }

$est_emetteur = _instr_est_emetteur($id, (int)$user['id']);   // = le DG / émetteur
$a_une_tache  = _instr_a_une_tache($id, (int)$user['id']);
if (!$est_emetteur && !$a_une_tache) { set_flash('error','Accès refusé.'); redirect('/modules/instructions/index.php'); }

// Accusé de réception sur les tâches du destinataire
get_db()->prepare("UPDATE instructions_taches SET vue_le=NOW() WHERE instruction_id=? AND assigne_a=? AND vue_le IS NULL")
        ->execute([$id, (int)$user['id']]);

// Statuts pendant lesquels le responsable peut encore déposer/retirer des pièces jointes
$STATUTS_MODIFIABLES = ['A_FAIRE','EN_COURS'];

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    if (in_array($instr['statut'], ['TERMINEE','ANNULEE'], true)) {
        set_flash('error', 'Cette instruction est clôturée : aucune modification n\'est possible.');
        redirect('/modules/instructions/voir.php?id=' . $id);
    }
    $action   = $_POST['action'] ?? '';
    $tache_id = (int)($_POST['tache_id'] ?? 0);

    // Vérifier que la tâche appartient bien à l'instruction
    $ok_tache = false;
    foreach ($instr['taches'] as $t) if ((int)$t['id'] === $tache_id) $ok_tache = $t;

    if ($action === 'tache_statut' && $ok_tache && (int)$ok_tache['assigne_a'] === (int)$user['id']) {
        // Le responsable ne peut que travailler : À faire / En cours (pas d'auto-validation)
        $st = clean($_POST['statut'] ?? '');
        if (!in_array($st, ['A_FAIRE','EN_COURS'], true)) $st = 'EN_COURS';
        $r = itache_changer_statut($tache_id, (int)$user['id'], $st, trim($_POST['reponse'] ?? ''),
            isset($_POST['avancement']) ? (int)$_POST['avancement'] : null);
        set_flash($r['succes']?'success':'error', $r['succes'] ? 'Suivi mis à jour.' : ($r['message'] ?? 'Erreur.'));

    } elseif ($action === 'tache_soumettre' && $ok_tache && (int)$ok_tache['assigne_a'] === (int)$user['id']) {
        $r = itache_soumettre($tache_id, (int)$user['id'], trim($_POST['message'] ?? ''));
        set_flash($r['succes']?'success':'error', $r['succes'] ? 'Travail soumis au DG pour validation.' : ($r['message'] ?? 'Erreur.'));

    } elseif ($action === 'tache_valider' && $ok_tache && $est_emetteur) {
        $r = itache_valider($tache_id, (int)$user['id'], trim($_POST['message'] ?? ''));
        set_flash($r['succes']?'success':'error', $r['succes'] ? 'Tâche validée et clôturée.' : ($r['message'] ?? 'Erreur.'));

    } elseif ($action === 'tache_rejeter' && $ok_tache && $est_emetteur) {
        $r = itache_rejeter($tache_id, (int)$user['id'], trim($_POST['note'] ?? ''));
        set_flash($r['succes']?'success':'error', $r['succes'] ? 'Tâche renvoyée au responsable pour amélioration.' : ($r['message'] ?? 'Erreur.'));

    } elseif ($action === 'echange_ajouter' && $ok_tache && ((int)$ok_tache['assigne_a'] === (int)$user['id'] || $est_emetteur)) {
        $r = itache_echange_ajouter($tache_id, $id, (int)$user['id'], trim($_POST['message'] ?? ''));
        set_flash($r['succes']?'success':'error', $r['succes'] ? 'Message envoyé.' : ($r['message'] ?? 'Erreur.'));

    } elseif ($action === 'output_fichier' && $ok_tache && !empty($_FILES['fichier']['name'][0])) {
        // Seul le responsable, et seulement tant que la tâche n'est pas soumise/validée
        $peut = (int)$ok_tache['assigne_a'] === (int)$user['id']
             && in_array($ok_tache['statut'], $STATUTS_MODIFIABLES, true);
        if ($peut) {
            $n = 0;
            foreach ($_FILES['fichier']['name'] as $k => $nom) {
                if ($_FILES['fichier']['error'][$k] !== UPLOAD_ERR_OK) continue;
                $f = ['name'=>$nom,'tmp_name'=>$_FILES['fichier']['tmp_name'][$k],'error'=>$_FILES['fichier']['error'][$k],'size'=>$_FILES['fichier']['size'][$k]];
                if (itache_output_fichier($tache_id, $id, $f, clean($_POST['libelle'][$k] ?? ''))['succes']) $n++;
            }
            set_flash($n?'success':'error', $n ? "$n document(s) ajouté(s)." : 'Aucun document ajouté.');
        } else {
            set_flash('error', 'Vous ne pouvez plus ajouter de document à cette étape.');
        }

    } elseif ($action === 'output_lien' && $ok_tache && trim($_POST['url'] ?? '') !== ''
              && (int)$ok_tache['assigne_a'] === (int)$user['id']
              && in_array($ok_tache['statut'], $STATUTS_MODIFIABLES, true)) {
        $r = itache_output_lien($tache_id, $id, clean($_POST['url']), clean($_POST['url_lib'] ?? ''));
        set_flash($r['succes']?'success':'error', $r['succes'] ? 'Lien ajouté.' : ($r['message'] ?? 'Erreur.'));

    } elseif ($action === 'output_supprimer') {
        // Seul le déposant peut supprimer, et seulement tant que la tâche est modifiable. Le DG ne supprime jamais.
        $doc_id = (int)($_POST['doc_id'] ?? 0);
        $owner = false; $t_statut = '';
        foreach ($instr['taches'] as $t) foreach ($t['outputs'] as $o)
            if ((int)$o['id']===$doc_id) { $owner = ((int)$o['depose_par']===(int)$user['id']); $t_statut = $t['statut']; }
        if ($owner && in_array($t_statut, $STATUTS_MODIFIABLES, true)) {
            itache_output_supprimer($doc_id); set_flash('success','Document supprimé.');
        } else {
            set_flash('error','Suppression impossible à cette étape.');
        }

    } elseif ($action === 'tache_supprimer' && $est_emetteur && $tache_id) {
        itache_supprimer($tache_id); set_flash('success','Tâche supprimée.');

    } elseif ($action === 'annuler' && $est_emetteur) {
        instruction_annuler($id); set_flash('success','Instruction annulée.');
    }
    redirect('/modules/instructions/voir.php?id=' . $id . ($tache_id ? '#t'.$tache_id : ''));
}

$inst_cfg = [
    'EN_COURS'=>['En cours','#dbeafe','#1e40af','fa-spinner'],
    'TERMINEE'=>['Terminée','#dcfce7','#166534','fa-circle-check'],
    'ANNULEE' =>['Annulée','#f3f4f6','#6b7280','fa-ban'],
];
$ic = $inst_cfg[$instr['statut']] ?? $inst_cfg['EN_COURS'];
$tache_cfg = [
    'BLOQUEE'   =>['En attente','#f3f4f6','#6b7280','fa-hourglass-half'],
    'A_FAIRE'   =>['À faire','#fef3c7','#92400e','fa-circle'],
    'EN_COURS'  =>['En cours','#dbeafe','#1e40af','fa-spinner'],
    'EN_REVISION'=>['Soumise — à valider','#ede9fe','#5b21b6','fa-magnifying-glass'],
    'TERMINEE'  =>['Validée','#dcfce7','#166534','fa-circle-check'],
    'ANNULEE'   =>['Annulée','#f3f4f6','#9ca3af','fa-ban'],
];
$prio_map = ['CRITIQUE'=>['#fee2e2','#991b1b'],'HAUTE'=>['#ffedd5','#9a3412'],'NORMALE'=>['#dbeafe','#1e40af'],'FAIBLE'=>['#f3f4f6','#6b7280']];

// Style des entrées du fil d'échanges
$ech_cfg = [
    'MESSAGE'    =>['#f1f5f9','#334155','fa-comment',        'a écrit'],
    'SOUMISSION' =>['#ede9fe','#5b21b6','fa-paper-plane',     'a soumis pour validation'],
    'VALIDATION' =>['#dcfce7','#166534','fa-circle-check',    'a validé la tâche'],
    'REJET'      =>['#fef3c7','#92400e','fa-rotate-left',     'demande des améliorations'],
];

$verrouille = in_array($instr['statut'], ['TERMINEE','ANNULEE'], true); // lecture seule
$nbT = count($instr['taches']);
$nbF = count(array_filter($instr['taches'], fn($t)=>$t['statut']==='TERMINEE'));
$pct_global = $nbT ? round($nbF/$nbT*100) : 0;

$page_titre      = $instr['objet'];
$page_breadcrumb = 'Instructions';
$module_actif    = 'INSTRUCTIONS';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.det-grid{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px 22px;margin-bottom:18px;}
.card h3{font-size:.95rem;font-weight:800;margin:0 0 14px;display:flex;align-items:center;gap:8px;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 11px;border-radius:20px;font-size:.72rem;font-weight:700;}
.contenu-box{font-size:.9rem;line-height:1.7;color:var(--text);white-space:pre-wrap;}
.tk{border:1.5px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:14px;}
.tk.mine{border-color:var(--blue-400);box-shadow:0 0 0 3px rgba(59,130,246,.08);}
.tk-head{display:flex;align-items:flex-start;gap:10px;justify-content:space-between;margin-bottom:8px;}
.tk-code{font-size:.66rem;font-weight:700;color:var(--text-muted);letter-spacing:.07em;}
.tk-titre{font-size:.96rem;font-weight:800;color:var(--text);line-height:1.3;}
.tk-meta{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:8px 0;font-size:.76rem;color:var(--text-muted);}
.cd{font-variant-numeric:tabular-nums;font-weight:800;}
.prog-track{height:6px;background:#e5e7eb;border-radius:20px;overflow:hidden;margin:8px 0 3px;}
.prog-fill{height:100%;border-radius:20px;}
.doc-item{display:flex;align-items:center;gap:9px;padding:8px 11px;border:1px solid var(--border);border-radius:9px;margin-bottom:7px;}
.doc-ic{width:30px;height:30px;border-radius:7px;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.8rem;}
.subform{margin-top:12px;padding-top:12px;border-top:1px dashed var(--border);}
.meta-list{font-size:.82rem;display:flex;flex-direction:column;gap:9px;}
.meta-list .row{display:flex;justify-content:space-between;gap:10px;} .meta-list .row span:first-child{color:var(--text-muted);}
.ech{margin-top:12px;padding-top:12px;border-top:1px dashed var(--border);}
.ech-title{font-size:.74rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.ech-item{display:flex;gap:9px;margin-bottom:10px;}
.ech-ic{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.72rem;}
.ech-body{flex:1;min-width:0;}
.ech-head{font-size:.74rem;color:var(--text-muted);margin-bottom:2px;}
.ech-head strong{color:var(--text);}
.ech-msg{font-size:.85rem;line-height:1.5;color:var(--text);white-space:pre-wrap;}
.banner{display:flex;align-items:center;gap:8px;padding:9px 13px;border-radius:9px;font-size:.82rem;font-weight:600;margin-top:10px;}
@media(max-width:880px){.det-grid{grid-template-columns:1fr;}}
</style>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap;">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Liste</a>
  <div style="flex:1;"></div>
  <?php if ($est_emetteur && !$verrouille): ?>
    <a href="tache_form.php?instruction_id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Ajouter une tâche</a>
    <a href="creer.php?id=<?= $id ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> En-tête</a>
    <form method="post" onsubmit="return confirm('Annuler cette instruction ?');" style="display:inline;">
      <?= csrf_field() ?><input type="hidden" name="action" value="annuler">
      <button class="btn btn-secondary btn-sm" style="color:#dc2626;"><i class="fa-solid fa-ban"></i> Annuler</button>
    </form>
  <?php endif; ?>
</div>

<div class="det-grid">
  <div>
    <!-- En-tête instruction -->
    <div class="card">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
        <span class="tk-code"><?= e($instr['code']) ?></span>
        <span class="badge" style="background:<?= $ic[1] ?>;color:<?= $ic[2] ?>;"><i class="fa-solid <?= $ic[3] ?>"></i> <?= $ic[0] ?></span>
        <span class="badge" style="background:<?= $instr['type']==='MULTIPLE'?'#e0f2fe':'#ede9fe' ?>;color:<?= $instr['type']==='MULTIPLE'?'#0369a1':'#6d28d9' ?>;">
          <i class="fa-solid <?= $instr['type']==='MULTIPLE'?'fa-users':'fa-user' ?>"></i> <?= $instr['type']==='MULTIPLE'?'Tâches multiples':'Tâche unique' ?>
        </span>
      </div>
      <h3 style="font-size:1.2rem;"><?= e($instr['objet']) ?></h3>
      <?php if (!empty($instr['description'])): ?><div class="contenu-box"><?= e($instr['description']) ?></div><?php endif; ?>
    </div>

    <!-- Tâches -->
    <div class="card">
      <h3><i class="fa-solid fa-list-check" style="color:var(--blue-600);"></i> Tâches (<?= $nbF ?>/<?= $nbT ?> validées)</h3>
      <?php if (empty($instr['taches'])): ?>
        <p style="font-size:.85rem;color:var(--text-muted);">Aucune tâche.</p>
      <?php endif; ?>

      <?php foreach ($instr['taches'] as $t):
        $tc = $tache_cfg[$t['statut']] ?? $tache_cfg['A_FAIRE'];
        $pc = $prio_map[$t['priorite']] ?? $prio_map['NORMALE'];
        $mine = (int)$t['assigne_a'] === (int)$user['id'];
        $bloquee = $t['statut']==='BLOQUEE';
        $en_revision = $t['statut']==='EN_REVISION';
        $modifiable = in_array($t['statut'], $STATUTS_MODIFIABLES, true);  // responsable peut encore agir
        $peut_echanger = ($mine || $est_emetteur) && !$verrouille && !$bloquee;
      ?>
      <div class="tk <?= $mine?'mine':'' ?>" id="t<?= $t['id'] ?>">
        <div class="tk-head">
          <div style="min-width:0;">
            <div class="tk-code"><?= e($t['code']) ?><?= $mine ? ' · <span style="color:var(--blue-600);">Vous</span>' : '' ?></div>
            <div class="tk-titre"><?= e($t['titre']) ?></div>
          </div>
          <div style="display:flex;gap:5px;flex-wrap:wrap;justify-content:flex-end;">
            <span class="badge" style="background:<?= $tc[1] ?>;color:<?= $tc[2] ?>;"><i class="fa-solid <?= $tc[3] ?>"></i> <?= $tc[0] ?></span>
            <span class="badge" style="background:<?= $pc[0] ?>;color:<?= $pc[1] ?>;"><?= $t['priorite'] ?></span>
          </div>
        </div>

        <?php if (!empty($t['description'])): ?>
          <div style="font-size:.84rem;color:var(--text-muted);line-height:1.55;margin-bottom:6px;"><?= nl2br(e($t['description'])) ?></div>
        <?php endif; ?>

        <div class="tk-meta">
          <span><i class="fa-solid fa-user"></i> <?= e($t['assigne_nom'] ?? '—') ?><?= $t['assigne_groupe'] ? ' ('.e($t['assigne_groupe']).')' : '' ?></span>
          <?php if ($bloquee && $t['depend_titre']): ?>
            <span style="color:#9a3412;"><i class="fa-solid fa-link"></i> En attente : <?= e($t['depend_code'].' — '.$t['depend_titre']) ?>
              (<?= $t['dependance_type']==='APRES_FIN'?'après fin':'après début' ?>)</span>
          <?php elseif ($t['date_echeance']): ?>
            <span><i class="fa-regular fa-clock"></i> Échéance :
              <?php if ($t['statut']==='TERMINEE'): ?>
                <strong style="color:#166534;">validée</strong>
              <?php elseif ($t['statut']==='EN_REVISION'): ?>
                <strong style="color:#5b21b6;">soumise — en attente du DG</strong>
              <?php else: ?>
                <span class="cd" data-deadline="<?= (new DateTime($t['date_echeance']))->format('c') ?>" data-id="<?= $t['id'] ?>">…</span>
              <?php endif; ?>
            </span>
          <?php endif; ?>
          <span style="color:var(--text-muted);">· délai <?= e(instr_delai_texte((int)$t['delai_valeur'], $t['delai_unite'])) ?></span>
        </div>

        <?php if ((int)$t['avancement']>0 || $t['statut']==='EN_COURS'): ?>
        <div class="prog-track"><div class="prog-fill" style="width:<?= (int)$t['avancement'] ?>%;background:<?= (int)$t['avancement']>=100?'#22c55e':'#3b82f6' ?>;"></div></div>
        <?php endif; ?>

        <!-- Outputs -->
        <?php if (!empty($t['outputs'])): ?>
        <div style="margin-top:10px;">
          <?php foreach ($t['outputs'] as $o):
            $lien = $o['type']==='LIEN'; $oic = $lien?'fa-link':($o['type']==='IMAGE'?'fa-image':'fa-file'); ?>
          <div class="doc-item">
            <div class="doc-ic"><i class="fa-solid <?= $oic ?>"></i></div>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:600;font-size:.82rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($o['libelle']) ?></div>
              <div style="font-size:.68rem;color:var(--text-muted);"><?= e($o['deposant'] ?? '—') ?> · <?= format_datetime($o['cree_le']) ?></div>
            </div>
            <?php if ($lien): ?>
              <a href="<?= e($o['url']) ?>" target="_blank" class="btn btn-secondary btn-sm btn-icon"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
            <?php else: ?>
              <a href="upload.php?id=<?= $o['id'] ?>" class="btn btn-secondary btn-sm btn-icon"><i class="fa-solid fa-download"></i></a>
            <?php endif; ?>
            <?php /* Suppression : seul le déposant, et seulement tant que la tâche est modifiable. Jamais le DG. */ ?>
            <?php if (!$verrouille && (int)$o['depose_par']===(int)$user['id'] && $modifiable): ?>
              <form method="post" onsubmit="return confirm('Supprimer ?');" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="action" value="output_supprimer"><input type="hidden" name="doc_id" value="<?= $o['id'] ?>">
                <button class="btn btn-secondary btn-sm btn-icon" style="color:#dc2626;"><i class="fa-solid fa-trash"></i></button>
              </form>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Suivi du responsable (travail en cours, sans auto-validation) -->
        <?php if ($mine && !$verrouille && $modifiable): ?>
        <div class="subform">
          <form method="post" style="margin-bottom:10px;">
            <?= csrf_field() ?><input type="hidden" name="action" value="tache_statut"><input type="hidden" name="tache_id" value="<?= $t['id'] ?>">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
              <div>
                <label class="lbl" style="font-size:.72rem;font-weight:700;">Statut</label>
                <select name="statut" class="form-control">
                  <?php foreach (['A_FAIRE'=>'À faire','EN_COURS'=>'En cours'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= $t['statut']===$k?'selected':'' ?>><?= $v ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="lbl" style="font-size:.72rem;font-weight:700;">Avancement</label>
                <input type="number" name="avancement" class="form-control" style="max-width:90px;" min="0" max="100" value="<?= (int)$t['avancement'] ?>">
              </div>
              <div style="flex:1;min-width:160px;">
                <label class="lbl" style="font-size:.72rem;font-weight:700;">Compte rendu</label>
                <input type="text" name="reponse" class="form-control" value="<?= e($t['reponse'] ?? '') ?>" placeholder="Note…">
              </div>
              <button class="btn btn-secondary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
            </div>
          </form>
          <!-- Soumettre pour validation -->
          <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
            <?= csrf_field() ?><input type="hidden" name="action" value="tache_soumettre"><input type="hidden" name="tache_id" value="<?= $t['id'] ?>">
            <div style="flex:1;min-width:200px;">
              <label class="lbl" style="font-size:.72rem;font-weight:700;">Message au DG (optionnel)</label>
              <input type="text" name="message" class="form-control" placeholder="Travail terminé, prêt pour validation…">
            </div>
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane"></i> Soumettre pour validation</button>
          </form>
        </div>
        <?php endif; ?>

        <!-- Bandeau : en attente de validation -->
        <?php if ($en_revision): ?>
          <div class="banner" style="background:#ede9fe;color:#5b21b6;">
            <i class="fa-solid fa-hourglass-half"></i>
            <?php if ($est_emetteur): ?>Soumise par le responsable — en attente de votre décision.
            <?php else: ?>Soumise — en attente de la validation du DG.<?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Décision du DG (émetteur) : valider ou demander des améliorations -->
        <?php if ($est_emetteur && !$verrouille && in_array($t['statut'], ['EN_REVISION','EN_COURS','A_FAIRE'], true)): ?>
        <div class="subform">
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <form method="post" onsubmit="return confirm('Valider et clôturer cette tâche ?');" style="display:inline;">
              <?= csrf_field() ?><input type="hidden" name="action" value="tache_valider"><input type="hidden" name="tache_id" value="<?= $t['id'] ?>">
              <button class="btn btn-primary btn-sm" style="background:#16a34a;border-color:#16a34a;"><i class="fa-solid fa-circle-check"></i> Valider (clôturer)</button>
            </form>
            <button type="button" class="btn btn-secondary btn-sm" style="color:#92400e;" onclick="document.getElementById('rejet-<?= $t['id'] ?>').style.display='flex';">
              <i class="fa-solid fa-rotate-left"></i> Demander des améliorations
            </button>
          </div>
          <form method="post" id="rejet-<?= $t['id'] ?>" style="display:none;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:10px;">
            <?= csrf_field() ?><input type="hidden" name="action" value="tache_rejeter"><input type="hidden" name="tache_id" value="<?= $t['id'] ?>">
            <div style="flex:1;min-width:220px;">
              <label class="lbl" style="font-size:.72rem;font-weight:700;">Ce qui doit être amélioré (obligatoire)</label>
              <input type="text" name="note" class="form-control" required placeholder="Ex : merci de compléter la section budget…">
            </div>
            <button class="btn btn-secondary btn-sm" style="color:#92400e;"><i class="fa-solid fa-paper-plane"></i> Renvoyer</button>
          </form>
        </div>
        <?php endif; ?>

        <!-- Dépôt de pièces jointes : uniquement le responsable, tant que la tâche n'est pas soumise/validée -->
        <?php if ($mine && $modifiable && !$verrouille): ?>
        <div class="subform">
          <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <?= csrf_field() ?><input type="hidden" name="action" value="output_fichier"><input type="hidden" name="tache_id" value="<?= $t['id'] ?>">
            <input type="file" name="fichier[]" class="form-control" style="flex:1;min-width:180px;" multiple
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.txt,.csv,.jpg,.jpeg,.png,.webp,.gif,.zip">
            <button class="btn btn-secondary btn-sm"><i class="fa-solid fa-upload"></i> Document</button>
          </form>
        </div>
        <?php elseif ($mine && $en_revision): ?>
        <div class="subform" style="font-size:.78rem;color:var(--text-muted);">
          <i class="fa-solid fa-lock"></i> Pièces jointes verrouillées tant que le DG n'a pas statué. Si une amélioration est demandée, vous pourrez de nouveau en ajouter.
        </div>
        <?php endif; ?>

        <!-- Fil d'échanges DG ⇄ responsable -->
        <?php if (($mine || $est_emetteur) && (!empty($t['echanges']) || $peut_echanger)): ?>
        <div class="ech">
          <div class="ech-title"><i class="fa-solid fa-comments"></i> Échanges</div>
          <?php if (empty($t['echanges'])): ?>
            <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:8px;">Aucun message pour l'instant.</div>
          <?php endif; ?>
          <?php foreach ($t['echanges'] as $ec):
            $ecfg = $ech_cfg[$ec['type']] ?? $ech_cfg['MESSAGE'];
            $est_dg = in_array($ec['auteur_groupe'] ?? '', ['SUPER_ADMIN','DG'], true);
          ?>
          <div class="ech-item">
            <div class="ech-ic" style="background:<?= $ecfg[0] ?>;color:<?= $ecfg[1] ?>;"><i class="fa-solid <?= $ecfg[2] ?>"></i></div>
            <div class="ech-body">
              <div class="ech-head">
                <strong><?= e($ec['auteur_nom'] ?? '—') ?></strong>
                <?= $est_dg ? '<span class="badge" style="background:#dbeafe;color:#1e40af;padding:1px 7px;">DG</span>' : '' ?>
                <?= $ecfg[3] ?> · <?= format_datetime($ec['cree_le']) ?>
              </div>
              <?php if (!empty($ec['message'])): ?><div class="ech-msg"><?= nl2br(e($ec['message'])) ?></div><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>

          <?php if ($peut_echanger): ?>
          <form method="post" style="display:flex;gap:8px;align-items:flex-end;margin-top:8px;">
            <?= csrf_field() ?><input type="hidden" name="action" value="echange_ajouter"><input type="hidden" name="tache_id" value="<?= $t['id'] ?>">
            <div style="flex:1;min-width:180px;">
              <input type="text" name="message" class="form-control" required placeholder="Écrire un message…">
            </div>
            <button class="btn btn-secondary btn-sm"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
          </form>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Actions émetteur -->
        <?php if ($est_emetteur && !$verrouille): ?>
        <div style="margin-top:10px;display:flex;gap:6px;">
          <a href="tache_form.php?instruction_id=<?= $id ?>&id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Modifier</a>
          <form method="post" onsubmit="return confirm('Supprimer cette tâche ?');" style="display:inline;">
            <?= csrf_field() ?><input type="hidden" name="action" value="tache_supprimer"><input type="hidden" name="tache_id" value="<?= $t['id'] ?>">
            <button class="btn btn-secondary btn-sm" style="color:#dc2626;"><i class="fa-solid fa-trash"></i></button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Latérale -->
  <div>
    <div class="card">
      <h3 style="font-size:.85rem;"><i class="fa-solid fa-chart-pie"></i> Avancement</h3>
      <div style="text-align:center;padding:8px 0;">
        <div style="font-size:2.2rem;font-weight:800;color:var(--blue-600);"><?= $pct_global ?>%</div>
        <div style="font-size:.74rem;color:var(--text-muted);"><?= $nbF ?>/<?= $nbT ?> tâche<?= $nbT>1?'s':'' ?> validée<?= $nbF>1?'s':'' ?></div>
      </div>
      <div class="prog-track"><div class="prog-fill" style="width:<?= $pct_global ?>%;background:<?= $pct_global>=100?'#22c55e':'#3b82f6' ?>;"></div></div>
    </div>
    <div class="card">
      <h3 style="font-size:.85rem;"><i class="fa-solid fa-circle-info"></i> Détails</h3>
      <div class="meta-list">
        <div class="row"><span>Émetteur (DG)</span><strong><?= e($instr['emetteur_nom']) ?></strong></div>
        <div class="row"><span>Émise le</span><span><?= format_datetime($instr['cree_le']) ?></span></div>
        <div class="row"><span>Type</span><span><?= $instr['type']==='MULTIPLE'?'Tâches multiples':'Tâche unique' ?></span></div>
        <div class="row"><span>Priorité</span><span><?= $instr['priorite'] ?></span></div>
        <div class="row"><span>Statut</span><span><?= $ic[0] ?></span></div>
      </div>
    </div>
  </div>
</div>

<?php
$extra_scripts = <<<'JS'
<script>
function fmt(ms){
  const past = ms<0; ms=Math.abs(ms);
  const j=Math.floor(ms/86400000),h=Math.floor(ms%86400000/3600000),m=Math.floor(ms%3600000/60000),s=Math.floor(ms%60000/1000);
  return (past?'-':'') + (j>0?j+'j ':'') + String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
}
function tickAll(){
  document.querySelectorAll('.cd[data-deadline]').forEach(el=>{
    const diff = new Date(el.dataset.deadline).getTime() - Date.now();
    el.textContent = fmt(diff);
    el.style.color = diff<0 ? '#dc2626' : (diff<3600000 ? '#d97706' : '#1e40af');
  });
}
tickAll(); setInterval(tickAll,1000);
</script>
JS;
include __DIR__ . '/../../views/layout/footer.php';
?>
