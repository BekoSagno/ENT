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
        // Une tâche par responsable choisi, toutes avec le même objet, délai et priorité
        $responsables = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['responsables'] ?? [])))));
        $delai_valeur = max(1, (int)($_POST['delai_valeur'] ?? 1));
        $delai_unite  = clean($_POST['delai_unite'] ?? 'HEURE');
        $data = [
            'objet'       => clean($_POST['objet'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'priorite'    => clean($_POST['priorite'] ?? 'NORMALE'),
            'taches'      => array_map(fn($uid) => [
                'titre'        => clean($_POST['objet'] ?? ''),
                'assigne_a'    => $uid,
                'delai_valeur' => $delai_valeur,
                'delai_unite'  => $delai_unite,
                'priorite'     => clean($_POST['priorite'] ?? 'NORMALE'),
            ], $responsables),
        ];
        $res = $responsables
            ? instruction_creer($data)
            : ['succes'=>false, 'message'=>'Choisissez au moins un responsable.'];
        if ($res['succes']) {
            $n = instr_notifier_nouvelles_taches($res['instruction_id']);
            $canal = MAIL_ENABLED ? ' dans l\'ENT et par e-mail' : ' dans l\'ENT';
            $msg = 'Instruction émise' . ($n['notifies'] ? ' : ' . $n['notifies'] . ' responsable(s) notifié(s)' . $canal : '') . '.';
            if ($n['echecs'] > 0) $msg .= ' ' . $n['echecs'] . ' e-mail(s) n\'ont pas pu être envoyés.';
            set_flash('success', $msg);
            redirect('/modules/instructions/voir.php?id=' . $res['instruction_id']);
        }
        set_flash('error', $res['message'] ?? 'Erreur.');
        $old_objet = $data['objet']; $old_desc = $data['description']; $old_prio = $data['priorite'];
        $old_resp  = $responsables; $old_delval = $delai_valeur; $old_delunit = $delai_unite;
    }
}

$users = instr_liste_users();
$prio_sel = $instr['priorite'] ?? ($old_prio ?? 'NORMALE');
$resp_sel = $old_resp ?? [];

$page_titre      = $mode==='creer' ? 'Nouvelle instruction' : 'Modifier l\'instruction';
$page_breadcrumb = 'Instructions';
$module_actif    = 'INSTRUCTIONS';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px 26px;max-width:820px;}
.lbl{display:block;font-size:.78rem;font-weight:700;color:var(--text);margin-bottom:6px;}
.hint{font-size:.72rem;color:var(--text-muted);margin-top:5px;} .req{color:#dc2626;}
.opt{font-weight:400;color:var(--text-muted);}
.prio-pills{display:flex;gap:8px;flex-wrap:wrap;}
.prio-pills label{cursor:pointer;padding:7px 16px;border-radius:20px;font-size:.78rem;font-weight:700;border:1.5px solid transparent;transition:border-color .15s;}
.prio-pills input{display:none;}
.row-2{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start;}
.delai-mini{display:flex;gap:8px;} .delai-mini input{width:80px;flex:0 0 80px;} .delai-mini select{flex:1;}

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
.resp-dropdown{position:absolute;left:0;right:0;z-index:40;margin-top:4px;border:1px solid var(--border);border-radius:10px;background:var(--surface,#fff);box-shadow:0 10px 28px rgba(15,23,42,.12);overflow:hidden;}
.resp-search{border:0;border-bottom:1px solid var(--border);border-radius:0;box-shadow:none;}
.resp-search:focus{outline:none;box-shadow:none;}
.resp-list{max-height:240px;overflow:auto;padding:4px 0;}
.resp-item{display:flex;align-items:flex-start;gap:10px;padding:9px 12px;margin:0;cursor:pointer;font-weight:400;border-bottom:1px solid var(--border);}
.resp-item:last-child{border-bottom:0;}
.resp-item:hover{background:var(--blue-50);}
.resp-item input{margin-top:3px;flex-shrink:0;}
.resp-item-text{display:flex;flex-direction:column;gap:2px;min-width:0;}
.resp-name{font-size:.86rem;font-weight:600;color:var(--text);}
.resp-fonc{font-size:.74rem;color:var(--text-muted);}
.resp-empty{padding:12px;margin:0;font-size:.8rem;color:var(--text-muted);text-align:center;}
@media(max-width:700px){.row-2{grid-template-columns:1fr;}}
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
    <label class="lbl">Description <span class="opt">(facultatif)</span></label>
    <textarea name="description" class="form-control" rows="3"
              placeholder="Ce qui est attendu, contexte, précisions…"><?= e($instr['description'] ?? ($old_desc ?? '')) ?></textarea>
  </div>

  <?php if ($mode === 'creer'): ?>
  <div class="form-group">
    <label class="lbl">Responsable(s) <span class="req">*</span></label>
    <div class="resp-picker" id="respPicker">
      <div class="resp-chips" id="respChips" hidden></div>
      <button type="button" class="resp-toggle form-control" id="respToggle" aria-expanded="false" aria-controls="respDropdown">
        <span class="resp-toggle-label">Choisir les responsables…</span>
        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
      </button>
      <div class="resp-dropdown" id="respDropdown" hidden>
        <input type="search" id="respSearch" class="form-control resp-search"
               placeholder="Rechercher un nom, une fonction…" autocomplete="off" aria-label="Rechercher un responsable">
        <div class="resp-list" role="group" aria-label="Responsables">
          <?php foreach ($users as $u):
            $rid = 'resp-' . (int)$u['id'];
            $coche = in_array((int)$u['id'], $resp_sel, true); ?>
          <label class="resp-item" for="<?= $rid ?>"
                 data-label="<?= e(mb_strtolower($u['nom_complet'].' '.$u['groupe'].' '.$u['matricule'])) ?>"
                 data-name="<?= e($u['nom_complet']) ?>">
            <input type="checkbox" name="responsables[]" id="<?= $rid ?>" value="<?= (int)$u['id'] ?>" <?= $coche ? 'checked' : '' ?>>
            <span class="resp-item-text">
              <span class="resp-name"><?= e($u['nom_complet']) ?></span>
              <span class="resp-fonc"><?= e($u['groupe']) ?></span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <p class="resp-empty" id="respEmpty" hidden>Aucune personne ne correspond à la recherche.</p>
      </div>
    </div>
    <div class="hint">Une tâche est créée pour chaque responsable ; il est prévenu dans l'ENT et par e-mail.</div>
  </div>
  <?php endif; ?>

  <div class="<?= $mode === 'creer' ? 'row-2' : '' ?>">
    <?php if ($mode === 'creer'): ?>
    <div class="form-group">
      <label class="lbl">Délai <span class="req">*</span></label>
      <div class="delai-mini">
        <input type="number" name="delai_valeur" class="form-control" min="1" max="999" required value="<?= (int)($old_delval ?? 1) ?>">
        <select name="delai_unite" class="form-control">
          <?php foreach (['MINUTE'=>'Minute(s)','HEURE'=>'Heure(s)','JOUR'=>'Jour(s)'] as $k=>$lbl): ?>
            <option value="<?= $k ?>" <?= ($old_delunit ?? 'HEURE')===$k?'selected':'' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="hint">À compter de l'émission.</div>
    </div>
    <?php endif; ?>

    <div class="form-group">
      <label class="lbl">Priorité</label>
      <div class="prio-pills" id="prioPills">
        <?php $prios = ['FAIBLE'=>['#f3f4f6','#6b7280'],'NORMALE'=>['#dbeafe','#1e40af'],'HAUTE'=>['#ffedd5','#9a3412'],'CRITIQUE'=>['#fee2e2','#991b1b']];
        foreach ($prios as $k=>$c): ?>
          <label data-color="<?= $c[1] ?>" style="background:<?= $c[0] ?>;color:<?= $c[1] ?>;<?= $prio_sel===$k?'border-color:'.$c[1].';':'' ?>">
            <input type="radio" name="priorite" value="<?= $k ?>" <?= $prio_sel===$k?'checked':'' ?>><span><?= ucfirst(strtolower($k)) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
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
  <div style="display:flex;gap:10px;margin-top:10px;">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Émettre l'instruction</button>
    <a href="index.php" class="btn btn-secondary">Annuler</a>
  </div>
  <?php endif; ?>
</form>

<script>
(function(){
  const pills = document.getElementById('prioPills');
  if (!pills) return;
  function majPrio(){
    pills.querySelectorAll('label').forEach(l => {
      l.style.borderColor = l.querySelector('input').checked ? l.dataset.color : 'transparent';
    });
  }
  pills.addEventListener('change', majPrio);
})();
</script>

<?php if ($mode === 'creer'): ?>
<script>
(function(){
  const picker   = document.getElementById('respPicker');
  const toggle   = document.getElementById('respToggle');
  const dropdown = document.getElementById('respDropdown');
  const search   = document.getElementById('respSearch');
  const list     = picker.querySelector('.resp-list');
  const empty    = document.getElementById('respEmpty');
  const chips    = document.getElementById('respChips');
  const label    = toggle.querySelector('.resp-toggle-label');

  function setOpen(open){
    dropdown.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      search.value = '';
      filtrer('');
      setTimeout(() => search.focus(), 0);
    }
  }

  function filtrer(q){
    let visibles = 0;
    list.querySelectorAll('.resp-item').forEach(item => {
      const ok = !q || item.dataset.label.includes(q);
      item.hidden = !ok;
      if (ok) visibles++;
    });
    empty.hidden = visibles > 0;
  }

  function syncChips(){
    const coches = [...list.querySelectorAll('input:checked')];
    chips.innerHTML = '';
    chips.hidden = coches.length === 0;
    toggle.classList.toggle('has-sel', coches.length > 0);
    label.textContent = coches.length === 0 ? 'Choisir les responsables…'
      : coches.length === 1 ? '1 responsable sélectionné'
      : coches.length + ' responsables sélectionnés';
    coches.forEach(cb => {
      const nom = cb.closest('.resp-item').dataset.name;
      const chip = document.createElement('span');
      chip.className = 'resp-chip';
      chip.textContent = nom + ' ';
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.setAttribute('aria-label', 'Retirer ' + nom);
      btn.innerHTML = '&times;';
      btn.addEventListener('click', () => { cb.checked = false; syncChips(); });
      chip.appendChild(btn);
      chips.appendChild(chip);
    });
  }

  toggle.addEventListener('click', e => { e.preventDefault(); setOpen(dropdown.hidden); });
  search.addEventListener('input', () => filtrer(search.value.trim().toLowerCase()));
  search.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
  list.addEventListener('change', syncChips);
  document.addEventListener('click', e => { if (!picker.contains(e.target)) setOpen(false); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') setOpen(false); });

  document.getElementById('instrForm').addEventListener('submit', e => {
    if (!list.querySelector('input:checked')) {
      e.preventDefault();
      setOpen(true);
      alert('Choisissez au moins un responsable.');
    }
  });

  syncChips();
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
