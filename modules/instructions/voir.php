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

$est_emetteur = _instr_est_emetteur($id, (int)$user['id']);
$a_une_tache  = _instr_a_une_tache($id, (int)$user['id']);
if (!$est_emetteur && !$a_une_tache) { set_flash('error','Accès refusé.'); redirect('/modules/instructions/index.php'); }

// Accusé de réception sur les tâches du destinataire
get_db()->prepare("UPDATE instructions_taches SET vue_le=NOW() WHERE instruction_id=? AND assigne_a=? AND vue_le IS NULL")
        ->execute([$id, (int)$user['id']]);

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
        $r = itache_changer_statut($tache_id, (int)$user['id'],
            clean($_POST['statut'] ?? ''), trim($_POST['reponse'] ?? ''),
            isset($_POST['avancement']) ? (int)$_POST['avancement'] : null);
        set_flash($r['succes']?'success':'error', $r['succes'] ? 'Suivi mis à jour.' : ($r['message'] ?? 'Erreur.'));

    } elseif ($action === 'output_fichier' && $ok_tache && !empty($_FILES['fichier']['name'][0])) {
        $peut = (int)$ok_tache['assigne_a'] === (int)$user['id'] || $est_emetteur;
        if ($peut) {
            $n = 0;
            foreach ($_FILES['fichier']['name'] as $k => $nom) {
                if ($_FILES['fichier']['error'][$k] !== UPLOAD_ERR_OK) continue;
                $f = ['name'=>$nom,'tmp_name'=>$_FILES['fichier']['tmp_name'][$k],'error'=>$_FILES['fichier']['error'][$k],'size'=>$_FILES['fichier']['size'][$k]];
                if (itache_output_fichier($tache_id, $id, $f, clean($_POST['libelle'][$k] ?? ''))['succes']) $n++;
            }
            set_flash($n?'success':'error', $n ? "$n document(s) ajouté(s)." : 'Aucun document ajouté.');
        }

    } elseif ($action === 'output_lien' && $ok_tache && trim($_POST['url'] ?? '') !== '') {
        $r = itache_output_lien($tache_id, $id, clean($_POST['url']), clean($_POST['url_lib'] ?? ''));
        set_flash($r['succes']?'success':'error', $r['succes'] ? 'Lien ajouté.' : ($r['message'] ?? 'Erreur.'));

    } elseif ($action === 'output_supprimer') {
        $doc_id = (int)($_POST['doc_id'] ?? 0);
        $owner = false;
        foreach ($instr['taches'] as $t) foreach ($t['outputs'] as $o)
            if ((int)$o['id']===$doc_id) $owner = ((int)$o['depose_par']===(int)$user['id']);
        if ($owner || $est_emetteur) { itache_output_supprimer($doc_id); set_flash('success','Document supprimé.'); }

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
    'EN_REVISION'=>['En révision','#ede9fe','#5b21b6','fa-magnifying-glass'],
    'TERMINEE'  =>['Terminée','#dcfce7','#166534','fa-circle-check'],
    'ANNULEE'   =>['Annulée','#f3f4f6','#9ca3af','fa-ban'],
];
$prio_map = ['CRITIQUE'=>['#fee2e2','#991b1b'],'HAUTE'=>['#ffedd5','#9a3412'],'NORMALE'=>['#dbeafe','#1e40af'],'FAIBLE'=>['#f3f4f6','#6b7280']];

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
      <h3><i class="fa-solid fa-list-check" style="color:var(--blue-600);"></i> Tâches (<?= $nbF ?>/<?= $nbT ?> terminées)</h3>
      <?php if (empty($instr['taches'])): ?>
        <p style="font-size:.85rem;color:var(--text-muted);">Aucune tâche.</p>
      <?php endif; ?>

      <?php foreach ($instr['taches'] as $t):
        $tc = $tache_cfg[$t['statut']] ?? $tache_cfg['A_FAIRE'];
        $pc = $prio_map[$t['priorite']] ?? $prio_map['NORMALE'];
        $mine = (int)$t['assigne_a'] === (int)$user['id'];
        $bloquee = $t['statut']==='BLOQUEE';
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
                <strong style="color:#166534;">terminée</strong>
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
            <?php if (!$verrouille && ((int)$o['depose_par']===(int)$user['id'] || $est_emetteur)): ?>
              <form method="post" onsubmit="return confirm('Supprimer ?');" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="action" value="output_supprimer"><input type="hidden" name="doc_id" value="<?= $o['id'] ?>">
                <button class="btn btn-secondary btn-sm btn-icon" style="color:#dc2626;"><i class="fa-solid fa-trash"></i></button>
              </form>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Suivi du responsable -->
        <?php if ($mine && !$bloquee && !$verrouille && $t['statut']!=='TERMINEE'): ?>
        <div class="subform">
          <form method="post" style="margin-bottom:10px;">
            <?= csrf_field() ?><input type="hidden" name="action" value="tache_statut"><input type="hidden" name="tache_id" value="<?= $t['id'] ?>">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
              <div>
                <label class="lbl" style="font-size:.72rem;font-weight:700;">Statut</label>
                <select name="statut" class="form-control">
                  <?php foreach (['A_FAIRE'=>'À faire','EN_COURS'=>'En cours','EN_REVISION'=>'En révision','TERMINEE'=>'Terminée'] as $k=>$v): ?>
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
              <button class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i></button>
            </div>
          </form>
        </div>
        <?php elseif ($mine && $t['statut']==='TERMINEE' && !empty($t['reponse'])): ?>
          <div style="margin-top:8px;font-size:.8rem;color:#166534;"><i class="fa-solid fa-quote-left"></i> <?= e($t['reponse']) ?></div>
        <?php endif; ?>

        <!-- Dépôt d'outputs (responsable ou émetteur) -->
        <?php if (($mine || $est_emetteur) && !$bloquee && !$verrouille): ?>
        <div class="subform">
          <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <?= csrf_field() ?><input type="hidden" name="action" value="output_fichier"><input type="hidden" name="tache_id" value="<?= $t['id'] ?>">
            <input type="file" name="fichier[]" class="form-control" style="flex:1;min-width:180px;" multiple
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.txt,.csv,.jpg,.jpeg,.png,.webp,.gif,.zip">
            <button class="btn btn-secondary btn-sm"><i class="fa-solid fa-upload"></i> Document</button>
          </form>
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
        <div style="font-size:.74rem;color:var(--text-muted);"><?= $nbF ?>/<?= $nbT ?> tâche<?= $nbT>1?'s':'' ?> terminée<?= $nbF>1?'s':'' ?></div>
      </div>
      <div class="prog-track"><div class="prog-fill" style="width:<?= $pct_global ?>%;background:<?= $pct_global>=100?'#22c55e':'#3b82f6' ?>;"></div></div>
    </div>
    <div class="card">
      <h3 style="font-size:.85rem;"><i class="fa-solid fa-circle-info"></i> Détails</h3>
      <div class="meta-list">
        <div class="row"><span>Émetteur</span><strong><?= e($instr['emetteur_nom']) ?></strong></div>
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
