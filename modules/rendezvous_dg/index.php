<?php
// modules/rendezvous_dg/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/rendezvous_dg.php';
require_once __DIR__ . '/_ui.php';

session_demarrer();
exiger_permission(RDV_DG_MODULE, 'VOIR');
rdv_dg_assurer_schema();
rdv_dg_marquer_termines();

$peut_gerer = rdv_dg_peut_gerer();
$peut_creer = a_permission(RDV_DG_MODULE, 'CREER');

$filtres = [
    'statut' => clean($_GET['statut'] ?? ''),
    'q' => clean($_GET['q'] ?? ''),
];
if (!$peut_gerer) {
    $filtres['utilisateur_id'] = (int)user_id();
}

$page = max(1, (int)($_GET['page'] ?? 1));
$res = rdv_dg_lister($filtres, 30, ($page - 1) * 30);
$pagination = function_exists('paginer') ? paginer($res['total'], 30, $page) : null;
$stats = rdv_dg_stats($peut_gerer ? null : (int)user_id());
$public_url = rtrim(APP_URL, '/') . '/modules/rendezvous_dg/public.php';

$page_titre = 'Rendez-vous DG';
$page_breadcrumb = 'Direction · Rendez-vous & visites';
$module_actif = 'RENDEZVOUS_DG';

include __DIR__ . '/../../views/layout/header.php';
rdv_dg_ui_styles();
?>

<div class="rdv-wrap">
  <div class="rdv-toolbar">
    <?php if ($peut_creer): ?>
    <a href="form.php" class="btn btn-primary"><i class="fa-solid fa-calendar-plus"></i> Prendre un créneau</a>
    <?php endif; ?>
    <?php if ($peut_gerer): ?>
    <a href="disponibilites.php" class="btn btn-secondary"><i class="fa-solid fa-calendar-week"></i> Disponibilités</a>
    <span class="rdv-public-link">
      <label for="rdv-public-url">Lien public</label>
      <input id="rdv-public-url" type="text" readonly value="<?= e($public_url) ?>"
             aria-label="Lien public de prise de rendez-vous">
      <button type="button" class="btn btn-secondary btn-sm rdv-copy-btn" id="rdv-copy-btn">
        <i class="fa-regular fa-copy"></i> <span>Copier</span>
      </button>
    </span>
    <?php endif; ?>
    <div class="spacer"></div>
    <span style="font-size:.82rem;color:var(--text-muted);">
      <strong><?= (int)$res['total'] ?></strong> demande<?= $res['total'] > 1 ? 's' : '' ?>
    </span>
  </div>

  <div class="rdv-stats">
    <div class="rdv-stat attente">
      <span class="ic"><i class="fa-solid fa-hourglass-half"></i></span>
      <div><div class="v"><?= $stats['EN_ATTENTE'] ?></div><div class="l">En attente</div></div>
    </div>
    <div class="rdv-stat confirme">
      <span class="ic"><i class="fa-solid fa-circle-check"></i></span>
      <div><div class="v"><?= $stats['CONFIRME'] ?></div><div class="l">Confirmés</div></div>
    </div>
    <div class="rdv-stat termine">
      <span class="ic"><i class="fa-solid fa-flag-checkered"></i></span>
      <div><div class="v"><?= $stats['TERMINE'] ?></div><div class="l">Terminés</div></div>
    </div>
    <div class="rdv-stat refuse">
      <span class="ic"><i class="fa-solid fa-ban"></i></span>
      <div><div class="v"><?= $stats['REFUSE'] + $stats['ANNULE'] ?></div><div class="l">Refusés / Annulés</div></div>
    </div>
  </div>

  <form method="get" class="rdv-filters card" style="padding:12px 14px;">
    <input type="text" name="q" class="form-control" placeholder="Rechercher…" value="<?= e($filtres['q']) ?>">
    <select name="statut" class="form-control">
      <option value="">Tous les statuts</option>
      <?php foreach (RDV_DG_STATUTS as $k => $l): ?>
      <option value="<?= e($k) ?>" <?= $filtres['statut'] === $k ? 'selected' : '' ?>><?= e($l) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-filter"></i> Filtrer</button>
  </form>

  <div class="card" style="overflow:auto;">
    <?php if (empty($res['data'])): ?>
      <div class="rdv-empty">Aucune demande pour le moment.</div>
    <?php else: ?>
    <table class="rdv-table">
      <thead>
        <tr>
          <th>Référence</th>
          <th>Demandeur</th>
          <th>Type</th>
          <th>Objet</th>
          <th>Date</th>
          <th>Statut</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($res['data'] as $r):
        $dateAff = $r['date_confirmee'] ?: $r['date_souhaitee'];
        $heureAff = $r['heure_debut'] ? rdv_dg_fmt_heure($r['heure_debut']) : ($r['creneau_souhaite'] ? rdv_dg_fmt_heure($r['creneau_souhaite']) : '');
      ?>
        <tr>
          <td><strong><?= e($r['reference']) ?></strong>
            <div class="rdv-meta"><?= $r['source'] === 'PUBLIC' ? 'Externe' : 'Interne' ?></div>
          </td>
          <td><?= e(trim($r['prenoms'] . ' ' . $r['nom'])) ?>
            <div class="rdv-meta"><?= e($r['organisation'] ?: $r['email']) ?></div>
          </td>
          <td><?= e(rdv_dg_libelle_type($r)) ?></td>
          <td><?= e(mb_strimwidth($r['objet'], 0, 80, '…')) ?></td>
          <td><?= $dateAff ? e(rdv_dg_fmt_date($dateAff)) : '—' ?>
            <?php if ($heureAff): ?><div class="rdv-meta"><?= e($heureAff) ?></div><?php endif; ?>
          </td>
          <td><?= rdv_dg_badge($r['statut']) ?></td>
          <td><a href="voir.php?id=<?= (int)$r['id'] ?>" class="btn btn-secondary btn-sm">Voir</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php if ($peut_gerer): ?>
<script>
(function () {
  const input = document.getElementById('rdv-public-url');
  const button = document.getElementById('rdv-copy-btn');
  if (!input || !button) return;

  button.addEventListener('click', async function () {
    let copied = false;
    try {
      await navigator.clipboard.writeText(input.value);
      copied = true;
    } catch (error) {
      input.select();
      input.setSelectionRange(0, input.value.length);
      copied = document.execCommand('copy');
    }

    if (!copied) return;
    button.classList.add('copied');
    button.querySelector('i').className = 'fa-solid fa-check';
    button.querySelector('span').textContent = 'Copié !';
    window.setTimeout(function () {
      button.classList.remove('copied');
      button.querySelector('i').className = 'fa-regular fa-copy';
      button.querySelector('span').textContent = 'Copier';
    }, 2000);
  });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../views/layout/footer.php';
