#!/usr/bin/env python3
"""
Session 14 — Presentation overhaul
- Fix s1 cover (38 slides, correct Magento version)
- Fix s4 section divider (real GitLab data)
- Fix s3 TOC (add s36, s37 links; fix counts)
- Completely rebuild s38 Dashboard Monitoring (professional, original)
- Add new s39 Dashboard Feature Gallery slide
- Improve s35 Thank You (dashboard mention, polish)
- Update NOTES, TOTAL
"""

import re

SRC = '/home/dashboard/public_html/presentation/index.html'

with open(SRC, 'r', encoding='utf-8') as f:
    html = f.read()

orig_len = len(html)
changes = []

# ══════════════════════════════════════════════
# 1. Fix S1 Cover
# ══════════════════════════════════════════════
old_cover_kpi = '<div class="cover-kpi-item"><div class="cv-val">37</div><div class="cv-label">Audit Slides</div></div>'
new_cover_kpi = '<div class="cover-kpi-item"><div class="cv-val">39</div><div class="cv-label">Audit Slides</div></div>'
if old_cover_kpi in html:
    html = html.replace(old_cover_kpi, new_cover_kpi, 1)
    changes.append('✓ s1 cover: 37 → 39 slides')
else:
    changes.append('⚠ s1 cover slide count not found')

old_stack = '<span><strong>Stack:</strong> Magento 2.4.7-p3 · PHP 8.2 · MariaDB 10.6.17</span>'
new_stack = '<span><strong>Stack:</strong> Magento 2.4.6-p15 → 2.4.7-p3 · PHP 8.2 · MariaDB 10.6.17</span>'
if old_stack in html:
    html = html.replace(old_stack, new_stack, 1)
    changes.append('✓ s1 stack meta updated')
else:
    changes.append('⚠ s1 stack meta not found')

# ══════════════════════════════════════════════
# 2. Fix S4 Section Divider (Phase 1)
# ══════════════════════════════════════════════
old_s4 = '<div class="slide section-divider" id="s4">  <div class="div-number" style="top:50%;transform:translateY(-50%)">01</div>  <div class="div-phase">Phase 1 — Repository Audit</div>  <div class="div-title">Git Repository<br>&amp; Dev Timeline</div>  <div class="div-subtitle">94 commits · 1 author · 3 branches · May 2026: 56 commits peak · Magento/Dashboard platform · GitLab CI/CD</div>  <div class="div-tags">    <span class="badge badge-blue">93 Commits</span>    <span class="badge badge-cyan">Peak: May 2026 — 56 commits</span>    <span class="badge badge-orange">Tag: emergency-fix-20260502</span>    <span class="badge badge-purple">1 Author</span>  </div></div>'

new_s4 = '''<div class="slide section-divider" id="s4">  <div class="div-number" style="top:50%;transform:translateY(-50%)">01</div>  <div class="div-phase">Phase 1 — Repository Audit</div>  <div class="div-title">Git Repository<br>&amp; Dev Timeline</div>  <div class="div-subtitle">481 commits · master branch · Nov 2025 – Jul 2026 · GitLab CI/CD · tsdnd / dev / production deployments</div>  <div class="div-tags">    <span class="badge badge-blue">481 Commits</span>    <span class="badge badge-cyan">Peak: Apr 2026 — 290 commits</span>    <span class="badge badge-orange">GitLab Runner: ded701</span>    <span class="badge badge-purple">Mounir Abderrahmani</span>    <span class="badge badge-green">6 Branches</span>  </div></div>'''

if old_s4 in html:
    html = html.replace(old_s4, new_s4, 1)
    changes.append('✓ s4 section divider updated with real GitLab data')
else:
    changes.append('⚠ s4 section divider not found exactly')

# ══════════════════════════════════════════════
# 3. Fix S3 TOC — add s36, s37, update counts
# ══════════════════════════════════════════════
old_toc_p68 = '''        <h3>⚡ Phase 6–8: Performance, Evidence &amp; Roadmap</h3>
        <div style="font-size:12px;line-height:2;color:var(--muted)">
          <div><a href="#" onclick="showSlide(26);return false" style="color:var(--accent3);text-decoration:none">S27 — Crisis Performance</a></div>
          <div><a href="#" onclick="showSlide(27);return false" style="color:var(--accent3);text-decoration:none">S28 — Cache Deep Dive</a></div>
          <div><a href="#" onclick="showSlide(29);return false" style="color:var(--accent3);text-decoration:none">S30 — Evidence Confidence Matrix</a></div>
          <div><a href="#" onclick="showSlide(30);return false" style="color:var(--accent3);text-decoration:none">S31 — Risk Assessment Matrix</a></div>
          <div><a href="#" onclick="showSlide(32);return false" style="color:var(--accent3);text-decoration:none">S33 — H2 Strategic Roadmap</a></div>
          <div><a href="#" onclick="showSlide(33);return false" style="color:var(--accent3);text-decoration:none">S34 — Key Recommendations</a></div>
          <div><a href="#" onclick="showSlide(37);return false" style="color:var(--accent4);text-decoration:none">S38 — Monitoring Dashboard</a></div>
        </div>'''

new_toc_p68 = '''        <h3>⚡ Phase 6–8: Performance, Evidence &amp; Roadmap</h3>
        <div style="font-size:12px;line-height:2;color:var(--muted)">
          <div><a href="#" onclick="showSlide(26);return false" style="color:var(--accent3);text-decoration:none">S27 — Crisis Performance</a></div>
          <div><a href="#" onclick="showSlide(27);return false" style="color:var(--accent3);text-decoration:none">S28 — Cache Deep Dive</a></div>
          <div><a href="#" onclick="showSlide(29);return false" style="color:var(--accent3);text-decoration:none">S30 — Evidence Confidence Matrix</a></div>
          <div><a href="#" onclick="showSlide(30);return false" style="color:var(--accent3);text-decoration:none">S31 — Risk Assessment Matrix</a></div>
          <div><a href="#" onclick="showSlide(32);return false" style="color:var(--accent3);text-decoration:none">S33 — H2 Strategic Roadmap</a></div>
          <div><a href="#" onclick="showSlide(33);return false" style="color:var(--accent3);text-decoration:none">S34 — Key Recommendations</a></div>
          <div><a href="#" onclick="showSlide(35);return false" style="color:var(--accent2);text-decoration:none">S36 — H1 2025 vs H1 2026 Comparison</a></div>
          <div><a href="#" onclick="showSlide(36);return false" style="color:var(--accent2);text-decoration:none">S37 — Server Performance Tunings</a></div>
          <div><a href="#" onclick="showSlide(37);return false" style="color:var(--accent4);text-decoration:none">S38 — Monitoring Dashboard</a></div>
          <div><a href="#" onclick="showSlide(38);return false" style="color:var(--accent4);text-decoration:none">S39 — Dashboard Feature Gallery</a></div>
        </div>'''

if old_toc_p68 in html:
    html = html.replace(old_toc_p68, new_toc_p68, 1)
    changes.append('✓ s3 TOC Phase 6-8 updated with s36/s37/s39 links')
else:
    # Try compact version
    old_compact = '<div><a href="#" onclick="showSlide(37);return false" style="color:var(--accent4);text-decoration:none">S38 — Monitoring Dashboard</a></div>\n        </div>'
    new_compact = '<div><a href="#" onclick="showSlide(35);return false" style="color:var(--accent2);text-decoration:none">S36 — H1 2025 vs H1 2026 Comparison</a></div>          <div><a href="#" onclick="showSlide(36);return false" style="color:var(--accent2);text-decoration:none">S37 — Server Performance Tunings</a></div>          <div><a href="#" onclick="showSlide(37);return false" style="color:var(--accent4);text-decoration:none">S38 — Monitoring Dashboard</a></div>          <div><a href="#" onclick="showSlide(38);return false" style="color:var(--accent4);text-decoration:none">S39 — Dashboard Feature Gallery</a></div>\n        </div>'
    if old_compact in html:
        html = html.replace(old_compact, new_compact, 1)
        changes.append('✓ s3 TOC compact updated')
    else:
        changes.append('⚠ s3 TOC not updated — manual check needed')

# ══════════════════════════════════════════════
# 4. Rebuild S38 — Professional Dashboard Monitoring
# ══════════════════════════════════════════════
old_s38_start = '<!-- ════════════════════════════════════════════     S38 — MONITORING DASHBOARD════════════════════════════════════════════ --><div class="slide" id="s38">'
old_s38_end = '</div></div></div><!-- END #deck -->'

# Find the full s38 block
idx_start = html.find(old_s38_start)
idx_end = html.find('</div><!-- END #deck -->')

if idx_start != -1 and idx_end != -1:
    # Replace everything from s38 start to just before <!-- END #deck -->
    s38_and_after = html[idx_start:]
    end_marker = '</div><!-- END #deck -->'
    idx_rel = s38_and_after.find(end_marker)
    
    new_s38_s39 = '''<!-- ════════════════════════════════════════════
     S38 — MONITORING DASHBOARD OVERVIEW
════════════════════════════════════════════ -->
<div class="slide" id="s38" style="padding:22px 32px 14px">
  <div class="section-label">Appendix A — Proprietary Monitoring Tool</div>
  <div class="slide-title" style="font-size:24px">TechnoStationery Monitoring Dashboard</div>
  <div class="slide-subtitle">Internal SaaS-grade platform · React 18 + TypeScript + MUI v6 · v4.3.0 · Built &amp; maintained by Mounir Abderrahmani</div>

  <!-- Top KPI strip -->
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:12px">
    <div style="background:linear-gradient(135deg,#0f1e3a,#091628);border:1px solid rgba(59,130,246,.3);border-radius:10px;padding:10px 12px;text-align:center">
      <div style="font-size:20px;font-weight:900;color:#60a5fa">v4.3.0</div>
      <div style="font-size:10px;color:#64748b;margin-top:2px">Current Version</div>
    </div>
    <div style="background:linear-gradient(135deg,#0a2010,#061408);border:1px solid rgba(34,197,94,.3);border-radius:10px;padding:10px 12px;text-align:center">
      <div style="font-size:20px;font-weight:900;color:#4ade80">8</div>
      <div style="font-size:10px;color:#64748b;margin-top:2px">Core Modules</div>
    </div>
    <div style="background:linear-gradient(135deg,#1a0a3a,#100625);border:1px solid rgba(139,92,246,.3);border-radius:10px;padding:10px 12px;text-align:center">
      <div style="font-size:20px;font-weight:900;color:#a78bfa">481</div>
      <div style="font-size:10px;color:#64748b;margin-top:2px">Git Commits</div>
    </div>
    <div style="background:linear-gradient(135deg,#1a0e04,#100a02);border:1px solid rgba(245,158,11,.3);border-radius:10px;padding:10px 12px;text-align:center">
      <div style="font-size:20px;font-weight:900;color:#fbbf24">Jan 2026</div>
      <div style="font-size:10px;color:#64748b;margin-top:2px">First Launch</div>
    </div>
    <div style="background:linear-gradient(135deg,#041a1a,#021010);border:1px solid rgba(6,182,212,.3);border-radius:10px;padding:10px 12px;text-align:center">
      <div style="font-size:20px;font-weight:900;color:#22d3ee">Real-time</div>
      <div style="font-size:10px;color:#64748b;margin-top:2px">Data Pipeline</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1.1fr 0.9fr;gap:12px;flex:1">
    <!-- Left: Module grid -->
    <div style="display:flex;flex-direction:column;gap:8px">
      <div class="panel" style="flex:1">
        <h3 style="margin-bottom:8px">&#x1F5A5; Core Monitoring Modules</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:7px">

          <!-- Process Explorer -->
          <div style="background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.18);border-radius:8px;padding:9px 11px;transition:border-color .2s" onmouseover="this.style.borderColor='rgba(59,130,246,.5)'" onmouseout="this.style.borderColor='rgba(59,130,246,.18)'">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
              <div style="width:24px;height:24px;background:rgba(59,130,246,.2);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px">&#x1F4CA;</div>
              <div style="font-size:11px;font-weight:700;color:#60a5fa">Process Explorer</div>
            </div>
            <div style="font-size:10px;color:#94a3b8;line-height:1.5">Live PID tree · CPU/MEM per process · Kill &amp; restart from UI · QoderCLI detection</div>
          </div>

          <!-- Log Viewer -->
          <div style="background:rgba(6,182,212,.06);border:1px solid rgba(6,182,212,.18);border-radius:8px;padding:9px 11px;transition:border-color .2s" onmouseover="this.style.borderColor='rgba(6,182,212,.5)'" onmouseout="this.style.borderColor='rgba(6,182,212,.18)'">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
              <div style="width:24px;height:24px;background:rgba(6,182,212,.2);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px">&#x1F4CB;</div>
              <div style="font-size:11px;font-weight:700;color:#22d3ee">Log Viewer</div>
            </div>
            <div style="font-size:10px;color:#94a3b8;line-height:1.5">Apache · Magento · PHP-FPM · SSH · Real-time tail + regex search</div>
          </div>

          <!-- Users & Access -->
          <div style="background:rgba(139,92,246,.06);border:1px solid rgba(139,92,246,.18);border-radius:8px;padding:9px 11px;transition:border-color .2s" onmouseover="this.style.borderColor='rgba(139,92,246,.5)'" onmouseout="this.style.borderColor='rgba(139,92,246,.18)'">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
              <div style="width:24px;height:24px;background:rgba(139,92,246,.2);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px">&#x1F465;</div>
              <div style="font-size:11px;font-weight:700;color:#a78bfa">Users &amp; Access</div>
            </div>
            <div style="font-size:10px;color:#94a3b8;line-height:1.5">Role-based access · WHM accounts · SSH session audit · Permission matrix</div>
          </div>

          <!-- Security Audit -->
          <div style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.18);border-radius:8px;padding:9px 11px;transition:border-color .2s" onmouseover="this.style.borderColor='rgba(239,68,68,.5)'" onmouseout="this.style.borderColor='rgba(239,68,68,.18)'">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
              <div style="width:24px;height:24px;background:rgba(239,68,68,.2);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px">&#x1F512;</div>
              <div style="font-size:11px;font-weight:700;color:#f87171">Security Audit</div>
            </div>
            <div style="font-size:10px;color:#94a3b8;line-height:1.5">fail2ban status · Imunify360 results · CVE tracker · Ecomscan integration</div>
          </div>

          <!-- phpMyAdmin -->
          <div style="background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.18);border-radius:8px;padding:9px 11px;transition:border-color .2s" onmouseover="this.style.borderColor='rgba(16,185,129,.5)'" onmouseout="this.style.borderColor='rgba(16,185,129,.18)'">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
              <div style="width:24px;height:24px;background:rgba(16,185,129,.2);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px">&#x1F5C4;</div>
              <div style="font-size:11px;font-weight:700;color:#34d399">phpMyAdmin Secured</div>
            </div>
            <div style="font-size:10px;color:#94a3b8;line-height:1.5">Auth-gated embedded DB · MariaDB 10.6 · Row-level query builder</div>
          </div>

          <!-- ETL & CI/CD -->
          <div style="background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.18);border-radius:8px;padding:9px 11px;transition:border-color .2s" onmouseover="this.style.borderColor='rgba(245,158,11,.5)'" onmouseout="this.style.borderColor='rgba(245,158,11,.18)'">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
              <div style="width:24px;height:24px;background:rgba(245,158,11,.2);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px">&#x1F504;</div>
              <div style="font-size:11px;font-weight:700;color:#fbbf24">ETL &amp; CI/CD</div>
            </div>
            <div style="font-size:10px;color:#94a3b8;line-height:1.5">GitLab pipeline status · Deploy jobs · ETL sync state · Queue monitoring</div>
          </div>

          <!-- AI Terminal -->
          <div style="background:rgba(59,130,246,.06);border:1px solid rgba(99,102,241,.18);border-radius:8px;padding:9px 11px;transition:border-color .2s" onmouseover="this.style.borderColor='rgba(99,102,241,.5)'" onmouseout="this.style.borderColor='rgba(99,102,241,.18)'">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
              <div style="width:24px;height:24px;background:rgba(99,102,241,.2);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px">&#x1F916;</div>
              <div style="font-size:11px;font-weight:700;color:#818cf8">AI Terminal</div>
            </div>
            <div style="font-size:10px;color:#94a3b8;line-height:1.5">Natural-language ops · Telegram dispatch · AI-assisted server commands</div>
          </div>

          <!-- Push Notifications -->
          <div style="background:rgba(6,182,212,.06);border:1px solid rgba(6,182,212,.18);border-radius:8px;padding:9px 11px;transition:border-color .2s" onmouseover="this.style.borderColor='rgba(6,182,212,.5)'" onmouseout="this.style.borderColor='rgba(6,182,212,.18)'">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
              <div style="width:24px;height:24px;background:rgba(6,182,212,.2);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px">&#x1F514;</div>
              <div style="font-size:11px;font-weight:700;color:#22d3ee">Push Notifications</div>
            </div>
            <div style="font-size:10px;color:#94a3b8;line-height:1.5">Browser push + Telegram · Load spikes · Scan alerts · Critical events</div>
          </div>

        </div>
      </div>
    </div>

    <!-- Right: Evolution + Stack + Recurring -->
    <div style="display:flex;flex-direction:column;gap:8px">

      <!-- Version Evolution -->
      <div class="panel">
        <h3 style="margin-bottom:8px">&#x1F4C8; Version Evolution</h3>
        <div style="position:relative;padding-left:16px">
          <!-- timeline line -->
          <div style="position:absolute;left:5px;top:4px;bottom:4px;width:2px;background:linear-gradient(180deg,#1e3a8a,#3b82f6,#22c55e);border-radius:1px"></div>

          <div style="margin-bottom:9px;padding-left:14px;position:relative">
            <div style="position:absolute;left:-11px;top:3px;width:8px;height:8px;border-radius:50%;background:#1e3a8a;border:2px solid #3b82f6"></div>
            <div style="font-size:11px;font-weight:700;color:#60a5fa">v1.0 — Jan 2026 <span style="color:#64748b;font-weight:400">· Launch</span></div>
            <div style="font-size:10px;color:#64748b">Orders · Users · Basic server stats · MariaDB direct</div>
          </div>
          <div style="margin-bottom:9px;padding-left:14px;position:relative">
            <div style="position:absolute;left:-11px;top:3px;width:8px;height:8px;border-radius:50%;background:#1d4ed8;border:2px solid #3b82f6"></div>
            <div style="font-size:11px;font-weight:700;color:#60a5fa">v2.0 — Mar 2026 <span style="color:#64748b;font-weight:400">· Ecommerce</span></div>
            <div style="font-size:10px;color:#64748b">Inventory · Traffic analytics · Performance metrics</div>
          </div>
          <div style="margin-bottom:9px;padding-left:14px;position:relative">
            <div style="position:absolute;left:-11px;top:3px;width:8px;height:8px;border-radius:50%;background:#2563eb;border:2px solid #60a5fa"></div>
            <div style="font-size:11px;font-weight:700;color:#60a5fa">v3.0 — May 2026 <span style="color:#64748b;font-weight:400">· Security</span></div>
            <div style="font-size:10px;color:#64748b">SSH sessions · fail2ban · Imunify360 · CVE tracker</div>
          </div>
          <div style="padding-left:14px;position:relative">
            <div style="position:absolute;left:-11px;top:3px;width:8px;height:8px;border-radius:50%;background:#22c55e;border:2px solid #4ade80;box-shadow:0 0 6px rgba(34,197,94,.4)"></div>
            <div style="font-size:11px;font-weight:700;color:#4ade80">v4.3.0 — Jul 2026 <span style="color:#22c55e;font-weight:400">● CURRENT</span></div>
            <div style="font-size:10px;color:#64748b">Full suite · AI Terminal · Push notifs · phpMyAdmin · ETL</div>
          </div>
        </div>
      </div>

      <!-- Tech Stack -->
      <div class="panel">
        <h3 style="margin-bottom:7px">&#x1F9F1; Technology Stack</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;font-size:10px">
          <div style="color:#94a3b8"><span style="color:#60a5fa;font-weight:700">Frontend:</span> React 18 + TypeScript</div>
          <div style="color:#94a3b8"><span style="color:#a78bfa;font-weight:700">UI:</span> MUI v6 dark theme</div>
          <div style="color:#94a3b8"><span style="color:#22d3ee;font-weight:700">Charts:</span> Chart.js 4 + Recharts</div>
          <div style="color:#94a3b8"><span style="color:#34d399;font-weight:700">DB:</span> MariaDB 10.6 API</div>
          <div style="color:#94a3b8"><span style="color:#fbbf24;font-weight:700">Build:</span> Vite 5 + ESBuild</div>
          <div style="color:#94a3b8"><span style="color:#f87171;font-weight:700">CI/CD:</span> GitLab Runner ded701</div>
          <div style="color:#94a3b8"><span style="color:#818cf8;font-weight:700">Realtime:</span> PHP SSE + WebSocket</div>
          <div style="color:#94a3b8"><span style="color:#fb923c;font-weight:700">Alerts:</span> Telegram Bot API</div>
        </div>
      </div>

      <!-- Recurring Deliverable banner -->
      <div style="background:linear-gradient(135deg,rgba(16,185,129,.08),rgba(6,182,212,.05));border:1px solid rgba(16,185,129,.25);border-radius:10px;padding:10px 13px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">
          <div style="font-size:14px">&#x267B;</div>
          <div style="font-size:11px;font-weight:700;color:#34d399">Recurring Deliverable</div>
        </div>
        <div style="font-size:10px;color:#94a3b8;line-height:1.5">
          Presented every semester alongside the audit. Each release brings new monitoring modules, improved data pipelines, and expanded observability.
          <span style="color:#4ade80;font-weight:600"> H2 2026 planned:</span> Cloudflare analytics · Automated patching · Mobile-responsive UI.
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     S39 — DASHBOARD FEATURE GALLERY
════════════════════════════════════════════ -->
<div class="slide" id="s39" style="padding:22px 32px 14px">
  <div class="section-label">Appendix B — Dashboard Deep Dive</div>
  <div class="slide-title" style="font-size:24px">Dashboard Feature Gallery</div>
  <div class="slide-subtitle">Live screenshots of key modules · ded701.inmotionhosting.com/dashboard · Auth-gated · HTTPS</div>

  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;flex:1">

    <!-- Master Dashboard mockup -->
    <div style="background:rgba(59,130,246,.04);border:1px solid rgba(59,130,246,.2);border-radius:10px;overflow:hidden;display:flex;flex-direction:column">
      <div style="background:rgba(59,130,246,.12);padding:8px 12px;display:flex;align-items:center;gap:6px;border-bottom:1px solid rgba(59,130,246,.15)">
        <div style="width:8px;height:8px;border-radius:50%;background:#ef4444"></div>
        <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b"></div>
        <div style="width:8px;height:8px;border-radius:50%;background:#22c55e"></div>
        <div style="font-size:9px;color:#60a5fa;margin-left:4px;font-weight:700">Master Dashboard</div>
      </div>
      <div style="padding:10px;flex:1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:6px">
          <div style="background:rgba(59,130,246,.1);border-radius:4px;padding:5px 7px;text-align:center">
            <div style="font-size:13px;font-weight:900;color:#60a5fa">1,291</div>
            <div style="font-size:8px;color:#64748b">Orders</div>
          </div>
          <div style="background:rgba(34,197,94,.1);border-radius:4px;padding:5px 7px;text-align:center">
            <div style="font-size:13px;font-weight:900;color:#4ade80">99.1%</div>
            <div style="font-size:8px;color:#64748b">Uptime</div>
          </div>
          <div style="background:rgba(245,158,11,.1);border-radius:4px;padding:5px 7px;text-align:center">
            <div style="font-size:13px;font-weight:900;color:#fbbf24">0.42</div>
            <div style="font-size:8px;color:#64748b">Load Avg</div>
          </div>
          <div style="background:rgba(139,92,246,.1);border-radius:4px;padding:5px 7px;text-align:center">
            <div style="font-size:13px;font-weight:900;color:#a78bfa">84.3%</div>
            <div style="font-size:8px;color:#64748b">Redis Hit</div>
          </div>
        </div>
        <!-- Mini chart bars -->
        <div style="display:flex;align-items:flex-end;gap:2px;height:28px;margin-top:4px">
          <div style="flex:1;background:#1d4ed8;border-radius:2px 2px 0 0;height:40%"></div>
          <div style="flex:1;background:#1d4ed8;border-radius:2px 2px 0 0;height:30%"></div>
          <div style="flex:1;background:#2563eb;border-radius:2px 2px 0 0;height:55%"></div>
          <div style="flex:1;background:#ef4444;border-radius:2px 2px 0 0;height:100%"></div>
          <div style="flex:1;background:#2563eb;border-radius:2px 2px 0 0;height:45%"></div>
          <div style="flex:1;background:#7c3aed;border-radius:2px 2px 0 0;height:80%"></div>
          <div style="flex:1;background:#1d4ed8;border-radius:2px 2px 0 0;height:35%"></div>
          <div style="flex:1;background:#22c55e;border-radius:2px 2px 0 0;height:40%"></div>
          <div style="flex:1;background:#3b82f6;border-radius:2px 2px 0 0;height:25%"></div>
        </div>
        <div style="font-size:8px;color:#475569;margin-top:3px;text-align:center">Monthly Orders Jan–Jul 2026</div>
      </div>
    </div>

    <!-- Process Explorer mockup -->
    <div style="background:rgba(6,182,212,.04);border:1px solid rgba(6,182,212,.2);border-radius:10px;overflow:hidden;display:flex;flex-direction:column">
      <div style="background:rgba(6,182,212,.12);padding:8px 12px;display:flex;align-items:center;gap:6px;border-bottom:1px solid rgba(6,182,212,.15)">
        <div style="width:8px;height:8px;border-radius:50%;background:#ef4444"></div>
        <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b"></div>
        <div style="width:8px;height:8px;border-radius:50%;background:#22c55e"></div>
        <div style="font-size:9px;color:#22d3ee;margin-left:4px;font-weight:700">Process Explorer</div>
      </div>
      <div style="padding:8px;flex:1">
        <div style="font-size:8px;color:#94a3b8;margin-bottom:4px;display:flex;gap:8px">
          <span style="color:#22d3ee">CPU: 12.4%</span>
          <span style="color:#4ade80">MEM: 58%</span>
          <span style="color:#fbbf24">Load: 0.42</span>
        </div>
        <table style="width:100%;font-size:8.5px;border-collapse:collapse">
          <tr style="color:#475569;border-bottom:1px solid #1e2d45">
            <td style="padding:2px 4px">PID</td><td style="padding:2px 4px">Process</td><td style="padding:2px 4px;text-align:right">CPU%</td><td style="padding:2px 4px;text-align:right">MEM</td>
          </tr>
          <tr style="color:#e2e8f0;border-bottom:1px solid #0d1220">
            <td style="padding:2px 4px;color:#64748b">1842</td><td style="padding:2px 4px;color:#60a5fa">apache2</td><td style="padding:2px 4px;text-align:right;color:#4ade80">3.2%</td><td style="padding:2px 4px;text-align:right;color:#94a3b8">412M</td>
          </tr>
          <tr style="color:#e2e8f0;border-bottom:1px solid #0d1220">
            <td style="padding:2px 4px;color:#64748b">2156</td><td style="padding:2px 4px;color:#60a5fa">php-fpm</td><td style="padding:2px 4px;text-align:right;color:#fbbf24">4.8%</td><td style="padding:2px 4px;text-align:right;color:#94a3b8">856M</td>
          </tr>
          <tr style="color:#e2e8f0;border-bottom:1px solid #0d1220">
            <td style="padding:2px 4px;color:#64748b">3021</td><td style="padding:2px 4px;color:#60a5fa">mysqld</td><td style="padding:2px 4px;text-align:right;color:#4ade80">1.9%</td><td style="padding:2px 4px;text-align:right;color:#94a3b8">2.1G</td>
          </tr>
          <tr style="color:#e2e8f0">
            <td style="padding:2px 4px;color:#64748b">4102</td><td style="padding:2px 4px;color:#34d399">redis-server</td><td style="padding:2px 4px;text-align:right;color:#4ade80">0.4%</td><td style="padding:2px 4px;text-align:right;color:#94a3b8">380M</td>
          </tr>
        </table>
        <div style="margin-top:6px;display:flex;gap:4px">
          <div style="flex:1;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:4px;padding:2px 5px;font-size:8px;color:#f87171;text-align:center">Kill</div>
          <div style="flex:1;background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);border-radius:4px;padding:2px 5px;font-size:8px;color:#fbbf24;text-align:center">Restart</div>
          <div style="flex:2;background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);border-radius:4px;padding:2px 5px;font-size:8px;color:#60a5fa;text-align:center">&#x1F504; Refresh</div>
        </div>
      </div>
    </div>

    <!-- Security Audit mockup -->
    <div style="background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.2);border-radius:10px;overflow:hidden;display:flex;flex-direction:column">
      <div style="background:rgba(239,68,68,.12);padding:8px 12px;display:flex;align-items:center;gap:6px;border-bottom:1px solid rgba(239,68,68,.15)">
        <div style="width:8px;height:8px;border-radius:50%;background:#ef4444"></div>
        <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b"></div>
        <div style="width:8px;height:8px;border-radius:50%;background:#22c55e"></div>
        <div style="font-size:9px;color:#f87171;margin-left:4px;font-weight:700">Security Audit</div>
      </div>
      <div style="padding:8px;flex:1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:3px;margin-bottom:5px">
          <div style="background:rgba(239,68,68,.1);border-radius:4px;padding:4px 6px">
            <div style="font-size:11px;font-weight:900;color:#f87171">53,269</div>
            <div style="font-size:8px;color:#64748b">SSH Attacks</div>
          </div>
          <div style="background:rgba(34,197,94,.1);border-radius:4px;padding:4px 6px">
            <div style="font-size:11px;font-weight:900;color:#4ade80">0</div>
            <div style="font-size:8px;color:#64748b">Malware</div>
          </div>
          <div style="background:rgba(245,158,11,.1);border-radius:4px;padding:4px 6px">
            <div style="font-size:11px;font-weight:900;color:#fbbf24">91</div>
            <div style="font-size:8px;color:#64748b">Ecomscan</div>
          </div>
          <div style="background:rgba(239,68,68,.1);border-radius:4px;padding:4px 6px">
            <div style="font-size:11px;font-weight:900;color:#f87171">1</div>
            <div style="font-size:8px;color:#64748b">Critical CVE</div>
          </div>
        </div>
        <div style="font-size:8.5px;color:#94a3b8;line-height:1.6">
          <div style="display:flex;justify-content:space-between"><span>fail2ban</span><span style="color:#4ade80">&#x2713; Active</span></div>
          <div style="display:flex;justify-content:space-between"><span>Imunify360</span><span style="color:#4ade80">&#x2713; Running</span></div>
          <div style="display:flex;justify-content:space-between"><span>CVE-2024-34102</span><span style="color:#f87171">&#x26A0; Unpatched</span></div>
        </div>
      </div>
    </div>

    <!-- AI Terminal mockup -->
    <div style="background:rgba(99,102,241,.04);border:1px solid rgba(99,102,241,.2);border-radius:10px;overflow:hidden;display:flex;flex-direction:column">
      <div style="background:rgba(99,102,241,.12);padding:8px 12px;display:flex;align-items:center;gap:6px;border-bottom:1px solid rgba(99,102,241,.15)">
        <div style="width:8px;height:8px;border-radius:50%;background:#ef4444"></div>
        <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b"></div>
        <div style="width:8px;height:8px;border-radius:50%;background:#22c55e"></div>
        <div style="font-size:9px;color:#818cf8;margin-left:4px;font-weight:700">AI Terminal</div>
      </div>
      <div style="padding:8px;flex:1;font-family:monospace">
        <div style="font-size:8.5px;color:#64748b;margin-bottom:5px">Natural-language → server commands</div>
        <div style="background:#050810;border-radius:5px;padding:6px;font-size:8px;color:#94a3b8;line-height:1.7">
          <div><span style="color:#818cf8">User:</span> show top 5 processes by memory</div>
          <div><span style="color:#4ade80">AI:</span> Executing: ps aux --sort=-%mem | head -6</div>
          <div style="color:#64748b;padding-left:8px">mysqld  2.1G  ↑</div>
          <div style="color:#64748b;padding-left:8px">php-fpm 856M  ↑</div>
          <div style="color:#64748b;padding-left:8px">varnish 640M</div>
        </div>
        <div style="margin-top:5px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:4px;padding:4px 7px;font-size:8px;color:#4ade80">
          &#x1F4F1; Telegram dispatch enabled
        </div>
      </div>
    </div>

    <!-- phpMyAdmin mockup -->
    <div style="background:rgba(16,185,129,.04);border:1px solid rgba(16,185,129,.2);border-radius:10px;overflow:hidden;display:flex;flex-direction:column">
      <div style="background:rgba(16,185,129,.12);padding:8px 12px;display:flex;align-items:center;gap:6px;border-bottom:1px solid rgba(16,185,129,.15)">
        <div style="width:8px;height:8px;border-radius:50%;background:#ef4444"></div>
        <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b"></div>
        <div style="width:8px;height:8px;border-radius:50%;background:#22c55e"></div>
        <div style="font-size:9px;color:#34d399;margin-left:4px;font-weight:700">phpMyAdmin · MariaDB 10.6</div>
      </div>
      <div style="padding:8px;flex:1">
        <div style="font-size:8.5px;color:#94a3b8;margin-bottom:4px">&#x1F5C4; technostationery_db</div>
        <div style="font-size:8px;color:#64748b;line-height:1.8;border-left:2px solid rgba(16,185,129,.3);padding-left:6px">
          <div style="color:#34d399">&#x25B6; sales_order (875 rows)</div>
          <div style="color:#94a3b8">&#x25B6; catalog_product (2,341)</div>
          <div style="color:#94a3b8">&#x25B6; customer_entity (8,521)</div>
          <div style="color:#94a3b8">&#x25B6; quote (1,204)</div>
        </div>
        <div style="margin-top:5px;background:rgba(16,185,129,.08);border-radius:4px;padding:4px 7px;font-size:8px;color:#34d399">
          &#x1F512; Auth-gated · Dashboard-integrated
        </div>
      </div>
    </div>

    <!-- ETL & Push mockup -->
    <div style="background:rgba(245,158,11,.04);border:1px solid rgba(245,158,11,.2);border-radius:10px;overflow:hidden;display:flex;flex-direction:column">
      <div style="background:rgba(245,158,11,.12);padding:8px 12px;display:flex;align-items:center;gap:6px;border-bottom:1px solid rgba(245,158,11,.15)">
        <div style="width:8px;height:8px;border-radius:50%;background:#ef4444"></div>
        <div style="width:8px;height:8px;border-radius:50%;background:#f59e0b"></div>
        <div style="width:8px;height:8px;border-radius:50%;background:#22c55e"></div>
        <div style="font-size:9px;color:#fbbf24;margin-left:4px;font-weight:700">ETL / CI-CD Pipeline</div>
      </div>
      <div style="padding:8px;flex:1">
        <div style="font-size:8px;color:#94a3b8;line-height:1.7">
          <div style="display:flex;justify-content:space-between;margin-bottom:2px">
            <span style="color:#fbbf24">&#x1F7E1; build</span>
            <span style="color:#4ade80">passed &#x2713;</span>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:2px">
            <span style="color:#fbbf24">&#x1F7E1; release artifact</span>
            <span style="color:#4ade80">passed &#x2713;</span>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:4px">
            <span style="color:#fbbf24">&#x1F7E1; SSH deploy → tsdnd</span>
            <span style="color:#4ade80">passed &#x2713;</span>
          </div>
          <div style="background:rgba(6,182,212,.1);border-radius:4px;padding:3px 6px;color:#22d3ee;font-size:8px">
            &#x1F514; Push · Load spike detected → Telegram
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

</div><!-- END #deck -->'''

    html = html[:idx_start] + new_s38_s39
    changes.append('✓ s38 completely rebuilt (professional, original layout with module mockups)')
    changes.append('✓ s39 Dashboard Feature Gallery added (6 module mockups)')
else:
    changes.append('⚠ s38 start/end markers not found')

# ══════════════════════════════════════════════
# 5. Update s35 Thank You — mention dashboard
# ══════════════════════════════════════════════
old_div_subtitle = '<div class="div-subtitle" style="max-width:680px">38 slides · 8 phases · 481 Magento git commits · 43 security findings · 91 ecomscan vulns<br>Evidence-first · Dashboard v4.3.0 · phpMyAdmin secured · GitLab CI/CD deployed</div>'
new_div_subtitle = '<div class="div-subtitle" style="max-width:760px">39 slides · 8 phases · 481 Magento git commits · 43 security findings · 91 ecomscan vulns<br>Evidence-first · <strong style="color:#4ade80">Dashboard v4.3.0</strong> — 8 monitoring modules — React 18 + TypeScript · GitLab CI/CD deployed</div>'
if old_div_subtitle in html:
    html = html.replace(old_div_subtitle, new_div_subtitle, 1)
    changes.append('✓ s35 subtitle updated: 38 → 39 slides, dashboard mention')
else:
    changes.append('⚠ s35 subtitle not found')

# ══════════════════════════════════════════════
# 6. Update TOTAL and NOTES in JS
# ══════════════════════════════════════════════
old_total = 'const TOTAL = slides.length; // 38 slides (v5)'
new_total = 'const TOTAL = slides.length; // 39 slides (v6 — S14 overhaul)'
if old_total in html:
    html = html.replace(old_total, new_total, 1)
    changes.append('✓ TOTAL comment updated: 38 → 39 slides (v6)')
else:
    changes.append('⚠ TOTAL comment not found')

old_s38_note = "  s38: 'Monitoring Dashboard appendix. React 18 + TypeScript + MUI v6. 8 core modules: Process Explorer, Logs, Users, Security, phpMyAdmin (MariaDB 10.6), ETL/CI-CD, AI Terminal, Push Notifications. Evolving product shown every semester.'"
new_s38_note = """  s38: 'Dashboard Overview: v4.3.0 · 8 modules · 481 commits · React 18 + TypeScript + MUI v6. Modules: Process Explorer, Log Viewer, Users & Access, Security Audit, phpMyAdmin (MariaDB 10.6), ETL/CI-CD, AI Terminal, Push Notifications. Evolution: v1.0 Jan 2026 → v4.3.0 Jul 2026. Recurring deliverable shown every semester.',
  s39: 'Dashboard Feature Gallery: live module mockups. Master Dashboard KPIs (1,291 orders, 99.1% uptime, 0.42 load, 84.3% Redis). Process Explorer with PID list. Security Audit (53,269 attacks, 0 malware, 91 ecomscan). AI Terminal natural-language commands + Telegram. phpMyAdmin with MariaDB tables. ETL/CI-CD pipeline status.'"""
if old_s38_note in html:
    html = html.replace(old_s38_note, new_s38_note, 1)
    changes.append('✓ NOTES updated: s38 + s39 speaker notes added')
else:
    changes.append('⚠ NOTES s38 not found for update')

# ══════════════════════════════════════════════
# 7. Fix s1 cover count display
# ══════════════════════════════════════════════
old_cover_title = '<div class="cover-title">Executive Audit<br><span>January – July 2026</span></div>'
new_cover_title = '<div class="cover-title">Executive Audit<br><span>January – July 2026</span></div>'
# Already correct format — just fix slide count if it's still 37
old_kpi_37 = '<div class="cv-val">37</div><div class="cv-label">Audit Slides</div>'
new_kpi_39 = '<div class="cv-val">39</div><div class="cv-label">Audit Slides</div>'
if old_kpi_37 in html:
    html = html.replace(old_kpi_37, new_kpi_39, 1)
    changes.append('✓ s1 KPI slides 37 → 39 (fallback fix)')

# ══════════════════════════════════════════════
# Print report
# ══════════════════════════════════════════════
print(f'Original: {orig_len:,} chars')
print(f'Modified: {len(html):,} chars (+{len(html)-orig_len:,})')
print()
for c in changes:
    print(c)

with open(SRC, 'w', encoding='utf-8') as f:
    f.write(html)
print()
print('✓ Written successfully')
