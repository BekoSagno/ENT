<?php
// modules/subventions/rdb/_form_js.php — Script formulaire RDB
/** @var string $meta_json */
/** @var string $etats_json */
/** @var string $ro_js */
/** @var int $step_js */
?>
<script>
const META = <?= $meta_json ?>;
const ETATS = <?= $etats_json ?>;
const RO = <?= $ro_js ?>;
let currentStep = <?= (int)$step_js ?>;
let dirty = false;
let navigating = false;

function goStep(n){
  currentStep = n;
  document.querySelectorAll('.rdb-panel').forEach(function(p){ p.classList.toggle('on', +p.dataset.panel === n); });
  document.querySelectorAll('.rdb-step').forEach(function(s){
    var v = +s.dataset.s;
    s.classList.toggle('active', v === n);
    s.classList.toggle('done', v < n);
  });
  window.scrollTo({top:0, behavior:'smooth'});
  setTimeout(refreshCells, 30);
}

function resetFlags(){
  var a = document.getElementById('rdbLeave');
  var b = document.getElementById('rdbPrefill');
  var c = document.getElementById('rdbJoinOnly');
  if (a) a.value = '0';
  if (b) b.value = '0';
  if (c) c.value = '0';
}

function saveAndGo(n){
  if (RO) { goStep(n); return; }
  var form = document.getElementById('rdbForm');
  var step = document.getElementById('rdbStep');
  if (!form || !step) { goStep(n); return; }
  navigating = true;
  dirty = false;
  resetFlags();
  step.value = String(n);
  form.submit();
}

function saveAndLeave(){
  if (RO) { location.href = 'index.php'; return; }
  if (!confirm('Enregistrer le brouillon et quitter ?')) return;
  var form = document.getElementById('rdbForm');
  if (!form) return;
  navigating = true;
  dirty = false;
  resetFlags();
  document.getElementById('rdbLeave').value = '1';
  document.getElementById('rdbStep').value = String(currentStep);
  form.submit();
}

function doPrefill(){
  if (RO) return;
  if (!confirm('Remplir automatiquement depuis Subventions ?\n\nLes lignes Ressources, Personnel et Fonctionnement seront remplacées par les données de la période.')) return;
  navigating = true;
  dirty = false;
  resetFlags();
  document.getElementById('rdbPrefill').value = '1';
  document.getElementById('rdbStep').value = '3';
  document.getElementById('rdbForm').submit();
}

function joinDoc(){
  var f = document.getElementById('rdbAnnexe');
  if (!f || !f.files || !f.files.length) {
    alert('Choisissez d\'abord un fichier a joindre.');
    return;
  }
  navigating = true;
  dirty = false;
  resetFlags();
  document.getElementById('rdbJoinOnly').value = '1';
  document.getElementById('rdbStep').value = '6';
  document.getElementById('rdbForm').submit();
}

function navStep(n){
  if (RO) { goStep(n); return; }
  if (n === currentStep) return;
  saveAndGo(n);
}

function formatGNF(v){
  var digits = String(v || '').replace(/[^0-9]/g, '');
  if (!digits) return '';
  return digits.replace(/\B(?=(?:[0-9]{3})+(?![0-9]))/g, ' ');
}

function fitNum(el){
  if (!el || !el.classList.contains('rdb-num')) return;
  el.value = formatGNF(el.value);
}

function autosize(el){
  if (!el || el.tagName !== 'TEXTAREA') return;
  el.style.height = 'auto';
  el.style.height = Math.max(52, el.scrollHeight) + 'px';
  el.scrollTop = 0;
}

function refreshCells(){
  document.querySelectorAll('.rdb-tbl-wrap').forEach(function(w){ w.scrollLeft = 0; });
  document.querySelectorAll('textarea.rdb-ta, .rdb-field textarea.form-control').forEach(autosize);
  document.querySelectorAll('input.rdb-num').forEach(fitNum);
}

var ligneIdx = {};
for (var t = 1; t <= 8; t++) {
  ligneIdx[t] = document.querySelectorAll('#tbl-' + t + ' tbody tr').length;
}

function addLigne(tn){
  var tbody = document.querySelector('#tbl-' + tn + ' tbody');
  if (!tbody) return;
  var idx = ligneIdx[tn]++;
  var cols = (META[tn] && META[tn].colonnes) ? META[tn].colonnes : {};
  var calc = (META[tn] && META[tn].cols_calc) ? META[tn].cols_calc : [];
  var numCols = ['credit','paye','montant_budgete','montant_depense','t1','t2','t3','t4'];
  var dateCols = ['date_debut','date_fin'];
  var tr = document.createElement('tr');
  tr.className = 'rdb-ligne';
  tr.dataset.tn = tn;
  var html = '<td class="rdb-cell-text"><textarea name="lignes[' + tn + '][' + idx + '][libelle]" class="rdb-ta" rows="2" placeholder="Saisir le libellé…"></textarea></td>';
  Object.keys(cols).forEach(function(ck){
    var cl = cols[ck];
    if (calc.indexOf(ck) !== -1) {
      html += '<td class="rdb-taux-cell">auto</td>';
      return;
    }
    if (ck === 'etat') {
      html += '<td class="rdb-cell-etat"><select name="lignes[' + tn + '][' + idx + '][etat]" class="rdb-sel"><option value="">Choisir…</option>';
      Object.keys(ETATS).forEach(function(ek){ html += '<option value="' + ek + '">' + ETATS[ek] + '</option>'; });
      html += '</select></td>';
    } else if (dateCols.indexOf(ck) !== -1) {
      html += '<td class="rdb-cell-date"><input type="date" name="lignes[' + tn + '][' + idx + '][' + ck + ']" class="rdb-date"></td>';
    } else if (numCols.indexOf(ck) !== -1) {
      html += '<td class="rdb-cell-num"><input type="text" name="lignes[' + tn + '][' + idx + '][' + ck + ']" class="rdb-num" inputmode="numeric" placeholder="0" autocomplete="off"></td>';
    } else {
      html += '<td class="rdb-cell-text"><textarea name="lignes[' + tn + '][' + idx + '][' + ck + ']" class="rdb-ta" rows="2" placeholder="' + cl + '"></textarea></td>';
    }
  });
  html += '<td class="rdb-cell-text"><textarea name="lignes[' + tn + '][' + idx + '][commentaire]" class="rdb-ta" rows="2" placeholder="Observation (facultatif)"></textarea></td>';
  html += '<td class="rdb-cell-action"><button type="button" class="rdb-rm" title="Retirer cette ligne">Retirer</button></td>';
  tr.innerHTML = html;
  tbody.appendChild(tr);
  dirty = true;
  refreshCells();
}

document.addEventListener('click', function(e){
  var addBtn = e.target.closest('.rdb-add-line');
  if (addBtn) { addLigne(+addBtn.dataset.tn); return; }

  var rm = e.target.closest('.rdb-rm');
  if (rm) {
    e.preventDefault();
    e.stopPropagation();
    if (!confirm('Retirer cette ligne ?')) return;
    var tr = rm.closest('tr.rdb-ligne');
    if (!tr) return;
    var tbody = tr.parentElement;
    var tn = parseInt(tr.getAttribute('data-tn') || '0', 10);
    tr.remove();
    dirty = true;
    // Toujours garder au moins une ligne vide pour la saisie
    if (tbody && tbody.querySelectorAll('tr').length === 0 && tn > 0) {
      addLigne(tn);
    }
    return;
  }

  var docDel = e.target.closest('.rdb-doc-del');
  if (docDel) {
    if (!confirm('Supprimer ce document ?')) return;
    document.getElementById('docDelId').value = docDel.getAttribute('data-doc-id');
    document.getElementById('docDelForm').submit();
  }
});

var rdbFormEl = document.getElementById('rdbForm');
if (rdbFormEl) {
  rdbFormEl.addEventListener('input', function(e){
    dirty = true;
    if (e.target.tagName === 'TEXTAREA') autosize(e.target);
    if (e.target.classList && e.target.classList.contains('rdb-num')) fitNum(e.target);
  });
}

var btnLeave = document.getElementById('btnLeave');
if (btnLeave) {
  btnLeave.addEventListener('click', function(){
    if (RO) { location.href = 'index.php'; return; }
    if (!confirm('Quitter ? Votre saisie sera enregistree en brouillon.')) return;
    navigating = true;
    dirty = false;
    resetFlags();
    document.getElementById('rdbLeave').value = '1';
    document.getElementById('rdbStep').value = String(currentStep);
    document.getElementById('rdbForm').submit();
  });
}

window.addEventListener('beforeunload', function(e){
  if (RO || navigating || !dirty) return;
  e.preventDefault();
  e.returnValue = '';
});

document.addEventListener('DOMContentLoaded', function(){
  refreshCells();
  goStep(currentStep);
});
</script>
