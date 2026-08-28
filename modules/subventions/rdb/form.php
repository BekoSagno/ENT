<?php
// modules/subventions/rdb/form.php — Saisie RDB (6 sections, enregistrement auto)
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../models/rdb.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission('SUBVENTIONS', 'RAPPORT');

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) { redirect('/modules/subventions/rdb/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Session expirée.');
        redirect('/modules/subventions/rdb/form.php?id=' . $id);
    }
    $res = rdb_sauver($id, $_POST);
    $step  = max(1, min(6, (int)($_POST['step'] ?? 1)));
    $leave = (int)($_POST['leave'] ?? 0) === 1;

    // Upload annexe (avec retour d'erreur visible)
    if (!empty($_FILES['annexe']['name'])) {
        $up = rdb_doc_ajouter($id, $_FILES['annexe'], 'annexe');
        if (empty($up['succes'])) {
            set_flash('error', $up['message'] ?? 'Échec de l\'ajout du document.');
            redirect('/modules/subventions/rdb/form.php?id=' . $id . '&step=6');
        }
        set_flash('success', 'Document joint : ' . ($up['nom'] ?? 'fichier'));
        // Si on joignait seulement, rester à l'étape 6
        if (!empty($_POST['join_only'])) {
            redirect('/modules/subventions/rdb/form.php?id=' . $id . '&step=6');
        }
        $step = 6;
    }

    // Import après sauvegarde de la saisie en cours
    if (!empty($_POST['do_prefill']) && $res['succes']) {
        $pref = rdb_prefill_subventions($id);
        set_flash($pref['succes'] ? 'success' : 'error',
            $pref['succes']
                ? (($pref['added'] ?? 0) . ' ligne(s) importée(s) — tableaux Ressources / Personnel / Fonctionnement remplacés.')
                : ($pref['message'] ?? 'Erreur import.'));
        redirect('/modules/subventions/rdb/form.php?id=' . $id . '&step=3');
    }

    if ($leave) {
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Brouillon enregistré.' : ($res['message'] ?? 'Erreur.'));
        redirect('/modules/subventions/rdb/index.php');
    }

    if (!$res['succes']) {
        set_flash('error', $res['message'] ?? 'Erreur.');
    }
    redirect('/modules/subventions/rdb/form.php?id=' . $id . '&step=' . $step);
}

$rap = rdb_charger($id);
if (!$rap) { set_flash('error', 'Rapport introuvable.'); redirect('/modules/subventions/rdb/index.php'); }

$meta     = rdb_tableaux_meta();
$etats    = rdb_etats_activite();
$readonly = ($rap['statut'] === 'finalise');
$lpt      = $rap['lignes_par_tableau'];
$step     = max(1, min(6, (int)($_GET['step'] ?? 1)));

$page_titre      = 'RDB T' . $rap['trimestre'] . ' — ' . $rap['annee'];
$page_breadcrumb = 'Subventions · RDB · T' . $rap['trimestre'] . '-' . $rap['annee'];
$module_actif    = 'SUBVENTIONS';
$subvention_sous_actif = 'RDB';
include __DIR__ . '/../../../views/layout/header.php';
?>
<style>
/* ——— RDB : vue claire, texte lisible ——— */
.rdb-wrap{max-width:1180px;margin:0 auto;color:var(--text);}

.rdb-toolbar{
  display:flex;align-items:center;gap:12px;flex-wrap:wrap;
  margin-bottom:16px;padding:12px 16px;
  background:#fff;border:1px solid var(--border);border-radius:12px;
}
.rdb-toolbar-meta{flex:1;min-width:200px;font-size:.9rem;color:var(--text);line-height:1.4;}
.rdb-toolbar-meta strong{color:var(--blue-800);font-weight:700;}
.rdb-badge{
  display:inline-block;margin-left:8px;padding:3px 10px;border-radius:999px;
  background:#dcfce7;color:#166534;font-size:.75rem;font-weight:700;
}

.rdb-hint{
  margin:0 0 16px;padding:12px 16px;
  background:var(--blue-50);border:1px solid var(--blue-200);border-radius:10px;
  font-size:.9rem;color:var(--blue-800);line-height:1.5;
}
.rdb-hint strong{color:var(--blue-900);}

.rdb-steps{
  display:flex;justify-content:space-between;gap:4px;
  margin:0 0 20px;padding:14px 10px 10px;
  background:#fff;border:1px solid var(--border);border-radius:12px;
  position:relative;
}
.rdb-steps::before{
  content:"";position:absolute;top:31px;left:8%;right:8%;height:3px;
  background:var(--blue-100);z-index:0;border-radius:2px;
}
.rdb-step{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:7px;flex:1;cursor:pointer;}
.rdb-step .dot{
  width:36px;height:36px;border-radius:50%;
  background:#fff;border:2.5px solid var(--blue-200);
  display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:.9rem;color:var(--text-muted);
}
.rdb-step .nm{
  font-size:.78rem;font-weight:700;color:var(--text-muted);
  text-align:center;max-width:96px;line-height:1.25;
}
.rdb-step.active .dot{background:var(--blue-600);border-color:var(--blue-600);color:#fff;box-shadow:0 0 0 4px var(--blue-100);}
.rdb-step.active .nm{color:var(--blue-800);font-weight:800;}
.rdb-step.done .dot{background:#16a34a;border-color:#16a34a;color:#fff;}
.rdb-step.done .nm{color:#166534;}

.rdb-card{
  background:#fff;border:1px solid var(--border);border-radius:14px;
  overflow:visible;box-shadow:0 1px 8px rgba(13,50,120,.04);
}
.rdb-hd{
  padding:18px 22px;
  background:linear-gradient(180deg, var(--blue-50) 0%, #fff 100%);
  border-bottom:2px solid var(--blue-100);
}
.rdb-hd h2{font-size:1.15rem;margin:0;color:var(--blue-900);font-weight:800;letter-spacing:-.01em;}
.rdb-hd p{font-size:.88rem;color:var(--text-muted);margin:6px 0 0;line-height:1.4;}
.rdb-bd{padding:22px 22px 26px;}
.rdb-sub{
  font-size:.95rem;font-weight:800;color:var(--blue-800);
  margin:4px 0 14px;padding-bottom:6px;border-bottom:2px solid var(--blue-100);
}
.rdb-panel{display:none;}.rdb-panel.on{display:block;}

.rdb-step-help{
  background:#fffbeb;border:1px solid #fde68a;border-radius:10px;
  padding:12px 14px;font-size:.88rem;color:#78350f;margin-bottom:16px;line-height:1.45;
}

.rdb-tbl-title{
  font-size:.95rem;margin:18px 0 0;padding:10px 14px;
  background:var(--blue-700);color:#fff;font-weight:700;
  border-radius:8px 8px 0 0;letter-spacing:.01em;
}
.rdb-tbl-wrap{
  width:100%;overflow-x:visible;
  border:1px solid var(--blue-200);border-top:0;border-radius:0 0 8px 8px;
  margin-bottom:8px;background:#fff;
}
.rdb-tbl{width:100%;border-collapse:collapse;font-size:.9rem;margin:0;table-layout:fixed;}
.rdb-tbl th,.rdb-tbl td{
  border:1px solid var(--blue-100);padding:0;vertical-align:top;
  word-break:break-word;overflow-wrap:anywhere;
}
.rdb-tbl th{
  background:var(--blue-50);font-size:.78rem;font-weight:800;
  text-transform:none;color:var(--blue-900);
  white-space:normal;line-height:1.3;padding:11px 10px;
  border-bottom:2px solid var(--blue-200);
}
.rdb-tbl tbody tr:nth-child(even) td{background:#fafcff;}
.rdb-tbl tbody tr:hover td{background:var(--blue-50);}

.rdb-cell-text{width:20%;}
.rdb-cell-num{width:11%;}
.rdb-cell-date{width:12%;}
.rdb-cell-etat{width:11%;}
.rdb-cell-action{
  width:92px;text-align:center;vertical-align:middle !important;
  padding:10px 6px !important;background:#fffaf9 !important;
}
.rdb-th-action{width:92px;}
.rdb-taux-cell{
  text-align:center;font-weight:700;color:var(--blue-800);
  white-space:nowrap;width:9%;padding:12px 10px;font-size:.9rem;
  vertical-align:middle;
}

.rdb-tbl textarea.rdb-ta,
.rdb-tbl input.rdb-num,
.rdb-tbl input.rdb-date,
.rdb-tbl select.rdb-sel{
  display:block;width:100%;box-sizing:border-box;
  margin:0;padding:12px 10px;
  border:0 !important;border-radius:0;outline:none;
  background:transparent;box-shadow:none;
  color:var(--text);font:inherit;font-size:.9rem;font-weight:500;
  min-height:44px;height:auto !important;
}
.rdb-tbl textarea.rdb-ta{
  line-height:1.5;white-space:pre-wrap;word-break:break-word;overflow-wrap:anywhere;
  min-height:3.4em !important;max-height:none !important;
  overflow:visible !important;resize:none;field-sizing:content;
  text-align:left;direction:ltr;
}
.rdb-tbl textarea.rdb-ta::placeholder,
.rdb-tbl input.rdb-num::placeholder{color:#94a3b8;font-weight:400;}
.rdb-tbl input.rdb-num{
  font-variant-numeric:tabular-nums;text-align:center;
  font-size:.9rem;font-weight:600;color:var(--blue-900);
}
.rdb-tbl input.rdb-date{font-size:.88rem;}
.rdb-tbl select.rdb-sel{font-size:.88rem;cursor:pointer;font-weight:600;}
.rdb-tbl td:focus-within{
  background:#e8f1ff !important;
  box-shadow:inset 0 0 0 2px var(--blue-500);
}

.rdb-ligne .rdb-rm{
  display:inline-block;border:1px solid #fecaca;background:#fef2f2;
  padding:6px 10px;margin:0;border-radius:6px;
  color:#b91c1c;font-size:.8rem;font-weight:700;cursor:pointer;
  white-space:nowrap;line-height:1.2;
}
.rdb-ligne .rdb-rm:hover{background:#fee2e2;border-color:#f87171;color:#7f1d1d;}

.rdb-wrap .rdb-field textarea.form-control{
  min-height:96px;height:auto;overflow:auto;white-space:pre-wrap;resize:vertical;
  font-size:.95rem;line-height:1.5;padding:12px 14px;color:var(--text);
  border:1.5px solid var(--blue-200);border-radius:10px;background:#fff;
}
.rdb-wrap .rdb-field textarea.form-control:focus{
  border-color:var(--blue-500);box-shadow:0 0 0 3px var(--blue-100);outline:none;
}
.rdb-docs{list-style:none;padding:0;margin:8px 0 14px;}
.rdb-docs li{
  display:flex;align-items:center;gap:10px;padding:12px 14px;
  border:1px solid var(--blue-200);border-radius:10px;margin-bottom:8px;
  background:var(--blue-50);font-size:.9rem;flex-wrap:wrap;
}
.rdb-docs a{font-weight:700;color:var(--blue-700);text-decoration:underline;}
.rdb-info{
  background:var(--blue-50);border:1px solid var(--blue-200);border-radius:10px;
  padding:14px 16px;font-size:.9rem;color:var(--blue-800);margin-bottom:16px;line-height:1.45;
}
.rdb-actions{
  display:flex;gap:10px;justify-content:space-between;flex-wrap:wrap;
  margin-top:22px;padding-top:16px;border-top:1px solid var(--blue-100);
}
.rdb-actions .right{display:flex;gap:8px;flex-wrap:wrap;margin-left:auto;}
.rdb-field{margin-bottom:18px;}
.rdb-label{display:block;font-size:.92rem;font-weight:700;margin-bottom:7px;color:var(--blue-900);}

.rdb-add-line{
  margin-bottom:18px !important;
  font-weight:700 !important;
}

@media (max-width:900px){
  .rdb-tbl-wrap{overflow-x:auto;}
  .rdb-tbl{min-width:760px;table-layout:auto;}
  .rdb-step .nm{font-size:.7rem;max-width:72px;}
}
</style>

<div class="rdb-wrap">
<div class="rdb-toolbar">
  <?php if (!$readonly): ?>
  <button type="button" class="btn btn-secondary btn-sm" id="btnLeave"><i class="fa-solid fa-arrow-left"></i> Liste RDB</button>
  <?php else: ?>
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Liste RDB</a>
  <?php endif; ?>
  <div class="rdb-toolbar-meta">
    <strong>T<?= (int)$rap['trimestre'] ?> — <?= (int)$rap['annee'] ?></strong>
    · Année N-1 : <strong><?= (int)$rap['annee_n1'] ?></strong>
    · Trimestre précédent : <strong>T<?= (int)$rap['trim_prec'] ?> <?= (int)$rap['trim_prec_annee'] ?></strong>
    <?php if ($readonly): ?><span class="rdb-badge">Finalisé</span><?php endif; ?>
  </div>
  <a href="print.php?id=<?= $id ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="fa-solid fa-print"></i> PDF</a>
  <?php if (!$readonly): ?>
  <form method="POST" action="actions.php" style="display:inline;" onsubmit="return confirm('Finaliser ce rapport ? Il ne sera plus modifiable.');">
    <?= csrf_field() ?><input type="hidden" name="action" value="finaliser"><input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check-double"></i> Finaliser</button>
  </form>
  <?php else: ?>
  <form method="POST" action="actions.php" style="display:inline;" onsubmit="return confirm('Rouvrir ce rapport pour modification ?');">
    <?= csrf_field() ?><input type="hidden" name="action" value="rouvrir"><input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-lock-open"></i> Rouvrir</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($readonly): ?>
<div class="rdb-info" style="background:#fef3c7;border-color:#fcd34d;color:#92400e;margin-bottom:14px;">
  <i class="fa-solid fa-lock"></i> Ce rapport est <strong>finalisé</strong> : lecture seule.
  Cliquez sur <strong>Rouvrir</strong> pour reprendre la saisie.
</div>
<?php else: ?>
<p class="rdb-hint">Remplissez l’étape, puis cliquez sur <strong>Suivant</strong> : vos informations sont enregistrées automatiquement. Vous pourrez revenir en arrière à tout moment.</p>
<?php endif; ?>

<div class="rdb-steps" id="steps">
  <?php $steps = ['EPA','Plan N-1','Réalisations','Dépenses','Synthèse','Conclusion'];
  foreach ($steps as $i => $lbl): $n=$i+1; ?>
  <div class="rdb-step <?= $n===$step?'active':($n<$step?'done':'') ?>" data-s="<?= $n ?>" onclick="navStep(<?= $n ?>)">
    <div class="dot"><?= $n ?></div><div class="nm"><?= $lbl ?></div>
  </div>
  <?php endforeach; ?>
</div>

<form method="POST" action="form.php?id=<?= $id ?>" enctype="multipart/form-data" id="rdbForm" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="step" id="rdbStep" value="<?= $step ?>">
  <input type="hidden" name="leave" id="rdbLeave" value="0">
  <input type="hidden" name="do_prefill" id="rdbPrefill" value="0">
  <input type="hidden" name="join_only" id="rdbJoinOnly" value="0">

  <div class="rdb-card">

  <!-- §1 Présentation EPA -->
  <div class="rdb-panel <?= $step===1?'on':'' ?>" data-panel="1">
    <div class="rdb-hd">
      <h2>1 — Présentation sommaire de l'EPA</h2>
      <p>Préciser les références de l'acte de création de la structure.</p>
    </div>
    <div class="rdb-bd">
      <div class="rdb-field"><label class="rdb-label">Références de l'acte de création</label>
        <textarea name="acte_creation" class="form-control" rows="2" <?= $readonly?'readonly':'' ?>><?= e($rap['acte_creation'] ?? '') ?></textarea></div>
      <div class="rdb-field"><label class="rdb-label">Missions</label>
        <textarea name="missions" class="form-control" rows="3" <?= $readonly?'readonly':'' ?>><?= e($rap['missions'] ?? '') ?></textarea></div>
      <div class="rdb-field"><label class="rdb-label">Organisation</label>
        <textarea name="organisation" class="form-control" rows="3" <?= $readonly?'readonly':'' ?>><?= e($rap['organisation'] ?? '') ?></textarea></div>
      <div class="rdb-actions"><span></span><div class="right">
        <?php if (!$readonly): ?>
        <button type="button" class="btn btn-primary" onclick="saveAndGo(2)">Suivant</button>
        <?php else: ?>
        <button type="button" class="btn btn-primary" onclick="goStep(2)">Suivant</button>
        <?php endif; ?>
      </div></div>
    </div>
  </div>

  <!-- §2 Plan d'actions N-1 -->
  <div class="rdb-panel <?= $step===2?'on':'' ?>" data-panel="2">
    <div class="rdb-hd">
      <h2>2 — Plan d'actions annuel budgétisé — Année N-1 (<?= (int)$rap['annee_n1'] ?>)</h2>
    </div>
    <div class="rdb-bd">
      <p class="rdb-step-help">Indiquez ici les activités prévues pour l’année précédente, avec le budget et le responsable. Utilisez <strong>+ Ajouter une ligne</strong> si besoin, ou <strong>Retirer</strong> à droite pour enlever une ligne.</p>
      <p class="rdb-sub">a) Activités programmées</p>
      <?php
        rdb_render_tableau(1, $meta, $lpt, $etats, $readonly);
        rdb_render_tableau(2, $meta, $lpt, $etats, $readonly);
      ?>
      <div class="rdb-actions">
        <button type="button" class="btn btn-secondary" onclick="<?= $readonly ? 'goStep(1)' : 'saveAndGo(1)' ?>">Retour</button>
        <div class="right">
          <button type="button" class="btn btn-primary" onclick="<?= $readonly ? 'goStep(3)' : 'saveAndGo(3)' ?>">Suivant</button>
        </div>
      </div>
    </div>
  </div>

  <!-- §3 Activités réalisées + ressources -->
  <div class="rdb-panel <?= $step===3?'on':'' ?>" data-panel="3">
    <div class="rdb-hd">
      <h2>2 — Plan d'actions annuel budgétisé (suite)</h2>
      <p>b) Activités réalisées — trimestre précédent : T<?= (int)$rap['trim_prec'] ?> <?= (int)$rap['trim_prec_annee'] ?></p>
    </div>
    <div class="rdb-bd">
      <p class="rdb-step-help">Renseignez les activités réalisées, puis les ressources. Le bouton <strong>Remplir depuis Subventions</strong> peut préremplir les montants à partir des caisses déjà saisies.</p>
      <?php
        rdb_render_tableau(3, $meta, $lpt, $etats, $readonly, 'Libellé');
        rdb_render_tableau(4, $meta, $lpt, $etats, $readonly, 'Source / Libellé');
      ?>
      <?php if (!$readonly): ?>
      <div style="margin:4px 0 16px;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="doPrefill()">
          Remplir depuis Subventions
        </button>
        <span class="rdb-hint" style="display:inline;margin-left:8px;">Remplace les lignes Ressources, Personnel et Fonctionnement par les données de la période.</span>
      </div>
      <?php endif; ?>
      <div class="rdb-actions">
        <button type="button" class="btn btn-secondary" onclick="<?= $readonly ? 'goStep(2)' : 'saveAndGo(2)' ?>">Retour</button>
        <div class="right">
          <button type="button" class="btn btn-primary" onclick="<?= $readonly ? 'goStep(4)' : 'saveAndGo(4)' ?>">Suivant</button>
        </div>
      </div>
    </div>
  </div>

  <!-- §4 Dépenses par nature -->
  <div class="rdb-panel <?= $step===4?'on':'' ?>" data-panel="4">
    <div class="rdb-hd">
      <h2>3 — Situation d'exécution détaillée par nature de dépenses</h2>
    </div>
    <div class="rdb-bd">
      <p class="rdb-step-help">Saisissez les dépenses par nature. Le taux se calcule tout seul (Payé ÷ Crédit). À droite de chaque ligne, <strong>Retirer</strong> enlève la ligne.</p>
      <div class="rdb-field"><label class="rdb-label">Commentaire global — dépenses de personnel (consultants, masse salariale…)</label>
        <textarea name="commentaire_personnel" class="form-control" rows="3" <?= $readonly?'readonly':'' ?>><?= e((string)($rap['commentaire_personnel'] ?? '')) ?></textarea></div>

      <?php foreach ([5, 6, 7, 8] as $tn): ?>
      <?php rdb_render_tableau($tn, $meta, $lpt, $etats, $readonly, 'Rubrique'); ?>
      <?php endforeach; ?>
      <div class="rdb-actions">
        <button type="button" class="btn btn-secondary" onclick="<?= $readonly ? 'goStep(3)' : 'saveAndGo(3)' ?>">Retour</button>
        <div class="right">
          <button type="button" class="btn btn-primary" onclick="<?= $readonly ? 'goStep(5)' : 'saveAndGo(5)' ?>">Suivant</button>
        </div>
      </div>
    </div>
  </div>

  <!-- §5 Récapitulatif -->
  <div class="rdb-panel <?= $step===5?'on':'' ?>" data-panel="5">
    <div class="rdb-hd">
      <h2><?= e($meta[9]['titre']) ?></h2>
      <p>Calculé automatiquement à partir des dépenses de personnel, fonctionnement, transfert et investissement.</p>
    </div>
    <div class="rdb-bd">
      <div class="rdb-tbl-wrap" style="border-top:1px solid var(--blue-200);border-radius:8px;">
        <table class="rdb-tbl">
          <thead><tr><th>Nature</th>
            <?php foreach ($meta[9]['colonnes'] as $cl): ?><th><?= e($cl) ?></th><?php endforeach; ?>
          </tr></thead>
          <tbody>
          <?php foreach ($lpt[9] ?? [] as $l):
            $d = $l['donnees'] ?? [];
          ?>
          <tr>
            <td style="padding:12px 10px;font-weight:700;"><strong><?= e($l['libelle'] ?? '') ?></strong></td>
            <?php foreach ($meta[9]['colonnes'] as $ck => $cl):
              $v = $d[$ck] ?? '';
              if ($ck === 'taux') {
                  echo '<td class="rdb-taux-cell">' . e(($v !== '' && $v !== null) ? $v . ' %' : '—') . '</td>';
              } else {
                  echo '<td class="rdb-taux-cell">' . e(is_numeric($v) ? number_format((float)$v, 0, ',', ' ') : '—') . '</td>';
              }
            endforeach; ?>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($lpt[9])): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:16px;">Complétez les dépenses (étape précédente) puis cliquez sur Suivant pour générer le récapitulatif.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="rdb-actions">
        <button type="button" class="btn btn-secondary" onclick="<?= $readonly ? 'goStep(4)' : 'saveAndGo(4)' ?>">Retour</button>
        <div class="right">
          <button type="button" class="btn btn-primary" onclick="<?= $readonly ? 'goStep(6)' : 'saveAndGo(6)' ?>">Suivant</button>
        </div>
      </div>
    </div>
  </div>

  <!-- §6 Conclusion -->
  <div class="rdb-panel <?= $step===6?'on':'' ?>" data-panel="6">
    <div class="rdb-hd">
      <h2>4, 5 et 6 — Suggestions, Recommandations, Conclusion</h2>
    </div>
    <div class="rdb-bd">
      <div class="rdb-field"><label class="rdb-label">4. Suggestions</label>
        <textarea name="suggestions" class="form-control" rows="4" <?= $readonly?'readonly':'' ?>><?= e($rap['suggestions'] ?? '') ?></textarea></div>
      <div class="rdb-field"><label class="rdb-label">5. Recommandations</label>
        <textarea name="recommandations" class="form-control" rows="4" <?= $readonly?'readonly':'' ?>><?= e($rap['recommandations'] ?? '') ?></textarea></div>
      <div class="rdb-field"><label class="rdb-label">6. Conclusion</label>
        <textarea name="conclusion" class="form-control" rows="4" <?= $readonly?'readonly':'' ?>><?= e($rap['conclusion'] ?? '') ?></textarea></div>
      <div class="rdb-field">
        <label class="rdb-label">Annexes (pièces jointes)</label>
        <?php $docs = $rap['documents'] ?? []; ?>
        <?php if ($docs): ?>
        <ul class="rdb-docs">
          <?php foreach ($docs as $doc): ?>
          <li>
            <i class="fa-solid fa-paperclip"></i>
            <a href="download.php?id=<?= (int)$doc['id'] ?>" target="_blank"><?= e($doc['nom_original'] ?? 'Document') ?></a>
            <span style="color:var(--text-muted);font-size:.75rem;">
              <?= !empty($doc['taille_fichier']) ? number_format((int)$doc['taille_fichier'] / 1024, 0, ',', ' ') . ' Ko' : '' ?>
              <?= !empty($doc['date_upload']) ? ' · ' . e(format_datetime($doc['date_upload'])) : '' ?>
            </span>
            <?php if (!$readonly): ?>
            <button type="button" class="btn btn-secondary btn-sm btn-icon rdb-doc-del" style="margin-left:auto;"
                    data-doc-id="<?= (int)$doc['id'] ?>" title="Supprimer">
              <i class="fa-solid fa-trash"></i>
            </button>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="rdb-hint">Aucun document joint pour le moment.</p>
        <?php endif; ?>
        <?php if (!$readonly): ?>
        <input type="file" name="annexe" id="rdbAnnexe" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png,.webp">
        <button type="button" class="btn btn-secondary btn-sm" style="margin-top:8px;" onclick="joinDoc()">
          <i class="fa-solid fa-upload"></i> Joindre le document
        </button>
        <?php endif; ?>
      </div>
      <div class="rdb-actions">
        <button type="button" class="btn btn-secondary" onclick="<?= $readonly ? 'goStep(5)' : 'saveAndGo(5)' ?>">Retour</button>
        <div class="right">
          <?php if (!$readonly): ?>
          <button type="button" class="btn btn-primary" onclick="saveAndLeave()"><i class="fa-solid fa-check"></i> Terminer (brouillon)</button>
          <?php endif; ?>
          <a href="print.php?id=<?= $id ?>" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-print"></i> PDF</a>
        </div>
      </div>
    </div>
  </div>

  </div><!-- /.rdb-card -->
</form>

<?php if (!$readonly): ?>
<form method="POST" action="actions.php" id="docDelForm" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="doc_suppr">
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="doc_id" id="docDelId" value="">
</form>
<?php endif; ?>
</div>

<?php
$meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
$etats_json = json_encode($etats, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
$ro_js = $readonly ? 'true' : 'false';
$step_js = (int)$step;
ob_start();
include __DIR__ . '/_form_js.php';
$extra_scripts = ob_get_clean();
include __DIR__ . '/../../../views/layout/footer.php';
