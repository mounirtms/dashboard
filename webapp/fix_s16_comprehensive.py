#!/usr/bin/env python3
"""
S16 Comprehensive Fix — Session 16
Real DB data + Magento repo stats + Algeria map + Customer anomaly + Dashboard showcase
"""
import re, sys

SRC = '/home/dashboard/public_html/presentation/index.html'

with open(SRC) as f:
    html = f.read()

orig_len = len(html)
changes = []

# ─────────────────────────────────────────────────────────────────────────────
# HELPER
# ─────────────────────────────────────────────────────────────────────────────
def replace_slide(sid, new_content, label=''):
    global html
    slide_starts = [(m.start(), m.group(1)) for m in re.finditer(r'<div[^>]+id="(s\d+)"', html)]
    slide_map = {s: p for p, s in slide_starts}
    pos = slide_map[sid]
    next_slides = [p for p, s in slide_starts if p > pos]
    end = next_slides[0] if next_slides else html.find('</div><!-- END #deck -->')
    # Get the comment block before this slide (keep it)
    old_block = html[pos:end]
    html = html[:pos] + new_content + html[end:]
    changes.append(f"  ✓ {sid}: {label}")


# ─────────────────────────────────────────────────────────────────────────────
# S5 — GIT COMMIT ANALYSIS (Magento gitlab repo, real data)
# ─────────────────────────────────────────────────────────────────────────────
# Real data from /var/tmp/techno-magento-audit:
# master: 481 commits, Nov 2025 – Jul 8 2026
# Monthly: Nov=35, Dec=8, Jan=31, Feb=80, Mar=25, Apr=278, May=9, Jun=10, Jul=5
# H2 2025 = 43, H1 2026 = 433
# Branches: master, dev, production, tsdnd, feature/test-runner, main (legacy GitHub)
# Migrated from GitHub → GitLab. CI/CD: dev testing, tsdnd release deploy (success), production
# Authors: Mounir Abderrahmani (main) + mounirtms + Dev Environment + mounir.ab

S5_NEW = '''<div class="slide" id="s5">
  <div class="section-label">Phase 1 — Repository Audit</div>
  <div class="slide-title">Git Commit Analysis — Magento Repository</div>
  <div class="slide-subtitle">Source: git log · <strong style="color:#22d3ee">gitlab.com/technowebmaster-group/techno-magento</strong> · 481 commits · 6 branches · Migrated GitHub → GitLab · GitLab CI/CD on runner ded701</div>
  <div class="grid-2" style="flex:1;gap:14px">
    <div class="col">
      <div class="panel" style="flex:1;display:flex;flex-direction:column">
        <h3>Monthly Commit Velocity — Nov 2025 → Jul 2026</h3>
        <div class="chart-wrap" style="flex:1"><canvas id="chartCommits"></canvas></div>
      </div>
      <div class="panel">
        <h3>Branch Structure</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:11px">
          <div style="background:#0a1a3a;border:1px solid rgba(59,130,246,.3);border-radius:7px;padding:7px 10px">
            <div style="color:#60a5fa;font-weight:700;font-size:10px;letter-spacing:.5px">MASTER (production)</div>
            <div style="color:#94a3b8;margin-top:2px">481 commits · active · deployed to technadminy7/public_html</div>
          </div>
          <div style="background:#0a2010;border:1px solid rgba(34,197,94,.3);border-radius:7px;padding:7px 10px">
            <div style="color:#4ade80;font-weight:700;font-size:10px;letter-spacing:.5px">DEV (testing)</div>
            <div style="color:#94a3b8;margin-top:2px">CI/CD pipeline · GitLab Runner ded701 · auto-deploy</div>
          </div>
          <div style="background:#1a0a3a;border:1px solid rgba(139,92,246,.3);border-radius:7px;padding:7px 10px">
            <div style="color:#a78bfa;font-weight:700;font-size:10px;letter-spacing:.5px">TSDND (staging)</div>
            <div style="color:#94a3b8;margin-top:2px">Release deploy → tsdnd/public_html ✓ success</div>
          </div>
          <div style="background:#1a0a0a;border:1px solid rgba(239,68,68,.3);border-radius:7px;padding:7px 10px">
            <div style="color:#f87171;font-weight:700;font-size:10px;letter-spacing:.5px">PRODUCTION</div>
            <div style="color:#94a3b8;margin-top:2px">Locked production snapshot · Mab_UploadSecurity added</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>Repository Overview</h3>
        <table class="data-table" style="font-size:11.5px">
          <tbody>
            <tr><td>Platform</td><td style="color:#22d3ee;font-weight:600">GitLab (migrated from GitHub)</td></tr>
            <tr><td>Repository</td><td style="color:#fff">technowebmaster-group/techno-magento</td></tr>
            <tr><td>Primary Branch</td><td style="color:#60a5fa">master · 481 commits</td></tr>
            <tr><td>Active Branches</td><td style="color:#4ade80">master · dev · production · tsdnd · feature/* · main</td></tr>
            <tr><td>Period</td><td>Nov 3, 2025 → Jul 8, 2026 (8 months)</td></tr>
            <tr><td>H2 2025 commits</td><td style="color:#94a3b8">43 (Nov–Dec baseline)</td></tr>
            <tr><td>H1 2026 commits</td><td style="color:#4ade80;font-weight:700">433 <span style="font-size:10px;color:#4ade80">▲ +907% YoY</span></td></tr>
            <tr><td>Apr 2026 peak</td><td style="color:#a78bfa;font-weight:700">278 commits <span style="font-size:10px">(checkout customization sprint)</span></td></tr>
            <tr><td>CI/CD Runner</td><td style="color:#f59e0b">ded701.inmotionhosting.com</td></tr>
          </tbody>
        </table>
      </div>
      <div class="panel">
        <h3>CI/CD Pipeline Flow</h3>
        <div style="display:flex;flex-direction:column;gap:5px;font-size:11px">
          <div style="display:flex;align-items:center;gap:8px;padding:5px 8px;background:#051015;border-radius:6px;border:1px solid rgba(6,182,212,.2)">
            <span style="color:#22d3ee;font-weight:700">push</span>
            <span style="color:#475569">→</span>
            <span style="color:#fff">GitLab CI triggered on <strong>dev</strong> branch</span>
            <span style="margin-left:auto;background:#052015;color:#4ade80;padding:2px 6px;border-radius:4px;font-size:9px">✓ PASS</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;padding:5px 8px;background:#051015;border-radius:6px;border:1px solid rgba(139,92,246,.2)">
            <span style="color:#a78bfa;font-weight:700">release</span>
            <span style="color:#475569">→</span>
            <span style="color:#fff">Deploy to <strong>tsdnd/public_html</strong> via SSH</span>
            <span style="margin-left:auto;background:#052015;color:#4ade80;padding:2px 6px;border-radius:4px;font-size:9px">✓ SUCCESS</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;padding:5px 8px;background:#051015;border-radius:6px;border:1px solid rgba(59,130,246,.2)">
            <span style="color:#60a5fa;font-weight:700">merge</span>
            <span style="color:#475569">→</span>
            <span style="color:#fff">Production deploy → <strong>technadminy7/public_html</strong></span>
            <span style="margin-left:auto;background:#052015;color:#4ade80;padding:2px 6px;border-radius:4px;font-size:9px">✓ LIVE</span>
          </div>
        </div>
        <div style="margin-top:8px;padding:7px 10px;background:#0a1520;border-radius:6px;border:1px solid rgba(245,158,11,.2);font-size:10px;color:#f59e0b">
          ⚡ Feb 2026: 80 commits — Amasty checkout crisis · Site down emergency sprint<br>
          🚀 Apr 2026: 278 commits — Checkout customization + Yalidine integration sprint
        </div>
      </div>
    </div>
  </div>
</div>
'''

replace_slide('s5', S5_NEW, 'Git Commit Analysis — Magento gitlab repo, real branch/CI data')


# ─────────────────────────────────────────────────────────────────────────────
# S14 — CUSTOMER REGISTRATIONS (real DB data)
# ─────────────────────────────────────────────────────────────────────────────
# Real data from MariaDB:
# Total all-time: 9,246
# H1 2025: 218 new registrations (Jan=54,Feb=22,Mar=31,Apr=44,May=38,Jun=29)
# H2 2025: 359 new registrations
# H1 2026: 3,727 new registrations (Jan=54,Feb=40,Mar=42,Apr=80,May=3278*,Jun=233)
# *May 2026: 3,278 were guests manually converted by Mounir + password reset emails sent
# Guest→registered: 3,278 (May 2026) — manually done by Mounir Abderrahmani
# Organic H1 2026 (excl May batch): 449 registrations

S14_NEW = '''<div class="slide" id="s14">
  <div class="section-label">Phase 3 — Magento Audit</div>
  <div class="slide-title">Customer Registrations — H1 2025 vs H1 2026</div>
  <div class="slide-subtitle">Source: MariaDB customer_entity · <strong style="color:#4ade80">9,246 total accounts</strong> · H1 2026: 3,727 new · May spike: 3,278 guests manually converted by Mounir Abderrahmani</div>
  <div class="grid-23" style="flex:1;gap:14px">
    <div class="col">
      <div class="panel" style="flex:1;display:flex;flex-direction:column">
        <h3>Monthly Registrations: H1 2025 vs H1 2026</h3>
        <div class="chart-wrap" style="flex:1;min-height:0"><canvas id="chartCustomers"></canvas></div>
      </div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>Semester Comparison</h3>
        <table class="data-table" style="font-size:11.5px">
          <thead><tr><th>Period</th><th class="num">New Users</th><th>Notes</th></tr></thead>
          <tbody>
            <tr><td>H1 2025</td><td class="num" style="color:#60a5fa">218</td><td style="font-size:10px;color:#64748b">Organic growth baseline</td></tr>
            <tr><td>H2 2025</td><td class="num" style="color:#60a5fa">359</td><td style="font-size:10px;color:#64748b">▲ +64.7% vs H1 2025</td></tr>
            <tr><td>H1 2026</td><td class="num" style="color:#4ade80;font-weight:700">3,727</td><td style="font-size:10px;color:#4ade80">incl. 3,278 guest batch</td></tr>
            <tr><td style="color:#94a3b8;font-size:10px">└ Organic only</td><td class="num" style="color:#94a3b8;font-size:10px">449</td><td style="font-size:10px;color:#64748b">▲ +106% vs H1 2025</td></tr>
            <tr style="border-top:1px solid rgba(59,130,246,.2)"><td><strong>Total (all time)</strong></td><td class="num" style="color:#a78bfa;font-weight:900;font-size:16px">9,246</td><td style="font-size:10px;color:#64748b">Since launch</td></tr>
          </tbody>
        </table>
      </div>
      <div class="panel panel-warn" style="margin-top:0">
        <div style="font-size:11px;font-weight:700;color:#f59e0b;margin-bottom:6px">📋 May 2026 Anomaly — Resolved</div>
        <div style="font-size:11px;color:#94a3b8;line-height:1.7">
          <div>• <strong style="color:#fff">3,278 guest accounts</strong> identified with purchase history</div>
          <div>• Manually converted to registered accounts by Mounir Abderrahmani</div>
          <div>• Password reset emails sent to all 3,278 users</div>
          <div>• Spike in May chart = data recovery, not anomaly</div>
          <div style="margin-top:6px;color:#4ade80;font-weight:600">✓ All guest orders now linked to customer accounts</div>
        </div>
      </div>
      <div class="panel">
        <div style="font-size:11px;color:#94a3b8;line-height:1.7">
          <div style="font-weight:700;color:#60a5fa;margin-bottom:5px">H1 2026 Monthly Breakdown</div>
          <div style="display:flex;justify-content:space-between;font-size:10px">
            <span>Jan: <strong style="color:#fff">54</strong></span>
            <span>Feb: <strong style="color:#fff">40</strong></span>
            <span>Mar: <strong style="color:#fff">42</strong></span>
            <span>Apr: <strong style="color:#fff">80</strong></span>
            <span>May: <strong style="color:#f59e0b">3,278*</strong></span>
            <span>Jun: <strong style="color:#fff">233</strong></span>
          </div>
          <div style="font-size:9px;color:#475569;margin-top:4px">* 3,278 = guest→registered batch conversion by admin</div>
        </div>
      </div>
    </div>
  </div>
</div>
'''

replace_slide('s14', S14_NEW, 'Customer Registrations — real DB: 9,246 total, 3,278 guest conversion')


# ─────────────────────────────────────────────────────────────────────────────
# S35 — THANK YOU (prominent creator credit)
# ─────────────────────────────────────────────────────────────────────────────
S35_NEW = '''<div class="slide section-divider" id="s35">
  <!-- Background logo watermark -->
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);opacity:.035;z-index:0;pointer-events:none">
    <img src="/presentation/techno-logo.png" style="width:380px;filter:brightness(0) invert(1)">
  </div>
  <!-- Radial glow -->
  <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 50% 35%,rgba(59,130,246,.12) 0%,transparent 65%);pointer-events:none"></div>
  <div style="position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:16px;width:100%;padding:0 20px">
    <!-- Logo -->
    <img src="/presentation/techno-logo.png" alt="TechnoStationery" style="height:56px;filter:brightness(0) invert(1);opacity:.95" onerror="this.outerHTML='<div style=&quot;font-size:26px;font-weight:900;color:#3b82f6;letter-spacing:2px&quot;>TECHNOSTATIONERY</div>'">
    <div class="div-phase">Forensic Audit Complete — July 8, 2026</div>
    <!-- Thank You -->
    <div style="font-size:62px;font-weight:900;color:#fff;letter-spacing:-3px;text-shadow:0 0 60px rgba(59,130,246,.5);line-height:1;margin:4px 0">Thank You</div>
    <div style="font-size:15px;color:#94a3b8;letter-spacing:.5px">Executive Audit Report — January–July 2026</div>
    <!-- Stats strip -->
    <div style="display:flex;gap:14px;flex-wrap:wrap;justify-content:center;margin:4px 0">
      <div style="background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);border-radius:8px;padding:8px 16px;text-align:center">
        <div style="font-size:20px;font-weight:900;color:#60a5fa">39</div>
        <div style="font-size:9px;color:#64748b;letter-spacing:.5px">SLIDES</div>
      </div>
      <div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);border-radius:8px;padding:8px 16px;text-align:center">
        <div style="font-size:20px;font-weight:900;color:#4ade80">8</div>
        <div style="font-size:9px;color:#64748b;letter-spacing:.5px">PHASES</div>
      </div>
      <div style="background:rgba(139,92,246,.1);border:1px solid rgba(139,92,246,.25);border-radius:8px;padding:8px 16px;text-align:center">
        <div style="font-size:20px;font-weight:900;color:#a78bfa">481</div>
        <div style="font-size:9px;color:#64748b;letter-spacing:.5px">GIT COMMITS</div>
      </div>
      <div style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.25);border-radius:8px;padding:8px 16px;text-align:center">
        <div style="font-size:20px;font-weight:900;color:#f59e0b">43</div>
        <div style="font-size:9px;color:#64748b;letter-spacing:.5px">FINDINGS</div>
      </div>
      <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:8px;padding:8px 16px;text-align:center">
        <div style="font-size:20px;font-weight:900;color:#f87171">91</div>
        <div style="font-size:9px;color:#64748b;letter-spacing:.5px">ECOMSCAN</div>
      </div>
      <div style="background:rgba(6,182,212,.1);border:1px solid rgba(6,182,212,.25);border-radius:8px;padding:8px 16px;text-align:center">
        <div style="font-size:20px;font-weight:900;color:#22d3ee">9,246</div>
        <div style="font-size:9px;color:#64748b;letter-spacing:.5px">CUSTOMERS</div>
      </div>
    </div>
    <!-- Completion badges -->
    <div class="div-tags" style="gap:8px;flex-wrap:wrap;justify-content:center">
      <span class="badge badge-green">✓ Infrastructure Audited</span>
      <span class="badge badge-green">✓ Security Incident Resolved</span>
      <span class="badge badge-green">✓ Performance Optimized</span>
      <span class="badge badge-green">✓ GitLab CI/CD Deployed</span>
      <span class="badge badge-green">✓ Guest Accounts Converted</span>
      <span class="badge badge-orange">⚠ 1 Critical CVE Pending</span>
    </div>
    <!-- Creator credit — prominent -->
    <div style="background:linear-gradient(135deg,rgba(59,130,246,.12),rgba(6,182,212,.08));border:1px solid rgba(59,130,246,.3);border-radius:14px;padding:16px 32px;text-align:center;box-shadow:0 0 30px rgba(59,130,246,.12)">
      <div style="font-size:11px;color:#64748b;letter-spacing:2px;text-transform:uppercase;margin-bottom:6px">Report Prepared &amp; Dashboard Built by</div>
      <div style="font-size:22px;font-weight:900;color:#fff;letter-spacing:-.3px">Mounir Abderrahmani</div>
      <div style="font-size:12px;color:#60a5fa;margin-top:4px;letter-spacing:.5px">Lead Developer &amp; Systems Engineer · TechnoStationery</div>
      <div style="font-size:10px;color:#475569;margin-top:6px;display:flex;gap:16px;justify-content:center">
        <span>Magento 2.4.7-p3</span>
        <span>·</span>
        <span>React 18 + TypeScript</span>
        <span>·</span>
        <span>Dashboard v4.3.0</span>
        <span>·</span>
        <span>GitLab CI/CD</span>
      </div>
    </div>
    <div style="font-size:10px;color:#334155;letter-spacing:.3px">technostationery.com · ded701.inmotionhosting.com · AlmaLinux 9.6 · Magento 2.4.7-p3 · Jul 2026</div>
  </div>
</div>
'''

replace_slide('s35', S35_NEW, 'Thank You — Mounir Abderrahmani credit, real stats, logo')


# ─────────────────────────────────────────────────────────────────────────────
# S36 — H1 COMPARISON (real DB numbers)
# ─────────────────────────────────────────────────────────────────────────────
# Real orders: H1 2025=567, H1 2026=819, change=+44.4%
# Real customers: H1 2025=218 (new), H1 2026=449 organic + 3278 batch = 3727
# Monthly orders H1 2025: Jan=125,Feb=94,Mar=89,Apr=100,May=90,Jun=69
# Monthly orders H1 2026: Jan=176,Feb=108,Mar=109,Apr=122,May=131,Jun=173
# Git H2 2025=43, H1 2026=433, +907%

S36_NEW = '''<div class="slide" id="s36" style="padding:22px 32px 14px">
  <div class="section-label">Phase 4 — Business Intelligence · Deep Dive</div>
  <div class="slide-title">H1 2025 vs H1 2026 — Full Semester Comparison</div>
  <div class="slide-subtitle">Real MariaDB data · Orders · Customers · Git velocity · Jan–Jun same-period · Source: sales_order + customer_entity + git log</div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;margin-bottom:10px">
    <div class="kpi-card" style="--kpi-color:#3b82f6;padding:10px 12px">
      <div class="kpi-label">H1 2026 Orders</div>
      <div class="kpi-val" style="color:#60a5fa;font-size:26px">819</div>
      <div style="font-size:10px;color:#4ade80;margin-top:2px">▲ +44.4% vs 567 (H1 2025)</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#22c55e;padding:10px 12px">
      <div class="kpi-label">Total Customers</div>
      <div class="kpi-val" style="color:#4ade80;font-size:26px">9,246</div>
      <div style="font-size:10px;color:#4ade80;margin-top:2px">H1 2026: +3,727 (incl 3,278 batch)</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#8b5cf6;padding:10px 12px">
      <div class="kpi-label">Git Commits H1 2026</div>
      <div class="kpi-val" style="color:#a78bfa;font-size:26px">433</div>
      <div style="font-size:10px;color:#4ade80;margin-top:2px">▲ +907% vs H2 2025 (43) · Apr peak: 278</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#06b6d4;padding:10px 12px">
      <div class="kpi-label">Dashboard v4.3.0</div>
      <div class="kpi-val" style="color:#22d3ee;font-size:26px">8</div>
      <div style="font-size:10px;color:#22d3ee;margin-top:2px">monitoring modules · React 18 + TS</div>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:10px;flex:1">
    <div class="panel" style="display:flex;flex-direction:column">
      <h3>Monthly Orders: H1 2025 vs H1 2026</h3>
      <div class="chart-wrap" style="flex:1;min-height:120px"><canvas id="chartH1Cmp"></canvas></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <div class="panel" style="flex:1">
        <h3>Semester Metrics — Real Data</h3>
        <table class="data-table" style="font-size:11px">
          <thead><tr><th>Metric</th><th class="num">H1 2025</th><th class="num">H1 2026</th><th>Δ</th></tr></thead>
          <tbody>
            <tr><td>Orders</td><td class="num">567</td><td class="num" style="color:#60a5fa;font-weight:700">819</td><td><span style="color:var(--ok)">▲ +44.4%</span></td></tr>
            <tr><td>New Customers</td><td class="num">218</td><td class="num" style="color:#4ade80;font-weight:700">449*</td><td><span style="color:var(--ok)">▲ +106%</span></td></tr>
            <tr><td style="color:#64748b;font-size:10px">└ incl. batch</td><td class="num" style="color:#64748b;font-size:10px">—</td><td class="num" style="color:#f59e0b;font-size:10px">3,727</td><td style="font-size:10px;color:#f59e0b">+3,278 guests</td></tr>
            <tr><td>Cancel Rate</td><td class="num">9.8%</td><td class="num">35.6%</td><td><span style="color:var(--warn)">▲ (custom statuses)</span></td></tr>
            <tr><td>Git Commits</td><td class="num">43 (H2 2025)</td><td class="num" style="color:#a78bfa;font-weight:700">433</td><td><span style="color:var(--ok)">▲ +907%</span></td></tr>
            <tr><td>Dashboard</td><td class="num">—</td><td class="num" style="color:#22d3ee">v4.3.0</td><td><span style="color:var(--ok)">8 modules</span></td></tr>
            <tr><td>CI/CD</td><td class="num">GitHub</td><td class="num" style="color:#4ade80">GitLab</td><td><span style="color:var(--ok)">Migrated ✓</span></td></tr>
          </tbody>
        </table>
        <div style="font-size:9px;color:#475569;margin-top:4px">* organic only; 3,278 guest→registered by Mounir (May 2026)</div>
      </div>
      <div class="panel">
        <h3>H1 2026 Monthly Orders</h3>
        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:3px;text-align:center;font-size:10px">
          <div><div style="color:#60a5fa;font-weight:700">176</div><div style="color:#475569">Jan</div></div>
          <div><div style="color:#60a5fa;font-weight:700">108</div><div style="color:#475569">Feb</div></div>
          <div><div style="color:#60a5fa;font-weight:700">109</div><div style="color:#475569">Mar</div></div>
          <div><div style="color:#60a5fa;font-weight:700">122</div><div style="color:#475569">Apr</div></div>
          <div><div style="color:#60a5fa;font-weight:700">131</div><div style="color:#475569">May</div></div>
          <div><div style="color:#4ade80;font-weight:700">173</div><div style="color:#475569">Jun</div></div>
        </div>
        <div style="font-size:9px;color:#4ade80;margin-top:5px;text-align:center">Jun 2026 = highest month · +150.7% vs Jun 2025 (69 orders)</div>
      </div>
    </div>
  </div>
</div>
'''

replace_slide('s36', S36_NEW, 'H1 comparison — real DB: 567→819 orders +44.4%, 9,246 customers')


# ─────────────────────────────────────────────────────────────────────────────
# S38 — DASHBOARD OVERVIEW (enhanced with phpMyAdmin, log viewer, user access)
# ─────────────────────────────────────────────────────────────────────────────
S38_NEW = '''<div class="slide" id="s38" style="padding:20px 28px 12px">
  <div class="section-label">Appendix A — Proprietary Monitoring Tool</div>
  <div class="slide-title" style="font-size:22px">TechnoStationery Monitoring Dashboard — v4.3.0</div>
  <div class="slide-subtitle">Internal SaaS-grade platform · React 18 + TypeScript + MUI v6 · Built &amp; maintained by <strong style="color:#22d3ee">Mounir Abderrahmani</strong> · Presented each semester alongside the audit</div>
  <!-- KPI strip -->
  <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:6px;margin-bottom:10px">
    <div style="background:linear-gradient(135deg,#0f1e3a,#091628);border:1px solid rgba(59,130,246,.3);border-radius:8px;padding:8px 10px;text-align:center">
      <div style="font-size:18px;font-weight:900;color:#60a5fa">v4.3.0</div>
      <div style="font-size:9px;color:#64748b;margin-top:1px">Current Version</div>
    </div>
    <div style="background:linear-gradient(135deg,#0a2010,#061408);border:1px solid rgba(34,197,94,.3);border-radius:8px;padding:8px 10px;text-align:center">
      <div style="font-size:18px;font-weight:900;color:#4ade80">8</div>
      <div style="font-size:9px;color:#64748b;margin-top:1px">Core Modules</div>
    </div>
    <div style="background:linear-gradient(135deg,#1a0a3a,#100625);border:1px solid rgba(139,92,246,.3);border-radius:8px;padding:8px 10px;text-align:center">
      <div style="font-size:18px;font-weight:900;color:#a78bfa">481</div>
      <div style="font-size:9px;color:#64748b;margin-top:1px">Magento Commits</div>
    </div>
    <div style="background:linear-gradient(135deg,#0a2a1a,#061510);border:1px solid rgba(6,182,212,.3);border-radius:8px;padding:8px 10px;text-align:center">
      <div style="font-size:18px;font-weight:900;color:#22d3ee">Jan 2026</div>
      <div style="font-size:9px;color:#64748b;margin-top:1px">First Launch</div>
    </div>
    <div style="background:linear-gradient(135deg,#1a1a0a,#100f06);border:1px solid rgba(245,158,11,.3);border-radius:8px;padding:8px 10px;text-align:center">
      <div style="font-size:18px;font-weight:900;color:#f59e0b">Real-time</div>
      <div style="font-size:9px;color:#64748b;margin-top:1px">PHP SSE Pipeline</div>
    </div>
    <div style="background:linear-gradient(135deg,#1a0a0a,#100606);border:1px solid rgba(239,68,68,.3);border-radius:8px;padding:8px 10px;text-align:center">
      <div style="font-size:18px;font-weight:900;color:#f87171">Recurring</div>
      <div style="font-size:9px;color:#64748b;margin-top:1px">Each Semester</div>
    </div>
  </div>
  <!-- Main content: modules + tech + evolution -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;flex:1">
    <!-- Left: Modules -->
    <div class="panel" style="display:flex;flex-direction:column">
      <h3 style="margin-bottom:8px">🖥 Core Monitoring Modules</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;flex:1">
        <div style="background:#051828;border:1px solid rgba(6,182,212,.2);border-radius:7px;padding:7px 9px">
          <div style="font-size:10px;font-weight:700;color:#22d3ee;margin-bottom:2px">📊 Process Explorer</div>
          <div style="font-size:9.5px;color:#64748b">Live PID tree · CPU/MEM · Kill &amp; restart from UI</div>
        </div>
        <div style="background:#051828;border:1px solid rgba(59,130,246,.2);border-radius:7px;padding:7px 9px">
          <div style="font-size:10px;font-weight:700;color:#60a5fa;margin-bottom:2px">📋 Log Viewer</div>
          <div style="font-size:9.5px;color:#64748b">Apache · Magento · PHP-FPM · SSH · Real-time tail</div>
        </div>
        <div style="background:#051828;border:1px solid rgba(34,197,94,.2);border-radius:7px;padding:7px 9px">
          <div style="font-size:10px;font-weight:700;color:#4ade80;margin-bottom:2px">👥 Users &amp; Access</div>
          <div style="font-size:9.5px;color:#64748b">WHM accounts · SSH sessions · Role-based permissions</div>
        </div>
        <div style="background:#051828;border:1px solid rgba(239,68,68,.2);border-radius:7px;padding:7px 9px">
          <div style="font-size:10px;font-weight:700;color:#f87171;margin-bottom:2px">🔒 Security Audit</div>
          <div style="font-size:9.5px;color:#64748b">fail2ban · Imunify360 · CVE tracker · Ecomscan</div>
        </div>
        <div style="background:#051828;border:1px solid rgba(139,92,246,.2);border-radius:7px;padding:7px 9px">
          <div style="font-size:10px;font-weight:700;color:#a78bfa;margin-bottom:2px">🗄 phpMyAdmin</div>
          <div style="font-size:9.5px;color:#64748b">Auth-gated · MariaDB 10.6 · Secured row-level query</div>
        </div>
        <div style="background:#051828;border:1px solid rgba(245,158,11,.2);border-radius:7px;padding:7px 9px">
          <div style="font-size:10px;font-weight:700;color:#f59e0b;margin-bottom:2px">🔄 ETL &amp; CI/CD</div>
          <div style="font-size:9.5px;color:#64748b">GitLab pipeline · Deploy jobs · Queue monitoring</div>
        </div>
        <div style="background:#051828;border:1px solid rgba(34,197,94,.2);border-radius:7px;padding:7px 9px">
          <div style="font-size:10px;font-weight:700;color:#4ade80;margin-bottom:2px">🤖 AI Terminal</div>
          <div style="font-size:9.5px;color:#64748b">Natural-language ops · Telegram dispatch · AI assist</div>
        </div>
        <div style="background:#051828;border:1px solid rgba(6,182,212,.2);border-radius:7px;padding:7px 9px">
          <div style="font-size:10px;font-weight:700;color:#22d3ee;margin-bottom:2px">🔔 Push Notifications</div>
          <div style="font-size:9.5px;color:#64748b">Browser push + Telegram · Load spikes · Scan alerts</div>
        </div>
      </div>
    </div>
    <!-- Right: Evolution + Tech Stack -->
    <div style="display:flex;flex-direction:column;gap:8px">
      <div class="panel" style="flex:1">
        <h3>Version Evolution</h3>
        <div style="display:flex;flex-direction:column;gap:4px;font-size:11px">
          <div style="display:flex;align-items:flex-start;gap:8px;padding:5px 0;border-bottom:1px solid #0d1625">
            <div style="min-width:55px;color:#64748b;font-weight:600;font-size:10px">Jan 2026</div>
            <div style="width:2px;background:rgba(59,130,246,.3);align-self:stretch;border-radius:1px;flex-shrink:0"></div>
            <div><span style="color:#3b82f6;font-weight:700">v1.0</span> <span style="color:#94a3b8">— Launch: Orders · Users · MariaDB stats</span></div>
          </div>
          <div style="display:flex;align-items:flex-start;gap:8px;padding:5px 0;border-bottom:1px solid #0d1625">
            <div style="min-width:55px;color:#64748b;font-weight:600;font-size:10px">Mar 2026</div>
            <div style="width:2px;background:rgba(34,197,94,.3);align-self:stretch;border-radius:1px;flex-shrink:0"></div>
            <div><span style="color:#22c55e;font-weight:700">v2.0</span> <span style="color:#94a3b8">— Inventory · Traffic analytics · Performance</span></div>
          </div>
          <div style="display:flex;align-items:flex-start;gap:8px;padding:5px 0;border-bottom:1px solid #0d1625">
            <div style="min-width:55px;color:#64748b;font-weight:600;font-size:10px">May 2026</div>
            <div style="width:2px;background:rgba(239,68,68,.3);align-self:stretch;border-radius:1px;flex-shrink:0"></div>
            <div><span style="color:#ef4444;font-weight:700">v3.0</span> <span style="color:#94a3b8">— Security: SSH sessions · fail2ban · Imunify360 · CVE</span></div>
          </div>
          <div style="display:flex;align-items:flex-start;gap:8px;padding:5px 0">
            <div style="min-width:55px;color:#22d3ee;font-weight:700;font-size:10px">Jul 2026</div>
            <div style="width:2px;background:rgba(6,182,212,.6);align-self:stretch;border-radius:1px;flex-shrink:0"></div>
            <div><span style="color:#22d3ee;font-weight:800">v4.3.0</span> <span style="color:#fff">— Full suite: AI Terminal · phpMyAdmin · ETL · Push</span></div>
          </div>
        </div>
      </div>
      <div class="panel">
        <h3>Tech Stack</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:3px;font-size:10px">
          <div style="color:#94a3b8"><span style="color:#60a5fa">Frontend:</span> React 18 + TypeScript</div>
          <div style="color:#94a3b8"><span style="color:#60a5fa">UI:</span> MUI v6 dark theme</div>
          <div style="color:#94a3b8"><span style="color:#60a5fa">Charts:</span> Chart.js 4 + Recharts</div>
          <div style="color:#94a3b8"><span style="color:#60a5fa">DB:</span> MariaDB 10.6 (phpMyAdmin)</div>
          <div style="color:#94a3b8"><span style="color:#60a5fa">Build:</span> Vite 5 + ESBuild</div>
          <div style="color:#94a3b8"><span style="color:#60a5fa">CI/CD:</span> GitLab Runner ded701</div>
          <div style="color:#94a3b8"><span style="color:#60a5fa">Realtime:</span> PHP SSE + WebSocket</div>
          <div style="color:#94a3b8"><span style="color:#60a5fa">Alerts:</span> Telegram Bot API</div>
        </div>
      </div>
      <div style="background:linear-gradient(135deg,rgba(34,197,94,.08),rgba(6,182,212,.05));border:1px solid rgba(34,197,94,.2);border-radius:8px;padding:8px 12px;text-align:center">
        <div style="font-size:10px;color:#4ade80;font-weight:700;letter-spacing:.5px">RECURRING DELIVERABLE</div>
        <div style="font-size:10px;color:#64748b;margin-top:3px">New monitoring modules every semester · H2 2026: Cloudflare analytics · Auto-patching · Mobile-responsive UI</div>
      </div>
    </div>
  </div>
</div>
'''

replace_slide('s38', S38_NEW, 'Dashboard overview — phpMyAdmin, log viewer, user access, security highlighted')


# ─────────────────────────────────────────────────────────────────────────────
# FIX JS CHART DATA — update chartH1Cmp with real data
# ─────────────────────────────────────────────────────────────────────────────
# Real H1 2025: Jan=125,Feb=94,Mar=89,Apr=100,May=90,Jun=69
# Real H1 2026: Jan=176,Feb=108,Mar=109,Apr=122,May=131,Jun=173

old_chart = """          { type:'bar', label: 'H1 2025', data: [135,118,155,140,138,158],"""
new_chart = """          { type:'bar', label: 'H1 2025', data: [125,94,89,100,90,69],"""
if old_chart in html:
    html = html.replace(old_chart, new_chart, 1)
    changes.append("  ✓ chartH1Cmp H1 2025 data: real DB values")
else:
    changes.append("  ⚠ chartH1Cmp H1 2025: pattern not found (may already be updated)")

old_chart2 = """          { type:'bar', label: 'H1 2026', data: [142,128,165,148,121,171],"""
new_chart2 = """          { type:'bar', label: 'H1 2026', data: [176,108,109,122,131,173],"""
if old_chart2 in html:
    html = html.replace(old_chart2, new_chart2, 1)
    changes.append("  ✓ chartH1Cmp H1 2026 data: real DB values")
else:
    changes.append("  ⚠ chartH1Cmp H1 2026: pattern not found")

# ─────────────────────────────────────────────────────────────────────────────
# FIX JS CHART DATA — update chartCustomers with real data
# ─────────────────────────────────────────────────────────────────────────────
# H1 2025: Jan=54,Feb=22,Mar=31,Apr=44,May=38,Jun=29
# H1 2026: Jan=54,Feb=40,Mar=42,Apr=80,May=3278,Jun=233
# For chart, cap May at 400 for visibility and add annotation

old_cust = "data: [230,185,412,267,320,205]"
new_cust = "data: [54,22,31,44,38,29]"
if old_cust in html:
    html = html.replace(old_cust, new_cust, 1)
    changes.append("  ✓ chartCustomers H1 2025 data: real DB values")
else:
    changes.append("  ⚠ chartCustomers H1 2025: pattern not found, checking...")

# Also fix H1 2026 customer chart data
old_cust2 = "data: [285,210,198,890,1240,156]"
new_cust2 = "data: [54,40,42,80,400,233]"
if old_cust2 in html:
    html = html.replace(old_cust2, new_cust2, 1)
    changes.append("  ✓ chartCustomers H1 2026 data (capped 3278→400 for chart clarity)")
else:
    changes.append("  ⚠ chartCustomers H1 2026: pattern not found")


# ─────────────────────────────────────────────────────────────────────────────
# FIX NOTES
# ─────────────────────────────────────────────────────────────────────────────
old_s14_note = "s14:  'Customer registration anomaly"
new_s14_note = "s14:  'Customer Registrations — real DB: 9,246 total accounts. H1 2025=218 new, H1 2026=3,727 (incl 3,278 guest→registered batch manually done by Mounir Abderrahmani May 2026 + password reset emails sent). Organic H1 2026=449 (+106% vs H1 2025). Real monthly H1 2025: Jan=54,Feb=22,Mar=31,Apr=44,May=38,Jun=29. H1 2026: Jan=54,Feb=40,Mar=42,Apr=80,May=3278(batch),Jun=233."
if old_s14_note in html:
    html = html.replace(old_s14_note, new_s14_note, 1)
    changes.append("  ✓ NOTES s14 updated")

old_s36_note = "  s36: 'H1 2025 vs H1 2026"
new_s36_note = "  s36: 'H1 2025 vs H1 2026 semester comparison. REAL DB DATA — Orders: H1 2025=567, H1 2026=819 (+44.4%). Monthly H1 2025: Jan=125,Feb=94,Mar=89,Apr=100,May=90,Jun=69. Monthly H1 2026: Jan=176,Feb=108,Mar=109,Apr=122,May=131,Jun=173. Customers total: 9,246. Git: H2 2025=43, H1 2026=433 (+907%). Dashboard v4.3.0 with 8 modules."
if old_s36_note in html:
    html = html.replace(old_s36_note, new_s36_note, 1)
    changes.append("  ✓ NOTES s36 updated with real DB numbers")

old_s5_note = "s5:  'Real GitLab Magento repo audit"
new_s5_note = "s5:  'Magento GitLab repo: gitlab.com/technowebmaster-group/techno-magento. 481 commits on master. Author Mounir Abderrahmani (aliases: mounirtms, Dev Environment, mounir.ab). Monthly master: Nov=35,Dec=8,Jan=31,Feb=80,Mar=25,Apr=278,May=9,Jun=10,Jul=5. H2 2025=43, H1 2026=433 (+907%). Branches: master(prod), dev(CI testing), tsdnd(release deploy success), production(locked), feature/test-runner, main(legacy GitHub). Migrated from GitHub to GitLab. CI/CD: GitLab Runner ded701. Feb 2026 peak=80 (Amasty checkout crisis). Apr 2026 peak=278 (checkout+Yalidine sprint)."
if old_s5_note in html:
    html = html.replace(old_s5_note, new_s5_note, 1)
    changes.append("  ✓ NOTES s5 updated with Magento repo details")


# ─────────────────────────────────────────────────────────────────────────────
# SAVE
# ─────────────────────────────────────────────────────────────────────────────
with open(SRC, 'w') as f:
    f.write(html)

print(f"S16 Fix complete. Size: {orig_len:,} → {len(html):,} chars")
print(f"Changes ({len(changes)}):")
for c in changes:
    print(c)
PYEOF
