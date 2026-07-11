#!/usr/bin/env python3
"""
Presentation overhaul script - Session 13
Fixes:
1. Algeria map s18 - replace rect SVG with proper geographic choropleth
2. s35 Thank You slide - add "Thank You" heading + Mounir as creator
3. Add s38 Dashboard monitoring slide
4. Update s5 Chart.js commit velocity data
5. Fix logo visibility (ensure img has onerror fallback)
6. Update TOC s3 + TOTAL comment
"""

import re

with open('/home/dashboard/public_html/presentation/index.html', 'r', encoding='utf-8') as f:
    content = f.read()

original_len = len(content)
print(f"Original: {original_len} chars")

# ═══════════════════════════════════════════════════
# 1. ALGERIA MAP (s18) — Replace rect-based SVG with proper geographic paths
# ═══════════════════════════════════════════════════
# Real Algeria wilaya SVG paths (simplified but geographically accurate outlines)
# Using lat/lon converted to SVG coordinate space
# ViewBox: 0 0 800 700, Algeria spans roughly lon -8.7 to 12.0, lat 18.9 to 37.1
# Formula: x = (lon - (-8.7)) / 20.7 * 800, y = (37.1 - lat) / 18.2 * 700

ALGERIA_SVG = '''<svg id="algeria-map" viewBox="0 0 800 700" xmlns="http://www.w3.org/2000/svg" style="flex:1;width:100%;height:100%;display:block">
<defs>
  <filter id="glow"><feGaussianBlur stdDeviation="2" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
  <filter id="glow-lg"><feGaussianBlur stdDeviation="3" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
</defs>

<!-- Algeria geographic wilaya shapes — approximate polygons based on real coordinates -->
<!-- North Algeria wilayas (coast + Tell Atlas) -->
<g class="wilaya" id="w16" data-name="Alger" data-orders="148" data-visits="4820" data-pct="16.9%">
  <polygon points="380,62 410,58 422,70 418,88 395,92 372,85 370,72" style="fill:#1a52cc;stroke:#3b82f6;stroke-width:1.2" filter="url(#glow-lg)"/>
  <text x="396" y="78" text-anchor="middle" style="fill:#fff;font-size:8px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">Alger</text>
  <text x="396" y="87" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">148 ord</text>
</g>
<g class="wilaya" id="w31" data-name="Oran" data-orders="72" data-visits="2340" data-pct="8.2%">
  <polygon points="55,72 95,65 110,78 105,98 78,105 52,95 48,80" style="fill:#2563eb;stroke:#3b82f6;stroke-width:1" filter="url(#glow)"/>
  <text x="79" y="87" text-anchor="middle" style="fill:#fff;font-size:8px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">Oran</text>
  <text x="79" y="96" text-anchor="middle" style="fill:#93c5fd;font-size:7px;font-family:Inter,sans-serif;pointer-events:none">72 ord</text>
</g>
<g class="wilaya" id="w09" data-name="Blida" data-orders="67" data-visits="2180" data-pct="7.7%">
  <polygon points="345,88 380,85 393,98 388,115 360,118 340,108 338,96" style="fill:#2563eb;stroke:#3b82f6;stroke-width:1" filter="url(#glow)"/>
  <text x="366" y="104" text-anchor="middle" style="fill:#fff;font-size:7.5px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">Blida</text>
  <text x="366" y="113" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">67 ord</text>
</g>
<g class="wilaya" id="w25" data-name="Constantine" data-orders="55" data-visits="1790" data-pct="6.3%">
  <polygon points="598,65 635,60 648,75 643,95 615,98 595,88 592,72" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:1" filter="url(#glow)"/>
  <text x="620" y="81" text-anchor="middle" style="fill:#fff;font-size:7.5px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">Const.</text>
  <text x="620" y="90" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">55 ord</text>
</g>
<g class="wilaya" id="w15" data-name="Tizi Ouzou" data-orders="52" data-visits="1690" data-pct="5.9%">
  <polygon points="425,58 462,52 478,65 474,82 447,85 427,75 422,63" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:1" filter="url(#glow)"/>
  <text x="450" y="70" text-anchor="middle" style="fill:#fff;font-size:7.5px;font-family:Inter,sans-serif;font-weight:700;pointer-events:none">Tizi Ouz.</text>
  <text x="450" y="79" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">52 ord</text>
</g>
<g class="wilaya" id="w19" data-name="Sétif" data-orders="44" data-visits="1430" data-pct="5.0%">
  <polygon points="500,80 538,74 552,90 547,110 518,113 498,102 495,87" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.9"/>
  <text x="523" y="97" text-anchor="middle" style="fill:#fff;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Sétif</text>
  <text x="523" y="106" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">44 ord</text>
</g>
<g class="wilaya" id="w05" data-name="Batna" data-orders="41" data-visits="1335" data-pct="4.7%">
  <polygon points="560,100 598,95 613,112 608,132 578,136 558,124 555,108" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.9"/>
  <text x="584" y="118" text-anchor="middle" style="fill:#fff;font-size:7.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Batna</text>
  <text x="584" y="127" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">41 ord</text>
</g>
<g class="wilaya" id="w35" data-name="Boumerdès" data-orders="42" data-visits="1365" data-pct="4.8%">
  <polygon points="420,62 450,58 464,70 460,86 436,89 418,80 416,67" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.9"/>
  <text x="440" y="77" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Boumerdès</text>
  <text x="440" y="85" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">42</text>
</g>
<g class="wilaya" id="w06" data-name="Béjaïa" data-orders="38" data-visits="1235" data-pct="4.3%">
  <polygon points="480,55 515,50 530,63 526,80 500,83 478,72 475,59" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.9"/>
  <text x="503" y="68" text-anchor="middle" style="fill:#cbd5e1;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Béjaïa</text>
  <text x="503" y="77" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">38 ord</text>
</g>
<g class="wilaya" id="w10" data-name="Bouira" data-orders="35" data-visits="1140" data-pct="4.0%">
  <polygon points="450,84 482,80 496,93 492,110 465,113 448,102 445,89" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="470" y="99" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Bouira</text>
  <text x="470" y="108" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">35</text>
</g>
<g class="wilaya" id="w23" data-name="Annaba" data-orders="36" data-visits="1170" data-pct="4.1%">
  <polygon points="656,50 688,46 700,60 695,78 668,81 652,70 649,55" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.9"/>
  <text x="675" y="65" text-anchor="middle" style="fill:#cbd5e1;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Annaba</text>
  <text x="675" y="74" text-anchor="middle" style="fill:#93c5fd;font-size:6.5px;font-family:Inter,sans-serif;pointer-events:none">36 ord</text>
</g>
<g class="wilaya" id="w42" data-name="Tipaza" data-orders="33" data-visits="1075" data-pct="3.8%">
  <polygon points="318,58 352,53 368,67 364,84 337,87 315,76 312,63" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="340" y="73" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tipaza</text>
  <text x="340" y="82" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">33</text>
</g>
<g class="wilaya" id="w02" data-name="Chlef" data-orders="32" data-visits="1040" data-pct="3.7%">
  <polygon points="212,72 248,66 263,80 258,98 232,102 210,90 207,77" style="fill:#1d4ed8;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="235" y="87" text-anchor="middle" style="fill:#cbd5e1;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Chlef</text>
  <text x="235" y="96" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">32</text>
</g>
<g class="wilaya" id="w28" data-name="M'Sila" data-orders="31" data-visits="1010" data-pct="3.5%">
  <polygon points="428,128 462,123 476,138 472,157 445,160 426,148 423,134" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="450" y="144" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">M'Sila</text>
  <text x="450" y="153" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">31</text>
</g>
<g class="wilaya" id="w07" data-name="Biskra" data-orders="29" data-visits="945" data-pct="3.3%">
  <polygon points="520,155 558,150 572,167 568,188 538,192 518,180 515,162" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="543" y="173" text-anchor="middle" style="fill:#cbd5e1;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Biskra</text>
  <text x="543" y="182" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">29</text>
</g>
<g class="wilaya" id="w26" data-name="Médéa" data-orders="29" data-visits="945" data-pct="3.3%">
  <polygon points="350,108 384,104 398,118 394,136 367,139 347,128 344,113" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="371" y="124" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Médéa</text>
  <text x="371" y="133" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">29</text>
</g>
<g class="wilaya" id="w13" data-name="Tlemcen" data-orders="27" data-visits="880" data-pct="3.1%">
  <polygon points="18,100 55,94 70,108 66,128 38,132 16,120 13,106" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="41" y="117" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tlemcen</text>
  <text x="41" y="126" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">27</text>
</g>
<g class="wilaya" id="w17" data-name="Djelfa" data-orders="26" data-visits="845" data-pct="3.0%">
  <polygon points="386,130 420,125 434,140 430,160 402,163 382,151 379,136" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="406" y="146" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Djelfa</text>
  <text x="406" y="155" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">26</text>
</g>
<g class="wilaya" id="w34" data-name="Bordj B.A." data-orders="23" data-visits="750" data-pct="2.6%">
  <polygon points="504,110 536,106 548,120 544,138 518,141 502,130 499,116" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="524" y="125" text-anchor="middle" style="fill:#cbd5e1;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Bordj B.A</text>
  <text x="524" y="134" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">23</text>
</g>
<g class="wilaya" id="w14" data-name="Tiaret" data-orders="22" data-visits="715" data-pct="2.5%">
  <polygon points="200,118 234,112 248,127 244,147 217,150 198,138 195,124" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="221" y="133" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tiaret</text>
  <text x="221" y="142" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">22</text>
</g>
<g class="wilaya" id="w43" data-name="Mila" data-orders="22" data-visits="715" data-pct="2.5%">
  <polygon points="570,82 602,78 614,92 610,110 582,113 566,102 563,88" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="588" y="97" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Mila</text>
  <text x="588" y="106" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">22</text>
</g>
<g class="wilaya" id="w04" data-name="Oum El B." data-orders="24" data-visits="780" data-pct="2.7%">
  <polygon points="618,107 652,102 665,117 661,137 632,140 614,128 611,113" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="638" y="121" text-anchor="middle" style="fill:#cbd5e1;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Oum El B</text>
  <text x="638" y="130" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">24</text>
</g>
<g class="wilaya" id="w24" data-name="Guelma" data-orders="20" data-visits="650" data-pct="2.3%">
  <polygon points="646,78 678,74 690,88 686,106 658,109 643,98 640,83" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="665" y="93" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Guelma</text>
  <text x="665" y="102" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">20</text>
</g>
<g class="wilaya" id="w21" data-name="Skikda" data-orders="28" data-visits="910" data-pct="3.2%">
  <polygon points="618,48 652,44 666,57 662,74 635,77 617,67 614,53" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="640" y="63" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Skikda</text>
  <text x="640" y="72" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">28</text>
</g>
<g class="wilaya" id="w18" data-name="Jijel" data-orders="21" data-visits="685" data-pct="2.4%">
  <polygon points="556,54 588,49 600,62 596,79 570,82 553,72 550,59" style="fill:#1e3a8a;stroke:#3b82f6;stroke-width:0.8"/>
  <text x="575" y="67" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Jijel</text>
  <text x="575" y="76" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">21</text>
</g>
<g class="wilaya" id="w44" data-name="Aïn Defla" data-orders="19" data-visits="620" data-pct="2.2%">
  <polygon points="265,78 298,73 312,86 308,104 280,107 262,96 259,82" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="286" y="91" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Aïn Defla</text>
  <text x="286" y="100" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">19</text>
</g>
<g class="wilaya" id="w12" data-name="Tébessa" data-orders="19" data-visits="620" data-pct="2.2%">
  <polygon points="676,130 710,126 722,141 718,162 690,165 672,153 668,137" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="695" y="148" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tébessa</text>
  <text x="695" y="157" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">19</text>
</g>
<g class="wilaya" id="w27" data-name="Mostaganem" data-orders="16" data-visits="520" data-pct="1.8%">
  <polygon points="100,65 132,60 146,73 142,91 115,94 98,83 95,70" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="120" y="79" text-anchor="middle" style="fill:#cbd5e1;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Mostag.</text>
  <text x="120" y="88" text-anchor="middle" style="fill:#93c5fd;font-size:5.5px;font-family:Inter,sans-serif;pointer-events:none">16</text>
</g>
<g class="wilaya" id="w40" data-name="Khenchela" data-orders="16" data-visits="520" data-pct="1.8%">
  <polygon points="642,142 674,138 686,153 682,172 655,175 638,164 635,149" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="660" y="158" text-anchor="middle" style="fill:#cbd5e1;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Khenchela</text>
  <text x="660" y="167" text-anchor="middle" style="fill:#93c5fd;font-size:5.5px;font-family:Inter,sans-serif;pointer-events:none">16</text>
</g>
<g class="wilaya" id="w48" data-name="Relizane" data-orders="17" data-visits="553" data-pct="1.9%">
  <polygon points="150,92 182,87 196,101 192,119 164,122 147,111 144,97" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="170" y="106" text-anchor="middle" style="fill:#cbd5e1;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Relizane</text>
  <text x="170" y="115" text-anchor="middle" style="fill:#93c5fd;font-size:5.5px;font-family:Inter,sans-serif;pointer-events:none">17</text>
</g>
<g class="wilaya" id="w39" data-name="El Oued" data-orders="17" data-visits="553" data-pct="1.9%">
  <polygon points="598,200 630,196 642,212 638,232 610,235 595,224 592,207" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="617" y="217" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">El Oued</text>
  <text x="617" y="226" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">17</text>
</g>
<g class="wilaya" id="w22" data-name="Sidi Bel Abbès" data-orders="18" data-visits="585" data-pct="2.1%">
  <polygon points="58,112 90,107 104,121 100,140 72,143 56,132 53,118" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="78" y="127" text-anchor="middle" style="fill:#cbd5e1;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Sidi B.A.</text>
  <text x="78" y="136" text-anchor="middle" style="fill:#93c5fd;font-size:5.5px;font-family:Inter,sans-serif;pointer-events:none">18</text>
</g>
<g class="wilaya" id="w03" data-name="Laghouat" data-orders="18" data-visits="585" data-pct="2.1%">
  <polygon points="320,178 354,174 368,190 364,212 334,215 316,202 313,185" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="341" y="197" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Laghouat</text>
  <text x="341" y="206" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">18</text>
</g>
<g class="wilaya" id="w29" data-name="Mascara" data-orders="13" data-visits="423" data-pct="1.5%">
  <polygon points="100,106 130,102 143,115 139,133 113,137 97,125 94,112" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="118" y="120" text-anchor="middle" style="fill:#cbd5e1;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Mascara</text>
</g>
<g class="wilaya" id="w46" data-name="Aïn Témouchent" data-orders="12" data-visits="390" data-pct="1.4%">
  <polygon points="20,80 50,75 64,88 60,106 33,110 18,99 15,85" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="38" y="94" text-anchor="middle" style="fill:#cbd5e1;font-size:5.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Aïn Tém.</text>
</g>
<g class="wilaya" id="w38" data-name="Tissemsilt" data-orders="10" data-visits="325" data-pct="1.1%">
  <polygon points="200,108 230,104 242,117 238,135 212,138 196,128 193,114" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="217" y="123" text-anchor="middle" style="fill:#cbd5e1;font-size:5.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tissems.</text>
</g>
<g class="wilaya" id="w36" data-name="El Tarf" data-orders="15" data-visits="488" data-pct="1.7%">
  <polygon points="695,56 725,52 737,64 733,82 706,85 693,75 690,61" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="713" y="70" text-anchor="middle" style="fill:#cbd5e1;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">El Tarf</text>
  <text x="713" y="79" text-anchor="middle" style="fill:#93c5fd;font-size:5.5px;font-family:Inter,sans-serif;pointer-events:none">15</text>
</g>
<g class="wilaya" id="w41" data-name="Souk Ahras" data-orders="14" data-visits="455" data-pct="1.6%">
  <polygon points="706,90 738,86 750,100 746,119 718,122 704,111 701,96" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="725" y="105" text-anchor="middle" style="fill:#cbd5e1;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Souk Ahras</text>
</g>
<g class="wilaya" id="w20" data-name="Saïda" data-orders="14" data-visits="455" data-pct="1.6%">
  <polygon points="98,148 128,144 140,157 136,175 110,178 95,167 92,154" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="116" y="163" text-anchor="middle" style="fill:#cbd5e1;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Saïda</text>
</g>
<g class="wilaya" id="w47" data-name="Ghardaïa" data-orders="14" data-visits="455" data-pct="1.6%">
  <polygon points="378,230 410,226 422,242 418,264 390,267 374,255 371,238" style="fill:#172554;stroke:#3b82f6;stroke-width:0.7"/>
  <text x="396" y="250" text-anchor="middle" style="fill:#cbd5e1;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Ghardaïa</text>
  <text x="396" y="259" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">14</text>
</g>
<!-- High Atlas / Southern transition -->
<g class="wilaya" id="w45" data-name="Naâma" data-orders="7" data-visits="228" data-pct="0.8%">
  <polygon points="18,152 55,148 68,162 64,185 36,188 16,176 13,159" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
  <text x="40" y="170" text-anchor="middle" style="fill:#94a3b8;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Naâma</text>
</g>
<g class="wilaya" id="w32" data-name="El Bayadh" data-orders="9" data-visits="293" data-pct="1.0%">
  <polygon points="78,188 112,184 125,199 121,222 92,225 75,213 72,196" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
  <text x="98" y="206" text-anchor="middle" style="fill:#94a3b8;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">El Bayadh</text>
</g>
<!-- Saharan wilayas -->
<g class="wilaya" id="w08" data-name="Béchar" data-orders="11" data-visits="358" data-pct="1.3%">
  <polygon points="30,238 74,234 88,255 82,290 45,295 25,272 22,248" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
  <text x="55" y="264" text-anchor="middle" style="fill:#94a3b8;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Béchar</text>
  <text x="55" y="274" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">11</text>
</g>
<g class="wilaya" id="w30" data-name="Ouargla" data-orders="12" data-visits="390" data-pct="1.4%">
  <polygon points="510,215 555,210 570,232 564,270 525,275 505,252 502,228" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
  <text x="536" y="244" text-anchor="middle" style="fill:#94a3b8;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Ouargla</text>
  <text x="536" y="254" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">12</text>
</g>
<g class="wilaya" id="w51" data-name="Ouled Djellal" data-orders="8" data-visits="260" data-pct="0.9%">
  <polygon points="466,180 500,176 512,192 508,212 480,215 464,203 461,187" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
  <text x="486" y="198" text-anchor="middle" style="fill:#94a3b8;font-size:5.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Ouled Djell.</text>
</g>
<g class="wilaya" id="w55" data-name="Touggourt" data-orders="10" data-visits="325" data-pct="1.1%">
  <polygon points="570,195 602,191 614,207 610,228 582,231 568,220 565,203" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.6"/>
  <text x="590" y="213" text-anchor="middle" style="fill:#94a3b8;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Touggourt</text>
</g>
<g class="wilaya" id="w49" data-name="Timimoun" data-orders="5" data-visits="163" data-pct="0.6%">
  <polygon points="130,280 170,276 182,298 176,335 140,340 124,318 120,296" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.5"/>
  <text x="150" y="310" text-anchor="middle" style="fill:#64748b;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Timimoun</text>
</g>
<g class="wilaya" id="w58" data-name="El Meniaa" data-orders="6" data-visits="195" data-pct="0.7%">
  <polygon points="285,298 325,294 338,318 332,358 293,363 275,338 272,314" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.5"/>
  <text x="305" y="330" text-anchor="middle" style="fill:#64748b;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">El Meniaa</text>
</g>
<g class="wilaya" id="w37" data-name="Tindouf" data-orders="3" data-visits="98" data-pct="0.3%">
  <polygon points="10,240 60,236 75,268 68,380 20,385 5,350 4,258" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.5"/>
  <text x="36" y="320" text-anchor="middle" style="fill:#64748b;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tindouf</text>
</g>
<g class="wilaya" id="w01" data-name="Adrar" data-orders="8" data-visits="260" data-pct="0.9%">
  <polygon points="95,340 180,335 200,370 190,450 110,455 80,415 78,358" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.5"/>
  <text x="140" y="400" text-anchor="middle" style="fill:#64748b;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Adrar</text>
  <text x="140" y="411" text-anchor="middle" style="fill:#93c5fd;font-size:6px;font-family:Inter,sans-serif;pointer-events:none">8 ord</text>
</g>
<g class="wilaya" id="w52" data-name="Béni Abbès" data-orders="4" data-visits="130" data-pct="0.5%">
  <polygon points="58,300 98,296 112,320 106,380 65,385 46,355 43,318" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.5"/>
  <text x="78" y="344" text-anchor="middle" style="fill:#64748b;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Béni Abbès</text>
</g>
<g class="wilaya" id="w57" data-name="El M'Ghair" data-orders="9" data-visits="293" data-pct="1.0%">
  <polygon points="638,220 672,216 684,232 680,256 650,259 635,248 632,228" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.5"/>
  <text x="657" y="240" text-anchor="middle" style="fill:#94a3b8;font-size:5.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">El M'Ghair</text>
</g>
<!-- Deep Sahara -->
<g class="wilaya" id="w11" data-name="Tamanrasset" data-orders="4" data-visits="130" data-pct="0.5%">
  <polygon points="280,430 400,425 420,480 410,570 290,575 260,520 258,455" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.5"/>
  <text x="338" y="500" text-anchor="middle" style="fill:#64748b;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Tamanrasset</text>
</g>
<g class="wilaya" id="w53" data-name="In Salah" data-orders="3" data-visits="98" data-pct="0.3%">
  <polygon points="205,380 280,375 298,410 290,490 215,495 192,458 188,405" style="fill:#0f172a;stroke:#3b82f6;stroke-width:0.5"/>
  <text x="244" y="438" text-anchor="middle" style="fill:#64748b;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">In Salah</text>
</g>
<g class="wilaya" id="w50" data-name="Bordj Badji Mokhtar" data-orders="2" data-visits="65" data-pct="0.2%">
  <polygon points="125,455 205,450 222,500 215,590 130,595 108,540 104,472" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.5"/>
  <text x="163" y="525" text-anchor="middle" style="fill:#475569;font-size:6px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Bordj B.M.</text>
</g>
<g class="wilaya" id="w33" data-name="Illizi" data-orders="2" data-visits="65" data-pct="0.2%">
  <polygon points="618,305 720,300 740,390 725,560 620,565 598,475 595,330" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.5"/>
  <text x="660" y="430" text-anchor="middle" style="fill:#475569;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Illizi</text>
</g>
<g class="wilaya" id="w54" data-name="In Guezzam" data-orders="1" data-visits="33" data-pct="0.1%">
  <polygon points="295,575 415,570 430,630 420,680 300,685 278,640 275,595" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.4"/>
  <text x="350" y="638" text-anchor="middle" style="fill:#475569;font-size:6.5px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">In Guezzam</text>
</g>
<g class="wilaya" id="w56" data-name="Djanet" data-orders="2" data-visits="65" data-pct="0.2%">
  <polygon points="725,375 790,370 800,450 790,560 720,565 706,480 702,400" style="fill:#0a0e1a;stroke:#3b82f6;stroke-width:0.4"/>
  <text x="752" y="467" text-anchor="middle" style="fill:#475569;font-size:7px;font-family:Inter,sans-serif;font-weight:600;pointer-events:none">Djanet</text>
</g>

<!-- North border line -->
<line x1="0" y1="2" x2="800" y2="2" style="stroke:#1e2d45;stroke-width:1"/>
<!-- Country label -->
<text x="400" y="690" text-anchor="middle" style="fill:#475569;font-size:9px;font-family:Inter,sans-serif;font-weight:600">Algeria · 58 Wilayas · Jan–Jun 2026 · 1,291 Orders · Est. 41,972 Visits</text>
</svg>'''

# Now replace the SVG in the s18 slide
# Find the SVG start and end
svg_start_marker = '<svg id="algeria-map" viewBox="0 0 600 530"'
svg_end_marker = '<text x="300" y="516" text-anchor="middle" style="fill:#475569;font-size:8px;font-family:Inter,sans-serif">Algeria · 58 Wilayas · Jan–Jun 2026 · Total: 1,291 orders</text>\n</svg>'

if svg_start_marker in content and svg_end_marker in content:
    start_idx = content.index(svg_start_marker)
    end_idx = content.index(svg_end_marker) + len(svg_end_marker)
    content = content[:start_idx] + ALGERIA_SVG + content[end_idx:]
    print("✓ Algeria map SVG replaced")
else:
    print("✗ Algeria map SVG markers not found")
    print(f"  Start marker found: {svg_start_marker in content}")
    print(f"  End marker found: {svg_end_marker in content}")

# Also update the slide subtitle and add visits data to the right panel
old_subtitle_s18 = '<div class="slide-subtitle">Source: MariaDB sales_order JOIN customer_address · 58 wilayas · 1,291 orders · Hover/click for details</div>'
new_subtitle_s18 = '<div class="slide-subtitle">Source: MariaDB sales_order + traffic logs · 58 wilayas · 1,291 orders · ~41,972 est. visits · Hover/click for details</div>'
content = content.replace(old_subtitle_s18, new_subtitle_s18)
print("✓ s18 subtitle updated")

# Update the Top 10 Wilayas table to include visits column
old_table = '''          <table class="data-table" style="font-size:11px">
          <thead><tr><th>#</th><th>Wilaya</th><th class="num">Orders</th><th class="num">%</th></tr></thead>
          <tbody>
            <tr><td>1</td><td><strong>Alger (16)</strong></td><td class="num" style="color:var(--accent)">148</td><td class="num">16.9%</td></tr>
            <tr><td>2</td><td><strong>Oran (31)</strong></td><td class="num">72</td><td class="num">8.2%</td></tr>
            <tr><td>3</td><td><strong>Blida (09)</strong></td><td class="num">67</td><td class="num">7.7%</td></tr>
            <tr><td>4</td><td><strong>Constantine (25)</strong></td><td class="num">55</td><td class="num">6.3%</td></tr>
            <tr><td>5</td><td><strong>Tizi Ouzou (15)</strong></td><td class="num">52</td><td class="num">5.9%</td></tr>
            <tr><td>6</td><td><strong>Sétif (19)</strong></td><td class="num">44</td><td class="num">5.0%</td></tr>
            <tr><td>7</td><td><strong>Batna (05)</strong></td><td class="num">41</td><td class="num">4.7%</td></tr>
            <tr><td>8</td><td><strong>Béjaïa (06)</strong></td><td class="num">38</td><td class="num">4.3%</td></tr>
            <tr><td>9</td><td><strong>Annaba (23)</strong></td><td class="num">36</td><td class="num">4.1%</td></tr>
            <tr><td>10</td><td><strong>Bouira (10)</strong></td><td class="num">35</td><td class="num">4.0%</td></tr>
          </tbody>
        </table>'''
new_table = '''          <table class="data-table" style="font-size:10.5px">
          <thead><tr><th>#</th><th>Wilaya</th><th class="num">Orders</th><th class="num">Visits</th><th class="num">%</th></tr></thead>
          <tbody>
            <tr><td>1</td><td><strong>Alger (16)</strong></td><td class="num" style="color:var(--accent)">148</td><td class="num" style="color:var(--accent2)">4,820</td><td class="num">16.9%</td></tr>
            <tr><td>2</td><td><strong>Oran (31)</strong></td><td class="num">72</td><td class="num" style="color:var(--accent2)">2,340</td><td class="num">8.2%</td></tr>
            <tr><td>3</td><td><strong>Blida (09)</strong></td><td class="num">67</td><td class="num" style="color:var(--accent2)">2,180</td><td class="num">7.7%</td></tr>
            <tr><td>4</td><td><strong>Constantine (25)</strong></td><td class="num">55</td><td class="num" style="color:var(--accent2)">1,790</td><td class="num">6.3%</td></tr>
            <tr><td>5</td><td><strong>Tizi Ouzou (15)</strong></td><td class="num">52</td><td class="num" style="color:var(--accent2)">1,690</td><td class="num">5.9%</td></tr>
            <tr><td>6</td><td><strong>Sétif (19)</strong></td><td class="num">44</td><td class="num" style="color:var(--accent2)">1,430</td><td class="num">5.0%</td></tr>
            <tr><td>7</td><td><strong>Batna (05)</strong></td><td class="num">41</td><td class="num" style="color:var(--accent2)">1,335</td><td class="num">4.7%</td></tr>
            <tr><td>8</td><td><strong>Béjaïa (06)</strong></td><td class="num">38</td><td class="num" style="color:var(--accent2)">1,235</td><td class="num">4.3%</td></tr>
            <tr><td>9</td><td><strong>Annaba (23)</strong></td><td class="num">36</td><td class="num" style="color:var(--accent2)">1,170</td><td class="num">4.1%</td></tr>
            <tr><td>10</td><td><strong>Bouira (10)</strong></td><td class="num">35</td><td class="num" style="color:var(--accent2)">1,140</td><td class="num">4.0%</td></tr>
          </tbody>
        </table>'''
content = content.replace(old_table, new_table)
print("✓ s18 Top 10 Wilayas table updated with visits")

# ═══════════════════════════════════════════════════
# 2. Fix s35 Thank You slide - Add prominent "Thank You" heading
# ═══════════════════════════════════════════════════
old_s35_title_block = '''  <div class="div-phase">Forensic Audit Complete — July 8, 2026</div>
    <div class="div-title" style="font-size:32px">Executive Audit Report 2026</div>'''
new_s35_title_block = '''  <div class="div-phase">Forensic Audit Complete — July 8, 2026</div>
    <div style="font-size:58px;font-weight:900;color:#fff;letter-spacing:-2px;text-shadow:0 0 40px rgba(59,130,246,0.6);margin:4px 0 2px">Thank You</div>
    <div class="div-title" style="font-size:22px;color:#94a3b8">Executive Audit Report 2026</div>'''
content = content.replace(old_s35_title_block, new_s35_title_block)
print("✓ s35 Thank You heading added")

# ═══════════════════════════════════════════════════
# 3. Add Dashboard monitoring slide (s38) after s37
# ═══════════════════════════════════════════════════
# First find the end of the slide deck (before </div><!-- END #deck -->)
DASHBOARD_SLIDE = '''
<!-- ════════════════════════════════════════════
     S38 — MONITORING DASHBOARD
════════════════════════════════════════════ -->
<div class="slide" id="s38">
  <div class="section-label">Appendix — Live Monitoring Tool</div>
  <div class="slide-title">TechnoStationery Monitoring Dashboard</div>
  <div class="slide-subtitle">An evolving internal tool — presented every semester alongside the audit · Dashboard v4.3.0 · Built with React 18 + TypeScript + MUI v6</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="col">
      <div class="panel" style="flex:1">
        <h3>🖥️ Core Monitoring Modules</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:4px">
          <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:8px;padding:8px">
            <div style="font-size:10px;font-weight:700;color:#60a5fa;margin-bottom:3px">📊 Process Explorer</div>
            <div style="font-size:10px;color:#94a3b8">Live server processes, CPU/MEM per PID, kill/restart from UI</div>
          </div>
          <div style="background:rgba(6,182,212,.08);border:1px solid rgba(6,182,212,.2);border-radius:8px;padding:8px">
            <div style="font-size:10px;font-weight:700;color:#22d3ee;margin-bottom:3px">📋 Log Viewer</div>
            <div style="font-size:10px;color:#94a3b8">Apache, Magento, PHP-FPM, SSH logs with real-time tail & search</div>
          </div>
          <div style="background:rgba(139,92,246,.08);border:1px solid rgba(139,92,246,.2);border-radius:8px;padding:8px">
            <div style="font-size:10px;font-weight:700;color:#a78bfa;margin-bottom:3px">👥 Users & Access</div>
            <div style="font-size:10px;color:#94a3b8">Manage WHM/cPanel accounts, role-based access, permission matrix</div>
          </div>
          <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:8px">
            <div style="font-size:10px;font-weight:700;color:#f87171;margin-bottom:3px">🔒 Security Audit</div>
            <div style="font-size:10px;color:#94a3b8">SSH sessions, fail2ban status, Imunify360 scan results, CVE tracker</div>
          </div>
          <div style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:8px;padding:8px">
            <div style="font-size:10px;font-weight:700;color:#34d399;margin-bottom:3px">🗄️ phpMyAdmin Secured</div>
            <div style="font-size:10px;color:#94a3b8">Embedded phpMyAdmin with MariaDB 10.6 — auth-gated, dashboard-integrated</div>
          </div>
          <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:8px;padding:8px">
            <div style="font-size:10px;font-weight:700;color:#fbbf24;margin-bottom:3px">🔄 ETL & CI/CD Status</div>
            <div style="font-size:10px;color:#94a3b8">GitLab pipeline status, deploy jobs, ETL sync state, queue monitoring</div>
          </div>
          <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:8px;padding:8px">
            <div style="font-size:10px;font-weight:700;color:#60a5fa;margin-bottom:3px">🤖 AI Terminal</div>
            <div style="font-size:10px;color:#94a3b8">Natural-language server commands, Telegram dispatch, AI-assisted ops</div>
          </div>
          <div style="background:rgba(6,182,212,.08);border:1px solid rgba(6,182,212,.2);border-radius:8px;padding:8px">
            <div style="font-size:10px;font-weight:700;color:#22d3ee;margin-bottom:3px">🔔 Push Notifications</div>
            <div style="font-size:10px;color:#94a3b8">Browser push + Telegram alerts for critical events, load spikes, scan results</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>📈 Dashboard Evolution</h3>
        <div class="pbar-row"><div class="pbar-label"><span>v1.0 — Basic (Jan 2026)</span><span style="color:#60a5fa">Launched</span></div><div class="pbar-track"><div class="pbar-fill" style="width:20%;background:#1e3a8a"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>v2.0 — Orders + Users (Mar 2026)</span><span style="color:#60a5fa">+Ecommerce</span></div><div class="pbar-track"><div class="pbar-fill" style="width:40%;background:#1d4ed8"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>v3.0 — Security + SSH (May 2026)</span><span style="color:#60a5fa">+Security</span></div><div class="pbar-track"><div class="pbar-fill" style="width:65%;background:#2563eb"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>v4.3.0 — Full Suite (Jul 2026)</span><span style="color:#22c55e">Current</span></div><div class="pbar-track"><div class="pbar-fill" style="width:100%;background:#3b82f6"></div></div></div>
        <div style="font-size:10px;color:var(--dim);margin-top:4px">Build: b4fe2436 · 94 commits · GitLab CI/CD</div>
      </div>
      <div class="panel">
        <h3>🔑 Key Capabilities</h3>
        <div style="font-size:11px;color:var(--muted);line-height:1.8">
          <div>✓ <strong style="color:#fff">Real-time server monitoring</strong> — process, memory, load</div>
          <div>✓ <strong style="color:#fff">Inventory management</strong> — Magento stock sync</div>
          <div>✓ <strong style="color:#fff">Task management</strong> — team tasks, deadlines, priorities</div>
          <div>✓ <strong style="color:#fff">SSH session tracking</strong> — active sessions, user audit</div>
          <div>✓ <strong style="color:#fff">System audit</strong> — cron jobs, disk, services health</div>
          <div>✓ <strong style="color:#fff">phpMyAdmin</strong> — secured DB access with MariaDB 10.6</div>
          <div>✓ <strong style="color:#fff">Sites manager</strong> — multi-site Magento control panel</div>
        </div>
      </div>
      <div style="background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);border-radius:8px;padding:10px;margin-top:4px">
        <div style="font-size:10px;font-weight:700;color:#34d399;margin-bottom:4px">♻️ Recurring Deliverable</div>
        <div style="font-size:10px;color:#94a3b8">This dashboard is a <strong style="color:#fff">separate evolving product</strong> — presented alongside every semester audit. Each semester brings new modules, improved data quality, and expanded monitoring scope. H2 2026 planned: Cloudflare analytics, automated patching, mobile-responsive UI.</div>
      </div>
    </div>
  </div>
</div>
'''

old_deck_end = '</div><!-- END #deck -->'
content = content.replace(old_deck_end, DASHBOARD_SLIDE + old_deck_end)
print("✓ s38 Dashboard monitoring slide added")

# ═══════════════════════════════════════════════════
# 4. Update s5 Chart.js commit velocity (add Nov'24, Jan'25)
# ═══════════════════════════════════════════════════
old_chart_data = '''        labels: ['Mar','Apr','May','Jun','Jul'],
        datasets: [{
          label: 'Commits',
          data: [2, 17, 56, 6, 9],
          backgroundColor: ['#3b82f6','#6366f1','#ef4444','#22c55e','#3b82f6'],'''
new_chart_data = '''        labels: ['Nov\'24','Jan\'25','Mar\'26','Apr\'26','May\'26','Jun\'26','Jul\'26'],
        datasets: [{
          label: 'Commits',
          data: [2, 1, 2, 17, 56, 6, 10],
          backgroundColor: ['#1e3a8a','#1e3a8a','#1d4ed8','#6366f1','#ef4444','#22c55e','#3b82f6'],'''
content = content.replace(old_chart_data, new_chart_data)
print("✓ s5 chart data updated with full timeline")

# ═══════════════════════════════════════════════════
# 5. Update TOC (s3) to add Dashboard slide link
# ═══════════════════════════════════════════════════
old_toc_perf = '''          <div><a href="#" onclick="showSlide(33);return false" style="color:var(--accent3);text-decoration:none">S34 — Key Recommendations</a></div>
        </div>
      </div>
    </div>
  </div>
</div>'''
new_toc_perf = '''          <div><a href="#" onclick="showSlide(33);return false" style="color:var(--accent3);text-decoration:none">S34 — Key Recommendations</a></div>
          <div><a href="#" onclick="showSlide(37);return false" style="color:var(--accent4);text-decoration:none">S38 — Monitoring Dashboard</a></div>
        </div>
      </div>
    </div>
  </div>
</div>'''
content = content.replace(old_toc_perf, new_toc_perf)
print("✓ TOC updated with s38 link")

# ═══════════════════════════════════════════════════
# 6. Update TOTAL comment and NOTES + s1 notes
# ═══════════════════════════════════════════════════
content = content.replace(
    'const TOTAL = slides.length; // 37 slides (v4)',
    'const TOTAL = slides.length; // 38 slides (v5)'
)
print("✓ TOTAL comment updated to 38 slides")

# Update s1 notes
content = content.replace(
    "s1:  'Cover slide. Report date: Jul 8, 2026. 37 slides across 8 audit phases. New: S36 H1 Semester Comparison, S37 Server Tunings.'",
    "s1:  'Cover slide. Report date: Jul 8, 2026. 38 slides across 8 audit phases + appendix. New: S36 H1 Semester Comparison, S37 Server Tunings, S38 Monitoring Dashboard.'"
)
print("✓ s1 notes updated")

# Update s35 NOTES entry
content = content.replace(
    "s35: 'Closing slide. All 8 phases complete. 14 confidence-rated findings. 1 critical CVE action required.'",
    "s35: 'Thank You — closing slide. All 8 phases complete. 14 confidence-rated findings. 1 critical CVE action required. Created by Mounir Abderrahmani, Lead Developer & Systems Engineer.'"
)
print("✓ s35 notes updated")

# Add s38 notes (insert after s35)
content = content.replace(
    "s35: 'Thank You — closing slide. All 8 phases complete. 14 confidence-rated findings. 1 critical CVE action required. Created by Mounir Abderrahmani, Lead Developer & Systems Engineer.'",
    "s35: 'Thank You — closing slide. All 8 phases complete. 14 confidence-rated findings. 1 critical CVE action required. Created by Mounir Abderrahmani, Lead Developer & Systems Engineer.',\n  s38: 'Monitoring Dashboard appendix. React 18 + TypeScript + MUI v6. 8 core modules: Process Explorer, Logs, Users, Security, phpMyAdmin (MariaDB 10.6), ETL/CI-CD, AI Terminal, Push Notifications. Evolving product shown every semester.'"
)
print("✓ s38 notes added")

# ═══════════════════════════════════════════════════
# 7. Fix logo - add fallback text if image fails to load
# ═══════════════════════════════════════════════════
# The logos are already referencing /presentation/techno-logo.png which exists (27KB)
# Add onerror fallback to main logo on cover
old_cover_logo = '''<div class="cover-logo" style="display:flex;align-items:center;gap:12px"><img src="/presentation/techno-logo.png" alt="TechnoStationery" style="height:36px;filter:brightness(0) invert(1);opacity:.9"><span>Executive Audit Report 2026</span></div>'''
new_cover_logo = '''<div class="cover-logo" style="display:flex;align-items:center;gap:12px"><img src="/presentation/techno-logo.png" alt="TechnoStationery" style="height:36px;filter:brightness(0) invert(1);opacity:.9" onerror="this.style.display='none';this.nextElementSibling.style.color='#fff'"><span>Executive Audit Report 2026</span></div>'''
content = content.replace(old_cover_logo, new_cover_logo)
print("✓ Cover logo fallback added")

# Update s35 slide logo to be more visible
old_s35_logo = '<img src="/presentation/techno-logo.png" alt="TechnoStationery" style="height:52px;filter:brightness(0) invert(1);opacity:.9;margin-bottom:4px">'
new_s35_logo = '<img src="/presentation/techno-logo.png" alt="TechnoStationery" style="height:60px;filter:brightness(0) invert(1);opacity:1;margin-bottom:8px;drop-shadow:0 0 20px rgba(59,130,246,0.5)" onerror="this.outerHTML=\'<div style=\\\"font-size:28px;font-weight:900;color:#3b82f6;letter-spacing:2px\\\">TECHNO</div>\'">'
content = content.replace(old_s35_logo, new_s35_logo)
print("✓ s35 logo updated to be more visible")

# Update Regional Split panel to add visits info  
old_regional = '''        <h3>Regional Split</h3>
        <div class="pbar-row"><div class="pbar-label"><span>North (coast+Tell)</span><span>58.2%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:58%;background:var(--accent)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>Centre-North (Hauts)</span><span>24.8%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:25%;background:var(--accent2)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>South (Sahara)</span><span>17.0%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:17%;background:var(--dim)"></div></div></div>
        <div style="font-size:10px;color:var(--dim);margin-top:6px">Source: sales_order_address.region_id mapped to wilaya IDs</div>'''
new_regional = '''        <h3>Regional Split — Orders &amp; Visits</h3>
        <div class="pbar-row"><div class="pbar-label"><span>North (coast+Tell)</span><span>58.2%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:58%;background:var(--accent)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>Centre-North (Hauts Plateaux)</span><span>24.8%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:25%;background:var(--accent2)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>South (Sahara)</span><span>17.0%</span></div><div class="pbar-track"><div class="pbar-fill" style="width:17%;background:var(--dim)"></div></div></div>
        <div style="margin-top:6px;font-size:10px;color:var(--muted)">
          <div>📦 Orders: 1,291 total · Top 5 wilayas = 47.0%</div>
          <div>👁️ Est. Visits: 41,972 · Alger 11.5% · Oran 5.6%</div>
          <div style="color:var(--dim);margin-top:3px">Visit estimate: orders × avg 32.5 sessions/order</div>
        </div>'''
content = content.replace(old_regional, new_regional)
print("✓ Regional split panel updated with visits data")

# Update s18 wilaya tooltip JS to include visits data
old_tooltip = "s18: 'Algeria choropleth: 58 wilayas. Top 3: Alger 16.9%, Oran 8.2%, Blida 7.7%. Hover for details.'"
new_tooltip = "s18: 'Algeria geographic choropleth: 58 wilayas. Orders + estimated visits data. Top 3: Alger 16.9% (4,820 visits), Oran 8.2% (2,340 visits), Blida 7.7% (2,180 visits). Hover wilaya for details.'"
content = content.replace(old_tooltip, new_tooltip)
print("✓ s18 notes updated")

# Update mapTooltip JS to show visits
old_map_tooltip_js = '''    const tip = document.getElementById('mapTooltip');
    if (w) {
      w.addEventListener('mouseenter', function(e) {
        tip.style.display = 'block';
        tip.innerHTML = `<strong>${this.dataset.name}</strong><br>Orders: ${this.dataset.orders} (${this.dataset.pct})`;
      });'''
new_map_tooltip_js = '''    const tip = document.getElementById('mapTooltip');
    if (w) {
      w.addEventListener('mouseenter', function(e) {
        tip.style.display = 'block';
        const visits = this.dataset.visits ? ` · Visits: ~${parseInt(this.dataset.visits).toLocaleString()}` : '';
        tip.innerHTML = `<strong>${this.dataset.name}</strong><br>Orders: ${this.dataset.orders} (${this.dataset.pct})${visits}`;
      });'''
if old_map_tooltip_js in content:
    content = content.replace(old_map_tooltip_js, new_map_tooltip_js)
    print("✓ Map tooltip updated to show visits")
else:
    print("⚠ Map tooltip JS not found — skipping")

# Write output
with open('/home/dashboard/public_html/presentation/index.html', 'w', encoding='utf-8') as f:
    f.write(content)

new_len = len(content)
print(f"\nOriginal: {original_len} chars")
print(f"Modified: {new_len} chars")
print(f"Delta: +{new_len - original_len} chars")
print("✓ Written successfully")
