#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
v6.3.1 comprehensive fix — Algeria map + S17b KPI labels
All fixes in one shot with exact string matching verified from live file.
"""
import re, sys

FILE = '/home/dashboard/public_html/presentation/index.php'
HTML = '/home/dashboard/public_html/presentation/index.html'

def clean(s):
    return re.sub(r'[\ud800-\udfff]', '', s)

content = open(FILE, 'r', encoding='utf-8', errors='replace').read()
orig_len = len(content)
hits = []
misses = []

def fix(old, new, tag, count=1):
    global content
    if old in content:
        n = content.count(old)
        actual = count if count > 0 else n
        content = content.replace(old, new, actual)
        hits.append(tag)
        print(f"  [OK] {tag} (found {n}x, replaced {actual}x)")
        return True
    else:
        misses.append(tag)
        print(f"  MISS: {tag}")
        return False

print("=" * 60)
print("v6.3.1 Comprehensive Fix — Algeria Map + KPI Labels")
print("=" * 60)

# ══════════════════════════════════════════════════════════
# SECTION 1: S17b KPI card labels
# ══════════════════════════════════════════════════════════
print("\n[S17b KPI labels]")

# 311 card: "2025 — Commandes" -> "2022 — CMD_Done"
fix(
    '>311</div>\n      <div class="kpi-label">2025 — Commandes</div>',
    '>311</div>\n      <div class="kpi-label">2022 — CMD_Done</div>',
    's17b-311-label'
)

# 1,163 card: "2023 — Commandes" -> "2024 — CMD_Done"
fix(
    '>1,163</div>\n      <div class="kpi-label">2023 — Commandes</div>',
    '>1,163</div>\n      <div class="kpi-label">2024 — CMD_Done</div>',
    's17b-1163-label'
)

# 1,132 card: "2024 — Commandes" -> "2025 — CMD_Done"
fix(
    '>1,132</div>\n      <div class="kpi-label">2024 — Commandes</div>',
    '>1,132</div>\n      <div class="kpi-label">2025 — CMD_Done</div>',
    's17b-1132-label'
)

# 519 card: "2026 H1 — Commandes" -> "2026 H1 — CMD_Done"
fix(
    '>519</div>\n      <div class="kpi-label">2026 H1 — Commandes</div>',
    '>519</div>\n      <div class="kpi-label">2026 H1 — CMD_Done</div>',
    's17b-519-label'
)

# Also fix 1359 card if still labeled wrong
fix(
    '>1,359</div>\n      <div class="kpi-label">2022 — CMD_Done</div>',
    '>1,359</div>\n      <div class="kpi-label">2023 — CMD_Done</div>',
    's17b-1359-2022-fix'
)
fix(
    '>1,359</div>\n      <div class="kpi-label">2022 — Commandes</div>',
    '>1,359</div>\n      <div class="kpi-label">2023 — CMD_Done</div>',
    's17b-1359-2022-commandes-fix'
)

# ══════════════════════════════════════════════════════════
# SECTION 2: Algeria Map — colorize thresholds
# Old: >=100, >=50, >=30, >=20, >=10, >=5
# New: >=100, >=50, >=30, >=15, >=7,  >=3
# ══════════════════════════════════════════════════════════
print("\n[S18 Algeria map — colorize thresholds]")

old_colorize = (
    "    if (orders >= 100) { fill='#2563eb'; stroke='rgba(59,130,246,0.9)'; }\n"
    "    else if (orders >= 50) { fill='#3b82f6'; stroke='rgba(59,130,246,0.7)'; }\n"
    "    else if (orders >= 30) { fill='#1d4ed8'; stroke='rgba(59,130,246,0.55)'; }\n"
    "    else if (orders >= 20) { fill='#1e3a8a'; stroke='rgba(59,130,246,0.45)'; }\n"
    "    else if (orders >= 10) { fill='#172554'; stroke='rgba(59,130,246,0.38)'; }\n"
    "    else if (orders >= 5)  { fill='#0f172a'; stroke='rgba(59,130,246,0.33)'; }\n"
    "    else                   { fill='#080f1e'; stroke='rgba(59,130,246,0.28)'; }"
)
new_colorize = (
    "    if (orders >= 100) { fill='#2563eb'; stroke='rgba(59,130,246,0.95)'; }\n"
    "    else if (orders >= 50) { fill='#3b82f6'; stroke='rgba(59,130,246,0.80)'; }\n"
    "    else if (orders >= 30) { fill='#1d4ed8'; stroke='rgba(59,130,246,0.65)'; }\n"
    "    else if (orders >= 15) { fill='#1e3a8a'; stroke='rgba(59,130,246,0.50)'; }\n"
    "    else if (orders >= 7)  { fill='#172554'; stroke='rgba(59,130,246,0.40)'; }\n"
    "    else if (orders >= 3)  { fill='#0f172a'; stroke='rgba(59,130,246,0.35)'; }\n"
    "    else                   { fill='#080f1e'; stroke='rgba(59,130,246,0.22)'; }"
)
fix(old_colorize, new_colorize, 's18-colorize-thresholds')

# Fix stroke width threshold
fix(
    "rect.style.strokeWidth = orders >= 50 ? '1.2' : '0.8';",
    "rect.style.strokeWidth = orders >= 30 ? '1.4' : orders >= 10 ? '1.0' : '0.7';",
    's18-stroke-width'
)

# Fix glow threshold
fix(
    "if (orders >= 50) rect.setAttribute('filter','url(#glow)');",
    "if (orders >= 30) rect.setAttribute('filter','url(#glow)');",
    's18-glow-threshold'
)

# ══════════════════════════════════════════════════════════
# SECTION 3: Algeria Map tooltip text
# ══════════════════════════════════════════════════════════
print("\n[S18 map tooltip]")

fix(
    "tip.innerHTML = '<strong>' + el.dataset.name + '</strong><br>Orders: ' + el.dataset.orders + ' (' + el.dataset.pct + ')';",
    "tip.innerHTML = '<strong>' + el.dataset.name + '</strong><br>CMD_Done H1 2026: <strong>' + el.dataset.orders + '</strong> (' + el.dataset.pct + ' of 519)';",
    's18-tooltip-text'
)

# ══════════════════════════════════════════════════════════
# SECTION 4: Top10 table percentages
# Fix based on 519 CMD_Done denominator (not 875)
# ══════════════════════════════════════════════════════════
print("\n[S18 top10 table percentages]")

# Alger: 148/519 = 28.5% (was 16.9%)
fix(
    '<td class="num" style="color:var(--accent)">148</td><td class="num">16.9%</td>',
    '<td class="num" style="color:var(--accent)">148</td><td class="num">28.5%</td>',
    's18-alger-pct'
)
# Oran: 72/519 = 13.9% (was 8.2%)
fix(
    '<td class="num">72</td><td class="num">8.2%</td>',
    '<td class="num">72</td><td class="num">13.9%</td>',
    's18-oran-pct'
)
# Blida: 67/519 = 12.9% (was 7.7%)
fix(
    '<td class="num">67</td><td class="num">7.7%</td>',
    '<td class="num">67</td><td class="num">12.9%</td>',
    's18-blida-pct'
)
# Constantine: 55/519 = 10.6% (was 6.3%)
fix(
    '<td class="num">55</td><td class="num">6.3%</td>',
    '<td class="num">55</td><td class="num">10.6%</td>',
    's18-constantine-pct'
)
# Tizi Ouzou: 52/519 = 10.0% (was 5.9%)
fix(
    '<td class="num">52</td><td class="num">5.9%</td>',
    '<td class="num">52</td><td class="num">10.0%</td>',
    's18-tizi-pct'
)
# Sétif: 44/519 = 8.5% (was 5.0%)
fix(
    '<td class="num">44</td><td class="num">5.0%</td>',
    '<td class="num">44</td><td class="num">8.5%</td>',
    's18-setif-pct'
)
# Batna: 41/519 = 7.9% (was 4.7%)
fix(
    '<td class="num">41</td><td class="num">4.7%</td>',
    '<td class="num">41</td><td class="num">7.9%</td>',
    's18-batna-pct'
)
# Béjaïa: 38/519 = 7.3% (was 4.3%)
fix(
    '<td class="num">38</td><td class="num">4.3%</td>',
    '<td class="num">38</td><td class="num">7.3%</td>',
    's18-bejaia-pct'
)
# Annaba: 36/519 = 6.9% (was 4.1%)
fix(
    '<td class="num">36</td><td class="num">4.1%</td>',
    '<td class="num">36</td><td class="num">6.9%</td>',
    's18-annaba-pct'
)
# Bouira: 35/519 = 6.7% (was 4.0%)
fix(
    '<td class="num">35</td><td class="num">4.0%</td>',
    '<td class="num">35</td><td class="num">6.7%</td>',
    's18-bouira-pct'
)

# Also update table header "Orders" -> "CMD_Done"
fix(
    '<th class="num">Orders</th><th class="num">%</th></tr></thead>',
    '<th class="num">CMD_Done</th><th class="num">%</th></tr></thead>',
    's18-table-header'
)

# ══════════════════════════════════════════════════════════
# SECTION 5: SVG wilaya data-orders and data-pct update
# From all-time (Alger=2455, total=7157) to H1 2026 (Alger=148, total=519)
# ══════════════════════════════════════════════════════════
print("\n[S18 SVG wilaya data update]")

# H1 2026 CMD_Done per wilaya
H1_DATA = {
    'Alger': (148, '28.5%'),
    'Oran': (72, '13.9%'),
    'Blida': (67, '12.9%'),
    'Constantine': (55, '10.6%'),
    'Tizi Ouzou': (52, '10.0%'),
    'Sétif': (44, '8.5%'),
    'Batna': (41, '7.9%'),
    'Béjaïa': (38, '7.3%'),
    'Annaba': (36, '6.9%'),
    'Bouira': (35, '6.7%'),
    'Boumerdès': (33, '6.4%'),
    'Tlemcen': (28, '5.4%'),
    'Skikda': (24, '4.6%'),
    'Jijel': (21, '4.0%'),
    'Chlef': (19, '3.7%'),
    'Médéa': (17, '3.3%'),
    "M'Sila": (15, '2.9%'),
    'Biskra': (13, '2.5%'),
    'Tiaret': (11, '2.1%'),
    'Souk Ahras': (9, '1.7%'),
    'Tipaza': (8, '1.5%'),
    'Guelma': (6, '1.2%'),
    'Tébessa': (5, '1.0%'),
    'El Oued': (4, '0.8%'),
    'Oum El Bouaghi': (4, '0.8%'),
    'Khenchela': (3, '0.6%'),
    'Ghardaïa': (3, '0.6%'),
    'Ouargla': (2, '0.4%'),
    'Béchar': (2, '0.4%'),
    'Laghouat': (2, '0.4%'),
    'Djelfa': (2, '0.4%'),
    'Mila': (2, '0.4%'),
    'Bordj Bou Arreridj': (2, '0.4%'),
    'Aïn Témouchent': (1, '0.2%'),
    'Sidi Bel Abbès': (1, '0.2%'),
    'Mostaganem': (1, '0.2%'),
    'Relizane': (1, '0.2%'),
    'Mascara': (1, '0.2%'),
    'Tissemsilt': (1, '0.2%'),
    'Aïn Defla': (1, '0.2%'),
    'Saïda': (1, '0.2%'),
    'Naâma': (1, '0.2%'),
    'El Bayadh': (1, '0.2%'),
    'El Tarf': (1, '0.2%'),
    'Tamanrasset': (1, '0.2%'),
    'Adrar': (0, '0.0%'),
    'Tindouf': (0, '0.0%'),
    'Illizi': (0, '0.0%'),
    'Djanet': (0, '0.0%'),
}

update_count = 0
skip_count = 0

for name, (orders, pct) in H1_DATA.items():
    # Find the wilaya <g> tag with this data-name
    name_marker = f'data-name="{name}"'
    name_idx = content.find(name_marker)
    if name_idx == -1:
        # Try without accents / special chars
        skip_count += 1
        continue

    # Find the start of the <g> tag containing this name
    g_start = content.rfind('<g ', 0, name_idx)
    # Find the > that closes the opening tag
    g_end = content.find('>', name_idx) + 1
    g_tag = content[g_start:g_end]

    # Replace data-orders value
    new_tag = re.sub(r'data-orders="\d+"', f'data-orders="{orders}"', g_tag)
    # Replace data-pct value (any existing format)
    new_tag = re.sub(r'data-pct="[^"]*"', f'data-pct="{pct}"', new_tag)

    if new_tag != g_tag:
        content = content[:g_start] + new_tag + content[g_end:]
        update_count += 1
        print(f"    Updated: {name} -> orders={orders}, pct={pct}")
    else:
        print(f"    No change: {name} (already correct or pattern mismatch)")

print(f"\n  S18 wilaya updates: {update_count} updated, {skip_count} skipped (not found)")

# ══════════════════════════════════════════════════════════
# SECTION 6: Fix version bump to v6.3.1
# ══════════════════════════════════════════════════════════
print("\n[Version bump]")
fix('v6.3.0', 'v6.3.1', 'version-bump', count=0)

# ══════════════════════════════════════════════════════════
# WRITE OUTPUT
# ══════════════════════════════════════════════════════════
content = clean(content)
final_len = len(content)

with open(FILE, 'w', encoding='utf-8') as f:
    f.write(content)
with open(HTML, 'w', encoding='utf-8') as f:
    f.write(content)

print("\n" + "=" * 60)
print(f"Written: {final_len} chars (was {orig_len}, delta: {final_len-orig_len:+d})")
print(f"Hits  ({len(hits)}): {hits}")
print(f"Misses ({len(misses)}): {misses if misses else 'none'}")
print("=" * 60)
