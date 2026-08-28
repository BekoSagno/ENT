<?php
// modules/rendezvous_dg/_ui.php
if (!function_exists('rdv_dg_ui_styles')) {
    function rdv_dg_ui_styles(): void { ?>
<style>
.rdv-wrap{max-width:1100px;margin:0 auto;}
.rdv-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:18px;}
.rdv-toolbar .spacer{flex:1;}
.rdv-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
.rdv-filters .form-control{min-width:140px;width:auto;}
.rdv-badge{display:inline-flex;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;white-space:nowrap;}
.rdv-badge.EN_ATTENTE{background:#fef3c7;color:#92400e;}
.rdv-badge.CONFIRME{background:#d1fae5;color:#065f46;}
.rdv-badge.REFUSE{background:#fee2e2;color:#991b1b;}
.rdv-badge.ANNULE{background:#e5e7eb;color:#374151;}
.rdv-badge.TERMINE{background:#dbeafe;color:#1e40af;}
.rdv-table{width:100%;border-collapse:collapse;}
.rdv-table th,.rdv-table td{padding:11px 12px;border-bottom:1px solid var(--border);font-size:.84rem;text-align:left;vertical-align:top;}
.rdv-table th{font-size:.72rem;text-transform:uppercase;color:var(--text-muted);background:var(--bg);}
.rdv-empty{
  padding:38px 20px;text-align:center;color:var(--text-muted);font-size:.88rem;
  background:var(--bg);border:1.5px dashed var(--border);border-radius:14px;
}
.rdv-card{
  border:1px solid var(--border);border-radius:14px;background:var(--surface);
  padding:20px 22px;margin-bottom:18px;box-shadow:0 2px 10px rgba(10,33,88,.04);
}
.rdv-card h3{font-size:.95rem;margin:0 0 12px;display:flex;align-items:center;gap:10px;}
.rdv-card h3 i{
  width:32px;height:32px;border-radius:9px;background:var(--blue-50);
  display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;
}
.rdv-grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
@media(max-width:780px){.rdv-grid2{grid-template-columns:1fr;}}
.rdv-meta{font-size:.8rem;color:var(--text-muted);margin-top:4px;}
.rdv-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;}
.rdv-hint{font-size:.78rem;color:var(--text-muted);margin-top:6px;}
.rdv-public-link{display:flex;align-items:center;gap:7px;min-width:260px;}
.rdv-public-link label{font-size:.75rem;font-weight:700;color:var(--text-muted);white-space:nowrap;}
.rdv-public-link input{
  width:235px;min-width:0;padding:9px 11px;border:1px solid var(--border);border-radius:9px;
  background:var(--surface);color:var(--blue-700);font-size:.78rem;cursor:text;
}
.rdv-public-link input:focus{outline:none;border-color:var(--blue-400);box-shadow:0 0 0 3px var(--blue-50);}
.rdv-copy-btn{white-space:nowrap;}
.rdv-copy-btn.copied{background:#d1fae5!important;border-color:#a7f3d0!important;color:#065f46!important;}
@media(max-width:700px){
  .rdv-public-link{width:100%;flex-wrap:wrap;}
  .rdv-public-link label{width:100%;}
  .rdv-public-link input{flex:1;width:auto;}
}

/* Statistiques */
.rdv-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px;}
@media(max-width:900px){.rdv-stats{grid-template-columns:repeat(2,1fr);}}
.rdv-stat{
  display:flex;align-items:center;gap:12px;padding:14px 16px;
  border:1px solid var(--border);border-radius:14px;background:var(--surface);
  box-shadow:0 2px 10px rgba(10,33,88,.04);
}
.rdv-stat .ic{
  width:40px;height:40px;border-radius:11px;display:flex;align-items:center;
  justify-content:center;font-size:1rem;flex-shrink:0;
}
.rdv-stat .v{font-size:1.25rem;font-weight:800;line-height:1.1;}
.rdv-stat .l{font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;}
.rdv-stat.attente .ic{background:#fef3c7;color:#92400e;}
.rdv-stat.confirme .ic{background:#d1fae5;color:#065f46;}
.rdv-stat.termine .ic{background:#dbeafe;color:#1e40af;}
.rdv-stat.refuse .ic{background:#fee2e2;color:#991b1b;}

/* Calendrier créneaux */
.rdv-week-nav{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;}
.rdv-week-nav strong{font-size:.92rem;color:var(--blue-900);}
.rdv-days{display:grid;grid-template-columns:repeat(auto-fill,minmax(148px,1fr));gap:10px;}
.rdv-day{
  border:1.5px solid var(--border);border-radius:14px;background:var(--bg);
  min-height:118px;overflow:hidden;display:flex;flex-direction:column;
}
.rdv-day.has-slots{background:var(--surface);border-color:var(--blue-200,#c7d7f5);}
.rdv-day-head{
  padding:9px 10px;background:var(--blue-50);border-bottom:1px solid var(--border);
  font-size:.78rem;font-weight:800;color:var(--blue-900);text-align:center;
}
.rdv-day-head .d{display:block;font-size:.7rem;font-weight:600;color:var(--text-muted);margin-top:1px;}
.rdv-day-body{padding:9px;display:flex;flex-direction:column;gap:7px;flex:1;}
.rdv-slot{
  display:block;text-align:center;padding:9px 6px;border-radius:9px;
  background:var(--surface);color:var(--blue-700);font-size:.8rem;font-weight:800;
  text-decoration:none;border:1.5px solid var(--blue-200,#c7d7f5);cursor:pointer;width:100%;
  transition:all .13s;
}
.rdv-slot:hover{
  background:var(--blue-600);border-color:var(--blue-600);color:#fff;
  transform:translateY(-1px);box-shadow:0 6px 14px rgba(19,71,168,.28);
}
.rdv-slot-disabled,.rdv-slot-disabled:hover{
  background:#f1f5f9;border-color:#e2e8f0;color:#94a3b8;cursor:not-allowed;
  transform:none;box-shadow:none;pointer-events:none;opacity:.9;
}
.rdv-slot-disabled .rdv-slot-etat{
  display:block;font-size:.65rem;font-weight:600;margin-top:2px;
  text-transform:uppercase;letter-spacing:.02em;
}
.rdv-slot-empty{font-size:.72rem;color:var(--text-muted);opacity:.7;text-align:center;padding:14px 4px;margin:auto 0;}
.rdv-slot-selected,.rdv-slot-selected:hover{background:#059669;border-color:#059669;color:#fff;transform:none;box-shadow:none;}
.rdv-creneau-box{
  display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:12px;
  background:#ecfdf5;border:1.5px solid #a7f3d0;margin-bottom:16px;font-size:.9rem;
}
.rdv-creneau-box .ic{
  width:38px;height:38px;border-radius:10px;background:#fff;color:#059669;
  display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;
}
.rdv-actions-row{display:flex;flex-wrap:wrap;gap:6px;align-items:center;}
.rdv-actions-row .form-control{min-width:130px;width:auto;}
.btn-danger{background:#dc2626;color:#fff;border:1px solid #dc2626;}
.btn-danger:hover{background:#b91c1c;border-color:#b91c1c;color:#fff;}
.btn-warning{background:#f59e0b;color:#fff;border:1px solid #f59e0b;}
.btn-warning:hover{background:#d97706;border-color:#d97706;color:#fff;}
.btn-icon{padding:7px 10px;min-width:34px;justify-content:center;line-height:1;}
.rdv-edit-panel{
  background:var(--bg);border:1px solid var(--border);border-radius:12px;
  padding:16px 18px;margin-top:10px;
}
.rdv-edit-panel .rdv-grid2{margin-bottom:0;}
.rdv-block-panel{
  background:#fffbeb;border-color:#fde68a;
}
.rdv-block-panel .rdv-block-hint{
  font-size:.8rem;color:var(--text-muted);margin-bottom:12px;line-height:1.5;
}

/* Notifications */
.rdv-notif-shell{max-width:980px;margin:0 auto;}
.rdv-notif-hero{
  position:relative;overflow:hidden;border:1px solid rgba(199,215,245,.9);border-radius:20px;
  background:linear-gradient(135deg,#0a2158 0%,#1347a8 62%,#1d4ed8 100%);
  color:#fff;padding:24px 26px;box-shadow:0 20px 50px rgba(10,33,88,.18);margin-bottom:18px;
}
.rdv-notif-hero:after{
  content:"";position:absolute;right:-60px;top:-60px;width:220px;height:220px;border-radius:999px;
  background:radial-gradient(circle,rgba(255,255,255,.18),rgba(255,255,255,0) 65%);
}
.rdv-notif-hero h2{margin:0 0 8px;font-size:1.35rem;line-height:1.2;color:#fff;}
.rdv-notif-hero p{margin:0;color:#dbeafe;font-size:.92rem;max-width:680px;}
.rdv-notif-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:18px 0 20px;}
@media(max-width:760px){.rdv-notif-stats{grid-template-columns:1fr;}}
.rdv-notif-stat{
  border:1px solid var(--border);border-radius:16px;background:linear-gradient(180deg,#fff,#f8fbff);
  padding:16px 18px;box-shadow:0 10px 28px rgba(15,23,42,.06);
}
.rdv-notif-stat .k{font-size:.72rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:var(--text-muted);}
.rdv-notif-stat .v{display:block;margin-top:6px;font-size:1.45rem;font-weight:900;color:var(--blue-950);}
.rdv-notif-panel{
  border:1px solid var(--border);border-radius:20px;background:#fff;padding:8px 0;
  box-shadow:0 16px 36px rgba(15,23,42,.06);
}
.rdv-notif-item{
  position:relative;padding:18px 22px;border-bottom:1px solid #edf2f7;transition:background .12s ease;
}
.rdv-notif-item:last-child{border-bottom:0;}
.rdv-notif-item:hover{background:#fbfdff;}
.rdv-notif-item.unread{background:linear-gradient(90deg,rgba(219,234,254,.55),rgba(255,255,255,0) 70%);}
.rdv-notif-item.unread:before{
  content:"";position:absolute;left:0;top:18px;bottom:18px;width:4px;border-radius:0 8px 8px 0;background:#2563eb;
}
.rdv-notif-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;}
.rdv-notif-title{display:flex;align-items:center;gap:10px;min-width:0;}
.rdv-notif-dot{
  width:11px;height:11px;border-radius:999px;flex-shrink:0;background:#cbd5e1;box-shadow:0 0 0 5px rgba(148,163,184,.12);
}
.rdv-notif-item.unread .rdv-notif-dot{background:#2563eb;box-shadow:0 0 0 6px rgba(37,99,235,.14);}
.rdv-notif-title strong{font-size:1rem;color:#0f172a;line-height:1.35;}
.rdv-notif-date{
  font-size:.78rem;font-weight:700;color:#94a3b8;white-space:nowrap;
  background:#f8fafc;border:1px solid #e2e8f0;border-radius:999px;padding:5px 10px;
}
.rdv-notif-msg{margin-top:8px;font-size:.92rem;line-height:1.6;color:#475569;max-width:780px;}
.rdv-notif-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;}
.rdv-notif-actions .btn{border-radius:10px;}
.rdv-notif-tag{
  display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:999px;
  font-size:.72rem;font-weight:800;letter-spacing:.03em;text-transform:uppercase;
  background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;
}
.rdv-notif-toolbar{margin-bottom:18px;}
.rdv-notif-toolbar .btn{border-radius:11px;}

/* Détail rendez-vous */
.rdv-detail-shell{max-width:980px;margin:0 auto;}
.rdv-detail-hero{
  position:relative;overflow:hidden;border:1px solid rgba(199,215,245,.9);border-radius:20px;
  background:linear-gradient(135deg,#0a2158 0%,#1347a8 62%,#1d4ed8 100%);
  color:#fff;padding:24px 26px;box-shadow:0 20px 50px rgba(10,33,88,.18);margin-bottom:18px;
}
.rdv-detail-hero:after{
  content:"";position:absolute;right:-50px;top:-50px;width:200px;height:200px;border-radius:999px;
  background:radial-gradient(circle,rgba(255,255,255,.16),rgba(255,255,255,0) 65%);
}
.rdv-detail-hero-top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;position:relative;z-index:1;}
.rdv-detail-ref{font-size:.78rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#bfdbfe;margin:0 0 6px;}
.rdv-detail-hero h2{margin:0;font-size:1.45rem;line-height:1.2;color:#fff;}
.rdv-detail-hero p{margin:8px 0 0;color:#dbeafe;font-size:.9rem;max-width:640px;}
.rdv-detail-hero-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;position:relative;z-index:1;}
.rdv-detail-hero .rdv-badge{border:1px solid rgba(255,255,255,.18);}
.rdv-detail-hero .btn{border-radius:11px;}
.rdv-detail-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:16px;margin-bottom:16px;}
@media(max-width:860px){.rdv-detail-grid{grid-template-columns:1fr;}}
.rdv-detail-card{
  border:1px solid var(--border);border-radius:18px;background:#fff;
  padding:20px 22px;box-shadow:0 14px 32px rgba(15,23,42,.06);
}
.rdv-detail-card h3{
  margin:0 0 16px;font-size:.95rem;display:flex;align-items:center;gap:10px;color:#0f172a;
}
.rdv-detail-card h3 .ic{
  width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#eff6ff,#dbeafe);
  color:#1d4ed8;display:inline-flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;
}
.rdv-detail-rows{display:flex;flex-direction:column;gap:0;}
.rdv-detail-row{
  display:grid;grid-template-columns:120px 1fr;gap:12px;padding:12px 0;
  border-bottom:1px solid #eef2f7;align-items:start;
}
.rdv-detail-row:last-child{border-bottom:0;padding-bottom:0;}
.rdv-detail-row:first-child{padding-top:0;}
.rdv-detail-row .lbl{
  font-size:.72rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#94a3b8;padding-top:2px;
}
.rdv-detail-row .val{font-size:.95rem;font-weight:650;color:#0f172a;line-height:1.5;}
.rdv-detail-row .val.muted{font-weight:500;color:#64748b;}
.rdv-detail-person{
  display:flex;gap:14px;align-items:flex-start;
}
.rdv-detail-avatar{
  width:52px;height:52px;border-radius:16px;flex-shrink:0;
  background:linear-gradient(135deg,#1347a8,#2563eb);color:#fff;
  display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1rem;
  box-shadow:0 10px 24px rgba(37,99,235,.28);
}
.rdv-detail-person-main strong{display:block;font-size:1.05rem;color:#0f172a;margin-bottom:4px;}
.rdv-detail-person-main .meta{font-size:.86rem;color:#64748b;line-height:1.45;}
.rdv-detail-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;}
.rdv-detail-chip{
  display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border-radius:999px;
  background:#f8fafc;border:1px solid #e2e8f0;font-size:.8rem;font-weight:650;color:#334155;
}
.rdv-detail-chip i{color:#2563eb;}
.rdv-detail-objet{
  margin-top:4px;padding:14px 16px;border-radius:14px;background:#f8fbff;border:1px solid #e2e8f0;
  font-size:.95rem;line-height:1.6;color:#1e293b;white-space:pre-wrap;
}
.rdv-detail-hint{
  margin-top:14px;padding:12px 14px;border-radius:12px;background:#fffbeb;border:1px solid #fde68a;
  color:#92400e;font-size:.84rem;line-height:1.5;
}
.rdv-detail-treat{
  border:1px solid #bfdbfe;border-radius:18px;background:linear-gradient(180deg,#f8fbff,#eff6ff);
  padding:20px 22px;box-shadow:0 14px 32px rgba(15,23,42,.05);margin-bottom:16px;
}
.rdv-detail-treat h3{margin:0 0 14px;display:flex;align-items:center;gap:10px;}
.rdv-detail-treat .rdv-split{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
@media(max-width:760px){.rdv-detail-treat .rdv-split{grid-template-columns:1fr;}}
.rdv-detail-treat-box{
  background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px;
}
.rdv-detail-treat-box.refuse{border-color:#fecaca;background:#fffafa;}
.rdv-detail-footer-actions{
  display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;
  margin-top:4px;padding:14px 16px;border-radius:14px;background:#fff;border:1px solid var(--border);
}
</style>
<?php
    }
}

if (!function_exists('rdv_dg_badge')) {
    function rdv_dg_badge(string $statut): string {
        return '<span class="rdv-badge ' . e($statut) . '">' . e(rdv_dg_libelle_statut($statut)) . '</span>';
    }
}

if (!function_exists('rdv_dg_render_calendrier')) {
    /**
     * @param array $semaine résultat de rdv_dg_creneaux_semaine()
     * @param string $base_url URL du formulaire (avec ? déjà ou sans)
     */
    function rdv_dg_render_calendrier(array $semaine, string $base_url, ?string $selected_key = null, bool $cliquable = true): void {
        $lundi = $semaine['lundi'];
        $sep = str_contains($base_url, '?') ? '&' : '?';
        $par = $semaine['par_jour'] ?? [];

        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($lundi . " +$i days"));
            if (!empty($par[$date])) {
                $dates[] = $date;
            }
        }

        $etat_lbl = [
            'passe'  => 'Passé',
            'pris'   => 'Pris',
            'bloque' => 'Bloqué',
        ];
        ?>
        <div class="rdv-week-nav">
          <strong>Semaine du <?= e(rdv_dg_fmt_date($lundi)) ?></strong>
        </div>
        <?php if (!$dates): ?>
        <div class="rdv-empty">Aucun créneau programmé cette semaine.</div>
        <?php else: ?>
        <div class="rdv-days">
        <?php foreach ($dates as $date):
            $js = (int)date('N', strtotime($date));
            $slots = $par[$date];
        ?>
          <div class="rdv-day has-slots">
            <div class="rdv-day-head">
              <?= e(RDV_DG_JOURS[$js] ?? '') ?>
              <span class="d"><?= e(date('d/m', strtotime($date))) ?></span>
            </div>
            <div class="rdv-day-body">
              <?php foreach ($slots as $s):
                $key = $s['dispo_id'] . '|' . $s['date'] . '|' . substr($s['heure_debut'], 0, 5);
                $etat = $s['etat'] ?? 'libre';
                $is_libre = ($etat === 'libre');
                $cls = 'rdv-slot';
                if ($selected_key === $key) $cls .= ' rdv-slot-selected';
                if (!$is_libre) $cls .= ' rdv-slot-disabled';

                $inner = e($s['label']);
                if (!$is_libre) {
                    $inner .= '<span class="rdv-slot-etat">' . e($etat_lbl[$etat] ?? 'Indisponible') . '</span>';
                }

                if ($cliquable && $is_libre) {
                    $href = $base_url . $sep . 'dispo=' . (int)$s['dispo_id']
                        . '&date=' . urlencode($s['date'])
                        . '&heure=' . urlencode(substr($s['heure_debut'], 0, 5));
                    echo '<a class="' . e($cls) . '" href="' . e($href) . '">' . $inner . '</a>';
                } else {
                    echo '<span class="' . e($cls) . '">' . $inner . '</span>';
                }
              endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php
    }
}
