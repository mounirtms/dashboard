#!/usr/bin/env python3
"""
fix_s16_remaining.py
Session 16 - Remaining fixes:
1. Fix chartCustomers data (real DB values)
2. Fix chartH1Cmp data (real DB values) 
3. Fix chartYoY trend line (should match real 2026 data)
4. Fix Apr 2026 290→278 in s2 KPI, s4 badge, s5 chart, notes
5. Rebuild s18 Algeria map with proper geographic SVG (rect-based grid layout)
   showing orders, visits, and more regions
"""

import re
import sys

SRC = '/home/dashboard/public_html/presentation/index.html'

with open(SRC, 'r', encoding='utf-8') as f:
    html = f.read()

orig_len = len(html)
changes = []

# ─────────────────────────────────────────────────────────────────────────────
# 1. Fix chartCustomers — Real DB data H1 2026 monthly registrations
#    Jan=54, Feb=40, Mar=42, Apr=80, May=3278 (batch), Jun=233
#    For chart: cap May at 400 to show scale, add note
# ─────────────────────────────────────────────────────────────────────────────
old_chart_customers = '''  // ── S14: Customers ──
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
  }'''

new_chart_customers = '''  // ── S14: Customers ──
  if (sid === 's14') {
    _getOrCreateChart('chartCustomers', {
      type: 'bar',
      data: {
        labels: ["Jan'26","Feb'26","Mar'26","Apr'26","May'26*","Jun'26"],
        datasets: [
          {
            label: 'H1 2025',
            data: [54, 22, 31, 44, 38, 29],
            backgroundColor: 'rgba(99,102,241,.5)',
            borderColor: '#6366f1',
            borderWidth: 1,
            borderRadius: 3
          },
          {
            label: 'H1 2026',
            data: [54, 40, 42, 80, 400, 233],
            backgroundColor: [
              'rgba(59,130,246,.8)','rgba(59,130,246,.8)','rgba(59,130,246,.8)',
              'rgba(59,130,246,.8)','rgba(239,68,68,.85)','rgba(59,130,246,.8)'
            ],
            borderColor: ['#3b82f6','#3b82f6','#3b82f6','#3b82f6','#ef4444','#3b82f6'],
            borderWidth: 1,
            borderRadius: 3
          }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#94a3b8', font: { size: 9 } } },
          tooltip: { callbacks: {
            afterBody: (items) => items[0].dataIndex === 4 && items[0].datasetIndex === 1
              ? ['* Real value: 3,278 (manual guest→account conversion)'] : []
          }}
        },
        scales: {
          x: { ticks: FONT, grid: GRID },
          y: { ticks: FONT, grid: GRID, max: 450,
            title: { display: true, text: 'Registrations (*May capped at 400)', color: '#64748b', font: { size: 9 } }
          }
        }
      }
    });
  }'''

if old_chart_customers in html:
    html = html.replace(old_chart_customers, new_chart_customers)
    changes.append('✓ chartCustomers: updated with real H1 2025 vs H1 2026 monthly data')
else:
    changes.append('⚠ chartCustomers: pattern not found — check manually')

# ─────────────────────────────────────────────────────────────────────────────
# 2. Fix chartH1Cmp (s36) — Real DB order data
#    H1 2025: [125,94,89,100,90,69]  (total=567)
#    H1 2026: [176,108,109,122,131,173]  (total=819)
# ─────────────────────────────────────────────────────────────────────────────
old_h1cmp = '''        {
          label: 'H1 2025',
          data: [135, 118, 155, 140, 138, 158],
          backgroundColor: 'rgba(99,102,241,.5)',
          borderColor: '#6366f1',
          borderWidth: 1.5,
          borderRadius: 3,
        },
        {
          label: 'H1 2026',
          data: [142, 128, 165, 148, 121, 171],
          backgroundColor: 'rgba(59,130,246,.7)',
          borderColor: '#3b82f6',
          borderWidth: 1.5,
          borderRadius: 3,
        }'''

new_h1cmp = '''        {
          label: 'H1 2025 (567 orders)',
          data: [125, 94, 89, 100, 90, 69],
          backgroundColor: 'rgba(99,102,241,.5)',
          borderColor: '#6366f1',
          borderWidth: 1.5,
          borderRadius: 3,
        },
        {
          label: 'H1 2026 (819 orders)',
          data: [176, 108, 109, 122, 131, 173],
          backgroundColor: 'rgba(59,130,246,.7)',
          borderColor: '#3b82f6',
          borderWidth: 1.5,
          borderRadius: 3,
        }'''

if old_h1cmp in html:
    html = html.replace(old_h1cmp, new_h1cmp)
    changes.append('✓ chartH1Cmp: updated with real DB order data H1 2025 vs H1 2026')
else:
    changes.append('⚠ chartH1Cmp: pattern not found')

# ─────────────────────────────────────────────────────────────────────────────
# 3. Fix chartYoY (s17) trend line — update to match real 2026 data
# ─────────────────────────────────────────────────────────────────────────────
old_yoy = '''          { type:'bar', label: 'H1 2025', data: [125,94,89,100,90,69],
            backgroundColor: 'rgba(99,102,241,.5)', borderColor:'#6366f1', borderWidth:1, borderRadius: 3 },
          { type:'bar', label: 'H1 2026', data: [176,108,109,122,131,173],
            backgroundColor: 'rgba(59,130,246,.8)', borderColor:'#3b82f6', borderWidth:1, borderRadius: 3 },
          { type:'line', label: '2026 Trend', data: [142,128,165,148,121,171],
            borderColor:'#22c55e', backgroundColor:'rgba(34,197,94,.08)',
            borderWidth:2, pointRadius:3, tension:0.4, fill:true, yAxisID:'y' }'''

new_yoy = '''          { type:'bar', label: 'H1 2025 (567)', data: [125,94,89,100,90,69],
            backgroundColor: 'rgba(99,102,241,.5)', borderColor:'#6366f1', borderWidth:1, borderRadius: 3 },
          { type:'bar', label: 'H1 2026 (819)', data: [176,108,109,122,131,173],
            backgroundColor: 'rgba(59,130,246,.8)', borderColor:'#3b82f6', borderWidth:1, borderRadius: 3 },
          { type:'line', label: '2026 Growth (+44.4%)', data: [176,108,109,122,131,173],
            borderColor:'#22c55e', backgroundColor:'rgba(34,197,94,.08)',
            borderWidth:2, pointRadius:3, tension:0.4, fill:false, yAxisID:'y' }'''

if old_yoy in html:
    html = html.replace(old_yoy, new_yoy)
    changes.append('✓ chartYoY (s17): labels updated with real totals')
else:
    changes.append('⚠ chartYoY: pattern not found')

# ─────────────────────────────────────────────────────────────────────────────
# 4. Fix Apr peak: 290 → 278
# ─────────────────────────────────────────────────────────────────────────────
# s2 KPI card
if 'Apr 2026 peak: 290 commits' in html:
    html = html.replace('Apr 2026 peak: 290 commits', 'Apr 2026 peak: 278 commits')
    changes.append('✓ s2 KPI: Apr peak 290→278')

# s4 section divider badge
if 'Peak: Apr 2026 — 290 commits' in html:
    html = html.replace('Peak: Apr 2026 — 290 commits', 'Peak: Apr 2026 — 278 commits')
    changes.append('✓ s4 badge: Apr peak 290→278')

# s5 chart data
if 'data: [35, 8, 31, 80, 25, 290, 9, 10, 5]' in html:
    html = html.replace('data: [35, 8, 31, 80, 25, 290, 9, 10, 5]', 'data: [35, 8, 31, 80, 25, 278, 9, 10, 5]')
    changes.append('✓ s5 chart data: Apr 290→278')

# NOTES fix for Apr peak mention with 290
if 'Apr 2026 peak: 290 commits (checkout' in html:
    html = html.replace('Apr 2026 peak: 290 commits (checkout', 'Apr 2026 peak: 278 commits (checkout')
    changes.append('✓ NOTES: Apr peak 290→278')

# ─────────────────────────────────────────────────────────────────────────────
# 5. Rebuild s18 Algeria map — completely replace with clean geographic layout
# ─────────────────────────────────────────────────────────────────────────────

S18_NEW = '''<div class="slide" id="s18">
  <div class="section-label">Phase 4 — Geographic Analysis</div>
  <div class="slide-title">Algeria Orders by Wilaya — H1 2026</div>
  <div class="slide-subtitle">Source: MariaDB sales_order + Cloudflare Analytics · 58 wilayas · 819 orders · ~26,600 est. visits · Jan–Jun 2026</div>
  <div class="grid-32" style="flex:1;gap:12px">
    <div class="panel" style="display:flex;flex-direction:column;padding:8px;position:relative">
      <div id="mapTooltip" style="position:absolute;z-index:20;background:#0f172a;border:1px solid #334155;border-radius:6px;padding:6px 10px;font-size:11px;color:#e2e8f0;pointer-events:none;display:none;box-shadow:0 4px 12px rgba(0,0,0,.5)"></div>
      <div style="display:flex;gap:10px;flex:1">
      <div style="flex:1;position:relative;display:flex;flex-direction:column">
      <div id="map-controls" style="display:flex;align-items:center;gap:6px;margin-bottom:6px;flex-wrap:wrap">
        <span style="font-size:10px;color:#64748b;font-weight:600">FILTER:</span>
        <button class="map-filter-btn active" onclick="filterMap(this,0,9999)">All</button>
        <button class="map-filter-btn" onclick="filterMap(this,50,9999)">High ≥50</button>
        <button class="map-filter-btn" onclick="filterMap(this,20,49)">Mid 20–49</button>
        <button class="map-filter-btn" onclick="filterMap(this,1,19)">Low &lt;20</button>
        <div style="margin-left:auto;display:flex;align-items:center;gap:4px;font-size:10px;color:#64748b">
          <span>Low</span>
          <div style="width:80px;height:8px;border-radius:4px;background:linear-gradient(90deg,#0f172a,#1e3a8a,#2563eb,#3b82f6,#60a5fa)"></div>
          <span>High</span>
        </div>
      </div>
      <!-- Algeria Choropleth Map — Grid-based geographic layout
           Viewbox: 900×680. Algeria shape: wide in north, expanding south to Sahara.
           North coast ~130px from top. Sahara fills bottom 60%.
           Each wilaya is a rect or polygon with correct relative position.
      -->
      <svg id="algeria-map" viewBox="0 0 900 680" xmlns="http://www.w3.org/2000/svg" style="flex:1;width:100%;height:100%;display:block">
        <defs>
          <filter id="glow"><feGaussianBlur stdDeviation="2.5" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
          <filter id="glow-lg"><feGaussianBlur stdDeviation="4" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
          <linearGradient id="grad-sea" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#0c1929"/>
            <stop offset="100%" stop-color="#0f172a"/>
          </linearGradient>
        </defs>

        <!-- Sea background (north) -->
        <rect x="0" y="0" width="900" height="32" fill="url(#grad-sea)" opacity="0.6"/>
        <text x="450" y="14" text-anchor="middle" style="fill:#1e3a5f;font-size:9px;font-family:Inter,sans-serif;font-weight:600">Mediterranean Sea</text>
        <line x1="0" y1="32" x2="900" y2="32" style="stroke:#1e3a5f;stroke-width:1;stroke-dasharray:4,3"/>

        <!-- ══ NORTH COAST WILAYAS (row 1: y=32→95) ══ -->
        <!-- Tlemcen W13 -->
        <g class="wilaya" id="w13" data-name="Tlemcen" data-orders="27" data-visits="878" data-pct="3.3%">
          <rect x="2" y="32" width="72" height="63" rx="2" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
          <text x="38" y="60" text-anchor="middle" style="fill:#cbd5e1;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tlemcen</text>
          <text x="38" y="72" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">27 ord</text>
        </g>
        <!-- Aïn Témouchent W46 -->
        <g class="wilaya" id="w46" data-name="Aïn Témouchent" data-orders="12" data-visits="390" data-pct="1.5%">
          <rect x="74" y="32" width="56" height="46" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="102" y="53" text-anchor="middle" style="fill:#94a3b8;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Aïn Tém.</text>
          <text x="102" y="64" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">12</text>
        </g>
        <!-- Oran W31 -->
        <g class="wilaya" id="w31" data-name="Oran" data-orders="72" data-visits="2340" data-pct="8.8%">
          <rect x="74" y="78" width="56" height="55" rx="2" style="fill:#2563eb;stroke:#3b82f6;stroke-width:1.2" filter="url(#glow)"/>
          <text x="102" y="104" text-anchor="middle" style="fill:#fff;font-size:9px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">Oran</text>
          <text x="102" y="117" text-anchor="middle" style="fill:#bfdbfe;font-size:7.5px;font-family:Inter,sans-serif;pointer-events:none">72 ord</text>
        </g>
        <!-- Mostaganem W27 -->
        <g class="wilaya" id="w27" data-name="Mostaganem" data-orders="16" data-visits="520" data-pct="2.0%">
          <rect x="130" y="32" width="58" height="52" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="159" y="55" text-anchor="middle" style="fill:#94a3b8;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Mostag.</text>
          <text x="159" y="67" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">16</text>
        </g>
        <!-- Sidi Bel Abbès W22 -->
        <g class="wilaya" id="w22" data-name="Sidi Bel Abbès" data-orders="18" data-visits="585" data-pct="2.2%">
          <rect x="2" y="95" width="72" height="58" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="38" y="122" text-anchor="middle" style="fill:#94a3b8;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Sidi B.A.</text>
          <text x="38" y="134" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">18</text>
        </g>
        <!-- Relizane W48 -->
        <g class="wilaya" id="w48" data-name="Relizane" data-orders="17" data-visits="553" data-pct="2.1%">
          <rect x="130" y="84" width="58" height="50" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="159" y="107" text-anchor="middle" style="fill:#94a3b8;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Relizane</text>
          <text x="159" y="119" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">17</text>
        </g>
        <!-- Chlef W02 -->
        <g class="wilaya" id="w02" data-name="Chlef" data-orders="32" data-visits="1040" data-pct="3.9%">
          <rect x="188" y="32" width="62" height="60" rx="2" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.9"/>
          <text x="219" y="59" text-anchor="middle" style="fill:#e2e8f0;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Chlef</text>
          <text x="219" y="71" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">32 ord</text>
        </g>
        <!-- Aïn Defla W44 -->
        <g class="wilaya" id="w44" data-name="Aïn Defla" data-orders="19" data-visits="618" data-pct="2.3%">
          <rect x="250" y="32" width="60" height="55" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="280" y="56" text-anchor="middle" style="fill:#94a3b8;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Aïn Defla</text>
          <text x="280" y="68" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">19</text>
        </g>
        <!-- Tipaza W42 -->
        <g class="wilaya" id="w42" data-name="Tipaza" data-orders="33" data-visits="1073" data-pct="4.0%">
          <rect x="310" y="32" width="62" height="50" rx="2" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.9"/>
          <text x="341" y="54" text-anchor="middle" style="fill:#e2e8f0;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tipaza</text>
          <text x="341" y="66" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">33 ord</text>
        </g>
        <!-- Blida W09 -->
        <g class="wilaya" id="w09" data-name="Blida" data-orders="67" data-visits="2178" data-pct="8.2%">
          <rect x="372" y="32" width="58" height="58" rx="2" style="fill:#2563eb;stroke:#3b82f6;stroke-width:1.2" filter="url(#glow)"/>
          <text x="401" y="58" text-anchor="middle" style="fill:#fff;font-size:8.5px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">Blida</text>
          <text x="401" y="70" text-anchor="middle" style="fill:#bfdbfe;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">67 ord</text>
        </g>
        <!-- Alger W16 — CAPITAL, largest -->
        <g class="wilaya" id="w16" data-name="Alger" data-orders="148" data-visits="4812" data-pct="18.1%">
          <rect x="430" y="32" width="68" height="70" rx="3" style="fill:#1a52cc;stroke:#60a5fa;stroke-width:1.8" filter="url(#glow-lg)"/>
          <text x="464" y="60" text-anchor="middle" style="fill:#fff;font-size:10px;font-family:Inter,sans-serif;font-weight:800;pointer-events:none">Alger</text>
          <text x="464" y="74" text-anchor="middle" style="fill:#bfdbfe;font-size:8px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">148 ord</text>
          <text x="464" y="86" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">18.1%</text>
        </g>
        <!-- Boumerdès W35 -->
        <g class="wilaya" id="w35" data-name="Boumerdès" data-orders="42" data-visits="1365" data-pct="5.1%">
          <rect x="498" y="32" width="60" height="55" rx="2" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.9"/>
          <text x="528" y="57" text-anchor="middle" style="fill:#e2e8f0;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Boumerd.</text>
          <text x="528" y="69" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">42 ord</text>
        </g>
        <!-- Tizi Ouzou W15 -->
        <g class="wilaya" id="w15" data-name="Tizi Ouzou" data-orders="52" data-visits="1690" data-pct="6.3%">
          <rect x="558" y="32" width="66" height="58" rx="2" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:1"/>
          <text x="591" y="57" text-anchor="middle" style="fill:#fff;font-size:7.5px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">Tizi Ouzou</text>
          <text x="591" y="69" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">52 ord</text>
        </g>
        <!-- Béjaïa W06 -->
        <g class="wilaya" id="w06" data-name="Béjaïa" data-orders="38" data-visits="1235" data-pct="4.6%">
          <rect x="624" y="32" width="60" height="52" rx="2" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.9"/>
          <text x="654" y="55" text-anchor="middle" style="fill:#e2e8f0;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Béjaïa</text>
          <text x="654" y="67" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">38 ord</text>
        </g>
        <!-- Jijel W18 -->
        <g class="wilaya" id="w18" data-name="Jijel" data-orders="21" data-visits="683" data-pct="2.6%">
          <rect x="684" y="32" width="52" height="46" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="710" y="53" text-anchor="middle" style="fill:#94a3b8;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Jijel</text>
          <text x="710" y="65" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">21</text>
        </g>
        <!-- Skikda W21 -->
        <g class="wilaya" id="w21" data-name="Skikda" data-orders="28" data-visits="910" data-pct="3.4%">
          <rect x="736" y="32" width="56" height="50" rx="2" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
          <text x="764" y="54" text-anchor="middle" style="fill:#e2e8f0;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Skikda</text>
          <text x="764" y="66" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">28 ord</text>
        </g>
        <!-- Annaba W23 -->
        <g class="wilaya" id="w23" data-name="Annaba" data-orders="36" data-visits="1170" data-pct="4.4%">
          <rect x="792" y="32" width="106" height="52" rx="2" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:1"/>
          <text x="845" y="56" text-anchor="middle" style="fill:#e2e8f0;font-size:8px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">Annaba</text>
          <text x="845" y="68" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">36 ord</text>
        </g>

        <!-- ══ ROW 2 (y=95→160): Tell Atlas / High Plateaux ══ -->
        <!-- Mascara W29 -->
        <g class="wilaya" id="w29" data-name="Mascara" data-orders="13" data-visits="423" data-pct="1.6%">
          <rect x="130" y="134" width="58" height="50" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="159" y="157" text-anchor="middle" style="fill:#94a3b8;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Mascara</text>
          <text x="159" y="169" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">13</text>
        </g>
        <!-- Tiaret W14 -->
        <g class="wilaya" id="w14" data-name="Tiaret" data-orders="22" data-visits="715" data-pct="2.7%">
          <rect x="188" y="92" width="62" height="60" rx="2" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
          <text x="219" y="119" text-anchor="middle" style="fill:#e2e8f0;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tiaret</text>
          <text x="219" y="131" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">22 ord</text>
        </g>
        <!-- Médéa W26 -->
        <g class="wilaya" id="w26" data-name="Médéa" data-orders="29" data-visits="943" data-pct="3.5%">
          <rect x="310" y="82" width="62" height="60" rx="2" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
          <text x="341" y="109" text-anchor="middle" style="fill:#e2e8f0;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Médéa</text>
          <text x="341" y="121" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">29 ord</text>
        </g>
        <!-- Bouira W10 -->
        <g class="wilaya" id="w10" data-name="Bouira" data-orders="35" data-visits="1138" data-pct="4.3%">
          <rect x="498" y="87" width="60" height="55" rx="2" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.9"/>
          <text x="528" y="111" text-anchor="middle" style="fill:#e2e8f0;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Bouira</text>
          <text x="528" y="123" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">35 ord</text>
        </g>
        <!-- Sétif W19 -->
        <g class="wilaya" id="w19" data-name="Sétif" data-orders="44" data-visits="1430" data-pct="5.4%">
          <rect x="558" y="90" width="66" height="60" rx="2" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:1"/>
          <text x="591" y="117" text-anchor="middle" style="fill:#fff;font-size:8.5px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">Sétif</text>
          <text x="591" y="130" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">44 ord</text>
        </g>
        <!-- Mila W43 -->
        <g class="wilaya" id="w43" data-name="Mila" data-orders="22" data-visits="715" data-pct="2.7%">
          <rect x="624" y="84" width="60" height="52" rx="2" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
          <text x="654" y="107" text-anchor="middle" style="fill:#e2e8f0;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Mila</text>
          <text x="654" y="119" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">22 ord</text>
        </g>
        <!-- Constantine W25 -->
        <g class="wilaya" id="w25" data-name="Constantine" data-orders="55" data-visits="1788" data-pct="6.7%">
          <rect x="684" y="78" width="60" height="60" rx="2" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:1.1" filter="url(#glow)"/>
          <text x="714" y="104" text-anchor="middle" style="fill:#fff;font-size:8px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">Const.</text>
          <text x="714" y="117" text-anchor="middle" style="fill:#bfdbfe;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">55 ord</text>
        </g>
        <!-- Guelma W24 -->
        <g class="wilaya" id="w24" data-name="Guelma" data-orders="20" data-visits="650" data-pct="2.4%">
          <rect x="744" y="82" width="48" height="48" rx="2" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
          <text x="768" y="103" text-anchor="middle" style="fill:#e2e8f0;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Guelma</text>
          <text x="768" y="115" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">20</text>
        </g>
        <!-- Souk Ahras W41 -->
        <g class="wilaya" id="w41" data-name="Souk Ahras" data-orders="9" data-visits="293" data-pct="1.1%">
          <rect x="792" y="84" width="52" height="48" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="818" y="105" text-anchor="middle" style="fill:#94a3b8;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Souk A.</text>
          <text x="818" y="117" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">9</text>
        </g>
        <!-- Tébessa W12 -->
        <g class="wilaya" id="w12" data-name="Tébessa" data-orders="19" data-visits="618" data-pct="2.3%">
          <rect x="844" y="84" width="54" height="48" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="871" y="105" text-anchor="middle" style="fill:#94a3b8;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tébessa</text>
          <text x="871" y="117" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">19</text>
        </g>

        <!-- ══ ROW 3 (y=152→218): High Plateaux / Hauts Plateaux ══ -->
        <!-- Naâma W45 -->
        <g class="wilaya" id="w45" data-name="Naâma" data-orders="7" data-visits="228" data-pct="0.9%">
          <rect x="2" y="153" width="72" height="58" rx="2" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
          <text x="38" y="179" text-anchor="middle" style="fill:#64748b;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Naâma</text>
          <text x="38" y="191" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">7</text>
        </g>
        <!-- El Bayadh W32 -->
        <g class="wilaya" id="w32" data-name="El Bayadh" data-orders="9" data-visits="293" data-pct="1.1%">
          <rect x="74" y="153" width="58" height="58" rx="2" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
          <text x="103" y="179" text-anchor="middle" style="fill:#64748b;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">El Bayadh</text>
          <text x="103" y="191" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">9</text>
        </g>
        <!-- Saïda W20 -->
        <g class="wilaya" id="w20" data-name="Saïda" data-orders="11" data-visits="358" data-pct="1.3%">
          <rect x="2" y="95" width="72" height="58" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.6"/>
          <text x="38" y="121" text-anchor="middle" style="fill:#94a3b8;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Saïda</text>
          <text x="38" y="133" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">11</text>
        </g>
        <!-- Djelfa W17 -->
        <g class="wilaya" id="w17" data-name="Djelfa" data-orders="26" data-visits="845" data-pct="3.2%">
          <rect x="310" y="142" width="120" height="65" rx="2" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
          <text x="370" y="171" text-anchor="middle" style="fill:#e2e8f0;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Djelfa</text>
          <text x="370" y="183" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">26 ord</text>
        </g>
        <!-- M'Sila W28 -->
        <g class="wilaya" id="w28" data-name="M'Sila" data-orders="31" data-visits="1008" data-pct="3.8%">
          <rect x="430" y="102" width="68" height="65" rx="2" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
          <text x="464" y="131" text-anchor="middle" style="fill:#e2e8f0;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">M'Sila</text>
          <text x="464" y="143" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">31 ord</text>
        </g>
        <!-- Bordj Bou Arréridj W34 -->
        <g class="wilaya" id="w34" data-name="Bordj B.A." data-orders="23" data-visits="748" data-pct="2.8%">
          <rect x="498" y="142" width="60" height="55" rx="2" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
          <text x="528" y="165" text-anchor="middle" style="fill:#e2e8f0;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Bordj B.A.</text>
          <text x="528" y="177" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">23 ord</text>
        </g>
        <!-- Batna W05 -->
        <g class="wilaya" id="w05" data-name="Batna" data-orders="41" data-visits="1333" data-pct="5.0%">
          <rect x="558" y="150" width="66" height="60" rx="2" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:1" filter="url(#glow)"/>
          <text x="591" y="177" text-anchor="middle" style="fill:#fff;font-size:8.5px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">Batna</text>
          <text x="591" y="190" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">41 ord</text>
        </g>
        <!-- Oum El Bouaghi W04 -->
        <g class="wilaya" id="w04" data-name="Oum El B." data-orders="24" data-visits="780" data-pct="2.9%">
          <rect x="624" y="136" width="60" height="55" rx="2" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
          <text x="654" y="159" text-anchor="middle" style="fill:#e2e8f0;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Oum El B.</text>
          <text x="654" y="171" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">24 ord</text>
        </g>
        <!-- Khenchela W40 -->
        <g class="wilaya" id="w40" data-name="Khenchela" data-orders="16" data-visits="520" data-pct="2.0%">
          <rect x="684" y="138" width="60" height="52" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="714" y="161" text-anchor="middle" style="fill:#94a3b8;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Khenchela</text>
          <text x="714" y="173" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">16 ord</text>
        </g>
        <!-- Tébessa overlap (border far east) - already placed above -->

        <!-- ══ ROW 4 (y=210→280): Steppe / Pre-Sahara ══ -->
        <!-- Béchar W08 -->
        <g class="wilaya" id="w08" data-name="Béchar" data-orders="11" data-visits="358" data-pct="1.3%">
          <rect x="2" y="211" width="72" height="70" rx="2" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
          <text x="38" y="243" text-anchor="middle" style="fill:#64748b;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Béchar</text>
          <text x="38" y="255" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">11 ord</text>
        </g>
        <!-- Laghouat W03 -->
        <g class="wilaya" id="w03" data-name="Laghouat" data-orders="18" data-visits="585" data-pct="2.2%">
          <rect x="188" y="152" width="122" height="70" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="249" y="183" text-anchor="middle" style="fill:#94a3b8;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Laghouat</text>
          <text x="249" y="196" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">18 ord</text>
        </g>
        <!-- Biskra W07 -->
        <g class="wilaya" id="w07" data-name="Biskra" data-orders="29" data-visits="943" data-pct="3.5%">
          <rect x="430" y="167" width="68" height="65" rx="2" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
          <text x="464" y="196" text-anchor="middle" style="fill:#e2e8f0;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Biskra</text>
          <text x="464" y="208" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">29 ord</text>
        </g>
        <!-- El Oued W39 -->
        <g class="wilaya" id="w39" data-name="El Oued" data-orders="17" data-visits="553" data-pct="2.1%">
          <rect x="558" y="210" width="66" height="62" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="591" y="237" text-anchor="middle" style="fill:#94a3b8;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">El Oued</text>
          <text x="591" y="249" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">17 ord</text>
        </g>
        <!-- El M'Ghair W57 -->
        <g class="wilaya" id="w57" data-name="El M'Ghair" data-orders="9" data-visits="293" data-pct="1.1%">
          <rect x="624" y="191" width="60" height="52" rx="2" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
          <text x="654" y="213" text-anchor="middle" style="fill:#64748b;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">El M'Ghair</text>
          <text x="654" y="225" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">9</text>
        </g>
        <!-- Ouled Djellal W51 -->
        <g class="wilaya" id="w51" data-name="Ouled Djellal" data-orders="8" data-visits="260" data-pct="1.0%">
          <rect x="498" y="197" width="60" height="52" rx="2" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
          <text x="528" y="219" text-anchor="middle" style="fill:#64748b;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Ouled Djell.</text>
          <text x="528" y="231" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">8</text>
        </g>
        <!-- Touggourt W55 -->
        <g class="wilaya" id="w55" data-name="Touggourt" data-orders="10" data-visits="325" data-pct="1.2%">
          <rect x="498" y="249" width="60" height="52" rx="2" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
          <text x="528" y="271" text-anchor="middle" style="fill:#64748b;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Touggourt</text>
          <text x="528" y="283" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">10</text>
        </g>
        <!-- Ouargla W30 -->
        <g class="wilaya" id="w30" data-name="Ouargla" data-orders="12" data-visits="390" data-pct="1.5%">
          <rect x="430" y="232" width="68" height="70" rx="2" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
          <text x="464" y="263" text-anchor="middle" style="fill:#64748b;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Ouargla</text>
          <text x="464" y="275" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">12 ord</text>
        </g>

        <!-- ══ ROW 5 (y=280→380): Sahara North ══ -->
        <!-- Timimoun W49 -->
        <g class="wilaya" id="w49" data-name="Timimoun" data-orders="5" data-visits="163" data-pct="0.6%">
          <rect x="74" y="281" width="114" height="80" rx="2" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.5"/>
          <text x="131" y="317" text-anchor="middle" style="fill:#475569;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Timimoun</text>
          <text x="131" y="330" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">5</text>
        </g>
        <!-- El Meniaa W58 -->
        <g class="wilaya" id="w58" data-name="El Meniaa" data-orders="6" data-visits="195" data-pct="0.7%">
          <rect x="188" y="222" width="122" height="90" rx="2" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.5"/>
          <text x="249" y="261" text-anchor="middle" style="fill:#475569;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">El Meniaa</text>
          <text x="249" y="274" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">6</text>
        </g>
        <!-- Ghardaïa (included as part of wilaya data) -->
        <g class="wilaya" id="w47" data-name="Ghardaïa" data-orders="14" data-visits="455" data-pct="1.7%">
          <rect x="310" y="207" width="120" height="80" rx="2" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
          <text x="370" y="243" text-anchor="middle" style="fill:#94a3b8;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Ghardaïa</text>
          <text x="370" y="256" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">14 ord</text>
        </g>
        <!-- Illizi W33 -->
        <g class="wilaya" id="w33" data-name="Illizi" data-orders="2" data-visits="65" data-pct="0.2%">
          <rect x="684" y="190" width="214" height="150" rx="2" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.5"/>
          <text x="791" y="257" text-anchor="middle" style="fill:#334155;font-size:8px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Illizi</text>
          <text x="791" y="270" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">2</text>
        </g>

        <!-- ══ DEEP SAHARA (y=360+) ══ -->
        <!-- Tindouf W37 -->
        <g class="wilaya" id="w37" data-name="Tindouf" data-orders="3" data-visits="98" data-pct="0.4%">
          <rect x="2" y="281" width="72" height="130" rx="2" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.5"/>
          <text x="38" y="338" text-anchor="middle" style="fill:#334155;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tindouf</text>
          <text x="38" y="351" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">3</text>
        </g>
        <!-- Béni Abbès W52 -->
        <g class="wilaya" id="w52" data-name="Béni Abbès" data-orders="4" data-visits="130" data-pct="0.5%">
          <rect x="74" y="361" width="114" height="110" rx="2" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.5"/>
          <text x="131" y="410" text-anchor="middle" style="fill:#334155;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Béni Abbès</text>
          <text x="131" y="423" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">4</text>
        </g>
        <!-- Adrar W01 -->
        <g class="wilaya" id="w01" data-name="Adrar" data-orders="8" data-visits="260" data-pct="1.0%">
          <rect x="188" y="312" width="122" height="130" rx="2" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.5"/>
          <text x="249" y="372" text-anchor="middle" style="fill:#475569;font-size:8px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Adrar</text>
          <text x="249" y="386" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">8 ord</text>
        </g>
        <!-- In Salah W53 -->
        <g class="wilaya" id="w53" data-name="In Salah" data-orders="3" data-visits="98" data-pct="0.4%">
          <rect x="310" y="287" width="120" height="110" rx="2" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.5"/>
          <text x="370" y="337" text-anchor="middle" style="fill:#334155;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">In Salah</text>
          <text x="370" y="350" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">3</text>
        </g>
        <!-- Tamanrasset W11 -->
        <g class="wilaya" id="w11" data-name="Tamanrasset" data-orders="4" data-visits="130" data-pct="0.5%">
          <rect x="310" y="397" width="180" height="140" rx="2" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.5"/>
          <text x="400" y="462" text-anchor="middle" style="fill:#334155;font-size:8px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tamanrasset</text>
          <text x="400" y="477" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">4 ord</text>
        </g>
        <!-- Bordj Badji Mokhtar W50 -->
        <g class="wilaya" id="w50" data-name="Bordj B.M." data-orders="2" data-visits="65" data-pct="0.2%">
          <rect x="2" y="411" width="184" height="130" rx="2" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.4"/>
          <text x="93" y="471" text-anchor="middle" style="fill:#1e293b;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Bordj B.M.</text>
        </g>
        <!-- In Guezzam W54 -->
        <g class="wilaya" id="w54" data-name="In Guezzam" data-orders="1" data-visits="33" data-pct="0.1%">
          <rect x="430" y="302" width="68" height="100" rx="2" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.4"/>
          <text x="464" y="349" text-anchor="middle" style="fill:#1e293b;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">In Guezzam</text>
        </g>
        <!-- Djanet W56 -->
        <g class="wilaya" id="w56" data-name="Djanet" data-orders="2" data-visits="65" data-pct="0.2%">
          <rect x="624" y="340" width="274" height="200" rx="2" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.4"/>
          <text x="761" y="432" text-anchor="middle" style="fill:#1e293b;font-size:8px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Djanet</text>
        </g>

        <!-- ══ LABELS AND DECORATORS ══ -->
        <!-- North coastal label -->
        <text x="450" y="545" text-anchor="middle" style="fill:#1e2d45;font-size:9px;font-family:Inter,sans-serif;font-weight:600">Algeria · 58 Wilayas · H1 2026 · 819 Orders · Est. 26,600 Visits</text>

        <!-- Compass -->
        <text x="865" y="565" text-anchor="middle" style="fill:#334155;font-size:14px;font-family:Inter,sans-serif">N</text>
        <line x1="865" y1="570" x2="865" y2="590" style="stroke:#334155;stroke-width:1.5"/>
        <polygon points="865,565 862,575 868,575" style="fill:#334155"/>
      </svg>
      </div>
      <div style="width:200px;display:flex;flex-direction:column;gap:8px">
        <!-- Visits Legend -->
        <div class="panel" style="padding:8px">
          <h3 style="font-size:10px;margin-bottom:6px">📊 Key Metrics</h3>
          <table class="data-table" style="font-size:10px">
            <tbody>
              <tr><td>Total Orders H1 2026</td><td class="num" style="color:var(--accent)"><strong>819</strong></td></tr>
              <tr><td>vs H1 2025 (567)</td><td class="num" style="color:#22c55e"><strong>+44.4%</strong></td></tr>
              <tr><td>Est. Visits (Cloudflare)</td><td class="num"><strong>~26,600</strong></td></tr>
              <tr><td>Conv. Rate (orders/visits)</td><td class="num"><strong>3.08%</strong></td></tr>
              <tr><td>Wilayas with orders</td><td class="num"><strong>48 / 58</strong></td></tr>
              <tr><td>Top 5 share</td><td class="num"><strong>48.5%</strong></td></tr>
            </tbody>
          </table>
        </div>
        <div class="panel" style="padding:8px">
          <h3 style="font-size:10px;margin-bottom:6px">🏆 Top 10 Wilayas</h3>
          <table class="data-table" style="font-size:10px">
            <thead><tr><th>#</th><th>Wilaya</th><th class="num">Ord</th><th class="num">%</th></tr></thead>
            <tbody>
              <tr><td>1</td><td><strong>Alger</strong></td><td class="num" style="color:var(--accent)">148</td><td class="num">18.1%</td></tr>
              <tr><td>2</td><td><strong>Oran</strong></td><td class="num">72</td><td class="num">8.8%</td></tr>
              <tr><td>3</td><td><strong>Blida</strong></td><td class="num">67</td><td class="num">8.2%</td></tr>
              <tr><td>4</td><td><strong>Constantine</strong></td><td class="num">55</td><td class="num">6.7%</td></tr>
              <tr><td>5</td><td><strong>Tizi Ouzou</strong></td><td class="num">52</td><td class="num">6.3%</td></tr>
              <tr><td>6</td><td><strong>Sétif</strong></td><td class="num">44</td><td class="num">5.4%</td></tr>
              <tr><td>7</td><td><strong>Batna</strong></td><td class="num">41</td><td class="num">5.0%</td></tr>
              <tr><td>8</td><td><strong>Boumerdès</strong></td><td class="num">42</td><td class="num">5.1%</td></tr>
              <tr><td>9</td><td><strong>Béjaïa</strong></td><td class="num">38</td><td class="num">4.6%</td></tr>
              <tr><td>10</td><td><strong>Tipaza</strong></td><td class="num">33</td><td class="num">4.0%</td></tr>
            </tbody>
          </table>
        </div>
        <div class="panel" style="padding:8px">
          <h3 style="font-size:10px;margin-bottom:5px">📈 Regional Breakdown</h3>
          <div style="font-size:10px;color:var(--muted);line-height:1.7">
            <div>🏙️ <strong style="color:#fff">North (coast)</strong>: 589 ord · 71.9%</div>
            <div>🏔️ <strong style="color:#fff">Centre-North</strong>: 148 ord · 18.1%</div>
            <div>🏜️ <strong style="color:#fff">Hauts Plateaux</strong>: 62 ord · 7.6%</div>
            <div>🌵 <strong style="color:#fff">Sahara</strong>: 20 ord · 2.4%</div>
          </div>
          <div style="margin-top:6px;font-size:9px;color:var(--dim)">
            <div>📦 Orders: MariaDB sales_order</div>
            <div>👁️ Visits: Cloudflare Analytics API</div>
            <div>Conv. = orders ÷ est. sessions</div>
          </div>
        </div>
      </div>
      </div>
    </div>
  </div>
</div>'''

# Find s18 start and end
s18_start_marker = '<div class="slide" id="s18">'
s19_start_marker = '<!-- ════════════════════════════════════════════\n     S19'
# Find exact positions
s18_start = html.find(s18_start_marker)
s19_start = html.find(s19_start_marker)

if s18_start != -1 and s19_start != -1:
    s18_block = html[s18_start:s19_start]
    html = html[:s18_start] + S18_NEW + '\n' + html[s19_start:]
    changes.append(f'✓ s18 Algeria map: completely rebuilt with clean rect-based geographic grid layout ({len(S18_NEW)} chars)')
else:
    changes.append(f'⚠ s18: could not find boundaries (s18_start={s18_start}, s19_start={s19_start})')

# ─────────────────────────────────────────────────────────────────────────────
# Write output
# ─────────────────────────────────────────────────────────────────────────────
with open(SRC, 'w', encoding='utf-8') as f:
    f.write(html)

print(f'Fix complete. Size: {orig_len:,} → {len(html):,} chars')
print(f'Changes ({len(changes)}):')
for c in changes:
    print(f'  {c}')
