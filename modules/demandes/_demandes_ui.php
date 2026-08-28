<?php
// modules/personnel/_demandes_ui.php

if (!function_exists('demandes_fmt_jours')) {
    function demandes_fmt_jours($n): string {
        return rtrim(rtrim(number_format((float)$n, 1, '.', ''), '0'), '.');
    }
}

if (!function_exists('demandes_ui_styles')) {
    function demandes_ui_styles(): void { ?>
<style>
.dm-wrap{max-width:920px;margin:0 auto;}
.dm-tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:22px;flex-wrap:wrap;}
.dm-tab{padding:10px 16px;font-size:.85rem;font-weight:600;color:var(--text-muted);border-bottom:2px solid transparent;display:inline-flex;align-items:center;gap:7px;text-decoration:none;}
.dm-tab:hover{color:var(--blue-600);}.dm-tab.active{color:var(--blue-600);border-bottom-color:var(--blue-600);}
.dm-pane{display:none;}.dm-pane.active{display:block;}
.dm-badge-warn{background:#dc2626;color:#fff;border-radius:20px;padding:1px 8px;font-size:.72rem;font-weight:700;margin-left:4px;}
.dm-back{margin-bottom:16px;}
.dm-choix-title{text-align:center;margin-bottom:8px;font-size:.95rem;color:var(--text);font-weight:600;}
.dm-choices{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:8px;}
@media(max-width:780px){.dm-choices{grid-template-columns:1fr;}}
.dm-choice{display:block;text-decoration:none;background:var(--surface);border:1.5px solid var(--border);border-radius:18px;padding:28px 22px;transition:all .15s;text-align:center;}
.dm-choice:hover{border-color:var(--blue-400);box-shadow:0 10px 30px rgba(13,50,120,.12);transform:translateY(-3px);}
.dm-choice-ic{width:64px;height:64px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:1.7rem;margin:0 auto 16px;}
.dm-choice h3{font-size:1.05rem;margin:0 0 8px;color:var(--text);}
.dm-choice p{font-size:.84rem;color:var(--text-muted);line-height:1.55;margin:0;}
.dm-link-hist{display:inline-flex;align-items:center;gap:6px;font-size:.84rem;font-weight:600;color:var(--blue-600);text-decoration:none;margin-top:22px;}
.dm-link-hist:hover{text-decoration:underline;}
.dm-link-hist-wrap{text-align:center;}
.dm-section-title{font-size:.95rem;font-weight:700;margin-bottom:12px;}
.dm-soldes{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-bottom:22px;max-width:360px;}
.dm-solde{border:1.5px solid var(--border);border-radius:12px;padding:12px 14px;background:var(--surface);}
.dm-solde-lbl{font-size:.75rem;color:var(--text-muted);font-weight:600;}
.dm-solde-val{font-size:1.35rem;font-weight:800;color:var(--blue-600);margin-top:4px;}
.dm-solde-val.muted{font-size:1rem;color:var(--text-muted);}
.dm-solde-val span{font-size:.75rem;color:var(--text-muted);}
.dm-solde-sub{font-size:.7rem;color:var(--text-muted);margin-top:2px;}
.dm-solde-repos{border-color:var(--blue-400);background:var(--blue-50);}
.dm-grid2{display:grid;grid-template-columns:1fr 1.4fr;gap:20px;align-items:start;}
.dm-form-tabs{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;}
.dm-form-tab{padding:8px 12px;border:1.5px solid var(--border);border-radius:10px;background:#fff;font-size:.78rem;font-weight:700;cursor:pointer;color:var(--text-muted);}
.dm-form-tab.active{border-color:var(--blue-400);background:var(--blue-50);color:var(--blue-800);}
.dm-form-pane{display:none;}.dm-form-pane.active{display:block;}
.dm-hint{font-size:.78rem;color:var(--text-muted);margin:10px 0 12px;}
.dm-card{border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:10px;}
.dm-card-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;}
.dm-card-title{font-weight:700;font-size:.9rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.dm-card-meta{font-size:.8rem;color:var(--text-muted);margin-top:4px;}
.dm-ref{font-size:.72rem;font-weight:600;color:var(--blue-600);background:var(--blue-50);padding:2px 8px;border-radius:6px;}
.dm-statut{display:inline-flex;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;white-space:nowrap;}
.dm-comment{font-size:.78rem;color:var(--text-muted);margin-top:8px;}
.dm-card-actions{margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;}
.dm-empty{font-size:.85rem;color:var(--text-muted);}
.dm-val-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px;}
.dm-val-form .form-control{flex:1;min-width:160px;margin:0;}
.dm-action-tag{display:inline-block;margin-top:6px;font-size:.72rem;font-weight:700;color:#1e40af;background:#dbeafe;padding:3px 10px;border-radius:6px;}

.dm-grid1{grid-template-columns:1fr !important;}
@media(max-width:880px){.dm-grid2{grid-template-columns:1fr !important;}}

.dm-agent-me{padding:8px 10px;background:var(--blue-50);border:1px solid var(--blue-200);border-radius:8px;margin-bottom:8px;font-size:.85rem;}
.dm-agent-me-note{color:var(--text-muted);font-size:.78rem;margin-left:4px;}
.dm-agent-picker{position:relative;}
.dm-agent-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;}
.dm-agent-chip{display:inline-flex;align-items:center;gap:6px;padding:4px 8px 4px 10px;background:var(--blue-50);border:1px solid var(--blue-200);border-radius:999px;font-size:.78rem;font-weight:600;color:var(--blue-900);}
.dm-agent-chip button{border:0;background:transparent;color:var(--text-muted);cursor:pointer;padding:0 2px;line-height:1;font-size:.85rem;}
.dm-agent-chip button:hover{color:var(--danger,#dc2626);}
.dm-agent-toggle{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;text-align:left;cursor:pointer;background:#fff;}
.dm-agent-toggle-label{color:var(--text-muted);font-weight:500;}
.dm-agent-toggle[aria-expanded="true"] .fa-chevron-down{transform:rotate(180deg);}
.dm-agent-toggle .fa-chevron-down{transition:transform .15s;color:var(--text-muted);font-size:.75rem;}
.dm-agent-dropdown{position:absolute;left:0;right:0;z-index:40;margin-top:4px;border:1px solid var(--border);border-radius:10px;background:var(--surface,#fff);box-shadow:0 10px 28px rgba(15,23,42,.12);overflow:hidden;}
.dm-agent-search{border:0;border-bottom:1px solid var(--border);border-radius:0;box-shadow:none;}
.dm-agent-search:focus{outline:none;box-shadow:none;border-color:var(--blue-400);}
.dm-agent-list{max-height:220px;overflow:auto;padding:4px 0;}
.dm-agent-item{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;margin:0;cursor:pointer;font-weight:400;border-bottom:1px solid var(--border);}
.dm-agent-item:last-child{border-bottom:0;}
.dm-agent-item:hover{background:var(--blue-50);}
.dm-agent-item input{margin-top:3px;flex-shrink:0;}
.dm-agent-item-text{display:flex;flex-direction:column;gap:2px;min-width:0;}
.dm-agent-name{font-size:.88rem;font-weight:600;color:var(--text);}
.dm-agent-fonc{font-size:.75rem;color:var(--text-muted);}
.dm-agent-empty{padding:12px;margin:0;font-size:.8rem;color:var(--text-muted);text-align:center;}
</style>
<?php
    }}

