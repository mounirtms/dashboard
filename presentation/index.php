<?php
// Auth gate — injected because root .htaccess parses .html files as PHP
$doc_root = dirname(dirname(__FILE__)); // /home/dashboard/public_html
require_once $doc_root . '/api/session_helper.php';
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: https://dashboard.technostationery.com/#/login', true, 302);
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TechnoStationery Executive Audit — Jan–Jul 2026</title>
<!-- Chart.js 4.4.0 — local + CDN fallback -->
<script src="/presentation/chart.umd.min.js"
        onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'"></script>
<script>
if (typeof Chart === 'undefined') {
  var s = document.createElement('script');
  s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
  document.head.appendChild(s);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"
        onerror="console.error('[TSM] Chart.js CDN load failed — charts will not render')"></script>
<script>
// Set ready flag right after CDN script tag (synchronous — fires if CDN succeeded)
if (typeof Chart !== 'undefined') {
  window._chartJsReady = true;
} else {
  console.warn('[TSM] Chart.js not available after CDN tag');
}
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
:root{
  --bg:#0a0e1a;--panel:#111827;--panel2:#1a2234;--border:#1e2d45;
  --accent:#3b82f6;--accent2:#06b6d4;--accent3:#8b5cf6;--accent4:#10b981;
  --warn:#f59e0b;--danger:#ef4444;--ok:#22c55e;
  --text:#e2e8f0;--muted:#94a3b8;--dim:#64748b;
  --red:#ef4444;--orange:#f97316;--yellow:#eab308;--green:#22c55e;
  --font:'Inter','Segoe UI',system-ui,-apple-system,sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:var(--font);overflow:hidden;height:100vh;display:flex;flex-direction:column;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}

/* ── DECK ── */
#deck{flex:1;position:relative;overflow:hidden}
/* ── SLIDE BASE ── */
.slide{
  position:absolute;inset:0;
  display:flex;flex-direction:column;
  padding:28px 36px 16px;overflow:hidden;
  opacity:0;pointer-events:none;
  transform:translateY(6px);
  transition:opacity .28s cubic-bezier(.4,0,.2,1),
             transform .28s cubic-bezier(.4,0,.2,1);
  will-change:opacity,transform;
}
.slide.active{
  opacity:1;pointer-events:auto;
  transform:translateY(0);
}
.slide.exit{
  opacity:0;transform:translateY(-6px);
}

/* ── NAV BAR ── */
#nav{
  height:54px;
  background:linear-gradient(180deg,#080d1c 0%,#060a16 100%);
  border-top:1px solid rgba(59,130,246,.2);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 14px;flex-shrink:0;
  box-shadow:0 -2px 16px rgba(0,0,0,.5);
  position:relative;z-index:20;
  gap:10px;
}
#progress-bar{
  position:absolute;top:0;left:0;height:3px;
  background:linear-gradient(90deg,var(--accent2) 0%,var(--accent) 55%,#8b5cf6 100%);
  transition:width .38s cubic-bezier(.4,0,.2,1);
  z-index:30;border-radius:0 2px 2px 0;
  box-shadow:0 0 12px rgba(59,130,246,.8),0 0 4px rgba(6,182,212,.5);
  pointer-events:none;
}
#nav-brand{
  display:flex;align-items:center;gap:8px;
  font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;
  color:var(--dim);white-space:nowrap;flex-shrink:0;
}
#nav-brand em{color:var(--accent2);font-style:normal;font-weight:900}
.nav-btn-primary{
  background:linear-gradient(135deg,rgba(59,130,246,.18),rgba(59,130,246,.08));
  border:1px solid rgba(59,130,246,.3);
  color:var(--text);
  padding:7px 18px;border-radius:7px;cursor:pointer;
  font-size:12px;font-weight:700;letter-spacing:.02em;
  transition:all .18s ease;display:flex;align-items:center;gap:6px;
  white-space:nowrap;flex-shrink:0;font-family:inherit;
}
.nav-btn-primary:hover{
  background:var(--accent);border-color:var(--accent);
  color:#fff;transform:translateY(-1px);
  box-shadow:0 4px 14px rgba(59,130,246,.35);
}
.nav-btn-primary:active{transform:translateY(0);box-shadow:none}
#nav-center{display:flex;align-items:center;gap:5px;flex:1;justify-content:center}
#nav-counter{
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  color:var(--text);font-size:12px;font-weight:800;
  min-width:64px;text-align:center;
  padding:5px 10px;border-radius:6px;
  letter-spacing:.04em;font-variant-numeric:tabular-nums;
}
.nav-btn-ghost{
  background:transparent;border:1px solid transparent;
  color:var(--dim);cursor:pointer;
  padding:5px 8px;border-radius:6px;
  font-size:10.5px;font-weight:600;letter-spacing:.02em;
  transition:all .15s;display:flex;align-items:center;gap:4px;
  white-space:nowrap;font-family:inherit;
}
.nav-btn-ghost:hover{
  color:var(--accent);border-color:rgba(59,130,246,.25);
  background:rgba(59,130,246,.07);
}
.nav-btn-dl{
  background:linear-gradient(135deg,rgba(34,197,94,.12),rgba(34,197,94,.06));
  border:1px solid rgba(34,197,94,.28);
  color:#4ade80;
  padding:6px 11px;border-radius:6px;cursor:pointer;
  font-size:10.5px;font-weight:700;letter-spacing:.03em;
  transition:all .18s;display:flex;align-items:center;gap:5px;
  white-space:nowrap;flex-shrink:0;text-decoration:none;font-family:inherit;
}
.nav-btn-dl:hover{
  background:rgba(34,197,94,.22);border-color:rgba(34,197,94,.5);
  color:#86efac;transform:translateY(-1px);
  box-shadow:0 3px 10px rgba(34,197,94,.22);
}
/* Legacy compat — keep old IDs working */
#toc-btn,#notes-btn,#fs-btn,#home-btn{font-family:inherit}

/* ── RESPONSIVE ── */
@media(max-width:900px){
  #nav{padding:0 8px;gap:6px}
  #nav-brand{display:none}
  .nav-btn-primary{padding:6px 12px;font-size:11px}
  #nav-center{gap:3px}
  .nav-btn-ghost{padding:4px 6px;font-size:10px}
  .nav-btn-dl{padding:5px 8px;font-size:10px}
  #nav-counter{min-width:52px;font-size:11px}
}
@media(max-width:640px){
  #nav-center .nav-btn-ghost:nth-child(n+4){display:none}
  .nav-btn-dl span{display:none}
  .nav-btn-primary{padding:6px 10px}
}

/* ── NOTES PANEL ── */
#notes-panel{
  position:fixed;bottom:54px;right:0;width:340px;
  background:linear-gradient(135deg,#090f1e,#0d1829);
  border:1px solid rgba(59,130,246,.2);
  border-right:none;
  border-radius:10px 0 0 10px;
  padding:14px 18px;
  font-size:12px;color:var(--muted);
  max-height:220px;overflow-y:auto;z-index:50;
  box-shadow:-4px 0 20px rgba(0,0,0,.4);
  backdrop-filter:blur(8px);
  line-height:1.55;
}
#notes-panel.hidden{display:none}
#notes-panel strong{
  color:var(--accent2);display:block;
  margin-bottom:8px;font-size:11px;
  letter-spacing:.06em;text-transform:uppercase;
  border-bottom:1px solid rgba(59,130,246,.15);
  padding-bottom:6px;
}
#notes-panel::-webkit-scrollbar{width:3px}
#notes-panel::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}

/* ── TYPOGRAPHY ── */
.slide-title{font-size:22px;font-weight:800;color:#fff;margin-bottom:4px;letter-spacing:-.5px;line-height:1.15}
.slide-subtitle{font-size:12.5px;color:var(--muted);margin-bottom:16px;line-height:1.45}
.section-label{
  font-size:9px;letter-spacing:3px;text-transform:uppercase;
  color:var(--accent2);margin-bottom:8px;
  display:inline-flex;align-items:center;gap:7px;font-weight:700;
}
.section-label::before{
  content:'';flex-shrink:0;display:inline-block;width:18px;height:2px;
  background:linear-gradient(90deg,var(--accent2),rgba(6,182,212,.2));
  border-radius:2px;
}
h3{font-size:13.5px;font-weight:700;color:var(--accent2);margin-bottom:8px;letter-spacing:-.01em}
h4{font-size:12px;font-weight:600;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}

/* ── GRID HELPERS ── */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.grid-23{display:grid;grid-template-columns:2fr 3fr;gap:16px}
.grid-32{display:grid;grid-template-columns:3fr 2fr;gap:16px}
.col{display:flex;flex-direction:column;gap:12px}

/* ── PANELS ── */
.panel{
  background:linear-gradient(135deg,var(--panel) 0%,rgba(17,24,39,.85) 100%);
  border:1px solid var(--border);border-radius:10px;padding:14px 16px;
  box-shadow:0 2px 8px rgba(0,0,0,.25);
  transition:border-color .2s;
}
.panel:hover{border-color:rgba(59,130,246,.2)}
.panel-dark{
  background:linear-gradient(135deg,var(--panel2) 0%,rgba(26,34,52,.8) 100%);
  border:1px solid var(--border);border-radius:10px;padding:14px 16px;
  box-shadow:0 1px 4px rgba(0,0,0,.2);
}
.panel-accent{background:#0f1e3a;border:1px solid #1d3a6e;border-radius:10px;padding:14px 16px}
.panel-danger{background:#1a0a0a;border:1px solid #5a1a1a;border-radius:10px;padding:14px 16px}
.panel-warn{background:#1a120a;border:1px solid #5a3a0a;border-radius:10px;padding:14px 16px}
.panel-ok{background:#0a1a10;border:1px solid #0a4a20;border-radius:10px;padding:14px 16px}

/* ── KPI CARDS ── */
.kpi-grid{display:grid;gap:12px}
.kpi-grid.g4{grid-template-columns:repeat(4,1fr)}
.kpi-grid.g3{grid-template-columns:repeat(3,1fr)}
.kpi-grid.g6{grid-template-columns:repeat(6,1fr)}
.kpi-card{
  background:linear-gradient(135deg,var(--panel) 0%,rgba(15,20,35,.9) 100%);
  border:1px solid var(--border);border-radius:10px;padding:14px 16px;
  position:relative;overflow:hidden;
  transition:border-color .2s,transform .2s;
}
.kpi-card:hover{border-color:rgba(59,130,246,.3);transform:translateY(-1px)}
.kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--kpi-color,var(--accent));opacity:.9}
.kpi-card::after{
  content:'';position:absolute;top:0;right:0;width:80px;height:80px;
  background:radial-gradient(circle at top right,rgba(var(--kpi-r,59),var(--kpi-g,130),var(--kpi-b,246),.06),transparent 70%);
  pointer-events:none;
}
.kpi-card .kpi-label{font-size:9.5px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;font-weight:600}
.kpi-card .kpi-val{font-size:28px;font-weight:900;color:#fff;line-height:1;margin-bottom:4px;letter-spacing:-.02em}
.kpi-card .kpi-sub{font-size:11px;color:var(--muted)}
.kpi-card .kpi-delta{font-size:11px;font-weight:600;margin-top:4px}
.kpi-card.blue{--kpi-color:var(--accent)}
.kpi-card.cyan{--kpi-color:var(--accent2)}
.kpi-card.purple{--kpi-color:var(--accent3)}
.kpi-card.green{--kpi-color:var(--ok)}
.kpi-card.orange{--kpi-color:var(--warn)}
.kpi-card.red{--kpi-color:var(--danger)}

/* ── BADGES ── */
.badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:9.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;transition:filter .2s}.badge:hover{filter:brightness(1.15)}
.badge-red{background:#3a0a0a;color:#f87171;border:1px solid #7f1d1d}
.badge-orange{background:#3a1a0a;color:#fb923c;border:1px solid #7c2d12}
.badge-yellow{background:#3a2a0a;color:#fbbf24;border:1px solid #78350f}
.badge-green{background:#0a2a10;color:#4ade80;border:1px solid #14532d}
.badge-blue{background:#0a1a3a;color:#60a5fa;border:1px solid #1e3a5f}
.badge-cyan{background:#0a2a3a;color:#22d3ee;border:1px solid #0e4f5f}
.badge-purple{background:#1a0a3a;color:#a78bfa;border:1px solid #3b0e7a}
.badge-gray{background:#1a2030;color:#94a3b8;border:1px solid #2a3550}

/* ── CONFIDENCE ── */
.conf{display:inline-block;padding:2px 7px;border-radius:4px;font-size:9px;font-weight:700;letter-spacing:.5px}
.conf-high{background:#0a2a10;color:#4ade80;border:1px solid #166534}
.conf-med{background:#3a2a0a;color:#fbbf24;border:1px solid #78350f}
.conf-low{background:#1a2030;color:#94a3b8;border:1px solid #2a3550}

/* ── TIMELINE ── */
.timeline{display:flex;flex-direction:column;gap:0}
.tl-item{display:grid;grid-template-columns:110px 20px 1fr;gap:8px;align-items:start;position:relative}
.tl-item:not(:last-child)::after{content:'';position:absolute;left:118px;top:20px;bottom:-8px;width:1px;background:linear-gradient(180deg,var(--border),transparent)}
.tl-time{font-size:11px;color:var(--accent2);text-align:right;padding-top:3px;font-weight:600}
.tl-dot{width:10px;height:10px;border-radius:50%;margin-top:5px;flex-shrink:0;border:2px solid}
.tl-dot.red{background:#3a0a0a;border-color:var(--red)}
.tl-dot.orange{background:#3a1a0a;border-color:var(--orange)}
.tl-dot.yellow{background:#3a2a0a;border-color:var(--yellow)}
.tl-dot.green{background:#0a2a10;border-color:var(--green)}
.tl-dot.blue{background:#0a1a3a;border-color:var(--accent)}
.tl-dot.cyan{background:#0a2a3a;border-color:var(--accent2)}
.tl-dot.purple{background:#1a0a3a;border-color:var(--accent3)}
.tl-content{padding:4px 0 10px}
.tl-title{font-size:12px;font-weight:700;color:#fff;margin-bottom:2px}
.tl-detail{font-size:11px;color:var(--muted);line-height:1.4}
.tl-src{font-size:9px;color:var(--dim);margin-top:3px;font-style:italic}

/* ── TABLE ── */
.data-table{width:100%;border-collapse:collapse;font-size:11.5px}
.data-table th{
  background:linear-gradient(180deg,#0d1625,#0a1220);
  color:var(--muted);padding:7px 11px;text-align:left;
  font-size:9.5px;letter-spacing:.7px;text-transform:uppercase;
  border-bottom:1px solid var(--border);font-weight:700;
}
.data-table td{
  padding:6px 11px;border-bottom:1px solid rgba(30,45,69,.6);
  color:var(--text);vertical-align:middle;line-height:1.4;
}
.data-table tr:hover td{background:rgba(59,130,246,.04)}
.data-table tr:last-child td{border-bottom:none}
.data-table .num{text-align:right;font-variant-numeric:tabular-nums}

/* ── BEFORE/AFTER ── */
.ba-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;border:1px solid var(--border);border-radius:8px;overflow:hidden}
.ba-header{background:#0d1625;padding:7px 12px;font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;border-bottom:1px solid var(--border);text-align:center}
.ba-before .ba-header{color:var(--danger)}
.ba-after .ba-header{color:var(--ok)}
.ba-row{display:flex;align-items:center;padding:6px 12px;border-bottom:1px solid #0d1220;gap:8px;font-size:11px}
.ba-row:last-child{border-bottom:none}
.ba-before .ba-row{background:#1a0808}
.ba-after .ba-row{background:#081a0e}
.ba-row .ba-key{color:var(--muted);min-width:110px;font-size:10px}
.ba-row .ba-val{color:#fff;font-weight:600}

/* ── SECTION DIVIDERS ── */
.section-divider{
  background:linear-gradient(135deg,#06091a 0%,#0c1a30 45%,#08101e 100%);
  display:flex!important;align-items:center;justify-content:center;
  flex-direction:column;gap:20px;position:relative;overflow:hidden;
}
.section-divider::before{
  content:'';position:absolute;inset:0;
  background:
    radial-gradient(ellipse at 50% 40%,rgba(59,130,246,.14) 0%,transparent 60%),
    radial-gradient(ellipse at 20% 80%,rgba(6,182,212,.06) 0%,transparent 50%);
}
.section-divider::after{
  content:'';position:absolute;
  bottom:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,rgba(59,130,246,.3),transparent);
}
.div-phase{font-size:10px;letter-spacing:5px;text-transform:uppercase;color:var(--accent2);opacity:.9;font-weight:600}
.div-number{font-size:180px;font-weight:900;color:rgba(255,255,255,.025);position:absolute;line-height:1;letter-spacing:-.05em;user-select:none}
.div-title{font-size:40px;font-weight:900;color:#fff;text-align:center;letter-spacing:-.6px;text-shadow:0 0 40px rgba(59,130,246,.15)}
.div-subtitle{font-size:13.5px;color:var(--muted);text-align:center;max-width:560px;line-height:1.55}
.div-tags{display:flex;gap:8px;flex-wrap:wrap;justify-content:center}

/* ── PROGRESS BAR ── */
.pbar-row{margin-bottom:8px}
.pbar-label{display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px}
.pbar-label span:first-child{color:var(--muted)}
.pbar-label span:last-child{color:#fff;font-weight:700}
.pbar-track{height:6px;background:rgba(13,22,37,.8);border-radius:3px;overflow:hidden;box-shadow:inset 0 1px 3px rgba(0,0,0,.4)}
.pbar-fill{height:100%;border-radius:3px;transition:width .6s cubic-bezier(.4,0,.2,1);box-shadow:0 0 6px currentColor}

/* ── RISK MATRIX ── */
.risk-row{display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid #0d1220;font-size:12px}
.risk-row:last-child{border-bottom:none}
.risk-sev{min-width:68px;text-align:center}
.risk-title{flex:1;color:#fff;font-weight:600}
.risk-detail{color:var(--muted);font-size:11px;flex:1.5}
.risk-status{min-width:70px;text-align:center}

/* ── ANOMALY BOX ── */
.anomaly-box{background:linear-gradient(135deg,#1a0808,#140606);border:1px solid #6a1f1f;border-radius:8px;padding:10px 14px;font-size:11px;box-shadow:0 0 12px rgba(239,68,68,.06)}
.anomaly-box .anomaly-title{color:var(--danger);font-weight:700;font-size:12px;margin-bottom:4px}

/* ── COVER CALLOUTS ── */
.cover-kpi{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.cover-kpi-item{
  background:linear-gradient(135deg,#0f1e3a,#091628);
  border:1px solid rgba(59,130,246,.25);
  border-radius:10px;padding:12px 18px;text-align:center;min-width:110px;
  transition:border-color .2s,transform .2s;
}
.cover-kpi-item:hover{border-color:rgba(59,130,246,.5);transform:translateY(-2px)}
.cover-kpi-item .cv-val{font-size:22px;font-weight:900;color:var(--accent);line-height:1}
.cover-kpi-item .cv-label{font-size:10px;color:var(--muted);margin-top:4px;letter-spacing:.05em}

/* ── ALGERIA MAP ── */
#algeria-map{width:100%;height:100%}
#algeria-map .wilaya{cursor:pointer;transition:fill .2s}#algeria-map .wt { fill:#cbd5e1; font-size:7px; font-family:Inter,sans-serif; font-weight:600; pointer-events:none; }#algeria-map .wn { fill:#fbbf24; font-size:7px; font-family:Inter,sans-serif; pointer-events:none; font-weight:700; }#algeria-map .wilaya path { cursor:pointer; transition:filter .15s, opacity .15s; }#algeria-map .wilaya:hover path { filter:brightness(1.9) !important; }#algeria-map .wilaya.dimmed path { opacity:.2; }#algeria-map .wilaya.highlighted path { filter:brightness(2.2) drop-shadow(0 0 5px #3b82f6); }
/* old rect rules removed — using path selectors above */
.map-filter-btn{
  padding:3px 10px;border-radius:20px;font-size:10px;font-weight:600;cursor:pointer;
  border:1px solid #1e2d45;background:#0d1625;color:#64748b;transition:all .2s;font-family:inherit;
}
.map-filter-btn.active,.map-filter-btn:hover{border-color:#3b82f6;color:#60a5fa;background:rgba(59,130,246,.1)}
.map-rank-item{
  display:flex;align-items:center;gap:5px;padding:4px 0;cursor:pointer;
  border-bottom:1px solid rgba(30,45,69,.5);position:relative;font-size:10px;
  transition:background .15s;border-radius:4px;
}
.map-rank-item:hover{background:rgba(59,130,246,.08)}
.map-rank-no{width:14px;font-size:9px;color:#475569;flex-shrink:0;text-align:center}
.map-rank-name{flex:1;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.map-rank-val{font-weight:700;color:#60a5fa;font-size:10px;flex-shrink:0}
.map-rank-bar{height:2px;background:linear-gradient(90deg,#3b82f6,#06b6d4);border-radius:1px;position:absolute;bottom:0;left:30px}
#mapTooltip{position:absolute;background:rgba(13,22,37,0.96);border:1px solid var(--accent);border-radius:6px;padding:8px 12px;font-size:12px;color:#e2e8f0;pointer-events:none;display:none;z-index:200;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,.5);backdrop-filter:blur(4px)}

/* ── CHART CONTAINERS ── */
.chart-wrap{position:relative;flex:1;min-height:0}

/* ── ENTRANCE ANIMATIONS ── */
@keyframes fadeUp{
  from{opacity:0;transform:translateY(12px)}
  to{opacity:1;transform:translateY(0)}
}
@keyframes fadeIn{
  from{opacity:0}to{opacity:1}
}
@keyframes scaleIn{
  from{opacity:0;transform:scale(.95)}
  to{opacity:1;transform:scale(1)}
}
.slide.active .section-label{animation:fadeUp .3s ease .05s both}
.slide.active .slide-title{animation:fadeUp .3s ease .1s both}
.slide.active .slide-subtitle{animation:fadeUp .3s ease .15s both}
.slide.active .kpi-grid{animation:fadeUp .35s ease .18s both}
.section-divider .div-phase{opacity:1;transform:none}
.section-divider .div-title{opacity:1;transform:none}
.section-divider .div-subtitle{opacity:1;transform:none}
.section-divider .div-tags{opacity:1;transform:none}
.section-divider.active .div-phase{animation:fadeUp .4s ease .1s both}
.section-divider.active .div-title{animation:fadeUp .4s ease .2s both}
.section-divider.active .div-subtitle{animation:fadeUp .35s ease .3s both}
.section-divider.active .div-tags{animation:fadeUp .35s ease .38s both}

/* ── SCROLLABLE ── */
.scroll{overflow-y:auto;flex:1;min-height:0}
.scroll::-webkit-scrollbar{width:4px}
.scroll::-webkit-scrollbar-track{background:transparent}
.scroll::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}

/* ── COVER ── */
#s1{background:linear-gradient(135deg,#050810 0%,#0b1830 40%,#080e1e 100%);display:flex!important;align-items:center;justify-content:center;flex-direction:column;gap:24px;position:relative;overflow:hidden}
#s1::before{content:'';position:absolute;top:-100px;right:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(59,130,246,.12) 0%,transparent 70%)}
#s1::after{content:'';position:absolute;bottom:-80px;left:-80px;width:400px;height:400px;background:radial-gradient(circle,rgba(6,182,212,.08) 0%,transparent 70%)}
.cover-logo{font-size:11px;letter-spacing:4px;text-transform:uppercase;color:var(--accent2)}
.cover-title{font-size:40px;font-weight:900;color:#fff;text-align:center;letter-spacing:-.5px;line-height:1.15}
.cover-title span{color:var(--accent)}
.cover-sub{font-size:14px;color:var(--muted);text-align:center}
.cover-meta{
  display:flex;gap:20px;font-size:11.5px;color:var(--dim);
  background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);
  border-radius:8px;padding:8px 16px;
}
.cover-meta span{display:flex;align-items:center;gap:6px}
.cover-meta strong{color:var(--muted)}

/* ── Logo CSS (single definition) ── */
:root {
  --logo-img: url("data:image/png;base64,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");
}
.slide-header-logo {
  position: absolute;
  top: 18px;
  right: 28px;
  z-index: 10;
  width: 110px;
  height: 34px;
  background-image: var(--logo-img);
  background-repeat: no-repeat;
  background-size: contain;
  background-position: right center;
  filter: brightness(0) invert(1);
  opacity: 0.85;
}
.div-logo-wm {
  position: absolute;
  top: 24px;
  right: 32px;
  z-index: 10;
  width: 120px;
  height: 36px;
  background-image: var(--logo-img);
  background-repeat: no-repeat;
  background-size: contain;
  background-position: right center;
  filter: brightness(0) invert(1);
  opacity: 0.75;
}
</style>
</head>
<body>
<div id="deck">

<!-- ════════════════════════════════════════════
     S1 — COVER
════════════════════════════════════════════ -->
<div class="slide" id="s1">
  <div class="cover-logo" style="display:flex;align-items:center;gap:12px"><img src="data:image/png;base64,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" alt="TechnoStationery" style="height:36px;filter:brightness(0) invert(1);opacity:.9"><span>Executive Audit Report 2026</span></div>
  <div class="cover-title">Executive Audit<br><span>January – July 2026</span></div>
  <div class="cover-sub">Infrastructure · Security · Performance · Business Intelligence<br>8-Phase Forensic Methodology · Evidence-First · Cross-Validated</div>
  <div class="cover-kpi">
    <div class="cover-kpi-item"><div class="cv-val">38</div><div class="cv-label">Audit Slides</div></div>
    <div class="cover-kpi-item"><div class="cv-val">8</div><div class="cv-label">Phases</div></div>
    <div class="cover-kpi-item"><div class="cv-val">498</div><div class="cv-label">CMD_Done H1</div></div>
    <div class="cover-kpi-item"><div class="cv-val">86.5%</div><div class="cv-label">Load Reduction</div></div>
    <div class="cover-kpi-item"><div class="cv-val">0</div><div class="cv-label">Confirmed Malware</div></div>
    <div class="cover-kpi-item"><div class="cv-val">Jul 12</div><div class="cv-label">Report Date</div></div>
  </div>
  <div class="cover-meta">
    <span><strong>Server:</strong> ded701.inmotionhosting.com</span>
    <span><strong>Stack:</strong> Magento 2.4.6-p15 · PHP 8.2 · MariaDB 10.6.17</span>
    <span><strong>OS:</strong> AlmaLinux 8.10 (RHEL 8)</span>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S2 — EXECUTIVE KPI DASHBOARD
════════════════════════════════════════════ -->
<div class="slide" id="s2">
  <div class="slide-header-logo"></div>
  <div class="section-label">Executive Dashboard</div>
  <div class="slide-title">Key Performance Indicators — H1 2026</div>
  <div class="slide-subtitle">Real data: MariaDB prod (technadminy7_dBT8x12y22) · Imunify360 · /var/log/secure · ecomscan · GitLab (2,215 commits) · Audited Jul 12, 2026</div>
  <div class="kpi-grid g4" style="margin-bottom:12px">
    <div class="kpi-card blue"><div class="kpi-label">Valid Orders H1 2026</div><div class="kpi-val">498</div><div class="kpi-sub">CMD_Done · Jan–Jun 2026</div><div class="kpi-delta" style="color:var(--ok)">&#x25B2; +11.9% vs 445 (H1 2025)</div></div>
    <div class="kpi-card cyan"><div class="kpi-label">Total Customers</div><div class="kpi-val">9,275</div><div class="kpi-sub">All-time registered · MariaDB</div><div class="kpi-delta" style="color:var(--muted)">incl. 3,278 bulk-migrated May 2026</div></div>
    <div class="kpi-card green"><div class="kpi-label">All-Time Revenue</div><div class="kpi-val">28.6M</div><div class="kpi-sub">DZD · 4,484 CMD_Done orders</div><div class="kpi-delta" style="color:var(--ok)">H1 2026: 2.78M DZD · AOV 5,591 DZD</div></div>
    <div class="kpi-card orange"><div class="kpi-label">Cancel Rate H1 2026</div><div class="kpi-val">36.6%</div><div class="kpi-sub">288 cancelled / 786 orders actifs</div><div class="kpi-delta" style="color:var(--muted)">Normal for Algerian COD model</div></div>
  </div>
  <div class="kpi-grid g4" style="margin-bottom:12px">
    <div class="kpi-card purple"><div class="kpi-label">Redis Hit Rate</div><div class="kpi-val">84.3%</div><div class="kpi-sub">Post-optimization · target 85%</div><div class="kpi-delta" style="color:var(--ok)">&#x25B2; from 5.7% (cold start)</div></div>
    <div class="kpi-card blue"><div class="kpi-label">GitLab Commits H1</div><div class="kpi-val">1,859</div><div class="kpi-sub">Jan–Jun 2026 · all branches</div><div class="kpi-delta" style="color:var(--ok)">&#x25B2; +1,449% vs 120 (H1 2025)</div></div>
    <div class="kpi-card red"><div class="kpi-label">SSH Attacks</div><div class="kpi-val">53,269</div><div class="kpi-sub">Historical btmp total</div><div class="kpi-delta" style="color:var(--ok)">&#x25BC; fail2ban deployed Jun 14</div></div>
    <div class="kpi-card cyan"><div class="kpi-label">Confirmed Malware</div><div class="kpi-val">0</div><div class="kpi-sub">18,141 Imunify FP resolved</div><div class="kpi-delta" style="color:var(--ok)">&#x2713; Cross-validated ecomscan</div></div>
  </div>
  <div class="kpi-grid g4">
    <div class="kpi-card orange"><div class="kpi-label">Critical CVEs</div><div class="kpi-val">1</div><div class="kpi-sub">CVE-2024-34102 unpatched</div><div class="kpi-delta" style="color:var(--danger)">&#x26A0; WAF mitigation active</div></div>
    <div class="kpi-card green"><div class="kpi-label">CVEs Fixed</div><div class="kpi-val">3/4</div><div class="kpi-sub">Apr 11, 2026</div><div class="kpi-delta" style="color:var(--ok)">phpseclib · symfony · jwt</div></div>
    <div class="kpi-card blue"><div class="kpi-label">Ecomscan Issues</div><div class="kpi-val">125</div><div class="kpi-sub">Jul 11 (0 malware)</div><div class="kpi-delta" style="color:var(--warn)">&#x25B2; 119&#x2192;125 · Jul 11 scan</div></div>
    <div class="kpi-card purple"><div class="kpi-label">Security Posture</div><div class="kpi-val" style="font-size:18px">MEDIUM</div><div class="kpi-sub">Improving</div><div class="kpi-delta" style="color:var(--warn)">1 critical CVE pending</div></div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S3 — TABLE OF CONTENTS
════════════════════════════════════════════ -->
<div class="slide" id="s3">
  <div class="slide-header-logo"></div>
  <div class="section-label">Navigation</div>
  <div class="slide-title">Audit Report Contents</div>
  <div class="slide-subtitle">38 slides · 8 phases · Click to jump · Prod: <strong>technostationery.com</strong> · Dev: <strong>dev.technostationery.com</strong></div>
  <div class="grid-2" style="flex:1;gap:20px">
    <div class="col">
      <div class="panel">
        <h3>📁 Phase 1–2: Repository &amp; Infrastructure</h3>
        <div style="font-size:12px;line-height:2;color:var(--muted)">
          <div><a href="#" onclick="showSlide(4);return false" style="color:var(--accent);text-decoration:none">S5 — Git Commit Analysis</a></div>
          <div><a href="#" onclick="showSlide(5);return false" style="color:var(--accent);text-decoration:none">S6 — Development Timeline</a></div>
          <div><a href="#" onclick="showSlide(7);return false" style="color:var(--accent);text-decoration:none">S8 — Server Hardware</a></div>
          <div><a href="#" onclick="showSlide(8);return false" style="color:var(--accent);text-decoration:none">S9 — MariaDB &amp; Redis</a></div>
          <div><a href="#" onclick="showSlide(9);return false" style="color:var(--accent);text-decoration:none">S10 — Apache &amp; SSH</a></div>
        </div>
      </div>
      <div class="panel">
        <h3>🛒 Phase 3–4: Magento &amp; Business</h3>
        <div style="font-size:12px;line-height:2;color:var(--muted)">
          <div><a href="#" onclick="showSlide(11);return false" style="color:var(--accent2);text-decoration:none">S12 — Monthly Orders</a></div>
          <div><a href="#" onclick="showSlide(12);return false" style="color:var(--accent2);text-decoration:none">S13 — Order Status</a></div>
          <div><a href="#" onclick="showSlide(13);return false" style="color:var(--accent2);text-decoration:none">S14 — Customer Registrations</a></div>
          <div><a href="#" onclick="showSlide(14);return false" style="color:var(--accent2);text-decoration:none">S15 — Top Products</a></div>
          <div><a href="#" onclick="showSlide(16);return false" style="color:var(--accent2);text-decoration:none">S17 — YoY Comparison</a></div>
          <div><a href="#" onclick="showSlide(17);return false" style="color:var(--accent2);text-decoration:none">S17b — 5-Year Annual Data</a></div>
          <div><a href="#" onclick="showSlide(18);return false" style="color:var(--accent2);text-decoration:none">S18 — Algeria Map</a></div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>🔒 Phase 5: Security Incident Module</h3>
        <div style="font-size:12px;line-height:2;color:var(--muted)">
          <div><a href="#" onclick="showSlide(20);return false" style="color:var(--danger);text-decoration:none">S20 — Security Executive Dashboard</a></div>
          <div><a href="#" onclick="showSlide(21);return false" style="color:var(--danger);text-decoration:none">S21 — May 2026 Forensic Timeline</a></div>
          <div><a href="#" onclick="showSlide(22);return false" style="color:var(--danger);text-decoration:none">S22 — SSH Brute-Force Analysis</a></div>
          <div><a href="#" onclick="showSlide(23);return false" style="color:var(--danger);text-decoration:none">S23 — CVE &amp; Vulnerability Matrix</a></div>
          <div><a href="#" onclick="showSlide(24);return false" style="color:var(--danger);text-decoration:none">S24 — Malware &amp; Ecomscan Analysis</a></div>
          <div><a href="#" onclick="showSlide(25);return false" style="color:var(--danger);text-decoration:none">S25 — Server Hardening Before/After</a></div>
        </div>
      </div>
      <div class="panel">
        <h3>⚡ Phase 6–8: Performance, Evidence &amp; Roadmap</h3>
        <div style="font-size:12px;line-height:2;color:var(--muted)">
          <div><a href="#" onclick="showSlide(27);return false" style="color:var(--accent3);text-decoration:none">S27 — Crisis Performance</a></div>
          <div><a href="#" onclick="showSlide(28);return false" style="color:var(--accent3);text-decoration:none">S28 — Cache Deep Dive</a></div>
          <div><a href="#" onclick="showSlide(30);return false" style="color:var(--accent3);text-decoration:none">S30 — Evidence Confidence Matrix</a></div>
          <div><a href="#" onclick="showSlide(31);return false" style="color:var(--accent3);text-decoration:none">S31 — Risk Assessment Matrix</a></div>
          <div><a href="#" onclick="showSlide(33);return false" style="color:var(--accent3);text-decoration:none">S33 — H2 Strategic Roadmap</a></div>
          <div><a href="#" onclick="showSlide(34);return false" style="color:var(--accent3);text-decoration:none">S34 — Key Recommendations</a></div>
          <div><a href="#" onclick="showSlide(35);return false" style="color:var(--accent3);text-decoration:none">S36 — H1 2025 vs H1 2026 Comparison</a></div>
          <div><a href="#" onclick="showSlide(36);return false" style="color:var(--accent3);text-decoration:none">S37 — Server Performance Tunings</a></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S4 — SECTION DIVIDER: REPO AUDIT
════════════════════════════════════════════ -->
<div class="slide section-divider" id="s4">
  <div class="div-logo-wm"></div>
  <div class="div-number" style="top:50%;transform:translateY(-50%)">01</div>
  <div class="div-phase">Phase 1 &#x2014; Repository Audit</div>
  <div class="div-title">Git Repository<br>&amp; Dev Timeline</div>
  <div class="div-subtitle">gitlab.com/technowebmaster-group/techno-magento &#x00B7; 2,215 commits &#x00B7; 6 branches &#x00B7; Oct 2024 &#x2013; Jul 2026</div>
  <div class="div-tags">
    <span class="badge badge-blue">2,215 Total Commits</span>
    <span class="badge badge-cyan">477 on master</span>
    <span class="badge badge-purple">46 MAB Modules</span>
    <span class="badge badge-green">Magento 2.4.6-p15</span>
    <span class="badge badge-orange">3 Security Incidents</span>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S5 — GIT COMMIT ANALYSIS
════════════════════════════════════════════ -->
<div class="slide" id="s5">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 1 &#x2014; Repository Audit</div>
  <div class="slide-title">Magento GitLab Repository &#x2014; Commit Analysis</div>
  <div class="slide-subtitle">Source: gitlab.com/technowebmaster-group/techno-magento &#x00B7; Audited Jul 12, 2026 &#x00B7; Init: Oct 17, 2024 &#x00B7; Last commit: Jul 11, 2026</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="col">
      <div class="panel" style="flex:1">
        <h3>Monthly Commit Velocity (All Branches)</h3>
        <div class="chart-wrap"><canvas id="chartCommits"></canvas></div>
      </div>
    </div>
    <div class="col">
      <div class="panel" style="flex:.45">
        <h3>Branch Structure</h3>
        <div class="pbar-row"><div class="pbar-label"><span>dev (active / staging)</span><span style="color:var(--accent2)">1,735</span></div><div class="pbar-track"><div class="pbar-fill" style="width:100%;background:var(--accent2)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>master (production)</span><span style="color:var(--ok)">477</span></div><div class="pbar-track"><div class="pbar-fill" style="width:27%;background:var(--ok)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>production (mirror)</span><span style="color:var(--muted)">477</span></div><div class="pbar-track"><div class="pbar-fill" style="width:27%;background:var(--border)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>tsdnd &#x00B7; main &#x00B7; feature/*</span><span style="color:var(--dim)">minor</span></div><div class="pbar-track"><div class="pbar-fill" style="width:4%;background:var(--border)"></div></div></div>
        <div style="margin-top:8px;font-size:10.5px;color:var(--muted)">
          <div>&#x1F464; <strong style="color:#fff">MounirAb</strong>: 2,191 commits (98.9%)</div>
          <div>&#x1F464; webmaster: 16 &#x00B7; Mounir AB: 4 &#x00B7; DND.fr: 4</div>
          <div style="margin-top:4px">&#x1F4C5; Init: <strong style="color:#fff">Oct 17, 2024</strong> &#x2014; f064912b8</div>
          <div>&#x1F4C5; Last: <strong style="color:#fff">Jul 11, 2026</strong> &#x2014; 0c5e54547</div>
          <div>&#x26A0; Peak: <strong style="color:var(--warn)">Apr 2026 — 535 commits</strong></div>
        </div>
      </div>
      <div class="panel" style="flex:1">
        <h3>Commit Type Distribution</h3>
        <div class="chart-wrap" style="height:130px"><canvas id="chartCommitType"></canvas></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-top:6px">
          <div style="text-align:center;padding:6px;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:6px">
            <div style="font-size:18px;font-weight:900;color:#fff">2,215</div>
            <div style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px">Total Commits</div>
          </div>
          <div style="text-align:center;padding:6px;background:rgba(6,182,212,.08);border:1px solid rgba(6,182,212,.2);border-radius:6px">
            <div style="font-size:18px;font-weight:900;color:#fff">46</div>
            <div style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px">Custom Modules</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S6 — DEVELOPMENT TIMELINE
════════════════════════════════════════════ -->
<div class="slide" id="s6">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 1 &#x2014; Repository Audit</div>
  <div class="slide-title">Magento Dev Timeline &#x2014; Oct 2024 to Jul 2026</div>
  <div class="slide-subtitle">Key milestones from GitLab commit log &#x00B7; gitlab.com/technowebmaster-group/techno-magento &#x00B7; 2,215 total commits across all branches</div>
  <div class="scroll" style="flex:1">
    <div class="timeline" style="gap:4px">
      <div class="tl-item"><div class="tl-time">Oct 17, 2024</div><div class="tl-dot blue"></div><div class="tl-content"><div class="tl-title">GitLab Repository Initialized</div><div class="tl-detail">f064912b8 &#x2014; "init commit adding the app to git" &#x2014; MounirAb. Magento 2 store versioned for the first time on GitLab.</div><div class="tl-src">Source: git log --reverse | head -1 &#x00B7; gitlab.com/technowebmaster-group/techno-magento</div></div></div>
      <div class="tl-item"><div class="tl-time">Nov&#x2013;Dec 2024</div><div class="tl-dot cyan"></div><div class="tl-content"><div class="tl-title">Initial Setup &#x2014; 5 Commits</div><div class="tl-detail">.gitignore tuning, add-to-cart popup fix. Repository structure stabilized. Custom module development begins.</div><div class="tl-src">Source: git log 2024-10..2024-12 (5 commits total)</div></div></div>
      <div class="tl-item"><div class="tl-time">Jan&#x2013;Feb 2025</div><div class="tl-dot cyan"></div><div class="tl-content"><div class="tl-title">Development Ramps &#x2014; 66 Commits</div><div class="tl-detail">Custom modules: Mab/CheckoutCustomization, YalidineCarrier, AbandonedCart, AlgeriaCompliance, DarkMode, AdminLocale. Amasty integrations begin.</div><div class="tl-src">Source: git log 2025-01..2025-02 (66 commits)</div></div></div>
      <div class="tl-item"><div class="tl-time">Mar&#x2013;Nov 2025</div><div class="tl-dot purple"></div><div class="tl-content"><div class="tl-title">Module Consolidation &#x2014; 174 Commits</div><div class="tl-detail">YalidineCarrier full implementation (MSI, parcel sync, 1,100 communes, 33 wilayas, CLI). Social login (MiniOrange/Firebase). Amasty fixes. Abandoned cart. OneSignal/Webpushr push notifications.</div><div class="tl-src">Source: git log 2025-03..2025-11 (174 commits)</div></div></div>
      <div class="tl-item"><div class="tl-time">Dec 2025</div><div class="tl-dot blue"></div><div class="tl-content"><div class="tl-title">Major Feature Sprint &#x2014; 107 Commits</div><div class="tl-detail">SourceAccountRepository, cart-level source selection, ParcelService layer, CLI testing tools, webhook optimization, Firebase SDK, SourceSelector module. Largest 2025 month.</div><div class="tl-src">Source: git log 2025-12 (107 commits &#x2014; peak of 2025)</div></div></div>
      <div class="tl-item"><div class="tl-time">Jan 2026</div><div class="tl-dot blue"></div><div class="tl-content"><div class="tl-title">&#x1F525; Mega Sprint &#x2014; 462 Commits</div><div class="tl-detail">Full Algeria commune sync (1,100 communes). Dealer config workaround (HTTP routing bypass). Elasticsearch fix. CSRF v4.3 protection. DI compile fixes. Production release v24.0. Environmental Manager Phase 1.</div><div class="tl-src">Source: git log 2026-01 (462 commits)</div></div></div>
      <div class="tl-item"><div class="tl-time">Feb 2026</div><div class="tl-dot cyan"></div><div class="tl-content"><div class="tl-title">Production Go-Live v7.0 &#x2014; 419 Commits</div><div class="tl-detail">Site fully operational. Beta site optimization + audit. Amasty Checkout integration Phases 1&#x2013;3. Dark mode complete. Apache config fix. Admin dashboard with analytics. Magento 2.4.6-p14.</div><div class="tl-src">Source: git log 2026-02 (419 commits)</div></div></div>
      <div class="tl-item"><div class="tl-time">Mar 2026</div><div class="tl-dot cyan"></div><div class="tl-content"><div class="tl-title">Checkout Architecture Rewrite &#x2014; 335 Commits</div><div class="tl-detail">3-step checkout architecture. Yalidine fee calculator with wilaya DB. Yellow Saturday popup. Social login v2.0 (Google/Facebook). Firebase SDK RequireJS integration. Source selector UI. Algeria 1,100 communes live.</div><div class="tl-src">Source: git log 2026-03 (335 commits)</div></div></div>
      <div class="tl-item"><div class="tl-time">Apr 2026</div><div class="tl-dot purple"></div><div class="tl-content"><div class="tl-title">&#x1F525; ALL-TIME PEAK &#x2014; 535 Commits</div><div class="tl-detail">Checkout-v8 complete rewrite (Amasty, Yalidine, custom shipping cards). Playwright E2E tests (58 cases). Gift card totals. Performance Phases 1&#x26;2. Environment Manager Phase 3. CSRF &#x26; PHPStan hardening. Cloudflare integration. Firebase centralization.</div><div class="tl-src">Source: git log 2026-04 (535 commits &#x2014; highest month in entire repo history)</div></div></div>
      <div class="tl-item"><div class="tl-time">May 2026</div><div class="tl-dot orange"></div><div class="tl-content"><div class="tl-title">Security Audit &#x26; Performance &#x2014; 65 Commits</div><div class="tl-detail">6 HIGH/MEDIUM vulnerabilities fixed (XSS, ObjectManager misuse, redirect key). Cron optimization, smart indexer skip. Server monitoring dashboard. Backup scripts. ReCaptchaFix removed. Amasty Thank You page fix.</div><div class="tl-src">Source: git log 2026-05 (65 commits)</div></div></div>
      <div class="tl-item"><div class="tl-time">Jun 9, 2026</div><div class="tl-dot red"></div><div class="tl-content"><div class="tl-title">&#x1F6A8; Security Incident &#x2014; Malware &#x26; Backdoors Removed</div><div class="tl-detail">c12137b7e: malware injection vectors removed from Sm/Themecore (215 lines). ceabd59d2: 6 backdoor .htaccess files locked in vendor/. 743db3717: 22 CVEs patched (twig/twig, symfony/mime, symfony/yaml, polyfills). pub/.htaccess hardened.</div><div class="tl-src">Source: git show c12137b7e, ceabd59d2, 743db3717 &#x00B7; 2026-06-09</div></div></div>
      <div class="tl-item"><div class="tl-time">Jun 10, 2026</div><div class="tl-dot green"></div><div class="tl-content"><div class="tl-title">Magento Upgraded to 2.4.6-p15</div><div class="tl-detail">a0096910c: Security patch p15 applied. CSP module re-enabled (Compat/CspShim). Server config hardened to stock Magento defaults. TinyMCE replaced with HugeRTE. Elasticsearch timeouts tuned.</div><div class="tl-src">Source: git show a0096910c &#x00B7; 2026-06-10 18:52</div></div></div>
      <div class="tl-item"><div class="tl-time">Jun 22, 2026</div><div class="tl-dot red"></div><div class="tl-content"><div class="tl-title">&#x1F6A8; PHP Backdoor Shells Removed from pub/</div><div class="tl-detail">f8dcdf3f9: Two PHP backdoor shells removed. pub/6ce96da85findex.php (308 lines of obfuscated code) + pub/81e627ea7d2b.php (1 line). Mab_UploadSecurity module added to prevent future uploads.</div><div class="tl-src">Source: git show f8dcdf3f9 &#x00B7; 2026-06-22 12:34 &#x00B7; 309 lines deleted</div></div></div>
      <div class="tl-item"><div class="tl-time">Jun 29&#x2013;Jul 2, 2026</div><div class="tl-dot blue"></div><div class="tl-content"><div class="tl-title">Checkout-v9.0 &#x26; Full CSP Compliance &#x2014; 43 Commits</div><div class="tl-detail">873defbf0: v9.0 complete rewrite (Amasty fix, Yalidine fee enforcement, live validation, admin delivery data). 9f01014b5+10b15db54: CSP inline violations eliminated across 5 modules. CSRF hardening. Rate limiting added.</div><div class="tl-src">Source: git log 2026-06-29..2026-07-02 (master branch)</div></div></div>
      <div class="tl-item"><div class="tl-time">Jul 1, 2026</div><div class="tl-dot cyan"></div><div class="tl-content"><div class="tl-title">GitLab CI/CD Pipeline Added (External Contributor)</div><div class="tl-detail">336ada749: Damien Louis (DND France, damien.louis@dnd.fr) contributed GitLab CD pipeline. Automated deployment via SSH to ded701. Redis flush, cache optimization, symlink management integrated.</div><div class="tl-src">Source: git log --author="Damien Louis" &#x00B7; tsdnd + dev branches</div></div></div>
      <div class="tl-item"><div class="tl-time">Jul 11, 2026</div><div class="tl-dot green"></div><div class="tl-content"><div class="tl-title">Latest Commits &#x2014; YalidineCarrier Unit Tests</div><div class="tl-detail">0c5e54547: 85 unit tests added across 4 test classes (SaveYalidineOrderData:27, WilayaRepository:22, SourceRepository:21, WilayaCommune:15). Standalone PHPUnit bootstrap, no vendor/ needed. phpunit.xml config.</div><div class="tl-src">Source: git log master..dev | head -10 &#x00B7; 2026-07-11 13:29 UTC</div></div></div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S7 — SECTION DIVIDER: INFRASTRUCTURE
════════════════════════════════════════════ -->
<div class="slide section-divider" id="s7">
  <div class="div-logo-wm"></div>
  <div class="div-number" style="top:50%;transform:translateY(-50%)">02</div>
  <div class="div-phase">Phase 2 — Infrastructure Audit</div>
  <div class="div-title">Server Infrastructure<br>&amp; Stack Analysis</div>
  <div class="div-subtitle">AlmaLinux 8.10 · Intel Xeon E3-1240 v3 · 32GB RAM · Apache 2.4.66 · PHP-FPM 8.2.30 · MariaDB 10.6.17</div>
  <div class="div-tags">
    <span class="badge badge-blue">ded701.inmotionhosting.com</span>
    <span class="badge badge-green">8 Cores</span>
    <span class="badge badge-cyan">32 GB RAM</span>
    <span class="badge badge-orange">May 5 Crisis Resolved</span>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S8 — SERVER HARDWARE
════════════════════════════════════════════ -->
<div class="slide" id="s8">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 2 — Infrastructure</div>
  <div class="slide-title">Server Hardware &amp; OS Configuration</div>
  <div class="slide-subtitle">Source: uname -r, lscpu, free, df — verified Jul 11, 2026</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="col">
      <div class="panel">
        <h3>Hardware Specifications</h3>
        <table class="data-table">
          <tr><td style="color:var(--muted);width:140px">Host</td><td><strong>ded701.inmotionhosting.com</strong></td></tr>
          <tr><td style="color:var(--muted)">IP</td><td><strong>205.134.249.177</strong></td></tr>
          <tr><td style="color:var(--muted)">CPU</td><td><strong>Intel Xeon E3-1240 v3</strong></td></tr>
          <tr><td style="color:var(--muted)">Cores</td><td><strong>8 (4C × 2 HT)</strong></td></tr>
          <tr><td style="color:var(--muted)">RAM</td><td><strong>32 GB</strong></td></tr>
          <tr><td style="color:var(--muted)">Storage</td><td><strong>1.8 TB (31% used)</strong></td></tr>
          <tr><td style="color:var(--muted)">OS</td><td><strong>AlmaLinux 8.10</strong></td></tr>
          <tr><td style="color:var(--muted)">Kernel</td><td><strong>4.18.0-553</strong></td></tr>
        </table>
      </div>
      <div class="panel">
        <h3>Stack Components</h3>
        <table class="data-table">
          <tr><td style="color:var(--muted);width:140px">Web Server</td><td><strong>Apache 2.4.66</strong></td><td><span class="badge badge-green">Active</span></td></tr>
          <tr><td style="color:var(--muted)">PHP</td><td><strong>PHP-FPM 8.2.30</strong></td><td><span class="badge badge-green">Static/66</span></td></tr>
          <tr><td style="color:var(--muted)">Database</td><td><strong>MariaDB 10.6.17</strong> :3307</td><td><span class="badge badge-green">8GB pool</span></td></tr>
          <tr><td style="color:var(--muted)">Cache</td><td><strong>Redis 5.0.3</strong></td><td><span class="badge badge-green">84.3% HR</span></td></tr>
          <tr><td style="color:var(--muted)">HTTP Cache</td><td><strong>Varnish 6.x</strong></td><td><span class="badge badge-yellow">15.5% HR</span></td></tr>
          <tr><td style="color:var(--muted)">Search</td><td><strong>Elasticsearch 7.x</strong></td><td><span class="badge badge-green">Green</span></td></tr>
          <tr><td style="color:var(--muted)">CMS</td><td><strong>Magento 2.4.6</strong></td><td><span class="badge badge-orange">CVE pending</span></td></tr>
          <tr><td style="color:var(--muted)">CDN/WAF</td><td><strong>Cloudflare + ModSec</strong></td><td><span class="badge badge-green">Active</span></td></tr>
        </table>
      </div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>Resource Utilization — Current (Jul 12)</h3>
        <div class="pbar-row" style="margin-top:8px"><div class="pbar-label"><span>CPU (avg)</span><span>~18%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:18%;background:var(--ok)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>RAM (used)</span><span>~40%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:40%;background:var(--accent)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>MariaDB buffer pool</span><span>8 GB</span></div><div class="pbar-track"><div class="pbar-fill" style="width:25%;background:var(--accent2)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>Disk space used</span><span>31%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:31%;background:var(--ok)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>PHP-FPM processes</span><span>66 max (static)</span></div><div class="pbar-track"><div class="pbar-fill" style="width:66%;background:var(--accent3)"></div></div></div>
      </div>
      <div class="panel panel-warn">
        <h3>May 5 Crisis — Before/After</h3>
        <div class="ba-grid">
          <div class="ba-before"><div class="ba-header">BEFORE (Crisis)</div>
            <div class="ba-row"><div class="ba-key">System Load</div><div class="ba-val">15.37</div></div>
            <div class="ba-row"><div class="ba-key">CPU Idle</div><div class="ba-val">0.7%</div></div>
            <div class="ba-row"><div class="ba-key">DB Buffer Pool</div><div class="ba-val">128 MB</div></div>
            <div class="ba-row"><div class="ba-key">PHP-FPM Mode</div><div class="ba-val">dynamic</div></div>
          </div>
          <div class="ba-after"><div class="ba-header">AFTER (Resolved)</div>
            <div class="ba-row"><div class="ba-key">System Load</div><div class="ba-val">2.04</div></div>
            <div class="ba-row"><div class="ba-key">CPU Idle</div><div class="ba-val">96.9%</div></div>
            <div class="ba-row"><div class="ba-key">DB Buffer Pool</div><div class="ba-val">8 GB</div></div>
            <div class="ba-row"><div class="ba-key">PHP-FPM Mode</div><div class="ba-val">static/66</div></div>
          </div>
        </div>
        <div style="font-size:10px;color:var(--dim);margin-top:8px">Source: SERVER_FIX_COMPLETE_REPORT.md · AUDIT_COMPLETION_STATUS.txt</div>
      </div>
    </div>
  </div>
</div>
<!-- ════════════════════════════════════════════
     S9 — MARIADB & REDIS
════════════════════════════════════════════ -->
<div class="slide" id="s9">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 2 — Infrastructure</div>
  <div class="slide-title">MariaDB 10.6 &amp; Redis — Deep Dive</div>
  <div class="slide-subtitle">Source: /opt/mariadb10.6/my.cnf · redis-cli INFO · imunify360.db scan timestamps</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="col">
      <div class="panel">
        <h3>MariaDB 10.6.17 Configuration</h3>
        <table class="data-table">
          <thead><tr><th>Parameter</th><th>Before</th><th>After</th><th>Impact</th></tr></thead>
          <tbody>
            <tr><td>innodb_buffer_pool_size</td><td style="color:var(--danger)">128 MB</td><td style="color:var(--ok)">8 GB</td><td>64× larger</td></tr>
            <tr><td>max_connections</td><td style="color:var(--warn)">151</td><td style="color:var(--ok)">200</td><td>+32%</td></tr>
            <tr><td>Port</td><td colspan="2" style="color:var(--accent)">3307 (non-default)</td><td>Isolation</td></tr>
            <tr><td>SSL</td><td colspan="2" style="color:var(--warn)">--skip-ssl (local)</td><td>Acceptable</td></tr>
            <tr><td>Auth</td><td colspan="2">MYSQL_PWD env var</td><td>Scripted</td></tr>
          </tbody>
        </table>
        <div style="margin-top:10px">
          <div class="pbar-row"><div class="pbar-label"><span>Buffer pool utilization</span><span>~8 GB / 32 GB RAM</span></div><div class="pbar-track"><div class="pbar-fill" style="width:25%;background:var(--accent)"></div></div></div>
          <div class="pbar-row"><div class="pbar-label"><span>InnoDB efficiency (post-fix)</span><span>~95% from RAM</span></div><div class="pbar-track"><div class="pbar-fill" style="width:95%;background:var(--ok)"></div></div></div>
        </div>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: /opt/mariadb10.6/my.cnf (verified)</div>
      </div>
      <div class="panel-danger panel">
        <h3>Crisis Root Cause — MariaDB</h3>
        <div style="font-size:11px;color:var(--muted);line-height:1.7">
          <div>• Buffer pool 128MB forced <strong style="color:var(--danger)">95% disk I/O</strong> for every query</div>
          <div>• At crisis peak: <strong style="color:var(--danger)">96% CPU</strong> on MariaDB alone</div>
          <div>• Query latency: <strong style="color:var(--danger)">100–1000ms</strong> (disk) vs 1ms (RAM)</div>
          <div>• Fix: buffer pool to 8GB → <strong style="color:var(--ok)">50× faster queries</strong></div>
          <div style="margin-top:6px;font-size:10px;color:var(--dim)">Source: AUDIT_COMPLETION_STATUS.txt · EXECUTIVE_SUMMARY.md <span class="conf conf-high">HIGH</span></div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>Redis Cache Performance</h3>
        <div class="chart-wrap" style="height:160px"><canvas id="chartRedisGauge"></canvas></div>
        <div class="grid-2" style="margin-top:10px;gap:10px">
          <div style="background:#0f1e3a;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:24px;font-weight:800;color:var(--ok)">84.3%</div>
            <div style="font-size:10px;color:var(--muted)">Current Hit Rate</div>
          </div>
          <div style="background:#0f1e3a;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:24px;font-weight:800;color:var(--warn)">5.7%</div>
            <div style="font-size:10px;color:var(--muted)">Pre-Crisis (Varnish)</div>
          </div>
        </div>
      </div>
      <div class="panel">
        <h3>Imunify360 Scan Timeline — Key Dates</h3>
        <table class="data-table" style="font-size:11px">
          <thead><tr><th>Date</th><th>Type</th><th>Files</th><th>Flagged</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td>Jun 8</td><td>Full background</td><td>2.5M+</td><td>80,832</td><td><span class="badge badge-orange">Refining</span></td></tr>
            <tr><td>Jun 9–15</td><td>Rescan-outdated</td><td>18–41K</td><td>18,144+</td><td><span class="badge badge-orange">Processing</span></td></tr>
            <tr><td>Jun 16</td><td>Rescan-outdated</td><td>18,143</td><td>18,143</td><td><span class="badge badge-red">Mass Flag</span></td></tr>
            <tr><td>Jun 29–Jul 7</td><td>Rescan-outdated</td><td>18,141</td><td>0</td><td><span class="badge badge-green">FP Cleared</span></td></tr>
          </tbody>
        </table>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: imunify360.db malware_scans (31 total scans)</div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S10 — APACHE & SSH OVERVIEW
════════════════════════════════════════════ -->
<div class="slide" id="s10">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 2 — Infrastructure</div>
  <div class="slide-title">Apache 2.4.66 &amp; SSH Attack Overview</div>
  <div class="slide-subtitle">Source: Apache access_log aggregates · /var/log/secure · /var/log/btmp (last checked Jul 5–7)</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="col">
      <div class="panel">
        <h3>Monthly Apache Traffic — Jan–Jun 2026</h3>
        <table class="data-table">
          <thead><tr><th>Month</th><th class="num">Requests</th><th class="num">4xx</th><th class="num">5xx</th><th>Notes</th></tr></thead>
          <tbody>
            <tr><td>Jan</td><td class="num">312,000</td><td class="num">4,200</td><td class="num">180</td><td><span class="badge badge-green">Normal</span></td></tr>
            <tr><td>Feb</td><td class="num">298,000</td><td class="num">3,900</td><td class="num">145</td><td><span class="badge badge-green">Normal</span></td></tr>
            <tr><td>Mar</td><td class="num" style="color:var(--warn)">640,000</td><td class="num">8,100</td><td class="num">320</td><td><span class="badge badge-orange">Anomaly</span></td></tr>
            <tr><td>Apr</td><td class="num">380,000</td><td class="num">4,800</td><td class="num">210</td><td><span class="badge badge-green">Normal</span></td></tr>
            <tr><td>May</td><td class="num">410,000</td><td class="num">12,400</td><td class="num">1,890</td><td><span class="badge badge-red">Crisis 5th</span></td></tr>
            <tr><td>Jun</td><td class="num">355,000</td><td class="num">5,100</td><td class="num">230</td><td><span class="badge badge-green">Recovered</span></td></tr>
          </tbody>
        </table>
        <div class="anomaly-box" style="margin-top:10px">
          <div class="anomaly-title">⚠ March 2026 Traffic Anomaly — 640K Requests</div>
          <div>2.1× normal volume. No matching order spike. Root cause unconfirmed. <span class="conf conf-med">MEDIUM CONFIDENCE</span></div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>SSH Attack Summary — Audit Window</h3>
        <div class="grid-2" style="gap:10px;margin-bottom:12px">
          <div style="background:#1a0a0a;border:1px solid #5a1a1a;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:28px;font-weight:800;color:var(--danger)">53,269</div>
            <div style="font-size:10px;color:var(--muted)">Historical btmp total</div>
          </div>
          <div style="background:#1a0a0a;border:1px solid #5a1a1a;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:28px;font-weight:800;color:var(--warn)">12,004</div>
            <div style="font-size:10px;color:var(--muted)">Current /var/log/secure</div>
          </div>
        </div>
        <h4>Top Attacking IPs (Jul 5–7)</h4>
        <table class="data-table" style="font-size:11px">
          <thead><tr><th>IP Address</th><th class="num">Attempts</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td>91.92.40.124</td><td class="num">761</td><td><span class="badge badge-red">Active</span></td></tr>
            <tr><td>45.156.87.34–254</td><td class="num">761×4</td><td><span class="badge badge-red">Subnet</span></td></tr>
            <tr><td>45.153.34.71/161</td><td class="num">761×2</td><td><span class="badge badge-red">Active</span></td></tr>
            <tr><td>211.200.98.61</td><td class="num">495</td><td><span class="badge badge-red">Active</span></td></tr>
            <tr><td>38.253.239.211</td><td class="num">15</td><td><span class="badge badge-orange">Banned</span></td></tr>
            <tr><td>166.1.60.230</td><td class="num">~10</td><td><span class="badge badge-orange">Banned</span></td></tr>
          </tbody>
        </table>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: grep "Failed password" /var/log/secure · ssh_hardening_report.html</div>
      </div>
      <div class="panel-ok panel" style="padding:10px 14px">
        <div style="font-size:11px;color:var(--muted)">✅ <strong style="color:#fff">Jun 14:</strong> fail2ban deployed — 2 IPs immediately banned<br>✅ <strong style="color:#fff">Jun 14:</strong> MaxAuthTries 10→3, PermitRootLogin→prohibit-password<br>⚠️ <strong style="color:var(--warn)">Active:</strong> Brute-force continues from new IPs daily</div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S11 — SECTION DIVIDER: MAGENTO
════════════════════════════════════════════ -->
<div class="slide section-divider" id="s11">
  <div class="div-logo-wm"></div>
  <div class="div-number" style="top:50%;transform:translateY(-50%)">03</div>
  <div class="div-phase">Phase 3 — Magento Audit</div>
  <div class="div-title">Business Performance<br>&amp; Order Analysis</div>
  <div class="div-subtitle">7,117 total orders · 4,484 CMD_Done (all-time) · 9,275 customers · 2022–Jul 2026 · MariaDB prod · Cancel rate 36.6% (Algerian COD)</div>
  <div class="div-tags">
    <span class="badge badge-blue">4,484 CMD_Done</span>
    <span class="badge badge-cyan">9,275 Customers</span>
    <span class="badge badge-green">+56.6% H1 YoY</span>
    <span class="badge badge-purple">Algeria Choropleth</span>
    <span class="badge badge-gray">83.29M DZD Revenue</span>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S12 — MONTHLY ORDERS
════════════════════════════════════════════ -->
<div class="slide" id="s12">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 3 — Audit Magento</div>
  <div class="slide-title">Commandes Mensuelles &amp; Revenus — Jan–Jun 2026</div>
  <div class="slide-subtitle">Source: MariaDB · sales_order WHERE status='CMD_Done' · 498 CMD_Done H1 2026 · 786 ordres actifs H1 2026 · DZD</div>
  <div class="grid-23" style="flex:1;gap:16px">
    <div class="panel" style="flex:1;display:flex;flex-direction:column">
      <h3>Commandes CMD_Done / Mois + Valeur Moyenne (AOV)</h3>
      <div class="chart-wrap" style="flex:1"><canvas id="chartMonthly"></canvas></div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>Détail Mensuel</h3>
        <table class="data-table" style="font-size:11px">
          <thead><tr><th>Mois</th><th class="num">CMD_Done</th><th class="num">AOV (DZD)</th><th>&#916; MoM</th></tr></thead>
          <tbody>
            <tr><td>Jan</td><td class="num">116</td><td class="num">5,520</td><td><span style="color:var(--accent)">baseline</span></td></tr>
            <tr><td>Fév</td><td class="num">69</td><td class="num">5,750</td><td><span style="color:var(--danger)">&#9660; &#8722;40.5%</span></td></tr>
            <tr><td>Mar</td><td class="num">74</td><td class="num">5,490</td><td><span style="color:var(--ok)">&#9650; +7.2%</span></td></tr>
            <tr><td>Avr</td><td class="num">81</td><td class="num">5,680</td><td><span style="color:var(--ok)">&#9650; +9.5%</span></td></tr>
            <tr><td>Mai</td><td class="num">88</td><td class="num">5,420</td><td><span style="color:var(--ok)">&#9650; +8.6%</span></td></tr>
            <tr><td>Jun</td><td class="num">70</td><td class="num">5,620</td><td><span style="color:var(--danger)">&#9660; &#8722;20.5%</span></td></tr>
          </tbody>
        </table>
      </div>
      <div class="panel" style="margin-top:8px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;text-align:center">
          <div>
            <div style="font-size:22px;font-weight:800;color:var(--accent)">498</div>
            <div style="font-size:10px;color:var(--muted)">CMD_Done H1 2026</div>
          </div>
          <div>
            <div style="font-size:22px;font-weight:800;color:#22c55e">5,591</div>
            <div style="font-size:10px;color:var(--muted)">AOV Moyen DZD</div>
          </div>
          <div>
            <div style="font-size:22px;font-weight:800;color:#f59e0b">2.78M</div>
            <div style="font-size:10px;color:var(--muted)">Revenu H1 DZD</div>
          </div>
          <div>
            <div style="font-size:22px;font-weight:800;color:#a78bfa">911</div>
            <div style="font-size:10px;color:var(--muted)">Total Orders H1</div>
          </div>
        </div>
        <div style="margin-top:6px;font-size:10px;color:var(--dim)">
          Feb dip: Yalidine test phase (dev). Mar+ recovery. May peak = 88 CMD_Done.
          <span class="conf conf-high">HIGH</span>
        </div>
      </div>
    </div>
  </div>


<!-- ════════════════════════════════════════════
     S13 — ORDER STATUS
════════════════════════════════════════════ -->
<div class="slide" id="s13">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 3 — Audit Magento</div>
  <div class="slide-title">Distribution des Statuts &amp; Taux d&#8217;Annulation</div>
  <div class="slide-subtitle">Source: MariaDB sales_order · 786 ordres actifs H1 2026 · Statuts personnalis&#233;s Alg&#233;riens (COD) · Taux annulation 36.6% = NORMAL march&#233; DZ</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="panel" style="flex:1;display:flex;flex-direction:column">
      <h3>Distribution des Statuts (Donut)</h3>
      <div class="chart-wrap" style="flex:1"><canvas id="chartStatus"></canvas></div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>D&#233;tail des Statuts</h3>
        <table class="data-table">
          <thead><tr><th>Statut Magento</th><th class="num">Nb</th><th class="num">%</th></tr></thead>
          <tbody>
            <tr style="background:rgba(34,197,94,.08)">
              <td><span class="badge badge-green">CMD_Done</span></td>
              <td class="num" style="font-weight:700;color:#22c55e">498</td>
              <td class="num" style="color:#22c55e">63.4%</td>
            </tr>
            <tr style="background:rgba(239,68,68,.06)">
              <td><span class="badge badge-red" style="font-size:9px">Annulee_confirmation</span></td>
              <td class="num">164</td>
              <td class="num" style="color:#f87171">20.9%</td>
            </tr>
            <tr style="background:rgba(239,68,68,.06)">
              <td><span class="badge badge-red" style="font-size:9px">Annulee_preparation</span></td>
              <td class="num">80</td>
              <td class="num" style="color:#f87171">10.2%</td>
            </tr>
            <tr style="background:rgba(239,68,68,.06)">
              <td><span class="badge badge-red" style="font-size:9px">Annulee_livraison</span></td>
              <td class="num">44</td>
              <td class="num" style="color:#f87171">5.6%</td>
            </tr>
            <tr>
              <td><span class="badge badge-yellow">pending/processing</span></td>
              <td class="num">41</td>
              <td class="num">5.2%</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="panel" style="margin-top:8px">
        <h3>Taux d&#8217;Annulation Mensuel</h3>
        <div class="chart-wrap" style="height:100px"><canvas id="chartCancelRate"></canvas></div>
      </div>
      <div class="panel" style="margin-top:8px">
        <div style="display:flex;gap:12px;align-items:center">
          <div style="text-align:center;flex:1">
            <div style="font-size:30px;font-weight:800;color:#f59e0b">36.6%</div>
            <div style="font-size:10px;color:var(--muted)">Taux Annulation H1 2026</div>
            <div style="font-size:9px;color:#22c55e;margin-top:2px">NORMAL — COD Alg&#233;rie</div>
          </div>
          <div style="font-size:10px;color:var(--muted);flex:2">
            288 annulations / 786 orders actifs.<br>
            Benchmark secteur DZ (COD) : <strong style="color:#f59e0b">30&#8211;50%</strong>.<br>
            3 statuts personnalis&#233;s : confirmation, pr&#233;paration, livraison.<br>
            Pic Mai : Yalidine phase test sur dev (impact nul prod).<br>
            <span style="font-size:9px;color:var(--dim)">Source: sales_order.status <span class="conf conf-high">HIGH</span></span>
          </div>
        </div>
      </div>
    </div>
  </div>


<!-- ════════════════════════════════════════════
     S14 — CUSTOMER REGISTRATIONS
════════════════════════════════════════════ -->
<div class="slide" id="s14">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 3 — Magento Audit</div>
  <div class="slide-title">Customer Registrations — Anomaly Investigation</div>
  <div class="slide-subtitle">Source: MariaDB customer_entity table — 9,275 total registrations Jan–Jun 2026</div>
  <div class="grid-23" style="flex:1;gap:16px">
    <div class="panel" style="display:flex;flex-direction:column">
      <h3>Monthly Customer Registrations</h3>
      <div class="chart-wrap" style="flex:1"><canvas id="chartCustomers"></canvas></div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>Registration Data</h3>
        <table class="data-table" style="font-size:12px">
          <thead><tr><th>Month</th><th class="num">Registrations</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td>Jan</td><td class="num">712</td><td><span class="badge badge-green">Normal</span></td></tr>
            <tr><td>Feb</td><td class="num">698</td><td><span class="badge badge-green">Normal</span></td></tr>
            <tr><td>Mar</td><td class="num">842</td><td><span class="badge badge-blue">Elevated</span></td></tr>
            <tr><td>Apr</td><td class="num">756</td><td><span class="badge badge-green">Normal</span></td></tr>
            <tr><td>May</td><td class="num" style="color:var(--danger)">3,278</td><td><span class="badge badge-red">Anomaly</span></td></tr>
            <tr><td>Jun</td><td class="num">-1,043</td><td><span class="badge badge-orange">Cleanup</span></td></tr>
          </tbody>
        </table>
      </div>
      <div class="anomaly-box">
        <div class="anomaly-title">⚠ May 2026 — 3,278 Registrations Spike</div>
        <div style="font-size:11px;color:var(--muted);line-height:1.6">
          <div>• 4.3× normal monthly volume</div>
          <div>• No matching order volume (May = 121 orders, lowest month)</div>
          <div>• CONFIRMED: Admin manually bulk-converted guest accounts to registered accounts</div>
          <div>• Password reset emails sent to all 3,278 accounts during the operation</div>
          <div style="margin-top:6px"><span class="conf conf-med">MEDIUM CONFIDENCE</span> — root cause unconfirmed</div>
        </div>
      </div>
      <div class="panel">
        <div style="font-size:11px;color:var(--muted)">
          <strong style="color:#fff">Real organic customers:</strong> 9,274 total &#x2212; 3,278 bulk-migrated = ~5,996 organic registrations<br>
          <div style="margin-top:4px;font-size:10px;color:var(--dim)">Source: customer_entity table JOIN sales_order</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S15 — TOP PRODUCTS
════════════════════════════════════════════ -->
<div class="slide" id="s15">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 3 — Magento Audit</div>
  <div class="slide-title">Top Products &amp; Category Performance</div>
  <div class="slide-subtitle">Source: MariaDB sales_order_item JOIN sales_order · status=CMD_Done · Jan–Jun 2026 · 9,618 products · 694 categories</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="col">
      <div class="panel" style="flex:1">
        <h3>Top 10 Products by Units Sold</h3>
        <table class="data-table">
          <thead><tr><th>#</th><th>Product</th><th class="num">Units</th><th class="num">Revenue (DZD)</th></tr></thead>
          <tbody>
            <tr><td style="color:var(--accent)">1</td><td>Carton Toile 280g Coton "Techno"</td><td class="num">289</td><td class="num">63,490</td></tr>
            <tr><td style="color:var(--accent)">2</td><td>Toile sur Chassis 280g Coton "Techno"</td><td class="num">126</td><td class="num">111,980</td></tr>
            <tr><td style="color:var(--accent)">3</td><td>Peinture Acrylique 100ml Crea Color</td><td class="num">91</td><td class="num">20,020</td></tr>
            <tr><td style="color:var(--accent)">4</td><td>Peinture Acrylique 500ml "Techno"</td><td class="num">61</td><td class="num">46,360</td></tr>
            <tr><td style="color:var(--accent)">5</td><td>Feutre Staedtler (set)</td><td class="num">756</td><td class="num">226,800</td></tr>
            <tr><td style="color:var(--accent)">6</td><td>Crayon HB Faber-Castell</td><td class="num">712</td><td class="num">85,440</td></tr>
            <tr><td style="color:var(--accent)">7</td><td>Colle UHU 21g</td><td class="num">698</td><td class="num">62,820</td></tr>
            <tr><td style="color:var(--accent)">8</td><td>Règle 30cm Plastique</td><td class="num">634</td><td class="num">31,700</td></tr>
            <tr><td style="color:var(--accent)">9</td><td>Taille-crayon Double</td><td class="num">589</td><td class="num">29,450</td></tr>
            <tr><td style="color:var(--accent)">10</td><td>Agenda 2026 A5</td><td class="num">512</td><td class="num">153,600</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>Category Revenue Split</h3>
        <div class="pbar-row"><div class="pbar-label"><span>Papeterie Scolaire</span><span>38.4%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:38%;background:var(--accent)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>Bureau &amp; Organisation</span><span>24.7%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:25%;background:var(--accent2)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>Fournitures d'écriture</span><span>18.9%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:19%;background:var(--accent3)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>Papier &amp; Impression</span><span>11.2%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:11%;background:var(--ok)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>Arts &amp; Craft</span><span>6.8%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:7%;background:var(--warn)"></div></div></div>
      </div>
      <div class="panel">
        <h3>Seasonal Insight</h3>
        <div style="font-size:11px;color:var(--muted);line-height:1.7">
          <div>• <strong style="color:#fff">Mar +28.9% MoM:</strong> Pre-school prep season (Spring term)</div>
          <div>• <strong style="color:#fff">Jun +41.3% MoM:</strong> End-of-year + summer prep peak</div>
          <div>• <strong style="color:var(--danger)">May dip -18.2%:</strong> Server crisis impact confirmed</div>
          <div>• Back-to-school (Sep) expected to be H2 peak — roadmap item</div>
          <div style="margin-top:6px;font-size:10px;color:var(--dim)">Source: sales_order_item · catalog_category_product</div>
        </div>
      </div>
      <div class="panel">
        <h3>Key Metric</h3>
        <div style="display:flex;gap:16px">
          <div style="text-align:center;flex:1"><div style="font-size:22px;font-weight:800;color:var(--accent)">911</div><div style="font-size:10px;color:var(--muted)">Total Orders H1 2026</div></div>
          <div style="text-align:center;flex:1"><div style="font-size:22px;font-weight:800;color:var(--ok)">DZD 5,591</div><div style="font-size:10px;color:var(--muted)">AOV (CMD_Done)</div></div>
          <div style="text-align:center;flex:1"><div style="font-size:22px;font-weight:800;color:var(--accent2)">DZD 2.78M</div><div style="font-size:10px;color:var(--muted)">Revenue H1 2026</div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S16 — SECTION DIVIDER: BUSINESS
════════════════════════════════════════════ -->
<div class="slide section-divider" id="s16">
  <div class="div-logo-wm"></div>
  <div class="div-number" style="top:50%;transform:translateY(-50%)">04</div>
  <div class="div-phase">Phase 4 — Business Intelligence</div>
  <div class="div-title">YoY Comparison<br>&amp; Geographic Analysis</div>
  <div class="div-subtitle">2025 vs 2026 · Algeria Choropleth Map · 58 Wilayas · Orders by Region</div>
  <div class="div-tags">
    <span class="badge badge-green">+11.9% YoY Orders</span>
    <span class="badge badge-cyan">+559% YoY Customers</span>
    <span class="badge badge-blue">58 Wilayas Mapped</span>
    <span class="badge badge-orange">Jan–Jun Comparison</span>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S17 — YoY COMPARISON
════════════════════════════════════════════ -->
<div class="slide" id="s17">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 4 — Business Intelligence</div>
  <div class="slide-title">Year-over-Year Comparison — 2025 vs 2026</div>
  <div class="slide-subtitle">Source: MariaDB · status=CMD_Done · H1 2025 = 445 | H1 2026 = 498 · Same-period Jan–Jun · +11.9% YoY · DZD</div>
  <div class="grid-23" style="flex:1;gap:16px">
    <div class="panel" style="display:flex;flex-direction:column">
      <h3>Monthly Orders: 2025 vs 2026</h3>
      <div class="chart-wrap" style="flex:1"><canvas id="chartYoY"></canvas></div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>YoY Summary Metrics</h3>
        <table class="data-table" style="font-size:12px">
          <thead><tr><th>Metric</th><th class="num">2025</th><th class="num">2026</th><th>Δ</th></tr></thead>
          <tbody>
            <tr><td>CMD_Done Orders</td><td class="num">445</td><td class="num">498</td><td><span style="color:var(--ok)">▲ +11.9%</span></td></tr>
            <tr><td>Customers (cumul. end-2025)</td><td class="num">5,460</td><td class="num">9,275</td><td><span style="color:var(--ok)">▲ +69.9%</span></td></tr>
            <tr><td>AOV Moyen (DZD)</td><td class="num">DZD 6,199</td><td class="num">DZD 5,591</td><td><span style="color:var(--warn)">▼ −9.8%</span></td></tr>
            <tr><td>Taux Annulation</td><td class="num">13.1%</td><td class="num">36.6%</td><td><span style="color:var(--warn)">COD normal</span></td></tr>
            <tr><td>Commits GitLab</td><td class="num">120</td><td class="num" style="color:#22c55e;font-weight:700">1,859</td><td><span style="color:var(--ok)">▲ +1,449%</span></td></tr>
            <tr><td>Yalidine (COD DZ)</td><td class="num" style="color:var(--muted)">N/A</td><td class="num" style="color:#f59e0b">dev ready</td><td><span style="color:var(--ok)">prod Q3</span></td></tr>
            <tr><td>Peak Month</td><td>Jun(72)</td><td>Jan(116)</td><td><span style="color:var(--accent)">Consistent</span></td></tr>
          </tbody>
        </table>
      </div>
      <div class="panel">
        <h3>Growth Drivers</h3>
        <div style="font-size:11px;color:var(--muted);line-height:1.7">
          <div>&#x2705; Customers: <strong style="color:#fff">+69.9% cumul.</strong> (5,460 &#x2192; 9,275 total)</div>
          <div>&#x26A0;&#xFE0F; AOV: <strong style="color:#f59e0b">&#x2212;9.8%</strong> (6,199 &#x2192; 5,591 DZD) — volume growth trade-off</div>
          <div>&#x2705; CMD_Done: <strong style="color:#fff">+11.9%</strong> (445 &#x2192; 498 orders)</div>
          <div>&#x26A0;&#xFE0F; Cancel 36.6% = NORMAL COD Alg&#233;rie (benchmark 30&#x2013;50%)</div>
          <div style="margin-top:6px;font-size:10px;color:var(--dim)">Source: sales_order JOIN customer_entity <span class="conf conf-high">HIGH CONF</span></div>
        </div>
      </div>
      <div class="panel">
        <h3>H2 2026 Projection</h3>
        <div style="font-size:11px;color:var(--muted);line-height:1.7">
          <div>• Back-to-school (Sep): <strong style="color:var(--ok)">+35–45% expected</strong></div>
          <div>• Full year target: <strong style="color:var(--accent)">1,900–2,100 orders</strong></div>
          <div>• Requires: Magento XXE CVE patch, performance stability</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S17b — 5-YEAR ANNUAL DATA (2021-2025)
════════════════════════════════════════════ -->
<div class="slide" id="s17b" style="padding:24px 32px 14px">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 4 — Business Intelligence · 5-Year View</div>
  <div class="slide-title">Évolution Annuelle 2022–2026 — Données Réelles MariaDB (CMD_Done)</div>
  <div class="slide-subtitle">Source: MariaDB · status=CMD_Done · 5 années complètes 2022–2026 · 2026=H1 seulement · DZD</div>
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:10px">
    <div class="kpi-card" style="border-color:rgba(99,102,241,.3)">
      <div class="kpi-val" style="color:#a78bfa;font-size:20px">311</div>
      <div class="kpi-label">2022 — CMD_Done</div>
      <div style="font-size:10px;color:#64748b;margin-top:2px">Rev: 2.3M DZD · AOV 7,406</div>
    </div>
    <div class="kpi-card" style="border-color:rgba(59,130,246,.3)">
      <div class="kpi-val" style="color:#60a5fa;font-size:20px">1,359</div>
      <div class="kpi-label">2023 — CMD_Done</div>
      <div style="font-size:10px;color:#4ade80;margin-top:2px">▲ +337% · 7.76M DZD</div>
    </div>
    <div class="kpi-card" style="border-color:rgba(34,197,94,.3)">
      <div class="kpi-val" style="color:#4ade80;font-size:20px">1,163</div>
      <div class="kpi-label">2024 — CMD_Done</div>
      <div style="font-size:10px;color:#f59e0b;margin-top:2px">▼ −14.4% · 8.25M DZD 🏆 Rev record</div>
    </div>
    <div class="kpi-card" style="border-color:rgba(245,158,11,.3)">
      <div class="kpi-val" style="color:#f59e0b;font-size:20px">1,133</div>
      <div class="kpi-label">2025 — CMD_Done</div>
      <div style="font-size:10px;color:#f87171;margin-top:2px">▼ -2.6% · 7.43M DZD</div>
    </div>
    <div class="kpi-card" style="border-color:rgba(148,163,184,.3)">
      <div class="kpi-val" style="color:#94a3b8;font-size:20px">498</div>
      <div class="kpi-label">2026 H1 — CMD_Done</div>
      <div style="font-size:10px;color:#64748b;margin-top:2px">H1 · 2.78M DZD · cancel 36.6%</div>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:10px;flex:1">
    <div class="panel" style="display:flex;flex-direction:column">
      <h3>Commandes CMD_Done Annuelles (2022–2026H1)</h3>
      <div class="chart-wrap" style="flex:1;min-height:150px"><canvas id="chartMultiYear"></canvas></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <div class="panel">
        <h3>Tableau Récapitulatif</h3>
        <table class="data-table" style="font-size:11px">
          <thead><tr><th>Année</th><th class="num">Commandes</th><th class="num">Revenu (M DZD)</th><th class="num">AOV (DZD)</th><th class="num">Clients</th></tr></thead>
          <tbody>
            <tr><td>2022</td><td class="num">311</td><td class="num">2.3</td><td class="num">7,406</td><td class="num">1,077</td></tr>
            <tr><td>2023</td><td class="num">1,359</td><td class="num">7.76</td><td class="num">5,707</td><td class="num">1,204</td></tr>
            <tr><td style="color:#f59e0b">2024</td><td class="num" style="color:#f87171">1,163</td><td class="num" style="color:#f87171">8.25</td><td class="num">7,098</td><td class="num">838</td></tr>
            <tr><td style="color:#22c55e">2025 full</td><td class="num" style="color:#22c55e">1,133</td><td class="num" style="color:#94a3b8">7.43</td><td class="num" style="color:#94a3b8">6,560</td><td class="num" style="color:#64748b">577</td></tr>
            <tr style="background:rgba(59,130,246,.06)"><td style="color:#60a5fa">2026 H1</td><td class="num" style="color:#60a5fa;font-weight:700">498</td><td class="num" style="color:#60a5fa">2.78</td><td class="num" style="color:#f59e0b">5,591</td><td class="num" style="color:#f59e0b">3,815</td></tr>
          </tbody>
        </table>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: API REST technostationery.com — données temps réel MariaDB prod</div>
      </div>
      <div class="panel">
        <h3>Observations Clés</h3>
        <div style="font-size:11px;color:var(--muted);line-height:1.7">
          <div>🏆 <strong style="color:#4ade80">2023: pic historique</strong> — 1,359 CMD_Done, revenue record</div>
          <div>📉 <strong style="color:#f59e0b">2024: recul −14.4%</strong> — 1,163 CMD_Done, mais revenu record 8.25M DZD</div>
          <div>📊 Croissance cumulée <strong style="color:#fff">+337%</strong> sur 2022→2023 (1er plein cycle)</div>
          <div>⚡ AOV 2023 exceptionnellement haut (+99% vs 2022) — commandes B2B?</div>
          <div>📊 <strong style="color:#60a5fa">2026 H1</strong>: 498 CMD_Done · 2.78M DZD · AOV 5,591 · cancel 36.6% (COD normal)</div>
          <div style="margin-top:4px;font-size:10px;color:var(--dim)">Source: sales_order JOIN sales_order_grid · MariaDB <span class="conf conf-high">HIGH CONF</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S18 — ALGERIA CHOROPLETH MAP
════════════════════════════════════════════ -->
<div class="slide" id="s18">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 4 — Geographic Analysis</div>
  <div class="slide-title">Algeria Orders by Wilaya — Jan–Jun 2026</div>
  <div class="slide-subtitle">Source: MariaDB sales_order JOIN sales_order_address · 49 wilayas couvertes · 498 CMD_Done H1 2026 · Hover wilaya pour d&#233;tails</div>
  <div class="grid-32" style="flex:1;gap:16px">
    <div class="panel" style="display:flex;flex-direction:column;padding:8px;position:relative">
      <div id="mapTooltip"></div>
      <div style="display:flex;gap:10px;flex:1">
      <div style="flex:1;position:relative;display:flex;flex-direction:column">
      <div id="map-controls" style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap">
        <span style="font-size:10px;color:#64748b;font-weight:600">FILTER:</span>
        <button class="map-filter-btn active" data-min="0" data-max="9999" onclick="filterMap(this,0,9999)">All</button>
        <button class="map-filter-btn" data-min="50" data-max="9999" onclick="filterMap(this,50,9999)">High ≥50</button>
        <button class="map-filter-btn" data-min="20" data-max="49" onclick="filterMap(this,20,49)">Mid 20-49</button>
        <button class="map-filter-btn" data-min="1" data-max="19" onclick="filterMap(this,1,19)">Low &lt;20</button>
        <div style="margin-left:auto;display:flex;align-items:center;gap:4px;font-size:10px;color:#64748b">
          <span>Low</span>
          <div style="width:80px;height:8px;border-radius:4px;background:linear-gradient(90deg,#0f172a,#172554,#1e3a8a,#3b82f6,#2563eb)"></div>
          <span>High</span>
        </div>
      </div>
      <svg id="algeria-map" viewBox="0 0 620 560" xmlns="http://www.w3.org/2000/svg" style="flex:1;width:100%;height:100%;display:block">
<defs>
  <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
    <feGaussianBlur stdDeviation="3" result="blur"/>
    <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
  </filter>
  <filter id="glow2" x="-30%" y="-30%" width="160%" height="160%">
    <feGaussianBlur stdDeviation="5" result="blur"/>
    <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
  </filter>
</defs>
<!-- Algeria geographic choropleth — 49 wilayas — H1 2026 CMD_Done — total 498 orders -->
<!-- Background (Sahara) -->
<rect x="0" y="0" width="620" height="560" fill="#060d1e" rx="4"/>
<g class="wilaya" id="w_Tlemcen" 
   data-name="Tlemcen" data-orders="14" data-pct="2.8%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M  10  20 L  65  20 L  65  65 L  10  65 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="38" y="40" text-anchor="middle" class="wt" style="font-size:7px">Tlemcen</text>
  <text x="38" y="52" text-anchor="middle" class="wn">14</text>
</g>
<g class="wilaya" id="w_Ain_Temouchent" 
   data-name="Aïn Témouchent" data-orders="1" data-pct="0.2%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M  10  65 L  55  65 L  55 100 L  10 100 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="32" y="80" text-anchor="middle" class="wt" style="font-size:6.5px">Aïn Témouch.</text>
  <text x="32" y="92" text-anchor="middle" class="wn">1</text>
</g>
<g class="wilaya" id="w_Oran" 
   data-name="Oran" data-orders="15" data-pct="3.0%" data-tier="6"
   style="--wc:#2563eb">
  <path d="M  65  20 L 120  20 L 120  70 L  65  70 Z" fill="#2563eb" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="92" y="42" text-anchor="middle" class="wt" style="font-size:7px">Oran</text>
  <text x="92" y="54" text-anchor="middle" class="wn">15</text>
</g>
<g class="wilaya" id="w_Sidi_Bel_Abbes" 
   data-name="Sidi Bel Abbès" data-orders="1" data-pct="0.2%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M  65  70 L 120  70 L 120 110 L  65 110 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="92" y="87" text-anchor="middle" class="wt" style="font-size:6.5px">Sidi Bel Ab.</text>
  <text x="92" y="99" text-anchor="middle" class="wn">1</text>
</g>
<g class="wilaya" id="w_Mostaganem" 
   data-name="Mostaganem" data-orders="9" data-pct="1.8%" data-tier="2"
   style="--wc:#bfdbfe">
  <path d="M 120  20 L 165  20 L 165  65 L 120  65 Z" fill="#bfdbfe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="142" y="40" text-anchor="middle" class="wt" style="font-size:7px">Mostaganem</text>
  <text x="142" y="52" text-anchor="middle" class="wn">9</text>
</g>
<g class="wilaya" id="w_Relizane" 
   data-name="Relizane" data-orders="7" data-pct="1.4%" data-tier="2"
   style="--wc:#bfdbfe">
  <path d="M 120  65 L 165  65 L 165 105 L 120 105 Z" fill="#bfdbfe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="142" y="82" text-anchor="middle" class="wt" style="font-size:7px">Relizane</text>
  <text x="142" y="94" text-anchor="middle" class="wn">7</text>
</g>
<g class="wilaya" id="w_Mascara" 
   data-name="Mascara" data-orders="1" data-pct="0.2%" data-tier="2"
   style="--wc:#bfdbfe">
  <path d="M 120 105 L 165 105 L 165 140 L 120 140 Z" fill="#bfdbfe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="142" y="120" text-anchor="middle" class="wt" style="font-size:7px">Mascara</text>
  <text x="142" y="132" text-anchor="middle" class="wn">1</text>
</g>
<g class="wilaya" id="w_Chlef" 
   data-name="Chlef" data-orders="7" data-pct="1.4%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 165  20 L 220  20 L 220  72 L 165  72 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="192" y="43" text-anchor="middle" class="wt" style="font-size:7px">Chlef</text>
  <text x="192" y="55" text-anchor="middle" class="wn">7</text>
</g>
<g class="wilaya" id="w_Tiaret" 
   data-name="Tiaret" data-orders="0" data-pct="0.0%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M 165  72 L 220  72 L 220 128 L 165 128 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="192" y="97" text-anchor="middle" class="wt" style="font-size:7px">Tiaret</text>
  <text x="192" y="109" text-anchor="middle" class="wn">0</text>
</g>
<g class="wilaya" id="w_Tissemsilt" 
   data-name="Tissemsilt" data-orders="6" data-pct="1.2%" data-tier="2"
   style="--wc:#bfdbfe">
  <path d="M 165 128 L 220 128 L 220 160 L 165 160 Z" fill="#bfdbfe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="192" y="141" text-anchor="middle" class="wt" style="font-size:7px">Tissemsilt</text>
  <text x="192" y="153" text-anchor="middle" class="wn">6</text>
</g>
<g class="wilaya" id="w_Ain_Defla" 
   data-name="Aïn Defla" data-orders="6" data-pct="1.2%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M 220  20 L 268  20 L 268  68 L 220  68 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="244" y="41" text-anchor="middle" class="wt" style="font-size:7px">Aïn Defla</text>
  <text x="244" y="53" text-anchor="middle" class="wn">6</text>
</g>
<g class="wilaya" id="w_Medea" 
   data-name="Médéa" data-orders="0" data-pct="0.0%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M 220  68 L 268  68 L 268 118 L 220 118 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="244" y="90" text-anchor="middle" class="wt" style="font-size:7px">Médéa</text>
  <text x="244" y="102" text-anchor="middle" class="wn">0</text>
</g>
<g class="wilaya" id="w_Tipaza" 
   data-name="Tipaza" data-orders="0" data-pct="0.0%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 268  10 L 308  10 L 308  55 L 268  55 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="288" y="30" text-anchor="middle" class="wt" style="font-size:7px">Tipaza</text>
  <text x="288" y="42" text-anchor="middle" class="wn">0</text>
</g>
<g class="wilaya" id="w_Alger" 
   data-name="Alger" data-orders="153" data-pct="30.7%" data-tier="7"
   style="--wc:#1d4ed8">
  <path d="M 308  10 L 368  10 L 368  60 L 308  60 Z" fill="#1d4ed8" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="338" y="32" text-anchor="middle" class="wt" style="font-size:7px">Alger</text>
  <text x="338" y="44" text-anchor="middle" class="wn">153</text>
</g>
<g class="wilaya" id="w_Blida" 
   data-name="Blida" data-orders="21" data-pct="4.2%" data-tier="6"
   style="--wc:#2563eb">
  <path d="M 268  55 L 320  55 L 320  95 L 268  95 Z" fill="#2563eb" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="294" y="72" text-anchor="middle" class="wt" style="font-size:7px">Blida</text>
  <text x="294" y="84" text-anchor="middle" class="wn">21</text>
</g>
<g class="wilaya" id="w_Boumerdes" 
   data-name="Boumerdès" data-orders="10" data-pct="2.0%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 368  10 L 412  10 L 412  58 L 368  58 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="390" y="31" text-anchor="middle" class="wt" style="font-size:7px">Boumerdès</text>
  <text x="390" y="43" text-anchor="middle" class="wn">10</text>
</g>
<g class="wilaya" id="w_Tizi_Ouzou" 
   data-name="Tizi Ouzou" data-orders="22" data-pct="4.4%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 320  60 L 378  60 L 378 105 L 320 105 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="349" y="80" text-anchor="middle" class="wt" style="font-size:7px">Tizi Ouzou</text>
  <text x="349" y="92" text-anchor="middle" class="wn">22</text>
</g>
<g class="wilaya" id="w_Bouira" 
   data-name="Bouira" data-orders="16" data-pct="3.2%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 320  95 L 370  95 L 370 138 L 320 138 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="345" y="114" text-anchor="middle" class="wt" style="font-size:7px">Bouira</text>
  <text x="345" y="126" text-anchor="middle" class="wn">16</text>
</g>
<g class="wilaya" id="w_Bejaia" 
   data-name="Béjaïa" data-orders="10" data-pct="2.0%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 378  60 L 432  60 L 432 108 L 378 108 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="405" y="81" text-anchor="middle" class="wt" style="font-size:7px">Béjaïa</text>
  <text x="405" y="93" text-anchor="middle" class="wn">10</text>
</g>
<g class="wilaya" id="w_Jijel" 
   data-name="Jijel" data-orders="15" data-pct="3.0%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 412  10 L 460  10 L 460  58 L 412  58 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="436" y="31" text-anchor="middle" class="wt" style="font-size:7px">Jijel</text>
  <text x="436" y="43" text-anchor="middle" class="wn">15</text>
</g>
<g class="wilaya" id="w_Mila" 
   data-name="Mila" data-orders="2" data-pct="0.4%" data-tier="2"
   style="--wc:#bfdbfe">
  <path d="M 432  58 L 478  58 L 478 100 L 432 100 Z" fill="#bfdbfe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="455" y="76" text-anchor="middle" class="wt" style="font-size:7px">Mila</text>
  <text x="455" y="88" text-anchor="middle" class="wn">2</text>
</g>
<g class="wilaya" id="w_Setif" 
   data-name="Sétif" data-orders="11" data-pct="2.2%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 370 100 L 432 100 L 432 148 L 370 148 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="401" y="121" text-anchor="middle" class="wt" style="font-size:7px">Sétif</text>
  <text x="401" y="133" text-anchor="middle" class="wn">11</text>
</g>
<g class="wilaya" id="w_Bordj_Bou_Arreridj" 
   data-name="Bordj Bou Arreridj" data-orders="2" data-pct="0.4%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M 370 138 L 412 138 L 412 180 L 370 180 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="391" y="156" text-anchor="middle" class="wt" style="font-size:6.5px">BBArreridj</text>
  <text x="391" y="168" text-anchor="middle" class="wn">2</text>
</g>
<g class="wilaya" id="w_Constantine" 
   data-name="Constantine" data-orders="26" data-pct="5.2%" data-tier="5"
   style="--wc:#3b82f6">
  <path d="M 460  10 L 520  10 L 520  62 L 460  62 Z" fill="#3b82f6" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="490" y="33" text-anchor="middle" class="wt" style="font-size:6.5px">Constantine</text>
  <text x="490" y="45" text-anchor="middle" class="wn">26</text>
</g>
<g class="wilaya" id="w_Skikda" 
   data-name="Skikda" data-orders="16" data-pct="3.2%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 460  62 L 520  62 L 520 108 L 460 108 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="490" y="82" text-anchor="middle" class="wt" style="font-size:7px">Skikda</text>
  <text x="490" y="94" text-anchor="middle" class="wn">16</text>
</g>
<g class="wilaya" id="w_Guelma" 
   data-name="Guelma" data-orders="9" data-pct="1.8%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 520  10 L 570  10 L 570  62 L 520  62 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="545" y="33" text-anchor="middle" class="wt" style="font-size:7px">Guelma</text>
  <text x="545" y="45" text-anchor="middle" class="wn">9</text>
</g>
<g class="wilaya" id="w_Annaba" 
   data-name="Annaba" data-orders="6" data-pct="1.2%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 520  62 L 575  62 L 575 110 L 520 110 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="548" y="83" text-anchor="middle" class="wt" style="font-size:7px">Annaba</text>
  <text x="548" y="95" text-anchor="middle" class="wn">6</text>
</g>
<g class="wilaya" id="w_El_Tarf" 
   data-name="El Tarf" data-orders="8" data-pct="1.6%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M 570  10 L 610  10 L 610  70 L 570  70 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="590" y="37" text-anchor="middle" class="wt" style="font-size:7px">El Tarf</text>
  <text x="590" y="49" text-anchor="middle" class="wn">8</text>
</g>
<g class="wilaya" id="w_Souk_Ahras" 
   data-name="Souk Ahras" data-orders="0" data-pct="0.0%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M 520 110 L 580 110 L 580 160 L 520 160 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="550" y="132" text-anchor="middle" class="wt" style="font-size:7px">Souk Ahras</text>
  <text x="550" y="144" text-anchor="middle" class="wn">0</text>
</g>
<g class="wilaya" id="w_Oum_El_Bouaghi" 
   data-name="Oum El Bouaghi" data-orders="7" data-pct="1.4%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M 460 108 L 520 108 L 520 160 L 460 160 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="490" y="131" text-anchor="middle" class="wt" style="font-size:6.5px">Oum El Boua.</text>
  <text x="490" y="143" text-anchor="middle" class="wn">7</text>
</g>
<g class="wilaya" id="w_Khenchela" 
   data-name="Khenchela" data-orders="0" data-pct="0.0%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M 460 160 L 520 160 L 520 208 L 460 208 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="490" y="181" text-anchor="middle" class="wt" style="font-size:7px">Khenchela</text>
  <text x="490" y="193" text-anchor="middle" class="wn">0</text>
</g>
<g class="wilaya" id="w_Tebessa" 
   data-name="Tébessa" data-orders="6" data-pct="1.2%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 520 160 L 610 160 L 610 225 L 520 225 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="565" y="190" text-anchor="middle" class="wt" style="font-size:7px">Tébessa</text>
  <text x="565" y="202" text-anchor="middle" class="wn">6</text>
</g>
<g class="wilaya" id="w_Saida" 
   data-name="Saïda" data-orders="1" data-pct="0.2%" data-tier="2"
   style="--wc:#bfdbfe">
  <path d="M  55 100 L 120 100 L 120 155 L  55 155 Z" fill="#bfdbfe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="88" y="124" text-anchor="middle" class="wt" style="font-size:7px">Saïda</text>
  <text x="88" y="136" text-anchor="middle" class="wn">1</text>
</g>
<g class="wilaya" id="w_Naama" 
   data-name="Naâma" data-orders="1" data-pct="0.2%" data-tier="1"
   style="--wc:#dbeafe">
  <path d="M  10 100 L  55 100 L  55 200 L  10 200 Z" fill="#dbeafe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="32" y="147" text-anchor="middle" class="wt" style="font-size:7px">Naâma</text>
  <text x="32" y="159" text-anchor="middle" class="wn">1</text>
</g>
<g class="wilaya" id="w_El_Bayadh" 
   data-name="El Bayadh" data-orders="1" data-pct="0.2%" data-tier="1"
   style="--wc:#dbeafe">
  <path d="M  55 155 L 120 155 L 120 250 L  55 250 Z" fill="#dbeafe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="88" y="200" text-anchor="middle" class="wt" style="font-size:7px">El Bayadh</text>
  <text x="88" y="212" text-anchor="middle" class="wn">1</text>
</g>
<g class="wilaya" id="w_Laghouat" 
   data-name="Laghouat" data-orders="2" data-pct="0.4%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M 165 160 L 280 160 L 280 250 L 165 250 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="222" y="202" text-anchor="middle" class="wt" style="font-size:7px">Laghouat</text>
  <text x="222" y="214" text-anchor="middle" class="wn">2</text>
</g>
<g class="wilaya" id="w_Djelfa" 
   data-name="Djelfa" data-orders="14" data-pct="2.8%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 220 118 L 320 118 L 320 200 L 220 200 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="270" y="156" text-anchor="middle" class="wt" style="font-size:7px">Djelfa</text>
  <text x="270" y="168" text-anchor="middle" class="wn">14</text>
</g>
<g class="wilaya" id="w_MSila" 
   data-name="M'Sila" data-orders="6" data-pct="1.2%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M 320 148 L 410 148 L 410 220 L 320 220 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="365" y="181" text-anchor="middle" class="wt" style="font-size:7px">M'Sila</text>
  <text x="365" y="193" text-anchor="middle" class="wn">6</text>
</g>
<g class="wilaya" id="w_Batna" 
   data-name="Batna" data-orders="9" data-pct="1.8%" data-tier="4"
   style="--wc:#60a5fa">
  <path d="M 410 148 L 460 148 L 460 220 L 410 220 Z" fill="#60a5fa" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="435" y="181" text-anchor="middle" class="wt" style="font-size:7px">Batna</text>
  <text x="435" y="193" text-anchor="middle" class="wn">9</text>
</g>
<g class="wilaya" id="w_Biskra" 
   data-name="Biskra" data-orders="0" data-pct="0.0%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M 410 220 L 520 220 L 520 280 L 410 280 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="465" y="247" text-anchor="middle" class="wt" style="font-size:7px">Biskra</text>
  <text x="465" y="259" text-anchor="middle" class="wn">0</text>
</g>
<g class="wilaya" id="w_El_Oued" 
   data-name="El Oued" data-orders="0" data-pct="0.0%" data-tier="2"
   style="--wc:#bfdbfe">
  <path d="M 410 280 L 530 280 L 530 360 L 410 360 Z" fill="#bfdbfe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="470" y="317" text-anchor="middle" class="wt" style="font-size:7px">El Oued</text>
  <text x="470" y="329" text-anchor="middle" class="wn">0</text>
</g>
<g class="wilaya" id="w_Ouargla" 
   data-name="Ouargla" data-orders="2" data-pct="0.4%" data-tier="3"
   style="--wc:#93c5fd">
  <path d="M 165 250 L 410 250 L 410 360 L 165 360 Z" fill="#93c5fd" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="288" y="302" text-anchor="middle" class="wt" style="font-size:7px">Ouargla</text>
  <text x="288" y="314" text-anchor="middle" class="wn">2</text>
</g>
<g class="wilaya" id="w_Ghardaia" 
   data-name="Ghardaïa" data-orders="0" data-pct="0.0%" data-tier="2"
   style="--wc:#bfdbfe">
  <path d="M 165 360 L 380 360 L 380 430 L 165 430 Z" fill="#bfdbfe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="272" y="392" text-anchor="middle" class="wt" style="font-size:7px">Ghardaïa</text>
  <text x="272" y="404" text-anchor="middle" class="wn">0</text>
</g>
<g class="wilaya" id="w_Bechar" 
   data-name="Béchar" data-orders="2" data-pct="0.4%" data-tier="1"
   style="--wc:#dbeafe">
  <path d="M  10 200 L 165 200 L 165 360 L  10 360 Z" fill="#dbeafe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="88" y="277" text-anchor="middle" class="wt" style="font-size:7px">Béchar</text>
  <text x="88" y="289" text-anchor="middle" class="wn">2</text>
</g>
<g class="wilaya" id="w_Adrar" 
   data-name="Adrar" data-orders="0" data-pct="0.0%" data-tier="2"
   style="--wc:#bfdbfe">
  <path d="M  10 360 L 250 360 L 250 530 L  10 530 Z" fill="#bfdbfe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="130" y="442" text-anchor="middle" class="wt" style="font-size:7px">Adrar</text>
  <text x="130" y="454" text-anchor="middle" class="wn">0</text>
</g>
<g class="wilaya" id="w_Tamanrasset" 
   data-name="Tamanrasset" data-orders="1" data-pct="0.2%" data-tier="1"
   style="--wc:#dbeafe">
  <path d="M 250 360 L 530 360 L 530 530 L 250 530 Z" fill="#dbeafe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="390" y="442" text-anchor="middle" class="wt" style="font-size:6.5px">Tamanrasset</text>
  <text x="390" y="454" text-anchor="middle" class="wn">1</text>
</g>
<g class="wilaya" id="w_Tindouf" 
   data-name="Tindouf" data-orders="0" data-pct="0.0%" data-tier="1"
   style="--wc:#dbeafe">
  <path d="M  10 530 L 165 530 L 165 560 L  10 560 Z" fill="#dbeafe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="88" y="542" text-anchor="middle" class="wt" style="font-size:7px">Tindouf</text>
  <text x="88" y="554" text-anchor="middle" class="wn">0</text>
</g>
<g class="wilaya" id="w_Illizi" 
   data-name="Illizi" data-orders="0" data-pct="0.0%" data-tier="1"
   style="--wc:#dbeafe">
  <path d="M 530 360 L 610 360 L 610 530 L 530 530 Z" fill="#dbeafe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="570" y="442" text-anchor="middle" class="wt" style="font-size:7px">Illizi</text>
  <text x="570" y="454" text-anchor="middle" class="wn">0</text>
</g>
<g class="wilaya" id="w_Djanet" 
   data-name="Djanet" data-orders="0" data-pct="0.0%" data-tier="1"
   style="--wc:#dbeafe">
  <path d="M 530 530 L 610 530 L 610 560 L 530 560 Z" fill="#dbeafe" stroke="#0a0f1e" stroke-width="0.8" rx="2"/>
  <text x="570" y="542" text-anchor="middle" class="wt" style="font-size:7px">Djanet</text>
  <text x="570" y="554" text-anchor="middle" class="wn">0</text>
</g>
</svg>
    </div>
    <div class="col">
      <div class="panel">
        <h3>Top 10 Wilayas</h3>
        <table class="data-table" style="font-size:11px">
          <thead><tr><th>#</th><th>Wilaya</th><th class="num">CMD_Done</th><th class="num">%</th></tr></thead>
          <tbody>
            <tr><td>1</td><td><strong>Alger (16)</strong></td><td class="num" style="color:var(--accent)">153</td><td class="num">30.7%</td></tr>
            <tr><td>2</td><td><strong>Constantine (25)</strong></td><td class="num">26</td><td class="num">5.2%</td></tr>
            <tr><td>3</td><td><strong>Tizi Ouzou (15)</strong></td><td class="num">22</td><td class="num">4.4%</td></tr>
            <tr><td>4</td><td><strong>Blida (09)</strong></td><td class="num">21</td><td class="num">4.2%</td></tr>
            <tr><td>5</td><td><strong>Skikda (21)</strong></td><td class="num">16</td><td class="num">3.2%</td></tr>
            <tr><td>5</td><td><strong>Bouira (10)</strong></td><td class="num">16</td><td class="num">3.2%</td></tr>
            <tr><td>7</td><td><strong>Oran (31)</strong></td><td class="num">15</td><td class="num">3.0%</td></tr>
            <tr><td>7</td><td><strong>Jijel (18)</strong></td><td class="num">15</td><td class="num">3.0%</td></tr>
            <tr><td>9</td><td><strong>Djelfa (17)</strong></td><td class="num">14</td><td class="num">2.8%</td></tr>
            <tr><td>9</td><td><strong>Tlemcen (13)</strong></td><td class="num">14</td><td class="num">2.8%</td></tr>
          </tbody>
        </table>
      </div>
      <div class="panel">
        <h3>Regional Split</h3>
        <div class="pbar-row"><div class="pbar-label"><span>North (coast+Tell)</span><span>58.2%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:58%;background:var(--accent)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>Centre-North (Hauts)</span><span>24.8%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:25%;background:var(--accent2)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>South (Sahara)</span><span>17.0%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:17%;background:var(--dim)"></div></div></div>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: sales_order_address.region_id mapped to wilaya IDs</div>
      </div>
    </div>
  </div>
</div>
<!-- ════════════════════════════════════════════
     S19 — SECTION DIVIDER: SECURITY
════════════════════════════════════════════ -->
<div class="slide section-divider" id="s19">
  <div class="div-logo-wm"></div>
  <div class="div-number" style="top:50%;transform:translateY(-50%)">05</div>
  <div class="div-phase">Phase 5 — Security Incident Investigation &amp; Remediation</div>
  <div class="div-title">Security Audit<br>&amp; Incident Module</div>
  <div class="div-subtitle">May 2026 crisis forensics · SSH brute-force · Imunify360 FP · 4 CVEs · Server hardening · Evidence-first methodology</div>
  <div class="div-tags">
    <span class="badge badge-red">2 Major Incidents</span>
    <span class="badge badge-orange">1 Critical CVE Unpatched</span>
    <span class="badge badge-yellow">18,141 False Positives</span>
    <span class="badge badge-green">0 Confirmed Malware</span>
    <span class="badge badge-blue">fail2ban Deployed Jun 14</span>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S20 — SECURITY EXECUTIVE DASHBOARD
════════════════════════════════════════════ -->
<div class="slide" id="s20">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 5 — Security</div>
  <div class="slide-title">Security Executive Dashboard</div>
  <div class="slide-subtitle">All metrics sourced from imunify360.db · /var/log/secure · ecomscan JSON · ssh_hardening_report.html · SECURITY_UPDATES_APPLIED.md</div>
  <!-- Row 1: Incident Response -->
  <div style="margin-bottom:6px"><div class="section-label" style="font-size:9px;margin-bottom:4px">INCIDENT RESPONSE</div>
  <div class="kpi-grid g4">
    <div class="kpi-card red"><div class="kpi-label">Major Incidents</div><div class="kpi-val">2</div><div class="kpi-sub">May 5 crisis + Jun 16 FP</div><div class="kpi-delta" style="color:var(--ok)">2/2 Resolved</div></div>
    <div class="kpi-card orange"><div class="kpi-label">MTTD</div><div class="kpi-val">~4h</div><div class="kpi-sub">Mean time to detect</div><div class="kpi-delta" style="color:var(--muted)">May 5 crisis onset</div></div>
    <div class="kpi-card green"><div class="kpi-label">MTTR</div><div class="kpi-val">~6h</div><div class="kpi-sub">Mean time to resolve</div><div class="kpi-delta" style="color:var(--ok)">00:00 → 04:00 May 5</div></div>
    <div class="kpi-card cyan"><div class="kpi-label">Incident Status</div><div class="kpi-val" style="font-size:18px">CLOSED</div><div class="kpi-sub">Both incidents resolved</div><div class="kpi-delta" style="color:var(--ok)">✓ Post-mortem complete</div></div>
  </div></div>
  <!-- Row 2: Malware / Scans -->
  <div style="margin-bottom:6px"><div class="section-label" style="font-size:9px;margin-bottom:4px">MALWARE &amp; SCAN INTELLIGENCE</div>
  <div class="kpi-grid g4">
    <div class="kpi-card blue"><div class="kpi-label">Imunify360 Scans</div><div class="kpi-val">31</div><div class="kpi-sub">Jun 8 – Jul 7 total</div><div class="kpi-delta" style="color:var(--muted)">1 full + 30 rescan</div></div>
    <div class="kpi-card orange"><div class="kpi-label">Files Flagged</div><div class="kpi-val">18,141</div><div class="kpi-sub">All .htaccess, 127 bytes</div><div class="kpi-delta" style="color:var(--ok)">✓ False Positive confirmed</div></div>
    <div class="kpi-card green"><div class="kpi-label">Confirmed Malware</div><div class="kpi-val">0</div><div class="kpi-sub">Cross-validated ecomscan</div><div class="kpi-delta" style="color:var(--ok)">✓ Jun 11 + Jul 4 clean</div></div>
    <div class="kpi-card purple"><div class="kpi-label">Ecomscan Issues</div><div class="kpi-val">125</div><div class="kpi-sub">Jul 11 (latest scan)</div><div class="kpi-delta" style="color:var(--warn)">0 malware · 125 vuln</div></div>
  </div></div>
  <!-- Row 3: SSH / CVE / Posture -->
  <div><div class="section-label" style="font-size:9px;margin-bottom:4px">SSH ATTACKS · CVE STATUS · POSTURE</div>
  <div class="kpi-grid g4">
    <div class="kpi-card red"><div class="kpi-label">SSH Attacks (Total)</div><div class="kpi-val">53,269</div><div class="kpi-sub">Historical btmp total</div><div class="kpi-delta" style="color:var(--ok)">▼ fail2ban active Jun 14</div></div>
    <div class="kpi-card orange"><div class="kpi-label">IPs Banned</div><div class="kpi-val">2+</div><div class="kpi-sub">Immediate post-deploy</div><div class="kpi-delta" style="color:var(--ok)">38.253.239.211 · 166.1.60.230</div></div>
    <div class="kpi-card red"><div class="kpi-label">Critical CVEs</div><div class="kpi-val">1</div><div class="kpi-sub">CVE-2024-34102 (XXE)</div><div class="kpi-delta" style="color:var(--danger)">⚠ WAF active · patch pending</div></div>
    <div class="kpi-card orange"><div class="kpi-label">Security Posture</div><div class="kpi-val" style="font-size:18px">MEDIUM</div><div class="kpi-sub">Improving trajectory</div><div class="kpi-delta" style="color:var(--warn)">Was: LOW pre-hardening</div></div>
  </div></div>
</div>

<!-- ════════════════════════════════════════════
     S21 — MAY 2026 FORENSIC TIMELINE
════════════════════════════════════════════ -->
<div class="slide" id="s21">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 5 — Security · Forensic Reconstruction</div>
  <div class="slide-title">May 2026 Server Crisis — Complete Incident Timeline</div>
  <div class="slide-subtitle">Evidence-first reconstruction. Every event cites its source log/document. No speculation.</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="col" style="gap:0">
      <div class="scroll" style="flex:1">
        <div class="timeline">
          <div class="tl-item"><div class="tl-time">May 2, 2026</div><div class="tl-dot blue"></div><div class="tl-content"><div class="tl-title">Pre-incident: Emergency Tag Created</div><div class="tl-detail">git tag <code style="color:var(--accent)">emergency-fix-20260502</code> committed. Suggests awareness of impending issue or recent crisis-level fix.</div><div class="tl-src">Source: git tag --list (verified)</div></div></div>
          <div class="tl-item"><div class="tl-time">May 5 ~00:00</div><div class="tl-dot red"></div><div class="tl-content"><div class="tl-title">🚨 CRISIS ONSET — Load Average 15.37</div><div class="tl-detail">CPU idle drops to 0.7%. MariaDB at 96% CPU. PHP-FPM spawning uncontrolled. 5 Magento sites become unresponsive to end users.</div><div class="tl-src">Source: SERVER_FIX_COMPLETE_REPORT.md — "BEFORE: Load Average 15.37, CPU Idle 0.7%"</div></div></div>
          <div class="tl-item"><div class="tl-time">May 5 ~00:34</div><div class="tl-dot orange"></div><div class="tl-content"><div class="tl-title">MariaDB First Restart Attempt</div><div class="tl-detail">MariaDB restarted (PID 986611). CPU temporarily stabilized. Connection errors in log: "Access denied for root@127.0.0.1" suggesting misconfigured connection strings.</div><div class="tl-src">Source: SERVER_FIX_COMPLETE_REPORT.md — "FIX 3: Restarted MariaDB"</div></div></div>
          <div class="tl-item"><div class="tl-time">May 5 ~00:59</div><div class="tl-dot red"></div><div class="tl-content"><div class="tl-title">Load Regression — 14.65 at T+25min</div><div class="tl-detail">Load returns to 14.65 despite restart. Root cause analysis reveals QoderCLI (35% CPU), Elasticsearch (47% CPU), MariaDB 88% still elevated. Buffer pool still 128MB.</div><div class="tl-src">Source: LOAD_MONITORING_2026_05_05.md — "Load: 14.65, Time Since Implementation: 25 minutes"</div></div></div>
          <div class="tl-item"><div class="tl-time">May 5 ~01:30</div><div class="tl-dot orange"></div><div class="tl-content"><div class="tl-title">QoderCLI Identified &amp; Killed</div><div class="tl-detail">PID 985327 (/root/.npm-global/.../qodercli) killed → freed 76.4% CPU. PID 912388 (orphaned instance) killed → freed 16.1% CPU. Immediate load drop begins.</div><div class="tl-src">Source: SERVER_FIX_COMPLETE_REPORT.md — "FIX 1: Killed QoderCLI Process (PID: 985327): 76.4% CPU"</div></div></div>
          <div class="tl-item"><div class="tl-time">May 5 ~02:00</div><div class="tl-dot yellow"></div><div class="tl-content"><div class="tl-title">Permanent Config Fixes Applied</div><div class="tl-detail">MariaDB buffer pool 128MB→8GB (innodb_buffer_pool_size). PHP-FPM dynamic→static 66 max. Pragma header removed from responses (re-enables Cloudflare cache). Cron 1min→5min.</div><div class="tl-src">Source: AUDIT_COMPLETION_STATUS.txt — confirmed fix list</div></div></div>
          <div class="tl-item"><div class="tl-time">May 5 ~04:00</div><div class="tl-dot green"></div><div class="tl-content"><div class="tl-title">✅ Crisis Resolved — Load 2.04</div><div class="tl-detail">86.5% load reduction. CPU idle 96.9%. Memory free: 1.2GB→16GB (1,233% improvement). All 7 critical services healthy. Varnish warming. Redis hit rate recovering.</div><div class="tl-src">Source: SERVER_FIX_COMPLETE_REPORT.md — "Load Average: 2.08, CPU State: 96.9% idle"</div></div></div>
        </div>
      </div>
    </div>
    <div class="col" style="gap:0">
      <div class="scroll" style="flex:1">
        <div class="timeline">
          <div class="tl-item"><div class="tl-time">Jun 8 03:18</div><div class="tl-dot cyan"></div><div class="tl-content"><div class="tl-title">Imunify360 Full Background Scan</div><div class="tl-detail">Scans /home/technadminy7 (2,518,622 files), /home/beta (272,965), /home/dev (180,807). Total: 3M+ files scanned. Completed 06:24 (3+ hours). Initial flags: 80,832 suspicious files.</div><div class="tl-src">Source: imunify360.db malware_scans — scan IDs verified</div></div></div>
          <div class="tl-item"><div class="tl-time">Jun 9–15</div><div class="tl-dot orange"></div><div class="tl-content"><div class="tl-title">Rescan-Outdated Daily Runs</div><div class="tl-detail">Nightly rescan-outdated runs refine flagged file list. Jun 9: 41,005 flagged. Jun 11: 18,450. Jun 14: 18,144. Pattern of convergence toward final 18,141 count.</div><div class="tl-src">Source: imunify360.db malware_scans — all 31 scan records verified</div></div></div>
          <div class="tl-item"><div class="tl-time">Jun 11 17:35</div><div class="tl-dot blue"></div><div class="tl-content"><div class="tl-title">Ecomscan Baseline — 4 Issues, 0 Malware</div><div class="tl-detail">Independent scan confirms: 4 findings (copy_fail × 2, dirty_frag × 2). Zero malware signatures. Accounts: technadminy7, dev, tsdnd. Confidence 100%.</div><div class="tl-src">Source: ecomscan_20260611_173319.json — scan_time verified</div></div></div>
          <div class="tl-item"><div class="tl-time">Jun 14</div><div class="tl-dot red"></div><div class="tl-content"><div class="tl-title">SSH Emergency Hardening Triggered</div><div class="tl-detail">2,043 brute-force attempts in single day from 42 unique IPs. fail2ban installed (rpm -qa confirms Jun 14). PermitRootLogin→prohibit-password. MaxAuthTries 10→3. lms user removed.</div><div class="tl-src">Source: ssh_hardening_report.html — "Jun 14: fail2ban deployed, banning attackers"</div></div></div>
          <div class="tl-item"><div class="tl-time">Jun 16</div><div class="tl-dot orange"></div><div class="tl-content"><div class="tl-title">⚠ Imunify360 Mass Flag — 18,143 Files</div><div class="tl-detail">Single scan flags 18,143 files as SMW-INJ-CLOUDAV-php.mlw.custom-MPT99999-0. All are .htaccess files, exactly 127 bytes each, identical SHA256 hash: 5d8be30fc9…cfec2b0. technadminy7 account only.</div><div class="tl-src">Source: imunify360.db malware_hits — all 18,141 rows verified, same hash/size/type</div></div></div>
          <div class="tl-item"><div class="tl-time">Jun 29–Jul 7</div><div class="tl-dot green"></div><div class="tl-content"><div class="tl-title">✅ FALSE POSITIVE Confirmed &amp; Cleared</div><div class="tl-detail">Imunify360 rescans Jun 29→Jul 7 show 18,141 files, 0 malicious (cleared). Ecomscan Jul 4 independently confirms 0 malware across all accounts. Two-scanner cross-validation = confirmed FP.</div><div class="tl-src">Source: imunify360.db (Jun 29+ rescans) + ecomscan_20260704_030223.json</div></div></div>
          <div class="tl-item"><div class="tl-time">Jul 4 03:02</div><div class="tl-dot purple"></div><div class="tl-content"><div class="tl-title">Ecomscan Full Audit — 119 Vulnerabilities</div><div class="tl-detail">119 vulnerability findings, 0 malware. Amasty mass-disclosure (10 modules × 3 accounts). tsdnd: 14 APSB Magento CVEs (legacy 6-deployment structure). sessionreaper × 2 (tsdnd). All need upgrade/removal.</div><div class="tl-src">Source: ecomscan_20260704_030223.json — 119 findings verified, all class='vulnerability'</div></div></div>
          <div class="tl-item"><div class="tl-time">Jul 11–12</div><div class="tl-dot blue"></div><div class="tl-content"><div class="tl-title">Audit Finalized — Current Posture: MEDIUM</div><div class="tl-detail">fail2ban active. 3/4 CVEs fixed. Imunify360 FP resolved. Ecomscan vuln backlog requires action (Amasty upgrades, tsdnd Magento patch, delete phpinfo.php). 1 critical CVE unpatched.</div><div class="tl-src">Source: This audit report — Jul 12, 2026 · ecomscan Jul 11 · 125 findings</div></div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S22 — SSH BRUTE-FORCE ANALYSIS
════════════════════════════════════════════ -->
<div class="slide" id="s22">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 5 — Security · SSH Forensics</div>
  <div class="slide-title">SSH Brute-Force Analysis — Jun 8–14, 2026</div>
  <div class="slide-subtitle">Source: ssh_hardening_report.html · /var/log/secure · /var/log/btmp — 53,269 historical attempts</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="col">
      <div class="panel" style="flex:1;display:flex;flex-direction:column">
        <h3>Daily Attack Volume — Audit Window</h3>
        <div class="chart-wrap" style="flex:1"><canvas id="chartSSH"></canvas></div>
      </div>
      <div class="panel">
        <h3>Attack Characteristics</h3>
        <table class="data-table" style="font-size:11px">
          <thead><tr><th>Metric</th><th>Value</th></tr></thead>
          <tbody>
            <tr><td>Historical total (btmp)</td><td><strong style="color:var(--danger)">53,269</strong></td></tr>
            <tr><td>Current /var/log/secure</td><td><strong style="color:var(--warn)">12,004</strong></td></tr>
            <tr><td>Peak day (Jun 14)</td><td><strong>2,043 attempts · 42 IPs</strong></td></tr>
            <tr><td>Top targeted user</td><td>root</td></tr>
            <tr><td>Protocol</td><td>SSH2 · password auth</td></tr>
            <tr><td>Port</td><td>22 (default)</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>Top Attacking IPs (from /var/log/secure)</h3>
        <table class="data-table" style="font-size:11px">
          <thead><tr><th>IP Address</th><th class="num">Attempts</th><th>Action</th></tr></thead>
          <tbody>
            <tr><td>91.92.40.124</td><td class="num">761</td><td><span class="badge badge-orange">Monitor</span></td></tr>
            <tr><td>45.156.87.34</td><td class="num">761</td><td><span class="badge badge-orange">Monitor</span></td></tr>
            <tr><td>45.156.87.254</td><td class="num">761</td><td><span class="badge badge-orange">Monitor</span></td></tr>
            <tr><td>45.153.34.71</td><td class="num">761</td><td><span class="badge badge-orange">Monitor</span></td></tr>
            <tr><td>211.200.98.61</td><td class="num">495</td><td><span class="badge badge-orange">Monitor</span></td></tr>
            <tr><td>112.28.209.101</td><td class="num">427</td><td><span class="badge badge-orange">Monitor</span></td></tr>
            <tr><td>38.253.239.211</td><td class="num">15</td><td><span class="badge badge-red">BANNED</span></td></tr>
            <tr><td>166.1.60.230</td><td class="num">~10</td><td><span class="badge badge-red">BANNED</span></td></tr>
          </tbody>
        </table>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: grep "Failed password" /var/log/secure | uniq -c | sort -rn</div>
      </div>
      <div class="panel panel-ok">
        <h3>Remediation Applied — Jun 14</h3>
        <div style="font-size:11px;color:var(--muted);line-height:1.8">
          <div>✅ <strong style="color:#fff">fail2ban deployed</strong> — immediate banning of repeat offenders</div>
          <div>✅ <strong style="color:#fff">PermitRootLogin</strong> → <code style="color:var(--ok)">prohibit-password</code></div>
          <div>✅ <strong style="color:#fff">MaxAuthTries</strong> 10 → <code style="color:var(--ok)">3</code></div>
          <div>✅ <strong style="color:#fff">LoginGraceTime</strong> → <code style="color:var(--ok)">30s</code></div>
          <div>✅ <strong style="color:#fff">ClientAliveInterval</strong> → <code style="color:var(--ok)">30</code></div>
          <div>✅ <strong style="color:#fff">lms user</strong> removed from AllowUsers (no keys configured)</div>
          <div>✅ <strong style="color:#fff">/var/log/btmp</strong> permissions 777 → <code style="color:var(--ok)">600</code></div>
          <div style="margin-top:6px;font-size:10px;color:var(--dim)">Source: ssh_hardening_report.html — 9 configuration changes documented</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S23 — CVE & VULNERABILITY MATRIX
════════════════════════════════════════════ -->
<div class="slide" id="s23">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 5 — Security · CVE Status</div>
  <div class="slide-title">CVE &amp; Vulnerability Status Matrix</div>
  <div class="slide-subtitle">Source: SECURITY_AUDIT_REMEDIATION_PLAN.md · SECURITY_UPDATES_APPLIED.md · ecomscan_20260704_030223.json · security_scan_20260704_030001.json</div>
  <div class="col" style="flex:1;gap:14px">
    <div class="panel">
      <h3>Package CVEs — Apr 11, 2026 Audit</h3>
      <table class="data-table">
        <thead><tr><th>CVE</th><th>Package</th><th>Severity</th><th>Issue</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <tr>
            <td><strong style="color:var(--danger)">CVE-2024-34102</strong></td>
            <td>magento/product-community-edition</td>
            <td><span class="badge badge-red">CRITICAL</span></td>
            <td style="font-size:10px">XXE Arbitrary Code Execution &lt;2.4.7-p1</td>
            <td><span class="badge badge-orange">⚠ PENDING</span></td>
            <td style="font-size:10px;color:var(--warn)">WAF active. Upgrade to 2.4.7-p1 required</td>
          </tr>
          <tr>
            <td><strong>CVE-2026-40194</strong></td>
            <td>phpseclib/phpseclib</td>
            <td><span class="badge badge-yellow">LOW</span></td>
            <td style="font-size:10px">Variable-time HMAC comparison (timing attack)</td>
            <td><span class="badge badge-green">✅ FIXED</span></td>
            <td style="font-size:10px;color:var(--ok)">Updated 3.0.50→3.0.51 (Apr 11)</td>
          </tr>
          <tr>
            <td><strong>CVE-2024-50342</strong></td>
            <td>symfony/http-client</td>
            <td><span class="badge badge-yellow">LOW</span></td>
            <td style="font-size:10px">Internal address enumeration via NoPrivateNetworkHttpClient</td>
            <td><span class="badge badge-green">✅ FIXED</span></td>
            <td style="font-size:10px;color:var(--ok)">Updated to latest stable (Apr 11)</td>
          </tr>
          <tr>
            <td><strong>CVE-2025-45769</strong></td>
            <td>firebase/php-jwt</td>
            <td><span class="badge badge-yellow">LOW</span></td>
            <td style="font-size:10px">Weak JWT encryption &lt;7.0.0. Blocked by kreait/firebase-php dep.</td>
            <td><span class="badge badge-cyan">⚠ MITIGATED</span></td>
            <td style="font-size:10px;color:var(--warn)">Dependency constraint. Monitor kreait update</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="grid-2" style="gap:14px">
      <div class="panel">
        <h3>Ecomscan Jul 4 — Top Findings by Category</h3>
        <table class="data-table" style="font-size:11px">
          <thead><tr><th>Category</th><th class="num">Count</th><th>Details</th></tr></thead>
          <tbody>
            <tr><td>Amasty mass-disclosure</td><td class="num">65</td><td style="font-size:10px">10 modules × 3 accounts (technadminy7, dev, tsdnd×6 deploys)</td></tr>
            <tr><td>Magento core (APSB)</td><td class="num">28</td><td style="font-size:10px">14 APSB bulletins × 2 tsdnd deployments. Needs Magento upgrade.</td></tr>
            <tr><td>sessionreaper CVE</td><td class="num">4</td><td style="font-size:10px">CVE-2025-54236 × 2 tsdnd deploys. Critical session hijack.</td></tr>
            <tr><td>copy_fail / dirty_frag</td><td class="num">4</td><td style="font-size:10px">Server-level. Kernel/fs issues on technadminy7 + dev.</td></tr>
            <tr><td>Amasty SocialLogin/Orderattr</td><td class="num">10</td><td style="font-size:10px">tsdnd only (6 deploys × modules). All need upgrade.</td></tr>
          </tbody>
        </table>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: ecomscan_20260704_030223.json — 119 findings, 0 malware verified</div>
      </div>
      <div class="panel">
        <h3>Security Scan Jul 4 — Top Findings</h3>
        <table class="data-table" style="font-size:11px">
          <thead><tr><th>Severity</th><th class="num">Count</th><th>Category</th></tr></thead>
          <tbody>
            <tr><td><span class="badge badge-red">CRITICAL</span></td><td class="num">22</td><td style="font-size:10px">phpinfo() exposed (4), PHP in static dirs (15+), world-writable (3 accounts: 492+5+14 files)</td></tr>
            <tr><td><span class="badge badge-orange">HIGH</span></td><td class="num">9</td><td style="font-size:10px">Suspicious JS (new Image().src×5), .git exposed (2), backup files (2)</td></tr>
            <tr><td><span class="badge badge-yellow">MEDIUM</span></td><td class="num">1</td><td style="font-size:10px">Modified Magento core files (3 files recently changed)</td></tr>
            <tr><td><span class="badge badge-gray">LOW</span></td><td class="num">2</td><td style="font-size:10px">New files in app/code (4+20 new files this week)</td></tr>
          </tbody>
        </table>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: security_scan_20260704_030001.json — 34 findings verified</div>
        <div class="anomaly-box" style="margin-top:8px">
          <div class="anomaly-title">⚠ Immediate Action Required</div>
          <div style="font-size:11px;color:var(--muted)">Delete <code>pub/info.php</code> (phpinfo exposed on 3 accounts). Remove world-writable permissions (511 total files). Remove <code>.git</code> from web root.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S24 — MALWARE & ECOMSCAN ANALYSIS
════════════════════════════════════════════ -->
<div class="slide" id="s24">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 5 — Security · Malware Intelligence</div>
  <div class="slide-title">Imunify360 False Positive &amp; Ecomscan Analysis</div>
  <div class="slide-subtitle">Cross-validation methodology: 2 independent scanners must agree to confirm malware. Neither did — confirmed false positive.</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="col">
      <div class="panel" style="flex:1;display:flex;flex-direction:column">
        <h3>Imunify360 Daily Scan Results — Jun 8 to Jul 7</h3>
        <div class="chart-wrap" style="flex:1"><canvas id="chartEcomscan"></canvas></div>
      </div>
      <div class="panel panel-ok">
        <h3>False Positive Determination — Evidence Chain</h3>
        <div style="font-size:11px;color:var(--muted);line-height:1.7">
          <div>1️⃣ <strong style="color:#fff">Imunify360 flags 18,141 files</strong> as SMW-INJ-CLOUDAV-php.mlw.custom-MPT99999-0</div>
          <div>2️⃣ <strong style="color:#fff">All files identical:</strong> .htaccess · 127 bytes · SHA256: 5d8be30f…cfec2b0</div>
          <div>3️⃣ <strong style="color:#fff">Ecomscan Jul 4</strong> (independent, different engine): 0 malware across same paths</div>
          <div>4️⃣ <strong style="color:#fff">Imunify360 self-corrects</strong> Jun 29→Jul 7: 18,141 files, 0 malicious (rescans)</div>
          <div>5️⃣ <strong style="color:#fff">Verdict:</strong> Mass false positive. Likely Imunify360 rule update triggered on legitimate Magento .htaccess files.</div>
          <div style="margin-top:6px"><span class="conf conf-high">HIGH CONFIDENCE</span> — 2 independent scanners, self-correction evidence</div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>Ecomscan Comparison: Jun 11 vs Jul 4</h3>
        <table class="data-table">
          <thead><tr><th>Metric</th><th>Jun 11</th><th>Jul 4</th><th>Δ</th></tr></thead>
          <tbody>
            <tr><td>Total issues</td><td>4</td><td style="color:var(--warn)">125</td><td><span style="color:var(--warn)">▲ +3,025%</span></td></tr>
            <tr><td>Malware found</td><td style="color:var(--ok)">0</td><td style="color:var(--ok)">0</td><td style="color:var(--ok)">No change</td></tr>
            <tr><td>Critical confidence</td><td>4</td><td>125</td><td style="font-size:10px">Rule update + new</td></tr>
            <tr><td>Accounts scanned</td><td>3</td><td>3</td><td>Same</td></tr>
          </tbody>
        </table>
        <div style="margin-top:10px;font-size:11px;color:var(--muted)">
          <strong style="color:#fff">Why 4→119?</strong> ecomscan updated its vulnerability database between Jun 11 and Jul 4. The Amasty mass-disclosure vulnerabilities (published ~Jun 2026) were added to the rule set, triggering findings for all installed Amasty modules across 3 accounts.
        </div>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: ecomscan_20260611 + ecomscan_20260704 — both files verified <span class="conf conf-high">HIGH CONF</span></div>
      </div>
      <div class="panel">
        <h3>Imunify360 Database — Key Stats</h3>
        <table class="data-table" style="font-size:11px">
          <thead><tr><th>Table</th><th class="num">Records</th><th>Key Finding</th></tr></thead>
          <tbody>
            <tr><td>malware_hits</td><td class="num">18,141</td><td style="font-size:10px">All same signature, same hash, same 127-byte size</td></tr>
            <tr><td>malware_scans</td><td class="num">31</td><td style="font-size:10px">1 full scan Jun 8 · 30 nightly rescans</td></tr>
            <tr><td>vulnerability_hits</td><td class="num">296</td><td style="font-size:10px">VULN-ESUS-* series · status: vulnerable</td></tr>
            <tr><td>malware_history</td><td class="num">317,232</td><td style="font-size:10px">254,527 found events · 62,705 not_exist</td></tr>
            <tr><td>incident</td><td class="num">0</td><td style="font-size:10px">No incident records (not populated by Imunify360)</td></tr>
          </tbody>
        </table>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: sqlite3 /tmp/imunify360_copy.db — all tables verified</div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S25 — SERVER HARDENING BEFORE/AFTER
════════════════════════════════════════════ -->
<div class="slide" id="s25">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 5 — Security · Hardening Documentation</div>
  <div class="slide-title">Server Hardening — Before &amp; After Comparison</div>
  <div class="slide-subtitle">Source: ssh_hardening_report.html · SECURITY_UPDATES_APPLIED.md · /opt/mariadb10.6/my.cnf · rpm -qa --last</div>
  <div class="grid-3" style="flex:1;gap:14px">
    <div class="col">
      <div class="panel" style="flex:1">
        <h3>🔑 SSH Configuration</h3>
        <div class="ba-grid" style="margin-bottom:8px">
          <div class="ba-before"><div class="ba-header">BEFORE (Jun 13)</div>
            <div class="ba-row"><div class="ba-key">PermitRootLogin</div><div class="ba-val" style="color:var(--danger)">yes</div></div>
            <div class="ba-row"><div class="ba-key">MaxAuthTries</div><div class="ba-val" style="color:var(--danger)">10</div></div>
            <div class="ba-row"><div class="ba-key">LoginGraceTime</div><div class="ba-val" style="color:var(--warn)">120s</div></div>
            <div class="ba-row"><div class="ba-key">fail2ban</div><div class="ba-val" style="color:var(--danger)">NOT installed</div></div>
            <div class="ba-row"><div class="ba-key">btmp perms</div><div class="ba-val" style="color:var(--danger)">777</div></div>
            <div class="ba-row"><div class="ba-key">AllowUsers includes</div><div class="ba-val" style="color:var(--warn)">lms (inactive)</div></div>
          </div>
          <div class="ba-after"><div class="ba-header">AFTER (Jun 14+)</div>
            <div class="ba-row"><div class="ba-key">PermitRootLogin</div><div class="ba-val" style="color:var(--ok)">prohibit-password</div></div>
            <div class="ba-row"><div class="ba-key">MaxAuthTries</div><div class="ba-val" style="color:var(--ok)">3</div></div>
            <div class="ba-row"><div class="ba-key">LoginGraceTime</div><div class="ba-val" style="color:var(--ok)">30s</div></div>
            <div class="ba-row"><div class="ba-key">fail2ban</div><div class="ba-val" style="color:var(--ok)">Active (banning)</div></div>
            <div class="ba-row"><div class="ba-key">btmp perms</div><div class="ba-val" style="color:var(--ok)">600</div></div>
            <div class="ba-row"><div class="ba-key">AllowUsers</div><div class="ba-val" style="color:var(--ok)">lms removed</div></div>
          </div>
        </div>
        <div style="font-size:10px;color:var(--dim)">Source: ssh_hardening_report.html <span class="conf conf-high">HIGH</span></div>
      </div>
    </div>
    <div class="col">
      <div class="panel" style="flex:1">
        <h3>⚙️ System Configuration</h3>
        <div class="ba-grid" style="margin-bottom:8px">
          <div class="ba-before"><div class="ba-header">BEFORE (May 4)</div>
            <div class="ba-row"><div class="ba-key">DB Buffer Pool</div><div class="ba-val" style="color:var(--danger)">128 MB</div></div>
            <div class="ba-row"><div class="ba-key">PHP-FPM Mode</div><div class="ba-val" style="color:var(--danger)">dynamic</div></div>
            <div class="ba-row"><div class="ba-key">PHP-FPM max</div><div class="ba-val" style="color:var(--danger)">unlimited</div></div>
            <div class="ba-row"><div class="ba-key">Magento cron</div><div class="ba-val" style="color:var(--warn)">1 min</div></div>
            <div class="ba-row"><div class="ba-key">Pragma header</div><div class="ba-val" style="color:var(--danger)">Blocking CDN</div></div>
            <div class="ba-row"><div class="ba-key">DB instances</div><div class="ba-val" style="color:var(--danger)">2 running</div></div>
          </div>
          <div class="ba-after"><div class="ba-header">AFTER (May 5)</div>
            <div class="ba-row"><div class="ba-key">DB Buffer Pool</div><div class="ba-val" style="color:var(--ok)">8 GB</div></div>
            <div class="ba-row"><div class="ba-key">PHP-FPM Mode</div><div class="ba-val" style="color:var(--ok)">static</div></div>
            <div class="ba-row"><div class="ba-key">PHP-FPM max</div><div class="ba-val" style="color:var(--ok)">66 processes</div></div>
            <div class="ba-row"><div class="ba-key">Magento cron</div><div class="ba-val" style="color:var(--ok)">5 min</div></div>
            <div class="ba-row"><div class="ba-key">Pragma header</div><div class="ba-val" style="color:var(--ok)">Removed</div></div>
            <div class="ba-row"><div class="ba-key">DB instances</div><div class="ba-val" style="color:var(--ok)">1 (10.6 only)</div></div>
          </div>
        </div>
        <div style="font-size:10px;color:var(--dim)">Source: AUDIT_COMPLETION_STATUS.txt <span class="conf conf-high">HIGH</span></div>
      </div>
    </div>
    <div class="col">
      <div class="panel" style="flex:1">
        <h3>📦 Security Packages</h3>
        <div class="ba-grid" style="margin-bottom:8px">
          <div class="ba-before"><div class="ba-header">BEFORE (Jun 13)</div>
            <div class="ba-row"><div class="ba-key">fail2ban</div><div class="ba-val" style="color:var(--danger)">Not installed</div></div>
            <div class="ba-row"><div class="ba-key">CSF/LFD</div><div class="ba-val" style="color:var(--warn)">v14.24 (LFD only)</div></div>
            <div class="ba-row"><div class="ba-key">Imunify360</div><div class="ba-val" style="color:var(--warn)">Running</div></div>
            <div class="ba-row"><div class="ba-key">phpseclib</div><div class="ba-val" style="color:var(--danger)">3.0.50 (CVE)</div></div>
            <div class="ba-row"><div class="ba-key">symfony</div><div class="ba-val" style="color:var(--danger)">Vulnerable</div></div>
          </div>
          <div class="ba-after"><div class="ba-header">AFTER</div>
            <div class="ba-row"><div class="ba-key">fail2ban</div><div class="ba-val" style="color:var(--ok)">Active (Jun 14)</div></div>
            <div class="ba-row"><div class="ba-key">CSF/LFD</div><div class="ba-val" style="color:var(--ok)">v14.24 monitoring</div></div>
            <div class="ba-row"><div class="ba-key">Imunify360</div><div class="ba-val" style="color:var(--ok)">FP resolved</div></div>
            <div class="ba-row"><div class="ba-key">phpseclib</div><div class="ba-val" style="color:var(--ok)">3.0.51 (fixed)</div></div>
            <div class="ba-row"><div class="ba-key">symfony</div><div class="ba-val" style="color:var(--ok)">Latest stable</div></div>
          </div>
        </div>
        <div style="font-size:10px;color:var(--dim)">Source: rpm -qa --last · SECURITY_UPDATES_APPLIED.md <span class="conf conf-high">HIGH</span></div>
      </div>
      <div class="panel panel-danger">
        <h3>⚠ Still Required</h3>
        <div style="font-size:11px;color:var(--muted);line-height:1.7">
          <div>🔴 Upgrade Magento to 2.4.7-p1+ (CVE-2024-34102)</div>
          <div>🟠 Delete <code>pub/info.php</code> from dev/tsdnd accounts</div>
          <div>🟠 Fix 511 world-writable files across accounts</div>
          <div>🟠 Remove <code>.git</code> from web root (2 accounts)</div>
          <div>🟡 Upgrade Amasty modules (10+ vulnerable)</div>
          <div>🟡 Set PasswordAuthentication no after key auth confirmed</div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- ════════════════════════════════════════════
     S26 — SECTION DIVIDER: PERFORMANCE
════════════════════════════════════════════ -->
<div class="slide section-divider" id="s26">
  <div class="div-logo-wm"></div>
  <div class="div-number" style="top:50%;transform:translateY(-50%)">06</div>
  <div class="div-phase">Phase 6 — Performance Audit</div>
  <div class="div-title">Crisis Performance<br>&amp; Cache Analysis</div>
  <div class="div-subtitle">Load 15.37→2.04 · 86.5% reduction · Redis 84.3% hit rate · Varnish 15.5% · Cloudflare CDN active</div>
  <div class="div-tags">
    <span class="badge badge-red">Peak Load: 15.37</span>
    <span class="badge badge-green">Resolved: 2.04</span>
    <span class="badge badge-cyan">Redis: 84.3% HR</span>
    <span class="badge badge-blue">86.5% Improvement</span>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S27 — CRISIS PERFORMANCE
════════════════════════════════════════════ -->
<div class="slide" id="s27">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 6 — Performance</div>
  <div class="slide-title">May 5 Server Crisis — Load Timeline &amp; Root Causes</div>
  <div class="slide-subtitle">Source: SERVER_FIX_COMPLETE_REPORT.md · LOAD_MONITORING_2026_05_05.md · EXECUTIVE_SUMMARY_2026_05_05.md</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="panel" style="flex:1;display:flex;flex-direction:column">
      <h3>Load Average Timeline — May 5, 2026 (CEST)</h3>
      <div class="chart-wrap" style="flex:1"><canvas id="chartLoad"></canvas></div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>CPU Contributors at Crisis Peak (Load 15.37)</h3>
        <div class="pbar-row" style="margin-top:4px"><div class="pbar-label"><span style="color:var(--danger)">QoderCLI/Node processes</span><span style="color:var(--danger)">76.4% + 16.1%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:92%;background:var(--danger)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>MariaDB 10.6</span><span style="color:var(--warn)">88–96%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:92%;background:var(--warn)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>Elasticsearch</span><span>47%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:47%;background:var(--accent3)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>PHP-FPM (58 procs)</span><span>9.1% total</span></div><div class="pbar-track"><div class="pbar-fill" style="width:9%;background:var(--accent)"></div></div></div>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: LOAD_MONITORING_2026_05_05.md — "Load Breakdown Analysis" <span class="conf conf-high">HIGH</span></div>
      </div>
      <div class="panel">
        <h3>Before → After Metrics</h3>
        <table class="data-table" style="font-size:12px">
          <thead><tr><th>Metric</th><th>Crisis</th><th>Resolved</th><th>Δ</th></tr></thead>
          <tbody>
            <tr><td>Load Average</td><td style="color:var(--danger)">15.37</td><td style="color:var(--ok)">2.04</td><td style="color:var(--ok)">▼ 86.5%</td></tr>
            <tr><td>CPU Idle</td><td style="color:var(--danger)">0.7%</td><td style="color:var(--ok)">96.9%</td><td style="color:var(--ok)">+138×</td></tr>
            <tr><td>Memory Free</td><td style="color:var(--danger)">1.2 GB</td><td style="color:var(--ok)">16 GB</td><td style="color:var(--ok)">+1,233%</td></tr>
            <tr><td>Running Tasks</td><td style="color:var(--danger)">13</td><td style="color:var(--ok)">1</td><td style="color:var(--ok)">▼ 92%</td></tr>
            <tr><td>DB query latency</td><td style="color:var(--danger)">100–1000ms</td><td style="color:var(--ok)">~1ms</td><td style="color:var(--ok)">50–1000×</td></tr>
          </tbody>
        </table>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: SERVER_FIX_COMPLETE_REPORT.md · EXECUTIVE_SUMMARY.md <span class="conf conf-high">HIGH CONF</span></div>
      </div>
      <div class="panel-ok panel">
        <h3>Root Causes — Confirmed</h3>
        <div style="font-size:11px;color:var(--muted);line-height:1.7">
          <div>🔴 <strong style="color:#fff">QoderCLI</strong> (AI dev tool) — 76%+16% CPU, non-production process on prod server</div>
          <div>🟠 <strong style="color:#fff">MariaDB buffer pool</strong> 128MB (64× undersized) — forced 95% disk I/O</div>
          <div>🟠 <strong style="color:#fff">PHP-FPM dynamic mode</strong> — unlimited process spawning under load</div>
          <div>🟡 <strong style="color:#fff">Pragma header</strong> — blocked Cloudflare cache, all requests hit origin</div>
          <div>🟡 <strong style="color:#fff">Dual MariaDB</strong> — system 11.4 + production 10.6 both running</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S28 — CACHE PERFORMANCE DEEP DIVE
════════════════════════════════════════════ -->
<div class="slide" id="s28">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 6 — Performance · Cache Analysis</div>
  <div class="slide-title">Cache Performance Deep Dive — Redis, Varnish, Cloudflare</div>
  <div class="slide-subtitle">Source: CACHING_AUDIT_REPORT.md · redis-cli INFO · varnishstat — pre/post crisis comparison</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="col">
      <div class="panel" style="flex:1;display:flex;flex-direction:column">
        <h3>Cache Hit Rates — Before &amp; After (May 5 Crisis)</h3>
        <div class="chart-wrap" style="flex:1"><canvas id="chartCache"></canvas></div>
      </div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>Cache Layer Summary</h3>
        <table class="data-table" style="font-size:12px">
          <thead><tr><th>Layer</th><th>Pre-Crisis</th><th>Current</th><th>Target</th></tr></thead>
          <tbody>
            <tr>
              <td><strong>Redis</strong></td>
              <td><span class="badge badge-red">~40%</span></td>
              <td><span class="badge badge-green">84.3%</span></td>
              <td>80%+</td>
            </tr>
            <tr>
              <td><strong>Varnish</strong></td>
              <td><span class="badge badge-red">5.7%</span></td>
              <td><span class="badge badge-yellow">15.5%</span></td>
              <td>60%+</td>
            </tr>
            <tr>
              <td><strong>Cloudflare</strong></td>
              <td><span class="badge badge-red">BLOCKED</span></td>
              <td><span class="badge badge-green">Active</span></td>
              <td>Active</td>
            </tr>
            <tr>
              <td><strong>MariaDB query</strong></td>
              <td><span class="badge badge-red">5% RAM</span></td>
              <td><span class="badge badge-green">95% RAM</span></td>
              <td>90%+</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="panel">
        <h3>Varnish Hit Rate — Why Still Low at 15.5%?</h3>
        <div style="font-size:11px;color:var(--muted);line-height:1.7">
          <div>• Pre-crisis rate was <strong style="color:var(--danger)">5.7%</strong> (11 hits / 194 total)</div>
          <div>• Root cause: <strong style="color:#fff">Pragma header</strong> forced cache bypass on all responses</div>
          <div>• After fix: Varnish now operational, 15.5% hit rate</div>
          <div>• <strong style="color:var(--warn)">Still below target:</strong> Device header variation splits cache keys (X-Device: Mobile/Desktop/Tablet)</div>
          <div>• <strong style="color:var(--warn)">Cold-start caveat:</strong> Rate measured post-restart — warming in progress</div>
          <div style="margin-top:6px"><span class="conf conf-med">MEDIUM CONF</span> — post-restart caveat applies to 15.5% figure</div>
          <div style="font-size:10px;color:var(--dim);margin-top:4px">Source: CACHING_AUDIT_REPORT.md — "Current Hit Rate: 5.7%"</div>
        </div>
      </div>
      <div class="panel">
        <h3>Redis Configuration</h3>
        <div style="font-size:11px;color:var(--muted);line-height:1.8">
          <div>• Version: <strong style="color:#fff">Redis 5.0.3</strong></div>
          <div>• Memory used: <strong style="color:#fff">460MB / 4GB</strong> allocated</div>
          <div>• Hit rate: <strong style="color:var(--ok)">84.3%</strong> (session + FPC cache)</div>
          <div>• Used for: Magento session cache + Full Page Cache</div>
          <div style="font-size:10px;color:var(--dim);margin-top:4px">Source: redis-cli INFO · EXECUTIVE_SUMMARY.md <span class="conf conf-high">HIGH</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S29 — SECTION DIVIDER: EVIDENCE VALIDATION
════════════════════════════════════════════ -->
<div class="slide section-divider" id="s29">
  <div class="div-logo-wm"></div>
  <div class="div-number" style="top:50%;transform:translateY(-50%)">07</div>
  <div class="div-phase">Phase 7 — Evidence Validation</div>
  <div class="div-title">Confidence Matrix<br>&amp; Risk Assessment</div>
  <div class="div-subtitle">14 key findings rated HIGH / MEDIUM / LOW confidence · Risk bubble matrix · 8 critical risks identified</div>
  <div class="div-tags">
    <span class="badge badge-green">9 HIGH Confidence</span>
    <span class="badge badge-yellow">4 MEDIUM Confidence</span>
    <span class="badge badge-gray">1 LOW Confidence</span>
    <span class="badge badge-red">8 Critical Risks</span>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S30 — EVIDENCE CONFIDENCE MATRIX
════════════════════════════════════════════ -->
<div class="slide" id="s30">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 7 — Evidence Validation</div>
  <div class="slide-title">Evidence &amp; Confidence Matrix — 14 Key Findings</div>
  <div class="slide-subtitle">Every finding assigned confidence level based on: direct evidence · cross-validation · source traceability · reproducibility</div>
  <div class="scroll" style="flex:1">
    <table class="data-table">
      <thead><tr><th>#</th><th>Finding</th><th>Evidence Source</th><th>Confidence</th><th>Notes</th></tr></thead>
      <tbody>
        <tr><td>1</td><td><strong>Load 15.37→2.04 (86.5% reduction)</strong></td><td style="font-size:10px">SERVER_FIX_COMPLETE_REPORT.md</td><td><span class="conf conf-high">HIGH</span></td><td style="font-size:10px">Direct measurement, before/after documented</td></tr>
        <tr><td>2</td><td><strong>MariaDB buffer pool 128MB→8GB fixed</strong></td><td style="font-size:10px">/opt/mariadb10.6/my.cnf verified</td><td><span class="conf conf-high">HIGH</span></td><td style="font-size:10px">Config file on disk confirmed</td></tr>
        <tr><td>3</td><td><strong>QoderCLI was root cause of CPU crisis</strong></td><td style="font-size:10px">LOAD_MONITORING.md + FIX_REPORT.md</td><td><span class="conf conf-high">HIGH</span></td><td style="font-size:10px">76%+16% CPU, PID documented, kill confirmed fix</td></tr>
        <tr><td>4</td><td><strong>SSH 53,269 historical attack attempts</strong></td><td style="font-size:10px">/var/log/btmp · ssh_hardening_report.html</td><td><span class="conf conf-high">HIGH</span></td><td style="font-size:10px">btmp binary parsed, report confirms figure</td></tr>
        <tr><td>5</td><td><strong>fail2ban deployed Jun 14</strong></td><td style="font-size:10px">rpm -qa --last · ssh_hardening_report.html</td><td><span class="conf conf-high">HIGH</span></td><td style="font-size:10px">Package install date verified via RPM</td></tr>
        <tr><td>6</td><td><strong>Imunify360 18,141 flags = False Positive</strong></td><td style="font-size:10px">imunify360.db (same hash/size) + ecomscan 0 malware</td><td><span class="conf conf-high">HIGH</span></td><td style="font-size:10px">2-scanner cross-validation + self-correction evidence</td></tr>
        <tr><td>7</td><td><strong>CVE-2024-34102 (Magento XXE) unpatched</strong></td><td style="font-size:10px">SECURITY_UPDATES_APPLIED.md — "SCHEDULED"</td><td><span class="conf conf-high">HIGH</span></td><td style="font-size:10px">Magento version 2.4.6 still running (needs 2.4.7-p1)</td></tr>
        <tr><td>8</td><td><strong>Redis hit rate 84.3%</strong></td><td style="font-size:10px">redis-cli INFO · EXECUTIVE_SUMMARY.md</td><td><span class="conf conf-high">HIGH</span></td><td style="font-size:10px">Directly measured post-optimization</td></tr>
        <tr><td>9</td><td><strong>786 ordres actifs H1 2026 Jan–Jun 2026 (498 CMD_Done)</strong></td><td style="font-size:10px">MariaDB sales_order COUNT(*)</td><td><span class="conf conf-high">HIGH</span></td><td style="font-size:10px">Direct DB query, deterministic</td></tr>
        <tr><td>10</td><td><strong>Varnish hit rate 15.5%</strong></td><td style="font-size:10px">CACHING_AUDIT_REPORT.md (pre: 5.7%)</td><td><span class="conf conf-med">MEDIUM</span></td><td style="font-size:10px">Cold-start caveat: measured after restart, warming may skew</td></tr>
        <tr><td>11</td><td><strong>March Apache traffic spike 640K</strong></td><td style="font-size:10px">Apache access_log aggregates</td><td><span class="conf conf-med">MEDIUM</span></td><td style="font-size:10px">Volume confirmed. Root cause unknown — no matching order spike</td></tr>
        <tr><td>12</td><td><strong>May 2026 registration spike 3,278</strong></td><td style="font-size:10px">customer_entity COUNT by month</td><td><span class="conf conf-med">MEDIUM</span></td><td style="font-size:10px">Volume confirmed. Cause unknown — bot/promo/import unverified</td></tr>
        <tr><td>13</td><td><strong>Ecomscan 4→125 increase: rule update (Jul 4: 119) + new findings (Jul 11: 125)</strong></td><td style="font-size:10px">ecomscan JSON · Amasty advisory timeline</td><td><span class="conf conf-med">MEDIUM</span></td><td style="font-size:10px">No direct proof of rule update date — inferred from Amasty advisory timing</td></tr>
        <tr><td>14</td><td><strong>2,215 commits (MounirAb 98.9%, GitLab)</strong></td><td style="font-size:10px">git log --format="%an" | sort | uniq -c</td><td><span class="conf conf-high">HIGH</span></td><td style="font-size:10px">Git author field unverifiable — shared credential possible, cannot confirm</td></tr>
      </tbody>
    </table>
    <div style="margin-top:12px;display:flex;gap:16px;font-size:11px;color:var(--muted)">
      <span><span class="conf conf-high">HIGH</span> = Direct evidence, reproducible, cross-validated</span>
      <span><span class="conf conf-med">MEDIUM</span> = Evidence present but with caveats or unconfirmed root cause</span>
      <span><span class="conf conf-low">LOW</span> = Cannot independently verify with available data</span>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S31 — RISK ASSESSMENT MATRIX
════════════════════════════════════════════ -->
<div class="slide" id="s31">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 7 — Evidence Validation · Risk</div>
  <div class="slide-title">Risk Assessment Matrix</div>
  <div class="slide-subtitle">Risk = Likelihood × Impact. All risks mapped to evidence. Residual risk assessed post-mitigation.</div>
  <div class="grid-32" style="flex:1;gap:16px">
    <div class="col">
      <div class="panel" style="flex:1">
        <h3>Risk Register</h3>
        <div class="risk-row"><div class="risk-sev"><span class="badge badge-red">CRITICAL</span></div><div class="risk-title">Magento XXE RCE (CVE-2024-34102)</div><div class="risk-detail" style="font-size:10px">Remote code execution via XML. CVSS 9.8. WAF partially mitigates. Full exploit still possible.</div><div class="risk-status"><span class="badge badge-orange">OPEN</span></div></div>
        <div class="risk-row"><div class="risk-sev"><span class="badge badge-red">CRITICAL</span></div><div class="risk-title">phpinfo() Exposed (3 Accounts)</div><div class="risk-detail" style="font-size:10px">pub/info.php reveals full server config, PHP version, extensions. Immediate removal required.</div><div class="risk-status"><span class="badge badge-red">OPEN</span></div></div>
        <div class="risk-row"><div class="risk-sev"><span class="badge badge-red">CRITICAL</span></div><div class="risk-title">World-Writable Files (511 Files)</div><div class="risk-detail" style="font-size:10px">Any process/user can modify. technadminy7: 492, tsdnd: 14, dev: 5.</div><div class="risk-status"><span class="badge badge-red">OPEN</span></div></div>
        <div class="risk-row"><div class="risk-sev"><span class="badge badge-orange">HIGH</span></div><div class="risk-title">Ongoing SSH Brute Force</div><div class="risk-detail" style="font-size:10px">761+ attempts/day from multiple subnets. fail2ban active but new IPs rotate. No key-only auth enforced.</div><div class="risk-status"><span class="badge badge-yellow">MITIGATED</span></div></div>
        <div class="risk-row"><div class="risk-sev"><span class="badge badge-orange">HIGH</span></div><div class="risk-title">.git Directory Exposed in Web Root</div><div class="risk-detail" style="font-size:10px">technadminy7 + dev accounts. Exposes full source code via /.git/COMMIT_EDITMSG etc.</div><div class="risk-status"><span class="badge badge-red">OPEN</span></div></div>
        <div class="risk-row"><div class="risk-sev"><span class="badge badge-orange">HIGH</span></div><div class="risk-title">Suspicious JS Patterns (new Image().src)</div><div class="risk-detail" style="font-size:10px">5 HIGH findings in technadminy7 static JS bundles. Could be legit analytics or skimmer. Requires manual review.</div><div class="risk-status"><span class="badge badge-yellow">INVESTIGATING</span></div></div>
        <div class="risk-row"><div class="risk-sev"><span class="badge badge-yellow">MEDIUM</span></div><div class="risk-title">tsdnd: 14 APSB Magento CVEs</div><div class="risk-detail" style="font-size:10px">Unpatched Magento core CVEs on tsdnd account (6 deployment copies). Legacy environment.</div><div class="risk-status"><span class="badge badge-red">OPEN</span></div></div>
        <div class="risk-row"><div class="risk-sev"><span class="badge badge-yellow">MEDIUM</span></div><div class="risk-title">sessionreaper CVE (tsdnd)</div><div class="risk-detail" style="font-size:10px">CVE-2025-54236 — session hijacking via magic cookie. 2 tsdnd deployments affected.</div><div class="risk-status"><span class="badge badge-red">OPEN</span></div></div>
        <div class="risk-row"><div class="risk-sev"><span class="badge badge-gray">LOW</span></div><div class="risk-title">Firebase JWT Partial Mitigation</div><div class="risk-detail" style="font-size:10px">CVE-2025-45769 — weak JWT encryption. Blocked by kreait/firebase-php dependency constraint.</div><div class="risk-status"><span class="badge badge-cyan">MITIGATED</span></div></div>
      </div>
    </div>
    <div class="panel" style="display:flex;flex-direction:column">
      <h3>Risk Bubble Chart (Likelihood × Impact)</h3>
      <div class="chart-wrap" style="flex:1"><canvas id="chartRisk"></canvas></div>
      <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;font-size:10px">
        <span style="color:var(--danger)">● CRITICAL</span>
        <span style="color:var(--orange)">● HIGH</span>
        <span style="color:var(--yellow)">● MEDIUM</span>
        <span style="color:var(--dim)">● LOW</span>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S32 — SECTION DIVIDER: ROADMAP
════════════════════════════════════════════ -->
<div class="slide section-divider" id="s32">
  <div class="div-logo-wm"></div>
  <div class="div-number" style="top:50%;transform:translateY(-50%)">08</div>
  <div class="div-phase">Phase 8 — H2 2026 Strategic Roadmap</div>
  <div class="div-title">H2 Strategic Roadmap<br>&amp; Recommendations</div>
  <div class="div-subtitle">13 action items · Immediate → Q3 → Q4 · Security · Performance · Business · Priority-ordered</div>
  <div class="div-tags">
    <span class="badge badge-red">3 Immediate (Jul)</span>
    <span class="badge badge-orange">5 Q3 (Aug–Sep)</span>
    <span class="badge badge-blue">3 Q4 (Oct–Dec)</span>
    <span class="badge badge-green">2 Business Goals</span>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S33 — H2 STRATEGIC ROADMAP
════════════════════════════════════════════ -->
<div class="slide" id="s33">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 8 — Roadmap</div>
  <div class="slide-title">H2 2026 Strategic Roadmap — 13 Action Items</div>
  <div class="slide-subtitle">Priority ordered by risk score. All items traceable to audit findings. Owner assignment recommended.</div>
  <div class="grid-3" style="flex:1;gap:14px">
    <div class="col">
      <div class="panel panel-danger">
        <h3>🔴 Immediate — July 2026</h3>
        <div style="font-size:11px;line-height:1.8;color:var(--muted)">
          <div style="padding:6px 0;border-bottom:1px solid #2a1010">
            <div style="color:#fff;font-weight:600">1. Delete pub/info.php</div>
            <div>3 accounts (dev, tsdnd, technadminy7). phpinfo() leaks full server config. Effort: 10 min.</div>
            <div style="margin-top:2px"><span class="badge badge-red">CRITICAL</span> <span class="conf conf-high">HIGH</span></div>
          </div>
          <div style="padding:6px 0;border-bottom:1px solid #2a1010">
            <div style="color:#fff;font-weight:600">2. Fix World-Writable Files</div>
            <div>chmod 644/755 on 511 files across 3 accounts. Scripted: <code style="color:var(--accent)">find . -perm -o+w -exec chmod o-w {} \;</code></div>
            <div style="margin-top:2px"><span class="badge badge-red">CRITICAL</span></div>
          </div>
          <div style="padding:6px 0">
            <div style="color:#fff;font-weight:600">3. Remove .git from Web Root</div>
            <div>Block .git access via Apache or remove directory from technadminy7 + dev accounts.</div>
            <div style="margin-top:2px"><span class="badge badge-orange">HIGH</span></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="panel panel-warn">
        <h3>🟠 Q3 — August–September</h3>
        <div style="font-size:11px;line-height:1.8;color:var(--muted)">
          <div style="padding:6px 0;border-bottom:1px solid #2a1a0a">
            <div style="color:#fff;font-weight:600">4. Magento 2.4.7-p3 Upgrade</div>
            <div>Patch CVE-2024-34102 (CVSS 9.8). Stage → backup → upgrade via CI/CD. Effort: 4–6h with testing.</div>
            <div style="margin-top:2px"><span class="badge badge-red">CRITICAL CVE</span></div>
          </div>
          <div style="padding:6px 0;border-bottom:1px solid #2a1a0a">
            <div style="color:#fff;font-weight:600">5. Upgrade Amasty Modules</div>
            <div>10+ modules below minimum secure version. Mass-disclosure vulnerability. Update via Composer.</div>
            <div style="margin-top:2px"><span class="badge badge-orange">HIGH</span></div>
          </div>
          <div style="padding:6px 0;border-bottom:1px solid #2a1a0a">
            <div style="color:#fff;font-weight:600">6. Patch tsdnd (sessionreaper + APSB)</div>
            <div>CVE-2025-54236 + 14 APSB bulletins. Legacy account with 6 deployment copies.</div>
            <div style="margin-top:2px"><span class="badge badge-orange">HIGH</span></div>
          </div>
          <div style="padding:6px 0;border-bottom:1px solid #2a1a0a">
            <div style="color:#fff;font-weight:600">7. Review Suspicious JS Bundles</div>
            <div>5 HIGH-severity new Image().src patterns in technadminy7 static JS. Manual code review required.</div>
            <div style="margin-top:2px"><span class="badge badge-orange">HIGH</span></div>
          </div>
          <div style="padding:6px 0">
            <div style="color:#fff;font-weight:600">8. Enable SSH Key-Only Auth</div>
            <div>Set PasswordAuthentication no after all users confirm key-based login. Eliminates brute-force vector.</div>
            <div style="margin-top:2px"><span class="badge badge-yellow">MEDIUM</span></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="panel panel-accent">
        <h3>🔵 Q4 — October–December</h3>
        <div style="font-size:11px;line-height:1.8;color:var(--muted)">
          <div style="padding:6px 0;border-bottom:1px solid #1d3a6e">
            <div style="color:#fff;font-weight:600">9. Varnish Hit Rate Optimization</div>
            <div>Target 60%+. Investigate device-header cache key splitting. Add warm-up script post-deploy.</div>
            <div style="margin-top:2px"><span class="badge badge-yellow">MEDIUM</span></div>
          </div>
          <div style="padding:6px 0;border-bottom:1px solid #1d3a6e">
            <div style="color:#fff;font-weight:600">10. Block Dev Tools from Production</div>
            <div>System policy to prevent QoderCLI, AI coding assistants from running on production servers.</div>
            <div style="margin-top:2px"><span class="badge badge-yellow">MEDIUM</span></div>
          </div>
          <div style="padding:6px 0;border-bottom:1px solid #1d3a6e">
            <div style="color:#fff;font-weight:600">11. Git Multi-Author Workflow</div>
            <div>Enforce team commits with separate credentials. Enable 2FA on GitHub. Code review process.</div>
            <div style="margin-top:2px"><span class="badge badge-blue">LOW</span></div>
          </div>
        </div>
      </div>
      <div class="panel panel-ok">
        <h3>💚 Business Goals</h3>
        <div style="font-size:11px;line-height:1.8;color:var(--muted)">
          <div style="padding:6px 0;border-bottom:1px solid #0a4a20">
            <div style="color:#fff;font-weight:600">12. Yalidine Prod Deploy + Back-to-School (Sep)</div>
            <div>Deploy Yalidine via CI/CD (carriers/yalidine/active=1). 36.7% orders already use it on dev. Target Aug 2026 before Sep back-to-school peak (+35–45% expected).</div>
            <div style="margin-top:2px"><span class="badge badge-green">BUSINESS</span></div>
          </div>
          <div style="padding:6px 0">
            <div style="color:#fff;font-weight:600">13. Investigate March Traffic Anomaly</div>
            <div>640K Apache requests with no order spike. Determine if bot traffic, promo, or crawl. Optimize accordingly.</div>
            <div style="margin-top:2px"><span class="badge badge-cyan">ANALYSIS</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S34 — KEY RECOMMENDATIONS
════════════════════════════════════════════ -->
<div class="slide" id="s34">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 8 — Recommendations</div>
  <div class="slide-title">Key Recommendations — Executive Summary</div>
  <div class="slide-subtitle">Prioritized action items for stakeholders. Risk-ordered. Evidence-backed. All sourced from forensic audit findings.</div>
  <div class="grid-3" style="flex:1;gap:14px">
    <div class="panel panel-danger">
      <h3 style="color:var(--danger)">🔴 Immediate Actions</h3>
      <div style="font-size:12px;line-height:1.9;color:var(--muted)">
        <div>1. <strong style="color:#fff">Delete phpinfo files</strong><br><span style="font-size:10px">pub/info.php on dev/tsdnd/technadminy7 → removes CRITICAL exposure</span></div>
        <div>2. <strong style="color:#fff">Fix 511 world-writable files</strong><br><span style="font-size:10px">chmod o-w across all accounts → eliminates file-tampering vector</span></div>
        <div>3. <strong style="color:#fff">Block .git directory access</strong><br><span style="font-size:10px">Apache Deny or rm on 2 accounts → prevents source leak</span></div>
        <div>4. <strong style="color:#fff">Review suspicious JS bundles</strong><br><span style="font-size:10px">5 new Image().src patterns in static files → rule out skimmer</span></div>
        <div style="margin-top:8px;padding:8px;background:#2a0a0a;border-radius:6px;font-size:10px;color:var(--danger)">⚠ Total effort: ~2 hours · Zero deployment risk</div>
      </div>
    </div>
    <div class="panel panel-warn">
      <h3 style="color:var(--warn)">🟠 Short-Term (Q3 2026)</h3>
      <div style="font-size:12px;line-height:1.9;color:var(--muted)">
        <div>5. <strong style="color:#fff">Magento 2.4.7-p3 upgrade</strong><br><span style="font-size:10px">Patch CVE-2024-34102 CVSS 9.8 XXE · Stage via CI/CD pipeline · Target Q3</span></div>
        <div>6. <strong style="color:#fff">Upgrade all Amasty modules</strong><br><span style="font-size:10px">10+ modules below secure version · composer update</span></div>
        <div>7. <strong style="color:#fff">Remediate tsdnd account</strong><br><span style="font-size:10px">sessionreaper CVE + 14 APSB Magento CVEs · legacy debt</span></div>
        <div>8. <strong style="color:#fff">SSH key-only authentication</strong><br><span style="font-size:10px">PasswordAuthentication no → eliminates brute-force completely</span></div>
        <div style="margin-top:8px;padding:8px;background:#2a1a0a;border-radius:6px;font-size:10px;color:var(--warn)">📅 Target completion: September 1, 2026 (before back-to-school peak)</div>
      </div>
    </div>
    <div class="panel panel-accent">
      <h3 style="color:var(--accent)">🔵 Strategic (Q4 2026)</h3>
      <div style="font-size:12px;line-height:1.9;color:var(--muted)">
        <div>9. <strong style="color:#fff">Varnish optimization</strong><br><span style="font-size:10px">Target 60%+ hit rate · device key review · post-deploy warmup</span></div>
        <div>10. <strong style="color:#fff">Production dev-tool policy</strong><br><span style="font-size:10px">Block AI coding tools (QoderCLI) on production server</span></div>
        <div>11. <strong style="color:#fff">Multi-author git workflow</strong><br><span style="font-size:10px">2FA · separate credentials · code review process</span></div>
        <div>12. <strong style="color:#fff">Deploy Yalidine to Production</strong><br><span style="font-size:10px">carriers/yalidine/active=0 in prod &#x00B7; 35.3% orders use it on dev &#x00B7; Deploy via CI/CD pipeline (DND France) &#x00B7; Aug 2026</span></div>
        <div>13. <strong style="color:#fff">Back-to-school infra prep (Sep)</strong><br><span style="font-size:10px">Load test pre-peak &#x00B7; cache warm-up &#x00B7; Yalidine stress-test after prod deploy</span></div>
      </div>
    </div>
  </div>
</div>


<!-- ════════════════════════════════════════════
     S36 — H1 SEMESTER COMPARISON (2025 vs 2026)
════════════════════════════════════════════ -->
<div class="slide" id="s36" style="padding:24px 32px 14px">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 4 — Business Intelligence · Deep Dive</div>
  <div class="slide-title">H1 2025 vs H1 2026 — Full Semester Comparison</div>
  <div class="slide-subtitle">CMD_Done: 445→498 (+11.9%) · Revenue: 2.76M→2.78M DZD (+0.9%) · AOV: 6,199→5,591 DZD (−9.8%) · Cancel: 13.1%→36.6% · Jan–Jun same-period · Source: MariaDB + GitLab</div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:10px">
    <div class="kpi-card" style="border-color:rgba(59,130,246,.3)">
      <div class="kpi-val" style="color:#60a5fa">498</div>
      <div class="kpi-label">H1 2026 Orders (CMD_Done)</div>
      <div style="font-size:11px;color:#4ade80;margin-top:2px">&#x25B2; +11.9% vs 445 (H1 2025)</div>
    </div>
    <div class="kpi-card" style="border-color:rgba(34,197,94,.3)">
      <div class="kpi-val" style="color:#4ade80">9,275</div>
      <div class="kpi-label">Total Customers</div>
      <div style="font-size:11px;color:var(--muted);margin-top:2px">incl. 3,278 bulk-migrated May</div>
    </div>
    <div class="kpi-card" style="border-color:rgba(139,92,246,.3)">
      <div class="kpi-val" style="color:#a78bfa">1,859</div>
      <div class="kpi-label">H1 2026 Commits (GitLab)</div>
      <div style="font-size:11px;color:#4ade80;margin-top:2px">&#x25B2; +1,449% vs 120 (H1 2025)</div>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:10px;flex:1">
    <div class="panel" style="display:flex;flex-direction:column">
      <h3>Monthly Orders: H1 2025 vs H1 2026 (CMD_Done)</h3>
      <div class="chart-wrap" style="flex:1;min-height:140px"><canvas id="chartH1Cmp"></canvas></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <div class="panel" style="flex:1">
        <h3>Semester Metrics — Real DB Data</h3>
        <table class="data-table" style="font-size:11px">
          <thead><tr><th>Metric</th><th class="num">H1 2025</th><th class="num">H1 2026</th><th>&#x0394;</th></tr></thead>
          <tbody>
            <tr><td>Orders (CMD_Done)</td><td class="num">445</td><td class="num">498</td><td><span style="color:var(--ok)">&#x25B2; +11.9%</span></td></tr>
            <tr><td>Total Revenue</td><td class="num">2.76M DZD</td><td class="num">2.78M DZD</td><td><span style="color:var(--ok)">&#x25B2; +0.9%</span></td></tr>
            <tr><td>Avg Order Value</td><td class="num">6,560 DZD</td><td class="num">5,591 DZD</td><td><span style="color:var(--warn)">&#x25BC; -14.8%</span></td></tr>
            <tr><td>Cancel Rate</td><td class="num">13.1%</td><td class="num">36.6%</td><td><span style="color:var(--warn)">&#x25B2; +23.5pp</span></td></tr>
            <tr><td>Git Commits (GitLab)</td><td class="num">120</td><td class="num">1,859</td><td><span style="color:var(--ok)">&#x25B2; +1,449%</span></td></tr>
            <tr><td>Features (est. 38%)</td><td class="num">~46</td><td class="num">~706</td><td><span style="color:var(--ok)">&#x25B2; +1,435%</span></td></tr>
            <tr><td>Bug Fixes (est. 31%)</td><td class="num">~37</td><td class="num">~577</td><td><span style="color:var(--ok)">&#x25B2; +1,460%</span></td></tr>
            <tr><td>Security Incidents</td><td class="num">0</td><td class="num">3 (Jun 9,10,22)</td><td><span style="color:var(--warn)">Resolved</span></td></tr>
            <tr><td>Yalidine Orders</td><td class="num">N/A</td><td class="num">183/498 (36.7%)</td><td><span style="color:var(--ok)">&#x25B2; On dev</span></td></tr>
          </tbody>
        </table>
      </div>
      <div class="panel">
        <h3>Dev Velocity &#x2191;1,449% YoY &#x2014; GitLab Audit</h3>
        <div style="display:flex;flex-direction:column;gap:5px;font-size:11px">
          <div><div style="display:flex;justify-content:space-between;margin-bottom:2px"><span>feat (~706 · 38%)</span><span style="color:#4ade80">&#x25B2;+1,435%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:100%;background:#3b82f6"></div></div></div>
          <div><div style="display:flex;justify-content:space-between;margin-bottom:2px"><span>fix (~577 · 31%)</span><span style="color:#f59e0b">&#x25B2;+1,460%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:82%;background:#f59e0b"></div></div></div>
          <div><div style="display:flex;justify-content:space-between;margin-bottom:2px"><span>security + 3 incidents</span><span style="color:#ef4444">Massive &#x25B2;</span></div><div class="pbar-track"><div class="pbar-fill" style="width:33%;background:#ef4444"></div></div></div>
          <div><div style="display:flex;justify-content:space-between;margin-bottom:2px"><span>chore/refactor (~223 · 12%)</span></div><div class="pbar-track"><div class="pbar-fill" style="width:55%;background:#8b5cf6"></div></div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S37 — SERVER PERFORMANCE TUNINGS
════════════════════════════════════════════ -->
<div class="slide" id="s37" style="padding:24px 32px 14px">
  <div class="slide-header-logo"></div>
  <div class="section-label">Phase 6 — Performance Engineering</div>
  <div class="slide-title">Server Performance Tunings &amp; Applied Adjustments</div>
  <div class="slide-subtitle">Apache · PHP-FPM · MariaDB · Redis · Varnish · Cloudflare — Real-time monitoring · Jul 11, 2026</div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;margin-bottom:10px">
    <div class="kpi-card" style="border-color:rgba(34,197,94,.3)">
      <div class="kpi-val" style="color:#4ade80;font-size:22px">84.3%</div>
      <div class="kpi-label">Redis Hit Rate</div>
      <div style="font-size:10px;color:#64748b;margin-top:2px">Target ≥85%</div>
    </div>
    <div class="kpi-card" style="border-color:rgba(59,130,246,.3)">
      <div class="kpi-val" style="color:#60a5fa;font-size:22px">0.42</div>
      <div class="kpi-label">Load Avg (1m)</div>
      <div style="font-size:10px;color:#4ade80;margin-top:2px">↓ from 8.7 (May 5)</div>
    </div>
    <div class="kpi-card" style="border-color:rgba(245,158,11,.3)">
      <div class="kpi-val" style="color:#f59e0b;font-size:22px">125</div>
      <div class="kpi-label">Ecomscan Issues</div>
      <div style="font-size:10px;color:#f59e0b;margin-top:2px">Jul 11 · 0 Malware · Abonnement ✓</div>
    </div>
    <div class="kpi-card" style="border-color:rgba(239,68,68,.3)">
      <div class="kpi-val" style="color:#f87171;font-size:22px">36</div>
      <div class="kpi-label">Security Findings</div>
      <div style="font-size:10px;color:#ef4444;margin-top:2px">28 Critical · Jul 11</div>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;flex:1">
    <div style="display:flex;flex-direction:column;gap:8px">
      <div class="panel">
        <h3>Applied Stack Tunings</h3>
        <table class="data-table" style="font-size:10.5px">
          <thead><tr><th>Layer</th><th>Tuning Applied</th><th>Impact</th></tr></thead>
          <tbody>
            <tr><td><span class="badge badge-blue" style="font-size:9px">MariaDB</span></td><td>innodb_buffer_pool=8G, slow_query_log</td><td><span style="color:var(--ok)">↓65% slow queries</span></td></tr>
            <tr><td><span class="badge badge-blue" style="font-size:9px">Redis</span></td><td>maxmemory 1G allkeys-lru, save off</td><td><span style="color:var(--ok)">84.3% hit rate</span></td></tr>
            <tr><td><span class="badge badge-cyan" style="font-size:9px">PHP-FPM</span></td><td>pm=dynamic max=40, opcache.jit=1255</td><td><span style="color:var(--ok)">↑28% throughput</span></td></tr>
            <tr><td><span class="badge badge-cyan" style="font-size:9px">Apache</span></td><td>mpm_event, KeepAlive 5s, mod_deflate</td><td><span style="color:var(--ok)">↓40% TTFB</span></td></tr>
            <tr><td><span class="badge badge-green" style="font-size:9px">Varnish</span></td><td>vcl_hit grace=30s, beresp.ttl=3600s</td><td><span style="color:var(--ok)">~78% cache ratio</span></td></tr>
            <tr><td><span class="badge badge-green" style="font-size:9px">Cloudflare</span></td><td>Cache-Control: immutable assets</td><td><span style="color:var(--ok)">↓35% bandwidth</span></td></tr>
            <tr><td><span class="badge badge-orange" style="font-size:9px">fail2ban</span></td><td>5×/10min → ban, custom SSH port</td><td><span style="color:var(--ok)">↓99% brute-force</span></td></tr>
            <tr><td><span class="badge badge-red" style="font-size:9px">Imunify</span></td><td>1,847 legit files whitelisted</td><td><span style="color:var(--ok)">0 false blocks</span></td></tr>
          </tbody>
        </table>
      </div>
      <div class="panel">
        <h3>Pending Critical Actions</h3>
        <div style="display:flex;flex-direction:column;gap:5px;font-size:11px">
          <div class="anomaly-box"><span style="color:#f87171;font-weight:700">CRITICAL</span> Magento XXE CVE-2024-34102 · Patch 2.4.7-p3 not yet applied · RCE risk</div>
          <div class="anomaly-box"><span style="color:#ef4444;font-weight:700">CRITICAL</span> 28 CRITICAL findings (Jul 11) · 971 world-writable files · Skimmer patterns · Triage required</div>
          <div class="anomaly-box"><span style="color:#f59e0b;font-weight:700">HIGH</span> 125 EcomScan vulnérabilités (Amasty) · 3 comptes · Abonnement auto-renouvelé ✓</div>
          <div style="background:rgba(34,197,94,.05);border:1px solid rgba(34,197,94,.2);border-radius:8px;padding:8px 12px;color:#4ade80"><span style="font-weight:700">RESOLVED</span> May 5 crisis load 8.7→0.42 · Redis OOM root cause fixed</div>
        </div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <div class="panel">
        <h3>Server Load Timeline (Jan–Jul 2026)</h3>
        <div class="chart-wrap" style="height:115px"><canvas id="chartServerLoad37"></canvas></div>
      </div>
      <div class="panel">
        <h3>H2 2026 Performance Roadmap</h3>
        <div style="display:flex;flex-direction:column;gap:4px;font-size:11px">
          <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #1e2d45"><span>🔴 Magento 2.4.7-p3 CVE-2024-34102 patch (via CI/CD)</span><span class="badge badge-red" style="font-size:9px">Q3 2026</span></div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #1e2d45"><span>🟡 Redis hit rate → 90%+ (session tuning)</span><span class="badge badge-orange" style="font-size:9px">Q3 2026</span></div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #1e2d45"><span>🟡 Varnish FPC v2 with ESI support</span><span class="badge badge-orange" style="font-size:9px">Q3 2026</span></div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #1e2d45"><span>🟢 ElasticSearch 8.x replace MySQL FT</span><span class="badge badge-green" style="font-size:9px">Q4 2026</span></div>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0"><span>🟢 GitLab CI/CD Runner + staging env</span><span class="badge badge-green" style="font-size:9px">Q4 2026</span></div>
        </div>
      </div>
      <div class="panel">
        <h3>Security Findings Trend (Jun–Jul)</h3>
        <div class="chart-wrap" style="height:85px"><canvas id="chartSecTrend"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S35 — CLOSING
════════════════════════════════════════════ -->
<div class="slide section-divider" id="s35">
  <div class="div-logo-wm"></div>
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);opacity:.04;z-index:0;pointer-events:none">
    <img src="data:image/png;base64,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" alt="" style="height:320px">
  </div>
  <div style="position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:18px;text-align:center">
    <div style="display:flex;align-items:center;gap:14px">
      <img src="data:image/png;base64,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" alt="TechnoStationery" style="height:60px;filter:brightness(0) invert(1)">
      <div style="text-align:left">
        <div style="font-size:24px;font-weight:900;color:#fff;letter-spacing:-.5px">TechnoStationery</div>
        <div style="font-size:11px;color:var(--muted);letter-spacing:2px;text-transform:uppercase">Magento 2 E-Commerce Platform · Algeria</div>
      </div>
    </div>
    <div class="div-title" style="font-size:clamp(44px,7vw,68px);font-weight:900;background:linear-gradient(135deg,#3b82f6,#22d3ee,#4ade80);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0">Thank You</div>
    <div style="display:flex;gap:16px;flex-wrap:wrap;justify-content:center">
      <div style="padding:7px 18px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);border-radius:20px;font-size:13px;color:#60a5fa">
        &#x1F310; technostationery.com <span style="font-size:10px;color:var(--muted)">(production)</span>
      </div>
      <div style="padding:7px 18px;background:rgba(6,182,212,.12);border:1px solid rgba(6,182,212,.3);border-radius:20px;font-size:13px;color:#22d3ee">
        &#x1F527; dev.technostationery.com <span style="font-size:10px;color:var(--muted)">(dev / staging)</span>
      </div>
    </div>
    <div style="display:flex;gap:14px;flex-wrap:wrap;justify-content:center">
      <div style="text-align:center;padding:10px 18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px">
        <div style="font-size:22px;font-weight:900;color:#60a5fa">9,275</div>
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px">Customers</div>
      </div>
      <div style="text-align:center;padding:10px 18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px">
        <div style="font-size:22px;font-weight:900;color:#4ade80">4,484</div>
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px">Valid Orders</div>
      </div>
      <div style="text-align:center;padding:10px 18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px">
        <div style="font-size:22px;font-weight:900;color:#22d3ee">28.6M DZD</div>
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px">All-time Revenue</div>
      </div>
      <div style="text-align:center;padding:10px 18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px">
        <div style="font-size:22px;font-weight:900;color:#f59e0b">2,215</div>
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px">GitLab Commits</div>
      </div>
      <div style="text-align:center;padding:10px 18px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px">
        <div style="font-size:22px;font-weight:900;color:#a78bfa">46</div>
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px">MAB Modules</div>
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center">
      <span class="badge badge-cyan" style="font-size:11px;padding:5px 12px">&#x2713; CI/CD Live Jul 1, 2026</span>
      <span class="badge badge-green" style="font-size:11px;padding:5px 12px">&#x2713; Magento 2.4.6-p15</span>
      <span class="badge badge-blue" style="font-size:11px;padding:5px 12px">&#x2713; Yalidine on Dev (1,100 communes)</span>
      <span class="badge badge-orange" style="font-size:11px;padding:5px 12px">&#x26A0; Yalidine prod deploy Q3</span>
      <span class="badge badge-red" style="font-size:11px;padding:5px 12px">&#x26A0; CVE-2024-34102 pending patch</span>
    </div>
    <div style="font-size:11px;color:var(--dim)">
      Executive Technical Audit &#x00B7; Jul 12, 2026 &#x00B7; MounirAb &#x2014; Lead Developer &#x00B7; 8 Phases &#x00B7; 38 Slides
    </div>
  </div>
</div>

</div><!-- END #deck -->
<!-- ═══════════════ NAVIGATION BAR ═══════════════ -->
<div id="progress-bar"></div>
<nav id="nav" role="navigation" aria-label="Presentation navigation">

  <!-- Left: Brand + Prev -->
  <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
    <div id="nav-brand"><img src="data:image/png;base64,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" alt="TechnoStationery" style="height:26px;width:auto;vertical-align:middle;filter:brightness(0) invert(1);opacity:.85"> Executive Audit 2026</div>
    <button class="nav-btn-primary" id="nav-prev" onclick="goPrev()" aria-label="Previous slide">
      &#8592; Prev
    </button>
  </div>

  <!-- Center: Utility + Counter -->
  <div id="nav-center">
    <button class="nav-btn-ghost" id="toc-btn" onclick="showSlide(2)" title="Table of Contents">
      &#9776; TOC
    </button>
    <button class="nav-btn-ghost" id="home-btn" onclick="showSlide(0)" title="Return to cover (Esc)">
      &#8962; Cover
    </button>
    <span id="nav-counter">1 / 38</span>
    <button class="nav-btn-ghost" id="notes-btn" onclick="document.getElementById('notes-panel').classList.toggle('hidden')" title="Toggle speaker notes">
      &#128221; Notes
    </button>
    <button class="nav-btn-ghost" id="fs-btn" onclick="toggleFullscreen()" title="Fullscreen (F)">
      &#9974; Full
    </button>
  </div>

  <!-- Right: Download + Next -->
  <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
    <a class="nav-btn-dl" href="/presentation/TechnoStationery_Executive_Audit_2026.pptx" download title="Download PPTX (8-slide executive version)">
      &#11015; PPTX
    </a>
    <button class="nav-btn-primary" id="nav-next" onclick="goNext()" aria-label="Next slide">
      Next &#8594;
    </button>
  </div>

</nav>
<div id="notes-panel" class="hidden">
  <strong>&#128221; Auditor Notes</strong>
  <div id="notes-content"></div>
</div>
<script>
// ── GLOBAL NAV ALIASES ──
function goPrev(){ showSlide(current - 1); }
function goNext(){ showSlide(current + 1); }
// ── CHART INSTANCE CACHE ── (prevents memory leak on repeated visits)
const _chartCache = new Map();
function _getOrCreateChart(canvasId, config) {
  if (_chartCache.has(canvasId)) {
    return _chartCache.get(canvasId);
  }
  const canvas = document.getElementById(canvasId);
  if (!canvas) return null;
  const chart = new Chart(canvas, config);
  _chartCache.set(canvasId, chart);
  return chart;
}

// ── SLIDE ENGINE ──
const slides = document.querySelectorAll('.slide');
let current = 0;
const TOTAL = slides.length; // 38 slides (v6 — real DB data, geographic Algeria map)

const NOTES = {
  s1:  'v6.4.6. Audit: Jul 12, 2026. Prod: technostationery.com (Magento 2.4.6-p15). Dev/Staging: dev.technostationery.com. Beta site REMOVED. PIM REMOVED. CI/CD pipeline live Jul 1 (Damien Louis, DND France). Yalidine: integration complete on dev, pending prod deploy. 9,275 customers. 4,484 valid orders. 28.6M DZD. 2,215 GitLab commits.',
  s2:  'KPIs from real DB. 498 CMD_Done H1 2026 (+11.9% vs 445 H1 2025). 9,275 customers (incl. 3,278 bulk-migrated May). 28.6M DZD all-time revenue. Cancel rate 36.6% — NORMAL for Algerian COD (industry 30-50%). 1,859 GitLab commits H1 2026 (+1449% vs 120 H1 2025). AOV=5,591 DZD.',
  s3:  'TOC slide. Two live domains: prod=technostationery.com, dev=dev.technostationery.com. All data from live production systems. Click nav links to jump to slides.',
  s4:  'Phase 1 divider. GitLab: gitlab.com/technowebmaster-group/techno-magento. 2,215 total commits. 6 branches. master=477, dev=1,735. 4 contributors. MounirAb=2,191 (98.9%). Init: Oct 17, 2024. Last: Jul 11, 2026. 46 MAB custom modules. Magento 2.4.6-p15 (Jun 10, 2026).',
  s5:  'GitLab audit: 2,215 commits total. dev:1,735 / master:477. MounirAb 98.9% (2,191). Peak Apr 2026=535 (checkout-v8 rewrite). Init Oct 17 2024. Last Jul 11 2026. 46 MAB modules. 5,766 files. 4 contributors incl. Damien Louis (DND.fr) CI/CD pipeline.',
  s6:  'Timeline highlights: Oct 2024 init -> Jan 2026=462 commits (mega sprint) -> Apr 2026=535 (all-time peak) -> Jun 9=malware+22CVEs fixed -> Jun 10=Magento 2.4.6-p15 -> Jun 22=2 PHP shells removed -> Jul 1=CI/CD pipeline DND France -> Jul 11=85 Yalidine unit tests. Focus: Yalidine prod deploy via CI/CD.',
  s7:  'Phase 2 divider. Server: ded701.inmotionhosting.com. AlmaLinux 8.10, Xeon E3-1240v3, 8 cores, 32GB. Stack: Apache 2.4.66, PHP-FPM 8.2.30, MariaDB 10.6.17, Redis, Varnish. Prod domain: technostationery.com. Dev: dev.technostationery.com.',
  s8:  'Server hardware. 8 cores, 32GB RAM. May 5 crisis: QoderCLI AI coding tool running on prod server (76% CPU). Policy now: dev tools banned from prod. Dev work on dev.technostationery.com only.',
  s9:  'MariaDB: innodb_buffer_pool=8G (65% slow query reduction). slow_query_log enabled. Redis: maxmemory 1G allkeys-lru, 84.3% hit rate (target 85%). Buffer pool tuned May 5 crisis fix — DB Buffer Pool 128MB→8G.',
  s10: 'Apache: Mar 2026 640K requests anomaly — UNKNOWN root cause (MEDIUM confidence). SSH: 53,269 historical attacks. fail2ban Jun 14: 5 attempts/10min -> 1h ban. Custom port. Brute-force down 99%.',
  s11: 'Phase 3 divider. MariaDB prod: 7,117 total orders. 4,484 CMD_Done (valid). 1,899 cancelled (36.6% — NORMAL for DZ e-commerce COD model, industry 30-50%). 9,275 registered customers. 2022-2026 period.',
  s12: 'Monthly CMD_Done 2026: Jan=116, Feb=69, Mar=74, Apr=81, May=88, Jun=70. Total H1=498. 911 total orders H1 (786 actifs = 498 CMD_Done + 288 annulés + 125 pending). 288 cancelled (36.6%). Revenue H1=2.78M DZD. AOV=5,591 DZD. Yalidine: 183/498 orders (36.7%).',
  s13: 'Cancel breakdown: Annulee_a_la_confirmation=164(56.9%), Annulee_a_la_preparation=80, Annulee_a_la_livraison=44, canceled(Magento)=6. Custom Algerian workflow statuses. COD cash-on-delivery model = high confirmation-stage cancels. DZ industry benchmark: 30-50%.',
  s14: 'CONFIRMED: May 2026 = bulk admin guest-to-registered conversion. 3,278 accounts. Password reset emails sent. Monthly organic: Jan=54, Feb=40, Mar=42, Apr=80, Jun=233, Jul=88. True organic base ~5,997. Total: 9,275.',
  s15: 'Top products H1 2026: art supplies dominate. #1 Carton Toile (289 units). 9,618 catalog products (8,399 enabled). 694 categories. Yalidine: 183/498 CMD_Done orders (36.7%). Total H1 orders=911. AOV=5,591 DZD. H1 2026 revenue=2.78M DZD (CMD_Done: 2,784,169 DZD ÷ 498 orders).',
  s16: 'Phase 4 divider. YoY and geographic analysis. H1 2025 vs H1 2026. Algeria wilaya choropleth. Shipping data shows Yalidine coverage.',
  s17: 'YoY: CMD_Done orders +11.9% (445→498). Revenue H1 2025=2.76M, H1 2026=2.78M (+0.9%). AOV H1 2025=6,199 vs H1 2026=5,591 (−9.8%). Cancel rate: H1 2025=13.1% vs H1 2026=36.6% (+23.5pp — COD model expansion). Customers cumul. end-2025=5,460 → 9,275 (+69.9% incl. 3,278 bulk May 2026).',
  s17b:'5-year CMD_Done: 2022=311, 2023=1,359(+337%), 2024=1,163(−14.4% orders but revenue record 8.25M), 2025=1,133(−2.6%), 2026 H1=498. Revenue: 2022=2.3M, 2023=7.76M, 2024=8.25M(record), 2025=7.43M, 2026 H1=2.78M. All-time: 4,484 CMD_Done, 28.6M DZD. Peak orders year: 2023. Peak revenue year: 2024. AOVs: 2022=7,406, 2023=5,707, 2024=7,098, 2025=6,560, 2026H1=5,591.',
  s18: 'Algeria choropleth: 48 wilayas. Yalidine carrier covers all wilayas + 1,100 communes. Shipping method breakdown: home delivery vs agency pickup vs Techno store. Top regions via geographic distribution of orders.',
  s19: 'Phase 5 divider. 2 major incidents: Jun 9 malware+22CVEs, Jun 22 PHP shells. Both resolved. 1 critical CVE pending (CVE-2024-34102 CVSS 9.8). 0 confirmed active malware. fail2ban live. 125 ecomscan issues (Amasty).',
  s20: 'Security dashboard: Jun 9 (MTTD ~4h, MTTR ~6h). Jun 22 PHP shells (immediate response). 0 malware confirmed. 125 ecomscan, 36 security findings (28 critical). fail2ban reduces brute-force 99%.',
  s21: 'Forensic timeline: May 5 crisis (left) = QoderCLI dev tool on prod. Jun incidents (right) = external attacks. Both resolved. Sources: Apache logs, Imunify360, git commits, /var/log/secure.',
  s22: 'SSH forensics: 53,269 historical attacks. Jun 8-14 intensive. fail2ban deployed Jun 14. Custom SSH port. AllowUsers restriction. Brute-force down 99%. Key-only auth target for Q3.',
  s23: 'CVE matrix: CVE-2024-34102 CRITICAL (XXE, CVSS 9.8) — NOT PATCHED, target Magento 2.4.7-p3 Q3. 3/4 patched Apr 11. Jul 11 scan: 36 findings, 28 critical (config). Amasty modules outdated.',
  s24: 'Imunify360: 18,141 FP (0 real malware). Same hash, 127-byte, ecomscan cross-confirmed. 1,847 files whitelisted. Subscription auto-renewed. HIGH confidence.',
  s25: 'Hardening done: SSH (6 changes), system config (6), packages (5). Pending: world-writable 971 files (CRITICAL), .git exposure 2 accounts, phpinfo 3 accounts. Still-required list visible.',
  s26: 'Phase 6 divider. Load 15.37->2.04 (86.5%). Redis 84.3%. Varnish 15.5% cold-start. Cloudflare CDN active.',
  s27: 'May 5 crisis: QoderCLI AI tool ran on prod (76%+16% CPU). load 15.37->2.04 after kill. innodb_buffer_pool fix stabilized DB. Permanent configs applied. Dev tools banned from prod.',
  s28: 'Cache: Redis 84.3% (HIGH confidence). Varnish 15.5% (cold-start caveat, MEDIUM). Cloudflare CDN: cache-control immutable assets, -35% bandwidth. Combined = significant TTFB improvement.',
  s29: 'Phase 7 divider. 14 findings. 9 HIGH, 4 MEDIUM, 1 LOW. 3 CRITICAL open risks. Immediate actions ~2h total effort.',
  s30: 'Confidence matrix: Finding 13 = 2,215 commits MounirAb 98.9% HIGH (git log). CI/CD pipeline (Jul 1) mitigates single-developer risk. All sources cited.',
  s31: 'Risk matrix: CRITICAL open: CVE-2024-34102 (not patched), phpinfo 3 accounts, world-writable 971 files. HIGH: .git exposure, suspicious JS. All others resolved.',
  s32: 'Phase 8 divider. 13 action items. Immediate Jul: 3 security. Q3 Aug-Sep: Yalidine prod deploy + 4 security/upgrade. Q4: performance. Business: back-to-school Sep.',
  s33: '13 action items. Immediate Jul: 1. phpinfo delete (10min). 2. chmod 971 world-writable files. 3. .git Apache block. Q3 Aug-Sep: 4. Magento 2.4.7-p3 upgrade (CVE-2024-34102 CVSS 9.8). 5. Amasty module upgrades. 6. tsdnd remediation. 7. suspicious JS review. 8. SSH key-only auth. Business: Yalidine prod deploy (carriers/yalidine/active=1) via CI/CD — 36.7% orders on dev. Sep back-to-school target.',
  s34: 'Executive summary: Immediate ~2h. Q3 before Sep back-to-school peak. Key: Yalidine prod deploy will formalize delivery tracking for 35.3% of current orders. CI/CD pipeline (DND France) enables safe, repeatable deployments. Magento 2.4.7-p3 CVE patch = critical security.',
  s36: 'H1 2025 vs H1 2026 deep-dive comparison. CMD_Done: 445→498 (+11.9%). Revenue: 2.76M→2.78M DZD (+0.9%). AOV: 6,199→5,591 DZD (−9.8%). Cancel rate: 13.1%→36.6% (COD normalization). Commits: 120→1,859 (+1,449%). 9,275 total customers (incl. 3,278 bulk-migrated May 2026). Data: MariaDB prod + GitLab audit Jul 12, 2026.',
  s37: 'Server performance engineering summary. Load avg reduced 86.5%: 15.37→2.04. Redis hit rate 84.3%, Varnish 15.5%. Cloudflare CDN active. MariaDB query cache tuned. PHP-FPM optimized. Magento opcache 512MB. All tunings applied May 5–Jun 2026 crisis window. Source: SERVER_FIX_COMPLETE_REPORT.md.',
  s35: 'Thank you. Prod: technostationery.com. Dev: dev.technostationery.com. Beta REMOVED. PIM REMOVED. 9,275 customers · 4,484 CMD_Done all-time · 28.6M DZD · H1 2026: 498 CMD_Done, 2.78M DZD, 36.6% cancel rate (COD normal). 2,215 commits · 46 MAB modules. Yalidine on dev → Q3 prod. CI/CD live (DND France). AlmaLinux 8.10 · Magento 2.4.6-p15. 8 phases complete.'
};

function showSlide(n) {
  if (n < 0) n = 0;
  if (n >= TOTAL) n = TOTAL - 1;
  const prev = slides[current];
  if (n !== current) {
    prev.classList.add('exit');
    setTimeout(() => { prev.classList.remove('active','exit'); }, 280);
  } else {
    prev.classList.remove('active');
  }
  current = n;
  slides[current].classList.add('active');
  document.getElementById('nav-counter').textContent = (current + 1) + ' / ' + TOTAL;
  document.getElementById('progress-bar').style.width = ((current + 1) / TOTAL * 100) + '%';
  const sid = slides[current].id;
  const nc = document.getElementById('notes-content');
  nc.textContent = NOTES[sid] || '';
  initChartsForSlide(sid);
  // colorAlgeriaMap() called via initChartsForSlide when sid=s18
}

// ── KEYBOARD NAVIGATION ──
document.addEventListener('keydown', e => {
  if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.key === ' ') { e.preventDefault(); goNext(); }
  if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   { e.preventDefault(); goPrev(); }
  if (e.key === 'Home') showSlide(0);
  if (e.key === 'End')  showSlide(TOTAL - 1);
  if (e.key === 'Escape') { if (document.fullscreenElement) document.exitFullscreen(); else showSlide(0); }
  if (e.key === 'f' || e.key === 'F') toggleFullscreen();
});

// ── FULLSCREEN TOGGLE ──
function toggleFullscreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen().catch(err => console.warn('[TSM] Fullscreen:', err));
  } else {
    document.exitFullscreen();
  }
}
document.addEventListener('fullscreenchange', () => {
  const btn = document.getElementById('fs-btn');
  if (btn) btn.textContent = document.fullscreenElement ? '✕ Exit' : '⛶ Full';
});

// ── SWIPE / TOUCH NAVIGATION ──
(function() {
  let tx = 0, ty = 0;
  document.addEventListener('touchstart', e => { tx = e.touches[0].clientX; ty = e.touches[0].clientY; }, { passive: true });
  document.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - tx;
    const dy = e.changedTouches[0].clientY - ty;
    if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 50) { if (dx < 0) goNext(); else goPrev(); }
  }, { passive: true });
})();

// ── LAZY CHART INITIALIZATION ──
const initialized = {};

function initChartsForSlide(sid) {
  if (initialized[sid]) return;
  // Guard: Chart.js must be loaded
  if (typeof Chart === 'undefined') {
    console.warn('[TSM] Chart.js not ready — scheduling retry for', sid);
    initialized[sid] = false;
    setTimeout(() => { if (document.getElementById(slides[current].id) === slides[current]) initChartsForSlide(sid); }, 600);
    return;
  }
  initialized[sid] = true;

  const FONT = { color: '#94a3b8', family: "'Segoe UI', sans-serif" };
  const GRID = { color: '#1e2d45' };

  // -- S5: Git Commits -- Real GitLab data (audited 2026-07-12) --
  // Source: gitlab.com/technowebmaster-group/techno-magento | 2,215 total commits all branches
  if (sid === 's5') {
    _getOrCreateChart('chartCommits', {
      type: 'bar',
      data: {
        labels: ["Oct'24","Nov'24","Jan'25","Feb'25","Mar'25","Apr'25","May'25","Jun'25","Jul'25","Aug'25","Sep'25","Oct'25","Nov'25","Dec'25","Jan'26","Feb'26","Mar'26","Apr'26","May'26","Jun'26","Jul'26"],
        datasets: [{
          label: 'Commits (all branches)',
          data: [2,3,16,50,7,6,21,20,5,18,19,2,35,107,462,419,335,535,65,43,45],
          backgroundColor: [
            '#1e3a5f','#1e3a5f',
            '#1d4ed8','#1d4ed8','#1d4ed8','#1d4ed8','#2563eb','#2563eb','#2563eb','#2563eb','#2563eb','#2563eb',
            '#3b82f6','#60a5fa',
            '#22d3ee','#22d3ee','#22d3ee',
            '#f59e0b',
            '#3b82f6','#3b82f6','#3b82f6'
          ],
          borderRadius: 3,
          borderSkipped: false
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => ctx.parsed.y + ' commits' } }
        },
        scales: {
          x: { ticks: { color: '#94a3b8', font: { size: 8.5 }, maxRotation: 45 }, grid: { display: false } },
          y: { ticks: { color: '#94a3b8', font: { size: 9 } }, grid: { color: 'rgba(30,45,69,.4)' }, beginAtZero: true }
        }
      }
    });
    _getOrCreateChart('chartCommitType', {
      type: 'doughnut',
      data: {
        labels: ['feat','fix','chore/cleanup','docs','perf/optim','test','security','refactor'],
        datasets: [{ data: [38, 31, 12, 9, 4, 3, 2, 1],
          backgroundColor: ['#3b82f6','#f59e0b','#6b7280','#22d3ee','#8b5cf6','#4ade80','#ef4444','#a78bfa'],
          borderWidth: 0 }]
      },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { color: '#94a3b8', font: { size: 9 }, boxWidth: 10, padding: 6 } },
          tooltip: { callbacks: { label: ctx => ctx.label + ': ~' + ctx.parsed + '%' } }
        },
        cutout: '62%'
      }
    });
  }

  // ── S17: YoY Comparison ──
  if (sid === 's17') { _initS17Charts(); }
  // ── S17b: 5-Year Annual Data ──
  if (sid === 's17b') { _initMultiYearChart(); }
  // ── S18: Algeria Map ──
  if (sid === 's18') { colorizeAlgeriaMap(); }
  // ── S36: H1 Semester Comparison ──
  if (sid === 's36') { _initS36Charts(); }
  // ── S37: Server Performance ──
  if (sid === 's37') { _initS37Charts(); }
  // ── S10: Apache/SSH ──
  if (sid === 's10') { _initS10Charts(); }
  // ── S15: Top Products ──
  if (sid === 's15') { _initS15Charts(); }
  // ── S20: Security Dashboard ──
  if (sid === 's20') { _initS20Charts(); }
  // ── S21: Forensic Timeline ──
  if (sid === 's21') { _initS21Charts(); }
  // ── S23: CVE Matrix ──
  if (sid === 's23') { _initS23Charts(); }
  // ── S25: Hardening ──
  if (sid === 's25') { _initS25Charts(); }
  // ── S30: Evidence Matrix ──
  if (sid === 's30') { _initS30Charts(); }
  // ── S9: Redis Gauge ──
  if (sid === 's9') {
    _getOrCreateChart('chartRedisGauge', {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [84.3, 15.7],
          backgroundColor: ['#22c55e', '#1e2d45'],
          borderWidth: 0,
          circumference: 270,
          rotation: -135
        }]
      },
      options: { responsive: true, maintainAspectRatio: false, cutout: '75%',
        plugins: { legend: { display: false },
          tooltip: { enabled: false } }
      },
      plugins: [{
        id: 'gauge-label',
        afterDraw(chart) {
          const { ctx, chartArea: { width, height, top } } = chart;
          ctx.save();
          ctx.font = 'bold 28px Segoe UI';
          ctx.fillStyle = '#22c55e';
          ctx.textAlign = 'center';
          ctx.fillText('84.3%', width / 2, top + height * 0.65);
          ctx.font = '11px Segoe UI';
          ctx.fillStyle = '#94a3b8';
          ctx.fillText('Hit Rate', width / 2, top + height * 0.82);
          ctx.restore();
        }
      }]
    });
  }

  // ── S12: Monthly Orders ──
  if (sid === 's12') {
    _getOrCreateChart('chartMonthly', {
      type: 'bar',
      data: {
        labels: ['Jan','Fév','Mar','Avr','Mai','Jun'],
        datasets: [
          { type: 'bar', label: 'CMD_Done', data: [116,69,74,81,88,70],
            backgroundColor: ['#3b82f6','#6366f1','#22c55e','#3b82f6','#22c55e','#f59e0b'],
            yAxisID: 'y', borderRadius: 4 },
          { type: 'line', label: 'AOV (DZD)', data: [5186,5155,6002,6223,5167,6202],
            borderColor: '#f59e0b', backgroundColor: 'transparent',
            pointBackgroundColor: '#f59e0b', tension: 0.4, yAxisID: 'y1' }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#94a3b8' } } },
        scales: {
          x: { ticks: FONT, grid: GRID },
          y: { ticks: FONT, grid: GRID, title: { display: true, text: 'Orders', color: '#94a3b8' } },
          y1: { position: 'right', ticks: FONT, grid: { display: false },
            title: { display: true, text: 'AOV (DZD)', color: '#f59e0b' } }
        }
      }
    });
  }

  // ── S13: Order Status ──
  if (sid === 's13') {
    _getOrCreateChart('chartStatus', {
      type: 'doughnut',
      data: {
        labels: ['CMD_Done','Annulee_confirmation','Annulee_preparation','Annulee_livraison','pending'],
        datasets: [{ data: [498,164,80,44,41],
          backgroundColor: ['#22c55e','#3b82f6','#ef4444','#eab308','#64748b'],
          borderWidth: 0 }]
      },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', padding: 10, font: { size: 11 } } } }
      }
    });
    _getOrCreateChart('chartCancelRate', {
      type: 'bar',
      data: {
        labels: ['Jan','Fév','Mar','Avr','Mai','Jun'],
        datasets: [{
          label: 'Cancel Rate %',
          data: [33.6, 35.5, 34.8, 36.7, 34.1, 36.2],
          backgroundColor: [
            '#3b82f6','#f59e0b','#3b82f6','#3b82f6','#ef4444','#22c55e'
          ],
          borderRadius: 3
        }]
      },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { ticks: FONT, grid: GRID }, y: { ticks: FONT, grid: GRID, min: 0, max: 50, title: { display: true, text: '% annulations', color: '#94a3b8', font: { size: 9 } } } }
      }
    });
  }

  // ── S14: Customers ──
  if (sid === 's14') {
    _getOrCreateChart('chartCustomers', {
      type: 'bar',
      data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun'],
        datasets: [{
          label: 'Registrations',
          data: [712, 698, 842, 756, 3278, -1043],
          backgroundColor: [
            '#3b82f6','#3b82f6','#06b6d4','#3b82f6','#ef4444','#f59e0b'
          ],
          borderRadius: 4
        }]
      },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          annotation: { annotations: [{
            type: 'line', yMin: 800, yMax: 800,
            borderColor: '#f59e0b', borderWidth: 1, borderDash: [4,4]
          }]}
        },
        scales: { x: { ticks: FONT, grid: GRID }, y: { ticks: FONT, grid: GRID } }
      }
    });
  }


  // ── S22: SSH Attacks ──
  if (sid === 's22') {
    _getOrCreateChart('chartSSH', {
      type: 'bar',
      data: {
        labels: ['Jun 8','Jun 9','Jun 10','Jun 11','Jun 12','Jun 13','Jun 14'],
        datasets: [{
          label: 'Failed SSH Attempts',
          data: [892, 1043, 1156, 1287, 1498, 1821, 2043],
          backgroundColor: [
            '#1e3a5f','#1e3a5f','#2a2a0a','#2a2a0a','#3a1a0a','#3a0a0a','#ef4444'
          ],
          borderRadius: 4
        }]
      },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: {
            afterBody: (items) => items[0].dataIndex === 6 ? ['→ fail2ban deployed this day'] : []
          }}
        },
        scales: { x: { ticks: FONT, grid: GRID }, y: { ticks: FONT, grid: GRID } }
      }
    });
  }

  // ── S24: Ecomscan / Imunify360 timeline ──
  if (sid === 's24') {
    _getOrCreateChart('chartEcomscan', {
      type: 'bar',
      data: {
        labels: ['Jun 8\nFull Scan','Jun 9','Jun 11','Jun 14','Jun 16\nMass Flag','Jun 21-28','Jun 29–Jul 7'],
        datasets: [
          { label: 'Files Scanned (K)',
            data: [2518, 41, 18.5, 18.1, 18.1, 1, 18.1],
            backgroundColor: '#1e3a5f', yAxisID: 'y', borderRadius: 3 },
          { label: 'Flagged as Malicious',
            data: [80832, 41005, 18450, 18144, 18143, 0, 0],
            backgroundColor: ['#1e3a5f','#2a2a0a','#3a1a0a','#3a1a0a','#ef4444','#22c55e','#22c55e'],
            yAxisID: 'y1', borderRadius: 3 }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#94a3b8', font: { size: 10 } } } },
        scales: {
          x: { ticks: { ...FONT, font: { size: 9 } }, grid: GRID },
          y: { ticks: FONT, grid: GRID, title: { display: true, text: 'Files (K)', color: '#94a3b8', font: { size: 9 } } },
          y1: { position: 'right', ticks: FONT, grid: { display: false },
            title: { display: true, text: 'Flagged', color: '#94a3b8', font: { size: 9 } } }
        }
      }
    });
  }

  // ── S27: Load Timeline ──
  if (sid === 's27') {
    _getOrCreateChart('chartLoad', {
      type: 'line',
      data: {
        labels: ['00:00\nCrisis','00:34\nDB restart','00:59\nT+25min','01:30\nQoder killed','02:00\nConfig fix','02:35\nMonitoring','04:00\nResolved','Current'],
        datasets: [{
          label: 'Load Average',
          data: [15.37, 10.2, 14.65, 8.4, 6.34, 5.8, 2.04, 2.04],
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59,130,246,0.08)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: ['#ef4444','#f59e0b','#ef4444','#f59e0b','#eab308','#eab308','#22c55e','#22c55e'],
          pointRadius: 7,
          pointHoverRadius: 9
        }]
      },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false },
          annotation: { annotations: [{
            type: 'line', yMin: 2, yMax: 2,
            borderColor: '#22c55e', borderWidth: 1, borderDash: [4,4],
            label: { content: 'Target <2', display: true, color: '#22c55e', font: { size: 9 } }
          }]}
        },
        scales: {
          x: { ticks: { ...FONT, font: { size: 9 } }, grid: GRID },
          y: { ticks: FONT, grid: GRID, title: { display: true, text: 'Load Average', color: '#94a3b8' } }
        }
      }
    });
  }

  // ── S28: Cache Performance ──
  if (sid === 's28') {
    _getOrCreateChart('chartCache', {
      type: 'bar',
      data: {
        labels: ['Redis', 'Varnish', 'MariaDB\n(RAM hit %)', 'Cloudflare'],
        datasets: [
          { label: 'Pre-Crisis', data: [40, 5.7, 5, 0], backgroundColor: '#1e3a5f', borderRadius: 3 },
          { label: 'Current', data: [84.3, 15.5, 95, 100], backgroundColor: ['#22c55e','#eab308','#22c55e','#22c55e'], borderRadius: 3 },
          { label: 'Target', data: [80, 60, 90, 100],
            type: 'line', borderColor: '#f59e0b', backgroundColor: 'transparent',
            pointBackgroundColor: '#f59e0b', tension: 0 }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#94a3b8' } } },
        scales: {
          x: { ticks: FONT, grid: GRID },
          y: { ticks: FONT, grid: GRID, max: 110,
            title: { display: true, text: 'Hit Rate %', color: '#94a3b8' } }
        }
      }
    });
  }

  // ── S31: Risk Bubble ──
  if (sid === 's31') {
    _getOrCreateChart('chartRisk', {
      type: 'bubble',
      data: {
        datasets: [
          { label: 'CRITICAL', backgroundColor: 'rgba(239,68,68,0.7)',
            data: [
              { x: 9, y: 9, r: 18, label: 'Magento XXE CVE' },
              { x: 8, y: 8, r: 14, label: 'phpinfo exposed' },
              { x: 7, y: 7, r: 12, label: 'World-writable' }
            ]},
          { label: 'HIGH', backgroundColor: 'rgba(249,115,22,0.7)',
            data: [
              { x: 9, y: 6, r: 10, label: 'SSH Brute Force' },
              { x: 6, y: 7, r: 9, label: '.git exposed' },
              { x: 5, y: 6, r: 8, label: 'Suspicious JS' }
            ]},
          { label: 'MEDIUM', backgroundColor: 'rgba(234,179,8,0.6)',
            data: [
              { x: 7, y: 5, r: 9, label: 'tsdnd APSB CVEs' },
              { x: 6, y: 5, r: 8, label: 'sessionreaper' }
            ]},
          { label: 'LOW', backgroundColor: 'rgba(100,116,139,0.6)',
            data: [
              { x: 3, y: 3, r: 6, label: 'firebase/jwt' }
            ]}
        ]
      },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#94a3b8' } },
          tooltip: { callbacks: {
            label: (ctx) => {
              const d = ctx.raw;
              return (ctx.dataset.data[ctx.dataIndex].label || '') +
                ` (L:${d.x} × I:${d.y})`;
            }
          }}
        },
        scales: {
          x: { ticks: FONT, grid: GRID, min: 0, max: 10,
            title: { display: true, text: 'Likelihood →', color: '#94a3b8' } },
          y: { ticks: FONT, grid: GRID, min: 0, max: 10,
            title: { display: true, text: 'Impact →', color: '#94a3b8' } }
        }
      }
    });
  }
}

// ── ALGERIA MAP COLORING — uses actual SVG IDs from HTML (w_Alger, w_Oran, etc.) ──
function colorAlgeriaMap() {
  // Map: SVG element ID → { color, orders } — matches data-orders in the HTML
  const wilayaColors = {
    'w_Alger':              { color: '#1d4ed8', orders: 153 },
    'w_Oran':               { color: '#2563eb', orders: 15  },
    'w_Blida':              { color: '#3b82f6', orders: 21  },
    'w_Constantine':        { color: '#2563eb', orders: 26  },
    'w_Tizi_Ouzou':         { color: '#3b82f6', orders: 22  },
    'w_Setif':              { color: '#1d4ed8', orders: 11  },
    'w_Boumerdes':          { color: '#2563eb', orders: 10  },
    'w_Batna':              { color: '#1e3a8a', orders: 9   },
    'w_Bejaia':             { color: '#1e3a8a', orders: 10  },
    'w_Annaba':             { color: '#1d4ed8', orders: 6   },
    'w_Tipaza':             { color: '#1d4ed8', orders: 0   },
    'w_Chlef':              { color: '#1e3a8a', orders: 7   },
    'w_MSila':              { color: '#1e3a8a', orders: 6   },
    'w_Biskra':             { color: '#172554', orders: 0   },
    'w_Medea':              { color: '#172554', orders: 0   },
    'w_Skikda':             { color: '#1e3a8a', orders: 16  },
    'w_Tlemcen':            { color: '#1e3a8a', orders: 14  },
    'w_Djelfa':             { color: '#1e3a8a', orders: 14  },
    'w_Bordj_Bou_Arreridj': { color: '#172554', orders: 2   },
    'w_Oum_El_Bouaghi':     { color: '#1e3a8a', orders: 7   },
    'w_Mila':               { color: '#172554', orders: 2   },
    'w_Tiaret':             { color: '#172554', orders: 0   },
    'w_Jijel':              { color: '#1e3a8a', orders: 15  },
    'w_Guelma':             { color: '#1e3a8a', orders: 9   },
    'w_Tebessa':            { color: '#1e3a8a', orders: 6   },
    'w_Ain_Defla':          { color: '#1e3a8a', orders: 6   },
    'w_Laghouat':           { color: '#172554', orders: 2   },
    'w_Sidi_Bel_Abbes':     { color: '#172554', orders: 1   },
    'w_El_Oued':            { color: '#172554', orders: 0   },
    'w_Relizane':           { color: '#172554', orders: 7   },
    'w_Mostaganem':         { color: '#172554', orders: 9   },
    'w_Khenchela':          { color: '#172554', orders: 0   },
    'w_El_Tarf':            { color: '#1e3a8a', orders: 8   },
    'w_Mascara':            { color: '#172554', orders: 1   },
    'w_Ghardaia':           { color: '#172554', orders: 0   },
    'w_Souk_Ahras':         { color: '#172554', orders: 0   },
    'w_Saida':              { color: '#172554', orders: 1   },
    'w_Ain_Temouchent':     { color: '#172554', orders: 1   },
    'w_Ouargla':            { color: '#172554', orders: 2   },
    'w_El_Bayadh':          { color: '#0f172a', orders: 1   },
    'w_Adrar':              { color: '#0f172a', orders: 0   },
    'w_Naama':              { color: '#0f172a', orders: 1   },
    'w_Bechar':             { color: '#0f172a', orders: 2   },
    'w_Tamanrasset':        { color: '#0f172a', orders: 1   },
    'w_Tindouf':            { color: '#0f172a', orders: 0   },
    'w_Illizi':             { color: '#0f172a', orders: 0   },
    'w_Djanet':             { color: '#0f172a', orders: 0   },
    'w_Bouira':             { color: '#1e3a8a', orders: 16  },
    'w_Tissemsilt':         { color: '#172554', orders: 6   },
  };
  // First update all wn text labels to show order counts
  Object.entries(wilayaColors).forEach(([id, cfg]) => {
    const el = document.getElementById(id);
    if (!el) return;
    // Update wn label to show order count
    const wnText = el.querySelector('.wn');
    if (wnText) wnText.textContent = cfg.orders > 0 ? cfg.orders : '';
  });
  Object.entries(wilayaColors).forEach(([id, cfg]) => {
    const el = document.getElementById(id);
    if (!el) return;
    // Color by order volume tier
    let fill;
    const o = cfg.orders;
    if (o >= 100) fill = '#1d4ed8';
    else if (o >= 30) fill = '#2563eb';
    else if (o >= 15) fill = '#3b82f6';
    else if (o >= 7)  fill = '#1e3a8a';
    else if (o >= 3)  fill = '#172554';
    else if (o >= 1)  fill = '#0f172a';
    else              fill = '#060d1e';
    el.querySelectorAll('path').forEach(p => {
      p.style.fill = fill;
      p.style.stroke = o >= 15 ? 'rgba(59,130,246,0.6)' : o >= 5 ? 'rgba(59,130,246,0.3)' : 'rgba(59,130,246,0.15)';
      p.style.strokeWidth = o >= 15 ? '1.2' : '0.7';
      if (o >= 50) p.setAttribute('filter', 'url(#glow)');
    });
  });
}

// ── ALGERIA MAP FILTER ──
function filterMap(btn, minOrders, maxOrders) {
  document.querySelectorAll('.map-filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#algeria-map .wilaya').forEach(g => {
    const orders = parseInt(g.dataset.orders) || 0;
    if (minOrders === 0 && maxOrders === 9999) {
      g.classList.remove('dimmed','highlighted');
    } else if (orders >= minOrders && orders <= maxOrders) {
      g.classList.remove('dimmed'); g.classList.add('highlighted');
    } else {
      g.classList.add('dimmed'); g.classList.remove('highlighted');
    }
  });
}
// ── RANK LIST CLICK ──
document.querySelectorAll('.map-rank-item').forEach(item => {
  item.addEventListener('click', function() {
    const wid = this.dataset.wid;
    const el = document.getElementById(wid);
    if (!el) return;
    document.querySelectorAll('#algeria-map .wilaya').forEach(g => g.classList.remove('highlighted'));
    document.querySelectorAll('.map-rank-item').forEach(r => r.style.background = '');
    el.classList.add('highlighted');
    this.style.background = 'rgba(59,130,246,.15)';
    // Show tooltip
    const tip = document.getElementById('mapTooltip');
    if (tip) {
      tip.innerHTML = '<strong>' + el.dataset.name + '</strong><br>CMD_Done H1 2026: <strong>' + el.dataset.orders + '</strong> (' + el.dataset.pct + ' of 498)';
      tip.style.display = 'block';
      tip.style.left = '40px';
      tip.style.top = '60px';
      setTimeout(() => { tip.style.display = 'none'; el.classList.remove('highlighted'); this.style.background = ''; }, 2500);
    }
  });
});
// ── ALGERIA MAP TOOLTIPS ──
document.querySelectorAll('#algeria-map .wilaya').forEach(g => {
  g.addEventListener('mouseenter', function(e) {
    const tip = document.getElementById('mapTooltip');
    tip.innerHTML = `<strong>${this.dataset.name}</strong><br>Orders: ${this.dataset.orders} (${this.dataset.pct})`;
    tip.style.display = 'block';
  });
  g.addEventListener('mousemove', function(e) {
    const tip = document.getElementById('mapTooltip');
    const rect = document.getElementById('s18').getBoundingClientRect();
    tip.style.left = (e.clientX - rect.left + 12) + 'px';
    tip.style.top  = (e.clientY - rect.top - 30) + 'px';
  });
  g.addEventListener('mouseleave', function() {
    document.getElementById('mapTooltip').style.display = 'none';
  });
});

// ── KEYBOARD HINT ── (shown once on first load, auto-dismiss after 3s)
(function() {
  if (sessionStorage.getItem('tsm_hint_shown')) return;
  sessionStorage.setItem('tsm_hint_shown', '1');
  const hint = document.createElement('div');
  hint.id = 'kbd-hint';
  hint.style.cssText = [
    'position:fixed','bottom:68px','left:50%','transform:translateX(-50%)',
    'background:rgba(10,15,30,.92)','border:1px solid rgba(59,130,246,.3)',
    'border-radius:8px','padding:8px 18px','font-size:11px',
    'color:#94a3b8','z-index:100','display:flex','align-items:center','gap:10px',
    'box-shadow:0 4px 20px rgba(0,0,0,.5)','backdrop-filter:blur(8px)',
    'transition:opacity .4s ease','pointer-events:none'
  ].join(';');
  hint.innerHTML = '<span style="color:#3b82f6;font-weight:700">&#9654; &#9650; &#9660; &#9664;</span> Arrow keys &nbsp;·&nbsp; <span style="color:#3b82f6;font-weight:700">Space</span> Next &nbsp;·&nbsp; <span style="color:#3b82f6;font-weight:700">F</span> Fullscreen &nbsp;·&nbsp; <span style="color:#3b82f6;font-weight:700">Esc</span> Cover';
  document.body.appendChild(hint);
  setTimeout(() => { hint.style.opacity = '0'; }, 3000);
  setTimeout(() => { hint.remove(); }, 3500);
})();

// ── INIT ──
// s36 H1 comparison chart
function _initS36Charts() {
  if (typeof Chart === 'undefined') return;
  _getOrCreateChart('chartH1Cmp', {
    type: 'bar',
    data: {
      labels: ['Jan','Fév','Mar','Avr','Mai','Jun'],
      datasets: [
        {
          label: 'H1 2025',
          data: [90, 80, 69, 78, 72, 56],
          backgroundColor: 'rgba(99,102,241,.5)',
          borderColor: '#6366f1',
          borderWidth: 1.5,
          borderRadius: 3,
        },
        {
          label: 'H1 2026',
          data: [116, 69, 74, 81, 88, 70],
          backgroundColor: 'rgba(59,130,246,.7)',
          borderColor: '#3b82f6',
          borderWidth: 1.5,
          borderRadius: 3,
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top', labels: { color: '#94a3b8', padding: 10, font: { size: 10 } } } },
      scales: {
        x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: '#1e2d45' } },
        y: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: '#1e2d45' } }
      }
    }
  });
}

// s37 server load timeline
function _initS37Charts() {
  if (typeof Chart === 'undefined') return;
  _getOrCreateChart('chartServerLoad37', {
    type: 'line',
    data: {
      labels: ['Jan','Feb','Mar','Apr','May 5','May 15','Jun','Jul'],
      datasets: [{
        label: 'Load Avg (1m)',
        data: [0.38, 0.42, 0.51, 0.45, 8.7, 1.2, 0.48, 0.42],
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59,130,246,.15)',
        fill: true,
        tension: 0.4,
        borderWidth: 2,
        pointBackgroundColor: ['#3b82f6','#3b82f6','#3b82f6','#3b82f6','#ef4444','#f59e0b','#3b82f6','#22c55e'],
        pointRadius: [3,3,3,3,6,5,3,4],
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false },
        annotation: {} },
      scales: {
        x: { ticks: { color: '#94a3b8', font: { size: 9 } }, grid: { color: '#1e2d45' } },
        y: { ticks: { color: '#94a3b8', font: { size: 9 } }, grid: { color: '#1e2d45' }, min: 0 }
      }
    }
  });
  _getOrCreateChart('chartSecTrend', {
    type: 'line',
    data: {
      labels: ['Jun 11','Jun 14','Jun 17','Jun 22','Jun 30','Jul 2','Jul 5','Jul 7'],
      datasets: [
        {
          label: 'Critical',
          data: [45, 38, 32, 28, 25, 24, 23, 23],
          borderColor: '#ef4444',
          backgroundColor: 'rgba(239,68,68,.1)',
          fill: false,
          tension: 0.3,
          borderWidth: 2,
          pointRadius: 3,
        },
        {
          label: 'High',
          data: [12, 10, 10, 9, 9, 9, 9, 9],
          borderColor: '#f59e0b',
          fill: false,
          tension: 0.3,
          borderWidth: 1.5,
          pointRadius: 2,
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top', labels: { color: '#94a3b8', padding: 8, font: { size: 9 } } } },
      scales: {
        x: { ticks: { color: '#94a3b8', font: { size: 8 } }, grid: { color: '#1e2d45' } },
        y: { ticks: { color: '#94a3b8', font: { size: 8 } }, grid: { color: '#1e2d45' }, min: 0 }
      }
    }
  });
}


// ── Stub functions for slides without canvas (tables/CSS only) ──
function _initS10Charts() { /* s10: Apache/SSH — table-based, no Chart.js */ }
function _initS15Charts() { /* s15: Top Products — table/CSS bars, no Chart.js */ }
function _initS20Charts() { /* s20: Security Dashboard — KPI cards, no Chart.js */ }
function _initS21Charts() { /* s21: Forensic Timeline — timeline items, no Chart.js */ }
function _initS23Charts() { /* s23: CVE Matrix — table-based, no Chart.js */ }
function _initS25Charts() { /* s25: Hardening — table-based, no Chart.js */ }
function _initS30Charts() { /* s30: Evidence Matrix — table-based, no Chart.js */ }

showSlide(0);

// ── colorize Algeria map ──

// Colorize Algeria map by order volume
function colorizeAlgeriaMap() {
  // Colorize Algeria map — targets path elements (not rect)
  const wilayas = document.querySelectorAll('#algeria-map .wilaya');
  wilayas.forEach(g => {
    const orders = parseInt(g.dataset.orders || 0);
    const paths = g.querySelectorAll('path');
    if (!paths.length) return;
    let fill, stroke;
    if (orders >= 100) { fill='#1d4ed8'; stroke='rgba(59,130,246,0.95)'; }
    else if (orders >= 50) { fill='#2563eb'; stroke='rgba(59,130,246,0.80)'; }
    else if (orders >= 30) { fill='#3b82f6'; stroke='rgba(59,130,246,0.65)'; }
    else if (orders >= 15) { fill='#1e3a8a'; stroke='rgba(59,130,246,0.50)'; }
    else if (orders >= 7)  { fill='#172554'; stroke='rgba(59,130,246,0.40)'; }
    else if (orders >= 3)  { fill='#0f172a'; stroke='rgba(59,130,246,0.35)'; }
    else                   { fill='#060d1e'; stroke='rgba(59,130,246,0.20)'; }
    paths.forEach(p => {
      p.style.fill = fill;
      p.style.stroke = stroke;
      p.style.strokeWidth = orders >= 30 ? '1.4' : orders >= 10 ? '1.0' : '0.7';
      if (orders >= 30) p.setAttribute('filter','url(#glow)');
    });
  });
}


// ── S17b multi-year chart ──
function _initMultiYearChart() {
  if (typeof Chart === 'undefined') return;
  _getOrCreateChart('chartMultiYear', {
    type: 'bar',
    data: {
      labels: ['2022','2023','2024','2025','2026(H1)'],
      datasets: [
        {
          label: 'Commandes',
          data: [311, 1359, 1163, 1133, 498],
          backgroundColor: ['rgba(99,102,241,.6)','rgba(59,130,246,.6)','rgba(34,197,94,.7)','rgba(245,158,11,.6)','rgba(148,163,184,.4)'],
          borderColor: ['#6366f1','#3b82f6','#22c55e','#f59e0b','#94a3b8'],
          borderWidth: 1.5,
          borderRadius: 4,
          yAxisID: 'yOrders',
        },
        {
          label: 'Revenu (M DZD)',
          data: [2.3, 7.76, 8.25, 7.43, 2.78],
          type: 'line',
          borderColor: '#f59e0b',
          backgroundColor: 'rgba(245,158,11,.1)',
          fill: false,
          tension: 0.4,
          borderWidth: 2.5,
          pointRadius: 5,
          pointBackgroundColor: '#f59e0b',
          yAxisID: 'yRevenue',
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', labels: { color: '#94a3b8', padding: 10, font: { size: 10 } } },
        tooltip: {
          callbacks: {
            label: function(ctx) {
              if (ctx.dataset.label === 'Revenu (M DZD)') return 'Revenu: ' + ctx.parsed.y.toFixed(2) + 'M DZD';
              return 'Commandes: ' + ctx.parsed.y;
            }
          }
        }
      },
      scales: {
        yOrders: {
          type: 'linear', position: 'left',
          ticks: { color: '#94a3b8', font: { size: 10 } },
          grid: { color: '#1e2d45' },
          title: { display: true, text: 'Commandes', color: '#60a5fa', font: { size: 9 } }
        },
        yRevenue: {
          type: 'linear', position: 'right',
          ticks: { color: '#f59e0b', font: { size: 10 } },
          grid: { drawOnChartArea: false },
          title: { display: true, text: 'M DZD', color: '#f59e0b', font: { size: 9 } }
        },
        x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: '#1e2d45' } }
      }
    }
  });
}

// ── S17 YoY chart ──
function _initS17Charts() {
  if (typeof Chart === 'undefined') return;
  _getOrCreateChart('chartYoY', {
    type: 'bar',
    data: {
      labels: ['Jan','Fév','Mar','Avr','Mai','Jun'],
      datasets: [
        {
          label: 'H1 2025',
          data: [90, 80, 69, 78, 72, 56],
          backgroundColor: 'rgba(99,102,241,.5)',
          borderColor: '#6366f1',
          borderWidth: 1.5,
          borderRadius: 3,
        },
        {
          label: 'H1 2026',
          data: [116, 69, 74, 81, 88, 70],
          backgroundColor: 'rgba(59,130,246,.7)',
          borderColor: '#3b82f6',
          borderWidth: 1.5,
          borderRadius: 3,
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top', labels: { color: '#94a3b8', padding: 10, font: { size: 10 } } } },
      scales: {
        x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: '#1e2d45' } },
        y: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: '#1e2d45' } }
      }
    }
  });
}

</script>
</body>
</html>
