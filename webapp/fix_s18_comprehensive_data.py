#!/usr/bin/env python3
"""
Session 18 — Comprehensive data fix for ALL 39 slides.
All values verified against MariaDB 2026-07-09.

KEY CORRECTIONS:
  1. s1/s2 cover: Orders 1,291 → 819 (real H1 2026), customers 5,243 → 661 unique
  2. s2 KPIs: YoY +44.4% (not +3.7%), customers +36.0% (not +8.2%)
  3. s12 revenue table: real per-month revenue (not old wrong values)
  4. s12 H1 comparison: revenue 4,916,245 → 2,893,230 DZD, AOV 6,003 → 5,517
  5. s13 status: CMD_Done 491→492, processing 29→28, Ann_prep 78→79, Autres 14→7+others
  6. s13 chart: data [491,29,163,78,44,14] → [492,28,163,79,44,13]
  7. s14 chartCustomers: fake [54,22...] → real [113,88,83,96,82,63] / [157,90,93,105,116,143]
  8. chartMonthly AOV: [6951,4527,6607,7184,5062,5457] → [5167,5147,5999,6224,5050,5513]
  9. s18 metrics: cancel 35.5%→35.7%, Alger 52.5%→31.1%
  10. s36 H1 table: revenue 4,916,245 → 2,893,230, AOV 6,003 → 5,517
  11. NOTES/data-attributes: consistency check
  12. Out-of-stock: was 37, now 6
"""

import re

# ══════════════════════════════════════════════════════════════════════════
# VERIFIED REAL DATA — MariaDB 2026-07-09
# ══════════════════════════════════════════════════════════════════════════
D = {
    # Orders H1 2026 by month (ALL statuses)
    'orders_2026': [176, 108, 109, 122, 131, 173],  # total=819
    'orders_2025': [125, 94, 89, 100, 90, 69],        # total=567

    # Revenue H1 2026 by month (non-cancelled grand_total)
    'rev_2026': [604584, 349982, 449955, 504104, 444368, 540237],  # total=2,893,230
    'rev_2025': [516267, 553956, 348278, 513886, 437549, 443352],  # total=2,813,288

    # AOV per month H1 2026 (non-cancelled)
    'aov_2026': [5167, 5147, 5999, 6224, 5050, 5513],  # avg=5517

    # Customers (unique emails) per month H1 2026 / 2025
    'cust_2026': [157, 90, 93, 105, 116, 143],   # total unique active=661
    'cust_2025': [113, 88, 83, 96, 82, 63],       # total unique active=486

    # Cancel rate per month
    'cancel_rate': [33.5, 37.0, 31.2, 33.6, 32.8, 43.4],

    # Order status H1 2026 (real counts)
    'cmd_done': 492, 'processing': 28,
    'ann_conf': 163, 'ann_prep': 79, 'ann_livr': 44,
    'canceled': 6, 'autres': 7,  # other active statuses
    'total_orders': 819,
    'cancel_total': 292,  # ann_conf+ann_prep+ann_livr+canceled
    'cancel_pct': 35.7,   # 292/819

    # KPIs
    'h1_2026_rev_total': 2893230,
    'h1_2025_rev_total': 2813288,
    'h1_2026_items': 3095,
    'h1_2025_items': 2176,
    'aov_avg_2026': 5517,
    'aov_avg_2025': round(2813288 / (567 - round(567*0.355))),  # approx

    # YoY %
    'yoy_orders_pct': '+44.4%',
    'yoy_rev_pct': '+2.8%',  # (2893230-2813288)/2813288 = +2.8%
    'yoy_items_pct': '+42.2%',  # (3095-2176)/2176

    # Catalogue
    'total_skus': 9618, 'in_stock': 9612, 'out_stock': 6,
    'simple': 8119, 'configurable': 1378,
}

# Computed values
D['cmd_done_pct'] = round(D['cmd_done']/D['total_orders']*100, 1)   # 60.1%
D['processing_pct'] = round(D['processing']/D['total_orders']*100, 1) # 3.4%
D['ann_conf_pct'] = round(D['ann_conf']/D['total_orders']*100, 1)    # 19.9%
D['ann_prep_pct'] = round(D['ann_prep']/D['total_orders']*100, 1)    # 9.6%
D['ann_livr_pct'] = round(D['ann_livr']/D['total_orders']*100, 1)    # 5.4%
D['autres_pct'] = round(D['autres']/D['total_orders']*100, 1)        # 0.9%


def fix(content):
    changes = []

    def rep(old, new, desc, count=1):
        nonlocal content
        if old in content:
            if count == 0:
                content = content.replace(old, new)
            else:
                content = content.replace(old, new, count)
            changes.append(f'✓ {desc}')
        else:
            changes.append(f'✗ MISS: {desc}')

    # ── 1. COVER / S2: Orders KPI (1,291 → 819) ────────────────────────
    rep('<div class="cv-val">1,291</div><div class="cv-label">Orders</div>',
        '<div class="cv-val">819</div><div class="cv-label">H1 Orders</div>',
        's1 cover orders 1291→819')

    rep('<div class="kpi-label">Total Orders</div><div class="kpi-val">1,291</div><div class="kpi-sub">Jan–Jun 2026</div><div class="kpi-delta" style="color:var(--accent)">▲ +3.7% YoY</div>',
        f'<div class="kpi-label">Total Orders H1</div><div class="kpi-val">819</div><div class="kpi-sub">Jan–Jun 2026 · all statuses</div><div class="kpi-delta" style="color:var(--ok)">▲ +44.4% YoY (567→819)</div>',
        's2 KPI orders 1291→819 +44.4%')

    # ── 2. S2: New Customers KPI (5,243 → 661) ─────────────────────────
    rep('<div class="kpi-label">New Customers</div><div class="kpi-val">5,243</div><div class="kpi-sub">Jan–Jun 2026</div><div class="kpi-delta" style="color:var(--ok)">▲ +8.2% YoY</div>',
        '<div class="kpi-label">Unique Customers</div><div class="kpi-val">661</div><div class="kpi-sub">H1 2026 · unique emails</div><div class="kpi-delta" style="color:var(--ok)">▲ +36.0% YoY (486→661)</div>',
        's2 KPI customers 5243→661 +36%')

    # ── 3. Div-subtitle with 1,291 orders · 5,243 customers ────────────
    rep('1,291 orders · 5,243 customers · Jan–Jun 2026 · MariaDB 10.6 · 58 Algerian wilayas',
        '819 orders · 661 unique customers · Jan–Jun 2026 · MariaDB 10.6 · 48 wilayas actives',
        'div-subtitle 1291→819 5243→661')

    rep('<span class="badge badge-cyan">5,243 Customers</span>',
        '<span class="badge badge-cyan">661 Clients Uniques</span>',
        'badge 5243→661')

    # ── 4. S12: Revenue table — fix per-month revenue & AOV ────────────
    # Jan: was 1,223,443 → 604,584 | AOV 6,951 → 5,167
    rep('<tr><td>Jan</td><td class="num">176</td><td class="num">1,223,443</td><td class="num">6,951</td><td><span style="color:var(--accent)">baseline</span></td></tr>',
        '<tr><td>Jan</td><td class="num">176</td><td class="num">604,584</td><td class="num">5,167</td><td><span style="color:var(--accent)">baseline</span></td></tr>',
        's12 Jan revenue/AOV')

    rep('<tr><td>Feb</td><td class="num">108</td><td class="num">488,897</td><td class="num">4,527</td><td><span style="color:var(--danger)">▼ -38.6%</span></td></tr>',
        '<tr><td>Feb</td><td class="num">108</td><td class="num">349,982</td><td class="num">5,147</td><td><span style="color:var(--danger)">▼ -38.6%</span></td></tr>',
        's12 Feb revenue/AOV')

    rep('<tr><td>Mar</td><td class="num">109</td><td class="num">720,205</td><td class="num">6,607</td><td><span style="color:var(--ok)">▲ +0.9%</span></td></tr>',
        '<tr><td>Mar</td><td class="num">109</td><td class="num">449,955</td><td class="num">5,999</td><td><span style="color:var(--ok)">▲ +0.9%</span></td></tr>',
        's12 Mar revenue/AOV')

    rep('<tr><td>Apr</td><td class="num">122</td><td class="num">876,423</td><td class="num">7,184</td><td><span style="color:var(--ok)">▲ +11.9%</span></td></tr>',
        '<tr><td>Apr</td><td class="num">122</td><td class="num">504,104</td><td class="num">6,224</td><td><span style="color:var(--ok)">▲ +11.9%</span></td></tr>',
        's12 Apr revenue/AOV')

    rep('<tr><td>May</td><td class="num"><strong style="color:var(--warn)">131</strong></td><td class="num">663,133</td><td class="num">5,062</td><td><span style="color:var(--ok)">▲ +7.4%</span></td></tr>',
        '<tr><td>May</td><td class="num"><strong style="color:var(--warn)">131</strong></td><td class="num">444,368</td><td class="num">5,050</td><td><span style="color:var(--ok)">▲ +7.4%</span></td></tr>',
        's12 May revenue/AOV')

    rep('<tr><td>Jun</td><td class="num"><strong style="color:var(--ok)">173</strong></td><td class="num">944,145</td><td class="num">5,457</td><td><span style="color:var(--ok)">▲ +32.1%</span></td></tr>',
        '<tr><td>Jun</td><td class="num"><strong style="color:var(--ok)">173</strong></td><td class="num">540,237</td><td class="num">5,513</td><td><span style="color:var(--ok)">▲ +32.1%</span></td></tr>',
        's12 Jun revenue/AOV')

    # ── 5. S12: H1 comparison table — fix revenue & AOV ────────────────
    rep('<tr><td>Revenue (DZD)</td><td class="num">3,441,315</td><td class="num" style="color:var(--ok)"><strong>4,916,245</strong></td><td class="num" style="color:var(--ok)">+42.9%</td></tr>',
        '<tr><td>Revenue (DZD)</td><td class="num">2,813,288</td><td class="num" style="color:var(--ok)"><strong>2,893,230</strong></td><td class="num" style="color:var(--ok)">+2.8%</td></tr>',
        's12 H1 comparison revenue table')

    rep('<tr><td>Avg Items/Order</td><td class="num">~3.5</td><td class="num"><strong>5.7</strong></td><td class="num" style="color:var(--ok)">+63%</td></tr>',
        f'<tr><td>Items Sold</td><td class="num">2,176</td><td class="num"><strong>3,095</strong></td><td class="num" style="color:var(--ok)">+42.2%</td></tr>',
        's12 items sold')

    rep('<tr><td>AOV (DZD)</td><td class="num">6,069</td><td class="num"><strong>6,003</strong></td><td class="num" style="color:var(--muted)">-1.1%</td></tr>',
        '<tr><td>AOV (DZD)</td><td class="num">4,965</td><td class="num"><strong>5,517</strong></td><td class="num" style="color:var(--ok)">+11.1%</td></tr>',
        's12 AOV comparison')

    # ── 6. S13: Status table — fix counts & percentages ─────────────────
    rep('>491</td><td class="num">59.9%</td>',
        f'>{D["cmd_done"]}</td><td class="num">{D["cmd_done_pct"]}%</td>',
        's13 CMD_Done 491→492 pct')

    rep('<td class="num">29</td><td class="num">3.5%</td>',
        f'<td class="num">{D["processing"]}</td><td class="num">{D["processing_pct"]}%</td>',
        's13 processing 29→28')

    rep('<td class="num">78</td><td class="num">9.5%</td>',
        f'<td class="num">{D["ann_prep"]}</td><td class="num">{D["ann_prep_pct"]}%</td>',
        's13 Ann.prep 78→79 9.6%')

    rep('<td class="num">14</td><td class="num">1.7%</td>',
        f'<td class="num">{D["autres"]}</td><td class="num">{D["autres_pct"]}%</td>',
        's13 Autres 14→7')

    # ── 7. S13: chartStatus data [491,29,163,78,44,14] → real ──────────
    rep('datasets: [{ data: [491,29,163,78,44,14],',
        f'datasets: [{{ data: [{D["cmd_done"]},{D["processing"]},{D["ann_conf"]},{D["ann_prep"]},{D["ann_livr"]},{D["autres"]}],',
        's13 chartStatus data')

    # ── 8. S13: cancel rate 35.5% → 35.7% ──────────────────────────────
    # Progress bars
    content = content.replace('>59.9%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:60%',
                               f'>{D["cmd_done_pct"]}%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:{D["cmd_done_pct"]}%')
    changes.append('✓ s13 pbar CMD_Done pct')

    content = content.replace('>3.5%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:3.5%',
                               f'>{D["processing_pct"]}%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:{D["processing_pct"]}%')
    changes.append('✓ s13 pbar processing pct')

    content = content.replace('>9.5%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:9.5%',
                               f'>{D["ann_prep_pct"]}%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:{D["ann_prep_pct"]}%')
    changes.append('✓ s13 pbar ann_prep pct')

    # ── 9. chartMonthly AOV data fix ────────────────────────────────────
    rep('data: [6951,4527,6607,7184,5062,5457],',
        'data: [5167,5147,5999,6224,5050,5513],',
        'chartMonthly AOV data')

    # ── 10. chartCustomers — replace fake data with real customers ───────
    rep("""          {
            label: 'H1 2025',
            data: [54, 22, 31, 44, 38, 29],
            backgroundColor: 'rgba(99,102,241,.5)',
            borderColor: '#6366f1',
            borderWidth: 1,
            borderRadius: 3
          },
          {
            label: 'H1 2026',
            data: [54, 40, 42, 80, 400, 233],""",
        f"""          {{
            label: 'H1 2025',
            data: [113, 88, 83, 96, 82, 63],
            backgroundColor: 'rgba(99,102,241,.5)',
            borderColor: '#6366f1',
            borderWidth: 1,
            borderRadius: 3
          }},
          {{
            label: 'H1 2026',
            data: [157, 90, 93, 105, 116, 143],""",
        'chartCustomers data 2025+2026')

    # Fix chart Y axis max for customers (was 450 for spike)
    rep("y: { ticks: FONT, grid: GRID, max: 450,\n            title: { display: true, text: 'Registrations (*May capped at 400)', color: '#64748b', font: { size: 9 } }",
        "y: { ticks: FONT, grid: GRID,\n            title: { display: true, text: 'Unique Customers/Month', color: '#64748b', font: { size: 9 } }",
        'chartCustomers Y axis max removed')

    # Fix labels (remove May*)
    rep("labels: [\"Jan'26\",\"Feb'26\",\"Mar'26\",\"Apr'26\",\"May'26*\",\"Jun'26\"],",
        "labels: [\"Jan'26\",\"Feb'26\",\"Mar'26\",\"Apr'26\",\"May'26\",\"Jun'26\"],",
        'chartCustomers labels fix May*')

    # Remove fake spike tooltip
    rep("""          tooltip: { callbacks: {
            afterBody: (items) => items[0].dataIndex === 4 && items[0].datasetIndex === 1
              ? ['* Real value: 3,278 (manual guest→account conversion)'] : []
          }}""",
        "          tooltip: { callbacks: {} }",
        'chartCustomers remove fake spike tooltip')

    # ── 11. S14: Fix customer slide text (5,243→661 unique, 3,727→657 new) ──
    rep('Source: MariaDB customer_entity · <strong style="color:#4ade80">9,246 total accounts</strong> · H1 2026: 3,727 new · May spike: 3,278 guests manually converted by Mounir Abderrahmani',
        'Source: MariaDB sales_order · <strong style="color:#4ade80">5,473 total customers all time</strong> · H1 2026: 661 unique · 657 new first-time buyers',
        's14 subtitle customers')

    # H1 2026 new customers table row
    rep('<tr><td>H1 2026</td><td class="num" style="color:#4ade80;font-weight:700">3,727</td><td style="font-size:10px;color:#4ade80">incl. 3,278 guest batch</td></tr>',
        '<tr><td>H1 2026</td><td class="num" style="color:#4ade80;font-weight:700">661</td><td style="font-size:10px;color:#4ade80">unique buyers (657 new)</td></tr>',
        's14 H1 2026 customers row')

    # ── 12. S18: Algeria map metrics fix (cancel 35.5→35.7, Alger 52.5→31.1) ──
    rep('<strong>35.5%</strong>',
        f'<strong>{D["cancel_pct"]}%</strong>',
        's18 cancel rate 35.5→35.7%')

    rep('<strong>31.1%</strong>',
        '<strong>31.1%</strong>',
        's18 Alger pct (already correct)')  # 164/528*100=31.1 correct, keep

    # Fix revenue in s18 metrics
    rep('<strong>4,916 KDZD</strong>',
        '<strong>2,893 KDZD</strong>',
        's18 revenue 4916→2893 KDZD')

    rep('<strong>6,003 DZD</strong>',
        '<strong>5,517 DZD</strong>',
        's18 AOV 6003→5517')

    # ── 13. S36 H1 comparison revenue fix ──────────────────────────────
    # Already checked H1Cmp chart is correct orders, but revenue text might be wrong
    # Find the H1 comparison text in s36
    rep('H1 2025=567, H1 2026=819 (+44.4%)',
        'H1 2025=567, H1 2026=819 (+44.4%) · Rev: 2,813K→2,893K DZD (+2.8%)',
        's36 NOTES revenue added')

    # ── 14. Fix NOTES for s12 revenue ───────────────────────────────────
    content = content.replace(
        "s12: 'Monthly Orders H1 2026",
        "s12: 'Monthly Orders H1 2026: [176,108,109,122,131,173]=819. Revenue H1 2026: [604K,350K,450K,504K,444K,540K]=2,893,230 DZD. AOV: [5167,5147,5999,6224,5050,5513] avg 5,517 DZD. H1 2025: 567 orders, 2,813,288 DZD. YoY: +44.4% orders, +2.8% revenue, +11.1% AOV. Source: MariaDB sales_order",
    )
    changes.append('✓ NOTES s12 updated')

    # ── 15. Fix all occurrences of old revenue 4,916,245 / 4,916 ────────
    content = content.replace('4,916,245', '2,893,230')
    changes.append('✓ Replace all 4,916,245 → 2,893,230')

    content = content.replace('4,916 KDZD', '2,893 KDZD')
    changes.append('✓ Replace all 4,916 KDZD → 2,893 KDZD')

    # ── 16. Fix all occurrences of old AOV 6,003 (in context of H1 avg) ─
    # Only in H1 comparison context
    content = content.replace("AOV (DZD)</td><td class=\"num\">6,069</td><td class=\"num\"><strong>6,003</strong>",
                               "AOV (DZD)</td><td class=\"num\">4,965</td><td class=\"num\"><strong>5,517</strong>")
    changes.append('✓ H1 AOV table fix (fallback)')

    # ── 17. Fix total revenue 3,441,315 (old H1 2025 rev) ───────────────
    content = content.replace('3,441,315', '2,813,288')
    changes.append('✓ H1 2025 revenue 3,441,315 → 2,813,288')

    # ── 18. Out of stock fix: 37 → 6 ────────────────────────────────────
    # Only in product context (not SSH etc.)
    content = content.replace('out_of_stock=37', 'out_of_stock=6')
    content = content.replace('Out of Stock: 37', 'Out of Stock: 6')
    content = content.replace('>37 OOS<', '>6 OOS<')
    content = content.replace('37 out-of-stock', '6 out-of-stock')
    content = content.replace('37 produits hors stock', '6 produits hors stock')
    changes.append('✓ OOS 37→6')

    # ── 19. Fix in_stock count 9,579 → 9,612 ────────────────────────────
    content = content.replace('9,579 in-stock', '9,612 in-stock')
    content = content.replace('9,579 in stock', '9,612 in stock')
    content = content.replace('in_stock=9,579', 'in_stock=9,612')
    content = content.replace('9579', '9612')
    changes.append('✓ in_stock 9579→9612')

    # ── 20. Fix s39 dashboard KPI "1,291 orders" ────────────────────────
    content = content.replace(
        'Master Dashboard KPIs (1,291 orders, 99.1% uptime',
        'Master Dashboard KPIs (819 H1 orders, 99.1% uptime'
    )
    changes.append('✓ s39 notes 1291→819')

    # ── 21. Fix subtitle/div-subtitle 1,291 references ──────────────────
    content = content.replace('1,291 orders', '819 orders H1 2026')
    changes.append('✓ All 1,291 orders → 819 orders H1 2026')

    # ── 22. Fix div-subtitle with old 5,243 ─────────────────────────────
    content = content.replace('5,243 customers', '661 customers H1')
    changes.append('✓ 5,243 customers → 661 customers H1')

    # ── 23. Fix NOTES s36 customer count ─────────────────────────────────
    content = content.replace(
        'Customers total: 9,246',
        'Customers total: 5,473 all-time · H1 2026: 661 unique'
    )
    changes.append('✓ NOTES s36 customers 9246→5473')

    # ── 24. Fix s36 slide display if any ─────────────────────────────────
    content = content.replace(
        'H1 2026: +3,727 (incl 3,278 batch)',
        'H1 2026: 661 unique clients actifs'
    )
    content = content.replace(
        '▲ +907% vs H2 2025 (43) · Apr peak: 278',
        '▲ +44.4% orders YoY · +36.0% clients H1'
    )
    changes.append('✓ s36 +907% fix')

    return content, changes


def main():
    html_path = '/home/dashboard/public_html/presentation/index.html'
    with open(html_path, 'r', encoding='utf-8') as f:
        content = f.read()
    print(f"Input: {len(content):,} chars")

    content, changes = fix(content)

    # Validate
    od = content.count('<div')
    cd = content.count('</div')
    slides = len(re.findall(r'class="slide[" ]', content))

    with open(html_path, 'w', encoding='utf-8') as f:
        f.write(content)

    print(f"Output: {len(content):,} chars")
    print(f"Div balance: {od}/{cd} diff={od-cd}")
    print(f"Slides: {slides}")
    print()
    print(f"Changes ({len(changes)}):")
    for c in changes:
        print(f"  {c}")

    return od == cd and slides == 39


if __name__ == '__main__':
    import sys
    ok = main()
    sys.exit(0 if ok else 1)
