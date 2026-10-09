<?php
// modules/instructions/tache_form.php — Ajouter / modifier une tâche d'instruction
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/instruction.php';
require_once __DIR__ . '/../../includes/mailer_instructions.php';

session_demarrer();
exiger_permission('INSTRUCTIONS', 'CREER');

$user           = user_courant();
$instruction_id = (int)($_GET['instruction_id'] ?? $_POST['instruction_id'] ?? 0);
$tache_id       = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$instr = $instruction_id ? instruction_charger($instruction_id) : null;
if (!$instr) { set_flash('error','Instruction introuvable.'); redirect('/modules/instructions/index.php'); }
if (!_instr_est_emetteur($instruction_id, (int)$user['id'])) {
    set_flash('error','Accès refusé.'); redirect('/modules/instructions/voir.php?id='.$instruction_id);
}

$tache = $tache_id ? itache_charger($tache_id) : null;
if ($tache && (int)$tache['instruction_id'] !== $instruction_id) { redirect('/modules/instructions/voir.php?id='.$instruction_id); }
$mode  = $tache ? 'modifier' : 'creer';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $d = [
        'titre'           => clean($_POST['titre'] ?? ''),
        'description'     => trim($_POST['description'] ?? ''),
        'assigne_a'       => (int)($_POST['assigne_a'] ?? 0),
        'delai_valeur'    => (int)($_POST['delai_valeur'] ?? 1),
        'delai_unite'     => clean($_POST['delai_unite'] ?? 'HEURE'),
        'priorite'        => clean($_POST['priorite'] ?? 'NORMALE'),
        'dependance_type' => clean($_POST['dependance_type'] ?? 'INDEPENDANTE'),
        'depend_de'       => (int)($_POST['depend_de'] ?? 0),
    ];
    if ($mode === 'creer') {
        $r = itache_creer($instruction_id, $d);
        if ($r['succes']) {
            $n = instr_notifier_nouvelles_taches($instruction_id, [$r['tache_id']]);
            set_flash('success', $n['notifies'] ? 'Tâche ajoutée et responsable notifié.' : 'Tâche ajoutée.');
            redirect('/modules/instructions/voir.php?id='.$instruction_id.'#t'.$r['tache_id']);
        }
        set_flash('error', $r['message'] ?? 'Erreur.');
    } else {
        $ancien_resp = (int)($tache['assigne_a'] ?? 0);
        $r = itache_modifier($tache_id, $d);
        if ($r['succes']) {
            $msg = 'Tâche modifiée.';
            if ($d['assigne_a'] !== $ancien_resp && instr_notifier_nouvelles_taches($instruction_id, [$tache_id])['notifies']) {
                $msg = 'Tâche modifiée et nouveau responsable notifié.';
            }
            set_flash('success', $msg);
            redirect('/modules/instructions/voir.php?id='.$instruction_id.'#t'.$tache_id);
        }
        set_flash('error', $r['message'] ?? 'Erreur.');
    }
    $tache = array_merge($tache ?? [], $d); // préserver la saisie sur erreur
}

$users      = instr_liste_users();
$candidats  = itache_pour_dependance($instruction_id, $tache_id); // prédécesseurs possibles
$dtype_sel  = $tache['dependance_type'] ?? 'INDEPENDANTE';
$depde_sel  = (int)($tache['depend_de'] ?? 0);

$page_titre      = $mode==='creer' ? 'Ajouter une tâche' : 'Modifier la tâche';
$page_breadcrumb = 'Instructions';
$module_actif    = 'INSTRUCTIONS';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px 26px;max-width:820px;}
.lbl{display:block;font-size:.78rem;font-weight:700;color:var(--text);margin-bottom:6px;}
.hint{font-size:.72rem;color:var(--text-muted);margin-top:5px;} .req{color:#dc2626;}
.delai-wrap{display:flex;gap:10px;}
.delai-wrap .f-num{width:90px;flex:0 0 90px;}
.delai-wrap .f-unit{flex:1 1 auto;}
.banner{background:#f0f6ff;border:1px solid var(--border);border-radius:10px;padding:10px 14px;font-size:.8rem;color:var(--text-muted);margin-bottom:18px;}
</style>

<a href="voir.php?id=<?= $instruction_id ?>" class="btn btn-secondary btn-sm" style="margin-bottom:16px;">
  <i class="fa-solid fa-arrow-left"></i> Retour à l'instruction
</a>

<div class="banner">
  <i class="fa-solid fa-bullhorn"></i> Instruction : <strong style="color:var(--text);"><?= e($instr['objet']) ?></strong> <span style="color:var(--text-muted);">(<?= e($instr['code']) ?>)</span>
</div>

<form method="post" class="form-card">
  <?= csrf_field() ?>
  <input type="hidden" name="instruction_id" value="<?= $instruction_id ?>">
  <?php if ($mode==='modifier'): ?><input type="hidden" name="id" value="<?= $tache_id ?>"><?php endif; ?>

  <div class="form-group">
    <label class="lbl">Tâche à faire <span class="req">*</span></label>
    <input type="text" name="titre" class="form-control" maxlength="300" required
           placeholder="Ex : Préparer les bordereaux de paiement" value="<?= e($tache['titre'] ?? '') ?>">
  </div>

  <div class="form-group">
    <label class="lbl">Détails <span style="font-weight:400;color:var(--text-muted);">(facultatif)</span></label>
    <textarea name="description" class="form-control" rows="3" placeholder="Précisions…"><?= e($tache['description'] ?? '') ?></textarea>
  </div>

  <div class="form-group">
    <label class="lbl">Responsable <span class="req">*</span></label>
    <select name="assigne_a" class="form-control" required>
      <option value="">— Choisir le responsable —</option>
      <?php foreach ($users as $u): ?>
        <option value="<?= $u['id'] ?>" <?= (int)($tache['assigne_a'] ?? 0)===(int)$u['id']?'selected':'' ?>>
          <?= e($u['nom_complet']) ?> · <?= e($u['groupe']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="lbl">Délai <span class="req">*</span></label>
      <div class="delai-wrap">
        <input type="number" name="delai_valeur" class="form-control f-num" min="1" max="999"
               value="<?= e((string)($tache['delai_valeur'] ?? 1)) ?>" required>
        <select name="delai_unite" class="form-control f-unit">
          <?php foreach (['MINUTE'=>'Minute(s)','HEURE'=>'Heure(s)','JOUR'=>'Jour(s)'] as $k=>$lbl): ?>
            <option value="<?= $k ?>" <?= ($tache['delai_unite'] ?? 'HEURE')===$k?'selected':'' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="hint">L'échéance = émission + ce délai.</div>
    </div>
    <div class="form-group">
      <label class="lbl">Priorité</label>
      <select name="priorite" class="form-control">
        <?php foreach (['FAIBLE'=>'Faible','NORMALE'=>'Normale','HAUTE'=>'Haute','CRITIQUE'=>'Critique'] as $k=>$lbl): ?>
          <option value="<?= $k ?>" <?= ($tache['priorite'] ?? 'NORMALE')===$k?'selected':'' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <?php if (!empty($candidats)): ?>
  <div class="form-group">
    <label class="lbl"><i class="fa-solid fa-link"></i> Dépendance <span style="font-weight:400;color:var(--text-muted);">(facultatif)</span></label>
    <div class="form-row">
      <select name="dependance_type" id="depType" class="form-control" onchange="majDep()">
        <option value="INDEPENDANTE" <?= $dtype_sel==='INDEPENDANTE'?'selected':'' ?>>Indépendante</option>
        <option value="APRES_FIN"    <?= $dtype_sel==='APRES_FIN'?'selected':'' ?>>Après la FIN d'une autre tâche</option>
        <option value="APRES_DEBUT"  <?= $dtype_sel==='APRES_DEBUT'?'selected':'' ?>>Après le DÉBUT d'une autre tâche</option>
      </select>
      <select name="depend_de" id="depDe" class="form-control">
        <option value="">— Tâche liée —</option>
        <?php foreach ($candidats as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $depde_sel===(int)$c['id']?'selected':'' ?>><?= e($c['code'].' — '.$c['titre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="hint">Une tâche dépendante reste « en attente » : son délai démarre quand la tâche liée est déclenchée.</div>
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:10px;margin-top:8px;">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= $mode==='creer'?'Ajouter la tâche':'Enregistrer' ?></button>
    <a href="voir.php?id=<?= $instruction_id ?>" class="btn btn-secondary">Annuler</a>
  </div>
</form>

<?php
$extra_scripts = <<<'JS'
<script>
function majDep(){
  const t=document.getElementById('depType'), d=document.getElementById('depDe');
  if(!t||!d) return;
  d.disabled = (t.value==='INDEPENDANTE');
  if(d.disabled) d.value='';
}
majDep();
</script>
JS;
include __DIR__ . '/../../views/layout/footer.php';
?>
