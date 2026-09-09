<?php
// modules/projet_article/_ui.php — UI parcours article scientifique

function article_ui_styles(): void { ?>
<style>
.pa-wrap{max-width:960px;margin:0 auto;}
.pa-toolbar{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:18px;}
.pa-toolbar .spacer{flex:1;min-width:8px;}
.pa-toolbar-filters{
  display:flex;align-items:center;gap:0;
  background:#fff;border:1.5px solid #d8e0ea;border-radius:10px;overflow:hidden;
  max-width:100%;
}
.pa-toolbar-filters .pa-q{
  width:220px;max-width:42vw;min-width:140px;border:none;border-radius:0;
  padding:9px 12px 9px 36px;box-shadow:none;background:transparent;
}
.pa-toolbar-filters .pa-q:focus{outline:none;box-shadow:none;}
.pa-toolbar-filters .pa-st{
  width:auto;min-width:150px;border:none;border-left:1px solid #e2e8f0;border-radius:0;
  padding:9px 12px;box-shadow:none;background:#f8fafc;
}
.pa-toolbar-filters .pa-st:focus{outline:none;box-shadow:none;}
.pa-toolbar-filters .pa-go{
  border:none;border-left:1px solid #e2e8f0;border-radius:0;
  background:#f1f5f9;color:#334155;padding:9px 14px;cursor:pointer;
}
.pa-toolbar-filters .pa-go:hover{background:#e2e8f0;color:#0f172a;}
.pa-toolbar-search-wrap{position:relative;display:flex;align-items:center;}
.pa-toolbar-search-wrap > i{
  position:absolute;left:12px;color:#94a3b8;font-size:.8rem;pointer-events:none;
}
.pa-list-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;}
.pa-list-card{
  display:flex;flex-direction:column;gap:10px;
  background:#fff;border:1px solid #d8e0ea;border-radius:12px;padding:16px 18px;
  color:inherit;transition:border-color .12s,box-shadow .12s;
}
.pa-list-card:hover{border-color:#93c5fd;box-shadow:0 4px 16px rgba(13,50,120,.08);}
.pa-list-top{display:flex;justify-content:space-between;align-items:center;gap:8px;}
.pa-list-code{font-size:.72rem;font-weight:700;color:#64748b;letter-spacing:.02em;}
.pa-list-badge{display:inline-flex;padding:3px 9px;border-radius:6px;font-size:.7rem;font-weight:700;}
.pa-list-titre{font-size:.95rem;font-weight:800;color:#0f172a;line-height:1.35;margin:0;}
.pa-list-prog-row{display:flex;justify-content:space-between;align-items:center;font-size:.75rem;color:#64748b;margin-bottom:5px;}
.pa-list-prog-row strong{color:#0f172a;font-weight:800;}
.pa-prog{height:6px;background:#e2e8f0;border-radius:4px;overflow:hidden;}
.pa-prog-fill{height:100%;background:#2563eb;border-radius:4px;}
.pa-list-meta{font-size:.72rem;color:#64748b;margin-top:auto;}
.pa-list-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding-top:4px;border-top:1px solid #eef2f7;margin-top:2px;}

.pa-page{max-width:1100px;margin:0 auto;padding-bottom:40px;}
.pa-stepper{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:20px;}
.pa-step-card{display:block;text-decoration:none;background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;
  padding:12px 10px;text-align:center;transition:border-color .15s,box-shadow .15s,background .15s;min-height:78px;}
.pa-step-card:hover{border-color:#93c5fd;background:#f8fbff;}
.pa-step-card .pa-sn{font-size:.7rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px;}
.pa-step-card .pa-st{font-size:.8rem;font-weight:800;color:#0f172a;line-height:1.25;}
.pa-step-card.on{border-color:#2563eb;background:#eff6ff;box-shadow:0 4px 14px rgba(37,99,235,.18);}
.pa-step-card.on .pa-sn{color:#2563eb;}
.pa-step-card.on .pa-st{color:#1e40af;}
.pa-step-card.done{border-color:#86efac;background:#f0fdf4;}
.pa-step-card.done .pa-sn{color:#16a34a;}
.pa-step-card.done .pa-st{color:#166534;}
.pa-step-card.done.on{border-color:#2563eb;background:#eff6ff;}

.pa-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,.04);padding:22px 24px;margin-bottom:16px;}
.pa-card-h{display:flex;align-items:center;gap:10px;font-size:1.05rem;font-weight:800;color:#0f172a;margin:0 0 16px;}
.pa-sec-num{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 8px;
  border-radius:8px;background:#1e40af;color:#fff;font-size:.78rem;font-weight:800;}
.pa-list{margin:0;padding:0 0 0 4px;list-style:none;}
.pa-list > .pa-field,.pa-list > .pa-item{position:relative;padding-left:16px;margin-bottom:14px;}
.pa-list > .pa-field::before,.pa-list > .pa-item::before{content:"";position:absolute;left:0;top:10px;width:7px;height:7px;border-radius:50%;background:#2563eb;}
.pa-label{display:block;font-size:.75rem;font-weight:800;color:#0f172a;margin-bottom:6px;}
.pa-input,.pa-area{
  width:100%;box-sizing:border-box;border:1px solid #94a3b8;border-radius:10px;background:#fff;color:#0f172a;
  padding:10px 12px;font-size:.92rem;font-family:inherit;line-height:1.55;font-weight:500;
}
.pa-area{resize:vertical;min-height:80px;}
.pa-input:focus,.pa-area:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
.pa-field{margin-bottom:14px;}
.pa-field:last-child{margin-bottom:0;}
.pa-req{color:#dc2626;}

.pa-tbl-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;}
.pa-tbl-head h4{margin:0;font-size:.9rem;font-weight:800;color:#0f172a;}
.pa-tbl-wrap{overflow-x:auto;border:1px solid #cbd5e1;border-radius:12px;}
.pa-tbl{width:100%;border-collapse:collapse;text-align:left;font-size:.82rem;min-width:560px;}
.pa-tbl th{background:#e2e8f0;color:#0f172a;font-weight:800;padding:10px 8px;border-bottom:1px solid #cbd5e1;white-space:nowrap;}
.pa-tbl td{padding:6px;border-bottom:1px solid #e2e8f0;vertical-align:top;}
.pa-tbl input,.pa-tbl textarea{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:6px;padding:6px 8px;font:inherit;}
.pa-btn-add{display:inline-flex;align-items:center;gap:6px;border:1px dashed #94a3b8;background:#f8fafc;border-radius:8px;padding:6px 12px;font-size:.78rem;font-weight:700;cursor:pointer;color:#2563eb;}
.pa-btn-add:hover{background:#eff6ff;border-color:#2563eb;}
.pa-del{border:none;background:transparent;color:#94a3b8;cursor:pointer;padding:4px;}
.pa-del:hover{color:#dc2626;}

.pa-nav{display:flex;align-items:center;gap:10px;margin-top:18px;flex-wrap:wrap;}
.pa-nav .spacer{flex:1;}
.pa-btn{display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:10px 16px;font-size:.88rem;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;}
.pa-btn-primary{background:#2563eb;color:#fff;}
.pa-btn-primary:hover{background:#1d4ed8;color:#fff;}
.pa-btn-ghost{background:#fff;border-color:#cbd5e1;color:#334155;}
.pa-btn-ghost:hover{background:#f8fafc;color:#0f172a;}

.pa-a4{background:#fff;border:1px solid #94a3b8;border-radius:4px;padding:18mm 16mm;max-width:210mm;margin:0 auto;box-shadow:0 8px 30px rgba(15,23,42,.08);line-height:1.65;color:#0f172a;}
.pa-a4-header{text-align:center;border-bottom:2px solid #1e3a8a;padding-bottom:12px;margin-bottom:18px;}
.pa-a4-header img{max-height:64px;margin-bottom:8px;max-width:100%;height:auto;}
.pa-a4-header .doc-title{font-size:1.15rem;margin:0 0 6px;color:#0f172a;font-weight:800;}
.pa-a4-header .meta{font-size:.8rem;color:#334155;font-weight:600;}
.pa-a4 h1{font-size:1.2rem;font-weight:800;margin:0 0 12px;line-height:1.35;text-align:center;color:#0f172a;}
.pa-a4 .pa-auteurs{text-align:center;font-size:.9rem;margin-bottom:18px;color:#334155;}
.pa-a4 h2{font-size:1.05rem;font-weight:800;margin:22px 0 8px;border-bottom:none;padding-bottom:0;color:#0f172a;}
.pa-a4 p,.pa-a4 .pa-a4-val{white-space:pre-wrap;margin:0 0 10px;font-size:.9rem;}
.pa-a4-empty{color:#64748b;font-style:italic;}
.pa-a4-toolbar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;}
.pa-kw{font-size:.85rem;margin-bottom:14px;}
.pa-kw strong{font-weight:800;}
@media print{
  .sidebar,.topbar,.header,.app-sidebar,.app-header,.pa-stepper,.pa-a4-toolbar,.pa-nav,
  .flash,.btn,nav,aside,#sidebar,.main-header{display:none!important;}
  .pa-page,.pa-a4,.main-content,.content,.page-content{max-width:none!important;margin:0!important;padding:0!important;box-shadow:none!important;border:none!important;}
  .pa-a4{width:100%;padding:10mm;}
  body{background:#fff!important;}
}
@media(max-width:800px){
  .pa-stepper{grid-template-columns:repeat(2,1fr);}
  .pa-a4{padding:24px 18px;}
}
</style>
<?php }

function article_ui_field(string $name, string $label, string $value, string $placeholder = '', int $rows = 3, bool $required = false): void {
    $req = $required ? ' <span class="pa-req">*</span>' : '';
    echo '<div class="pa-field">';
    echo '<label class="pa-label" for="' . e($name) . '">' . e($label) . $req . '</label>';
    if ($rows <= 1) {
        echo '<input class="pa-input" type="text" name="' . e($name) . '" id="' . e($name) . '" value="' . e($value) . '"'
            . ($placeholder !== '' ? ' placeholder="' . e($placeholder) . '"' : '')
            . ($required ? ' required' : '') . '>';
    } else {
        echo '<textarea class="pa-area" name="' . e($name) . '" id="' . e($name) . '" rows="' . (int)$rows . '"'
            . ($placeholder !== '' ? ' placeholder="' . e($placeholder) . '"' : '') . '>' . e($value) . '</textarea>';
    }
    echo '</div>';
}

function article_ui_sec_open(string $num, string $titre): void {
    echo '<div class="pa-card"><h3 class="pa-card-h"><span class="pa-sec-num">' . e($num) . '</span> ' . e($titre) . '</h3>';
}
function article_ui_sec_close(): void {
    echo '</div>';
}
function article_ui_list_open(): void {
    echo '<div class="pa-list">';
}
function article_ui_list_close(): void {
    echo '</div>';
}

function article_ui_tableau(string $name, array $cols, array $rows, string $label = ''): void {
    if ($rows === []) $rows = [array_fill_keys(array_keys($cols), '')];
    $uid = 'tbl_' . preg_replace('/[^a-z0-9_]/i', '', $name);
    ?>
<div class="pa-field" id="<?= e($uid) ?>" data-name="<?= e($name) ?>" data-cols="<?= e(json_encode($cols)) ?>">
  <div class="pa-tbl-head">
    <h4><?= e($label !== '' ? $label : ucfirst($name)) ?></h4>
    <button type="button" class="pa-btn-add" onclick="paAddRow('<?= e($uid) ?>')"><i class="fa-solid fa-plus"></i> Ajouter une ligne</button>
  </div>
  <div class="pa-tbl-wrap">
    <table class="pa-tbl">
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
              $isLong = in_array($k, ['affiliations'], true);
          ?>
          <td>
            <?php if ($isLong): ?>
              <textarea name="<?= $nm ?>" rows="2"><?= $val ?></textarea>
            <?php else: ?>
              <input type="text" name="<?= $nm ?>" value="<?= $val ?>">
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td><button type="button" class="pa-del" title="Supprimer" onclick="this.closest('tr').remove();"><i class="fa-solid fa-xmark"></i></button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
    <?php
}

function article_ui_tableau_script(): void { ?>
<script>
function paAddRow(uid) {
  const block = document.getElementById(uid);
  if (!block) return;
  const tbody = block.querySelector('tbody');
  const name = block.getAttribute('data-name');
  const cols = JSON.parse(block.getAttribute('data-cols') || '{}');
  const i = tbody.querySelectorAll('tr').length;
  const longCols = ['affiliations'];
  const tr = document.createElement('tr');
  Object.keys(cols).forEach(function(k) {
    const td = document.createElement('td');
    const nm = name + '[' + i + '][' + k + ']';
    if (longCols.indexOf(k) >= 0) {
      td.innerHTML = '<textarea name="' + nm + '" rows="2"></textarea>';
    } else {
      td.innerHTML = '<input type="text" name="' + nm + '" value="">';
    }
    tr.appendChild(td);
  });
  const tdDel = document.createElement('td');
  tdDel.innerHTML = '<button type="button" class="pa-del" title="Supprimer" onclick="this.closest(\'tr\').remove();"><i class="fa-solid fa-xmark"></i></button>';
  tr.appendChild(tdDel);
  tbody.appendChild(tr);
}

(function(){
  const addBtn = document.getElementById('paAnnexesAddBtn');
  const picker = document.getElementById('annexesPicker');
  const hidden = document.getElementById('annexes');
  const list = document.getElementById('paAnnexesPending');
  if (!addBtn || !picker || !hidden || !list) return;
  let bag = new DataTransfer();
  function refresh() {
    list.innerHTML = '';
    Array.from(bag.files).forEach(function(f, idx) {
      const li = document.createElement('li');
      li.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:.85rem;';
      li.innerHTML = '<i class="fa-solid fa-file" style="color:#2563eb;"></i><span style="flex:1;">' + f.name + '</span>'
        + '<button type="button" class="pa-del" data-i="' + idx + '"><i class="fa-solid fa-xmark"></i></button>';
      li.querySelector('button').onclick = function() {
        const next = new DataTransfer();
        Array.from(bag.files).forEach(function(ff, j){ if (j !== idx) next.items.add(ff); });
        bag = next; hidden.files = bag.files; refresh();
      };
      list.appendChild(li);
    });
  }
  addBtn.onclick = function(){ picker.click(); };
  picker.onchange = function(){
    Array.from(picker.files || []).forEach(function(f){ bag.items.add(f); });
    picker.value = '';
    hidden.files = bag.files;
    refresh();
  };
})();
</script>
<?php }

function article_ui_stepper(int $id, int $etape, array $contenu): void {
    $parcours = article_parcours_ui();
    echo '<div class="pa-stepper">';
    foreach ($parcours as $n => $p) {
        $done = !empty($p['synthese']) ? false : article_parcours_rempli($contenu, $n);
        $cls = 'pa-step-card';
        if ($n === $etape) $cls .= ' on';
        if ($done) $cls .= ' done';
        echo '<a class="' . $cls . '" href="editer.php?id=' . $id . '&etape=' . $n . '">';
        echo '<div class="pa-sn">Étape ' . $n . '</div>';
        echo '<div class="pa-st">' . e($p['titre']) . '</div>';
        echo '</a>';
    }
    echo '</div>';
}

function article_ui_a4_val($val): void {
    $t = trim((string)$val);
    if ($t === '') {
        echo '<p class="pa-a4-empty">— Non renseigné —</p>';
    } else {
        echo '<div class="pa-a4-val">' . nl2br(e($t)) . '</div>';
    }
}

function article_ui_synthese_a4(array $d): void {
    $c = $d['contenu_arr'];
    $id = $c['identite'];
    $r = $c['resumes'];
    $b = $c['corps'];
    $m = $c['mentions'];
    $f = $c['fin'];
    $auteurs = $id['auteurs'] ?? [];
    $entete = '../../img_ordre_mission/entete_crict_new.png';
    $enteteOk = is_file(__DIR__ . '/../../img_ordre_mission/entete_crict_new.png');
    ?>
<div class="pa-a4" id="paA4">
  <div class="pa-a4-header">
    <?php if ($enteteOk): ?><img src="<?= e($entete) ?>" alt="CRICT"><?php endif; ?>
    <div class="doc-title">ARTICLE SCIENTIFIQUE</div>
    <div class="meta"><?= e($d['code']) ?> · <?= e($d['titre']) ?></div>
  </div>

  <h1><?= e($d['titre']) ?></h1>
  <div class="pa-auteurs">
    <?php if ($auteurs): ?>
      <div>
        <?php
        $noms = [];
        foreach ($auteurs as $a) {
            $n = trim((string)($a['nom'] ?? ''));
            if ($n !== '') $noms[] = $n;
        }
        echo e(implode(' · ', $noms));
        ?>
      </div>
      <?php foreach ($auteurs as $a):
          $aff = trim((string)($a['affiliations'] ?? ''));
          if ($aff === '') continue;
      ?>
        <div style="font-size:.8rem;color:#64748b;"><?= e(($a['nom'] ?? '') . ' — ' . $aff) ?></div>
      <?php endforeach; ?>
    <?php else: ?>
      <span class="pa-a4-empty">Auteurs non renseignés</span>
    <?php endif; ?>
  </div>

  <h2>Résumé</h2>
  <?php article_ui_a4_val($r['resume'] ?? ''); ?>
  <div class="pa-kw"><strong>Mots-clés :</strong> <?= e(trim((string)($r['mots_cles'] ?? '')) ?: '—') ?></div>

  <h2>Abstract</h2>
  <?php article_ui_a4_val($r['abstract'] ?? ''); ?>
  <div class="pa-kw"><strong>Keywords :</strong> <?= e(trim((string)($r['keywords'] ?? '')) ?: '—') ?></div>

  <h2>1. Introduction</h2>
  <?php article_ui_a4_val($b['introduction'] ?? ''); ?>
  <h2>2. Revue de littérature / travaux connexes</h2>
  <?php article_ui_a4_val($b['litterature'] ?? ''); ?>
  <h2>3. Matériels et méthodes</h2>
  <?php article_ui_a4_val($b['methodes'] ?? ''); ?>
  <h2>4. Résultats</h2>
  <?php article_ui_a4_val($b['resultats'] ?? ''); ?>
  <h2>5. Discussion</h2>
  <?php article_ui_a4_val($b['discussion'] ?? ''); ?>
  <h2>6. Conclusion et perspectives</h2>
  <?php article_ui_a4_val($b['conclusion'] ?? ''); ?>

  <h2>Contributions des auteurs</h2>
  <?php article_ui_a4_val($m['contributions'] ?? ''); ?>
  <h2>Financement</h2>
  <?php article_ui_a4_val($m['financement'] ?? ''); ?>
  <h2>Conflits d’intérêts</h2>
  <?php article_ui_a4_val($m['conflits'] ?? ''); ?>
  <h2>Disponibilité des données</h2>
  <?php article_ui_a4_val($m['donnees'] ?? ''); ?>
  <h2>Remerciements</h2>
  <?php article_ui_a4_val($m['remerciements'] ?? ''); ?>
  <h2>Références bibliographiques</h2>
  <?php article_ui_a4_val($f['references'] ?? ''); ?>

  <?php if (!empty($f['annexes_fichiers'])): ?>
  <h2>Annexes</h2>
  <ul>
    <?php foreach ($f['annexes_fichiers'] as $af): ?>
    <li><?= e($af['nom'] ?? ($af['fichier'] ?? '')) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
    <?php
}
