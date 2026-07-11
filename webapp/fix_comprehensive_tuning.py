#!/usr/bin/env python3
"""
Comprehensive tuning script - fixes data accuracy, UI polish, and optimizations
Session 19 - 2026-07-09
"""

content = open('/home/dashboard/public_html/presentation/index.html').read()
original_size = len(content)
fixes_applied = []

def fix(old, new, label):
    global content
    if old in content:
        content = content.replace(old, new, 1)
        fixes_applied.append(f'✓ {label}')
        return True
    else:
        fixes_applied.append(f'✗ NOT FOUND: {label}')
        return False

# ============================================================
# 1. FIX s13 status revenue table
# Real values: CMD_Done=2,766,089; processing=112,125; ann_prep=688,919; autres=7 statuses
# ============================================================
fix(
    '<td class="num">2,750,249</td>',
    '<td class="num">2,766,089</td>',
    's13 CMD_Done revenue 2,750,249→2,766,089'
)
fix(
    '<td class="num">128,485</td>',
    '<td class="num">112,125</td>',
    's13 processing revenue 128,485→112,125'
)
fix(
    '<td class="num">686,519</td>',
    '<td class="num">688,919</td>',
    's13 ann_prep revenue 686,519→688,919'
)

# s13 "Autres" revenue (canceled=6,190 + Commande_preparee=4,090 + others=10,925 = 21,205)
# total_cancelled_autres = 4090+1390+3000+4000+2535 = 15,015 + 6190 = 21,205
fix(
    '<td class="num">23,605</td>',
    '<td class="num">21,205</td>',
    's13 autres revenue 23,605→21,205'
)

# s13 funnel text "9.5%" → "9.6%" (prep = 79/819 = 9.6%)
fix(
    '<div>📦 <strong style="color:#fff">Preparation stage</strong>: 9.5% — out of stock or wrong item</div>',
    '<div>📦 <strong style="color:#fff">Preparation stage</strong>: 9.6% — out of stock or wrong item</div>',
    's13 prep stage 9.5%→9.6%'
)

# ============================================================
# 2. FIX s14 new buyers count: 657 → 566
# ============================================================
fix(
    '657 new first-time buyers</div>',
    '566 new first-time buyers</div>',
    's14 subtitle new buyers 657→566'
)
fix(
    '657 nouveaux acheteurs (premier achat H1 2026)',
    '566 nouveaux acheteurs (premier achat H1 2026)',
    's14 panel nouveaux acheteurs 657→566'
)
fix(
    '<td style="font-size:10px;color:#4ade80">unique buyers (657 new)</td>',
    '<td style="font-size:10px;color:#4ade80">unique buyers (566 new)</td>',
    's14 table unique buyers 657→566'
)

# ============================================================
# 3. FIX s15 top products table - real DB data
# Real top 10: 
# 1. CARTON TOILE 280g → 322 units, 69,320 DZD, 28 orders
# 2. TOILE SUR CHASSIS 280g → 122 units, 109,350 DZD, 36 orders
# 3. PEINTURE ACRYLIQUE 100 ML → 96 units, 21,120 DZD, 20 orders
# 4. PEINTURE ACRYLIQUE 500 ml → 86 units, 63,360 DZD, 3 orders
# 5. PEINTURE ACRYLIQUE 200 ML BLANCHE → 54 units, 15,180 DZD, 19 orders
# 6. GOUACHE HAUTE QUALITE FLUO 300ml → 47 units, 58,750 DZD, 5 orders
# 7. ETIQUETTES BLANCHES A4=27 → 42 units, 102,900 DZD, 3 orders
# 8. PAPIER CREPON 50cmx200cm → 36 units, 5,400 DZD, 5 orders
# 9. CARTON TOILE 280g 18x24cm → 34 units, 5,940 DZD, 3 orders
# 10. MARQUEUR TABLEAU BLANC RECHARGEABLE → 31 units, 2,945 DZD, 5 orders
# ============================================================
old_products_table = '''            <tr>
              <td style="color:var(--accent)">1</td>
              <td><strong>Carton Toile 280g Coton "Techno"</strong><br><span style="font-size:9px;color:var(--dim)">Multi-formats (18×24, 24×30, 30×40, 40×50cm)</span></td>
              <td class="num" style="color:var(--ok)">574</td><td class="num">115,520</td><td class="num">62</td>
            </tr>
            <tr>
              <td style="color:var(--accent)">2</td>
              <td><strong>Étiquettes Blanches A4=27 "Techno" REF:5204</strong><br><span style="font-size:9px;color:var(--dim)">Paquet 100 feuilles</span></td>
              <td class="num" style="color:var(--ok)">102</td><td class="num" style="color:var(--warn)">249,900</td><td class="num">5</td>
            </tr>
            <tr>
              <td style="color:var(--accent)">3</td>
              <td><strong>Toile sur Châssis 280g Coton "Techno"</strong><br><span style="font-size:9px;color:var(--dim)">Multi-formats</span></td>
              <td class="num">139</td><td class="num">93,400</td><td class="num">53</td>
            </tr>
            <tr>
              <td style="color:var(--accent)">4</td>
              <td><strong>Peinture Acrylique 200ml Blanche Créa Color</strong><br><span style="font-size:9px;color:var(--dim)">"Techno" REF:7349</span></td>
              <td class="num">64</td><td class="num">17,980</td><td class="num">26</td>
            </tr>
            <tr>
              <td style="color:var(--accent)">5</td>
              <td><strong>Peinture Acrylique 100ml Créa Color "Techno"</strong><br><span style="font-size:9px;color:var(--dim)">Gamme couleurs</span></td>
              <td class="num">41</td><td class="num">9,020</td><td class="num">19</td>
            </tr>
            <tr>
              <td style="color:var(--accent)">6</td>
              <td><strong>Peinture Acrylique 200ml Coffret 6 Couleurs</strong><br><span style="font-size:9px;color:var(--dim)">"Techno" REF:7356</span></td>
              <td class="num">32</td><td class="num">55,100</td><td class="num">32</td>
            </tr>
            <tr>
              <td>7</td>
              <td><strong>Calculatrice Sci. 417F "CASIO" FX-991ES PLUS-2</strong></td>
              <td class="num">18</td><td class="num" style="color:var(--warn)">177,160</td><td class="num">13</td>
            </tr>
            <tr>
              <td>8</td>
              <td><strong>Marqueur Double Tête Artmark "Techno"</strong></td>
              <td class="num">11</td><td class="num">52,250</td><td class="num">11</td>
            </tr>
            <tr>
              <td>9</td>
              <td><strong>Caisse Enregistreuse "CASIO" SE-S100SG-BK</strong></td>
              <td class="num">5</td><td class="num" style="color:var(--danger)">215,000</td><td class="num">4</td>
            </tr>
            <tr>
              <td>10</td>
              <td><strong>Calc. Graphique Python "CASIO" GRAPH 35+E II</strong></td>
              <td class="num">4</td><td class="num" style="color:var(--warn)">90,000</td><td class="num">4</td>
            </tr>'''

new_products_table = '''            <tr>
              <td style="color:var(--accent)">1</td>
              <td><strong>Carton Toile 280g Coton "Techno"</strong><br><span style="font-size:9px;color:var(--dim)">SKU: 1140632000 · multi-formats</span></td>
              <td class="num" style="color:var(--ok)">322</td><td class="num">69,320</td><td class="num">28</td>
            </tr>
            <tr>
              <td style="color:var(--accent)">2</td>
              <td><strong>Toile sur Châssis 280g Coton "Techno"</strong><br><span style="font-size:9px;color:var(--dim)">SKU: 12 · multi-formats</span></td>
              <td class="num" style="color:var(--ok)">122</td><td class="num" style="color:var(--warn)">109,350</td><td class="num">36</td>
            </tr>
            <tr>
              <td style="color:var(--accent)">3</td>
              <td><strong>Peinture Acrylique 100ml Créa Color "Techno"</strong><br><span style="font-size:9px;color:var(--dim)">SKU: 1140631998</span></td>
              <td class="num">96</td><td class="num">21,120</td><td class="num">20</td>
            </tr>
            <tr>
              <td style="color:var(--accent)">4</td>
              <td><strong>Peinture Acrylique 500ml "Techno"</strong><br><span style="font-size:9px;color:var(--dim)">SKU: 1140631994</span></td>
              <td class="num">86</td><td class="num">63,360</td><td class="num">3</td>
            </tr>
            <tr>
              <td style="color:var(--accent)">5</td>
              <td><strong>Peinture Acrylique 200ml Blanche Créa Color "Techno"</strong><br><span style="font-size:9px;color:var(--dim)">SKU: 1140658821 · REF:7349</span></td>
              <td class="num">54</td><td class="num">15,180</td><td class="num">19</td>
            </tr>
            <tr>
              <td style="color:var(--accent)">6</td>
              <td><strong>Gouache Haute Qualité Fluo 300ml "Primo"</strong><br><span style="font-size:9px;color:var(--dim)">SKU: 1140632651</span></td>
              <td class="num">47</td><td class="num">58,750</td><td class="num">5</td>
            </tr>
            <tr>
              <td>7</td>
              <td><strong>Étiquettes Blanches A4=27 "Techno" REF:5204</strong><br><span style="font-size:9px;color:var(--dim)">SKU: 1140619233 · paquet 100F</span></td>
              <td class="num">42</td><td class="num" style="color:var(--warn)">102,900</td><td class="num">3</td>
            </tr>
            <tr>
              <td>8</td>
              <td><strong>Papier Crépon 50cm×200cm "Techno" REF:4014</strong><br><span style="font-size:9px;color:var(--dim)">SKU: 107574101</span></td>
              <td class="num">36</td><td class="num">5,400</td><td class="num">5</td>
            </tr>
            <tr>
              <td>9</td>
              <td><strong>Carton Toile 280g Coton 18×24cm "Techno" REF:4727</strong><br><span style="font-size:9px;color:var(--dim)">SKU: 1140612173</span></td>
              <td class="num">34</td><td class="num">5,940</td><td class="num">3</td>
            </tr>
            <tr>
              <td>10</td>
              <td><strong>Marqueur Tableau Blanc Rechargeable Tête Biseautée "Techno"</strong><br><span style="font-size:9px;color:var(--dim)">SKU: 495</span></td>
              <td class="num">31</td><td class="num">2,945</td><td class="num">5</td>
            </tr>'''

fix(old_products_table, new_products_table, 's15 top products table — 10 real DB products with correct quantities')

# ============================================================
# 4. FIX s15 catalogue — in_stock 9,579 → 9,612
# ============================================================
fix(
    '<tr><td>En stock (qty &gt; 0)</td><td class="num" style="color:var(--ok)">9,579</td></tr>',
    '<tr><td>En stock (is_in_stock=1)</td><td class="num" style="color:var(--ok)">9,612</td></tr>',
    's15 in_stock 9,579→9,612'
)

# ============================================================
# 5. FIX s14 subtitle: 5,473 → correct context (unique buyers)
# ============================================================
# Already correct: "5,473 total customers all time" is valid (distinct emails in orders)

# ============================================================
# 6. FIX CSS improvements - better visual hierarchy
# ============================================================
# Add panel-err class for danger panels 
old_css_panel_err = '.panel-warn{background:#1a120a;border:1px solid #5a3a0a;border-radius:10px;padding:14px 16px}'
new_css_panel_err = '.panel-warn{background:#1a120a;border:1px solid #5a3a0a;border-radius:10px;padding:14px 16px}.panel-err{background:#1a0808;border:1px solid #6a1f1f;border-radius:10px;padding:14px 16px}'
fix(old_css_panel_err, new_css_panel_err, 'CSS: add panel-err class')

# ============================================================
# 7. OPTIMIZE JS: Add IntersectionObserver for lazy chart init
# ============================================================
# Currently charts are all init'd when slide becomes active
# This is already efficient — skip heavy rewrite

# ============================================================
# 8. FIX s36 H2 2025 row: show real value
# ============================================================
fix(
    '<td class="num" style="color:#60a5fa">—</td><td style="font-size:10px;color:#64748b">Non calculé H2</td>',
    '<td class="num" style="color:#60a5fa">820</td><td style="font-size:10px;color:#64748b">H2 2025 unique buyers</td>',
    's14 H2 2025 unique customers —→820'
)

# ============================================================
# 9. GLOBAL UI IMPROVEMENTS
# ============================================================

# Improve slide-subtitle bottom margin for breathing room
old_subtitle_style = '.slide-subtitle{font-size:12.5px;color:var(--muted);margin-bottom:16px;line-height:1.45}'
new_subtitle_style = '.slide-subtitle{font-size:12.5px;color:var(--muted);margin-bottom:14px;line-height:1.5}'
fix(old_subtitle_style, new_subtitle_style, 'CSS: slide-subtitle line-height 1.45→1.5')

# Improve data-table hover contrast
old_table_hover = '.data-table tr:hover td{background:rgba(59,130,246,.04)}'
new_table_hover = '.data-table tr:hover td{background:rgba(59,130,246,.07)}'
fix(old_table_hover, new_table_hover, 'CSS: table hover more visible')

# ============================================================
# 10. FIX s1 cover date: "Jul 8" → "Jul 9" (today)
# ============================================================
fix(
    '<div class="cv-val">Jul 8</div><div class="cv-label">Report Date</div>',
    '<div class="cv-val">Jul 9</div><div class="cv-label">Report Date</div>',
    's1 cover date Jul 8→Jul 9'
)

# Also fix title date
fix(
    '<title>TechnoStationery Executive Audit — Jan–Jul 2026</title>',
    '<title>TechnoStationery Executive Audit — H1 2026 · Jul 9, 2026</title>',
    'HTML title updated'
)

# ============================================================
# 11. FIX s9 slide — check for Redis uptime/stats accuracy
# ============================================================

# ============================================================
# 12. NARRATOR TEXT FIXES — remaining stale descriptions
# ============================================================
fix(
    "  s14: 'Customers: 5,473 total. H1 2026 = 661 unique buyers. H1 2025 = 486. +36% YoY. Monthly: Jan=157, Feb=90, Mar=93, Apr=105, May=116, Jun=143.',",
    "  s14: 'Customers: 5,473 total unique buyers all-time. H1 2026 = 661 unique · 566 new first-time buyers. H1 2025 = 486. +36.0% YoY. Monthly H1 2026: Jan=157, Feb=90, Mar=93, Apr=105, May=116, Jun=143. H2 2025 = 820 unique.',",
    's14 narrator updated with real data'
)

fix(
    "  s15: 'Top products by units sold. Carton Toile 280g dominates with 574 units. 9,618 total SKUs, 6 OOS. Real product data from sales_order_item JOIN catalog_product.',",
    "  s15: 'Top products H1 2026 by units sold (non-cancelled). #1: Carton Toile 280g (322 units, 69,320 DZD), #2: Toile Châssis 280g (122 units), #3: Peinture Acrylique 100ml (96 units). 9,618 total SKUs, 6 OOS, 9,612 in_stock. Real data from MariaDB sales_order_item.',",
    's15 narrator updated with real product data'
)

# ============================================================
# 13. FIX s17 H2 2026 projection panel — make more realistic
# ============================================================
# Current shows "1,900–2,100 orders" — H1 = 819, H2 2025 = 1023  
# H2 2026 projection: if same YoY growth +44.4% on H2 2025 = 1023*1.444 ≈ 1,477
# Full year 2026 = 819 + 1477 = ~2,296 orders projected
old_projection = '''          <div>• Back-to-school (Sep): <strong style="color:var(--ok)">+35–45% expected</strong></div>
          <div>• Full year target: <strong style="color:var(--accent)">1,900–2,100 orders</strong></div>
          <div>• Requires: Magento XXE CVE patch, performance stability</div>'''
new_projection = '''          <div>• Back-to-school (Sep): <strong style="color:var(--ok)">+35–45% expected</strong></div>
          <div>• H2 2026 projection: <strong style="color:var(--accent)">1,400–1,600 orders</strong></div>
          <div>• Full year 2026 target: <strong style="color:var(--accent2)">2,200–2,400 orders</strong></div>
          <div>• Requires: XXE CVE patch, cancel rate reduction &lt;25%</div>'''
fix(old_projection, new_projection, 's17 H2 2026 projection updated to realistic numbers')

# ============================================================
# 14. FIX: Add "canceled" in "Autres" breakdown for s13
# ============================================================
# canceled=6, Commande_preparee=3, Commande_en_livraison_prestatair=1, tentative_recup_1=1, 
# Conf_livraison_1=1, tentative_livraison_2=1 = 13 total → "Autres" = 13 (not 7)
# Wait: current table shows 7 for "Autres" but real is 7 "other active" (comm_prep=3+4 others)
# + canceled=6 was shown separately: 819-492-28-163-79-44-6=7 — so 7 is correct for "autres non-canceled"
# But total should be 819: 492+28+163+79+44+6+7=819 ✓

# ============================================================  
# Save
# ============================================================
open('/home/dashboard/public_html/presentation/index.html', 'w').write(content)
new_size = len(content)
print(f"\n{'='*60}")
print(f"COMPREHENSIVE TUNING COMPLETE")
print(f"{'='*60}")
print(f"Original: {original_size:,} chars → New: {new_size:,} chars (Δ {new_size-original_size:+,})")
print(f"\nFixes applied ({len([f for f in fixes_applied if f.startswith('✓')])} success, {len([f for f in fixes_applied if f.startswith('✗')])} not found):")
for f in fixes_applied:
    print(f"  {f}")
