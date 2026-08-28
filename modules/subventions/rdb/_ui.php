<?php
// modules/subventions/rdb/_ui.php — Rendu des lignes de tableaux RDB

if (!function_exists('rdb_is_num_col')) {
    function rdb_is_num_col(string $ck): bool {
        return in_array($ck, [
            'montant_budgete', 'montant_depense', 'credit', 'paye',
            't1', 't2', 't3', 't4',
        ], true);
    }
}

if (!function_exists('rdb_is_date_col')) {
    function rdb_is_date_col(string $ck): bool {
        return in_array($ck, ['date_debut', 'date_fin'], true);
    }
}

if (!function_exists('rdb_is_text_col')) {
    function rdb_is_text_col(string $ck): bool {
        return !rdb_is_num_col($ck) && !rdb_is_date_col($ck) && $ck !== 'etat' && $ck !== 'taux';
    }
}

if (!function_exists('rdb_date_input_val')) {
    function rdb_date_input_val($v): string {
        $v = trim((string)$v);
        if ($v === '') return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) return substr($v, 0, 10);
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $v, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }
        return $v;
    }
}

if (!function_exists('rdb_cell_class')) {
    function rdb_cell_class(string $ck): string {
        if (rdb_is_num_col($ck)) return 'rdb-cell-num';
        if (rdb_is_date_col($ck)) return 'rdb-cell-date';
        if ($ck === 'etat') return 'rdb-cell-etat';
        if (rdb_is_text_col($ck)) return 'rdb-cell-text';
        return '';
    }
}

if (!function_exists('rdb_render_ligne')) {
    function rdb_render_ligne(int $tn, int $idx, array $l, array $meta, array $etats, bool $ro): void {
        $d         = $l['donnees'] ?? [];
        $cols      = $meta[$tn]['colonnes'] ?? [];
        $calc_cols = $meta[$tn]['cols_calc'] ?? [];
        $pfx       = "lignes[$tn][$idx]";
        ?>
        <tr class="rdb-ligne" data-tn="<?= $tn ?>">
          <td class="rdb-cell-text">
            <textarea name="<?= $pfx ?>[libelle]" class="rdb-ta" rows="2"
                      placeholder="Saisir le libellé…"
                      <?= $ro ? 'readonly' : '' ?>><?= e((string)($l['libelle'] ?? '')) ?></textarea>
          </td>
          <?php foreach ($cols as $ck => $clbl):
              if (in_array($ck, $calc_cols, true)) {
                  $cred = (float)($d['credit'] ?? 0);
                  $paye = (float)($d['paye'] ?? 0);
                  $taux = isset($d['taux']) ? (float)$d['taux'] : rdb_calc_taux($cred, $paye);
                  $taux_lbl = $cred > 0 ? $taux . ' %' : '—';
                  ?>
          <td class="rdb-taux-cell"><?= e($taux_lbl) ?></td>
                  <?php
                  continue;
              }
              $val = $d[$ck] ?? '';
              if (is_float($val) || is_int($val)) {
                  $val = $val != 0 ? number_format((float)$val, 0, ',', ' ') : '';
              } else {
                  $val = (string)$val;
              }
              if (rdb_is_num_col($ck) && $val !== '' && is_numeric(str_replace([' ', "\xc2\xa0"], '', $val))) {
                  $val = number_format((float)str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], $val), 0, ',', ' ');
              }
          ?>
          <td class="<?= e(rdb_cell_class($ck)) ?>">
            <?php if ($ck === 'etat'): ?>
              <select name="<?= $pfx ?>[etat]" class="rdb-sel" <?= $ro ? 'disabled' : '' ?>>
                <option value="">Choisir…</option>
                <?php foreach ($etats as $ek => $el): ?>
                  <option value="<?= e($ek) ?>" <?= ($d['etat'] ?? '') === $ek ? 'selected' : '' ?>><?= e($el) ?></option>
                <?php endforeach; ?>
              </select>
            <?php elseif (rdb_is_date_col($ck)): ?>
              <input type="date" name="<?= $pfx ?>[<?= e($ck) ?>]" class="rdb-date"
                     value="<?= e(rdb_date_input_val($val)) ?>" <?= $ro ? 'readonly' : '' ?>>
            <?php elseif (rdb_is_text_col($ck)): ?>
              <textarea name="<?= $pfx ?>[<?= e($ck) ?>]" class="rdb-ta" rows="2"
                        placeholder="<?= e($clbl) ?>" <?= $ro ? 'readonly' : '' ?>><?= e($val) ?></textarea>
            <?php else: ?>
              <input type="text" name="<?= $pfx ?>[<?= e($ck) ?>]" class="rdb-num" inputmode="numeric"
                     value="<?= e($val) ?>" <?= $ro ? 'readonly' : '' ?> placeholder="0" autocomplete="off">
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td class="rdb-cell-text">
            <textarea name="<?= $pfx ?>[commentaire]" class="rdb-ta" rows="2"
                      placeholder="Observation (facultatif)" <?= $ro ? 'readonly' : '' ?>><?= e((string)($l['commentaire'] ?? '')) ?></textarea>
          </td>
          <?php if (!$ro): ?>
          <td class="rdb-cell-action">
            <button type="button" class="rdb-rm" title="Retirer cette ligne">Retirer</button>
          </td>
          <?php endif; ?>
        </tr>
        <?php
    }
}

if (!function_exists('rdb_render_tableau')) {
    function rdb_render_tableau(int $tn, array $meta, array $lpt, array $etats, bool $readonly, string $libelle_col = 'Libellé / Activité'): void {
        $cols  = $meta[$tn]['colonnes'] ?? [];
        $titre = $meta[$tn]['titre'] ?? ('Tableau ' . $tn);
        ?>
      <h4 class="rdb-tbl-title"><?= e($titre) ?></h4>
      <div class="rdb-tbl-wrap">
        <table class="rdb-tbl" id="tbl-<?= $tn ?>">
          <thead>
            <tr>
              <th><?= e($libelle_col) ?></th>
              <?php foreach ($cols as $clbl): ?><th><?= e($clbl) ?></th><?php endforeach; ?>
              <th>Observation</th>
              <?php if (!$readonly): ?><th class="rdb-th-action"></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php
            $rows = $lpt[$tn] ?? [];
            if (empty($rows) && !$readonly) {
                $rows = [['libelle' => '', 'donnees' => [], 'commentaire' => '']];
            }
            foreach ($rows as $idx => $l) {
                rdb_render_ligne($tn, $idx, $l, $meta, $etats, $readonly);
            }
            if (empty($rows) && $readonly): ?>
          <tr><td colspan="<?= count($cols) + 2 ?>" style="text-align:center;color:var(--text-muted);font-size:.9rem;padding:16px;">Aucune information pour le moment.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (!$readonly): ?>
      <button type="button" class="btn btn-secondary btn-sm rdb-add-line" data-tn="<?= $tn ?>">
        + Ajouter une ligne
      </button>
      <?php endif;
    }
}
