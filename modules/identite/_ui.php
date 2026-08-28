<?php
// modules/evaluation/_ui.php — aide  d'affichage (module evaluation )
if (!function_exists('identite_ui_styles')) {
function identite_ui_styles(): void { ?>
<style>
.id-hero{background:linear-gradient(135deg,var(--blue-900),var(--blue-600));border-radius:var(--radius);padding:28px 32px;color:#fff;margin-bottom:24px;box-shadow:0 8px 28px rgba(26,92,204,.28);}
.id-hero h2{font-size:1.4rem;font-weight:800;margin-bottom:8px;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.12);}
.id-hero p{font-size:.88rem;color:#e8f0ff;margin:0;line-height:1.55;}
.id-search{margin-bottom:22px;position:relative;}
.id-search-field{position:relative;flex:1;min-width:200px;}
.id-search-field .form-control{padding-left:38px;padding-right:38px;font-size:.9rem;border:1.5px solid var(--border);width:100%;}
.id-search-field i.fa-search{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--blue-600);z-index:1;pointer-events:none;}
.id-search-field .id-search-clear{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:none;background:transparent;color:#94a3b8;cursor:pointer;padding:4px 8px;border-radius:6px;text-decoration:none;line-height:1;}
.id-search-field .id-search-clear:hover{color:var(--blue-700);background:#f1f5f9;}
.id-search-clear{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:none;background:transparent;color:#94a3b8;cursor:pointer;padding:4px 8px;border-radius:6px;}
.id-search-clear:hover{color:var(--blue-700);background:#f1f5f9;}
.id-search-panel{margin-bottom:22px;}
.id-search-meta{font-size:.82rem;font-weight:700;color:var(--text-muted);margin-bottom:12px;}
.id-result-snippet{font-size:.78rem;color:#4a5d78;margin-top:6px;line-height:1.45;}
.id-result-sub{font-size:.75rem;color:var(--text-muted);margin-top:4px;}
.id-result-empty{text-align:center;color:var(--text-muted);padding:24px;font-style:italic;}
.id-search-stars{color:#f59e0b;font-size:.72rem;margin-left:8px;letter-spacing:1px;}
mark{background:#fef08a;padding:0 2px;border-radius:2px;}
.id-card-maturite{margin-top:auto;padding-top:8px;border-top:1px solid #e8eef8;}
.id-card-maturite-lbl{font-size:.75rem;font-weight:700;margin-left:4px;}
.id-card-dim{opacity:.35;transform:none !important;pointer-events:none;}
.id-card.id-card-dim[href]{pointer-events:none;}
.id-swot-preview{background:#fff;border:2px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:28px;box-shadow:0 2px 10px rgba(13,50,120,.05);overflow:hidden;min-width:0;}
.id-swot-preview-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap;}
.id-swot-preview-head h3{margin:0;font-size:.95rem;font-weight:800;color:var(--blue-950);display:flex;align-items:center;gap:8px;}
.id-swot-voir-tout{font-size:.82rem;font-weight:700;color:var(--blue-600);text-decoration:none;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;flex-shrink:0;}
.id-swot-voir-tout:hover{color:var(--blue-800);text-decoration:underline;}
.id-swot-preview-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;min-width:0;}
.id-swot-preview-item{min-width:0;padding:10px 12px;border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;}
.id-swot-preview-item.id-swot-preview-f{border-color:#bbf7d0;background:#f6fef9;}
.id-swot-preview-item.id-swot-preview-w{border-color:#fecaca;background:#fffafa;}
.id-swot-preview-item.id-swot-preview-o{border-color:#bfdbfe;background:#f8fbff;}
.id-swot-preview-item.id-swot-preview-m{border-color:#fed7aa;background:#fffaf5;}
.id-swot-preview-lbl{display:block;font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px;}
.id-swot-preview-f .id-swot-preview-lbl{color:#166534;}
.id-swot-preview-w .id-swot-preview-lbl{color:#991b1b;}
.id-swot-preview-o .id-swot-preview-lbl{color:#1e40af;}
.id-swot-preview-m .id-swot-preview-lbl{color:#c2410c;}
.id-swot-preview-val{display:block;font-size:.8rem;line-height:1.45;color:#334155;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;min-width:0;}
.id-swot-vide-inline{color:#94a3b8;font-style:italic;font-weight:400;}
.id-swot-preview-reco{font-size:.82rem;color:#334155;margin:12px 0 0;padding-top:12px;border-top:1px solid #e8eef8;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;min-width:0;}
.id-swot-preview-reco .id-swot-preview-reco-txt{display:block;margin-top:6px;}
.id-swot-preview-reco strong{color:#475569;font-weight:700;}
.id-swot-preview-rows{display:flex;flex-direction:column;gap:12px;}
.id-swot-preview-row-lbl{font-size:.7rem;font-weight:700;color:#64748b;margin:0 0 6px;text-transform:uppercase;letter-spacing:.03em;}
.id-swot-preview.id-card-dim{pointer-events:auto;}
@media(max-width:900px){.id-swot-preview-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:480px){.id-swot-preview-grid{grid-template-columns:1fr;}}
.id-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;margin-bottom:28px;}
.id-card{background:#fff;border:2px solid var(--border);border-radius:14px;padding:22px;text-decoration:none;color:inherit;transition:transform .15s,box-shadow .15s,border-color .15s;display:flex;flex-direction:column;gap:12px;min-height:155px;box-shadow:0 2px 10px rgba(13,50,120,.05);}
.id-card:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(13,50,120,.14);border-color:var(--blue-400);}
.id-card-active{border-color:var(--blue-300);background:linear-gradient(180deg,#fff 0%,var(--blue-50) 100%);}
.id-card-upcoming{pointer-events:none;border:2px dashed #b8c9e8;background:#f8fbff;opacity:1;}
.id-card-upcoming h3{color:var(--blue-900);font-size:1rem;}
.id-card-upcoming p{color:#3d4f6f;font-size:.82rem;font-weight:500;}
.id-card-upcoming .id-card-icon{background:#e0ebff;color:var(--blue-700);}
.id-card-icon{width:48px;height:48px;border-radius:12px;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;}
.id-card h3{font-size:1rem;font-weight:800;color:var(--blue-950);margin:0;line-height:1.3;}
.id-card p{font-size:.82rem;color:#4a5d78;margin:0;flex:1;line-height:1.5;font-weight:500;}
.id-score{font-size:.82rem;font-weight:700;display:inline-flex;align-items:center;gap:6px;}
.id-stars{display:inline-flex;gap:2px;font-size:.78rem;}
.id-star{color:#cbd5e1;}
.id-star.on{color:#f59e0b;}
.id-preuve-hint{font-size:.72rem;color:#64748b;display:inline-flex;align-items:center;gap:4px;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.id-preuve-box{background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:10px;padding:14px 16px;font-size:.9rem;line-height:1.6;color:#0b3a5c;}
.id-preuve-box.vide{color:#64748b;font-style:italic;border-style:dashed;border-color:#cbd5e1;background:#f8fafc;}
.id-num-badge{font-size:.78rem;font-weight:800;color:var(--blue-700);background:var(--blue-100);padding:4px 10px;border-radius:8px;}
.id-dots{display:inline-flex;gap:4px;}
.id-dot{width:9px;height:9px;border-radius:50%;background:#cbd5e1;}
.id-dot.on{background:var(--blue-600);}
.id-bar{height:8px;background:#e2e8f0;border-radius:20px;overflow:hidden;margin-top:4px;}
.id-bar-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,var(--blue-500),var(--blue-600));}
.id-badge-ac{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:.72rem;font-weight:700;background:#fef3c7;color:#92400e;border:1px solid #fde68a;}
.id-badge-ok{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:.72rem;font-weight:700;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
.id-badge-info{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:.72rem;font-weight:700;background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;}
.id-badge-soon{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:.72rem;font-weight:700;background:#ffedd5;color:#c2410c;border:1px solid #fdba74;}
.id-topbar{display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap;}
.id-topbar .spacer{flex:1;}
.id-critere{border:2px solid var(--border);border-radius:14px;margin-bottom:14px;overflow:hidden;background:#fff;box-shadow:0 1px 6px rgba(13,50,120,.04);}
.id-critere-h{padding:16px 20px;cursor:pointer;display:flex;align-items:center;gap:14px;user-select:none;}
.id-critere-h:hover{background:var(--blue-50);}
.id-critere-code{font-size:.74rem;font-weight:800;color:var(--blue-700);background:var(--blue-100);padding:5px 11px;border-radius:6px;flex-shrink:0;}
.id-critere-titre{font-size:.98rem;font-weight:700;flex:1;color:var(--blue-950);line-height:1.4;}
.id-critere-body{display:none;padding:0 20px 20px;border-top:1px solid var(--border);}
.id-critere.open .id-critere-body{display:block;}
.id-critere.open .id-critere-h{background:var(--blue-50);}
.id-section{margin-top:18px;}
.id-section-label{font-size:.74rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#33415a;margin-bottom:9px;}
.id-reponse{background:#fff;border:2px solid var(--blue-200);border-radius:10px;padding:16px 18px;font-size:.96rem;line-height:1.7;color:#0f1d33;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;}
.id-reponse.vide{color:#64748b;font-style:italic;border-style:dashed;border-color:#cbd5e1;background:#f8fafc;white-space:normal;}
.id-ref{font-size:.9rem;color:#1e293b;line-height:1.65;}
.id-ref strong{color:var(--blue-950);font-weight:700;}
.id-pieces{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;}
.id-piece{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;background:#fff;border:1.5px solid var(--border);border-radius:8px;font-size:.8rem;text-decoration:none;color:var(--blue-800);font-weight:600;}
.id-piece:hover{border-color:var(--blue-400);background:var(--blue-50);}
.id-id-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px;}
.id-id-item{background:#fff;border:2px solid var(--border);border-radius:12px;padding:20px;box-shadow:0 1px 6px rgba(13,50,120,.04);}
.id-id-item label{display:block;font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#5a6b85;margin-bottom:10px;}
.id-id-val{font-size:.92rem;font-weight:600;color:var(--blue-950);line-height:1.55;min-height:1.5em;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;}
.id-id-val.vide{color:#64748b;font-style:italic;white-space:normal;}
.id-id-item textarea.form-control{min-height:72px;resize:vertical;line-height:1.5;}
.id-synth{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:20px;}
.id-synth-box{background:#f8fafc;border:1.5px solid var(--border);border-radius:10px;padding:16px;}
.id-synth-box strong{display:block;font-size:.7rem;text-transform:uppercase;color:#5a6b85;margin-bottom:8px;}
.id-result{background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:14px 18px;margin-bottom:10px;}
.id-result a{color:var(--blue-700);font-weight:700;text-decoration:none;font-size:.9rem;}
.id-swot-wrap{overflow-x:auto;margin:0 -4px;padding:4px;}
.id-swot-table{width:100%;border-collapse:separate;border-spacing:10px;table-layout:fixed;min-width:720px;}
.id-swot-table th{font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;padding:10px 14px;text-align:left;border-radius:10px;}
.id-swot-table th.id-swot-col-num{width:44px;text-align:center;background:#f1f5f9;color:#64748b;}
.id-swot-table th.id-swot-col-f{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0;}
.id-swot-table th.id-swot-col-w{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;}
.id-swot-table th.id-swot-col-o{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;}
.id-swot-table th.id-swot-col-t{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;}
.id-swot-table td{vertical-align:top;padding:0;border:none;}
.id-swot-cell{display:block;min-height:72px;padding:14px 16px;background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;font-size:.88rem;line-height:1.6;color:var(--blue-950);word-wrap:break-word;overflow-wrap:anywhere;}
.id-swot-cell.id-swot-cell-f{border-color:#bbf7d0;background:#fafffc;}
.id-swot-cell.id-swot-cell-w{border-color:#fecaca;background:#fffbfb;}
.id-swot-cell.id-swot-cell-o{border-color:#bfdbfe;background:#f8fbff;}
.id-swot-cell.id-swot-cell-t{border-color:#fed7aa;background:#fffaf5;}
.id-swot-num{display:flex;align-items:center;justify-content:center;min-height:72px;font-size:1rem;font-weight:800;color:var(--blue-700);background:var(--blue-50);border:1.5px solid var(--blue-200);border-radius:12px;}
.id-swot-text{margin:0;}
.id-swot-vide{color:#94a3b8;font-style:italic;font-size:.82rem;}
.id-swot-table textarea.form-control{min-height:80px;font-size:.88rem;line-height:1.5;resize:vertical;border-radius:10px;}
.id-swot-rows-mobile{display:none;}
@media(max-width:900px){
  .id-swot-wrap{display:none;}
  .id-swot-rows-mobile{display:flex;flex-direction:column;gap:16px;}
  .id-swot-row-card{background:#fff;border:1.5px solid var(--border);border-radius:14px;overflow:hidden;}
  .id-swot-row-card-h{padding:10px 16px;background:var(--blue-50);font-weight:800;font-size:.85rem;color:var(--blue-900);border-bottom:1px solid var(--border);}
  .id-swot-row-card-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:12px;}
  .id-swot-mini label{display:block;font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;}
  .id-swot-mini.id-swot-mini-f label{color:#166534;}
  .id-swot-mini.id-swot-mini-w label{color:#991b1b;}
  .id-swot-mini.id-swot-mini-o label{color:#1e40af;}
  .id-swot-mini.id-swot-mini-t label{color:#c2410c;}
}
@media(max-width:640px){.id-grid,.id-id-grid{grid-template-columns:1fr;}.id-swot-row-card-grid{grid-template-columns:1fr;}}

/* Aide courte + statut critère */
.id-hint{font-size:.78rem;color:var(--text-muted);margin:-4px 0 14px;line-height:1.5;}
.id-doc-pill{font-size:.72rem;font-weight:700;color:var(--blue-700);background:var(--blue-100);padding:3px 9px;border-radius:20px;display:inline-flex;align-items:center;gap:4px;flex-shrink:0;}
.id-status{font-size:.66rem;font-weight:800;padding:3px 10px;border-radius:20px;flex-shrink:0;text-transform:uppercase;letter-spacing:.03em;}
.id-status.ok{background:#dcfce7;color:#166534;}
.id-status.todo{background:#fef3c7;color:#92400e;}
.id-status.warn{background:#ffedd5;color:#c2410c;}
.id-status.risk{background:#fee2e2;color:#b91c1c;}

/* Référentiels d'évaluation — résumé readiness */
.id-eval-summary{display:flex;flex-wrap:wrap;gap:22px;align-items:center;background:#fff;border:2px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:22px;box-shadow:0 2px 10px rgba(13,50,120,.05);}
.id-eval-summary-item{display:flex;flex-direction:column;gap:8px;}
.id-eval-summary-lbl{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#5a6b85;}
.id-eval-summary-val{font-size:1.6rem;font-weight:800;line-height:1;}
.id-eval-bar{height:10px;background:#e2e8f0;border-radius:20px;overflow:hidden;}
.id-eval-bar-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,var(--blue-500),var(--blue-600));transition:width .3s;}

/* Bloc référence repliable (notation) */
.id-ref-details{margin-top:14px;border:1.5px dashed var(--border);border-radius:10px;background:#f8fafc;padding:0 14px;}
.id-ref-details>summary{cursor:pointer;font-size:.8rem;font-weight:700;color:var(--blue-700);padding:12px 0;list-style:none;display:flex;align-items:center;gap:8px;}
.id-ref-details>summary::-webkit-details-marker{display:none;}
.id-ref-details>summary:hover{color:var(--blue-900);}
.id-ref-details[open]{padding-bottom:14px;}

/* Zone de téléversement multi-fichiers */
.id-upzone{position:relative;border:2px dashed var(--blue-300);border-radius:12px;background:var(--blue-50);padding:20px 18px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;}
.id-upzone:hover,.id-upzone.drag{border-color:var(--blue-500);background:#e8f1ff;}
.id-upzone .id-upinput{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;}
.id-upzone>i{font-size:1.5rem;color:var(--blue-500);display:block;margin-bottom:8px;}
.id-upzone-main{font-size:.86rem;font-weight:700;color:var(--blue-900);}
.id-upzone-sub{font-size:.72rem;color:var(--text-muted);margin-top:4px;}
.id-upzone-list{display:flex;flex-direction:column;gap:6px;margin-top:10px;}
.id-upfile{display:flex;align-items:center;gap:10px;padding:8px 12px;background:#fff;border:1.5px solid var(--border);border-radius:9px;font-size:.82rem;}
.id-upfile>i{color:var(--blue-600);flex-shrink:0;}
.id-upfile.err{border-color:#fecaca;background:#fff5f5;}
.id-upfile.err>i,.id-upfile.err .id-upfile-size{color:#dc2626;}
.id-upfile-name{flex:1;min-width:0;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--blue-950);}
.id-upfile-size{font-size:.72rem;color:var(--text-muted);white-space:nowrap;}
.id-upfile-x{border:none;background:#f1f5f9;color:#64748b;width:24px;height:24px;border-radius:6px;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;}
.id-upfile-x:hover{background:#fee2e2;color:#dc2626;}

/* État « à venir » (référentiel non encore disponible) */
.id-empty{text-align:center;max-width:560px;margin:30px auto;padding:36px 28px;background:#fff;border:1.5px solid var(--border);border-radius:14px;}
.id-empty-icon{width:64px;height:64px;margin:0 auto 16px;border-radius:50%;background:var(--blue-50);color:var(--blue-500);display:flex;align-items:center;justify-content:center;font-size:1.6rem;}
.id-empty h3{margin:0 0 10px;font-size:1.15rem;color:var(--blue-950);}
.id-empty p{margin:0;font-size:.9rem;line-height:1.55;color:var(--text-muted);}
</style>
<?php }}

function identite_ui_score_etoiles(?int $score): string {
    $html = '<span class="id-stars" title="' . ($score !== null ? $score . '/5' : 'Non noté') . '">';
    for ($i = 1; $i <= 5; $i++) {
        $on = ($score !== null && $i <= $score) ? ' on' : '';
        $html .= '<i class="fa-solid fa-star id-star' . $on . '"></i>';
    }
    return $html . '</span>';
}

function identite_ui_score_dots(?int $score): string {
    return identite_ui_score_etoiles($score);
}

function identite_ui_afficher_texte(?string $texte): string {
    if (!identite_est_complete($texte)) {
        return '<span class="vide">' . e(IDENTITE_A_COMPLETER) . '</span>';
    }
    return nl2br(e($texte));
}

function identite_ui_swot_cell(?string $texte, string $type = ''): string {
    $cls = 'id-swot-cell' . ($type ? ' id-swot-cell-' . $type : '');
    if (!identite_est_complete($texte)) {
        return '<div class="' . $cls . '"><span class="id-swot-vide">' . e(IDENTITE_A_COMPLETER) . '</span></div>';
    }
    return '<div class="' . $cls . '"><div class="id-swot-text">' . nl2br(e($texte)) . '</div></div>';
}

function identite_ui_recherche_active(string $q): bool {
    return mb_strlen(trim($q)) >= 2;
}

function identite_ui_recherche_form(string $q, string $placeholder, string $clear_url, array $hidden = []): void {
    ?>
<form method="get" action="" class="id-search" style="margin-bottom:22px;">
  <?php foreach ($hidden as $name => $val):
      if ($val === '' || $val === null) continue; ?>
  <input type="hidden" name="<?= e($name) ?>" value="<?= e((string)$val) ?>">
  <?php endforeach; ?>
  <div class="id-search-field">
    <i class="fa-solid fa-search"></i>
    <input type="search" name="q" class="form-control" autocomplete="off"
           value="<?= e($q) ?>"
           placeholder="<?= e($placeholder) ?>"
           aria-label="<?= e($placeholder) ?>">
    <?php if ($q !== ''): ?>
    <a href="<?= e($clear_url) ?>" class="id-search-clear" title="Effacer la recherche">
      <i class="fa-solid fa-xmark"></i>
    </a>
    <?php endif; ?>
  </div>
</form>
    <?php
}

function identite_ui_recherche_panneau(string $q, array $resultats, callable $render_item, string $vide_hint): void {
    if ($q === '') return;
    ?>
<div class="id-search-panel">
  <div class="id-search-meta">
    <?php if (mb_strlen($q) < 2): ?>
      Saisissez au moins 2 caractères.
    <?php elseif (empty($resultats)): ?>
      Aucun résultat pour « <?= e($q) ?> »
    <?php else: ?>
      <?= count($resultats) ?> résultat<?= count($resultats) > 1 ? 's' : '' ?> pour « <?= e($q) ?> »
    <?php endif; ?>
  </div>
  <div class="id-search-list">
    <?php foreach ($resultats as $r) { $render_item($r); } ?>
    <?php if (mb_strlen($q) >= 2 && empty($resultats)): ?>
    <div class="id-result-empty"><?= e($vide_hint) ?></div>
    <?php endif; ?>
  </div>
</div>
    <?php
}

function identite_ui_recherche_item_dgrs(array $r): void {
    ?>
<a href="<?= e($r['url']) ?>" class="id-result" style="display:block;text-decoration:none;color:inherit;margin-bottom:10px;">
  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <span style="font-size:.72rem;font-weight:800;color:var(--blue-600);"><?= e($r['code'] ?? '') ?></span>
    <?php if (isset($r['score']) && $r['score'] !== null): ?>
    <span class="id-search-stars"><?= (int)$r['score'] ?>/5</span>
    <?php endif; ?>
  </div>
  <div style="font-size:.88rem;font-weight:700;color:var(--blue-950);margin-top:4px;"><?= e($r['titre'] ?? '') ?></div>
  <div class="id-result-sub"><?= e($r['sous'] ?? '') ?></div>
  <?php if (!empty($r['snippet'])): ?>
  <div class="id-result-snippet"><?= e($r['snippet']) ?></div>
  <?php endif; ?>
</a>
    <?php
}

function identite_ui_recherche_item_anaq(array $r): void {
    ?>
<a href="<?= e($r['url']) ?>" class="aa-result-item">
  <div class="aa-result-code"><?= e($r['code'] ?? '') ?></div>
  <div class="aa-result-titre"><?= e($r['titre'] ?? '') ?></div>
  <div class="id-result-sub"><?= e($r['sous'] ?? '') ?></div>
  <?php if (!empty($r['snippet'])): ?>
  <div class="id-result-snippet"><?= e($r['snippet']) ?></div>
  <?php endif; ?>
</a>
    <?php
}

function auto_anaq_filtrer_recherche(array $items, string $filtre, string $q): array {
    if ($filtre === 'tout' || $filtre === '') return $items;
    $ql = mb_strtolower($q);
    return array_values(array_filter($items, function (array $r) use ($filtre, $ql): bool {
        $sn = mb_strtolower($r['snippet'] ?? '');
        if ($filtre === 'question') {
            return mb_strpos($sn, '?') !== false || ($ql !== '' && mb_strpos($sn, $ql) !== false);
        }
        if ($filtre === 'preuves') {
            return mb_strpos($sn, 'décret') !== false || mb_strpos($r['sous'] ?? '', 'Réf') !== false;
        }
        if ($filtre === 'reponse') {
            return mb_strpos($sn, 'crict') !== false;
        }
        return true;
    }));
}

function identite_ui_script_upzone(): void {
    ?>
<script>
document.querySelectorAll('[data-upzone]').forEach(function (zone) {
  var input = zone.querySelector('.id-upinput');
  var list  = zone.parentElement.querySelector('[data-upzone-list]');
  if (!input || !list) return;
  var store = new DataTransfer();
  var MAX   = 20 * 1024 * 1024;

  function human(n) {
    if (n >= 1048576) return (n / 1048576).toFixed(1) + ' Mo';
    if (n >= 1024) return Math.round(n / 1024) + ' Ko';
    return n + ' o';
  }
  function iconFor(name) {
    var ext = (name.split('.').pop() || '').toLowerCase();
    if (ext === 'pdf') return 'fa-file-pdf';
    if (ext === 'doc' || ext === 'docx' || ext === 'odt') return 'fa-file-word';
    if (ext === 'xls' || ext === 'xlsx' || ext === 'csv' || ext === 'ods') return 'fa-file-excel';
    if (ext === 'ppt' || ext === 'pptx') return 'fa-file-powerpoint';
    if (ext === 'jpg' || ext === 'jpeg' || ext === 'png' || ext === 'webp' || ext === 'gif') return 'fa-file-image';
    if (ext === 'txt') return 'fa-file-lines';
    return 'fa-file';
  }
  function esc(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function render() {
    list.innerHTML = '';
    Array.prototype.forEach.call(store.files, function (f, i) {
      var tooBig = f.size > MAX;
      var row = document.createElement('div');
      row.className = 'id-upfile' + (tooBig ? ' err' : '');
      row.innerHTML =
        '<i class="fa-solid ' + iconFor(f.name) + '"></i>' +
        '<span class="id-upfile-name">' + esc(f.name) + '</span>' +
        '<span class="id-upfile-size">' + human(f.size) + (tooBig ? ' · trop volumineux' : '') + '</span>' +
        '<button type="button" class="id-upfile-x" data-i="' + i + '" title="Retirer"><i class="fa-solid fa-xmark"></i></button>';
      list.appendChild(row);
    });
    input.files = store.files;
  }
  function addFiles(files) {
    Array.prototype.forEach.call(files, function (f) {
      var dup = false;
      Array.prototype.forEach.call(store.files, function (g) {
        if (g.name === f.name && g.size === f.size) dup = true;
      });
      if (!dup) store.items.add(f);
    });
    render();
  }
  input.addEventListener('change', function () {
    var picked = [];
    Array.prototype.forEach.call(input.files, function (f) { picked.push(f); });
    addFiles(picked);
  });
  list.addEventListener('click', function (e) {
    var btn = e.target.closest('.id-upfile-x');
    if (!btn) return;
    var idx = parseInt(btn.dataset.i, 10);
    var tmp = new DataTransfer();
    Array.prototype.forEach.call(store.files, function (f, i) { if (i !== idx) tmp.items.add(f); });
    store = tmp;
    render();
  });
  ['dragenter', 'dragover'].forEach(function (ev) {
    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('drag'); });
  });
  ['dragleave', 'dragend', 'drop'].forEach(function (ev) {
    zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('drag'); });
  });
  zone.addEventListener('drop', function (e) {
    if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files);
  });
});
</script>
    <?php
}

function identite_ui_swot_apercu_ligne(array $row): string {
    $cols = [
        ['forces',       'Forces',       'f'],
        ['faiblesses',   'Faiblesses',   'w'],
        ['opportunites', 'Opportunités', 'o'],
        ['menaces',      'Menaces',      'm'],
    ];
    $html = '<div class="id-swot-preview-grid">';
    foreach ($cols as [$key, $lbl, $cls]) {
        $raw = trim((string)($row[$key] ?? ''));
        $complet = identite_est_complete($raw);
        $html .= '<div class="id-swot-preview-item id-swot-preview-' . $cls . '">';
        $html .= '<span class="id-swot-preview-lbl">' . e($lbl) . '</span>';
        if ($complet) {
            $html .= '<span class="id-swot-preview-val">' . nl2br(e($raw)) . '</span>';
        } else {
            $html .= '<span class="id-swot-preview-val id-swot-vide-inline">' . e(IDENTITE_A_COMPLETER) . '</span>';
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

/** Affiche toutes les lignes SWOT en entier (sans troncature). */
function identite_ui_swot_apercu(array $swot): string {
    if (!$swot) {
        return identite_ui_swot_apercu_ligne([]);
    }
    $html = '<div class="id-swot-preview-rows">';
    foreach ($swot as $row) {
        $n = (int)($row['ligne'] ?? 0);
        $html .= '<div class="id-swot-preview-row">';
        if (count($swot) > 1) {
            $html .= '<div class="id-swot-preview-row-lbl">Ligne ' . $n . '</div>';
        }
        $html .= identite_ui_swot_apercu_ligne($row);
        $html .= '</div>';
    }
    return $html . '</div>';
}

if (!function_exists('auto_anaq_ui_styles')) {
function auto_anaq_ui_styles(): void { ?>
<style>
.aa-prog{margin-top:auto;}
.aa-prog-bar{height:8px;background:#e2e8f0;border-radius:20px;overflow:hidden;}
.aa-prog-fill{height:100%;background:linear-gradient(90deg,var(--blue-500),var(--blue-600));border-radius:20px;transition:width .3s;}
.aa-prog-lbl{font-size:.72rem;color:var(--text-muted);margin-top:6px;display:block;}
.aa-prog-hero{margin-top:14px;max-width:420px;}
.aa-prog-hero-meta{display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin-bottom:8px;font-size:.8rem;opacity:.95;}
.aa-prog-hero-meta strong{font-size:.95rem;font-weight:800;}
.aa-prog-hero .aa-prog-bar{height:12px;background:rgba(255,255,255,.25);}
.aa-prog-hero .aa-prog-fill{background:linear-gradient(90deg,#5eead4,#34d399);}
.aa-prog-hero-hint{margin-top:8px;font-size:.72rem;opacity:.75;}
.aa-filtres{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
.aa-filtre{border:1.5px solid var(--border);background:#fff;border-radius:20px;padding:5px 14px;font-size:.75rem;font-weight:700;color:var(--text-muted);cursor:pointer;text-decoration:none;display:inline-block;}
.aa-filtre.on{background:var(--blue-100);border-color:var(--blue-400);color:var(--blue-800);}
.aa-result-item{display:block;padding:14px 16px;border:1.5px solid var(--border);border-radius:12px;margin-bottom:8px;text-decoration:none;color:inherit;transition:background .12s,border-color .12s;}
.aa-result-item:hover{background:var(--blue-50);border-color:var(--blue-300);}
.aa-result-code{font-size:.72rem;font-weight:800;color:var(--blue-600);font-family:var(--mono);}
.aa-result-titre{font-size:.88rem;font-weight:700;color:var(--blue-950);margin-top:4px;}
.aa-ref-block{background:#fff;border:1.5px solid var(--border);border-radius:14px;margin-bottom:18px;overflow:hidden;}
.aa-ref-hd{padding:16px 20px;background:var(--bg);border-bottom:1px solid var(--border);}
.aa-ref-code{font-size:.7rem;font-weight:800;color:var(--blue-600);text-transform:uppercase;letter-spacing:.05em;}
.aa-ref-hd h3{font-size:.9rem;font-weight:700;margin:6px 0 0;color:var(--blue-950);line-height:1.4;}
.aa-point-list{display:flex;flex-direction:column;}
.aa-point-row{display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid var(--border);text-decoration:none;color:inherit;transition:background .12s;}
.aa-point-row:last-child{border-bottom:none;}
.aa-point-row:hover{background:var(--blue-50);}
.aa-point-code{font-size:.75rem;font-weight:800;color:var(--blue-700);font-family:var(--mono);min-width:48px;}
.aa-point-titre{flex:1;font-size:.84rem;font-weight:600;color:var(--text);}
.aa-point-arrow{color:var(--text-muted);font-size:.75rem;}
.aa-badge{display:inline-flex;padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:700;white-space:nowrap;}
.aa-point-hero{background:#fff;border:1.5px solid var(--border);border-radius:14px;padding:22px 24px;margin-bottom:20px;}
.aa-point-code-lg{font-size:1.1rem;font-weight:800;color:var(--blue-600);font-family:var(--mono);}
.aa-point-ref{font-size:.78rem;color:var(--text-muted);margin:6px 0 10px;}
.aa-point-hero h1{font-size:1.05rem;font-weight:800;color:var(--blue-950);margin:0;line-height:1.45;}
.aa-zone{background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:18px 20px;margin-bottom:14px;}
.aa-zone-lbl{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--blue-700);margin-bottom:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.aa-zone-body{font-size:.9rem;line-height:1.65;color:var(--text);}
.aa-zone-body.vide{color:#94a3b8;font-style:italic;}
.aa-preuves-list{margin:0;padding-left:20px;max-height:220px;overflow-y:auto;}
.aa-preuves-list li{margin-bottom:6px;font-size:.85rem;line-height:1.5;}
.aa-preuves-hidden{display:none;}
.aa-score-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;}
.aa-score-opt{display:flex;flex-direction:column;align-items:center;padding:10px 8px;border:2px solid var(--border);border-radius:10px;cursor:pointer;transition:all .12s;text-align:center;}
.aa-score-opt:has(input:checked){border-color:var(--blue-500);background:var(--blue-50);}
.aa-score-opt input{display:none;}
.aa-score-num{font-size:1.1rem;font-weight:800;color:var(--blue-700);}
.aa-score-lbl{font-size:.65rem;color:var(--text-muted);margin-top:4px;line-height:1.2;}
.aa-nav-points{display:flex;justify-content:space-between;gap:12px;margin-top:24px;flex-wrap:wrap;}
.aa-swot-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;}
.aa-swot-box{padding:12px;border-radius:10px;border:1.5px solid #e2e8f0;}
.aa-swot-box label{font-size:.68rem;font-weight:800;text-transform:uppercase;display:block;margin-bottom:6px;}
.aa-swot-box.f{background:#f6fef9;border-color:#bbf7d0;}.aa-swot-box.f label{color:#166534;}
.aa-swot-box.w{background:#fffafa;border-color:#fecaca;}.aa-swot-box.w label{color:#991b1b;}
.aa-swot-box.o{background:#f8fbff;border-color:#bfdbfe;}.aa-swot-box.o label{color:#1e40af;}
.aa-swot-box.m{background:#fffaf5;border-color:#fed7aa;}.aa-swot-box.m label{color:#c2410c;}
.aa-swot-val{font-size:.84rem;line-height:1.5;}
.aa-swot-card{margin-top:28px;}
.aa-pieces{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;}
@media(max-width:640px){.aa-swot-grid{grid-template-columns:1fr;}.aa-score-row{grid-template-columns:repeat(3,1fr);}}
</style>
<?php }
}
