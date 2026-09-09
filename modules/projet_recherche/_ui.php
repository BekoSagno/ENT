<?php
// modules/projet_recherche/_ui.php — UI parcours 6 étapes (canevas CRICT)

function recherche_ui_styles(): void { ?>
<style>
/* ── Liste projets ── */
.pr-wrap{max-width:960px;margin:0 auto;}
.pr-toolbar{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:18px;}
.pr-toolbar .spacer{flex:1;min-width:8px;}
.pr-toolbar-filters{
  display:flex;align-items:center;gap:0;
  background:#fff;border:1.5px solid #d8e0ea;border-radius:10px;overflow:hidden;
  max-width:100%;
}
.pr-toolbar-filters .pr-q{
  width:220px;max-width:42vw;min-width:140px;border:none;border-radius:0;
  padding:9px 12px 9px 36px;box-shadow:none;background:transparent;
}
.pr-toolbar-filters .pr-q:focus{outline:none;box-shadow:none;}
.pr-toolbar-filters .pr-st{
  width:auto;min-width:150px;border:none;border-left:1px solid #e2e8f0;border-radius:0;
  padding:9px 12px;box-shadow:none;background:#f8fafc;
}
.pr-toolbar-filters .pr-st:focus{outline:none;box-shadow:none;}
.pr-toolbar-filters .pr-go{
  border:none;border-left:1px solid #e2e8f0;border-radius:0;
  background:#f1f5f9;color:#334155;padding:9px 14px;cursor:pointer;
}
.pr-toolbar-filters .pr-go:hover{background:#e2e8f0;color:#0f172a;}
.pr-toolbar-search-wrap{position:relative;display:flex;align-items:center;}
.pr-toolbar-search-wrap > i{
  position:absolute;left:12px;color:#94a3b8;font-size:.8rem;pointer-events:none;
}
.pr-list-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;}
.pr-list-card{
  display:flex;flex-direction:column;gap:10px;
  background:#fff;border:1px solid #d8e0ea;border-radius:12px;padding:16px 18px;
  color:inherit;transition:border-color .12s,box-shadow .12s;
}
.pr-list-card:hover{border-color:#93c5fd;box-shadow:0 4px 16px rgba(13,50,120,.08);}
.pr-list-top{display:flex;justify-content:space-between;align-items:center;gap:8px;}
.pr-list-code{font-size:.72rem;font-weight:700;color:#64748b;letter-spacing:.02em;}
.pr-list-badge{display:inline-flex;padding:3px 9px;border-radius:6px;font-size:.7rem;font-weight:700;}
.pr-list-titre{font-size:.95rem;font-weight:800;color:#0f172a;line-height:1.35;margin:0;}
.pr-list-prog-row{display:flex;justify-content:space-between;align-items:center;font-size:.75rem;color:#64748b;margin-bottom:5px;}
.pr-list-prog-row strong{color:#0f172a;font-weight:800;}
.pr-prog{height:6px;background:#e2e8f0;border-radius:4px;overflow:hidden;}
.pr-prog-fill{height:100%;background:#2563eb;border-radius:4px;}
.pr-list-meta{font-size:.72rem;color:#64748b;margin-top:auto;}
.pr-list-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding-top:4px;border-top:1px solid #eef2f7;margin-top:2px;}

.pr-page{max-width:1100px;margin:0 auto;padding-bottom:40px;}
.pr-banner{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
  background:#eff6ff;border:1px solid #bfdbfe;border-radius:14px;padding:12px 16px;margin-bottom:18px;font-size:.84rem;color:#1e3a8a;}
.pr-autosave{display:inline-flex;align-items:center;gap:6px;background:#1d4ed8;color:#fff;font-size:.65rem;font-weight:700;
  letter-spacing:.04em;padding:4px 10px;border-radius:999px;text-transform:uppercase;}

/* Stepper 6 étapes */
.pr-stepper{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-bottom:20px;}
.pr-step-card{display:block;text-decoration:none;background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;
  padding:12px 10px;text-align:center;transition:border-color .15s,box-shadow .15s,background .15s;min-height:78px;}
.pr-step-card:hover{border-color:#93c5fd;background:#f8fbff;}
.pr-step-card .pr-sn{font-size:.7rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px;}
.pr-step-card .pr-st{font-size:.8rem;font-weight:800;color:#0f172a;line-height:1.25;}
.pr-step-card.on{border-color:#2563eb;background:#eff6ff;box-shadow:0 4px 14px rgba(37,99,235,.18);}
.pr-step-card.on .pr-sn{color:#2563eb;}
.pr-step-card.on .pr-st{color:#1e40af;}
.pr-step-card.done{border-color:#86efac;background:#f0fdf4;}
.pr-step-card.done .pr-sn{color:#16a34a;}
.pr-step-card.done .pr-st{color:#166534;}
.pr-step-card.done.on{border-color:#2563eb;background:#eff6ff;}

/* Cartes sections formulaire */
.pr-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,.04);padding:22px 24px;margin-bottom:16px;}
.pr-card-h{display:flex;align-items:center;gap:10px;font-size:1.05rem;font-weight:800;color:#0f172a;
  padding-bottom:0;margin:0 0 16px;border-bottom:none;}
.pr-sec-num{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 8px;
  border-radius:8px;background:#1e40af;color:#fff;font-size:.78rem;font-weight:800;}
.pr-sub{margin:0 0 18px;padding:14px 14px 6px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;border-left:4px solid #2563eb;}
.pr-sub:last-child{margin-bottom:0;}
.pr-sub-h{margin:0 0 12px;font-size:.88rem;font-weight:800;color:#1e3a8a;}
.pr-list{margin:0;padding:0 0 0 4px;list-style:none;}
.pr-list > .pr-field,
.pr-list > .pr-item{position:relative;padding-left:16px;margin-bottom:14px;}
.pr-list > .pr-field::before,
.pr-list > .pr-item::before{content:"";position:absolute;left:0;top:10px;width:7px;height:7px;border-radius:50%;background:#2563eb;}
.pr-label{display:block;font-size:.75rem;font-weight:800;color:#0f172a;text-transform:none;letter-spacing:0;margin-bottom:6px;}
.pr-input,.pr-area{
  width:100%;box-sizing:border-box;border:1px solid #94a3b8;border-radius:10px;background:#fff;color:#0f172a;
  padding:10px 12px;font-size:.92rem;font-family:inherit;line-height:1.55;font-weight:500;
}
.pr-area{resize:vertical;min-height:80px;}
.pr-input:focus,.pr-area:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
.pr-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.pr-field{margin-bottom:14px;}
.pr-field:last-child{margin-bottom:0;}
.pr-req{color:#dc2626;}
.pr-note{font-size:.82rem;color:#334155;font-weight:500;margin:0 0 12px;line-height:1.45;}

/* Tableaux dynamiques */
.pr-tbl-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;}
.pr-tbl-head h4{margin:0;font-size:.9rem;font-weight:800;color:#0f172a;}
.pr-tbl-wrap{overflow-x:auto;border:1px solid #cbd5e1;border-radius:12px;}
.pr-tbl{width:100%;border-collapse:collapse;text-align:left;font-size:.82rem;min-width:640px;}
.pr-tbl th{background:#e2e8f0;color:#0f172a;font-weight:800;padding:10px 8px;border-bottom:1px solid #cbd5e1;white-space:nowrap;}
.pr-tbl td{padding:6px;border-bottom:1px solid #e2e8f0;vertical-align:top;}
.pr-tbl tr:last-child td{border-bottom:none;}
.pr-tbl input,.pr-tbl textarea{
  width:100%;box-sizing:border-box;border:1px solid #94a3b8;border-radius:8px;padding:8px;
  font-size:.85rem;font-family:inherit;background:#fff;color:#0f172a;min-height:36px;font-weight:500;
}
.pr-tbl textarea{min-height:48px;resize:vertical;}
.pr-tbl input:focus,.pr-tbl textarea:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.2);}
.pr-tbl .pr-del{background:#fff;border:1px solid #e2e8f0;color:#94a3b8;border-radius:8px;width:32px;height:32px;cursor:pointer;}
.pr-tbl .pr-del:hover{color:#dc2626;border-color:#fecaca;background:#fef2f2;}
.pr-btn-add{display:inline-flex;align-items:center;gap:6px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;
  border-radius:8px;padding:7px 12px;font-size:.75rem;font-weight:700;cursor:pointer;}
.pr-btn-add:hover{background:#dbeafe;}
.pr-budget-total{margin-top:12px;text-align:right;font-size:.95rem;font-weight:800;color:#0f172a;}
.pr-budget-total span{color:#1d4ed8;}

/* Navigation */
.pr-nav{display:flex;align-items:center;gap:10px;margin-top:8px;padding-top:4px;}
.pr-nav .spacer{flex:1;}
.pr-btn{display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:10px 18px;font-size:.88rem;font-weight:700;
  cursor:pointer;border:1px solid transparent;text-decoration:none;}
.pr-btn-ghost{background:#fff;border-color:#cbd5e1;color:#334155;}
.pr-btn-ghost:hover{background:#f8fafc;}
.pr-btn-primary{background:#2563eb;color:#fff;}
.pr-btn-primary:hover{background:#1d4ed8;}
.pr-btn-success{background:#059669;color:#fff;}
.pr-btn-success:hover{background:#047857;}

/* Feuille A4 */
.pr-a4-toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;}
.pr-a4{
  background:#fff;border:1px solid #94a3b8;border-radius:4px;box-shadow:0 8px 30px rgba(15,23,42,.08);
  max-width:210mm;margin:0 auto;padding:18mm 16mm;color:#0f172a;
}
.pr-a4-header{text-align:center;border-bottom:2px solid #1e3a8a;padding-bottom:12px;margin-bottom:18px;}
.pr-a4-header img{max-height:64px;margin-bottom:8px;}
.pr-a4-header h1{font-size:1.15rem;margin:0 0 6px;color:#0f172a;font-weight:800;}
.pr-a4-header .meta{font-size:.8rem;color:#334155;font-weight:600;}
.pr-a4 h2{font-size:1.05rem;font-weight:800;color:#0f172a;border-bottom:none;padding-bottom:0;margin:26px 0 12px;}
.pr-a4 h3{font-size:.82rem;font-weight:800;color:#0f172a;margin:18px 0 12px;padding-left:0;}
.pr-a4 .pr-a4-list{margin:0 0 12px;padding:0 0 0 18px;}
.pr-a4 .pr-a4-list > li{margin-bottom:12px;}
.pr-a4 .pr-a4-list > li > strong{display:block;font-size:.82rem;font-weight:800;color:#0f172a;margin-bottom:3px;}
.pr-a4 .pr-a4-list a{color:#1d4ed8;font-weight:600;text-decoration:underline;}
.pr-a4 .val{font-size:.95rem;line-height:1.65;font-weight:500;color:#0f172a;white-space:pre-wrap;margin:0 0 12px;}
.pr-a4 .val + .val{margin-top:0;}
.pr-a4 .bloc{margin-bottom:14px;}
.pr-a4 .bloc label{display:block;font-size:.78rem;font-weight:800;color:#0f172a;text-transform:none;letter-spacing:0;margin-bottom:4px;}
.pr-a4 .bloc .val,
.pr-a4 .pr-a4-list .val{font-size:.95rem;line-height:1.65;font-weight:500;color:#0f172a;white-space:pre-wrap;margin-bottom:8px;}
.pr-a4 table{width:100%;border-collapse:collapse;font-size:.85rem;margin:4px 0 18px;color:#0f172a;}
.pr-a4 h3 + table{margin-top:4px;}
.pr-a4 table + .val,
.pr-a4 table + h2,
.pr-a4 table + h3{margin-top:16px;}
.pr-a4 .val + h3{margin-top:18px;}
.pr-a4 th,.pr-a4 td{border:1px solid #94a3b8;padding:7px 8px;vertical-align:top;text-align:left;}
.pr-a4 th{background:#e2e8f0;font-weight:800;color:#0f172a;}
.pr-a4 td{font-weight:500;color:#0f172a;}
.pr-a4-empty{color:#64748b;font-style:italic;font-weight:500;margin-bottom:12px;}
.pr-a4 .pr-a4-note{font-size:.85rem;color:#334155;font-style:italic;margin:6px 0 12px;}

@media(max-width:900px){
  .pr-stepper{grid-template-columns:repeat(3,1fr);}
  .pr-grid-2{grid-template-columns:1fr;}
}
@media(max-width:560px){
  .pr-stepper{grid-template-columns:repeat(2,1fr);}
}

@media print{
  .sidebar,.topbar,.header,.app-sidebar,.app-header,.pr-stepper,.pr-banner,.pr-a4-toolbar,.pr-nav,
  .flash,.btn,nav,aside,#sidebar,.main-header{display:none!important;}
  .pr-page,.pr-a4,.main-content,.content,.page-content{max-width:none!important;margin:0!important;padding:0!important;box-shadow:none!important;border:none!important;}
  .pr-a4{width:100%;padding:10mm;}
  body{background:#fff!important;}
}
</style>
<?php }

function recherche_ui_field(string $name, string $label, string $value, string $placeholder = '', int $rows = 3, bool $required = false, bool $hide_label = false): void {
    $req = $required ? ' <span class="pr-req">*</span>' : '';
    echo '<div class="pr-field">';
    if (!$hide_label) {
        echo '<label class="pr-label" for="' . e($name) . '">' . e($label) . $req . '</label>';
    }
    if ($rows <= 1) {
        echo '<input class="pr-input" type="text" name="' . e($name) . '" id="' . e($name) . '" value="' . e($value) . '"'
            . ($placeholder !== '' ? ' placeholder="' . e($placeholder) . '"' : '')
            . ($required ? ' required' : '') . '>';
    } else {
        echo '<textarea class="pr-area" name="' . e($name) . '" id="' . e($name) . '" rows="' . (int)$rows . '"'
            . ($placeholder !== '' ? ' placeholder="' . e($placeholder) . '"' : '') . '>' . e($value) . '</textarea>';
    }
    echo '</div>';
}

function recherche_ui_sec_open(string $num, string $titre): void {
    echo '<div class="pr-card"><h3 class="pr-card-h"><span class="pr-sec-num">' . e($num) . '</span> ' . e($titre) . '</h3>';
}
function recherche_ui_sec_close(): void {
    echo '</div>';
}
function recherche_ui_sub_open(string $titre): void {
    echo '<div class="pr-sub"><h4 class="pr-sub-h">' . e($titre) . '</h4><div class="pr-list">';
}
function recherche_ui_sub_close(): void {
    echo '</div></div>';
}
function recherche_ui_list_open(): void {
    echo '<div class="pr-list">';
}
function recherche_ui_list_close(): void {
    echo '</div>';
}

function recherche_ui_tableau(string $name, array $cols, array $rows, string $label = '', bool $budget = false): void {
    if ($rows === []) $rows = [array_fill_keys(array_keys($cols), '')];
    $uid = 'tbl_' . preg_replace('/[^a-z0-9_]/i', '', $name);
    $cls = $budget ? ' pr-budget-table' : '';
    ?>
<div class="pr-field" id="<?= e($uid) ?>" data-name="<?= e($name) ?>" data-cols="<?= e(json_encode($cols)) ?>" data-budget="<?= $budget ? '1' : '0' ?>">
  <div class="pr-tbl-head">
    <h4><?= e($label !== '' ? $label : ucfirst($name)) ?></h4>
    <button type="button" class="pr-btn-add" onclick="prAddRow('<?= e($uid) ?>')"><i class="fa-solid fa-plus"></i> Ajouter une ligne</button>
  </div>
  <div class="pr-tbl-wrap">
    <table class="pr-tbl<?= $cls ?>">
      <thead><tr>
        <?php foreach ($cols as $c): ?><th><?= e($c) ?></th><?php endforeach; ?>
        <th style="width:40px;"></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $i => $row): ?>
        <tr>
          <?php foreach ($cols as $k => $c):
              $val = e($row[$k] ?? '');
              $nm = e($name) . '[' . (int)$i . '][' . e($k) . ']';
              $isNum = in_array($k, ['quantite', 'cout_unitaire', 'cout_total', 'temps'], true);
              $isLong = in_array($k, ['responsabilites', 'justification', 'attenuation', 'definition', 'livrables'], true);
          ?>
          <td>
            <?php if ($k === 'cout_total'): ?>
              <input type="text" name="<?= $nm ?>" value="<?= $val ?>" class="pr-budget-total-cell" readonly>
            <?php elseif ($isNum): ?>
              <input type="number" step="any" min="0" name="<?= $nm ?>" value="<?= $val ?>"
                     class="<?= $k === 'quantite' ? 'pr-budget-q' : ($k === 'cout_unitaire' ? 'pr-budget-pu' : '') ?>"
                     <?= $budget && in_array($k, ['quantite','cout_unitaire'], true) ? 'oninput="prBudgetCalc()"' : '' ?>>
            <?php elseif ($isLong): ?>
              <textarea name="<?= $nm ?>" rows="2"><?= $val ?></textarea>
            <?php else: ?>
              <input type="text" name="<?= $nm ?>" value="<?= $val ?>">
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td><button type="button" class="pr-del" title="Supprimer" onclick="this.closest('tr').remove();prBudgetCalc();"><i class="fa-solid fa-xmark"></i></button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($budget): ?>
  <div class="pr-budget-total">Total général : <span id="prBudgetGrandTotal">0</span> GNF</div>
  <?php endif; ?>
</div>
    <?php
}

function recherche_ui_tableau_script(): void { ?>
<script>
function prAddRow(uid) {
  const block = document.getElementById(uid);
  if (!block) return;
  const tbody = block.querySelector('tbody');
  const name = block.getAttribute('data-name');
  const cols = JSON.parse(block.getAttribute('data-cols') || '{}');
  const budget = block.getAttribute('data-budget') === '1';
  const i = tbody.querySelectorAll('tr').length;
  const longCols = ['responsabilites','justification','attenuation','definition','livrables'];
  const numCols = ['quantite','cout_unitaire','cout_total','temps'];
  const tr = document.createElement('tr');
  Object.keys(cols).forEach(function(k) {
    const td = document.createElement('td');
    const nm = name + '[' + i + '][' + k + ']';
    if (k === 'cout_total') {
      td.innerHTML = '<input type="text" name="' + nm + '" value="" class="pr-budget-total-cell" readonly>';
    } else if (numCols.indexOf(k) >= 0) {
      let cls = '';
      let on = '';
      if (k === 'quantite') { cls = 'pr-budget-q'; on = budget ? ' oninput="prBudgetCalc()"' : ''; }
      if (k === 'cout_unitaire') { cls = 'pr-budget-pu'; on = budget ? ' oninput="prBudgetCalc()"' : ''; }
      td.innerHTML = '<input type="number" step="any" min="0" name="' + nm + '" value="" class="' + cls + '"' + on + '>';
    } else if (longCols.indexOf(k) >= 0) {
      td.innerHTML = '<textarea name="' + nm + '" rows="2"></textarea>';
    } else {
      td.innerHTML = '<input type="text" name="' + nm + '" value="">';
    }
    tr.appendChild(td);
  });
  const tdDel = document.createElement('td');
  tdDel.innerHTML = '<button type="button" class="pr-del" title="Supprimer" onclick="this.closest(\'tr\').remove();prBudgetCalc();"><i class="fa-solid fa-xmark"></i></button>';
  tr.appendChild(tdDel);
  tbody.appendChild(tr);
  if (budget) prBudgetCalc();
}
function prBudgetCalc() {
  let grand = 0;
  document.querySelectorAll('.pr-budget-table tbody tr').forEach(function(tr) {
    const q = parseFloat((tr.querySelector('.pr-budget-q') || {}).value || 0) || 0;
    const pu = parseFloat((tr.querySelector('.pr-budget-pu') || {}).value || 0) || 0;
    const tot = q * pu;
    const cell = tr.querySelector('.pr-budget-total-cell');
    if (cell) cell.value = tot ? String(Math.round(tot * 100) / 100) : '';
    grand += tot;
  });
  const el = document.getElementById('prBudgetGrandTotal');
  if (el) el.textContent = grand.toLocaleString('fr-FR', {maximumFractionDigits: 0});
}
document.addEventListener('DOMContentLoaded', prBudgetCalc);

/* Annexes : ajouter un à un (ou plusieurs), sans remplacer les précédents */
(function() {
  const picker = document.getElementById('annexesPicker');
  const submitInput = document.getElementById('annexes');
  const list = document.getElementById('prAnnexesPending');
  const addBtn = document.getElementById('prAnnexesAddBtn');
  if (!picker || !submitInput || !list || !addBtn || typeof DataTransfer === 'undefined') return;

  let pending = [];

  function syncSubmitInput() {
    const dt = new DataTransfer();
    pending.forEach(function(f) { dt.items.add(f); });
    submitInput.files = dt.files;
  }

  function render() {
    syncSubmitInput();
    list.innerHTML = '';
    if (!pending.length) return;
    pending.forEach(function(f, i) {
      const li = document.createElement('li');
      li.style.cssText = 'display:flex;align-items:center;gap:10px;margin-bottom:6px;padding:8px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:.85rem;';
      const icon = document.createElement('i');
      icon.className = 'fa-solid fa-paperclip';
      icon.style.color = '#64748b';
      const name = document.createElement('span');
      name.style.flex = '1';
      name.textContent = f.name + ' (' + Math.max(1, Math.round(f.size / 1024)) + ' Ko)';
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = 'Retirer';
      btn.style.cssText = 'border:1px solid #fecaca;background:#fff;color:#dc2626;border-radius:6px;padding:4px 8px;cursor:pointer;font-size:.75rem;font-weight:700;';
      btn.addEventListener('click', function() {
        pending.splice(i, 1);
        render();
      });
      li.appendChild(icon);
      li.appendChild(name);
      li.appendChild(btn);
      list.appendChild(li);
    });
  }

  addBtn.addEventListener('click', function() {
    picker.value = '';
    picker.click();
  });

  picker.addEventListener('change', function() {
    Array.from(picker.files || []).forEach(function(f) {
      const dup = pending.some(function(x) {
        return x.name === f.name && x.size === f.size && x.lastModified === f.lastModified;
      });
      if (!dup) pending.push(f);
    });
    picker.value = '';
    render();
  });

  const form = submitInput.closest('form');
  if (form) {
    form.addEventListener('submit', function() {
      syncSubmitInput();
    });
  }
})();
</script>
<?php }

function recherche_ui_stepper(int $id, int $etape, array $contenu): void {
    $parcours = recherche_parcours_ui();
    echo '<nav class="pr-stepper" aria-label="Étapes du projet">';
    foreach ($parcours as $n => $p) {
        $done = recherche_parcours_rempli($contenu, $n);
        $cls = ($n === $etape ? 'on' : '') . ($done && $n !== $etape ? ' done' : '');
        echo '<a class="pr-step-card ' . $cls . '" href="editer.php?id=' . $id . '&etape=' . $n . '">';
        echo '<div class="pr-sn">Étape ' . $n . '</div>';
        echo '<div class="pr-st">' . e($p['emoji'] . ' ' . $p['titre']) . '</div>';
        echo '</a>';
    }
    echo '</nav>';
}

function recherche_ui_a4_bloc(string $label, $val): void {
    $t = trim(is_string($val) ? $val : '');
    echo '<li><strong>' . e($label) . '</strong>';
    if ($t === '') echo '<div class="val pr-a4-empty">—</div>';
    else echo '<div class="val">' . nl2br(e($t)) . '</div>';
    echo '</li>';
}

/** Contenu seul (sans répéter le titre de section) */
function recherche_ui_a4_val($val): void {
    $t = trim(is_string($val) ? $val : '');
    if ($t === '') echo '<div class="val pr-a4-empty">—</div>';
    else echo '<div class="val">' . nl2br(e($t)) . '</div>';
}

function recherche_ui_a4_table(array $cols, array $rows): void {
    if (!$rows) {
        echo '<div class="val pr-a4-empty">—</div>';
        return;
    }
    echo '<table><thead><tr>';
    foreach ($cols as $c) echo '<th>' . e($c) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach (array_keys($cols) as $k) echo '<td>' . nl2br(e($row[$k] ?? '')) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

function recherche_ui_a4_list_open(): void { echo '<ul class="pr-a4-list">'; }
function recherche_ui_a4_list_close(): void { echo '</ul>'; }

/** Aperçu feuille A4 — §§1-13 : contenu sans sous-listes ; tableaux gardés ; §§14+ : sous-titres gardés */
function recherche_ui_synthese_a4(array $d): void {
    $c = $d['contenu_arr'];
    $p = $c['presentation'];
    $cx = $c['contexte'];
    $li = $c['litterature'];
    $pr = $c['problematique'];
    $ob = $c['objectifs'];
    $m = $c['methode'];
    $me = $c['mise_en_oeuvre'];
    $b = $c['budget_fin'];
    $entete = '../../img_ordre_mission/entete_crict_new.png';
    $enteteOk = is_file(__DIR__ . '/../../img_ordre_mission/entete_crict_new.png');
    $pid = (int)$d['id'];
    ?>
<div class="pr-a4" id="prA4">
  <div class="pr-a4-header">
    <?php if ($enteteOk): ?><img src="<?= e($entete) ?>" alt="CRICT"><?php endif; ?>
    <h1>PLAN DE RÉDACTION — PROJET DE RECHERCHE</h1>
    <div class="meta"><?= e($d['code']) ?> · <?= e($d['titre']) ?></div>
  </div>

  <h2>1. Présentation institutionnelle du projet</h2>
  <?php recherche_ui_a4_val($p['institution_porteuse'] ?? ''); ?>
  <?php recherche_ui_a4_val($p['ancrage'] ?? ''); ?>
  <h3>Équipe de recherche</h3>
  <?php recherche_ui_a4_table([
      'membre' => 'Membres',
      'grade' => 'Grade et spécialité',
      'institution' => 'Institution',
      'responsabilites' => 'Responsabilités dans le projet',
      'temps' => 'Temps consacré',
  ], $p['equipe'] ?? []); ?>

  <h2>2. Contexte et justification</h2>
  <h3>Contexte général</h3>
  <?php recherche_ui_a4_val($cx['situation_actuelle'] ?? ''); ?>
  <?php recherche_ui_a4_val($cx['justification_scientifique'] ?? ''); ?>
  <?php recherche_ui_a4_val($cx['justification_socio'] ?? ''); ?>

  <h2>3. Revue de la littérature et cadre de référence</h2>
  <?php recherche_ui_a4_val($li['revue_critique'] ?? ''); ?>
  <?php recherche_ui_a4_val($li['lacune'] ?? ''); ?>
  <?php recherche_ui_a4_val($li['cadre_theorique'] ?? ''); ?>
  <?php recherche_ui_a4_val($li['cadre_conceptuel'] ?? ''); ?>

  <h2>4. Problématique de la recherche</h2>
  <?php recherche_ui_a4_val($pr['constat'] ?? ''); ?>
  <?php recherche_ui_a4_val($pr['probleme_scientifique'] ?? ''); ?>
  <?php recherche_ui_a4_val($pr['question_principale'] ?? ''); ?>
  <?php recherche_ui_a4_val($pr['questions_specifiques'] ?? ''); ?>
  <?php recherche_ui_a4_val($pr['hypothese_generale'] ?? ''); ?>
  <?php recherche_ui_a4_val($pr['hypotheses_specifiques'] ?? ''); ?>
  <?php recherche_ui_a4_val($pr['delimitation'] ?? ''); ?>

  <h2>5. Objectifs de la recherche</h2>
  <?php recherche_ui_a4_val($ob['objectif_general'] ?? ''); ?>
  <?php recherche_ui_a4_val($ob['objectifs_specifiques'] ?? ''); ?>
  <h3>Matrice de cohérence scientifique</h3>
  <?php recherche_ui_a4_table([
      'question' => 'Questions de recherche',
      'hypothese' => 'Hypothèses',
      'objectif' => 'Objectifs spécifiques',
      'methode' => 'Méthodes',
      'resultat' => 'Résultats attendus',
  ], $ob['matrice'] ?? []); ?>

  <h2>6. Originalité et contribution scientifique</h2>
  <?php recherche_ui_a4_val($m['originalite'] ?? ''); ?>
  <?php recherche_ui_a4_val($m['contribution_theorique'] ?? ''); ?>
  <?php recherche_ui_a4_val($m['contribution_methodo'] ?? ''); ?>
  <?php recherche_ui_a4_val($m['contribution_pratique'] ?? ''); ?>

  <h2>7. Méthodologie de recherche</h2>
  <?php recherche_ui_a4_val($m['type_approche'] ?? ''); ?>
  <?php recherche_ui_a4_val($m['site_cadre'] ?? ''); ?>
  <?php recherche_ui_a4_val($m['population'] ?? ''); ?>
  <?php recherche_ui_a4_val($m['echantillonnage'] ?? ''); ?>
  <h3>Variables et indicateurs</h3>
  <?php recherche_ui_a4_table([
      'variable' => 'Variable ou dimension',
      'definition' => 'Définition opérationnelle',
      'indicateur' => 'Indicateur',
      'instrument' => 'Instrument de mesure',
  ], $m['variables'] ?? []); ?>
  <?php recherche_ui_a4_val($m['techniques_collecte'] ?? ''); ?>
  <?php recherche_ui_a4_val($m['procedure_collecte'] ?? ''); ?>
  <?php recherche_ui_a4_val($m['methodes_analyse'] ?? ''); ?>
  <?php recherche_ui_a4_val($m['validite'] ?? ''); ?>
  <?php recherche_ui_a4_val($m['limites'] ?? ''); ?>

  <h2>8. Considérations éthiques et protection des données</h2>
  <?php recherche_ui_a4_val($m['ethique'] ?? ''); ?>

  <h2>9. Résultats, livrables et effets attendus</h2>
  <?php recherche_ui_a4_val($me['resultats_scientifiques'] ?? ''); ?>
  <?php recherche_ui_a4_val($me['livrables'] ?? ''); ?>
  <?php recherche_ui_a4_val($me['effets_attendus'] ?? ''); ?>
  <?php recherche_ui_a4_val($me['impact_attendu'] ?? ''); ?>

  <h2>10. Plan de travail et chronogramme</h2>
  <?php recherche_ui_a4_table([
      'activite' => 'Activités',
      'responsable' => 'Responsable',
      'livrables' => 'Livrables',
      'debut' => 'Début',
      'fin' => 'Fin',
      'indicateurs' => 'Indicateurs',
  ], $me['chronogramme'] ?? []); ?>

  <h2>11. Gouvernance et gestion du projet</h2>
  <?php recherche_ui_a4_val($me['organisation'] ?? ''); ?>
  <?php recherche_ui_a4_val($me['coordination'] ?? ''); ?>
  <?php recherche_ui_a4_val($me['partenariats'] ?? ''); ?>

  <h2>12. Suivi-évaluation et assurance qualité</h2>
  <?php recherche_ui_a4_table([
      'resultats' => 'Résultats',
      'indicateurs' => 'Indicateurs',
      'initial' => 'Situation initiale',
      'cible' => 'Cible',
      'source' => 'Source de vérification',
      'frequence' => 'Fréquence',
  ], $me['suivi_eval'] ?? []); ?>

  <h2>13. Analyse des risques</h2>
  <?php recherche_ui_a4_table([
      'risque' => 'Risques',
      'proba' => 'Probabilité',
      'impact' => 'Impact',
      'attenuation' => 'Mesures d\'atténuation',
      'responsable' => 'Responsable',
  ], $me['risques'] ?? []); ?>

  <h2>14. Diffusion, publication et valorisation</h2>
  <?php recherche_ui_a4_list_open(); ?>
    <?php recherche_ui_a4_bloc('Publications scientifiques', $b['publications'] ?? ''); ?>
    <?php recherche_ui_a4_bloc('Communications scientifiques', $b['communications'] ?? ''); ?>
    <?php recherche_ui_a4_bloc('Vulgarisation', $b['vulgarisation'] ?? ''); ?>
    <?php recherche_ui_a4_bloc('Valorisation et propriété intellectuelle', $b['valorisation_pi'] ?? ''); ?>
  <?php recherche_ui_a4_list_close(); ?>

  <h2>15. Durabilité du projet</h2>
  <?php recherche_ui_a4_val($b['durabilite'] ?? ''); ?>

  <h2>16. Budget détaillé</h2>
  <?php recherche_ui_a4_table([
      'activite' => 'Activités',
      'quantite' => 'Quantité',
      'cout_unitaire' => 'Coût unitaire',
      'cout_total' => 'Coût total',
      'justification' => 'Justification',
  ], $b['budget'] ?? []); ?>

  <h2>17. Références bibliographiques</h2>
  <?php recherche_ui_a4_val($b['references'] ?? ''); ?>

  <h2>18. Annexes</h2>
  <?php
  $files = $b['annexes_fichiers'] ?? [];
  if (!$files) {
      recherche_ui_a4_val('');
  } else {
      echo '<ul class="pr-a4-list">';
      foreach ($files as $f) {
          $nom = $f['nom'] ?? ($f['fichier'] ?? 'fichier');
          $url = 'download_annexe.php?id=' . $pid . '&f=' . rawurlencode((string)($f['fichier'] ?? ''));
          echo '<li><a href="' . e($url) . '" target="_blank">' . e($nom) . '</a></li>';
      }
      echo '</ul>';
  }
  ?>
</div>
    <?php
}

/* Compat aperçu voir.php */
function recherche_ui_txt(?string $v): string {
    $t = trim((string)$v);
    if ($t === '') return '<div class="pr-a4-empty">—</div>';
    return '<div>' . nl2br(e($t)) . '</div>';
}
function recherche_ui_tableau_voir(array $cols, array $rows): string {
    ob_start();
    recherche_ui_a4_table($cols, $rows);
    return (string)ob_get_clean();
}
function recherche_ui_topbar(array $d, int $etape = 0, string $back_url = 'index.php', bool $show_apercu = true): void {
    // conservé pour index/voir — barre minimale
    echo '<div style="margin-bottom:12px;"><a href="' . e($back_url) . '" class="pr-btn pr-btn-ghost">← Liste</a></div>';
}
function recherche_ui_voir_section(int $num, string $titre, callable $render, bool $open = false): void {
    echo '<div class="pr-card"><div class="pr-card-h">' . (int)$num . '. ' . e($titre) . '</div>';
    $render();
    echo '</div>';
}
function recherche_ui_textarea(string $name, string $label, string $value, string $help = '', int $rows = 3): void {
    recherche_ui_field($name, $label, $value, '', $rows);
}
