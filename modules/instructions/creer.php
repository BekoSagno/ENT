<?php
// modules/instructions/creer.php — Émettre une instruction (1 ou N tâches)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/instruction.php';
require_once __DIR__ . '/../../includes/mailer_instructions.php';

session_demarrer();
exiger_permission('INSTRUCTIONS', 'CREER');

$user  = user_courant();
$id    = (int)($_GET['id'] ?? 0);
$instr = $id ? instruction_charger($id) : null;
$mode  = $instr ? 'modifier' : 'creer';

if ($instr && !_instr_est_emetteur($id, (int)$user['id'])) {
    set_flash('error', 'Accès refusé.'); redirect('/modules/instructions/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    if ($mode === 'modifier') {
        $res = instruction_modifier_entete($id, [
            'objet'       => clean($_POST['objet'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'priorite'    => clean($_POST['priorite'] ?? 'NORMALE'),
        ]);
        if ($res['succes']) { set_flash('success','Instruction modifiée.'); redirect('/modules/instructions/voir.php?id='.$id); }
        set_flash('error', $res['message'] ?? 'Erreur.');
    } else {
        $data = [
            'objet'       => clean($_POST['objet'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'priorite'    => clean($_POST['priorite'] ?? 'NORMALE'),
            'taches'      => $_POST['taches'] ?? [],
        ];
        $res = instruction_creer($data);
        if ($res['succes']) {
            if (MAIL_ENABLED) notifier_instruction($res['instruction_id']);
            set_flash('success', 'Instruction émise et responsables notifiés.');
            redirect('/modules/instructions/voir.php?id=' . $res['instruction_id']);
        }
        set_flash('error', $res['message'] ?? 'Erreur.');
        $old_objet = $data['objet']; $old_desc = $data['description']; $old_prio = $data['priorite'];
    }
}

$users = instr_liste_users();
$prio_sel = $instr['priorite'] ?? ($old_prio ?? 'NORMALE');

$page_titre      = $mode==='creer' ? 'Nouvelle instruction' : 'Modifier l\'instruction';
$page_breadcrumb = 'Instructions';
$module_actif    = 'INSTRUCTIONS';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px 26px;max-width:980px;}
.form-row{margin-bottom:18px;} .lbl{display:block;font-size:.78rem;font-weight:700;color:var(--text);margin-bottom:6px;}
.hint{font-size:.72rem;color:var(--text-muted);margin-top:4px;} .req{color:#dc2626;}
.prio-pills{display:flex;gap:8px;flex-wrap:wrap;}
.prio-pills label{cursor:pointer;padding:7px 16px;border-radius:20px;font-size:.78rem;font-weight:700;border:1.5px solid transparent;}
.prio-pills input{display:none;}
.seg{display:inline-flex;border:1.5px solid var(--border);border-radius:10px;overflow:hidden;}
.seg label{padding:9px 18px;font-size:.82rem;font-weight:700;cursor:pointer;color:var(--text-muted);}
.seg input{display:none;} .seg input:checked+label{background:var(--blue-600);color:#fff;}
.task-row{border:1.5px solid var(--border);border-radius:12px;padding:16px;margin-bottom:14px;background:var(--bg);position:relative;}
.task-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.task-num{font-size:.74rem;font-weight:800;color:var(--blue-700);background:var(--blue-100);padding:3px 11px;border-radius:20px;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;} .grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
.delai-mini{display:flex;gap:8px;} .delai-mini input{max-width:90px;}
.dep-zone{margin-top:10px;padding-top:10px;border-top:1px dashed var(--border);}
.btn-del-task{background:none;border:none;color:#dc2626;cursor:pointer;font-size:.9rem;}
@media(max-width:700px){.grid2,.grid3{grid-template-columns:1fr;}}
</style>

<a href="<?= $mode==='modifier' ? 'voir.php?id='.$id : 'index.php' ?>" class="btn btn-secondary btn-sm" style="margin-bottom:16px;">
  <i class="fa-solid fa-arrow-left"></i> Retour
</a>

<form method="post" class="form-card" id="instrForm">
  <?= csrf_field() ?>

  <div class="form-group">
    <label class="lbl">Objet de l'instruction <span class="req">*</span></label>
    <input type="text" name="objet" class="form-control" maxlength="255" required
           placeholder="Ex : Paiement des primes du personnel"
           value="<?= e($instr['objet'] ?? ($old_objet ?? '')) ?>">
  </div>

  <div class="form-group">
    <label class="lbl">Contexte / précisions <span style="font-weight:400;color:var(--text-muted);">(facultatif)</span></label>
    <textarea name="description" class="form-control" rows="3"
              placeholder="Informations générales communes à toutes les tâches…"><?= e($instr['description'] ?? ($old_desc ?? '')) ?></textarea>
  </div>

  <div class="form-group">
    <label class="lbl">Priorité par défaut</label>
    <div class="prio-pills">
      <?php $prios = ['FAIBLE'=>['#f3f4f6','#6b7280'],'NORMALE'=>['#dbeafe','#1e40af'],'HAUTE'=>['#ffedd5','#9a3412'],'CRITIQUE'=>['#fee2e2','#991b1b']];
      foreach ($prios as $k=>$c): ?>
        <label style="background:<?= $c[0] ?>;color:<?= $c[1] ?>;<?= $prio_sel===$k?'border-color:'.$c[1].';':'' ?>">
          <input type="radio" name="priorite" value="<?= $k ?>" <?= $prio_sel===$k?'checked':'' ?>><span><?= ucfirst(strtolower($k)) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($mode === 'modifier'): ?>
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 16px;font-size:.82rem;color:#1e40af;">
      <i class="fa-solid fa-circle-info"></i> Les tâches (responsables, délais, dépendances) se gèrent depuis la page de l'instruction.
    </div>
    <div style="display:flex;gap:10px;margin-top:18px;">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
      <a href="voir.php?id=<?= $id ?>" class="btn btn-secondary">Annuler</a>
    </div>
  <?php else: ?>

  <hr style="border:none;border-top:1px solid var(--border);margin:22px 0;">

  <div class="form-group">
    <label class="lbl">Type d'instruction</label>
    <div class="seg">
      <input type="radio" name="ui_type" id="ui_unique" value="UNIQUE" checked>
      <label for="ui_unique"><i class="fa-solid fa-user"></i> Tâche unique</label>
      <input type="radio" name="ui_type" id="ui_multiple" value="MULTIPLE">
      <label for="ui_multiple"><i class="fa-solid fa-users"></i> Tâches multiples</label>
    </div>
    <div class="hint">Unique = une seule personne, une seule tâche. Multiple = une tâche distincte par personne, avec dépendances possibles.</div>
  </div>

  <div id="tasksContainer"></div>

  <button type="button" class="btn btn-secondary btn-sm" id="addTaskBtn" style="display:none;">
    <i class="fa-solid fa-plus"></i> Ajouter une tâche
  </button>

  <div style="display:flex;gap:10px;margin-top:22px;">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Émettre l'instruction</button>
    <a href="index.php" class="btn btn-secondary">Annuler</a>
  </div>
  <?php endif; ?>
</form>

<?php if ($mode === 'creer'):
  // Données utilisateurs pour le JS
  $users_json = json_encode(array_map(fn($u)=>['id'=>(int)$u['id'],'nom'=>$u['nom_complet'].' · '.$u['groupe']], $users), JSON_UNESCAPED_UNICODE);
?>
<script>
const USERS = <?= $users_json ?>;
const container = document.getElementById('tasksContainer');
const addBtn = document.getElementById('addTaskBtn');
let multiple = false;

function userOptions(sel){
  return '<option value="">— Choisir —</option>' +
    USERS.map(u=>`<option value="${u.id}"${sel==u.id?' selected':''}>${u.nom}</option>`).join('');
}

function buildRow(){
  const div = document.createElement('div');
  div.className = 'task-row';
  div.innerHTML = `
    <div class="task-head">
      <span class="task-num"></span>
      <button type="button" class="btn-del-task" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
    </div>
    <div class="form-group" style="margin-bottom:12px;">
      <label class="lbl">Tâche à faire <span class="req">*</span></label>
      <input type="text" class="form-control f-titre" placeholder="Ex : Préparer les bordereaux de paiement">
    </div>
    <div class="form-group" style="margin-bottom:12px;">
      <label class="lbl" style="font-weight:600;">Détails (facultatif)</label>
      <textarea class="form-control f-desc" rows="2" placeholder="Précisions sur la tâche…"></textarea>
    </div>
    <div class="grid3">
      <div>
        <label class="lbl" style="font-weight:600;">Responsable <span class="req">*</span></label>
        <select class="form-control f-assigne">${userOptions('')}</select>
      </div>
      <div>
        <label class="lbl" style="font-weight:600;">Délai <span class="req">*</span></label>
        <div class="delai-mini">
          <input type="number" class="form-control f-delval" min="1" max="999" value="1">
          <select class="form-control f-delunit">
            <option value="MINUTE">Minute(s)</option><option value="HEURE" selected>Heure(s)</option><option value="JOUR">Jour(s)</option>
          </select>
        </div>
      </div>
      <div>
        <label class="lbl" style="font-weight:600;">Priorité</label>
        <select class="form-control f-prio">
          <option value="FAIBLE">Faible</option><option value="NORMALE" selected>Normale</option>
          <option value="HAUTE">Haute</option><option value="CRITIQUE">Critique</option>
        </select>
      </div>
    </div>
    <div class="dep-zone dep-block" style="display:none;">
      <label class="lbl" style="font-weight:600;"><i class="fa-solid fa-link"></i> Dépendance</label>
      <div class="grid2">
        <select class="form-control f-deptype">
          <option value="INDEPENDANTE">Indépendante</option>
          <option value="APRES_FIN">Après la FIN d'une autre tâche</option>
          <option value="APRES_DEBUT">Après le DÉBUT d'une autre tâche</option>
        </select>
        <select class="form-control f-depde" disabled></select>
      </div>
      <div class="hint">Une tâche dépendante reste « en attente » : son délai démarre quand la tâche liée est déclenchée.</div>
    </div>`;
  // suppression
  div.querySelector('.btn-del-task').addEventListener('click', ()=>{ div.remove(); renumber(); });
  // activer/désactiver le choix de la tâche liée
  const dt = div.querySelector('.f-deptype'), dd = div.querySelector('.f-depde');
  dt.addEventListener('change', ()=>{ dd.disabled = (dt.value==='INDEPENDANTE'); if(dd.disabled) dd.value=''; });
  container.appendChild(div);
  renumber();
  return div;
}

function renumber(){
  const rows = [...container.querySelectorAll('.task-row')];
  rows.forEach((r,i)=>{
    r.querySelector('.task-num').textContent = 'Tâche N°' + (i+1);
    r.querySelector('.btn-del-task').style.display = (multiple && rows.length>1) ? 'inline' : 'none';
    // attribuer les noms de champs (contigus)
    r.querySelector('.f-titre').name   = `taches[${i}][titre]`;
    r.querySelector('.f-desc').name    = `taches[${i}][description]`;
    r.querySelector('.f-assigne').name = `taches[${i}][assigne_a]`;
    r.querySelector('.f-delval').name  = `taches[${i}][delai_valeur]`;
    r.querySelector('.f-delunit').name = `taches[${i}][delai_unite]`;
    r.querySelector('.f-prio').name    = `taches[${i}][priorite]`;
    const dt = r.querySelector('.f-deptype'), dd = r.querySelector('.f-depde');
    dt.name = `taches[${i}][dependance_type]`;
    dd.name = `taches[${i}][depend_row]`;
    r.querySelector('.dep-block').style.display = multiple ? 'block' : 'none';
  });
  // reconstruire les listes de tâches liées (uniquement tâches précédentes)
  rows.forEach((r,i)=>{
    const dd = r.querySelector('.f-depde'); const cur = dd.value;
    let opts = '<option value="">— Choisir —</option>';
    rows.forEach((r2,j)=>{
      if (j===i) return;
      const titre = r2.querySelector('.f-titre').value || ('Tâche N°'+(j+1));
      opts += `<option value="${j+1}">N°${j+1} — ${titre.substring(0,40)}</option>`;
    });
    dd.innerHTML = opts; if(cur) dd.value = cur;
  });
}

function setMode(isMulti){
  multiple = isMulti;
  addBtn.style.display = isMulti ? 'inline-flex' : 'none';
  if (!isMulti){
    // garder une seule tâche
    const rows = [...container.querySelectorAll('.task-row')];
    rows.slice(1).forEach(r=>r.remove());
    // réinitialiser la dépendance
    const r0 = container.querySelector('.task-row');
    if (r0){ r0.querySelector('.f-deptype').value='INDEPENDANTE'; const dd=r0.querySelector('.f-depde'); dd.value=''; dd.disabled=true; }
  }
  renumber();
}

document.getElementById('ui_unique').addEventListener('change', ()=>setMode(false));
document.getElementById('ui_multiple').addEventListener('change', ()=>setMode(true));
addBtn.addEventListener('click', ()=>buildRow());
// rafraîchir les libellés des dépendances quand un titre change
container.addEventListener('input', e=>{ if(e.target.classList.contains('f-titre')) renumber(); });

// première tâche
buildRow();
setMode(false);
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
