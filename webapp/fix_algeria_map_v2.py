#!/usr/bin/env python3
"""
Algeria map rebuild v2 - Correct geographic grid, no overlaps.
Real MariaDB data from sales_order_address.region (528 non-cancelled orders H1 2026)

Geographic layout (W→E, N→S):
  COASTAL (N):  Tlemcen, Aïn Tém., Oran, Mostaganem, Sidi B.A., Relizane,
                Mascara, Chlef, Tissemsilt, Aïn Defla, Médéa, Tipaza, Blida,
                Alger, Boumerdès, Bouira, Tizi Ouzou, Béjaïa, Jijel, Mila,
                Sétif, Batna, Skikda, Constantine, Oum El Bouaghi, Guelma,
                Khenchela, Annaba, Souk Ahras, El Tarf, Tébessa
  HAUTS PLATEAU: Naâma, El Bayadh, Saïda, Tiaret, Djelfa, M'Sila, Biskra,
                 El Oued, Laghouat, Bordj Bou Arreridj, Ghardaïa, Ouargla
  SAHARA: Béchar, Tindouf, Adrar, Timimoun, In Salah, El Meniaa,
          Tamanrasset, Illizi, Djanet, In Guezzam
"""
import re

# ── REAL DATA FROM MariaDB ──────────────────────────────────────────────────
WILAYA_DATA = {
    'Alger':            {'orders': 164, 'items': 1397, 'revenue': 1853116},
    'Constantine':      {'orders': 27,  'items': 450,  'revenue': 213011},
    'Blida':            {'orders': 26,  'items': 173,  'revenue': 236753},
    'Tizi Ouzou':       {'orders': 23,  'items': 235,  'revenue': 188530},
    'Skikda':           {'orders': 17,  'items': 166,  'revenue': 129877},
    'Oran':             {'orders': 16,  'items': 120,  'revenue': 126595},
    'Bouira':           {'orders': 16,  'items': 127,  'revenue': 115250},
    'Tlemcen':          {'orders': 15,  'items': 154,  'revenue': 121675},
    'Jijel':            {'orders': 15,  'items': 201,  'revenue': 120580},
    'Djelfa':           {'orders': 14,  'items': 57,   'revenue': 48503},
    'Sétif':            {'orders': 12,  'items': 268,  'revenue': 95000},
    'Batna':            {'orders': 11,  'items': 45,   'revenue': 52000},
    'Mostaganem':       {'orders': 10,  'items': 68,   'revenue': 61105},
    'Boumerdès':        {'orders': 10,  'items': 43,   'revenue': 65120},
    'Béjaïa':           {'orders': 10,  'items': 25,   'revenue': 102690},
    'Guelma':           {'orders': 9,   'items': 58,   'revenue': 51625},
    'Relizane':         {'orders': 7,   'items': 46,   'revenue': 35140},
    'Annaba':           {'orders': 7,   'items': 30,   'revenue': 190705},
    'El Tarf':          {'orders': 7,   'items': 110,  'revenue': 49175},
    'Oum El Bouaghi':   {'orders': 7,   'items': 51,   'revenue': 48935},
    'Chlef':            {'orders': 7,   'items': 54,   'revenue': 40605},
    'Tiaret':           {'orders': 7,   'items': 29,   'revenue': 60060},
    'Tébessa':          {'orders': 6,   'items': 100,  'revenue': 58950},
    "M'Sila":           {'orders': 6,   'items': 12,   'revenue': 21135},
    'Aïn Defla':        {'orders': 6,   'items': 122,  'revenue': 87880},
    'Tissemsilt':       {'orders': 6,   'items': 24,   'revenue': 29080},
    'Biskra':           {'orders': 6,   'items': 25,   'revenue': 62650},
    'Mila':             {'orders': 6,   'items': 51,   'revenue': 43565},
    'Sidi Bel Abbès':   {'orders': 5,   'items': 32,   'revenue': 18640},
    'Naâma':            {'orders': 4,   'items': 27,   'revenue': 23495},
    'Saïda':            {'orders': 4,   'items': 75,   'revenue': 24555},
    'Ghardaïa':         {'orders': 4,   'items': 31,   'revenue': 14920},
    'Mascara':          {'orders': 4,   'items': 21,   'revenue': 11940},
    'Médéa':            {'orders': 4,   'items': 15,   'revenue': 17025},
    'Khenchela':        {'orders': 4,   'items': 38,   'revenue': 156170},
    'Tipaza':           {'orders': 4,   'items': 37,   'revenue': 20990},
    'Souk Ahras':       {'orders': 4,   'items': 9,    'revenue': 43640},
    'El Oued':          {'orders': 4,   'items': 54,   'revenue': 99520},
    'Bordj Bou Arreridj': {'orders': 2, 'items': 2,   'revenue': 4180},
    'Tamanrasset':      {'orders': 2,   'items': 7,    'revenue': 35980},
    'Ouargla':          {'orders': 2,   'items': 8,    'revenue': 5900},
    'Illizi':           {'orders': 2,   'items': 21,   'revenue': 17240},
    'Aïn Témouchent':   {'orders': 2,   'items': 11,   'revenue': 21450},
    'Béchar':           {'orders': 1,   'items': 4,    'revenue': 4070},
    'El Bayadh':        {'orders': 1,   'items': 3,    'revenue': 22740},
    'Tindouf':          {'orders': 1,   'items': 13,   'revenue': 12390},
    'Adrar':            {'orders': 1,   'items': 3,    'revenue': 8450},
    'Laghouat':         {'orders': 0,   'items': 0,    'revenue': 0},
    'Timimoun':         {'orders': 0,   'items': 0,    'revenue': 0},
    'El Meniaa':        {'orders': 0,   'items': 0,    'revenue': 0},
    'In Salah':         {'orders': 0,   'items': 0,    'revenue': 0},
    'In Guezzam':       {'orders': 0,   'items': 0,    'revenue': 0},
    'Djanet':           {'orders': 0,   'items': 0,    'revenue': 0},
}

TOTAL = 528  # non-cancelled H1 2026

def pct(n):
    return f"{n/TOTAL*100:.1f}%" if TOTAL else "0%"

def safe_id(name):
    # Replace special chars one by one to avoid length mismatch
    s = name
    for a, b in [('ï','i'),('é','e'),('è','e'),('â','a'),('ô','o'),('û','u'),
                 ('î','i'),('ê','e'),('à','a'),('ë','e'),('ü','u'),
                 ('É','E'),('À','A'),(' ','_'),("'",'_'),('-','_'),('.','')]:
        s = s.replace(a, b)
    return s

def color(n):
    """Returns (fill, stroke, stroke_width, filter, text_color, font_size)"""
    if n >= 100: return '#1a52cc', '#60a5fa', 1.5, 'url(#glow-lg)', '#ffffff', 9.5
    if n >= 20:  return '#1d4ed8', '#60a5fa', 1.5, 'url(#glow)',    '#ffffff', 8.5
    if n >= 10:  return '#2563eb', '#3b82f6', 1.0, '',              '#e2e8f0', 8.0
    if n >= 5:   return '#1e3a8a', '#3b82f6', 1.0, '',              '#dbeafe', 7.5
    if n >= 2:   return '#172554', '#2563eb', 0.8, '',              '#94a3b8', 7.0
    if n >= 1:   return '#0f172a', '#2563eb', 0.6, '',              '#94a3b8', 6.5
    return              '#080d14', '#1e3a8a', 0.5, '',              '#1e293b', 6.0

def w(name, x, y, width, height, label=None):
    """Generate a wilaya <g> element."""
    d = WILAYA_DATA.get(name, {'orders': 0, 'items': 0, 'revenue': 0})
    n, items, rev = d['orders'], d['items'], d['revenue']
    p = pct(n)
    fi, st, sw, flt, tc, fs = color(n)

    lbl = label or name
    if len(lbl) > 11:
        lbl = lbl[:10] + '.'

    sid = safe_id(name)
    cx, cy = x + width // 2, y + height // 2
    fa = f' filter="{flt}"' if flt else ''

    # Position label and count vertically based on cell height
    if height >= 90:
        ly, ny = cy - 10, cy + 8
    elif height >= 60:
        ly, ny = cy - 6, cy + 7
    else:
        ly, ny = cy - 4, cy + 6

    fw = 700 if n >= 5 else 600
    nc = '#60a5fa' if n >= 20 else ('#93c5fd' if n >= 5 else '#64748b')
    ns = f'{max(fs-1,6.0):.1f}px'

    out = f'<g class="wilaya" id="w_{sid}" data-name="{name}" data-orders="{n}" data-items="{items}" data-revenue="{rev}" data-pct="{p}">'
    out += f'<rect x="{x}" y="{y}" width="{width}" height="{height}" rx="2" style="fill:{fi};stroke:{st};stroke-width:{sw}"{fa}/>'
    out += f'<text x="{cx}" y="{ly}" text-anchor="middle" style="fill:{tc};font-size:{fs}px;font-family:Inter,sans-serif;font-weight:{fw};pointer-events:none">{lbl}</text>'
    if n > 0:
        out += f'<text x="{cx}" y="{ny}" text-anchor="middle" style="fill:{nc};font-size:{ns};font-family:Inter,sans-serif;pointer-events:none">{n}</text>'
    out += '</g>'
    return out


def build_svg_body():
    """
    ══════════════════════════════════════════════════════════
    CORRECTED ALGERIA GRID  (900×580 viewBox)
    
    Column widths (all 60px, x starts at 0):
      14 columns × 64px = 896px ≈ 900px
      X positions: col_i starts at i*64
    
    ROWS (5 horizontal bands):
      Row 1 (coast-top):    y=33,  h=52  → coast W wilaya top halves
      Row 2 (coast-bottom): y=85,  h=52  → coast W wilaya bottom / E coast
      Row 3 (tell/hauts):   y=137, h=75  → Tell inland + Hauts Plateaux N
      Row 4 (plateaux-S):   y=212, h=95  → Hauts Plateaux S + Saharan Atlas
      Row 5 (sahara-N):     y=307, h=105 → Northern Sahara
      Row 6 (sahara-S):     y=412, h=165 → Deep Sahara

    Algeria W→E correct column mapping:
      col  0: Tlemcen         (W coast)
      col  1: Aïn Témouchent / Oran
      col  2: Mostaganem / Sidi Bel Abbès
      col  3: Relizane / Mascara
      col  4: Chlef / Tissemsilt
      col  5: Aïn Defla / Médéa
      col  6: Tipaza / Blida
      col  7: ALGER (2-col wide)
      col  8: (Alger east side)
      col  9: Boumerdès / Bouira
      col 10: Tizi Ouzou / Béjaïa
      col 11: Jijel / Mila
      col 12: Sétif / Batna (inland of Skikda)
      col 13: Skikda / Constantine
      col 14: Oum El Bouaghi / Guelma (rightmost coast strip)
      → We have 14 cols (0-13), rightedge = 897

    NE COAST (cols 11-13):
      Jijel (coast top), Skikda (coast top), Annaba (coast top)
      Mila (behind Jijel), Constantine (behind Skikda), Guelma (behind Annaba)
      El Tarf (east of Annaba, extreme NE corner)
    ══════════════════════════════════════════════════════════
    """
    C = 64    # column width
    # Row definitions: (y_start, height)
    R = [
        (33, 52),   # R0: coast top  (y=33..84)
        (85, 52),   # R1: coast bot  (y=85..136)
        (137, 75),  # R2: tell       (y=137..211)
        (212, 95),  # R3: plateaux   (y=212..306)
        (307, 105), # R4: sahara-N   (y=307..411)
        (412, 160), # R5: sahara-S   (y=412..571)
    ]

    def Y(row): return R[row][0]
    def H(row): return R[row][1]
    def X(col): return col * C  # cols 0..13

    parts = []

    # ── ROW 0+1 MERGED: COASTAL STRIP ────────────────────────────────────
    # Cols 0: Tlemcen (tall, 2 rows) — W coast
    parts.append(w('Tlemcen',        X(0), Y(0), C,   H(0)+H(1)))

    # Col 1: Aïn Témouchent (top) / Oran (bottom)
    parts.append(w('Aïn Témouchent', X(1), Y(0), C,   H(0), 'Aïn Tém.'))
    parts.append(w('Oran',           X(1), Y(1), C,   H(1)))

    # Col 2: Mostaganem (top) / Sidi Bel Abbès (bottom)
    parts.append(w('Mostaganem',     X(2), Y(0), C,   H(0), 'Mostag.'))
    parts.append(w('Sidi Bel Abbès', X(2), Y(1), C,   H(1), 'Sidi B.A.'))

    # Col 3: Relizane (top) / Mascara (bottom)
    parts.append(w('Relizane',       X(3), Y(0), C,   H(0)))
    parts.append(w('Mascara',        X(3), Y(1), C,   H(1)))

    # Col 4: Chlef (top) / Tissemsilt (bottom)
    parts.append(w('Chlef',          X(4), Y(0), C,   H(0)))
    parts.append(w('Tissemsilt',     X(4), Y(1), C,   H(1), 'Tissems.'))

    # Col 5: Aïn Defla (top) / Médéa (bottom)
    parts.append(w('Aïn Defla',      X(5), Y(0), C,   H(0), 'Aïn Defla'))
    parts.append(w('Médéa',          X(5), Y(1), C,   H(1)))

    # Col 6: Tipaza (top) / Blida (bottom)
    parts.append(w('Tipaza',         X(6), Y(0), C,   H(0)))
    parts.append(w('Blida',          X(6), Y(1), C,   H(1)))

    # Col 7+8: ALGER (capital — double width, tall 2 rows)
    parts.append(w('Alger',          X(7), Y(0), C*2, H(0)+H(1), 'Alger'))

    # Col 9: Boumerdès (top) / Bouira (bottom)
    parts.append(w('Boumerdès',      X(9), Y(0), C,   H(0), 'Boumerd.'))
    parts.append(w('Bouira',         X(9), Y(1), C,   H(1)))

    # Col 10: Tizi Ouzou (top) / Béjaïa (bottom)
    parts.append(w('Tizi Ouzou',     X(10), Y(0), C,  H(0), 'Tizi Ouz.'))
    parts.append(w('Béjaïa',         X(10), Y(1), C,  H(1)))

    # Col 11: Jijel (top, coast) / Mila (bottom, inland)
    parts.append(w('Jijel',          X(11), Y(0), C,  H(0)))
    parts.append(w('Mila',           X(11), Y(1), C,  H(1)))

    # Col 12: Skikda (top, coast) / Constantine (bottom, inland)
    parts.append(w('Skikda',         X(12), Y(0), C,  H(0)))
    parts.append(w('Constantine',    X(12), Y(1), C,  H(1), 'Const.'))

    # Col 13: Annaba (top, coast NE) / Guelma (bottom)
    parts.append(w('Annaba',         X(13), Y(0), C,  H(0)))
    parts.append(w('Guelma',         X(13), Y(1), C,  H(1)))

    # ── ROW 2: TELL INLAND + HAUTS PLATEAUX NORD (y=137..211, h=75) ─────
    # Col 0: Naâma (SW inland)
    parts.append(w('Naâma',          X(0), Y(2), C,   H(2)))

    # Col 1: El Bayadh
    parts.append(w('El Bayadh',      X(1), Y(2), C,   H(2), 'El Bayadh'))

    # Col 2: Saïda
    parts.append(w('Saïda',          X(2), Y(2), C,   H(2)))

    # Col 3+4: Tiaret (2 cols wide)
    parts.append(w('Tiaret',         X(3), Y(2), C*2, H(2)))

    # Col 5+6: Djelfa (2 cols wide, central)
    parts.append(w('Djelfa',         X(5), Y(2), C*2, H(2)))

    # Col 7: Bordj Bou Arreridj (BBA)
    parts.append(w('Bordj Bou Arreridj', X(7), Y(2), C, H(2), 'BBA'))

    # Col 8+9: M'Sila (2 cols, central tell)
    parts.append(w("M'Sila",         X(8), Y(2), C*2, H(2), "M'Sila"))

    # Col 10: Sétif (inland of Béjaïa/Jijel)
    parts.append(w('Sétif',          X(10), Y(2), C,  H(2)))

    # Col 11: Batna (inland of Mila/Constantine)
    parts.append(w('Batna',          X(11), Y(2), C,  H(2)))

    # Col 12: Oum El Bouaghi (inland of Constantine)
    parts.append(w('Oum El Bouaghi', X(12), Y(2), C,  H(2), 'Oum El B.'))

    # Col 13: Khenchela / Tébessa / Souk Ahras / El Tarf
    #   → Split col 13 into 4 quarters vertically at row 2
    #   → But row 2 h=75, too small for 4 wilayas.
    #   → Use 2 sub-columns by splitting col13 (width 64) into two 32px cols:
    # Actually, let's use a different approach:
    #   Col 13 row2 top half (h=37): El Tarf (coastal, next to Annaba)
    #   Col 13 row2 bot half (h=38): Souk Ahras (behind Annaba)
    #   → El Tarf is actually on the COAST (next to Annaba in row0/1)
    #   → Let's move El Tarf to coast and put extra width:

    # Better approach: El Tarf is coastal NE (Skikda-adjacent, E of Annaba)
    # We extend the map slightly to show it. Since we only have 14 cols and
    # col13 is the rightmost, we show El Tarf as a sliver in row0 or a note.
    # For simplicity: El Tarf shares col13 row2 (top), Souk Ahras row2 (bot)
    # These are NE wilayas, all neighbors of Annaba/Guelma.

    # Col 13 row2: split into top/bottom (37px each)
    parts.append(w('El Tarf',        X(13), Y(2),     C,  38, 'El Tarf'))
    parts.append(w('Souk Ahras',     X(13), Y(2)+38,  C,  37, 'Souk Ah.'))

    # ── ROW 3: HAUTS PLATEAUX SUD + SAHARAN ATLAS (y=212..306, h=95) ────
    # Col 0+1: Béchar (spans 2 cols, large SW)
    parts.append(w('Béchar',         X(0), Y(3), C*2,  H(3)))

    # Col 2+3: Laghouat (central N Sahara entry, 2 cols)
    parts.append(w('Laghouat',       X(2), Y(3), C*2,  H(3)))

    # Col 4+5: Timimoun area (empty/desert, 2 cols)
    parts.append(w('Timimoun',       X(4), Y(3), C*2,  H(3)))

    # Col 6+7: El Meniaa (central Sahara, 2 cols)
    parts.append(w('El Meniaa',      X(6), Y(3), C*2,  H(3), 'El Meniaa'))

    # Col 8+9: Biskra (E Sahara entry, 2 cols)
    parts.append(w('Biskra',         X(8), Y(3), C*2,  H(3)))

    # Col 10+11: El Oued (SE Sahara, 2 cols)
    parts.append(w('El Oued',        X(10), Y(3), C*2, H(3), 'El Oued'))

    # Col 12+13: Tébessa + Khenchela split
    #   Khenchela (col12, interior SE)
    #   Tébessa (col13, extreme E border)
    parts.append(w('Khenchela',      X(12), Y(3), C,   H(3), 'Khenche.'))
    parts.append(w('Tébessa',        X(13), Y(3), C,   H(3)))

    # ── ROW 4: NORTHERN SAHARA (y=307..411, h=105) ───────────────────────
    # Col 0+1: Tindouf (extreme W, huge)
    parts.append(w('Tindouf',        X(0), Y(4), C*2,  H(4)))

    # Col 2+3: Adrar (W Sahara, 2 cols)
    parts.append(w('Adrar',          X(2), Y(4), C*2,  H(4)))

    # Col 4+5+6: In Salah (central Sahara, 3 cols)
    parts.append(w('In Salah',       X(4), Y(4), C*3,  H(4), 'In Salah'))

    # Col 7+8+9: Ghardaïa (N-central Sahara, 3 cols)
    parts.append(w('Ghardaïa',       X(7), Y(4), C*3,  H(4), 'Ghardaïa'))

    # Col 10+11: Ouargla (E Sahara, 2 cols)
    parts.append(w('Ouargla',        X(10), Y(4), C*2, H(4)))

    # Col 12+13: Illizi (SE, spans rows 4+5)
    parts.append(w('Illizi',         X(12), Y(4), C*2, H(4)+H(5)))

    # ── ROW 5: DEEP SAHARA (y=412..571, h=160) ────────────────────────────
    # Col 0+1+2+3: Tamanrasset (vast S, 4 cols)
    parts.append(w('Tamanrasset',    X(0), Y(5), C*4,  H(5), 'Tamanras.'))

    # Col 4+5+6: Djanet area / In Guezzam west part
    parts.append(w('Djanet',         X(4), Y(5), C*4,  H(5)))

    # Col 10+11: In Guezzam (extreme S, 2 cols)
    parts.append(w('In Guezzam',     X(8), Y(5), C*4,  H(5), 'In Guezzam'))
    # Illizi already covers col 12+13 rows 4+5

    return '\n'.join(parts)


def main():
    html_path = '/home/dashboard/public_html/presentation/index.html'
    with open(html_path, 'r', encoding='utf-8') as f:
        content = f.read()
    print(f"Input: {len(content):,} chars")

    # ── Build SVG body ───────────────────────────────────────────────────
    body = build_svg_body()

    new_inner = f"""
        <defs>
          <filter id="glow"><feGaussianBlur stdDeviation="2" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
          <filter id="glow-lg"><feGaussianBlur stdDeviation="4" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
        </defs>
        <!-- Background -->
        <rect x="0" y="0" width="900" height="580" fill="#080d14"/>
        <!-- Mediterranean header -->
        <rect x="0" y="0" width="900" height="32" fill="#0c1929" opacity="0.7"/>
        <text x="450" y="20" text-anchor="middle" style="fill:#1e3a5f;font-size:9px;font-family:Inter,sans-serif;font-weight:600;letter-spacing:2px">MÉDITERRANÉE</text>
        <line x1="0" y1="32" x2="900" y2="32" style="stroke:#1e3a5f;stroke-width:0.8;stroke-dasharray:4,3"/>
        <!-- Wilaya rectangles -->
{body}
        <!-- Footer -->
        <text x="450" y="575" text-anchor="middle" style="fill:#1e293b;font-size:8px;font-family:Inter,sans-serif;font-weight:600">Algérie · {TOTAL} commandes non-annulées · H1 2026 · Source: sales_order_address.region</text>
      """

    # ── Replace SVG content ──────────────────────────────────────────────
    svg_pat = r'(<svg id="algeria-map"[^>]+>)(.*?)(</svg>)'
    new_content, cnt = re.subn(
        svg_pat,
        lambda m: m.group(1) + new_inner + m.group(3),
        content,
        flags=re.DOTALL
    )
    if cnt == 0:
        print("ERROR: SVG not found"); return False
    print(f"SVG replaced: {cnt} match")

    # ── Fix subtitle ─────────────────────────────────────────────────────
    for old, new in [
        ('813 non-cancelled orders · 49 wilayas actives',
         f'{TOTAL} commandes non-annulées · 48 wilayas actives'),
        ('Source: MariaDB sales_order_address.region (real shipping data) · 813 non-cancelled orders · 49 wilayas actives · Jan–Jun 2026',
         f'Source: MariaDB sales_order_address.region · {TOTAL} commandes non-annulées · 48 wilayas actives · Jan–Jun 2026'),
        ('Source: MariaDB sales_order_address.region (données réelles) · 528 commandes non-annulées · 48 wilayas actives · Jan–Jun 2026',
         f'Source: MariaDB sales_order_address.region · {TOTAL} commandes non-annulées · 48 wilayas actives · Jan–Jun 2026'),
    ]:
        if old in new_content:
            new_content = new_content.replace(old, new)
            print(f"  Subtitle fixed")

    # ── Fix metrics panel numbers ────────────────────────────────────────
    for old, new in [
        ('<strong>813</strong>', f'<strong>{TOTAL}</strong>'),
        ('<strong>52.5%</strong>', f'<strong>{pct(164)}</strong>'),
    ]:
        if old in new_content:
            new_content = new_content.replace(old, new, 1)

    # ── Fix top 10 table ─────────────────────────────────────────────────
    top10 = sorted(WILAYA_DATA.items(), key=lambda x: -x[1]['orders'])[:10]
    top10_html = ''
    for rank, (name, d) in enumerate(top10, 1):
        n = d['orders']
        ac = ' style="color:var(--accent)"' if rank <= 3 else ''
        top10_html += f'<tr><td{ac}>{rank}</td><td><strong>{name}</strong></td><td class="num"{ac}>{n}</td><td class="num">{pct(n)}</td></tr>'

    # Replace the top10 tbody
    top10_pat = r'(<tbody>)(<tr><td[^>]*>1</td>.*?)(</tbody>\s*</table>\s*</div>\s*<div class="panel"[^>]*>\s*<h3[^>]*>🗺️)'
    new_content, c2 = re.subn(
        top10_pat,
        lambda m: m.group(1) + top10_html + m.group(3),
        new_content,
        flags=re.DOTALL
    )
    print(f"Top10 replaced: {c2}")

    # ── Validate ─────────────────────────────────────────────────────────
    od = new_content.count('<div')
    cd = new_content.count('</div')
    print(f"Div balance: {od}/{cd} diff={od-cd}")
    slides = len(re.findall(r'class="slide[" ]', new_content))
    print(f"Slides: {slides}")

    with open(html_path, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print(f"Written: {len(new_content):,} chars ✓")
    return True

if __name__ == '__main__':
    ok = main()
    exit(0 if ok else 1)
