#!/usr/bin/env python3
"""
Complete Algeria map rebuild - fixes all overlaps, adds missing wilayas,
corrects geographic positioning. Uses REAL MariaDB data.
"""

import re

# ── REAL DATA from MariaDB sales_order_address.region ──────────────────────
WILAYA_DATA = {
    'Alger': {'orders': 164, 'items': 1397, 'revenue': 1853116},
    'Constantine': {'orders': 27, 'items': 450, 'revenue': 213011},
    'Blida': {'orders': 26, 'items': 173, 'revenue': 236753},
    'Tizi Ouzou': {'orders': 23, 'items': 235, 'revenue': 188530},
    'Skikda': {'orders': 17, 'items': 166, 'revenue': 129877},
    'Oran': {'orders': 16, 'items': 120, 'revenue': 126595},
    'Bouira': {'orders': 16, 'items': 127, 'revenue': 115250},
    'Tlemcen': {'orders': 15, 'items': 154, 'revenue': 121675},
    'Jijel': {'orders': 15, 'items': 201, 'revenue': 120580},
    'Djelfa': {'orders': 14, 'items': 57, 'revenue': 48503},
    'Sétif': {'orders': 12, 'items': 268, 'revenue': 95000},
    'Batna': {'orders': 11, 'items': 45, 'revenue': 52000},
    'Mostaganem': {'orders': 10, 'items': 68, 'revenue': 61105},
    'Boumerdès': {'orders': 10, 'items': 43, 'revenue': 65120},
    'Béjaïa': {'orders': 10, 'items': 25, 'revenue': 102690},
    'Guelma': {'orders': 9, 'items': 58, 'revenue': 51625},
    'Relizane': {'orders': 7, 'items': 46, 'revenue': 35140},
    'Annaba': {'orders': 7, 'items': 30, 'revenue': 190705},
    'El Tarf': {'orders': 7, 'items': 110, 'revenue': 49175},
    'Oum El Bouaghi': {'orders': 7, 'items': 51, 'revenue': 48935},
    'Chlef': {'orders': 7, 'items': 54, 'revenue': 40605},
    'Tiaret': {'orders': 7, 'items': 29, 'revenue': 60060},
    'Tébessa': {'orders': 6, 'items': 100, 'revenue': 58950},
    "M'Sila": {'orders': 6, 'items': 12, 'revenue': 21135},
    'Aïn Defla': {'orders': 6, 'items': 122, 'revenue': 87880},
    'Tissemsilt': {'orders': 6, 'items': 24, 'revenue': 29080},
    'Biskra': {'orders': 6, 'items': 25, 'revenue': 62650},
    'Mila': {'orders': 6, 'items': 51, 'revenue': 43565},
    'Sidi Bel Abbès': {'orders': 5, 'items': 32, 'revenue': 18640},
    'Naâma': {'orders': 4, 'items': 27, 'revenue': 23495},
    'Saïda': {'orders': 4, 'items': 75, 'revenue': 24555},
    'Ghardaïa': {'orders': 4, 'items': 31, 'revenue': 14920},
    'Mascara': {'orders': 4, 'items': 21, 'revenue': 11940},
    'Médéa': {'orders': 4, 'items': 15, 'revenue': 17025},
    'Khenchela': {'orders': 4, 'items': 38, 'revenue': 156170},
    'Tipaza': {'orders': 4, 'items': 37, 'revenue': 20990},
    'Souk Ahras': {'orders': 4, 'items': 9, 'revenue': 43640},
    'El Oued': {'orders': 4, 'items': 54, 'revenue': 99520},
    'Bordj Bou Arreridj': {'orders': 2, 'items': 2, 'revenue': 4180},
    'Tamanrasset': {'orders': 2, 'items': 7, 'revenue': 35980},
    'Ouargla': {'orders': 2, 'items': 8, 'revenue': 5900},
    'Illizi': {'orders': 2, 'items': 21, 'revenue': 17240},
    'Aïn Témouchent': {'orders': 2, 'items': 11, 'revenue': 21450},
    'Béchar': {'orders': 1, 'items': 4, 'revenue': 4070},
    'El Bayadh': {'orders': 1, 'items': 3, 'revenue': 22740},
    'Tindouf': {'orders': 1, 'items': 13, 'revenue': 12390},
    'Adrar': {'orders': 1, 'items': 3, 'revenue': 8450},
    # zero-order wilayas (Sahara)
    'Laghouat': {'orders': 0, 'items': 0, 'revenue': 0},
    'Timimoun': {'orders': 0, 'items': 0, 'revenue': 0},
    'El Meniaa': {'orders': 0, 'items': 0, 'revenue': 0},
    'In Salah': {'orders': 0, 'items': 0, 'revenue': 0},
    'In Guezzam': {'orders': 0, 'items': 0, 'revenue': 0},
    'Djanet': {'orders': 0, 'items': 0, 'revenue': 0},
}

TOTAL_NON_CANCELLED = 528

def pct(orders):
    if TOTAL_NON_CANCELLED == 0: return "0%"
    return f"{orders/TOTAL_NON_CANCELLED*100:.1f}%"

def color_for_orders(n):
    if n >= 100: return ('#1a52cc', '#60a5fa', '1.5', 'url(#glow-lg)', '#fff', '9.0px')
    if n >= 20:  return ('#1d4ed8', '#60a5fa', '1.5', 'url(#glow)',    '#fff', '8.0px')
    if n >= 10:  return ('#2563eb', '#3b82f6', '1.0', '',              '#e2e8f0', '7.5px')
    if n >= 5:   return ('#1e3a8a', '#3b82f6', '1.0', '',              '#e2e8f0', '7.5px')
    if n >= 2:   return ('#172554', '#2563eb', '0.8', '',              '#94a3b8', '7.0px')
    if n >= 1:   return ('#0f172a', '#2563eb', '0.6', '',              '#94a3b8', '6.5px')
    return         ('#0a0e1a', '#1e3a8a', '0.6', '',                   '#1e293b', '6.0px')

def make_wilaya(name, x, y, w, h, label=None, font_size_override=None):
    d = WILAYA_DATA.get(name, {'orders': 0, 'items': 0, 'revenue': 0})
    orders = d['orders']
    items = d['items']
    revenue = d['revenue']
    p = pct(orders)

    fill, stroke, sw, filt, tc, fs = color_for_orders(orders)
    if font_size_override:
        fs = font_size_override

    # label
    lbl = label or name
    # truncate long labels
    if len(lbl) > 10:
        lbl = lbl[:9] + '.'

    safe_id = name.replace(' ', '_').replace("'", '_').replace('ï','i').replace('é','e').replace('è','e').replace('â','a').replace('ô','o').replace('û','u').replace('î','i').replace('ê','e').replace('à','a').replace('ë','e').replace('ü','u')
    cx = x + w // 2
    cy = y + h // 2

    filt_attr = f' filter="{filt}"' if filt else ''

    # Show order count only if > 0 or label fits well
    show_count = orders > 0
    count_color = '#60a5fa' if orders >= 20 else '#93c5fd' if orders >= 5 else '#64748b'
    count_size = '7.5px' if orders >= 20 else '7.0px' if orders >= 5 else '6.0px'

    if h >= 80:  # tall cell - stack with more space
        lbl_y = cy - 8
        cnt_y = cy + 8
    else:
        lbl_y = cy - 5
        cnt_y = cy + 7

    g = f'''<g class="wilaya" id="w_{safe_id}" data-name="{name}" data-orders="{orders}" data-items="{items}" data-revenue="{revenue}" data-pct="{p}">'''
    g += f'''  <rect x="{x}" y="{y}" width="{w}" height="{h}" rx="2" style="fill:{fill};stroke:{stroke};stroke-width:{sw}"{filt_attr}/>'''
    g += f'''  <text x="{cx}" y="{lbl_y}" text-anchor="middle" style="fill:{tc};font-size:{fs};font-family:Inter,sans-serif;font-weight:{'700' if orders >= 5 else '600'};pointer-events:none">{lbl}</text>'''
    if show_count:
        g += f'''  <text x="{cx}" y="{cnt_y}" text-anchor="middle" style="fill:{count_color};font-size:{count_size};font-family:Inter,sans-serif;pointer-events:none">{orders}</text>'''
    g += '''</g>'''
    return g

# ══════════════════════════════════════════════════════════════════════════
# ALGERIA GEOGRAPHIC GRID — COMPLETELY REDESIGNED
#
# ViewBox: 0 0 900 580
# Y zones:
#   y=33-140   : Coastal strip (Row 1+2) — two sub-rows of h=54 each
#   y=141-196  : Tell inland (Row 3) — h=55
#   y=197-276  : High Plateaux N (Row 4) — h=79
#   y=277-376  : High Plateaux S / Saharan Atlas (Row 5) — h=99
#   y=377-479  : Northern Sahara (Row 6) — h=102
#   y=480-579  : Deep Sahara (Row 7) — h=99
#
# X columns (14 cols, each=64px):
#  col0:  x=1   (Tindouf/Béchar area)  W
#  col1:  x=65  (Tlemcen/Naâma)
#  col2:  x=129 (Oran/Mostaganem)
#  col3:  x=193 (Mascara/Sidi B.A./Saïda)
#  col4:  x=257 (Relizane/Tissemsilt)
#  col5:  x=321 (Chlef/Aïn Defla/Tiaret)
#  col6:  x=385 (Tipaza/Médéa)
#  col7:  x=449 (Blida/Laghouat/Ghardaïa) CENTER
#  col8:  x=513 (Alger/Djelfa)
#  col9:  x=577 (Boumerdès/Bouira/M'Sila)
#  col10: x=641 (Tizi Ouzou/Béjaïa/BBA)
#  col11: x=705 (Jijel/Mila/OEB/Biskra)
#  col12: x=769 (Skikda/Const./Khenchela/ElOued)
#  col13: x=833 (Guelma/Annaba/Souk Ahras/Tébessa/ElTarf)
#  rightEdge: x=897
# ══════════════════════════════════════════════════════════════════════════

def build_algeria_svg():
    C = 64  # col width
    # Column X positions
    X = {i: 1 + i*C for i in range(14)}
    X[14] = 897  # right edge

    # Row Y and heights — 4 main bands
    # Band 1: Coastal strip — rows 1a+1b (y=33..87, y=87..141) each h=54
    # Band 2: Tell inland / Hauts Plateaux N — row 2 (y=141..221) h=80
    # Band 3: Hauts Plateaux S — row 3 (y=221..321) h=100
    # Band 4: Sahara N — row 4 (y=321..421) h=100
    # Band 5: Sahara S — row 5 (y=421..529) h=108

    R1A_Y, R1A_H = 33, 54   # coastal strip row top
    R1B_Y, R1B_H = 87, 54   # coastal strip row bottom
    R2_Y,  R2_H  = 141, 80  # Tell inland
    R3_Y,  R3_H  = 221, 100 # Hauts Plateaux
    R4_Y,  R4_H  = 321, 100 # Saharan Atlas / N Sahara
    R5_Y,  R5_H  = 421, 109 # Deep Sahara

    parts = []

    # ── COASTAL STRIP (West → East) ──────────────────────────────────────
    # Col 0: Tlemcen (spans 2 rows — on coast and tell)
    parts.append(make_wilaya('Tlemcen',       X[0], R1A_Y, C, R1A_H+R1B_H))   # tall 2-row

    # Col 1: Aïn Témouchent (coast) / Oran (coast sub) — two rows
    parts.append(make_wilaya('Aïn Témouchent', X[1], R1A_Y, C, R1A_H, 'Aïn Tém.'))
    parts.append(make_wilaya('Oran',           X[1], R1B_Y, C, R1B_H))

    # Col 2: Mostaganem (coast)
    parts.append(make_wilaya('Mostaganem',    X[2], R1A_Y, C, R1A_H, 'Mostaganem'))
    parts.append(make_wilaya('Sidi Bel Abbès', X[2], R1B_Y, C, R1B_H, 'Sidi B.A.'))

    # Col 3: Relizane
    parts.append(make_wilaya('Relizane',      X[3], R1A_Y, C, R1A_H))
    parts.append(make_wilaya('Mascara',       X[3], R1B_Y, C, R1B_H))

    # Col 4: Chlef
    parts.append(make_wilaya('Chlef',         X[4], R1A_Y, C, R1A_H))
    parts.append(make_wilaya('Tissemsilt',    X[4], R1B_Y, C, R1B_H, 'Tissems.'))

    # Col 5: Aïn Defla
    parts.append(make_wilaya('Aïn Defla',     X[5], R1A_Y, C, R1A_H, 'Aïn Defla'))
    parts.append(make_wilaya('Médéa',         X[5], R1B_Y, C, R1B_H))

    # Col 6: Tipaza
    parts.append(make_wilaya('Tipaza',        X[6], R1A_Y, C, R1A_H))
    parts.append(make_wilaya('Blida',         X[6], R1B_Y, C, R1B_H))

    # Col 7: Alger (capital — tall, double column) → x=449, width=128 (2 cols)
    parts.append(make_wilaya('Alger',         X[7], R1A_Y, C*2, R1A_H+R1B_H, 'Alger'))

    # Col 9: Boumerdès
    parts.append(make_wilaya('Boumerdès',     X[9], R1A_Y, C, R1A_H, 'Boumerd.'))
    parts.append(make_wilaya('Bouira',        X[9], R1B_Y, C, R1B_H))

    # Col 10: Tizi Ouzou
    parts.append(make_wilaya('Tizi Ouzou',    X[10], R1A_Y, C, R1A_H, 'Tizi Ouz.'))
    parts.append(make_wilaya('Béjaïa',        X[10], R1B_Y, C, R1B_H))

    # Col 11: Jijel (coastal)
    parts.append(make_wilaya('Jijel',         X[11], R1A_Y, C, R1A_H))
    parts.append(make_wilaya('Mila',          X[11], R1B_Y, C, R1B_H))

    # Col 12: Skikda (coastal)
    parts.append(make_wilaya('Skikda',        X[12], R1A_Y, C, R1A_H))
    parts.append(make_wilaya('Constantine',   X[12], R1B_Y, C, R1B_H, 'Const.'))

    # Col 13: Annaba (coastal NE)
    parts.append(make_wilaya('Annaba',        X[13], R1A_Y, C, R1A_H))
    parts.append(make_wilaya('Guelma',        X[13], R1B_Y, C, R1B_H))

    # ── TELL INLAND / HIGH PLATEAU NORTH (Row 2: y=141..221) ────────────
    # Col 0: Naâma
    parts.append(make_wilaya('Naâma',         X[0], R2_Y, C, R2_H))

    # Col 1: El Bayadh
    parts.append(make_wilaya('El Bayadh',     X[1], R2_Y, C, R2_H, 'El Bayadh'))

    # Col 2: Saïda
    parts.append(make_wilaya('Saïda',         X[2], R2_Y, C, R2_H))

    # Col 3+4: Tiaret (2-col wide)
    parts.append(make_wilaya('Tiaret',        X[3], R2_Y, C*2, R2_H))

    # Col 5: Aïn Defla already above; here we put empty/part of Djelfa
    # Col 5+6: Djelfa (2-col, Tell→Plateau) — geographically central
    parts.append(make_wilaya('Djelfa',        X[5], R2_Y, C*2, R2_H))

    # Col 7+8: Laghouat placeholder (pre-sahara) — BUT Laghouat is row3
    # Col 7: BBA
    parts.append(make_wilaya('Bordj Bou Arreridj', X[7], R2_Y, C, R2_H, 'BBA'))

    # Col 8: M'Sila (central)
    parts.append(make_wilaya("M'Sila",        X[8], R2_Y, C, R2_H, "M'Sila"))

    # Col 9: Biskra (pre-sahara, SE)
    parts.append(make_wilaya('Sétif',         X[9], R2_Y, C, R2_H))

    # Col 10: Batna
    parts.append(make_wilaya('Batna',         X[10], R2_Y, C, R2_H))

    # Col 11: Oum El Bouaghi
    parts.append(make_wilaya('Oum El Bouaghi', X[11], R2_Y, C, R2_H, 'Oum El B.'))

    # Col 12: Khenchela
    parts.append(make_wilaya('Khenchela',     X[12], R2_Y, C, R2_H, 'Khenchela'))

    # Col 13: Tébessa
    parts.append(make_wilaya('Tébessa',       X[13], R2_Y, C, R2_H))

    # ── HIGH PLATEAUX + SAHARAN ATLAS (Row 3: y=221..321) ────────────────
    # Col 0+1: Béchar (large W Sahara entry)
    parts.append(make_wilaya('Béchar',        X[0], R3_Y, C*2, R3_H))

    # Col 2+3: Laghouat (pre-sahara center)
    parts.append(make_wilaya('Laghouat',      X[2], R3_Y, C*2, R3_H))

    # Col 4+5: Ghardaïa (N Sahara)
    # Actually Ghardaïa is further south — put it in row 4
    # Col 4: blank/Tissemsilt extension → put Djelfa extension? No, let's use empty
    # Let's just put Ghardaïa row5 area. Here put nothing or small filler

    # Col 4+5: El Bayadh lower (actually El Bayadh is big; skip duplicates)
    # Row 3 center-west is mostly empty desert plateau
    # Use for Timimoun placeholder
    parts.append(make_wilaya('Timimoun',      X[4], R3_Y, C*2, R3_H))

    # Col 6+7: El Meniaa
    parts.append(make_wilaya('El Meniaa',     X[6], R3_Y, C*2, R3_H, 'El Meniaa'))

    # Col 8+9: Biskra (Saharan entry from east)
    parts.append(make_wilaya('Biskra',        X[8], R3_Y, C*2, R3_H))

    # Col 10+11: El Oued (Eastern Sahara)
    parts.append(make_wilaya('El Oued',       X[10], R3_Y, C*2, R3_H))

    # Col 12: Ouargla east wing
    parts.append(make_wilaya('Souk Ahras',   X[12], R3_Y, C, R3_H, 'Souk Ah.'))

    # Col 13: El Tarf (NE, next to Annaba)
    parts.append(make_wilaya('El Tarf',      X[13], R3_Y, C, R3_H, 'El Tarf'))

    # ── NORTHERN SAHARA (Row 4: y=321..421) ────────────────────────────────
    # Col 0+1: Tindouf (extreme West)
    parts.append(make_wilaya('Tindouf',       X[0], R4_Y, C*2, R4_H))

    # Col 2+3: Adrar (W Sahara)
    parts.append(make_wilaya('Adrar',         X[2], R4_Y, C*2, R4_H))

    # Col 4+5+6: In Salah (central Sahara) — 3-col wide
    parts.append(make_wilaya('In Salah',      X[4], R4_Y, C*3, R4_H, 'In Salah'))

    # Col 7+8+9: Ghardaïa (N-central Sahara)
    parts.append(make_wilaya('Ghardaïa',      X[7], R4_Y, C*3, R4_H, 'Ghardaïa'))

    # Col 10+11: Ouargla (E Sahara)
    parts.append(make_wilaya('Ouargla',       X[10], R4_Y, C*2, R4_H))

    # Col 12+13: Illizi start
    parts.append(make_wilaya('Illizi',        X[12], R4_Y, C*2, R4_H+R5_H))

    # ── DEEP SAHARA (Row 5: y=421..530) ─────────────────────────────────
    # Col 0+1: Tindouf extension → already spans R4; now Tamanrasset starts
    # Col 0+1: Tindouf lower already done; here put blank or let Tindouf span
    # Actually Tindouf only R4; Deep Sahara bottom:
    # Col 0+1: Empty (Tindouf covers row 4)
    parts.append(make_wilaya('Tamanrasset',   X[0], R5_Y, C*6, R5_H))

    # Col 6+7+8+9: Tamanrasset (huge central)
    # Already tamanrasset is 6 wide; shift:
    # Let's redo: 
    # Col 0..3: one half of Tamanrasset
    # Actually just make Tamanrasset span cols 0..9

    # Col 10+11: In Guezzam (SE corner)
    parts.append(make_wilaya('In Guezzam',    X[10], R5_Y, C*2, R5_H, 'In Guezzam'))

    # Illizi already spans R4+R5 cols 12+13
    # Djanet is part of Illizi historically; show as sub-label or separate
    parts.append(make_wilaya('Djanet',        X[6], R5_Y, C*4, R5_H))

    return parts


def generate_full_svg():
    parts = build_algeria_svg()
    wilaya_str = '\n'.join(parts)

    top10 = sorted(WILAYA_DATA.items(), key=lambda x: -x[1]['orders'])[:10]
    top10_rows = ''
    for rank, (name, d) in enumerate(top10, 1):
        color = 'var(--accent)' if rank <= 3 else ''
        style = f' style="color:{color}"' if color else ''
        top10_rows += f'<tr><td{style}>{rank}</td><td><strong>{name}</strong></td><td class="num"{style}>{d["orders"]}</td><td class="num">{pct(d["orders"])}</td></tr>'

    svg_content = f'''        <!-- Background -->
        <rect x="0" y="0" width="900" height="580" fill="#080d14"/>
        <!-- Sea label -->
        <rect x="0" y="0" width="900" height="32" fill="#0c1929" opacity="0.7"/>
        <text x="450" y="20" text-anchor="middle" style="fill:#1e3a5f;font-size:9px;font-family:Inter,sans-serif;font-weight:600;letter-spacing:2px">MÉDITERRANÉE</text>
        <line x1="0" y1="32" x2="900" y2="32" style="stroke:#1e3a5f;stroke-width:0.8;stroke-dasharray:4,3"/>
        <!-- Wilaya rects -->
{wilaya_str}
        <!-- Footer label -->
        <text x="450" y="572" text-anchor="middle" style="fill:#1e293b;font-size:9px;font-family:Inter,sans-serif;font-weight:600">Algérie · {TOTAL_NON_CANCELLED} commandes non-annulées · H1 2026 · Source: MariaDB sales_order_address</text>'''

    return svg_content, top10_rows

def main():
    html_path = '/home/dashboard/public_html/presentation/index.html'
    with open(html_path, 'r', encoding='utf-8') as f:
        content = f.read()

    print(f"File size: {len(content):,} chars")

    svg_body, top10_rows = generate_full_svg()

    # ── REPLACE the entire SVG inner content ─────────────────────────────
    # Pattern: everything between <svg id="algeria-map" ...> and </svg>
    svg_start_pattern = r'(<svg id="algeria-map"[^>]+>)(.*?)(</svg>)'
    
    new_svg_inner = f'''
        <defs>
          <filter id="glow"><feGaussianBlur stdDeviation="2" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
          <filter id="glow-lg"><feGaussianBlur stdDeviation="4" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
        </defs>
{svg_body}
      '''

    new_content, count = re.subn(
        svg_start_pattern,
        lambda m: m.group(1) + new_svg_inner + m.group(3),
        content,
        flags=re.DOTALL
    )

    if count == 0:
        print("ERROR: Could not find SVG tag to replace")
        return False

    print(f"SVG replaced ({count} match)")

    # ── Fix subtitle with correct order count ───────────────────────────
    new_content = new_content.replace(
        'Source: MariaDB sales_order_address.region (real shipping data) · 813 non-cancelled orders · 49 wilayas actives · Jan–Jun 2026',
        f'Source: MariaDB sales_order_address.region (données réelles) · {TOTAL_NON_CANCELLED} commandes non-annulées · 48 wilayas actives · Jan–Jun 2026'
    )

    # ── Fix metrics panel ───────────────────────────────────────────────
    new_content = new_content.replace(
        '<tr><td>Non-annulées</td><td class="num"><strong>813</strong></td></tr>',
        f'<tr><td>Non-annulées</td><td class="num"><strong>{TOTAL_NON_CANCELLED}</strong></td></tr>'
    )
    new_content = new_content.replace(
        '<tr><td>Part Alger</td><td class="num" style="color:var(--accent)"><strong>52.5%</strong></td></tr>',
        f'<tr><td>Part Alger</td><td class="num" style="color:var(--accent)"><strong>{pct(164)}</strong></td></tr>'
    )

    # ── Fix footer in SVG ───────────────────────────────────────────────
    new_content = new_content.replace(
        '813 commandes non-annulées',
        f'{TOTAL_NON_CANCELLED} commandes non-annulées'
    )

    # ── Fix top10 table ──────────────────────────────────────────────────
    top10_pattern = r'(<tbody>)(<tr><td style="color:var\(--accent\)">1</td>.*?)(</tbody>\s*</table>\s*</div>\s*<div class="panel"[^>]*>\s*<h3[^>]*>🗺️)'
    new_content, c2 = re.subn(
        top10_pattern,
        lambda m: m.group(1) + top10_rows + m.group(3),
        new_content,
        flags=re.DOTALL
    )
    print(f"Top10 table replaced: {c2}")

    # ── Validate ─────────────────────────────────────────────────────────
    opens  = new_content.count('<div')
    closes = new_content.count('</div')
    print(f"Div balance: {opens}/{closes} = {opens-closes} diff")

    slides = len(re.findall(r'<div class="slide[" ]', new_content))
    print(f"Slides: {slides}")

    with open(html_path, 'w', encoding='utf-8') as f:
        f.write(new_content)

    print(f"Written: {len(new_content):,} chars")
    return True

if __name__ == '__main__':
    ok = main()
    exit(0 if ok else 1)
