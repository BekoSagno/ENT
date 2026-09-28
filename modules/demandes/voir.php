<?php
// modules/demandes/voir.php — Détail d'une demande (vue DG / valideur / demandeur)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/personnel.php';
require_once __DIR__ . '/../../models/demandes.php';

session_demarrer();
exiger_connexion();
demandes_assurer_schema();

$uid     = (int)user_id();
$mon_pid = personnel_assurer_profil($uid);
$peut_rh = a_permission('PERSONNEL', 'VALIDER');
$est_dg  = demandes_est_dg();

$id = (int)($_GET['id'] ?? 0);
$d  = demandes_par_id($id);

if (!$d || !demandes_peut_voir_detail($d, $uid, $mon_pid, $peut_rh, $est_dg)) {
    set_flash('error', 'Demande introuvable ou accès refusé.');
    redirect('/modules/demandes/demandes.php');
}

$stc = [
    'EN_ATTENTE'    => ['En attente', '#fef3c7', '#92400e'],
    'EN_ATTENTE_DG' => ['Attente DG', '#dbeafe', '#1e40af'],
    'APPROUVE'      => ['Approuvé', '#dcfce7', '#166534'],
    'REFUSE'        => ['Refusé', '#fee2e2', '#991b1b'],
    'ANNULE'        => ['Annulé', '#f3f4f6', '#6b7280'],
];
$b = $stc[$d['statut']] ?? $stc['EN_ATTENTE'];
$agents = ($d['categorie'] === 'MISSION') ? demandes_mission_agents($id) : [];

$valide_par_nom = demandes_nom_utilisateur((int)($d['valide_par'] ?? 0));
$valide_resp_nom = demandes_nom_utilisateur((int)($d['valide_resp_par'] ?? 0));
$valide_dg_nom = demandes_nom_utilisateur((int)($d['valide_dg_par'] ?? 0));

$retour = ($peut_rh || $est_dg)
    ? '/modules/demandes/demandes.php?tab=historique_val&focus=' . $id
    : '/modules/demandes/demandes.php?tab=mes&cat=historique&focus=' . $id;

$page_titre      = 'Détail demande' . (!empty($d['reference']) ? ' — ' . $d['reference'] : '');
$page_breadcrumb = 'RH · Mes demandes · Détail';
$module_actif    = 'PERSONNEL_DEMANDES';

include __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/_demandes_ui.php';
demandes_ui_styles();
?>

<style>
.dm-detail{max-width:820px;margin:0 auto;}
.dm-detail-hero{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:16px;}
.dm-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:700px){.dm-detail-grid{grid-template-columns:1fr;}}
.dm-detail-item{border:1px solid var(--border);border-radius:10px;padding:12px 14px;background:var(--surface);}
.dm-detail-item .k{font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;}
.dm-detail-item .v{margin-top:4px;font-size:.92rem;font-weight:600;color:var(--text);line-height:1.45;word-break:break-word;}
.dm-detail-block{margin-top:14px;border:1px solid var(--border);border-radius:12px;padding:14px 16px;background:var(--surface);}
.dm-detail-block h3{margin:0 0 8px;font-size:.9rem;}
.dm-detail-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;}
</style>

<div class="dm-detail">
  <div class="dm-detail-hero">
    <div>
      <a href="<?= e($retour) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
      <h2 style="margin:12px 0 4px;font-size:1.15rem;">
        <?= e($d['type_libelle'] ?? demandes_type_libelle($d)) ?>
        <?php if (!empty($d['reference'])): ?><span class="dm-ref"><?= e($d['reference']) ?></span><?php endif; ?>
      </h2>
      <div class="dm-card-meta"><?= e($d['employe_nom'] ?? '') ?></div>
    </div>
    <span class="dm-statut" style="background:<?= $b[1] ?>;color:<?= $b[2] ?>;"><?= e(demandes_libelle_statut($d['statut'])) ?></span>
  </div>

  <div class="dm-detail-grid">
    <div class="dm-detail-item">
      <div class="k">Catégorie</div>
      <div class="v"><?= e(demandes_libelle_categorie($d['categorie'] ?? '')) ?></div>
    </div>
    <div class="dm-detail-item">
      <div class="k">Période</div>
      <div class="v"><?= e(format_date($d['date_debut'])) ?> → <?= e(format_date($d['date_fin'])) ?> · <?= e(demandes_fmt_jours($d['nombre_jours'])) ?> j.</div>
    </div>
    <div class="dm-detail-item">
      <div class="k">Soumise le</div>
      <div class="v"><?= !empty($d['cree_le']) ? e(format_datetime($d['cree_le'])) : '—' ?></div>
    </div>
    <div class="dm-detail-item">
      <div class="k">Demandeur</div>
      <div class="v"><?= e($d['employe_nom'] ?? '—') ?></div>
    </div>
  </div>

  <?php if (!empty($d['motif'])): ?>
  <div class="dm-detail-block">
    <h3>Motif / observations</h3>
    <div><?= nl2br(e($d['motif'])) ?></div>
  </div>
  <?php endif; ?>

  <?php if (($d['categorie'] ?? '') === 'MISSION'): ?>
  <div class="dm-detail-block">
    <h3>Détails de la mission</h3>
    <div class="dm-detail-grid" style="margin-top:8px;">
      <div class="dm-detail-item"><div class="k">Destination</div><div class="v"><?= e($d['destination'] ?? '—') ?></div></div>
      <div class="dm-detail-item"><div class="k">Moyen de transport</div><div class="v"><?= e($d['moyen_transport'] ?: '—') ?></div></div>
      <div class="dm-detail-item"><div class="k">Frais estimés</div><div class="v"><?= e($d['frais_estimes'] ?: '—') ?></div></div>
      <div class="dm-detail-item"><div class="k">N° ordre de mission</div><div class="v"><?= e($d['ordre_mission_numero'] ?: '—') ?></div></div>
    </div>
    <?php if (!empty($d['objet_mission'])): ?>
    <div style="margin-top:12px;">
      <div class="k" style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Objet de mission</div>
      <div style="margin-top:6px;line-height:1.55;"><?= $d['objet_mission'] /* HTML déjà nettoyé à la saisie */ ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($d['prise_en_charge'])): ?>
    <div style="margin-top:12px;">
      <div class="k" style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Prise en charge</div>
      <div style="margin-top:6px;"><?= nl2br(e($d['prise_en_charge'])) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($agents): ?>
    <div style="margin-top:12px;">
      <div class="k" style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Agents</div>
      <ul style="margin:8px 0 0;padding-left:18px;">
        <?php foreach ($agents as $a): ?>
        <li><?= e(trim(($a['prenoms'] ?? '') . ' ' . ($a['nom'] ?? ''))) ?><?php if (!empty($a['fonction'])): ?> — <?= e($a['fonction']) ?><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="dm-detail-block">
    <h3>Validation</h3>
    <div class="dm-detail-grid" style="margin-top:8px;">
      <?php if ($valide_resp_nom || !empty($d['valide_resp_le'])): ?>
      <div class="dm-detail-item">
        <div class="k">Visa responsable</div>
        <div class="v"><?= e($valide_resp_nom ?: '—') ?><?php if (!empty($d['valide_resp_le'])): ?><br><span style="font-weight:500;color:var(--text-muted);font-size:.8rem;"><?= e(format_datetime($d['valide_resp_le'])) ?></span><?php endif; ?></div>
      </div>
      <?php endif; ?>
      <?php if ($valide_dg_nom || !empty($d['valide_dg_le'])): ?>
      <div class="dm-detail-item">
        <div class="k">Validation DG</div>
        <div class="v"><?= e($valide_dg_nom ?: '—') ?><?php if (!empty($d['valide_dg_le'])): ?><br><span style="font-weight:500;color:var(--text-muted);font-size:.8rem;"><?= e(format_datetime($d['valide_dg_le'])) ?></span><?php endif; ?></div>
      </div>
      <?php endif; ?>
      <?php if ($valide_par_nom && !$valide_dg_nom && !$valide_resp_nom): ?>
      <div class="dm-detail-item">
        <div class="k">Traité par</div>
        <div class="v"><?= e($valide_par_nom) ?></div>
      </div>
      <?php endif; ?>
      <div class="dm-detail-item">
        <div class="k">Statut</div>
        <div class="v"><?= e(demandes_libelle_statut($d['statut'])) ?></div>
      </div>
    </div>
    <?php if (!empty($d['commentaire_validation'])): ?>
    <div class="dm-comment" style="margin-top:12px;"><i class="fa-solid fa-comment"></i> <?= e($d['commentaire_validation']) ?></div>
    <?php endif; ?>
  </div>

  <div class="dm-detail-actions">
    <a href="<?= e($retour) ?>" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour à l'historique</a>
    <?php if ($d['categorie'] === 'MISSION' && $d['statut'] === 'APPROUVE'): ?>
    <a href="mission_print.php?id=<?= $id ?>" class="btn btn-primary" target="_blank">
      <i class="fa-solid fa-print"></i> Ordre de mission (PDF)
    </a>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php';
