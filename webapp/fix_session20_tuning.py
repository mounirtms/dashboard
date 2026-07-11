#!/usr/bin/env python3
"""Session 20 — Comprehensive tuning, date corrections, JS optimization.

Fixes applied:
1. s8 subtitle: "Jul 8" → "Jul 9" (verified date)
2. s37 subtitle: "Jul 8" → "Jul 9"
3. s37 Load KPI sub: "↓ from 8.7 (May 5)" → "↓ from 15.37 (May 5)"
4. s37 PHP-FPM tuning: "pm=static max=30" → "pm=static max=66" (consistent with s8 Static/66)
5. s8 resource panel: "Current (Jul 8)" → "Current (Jul 9)"
6. NOTES s1 cover date: "Jul 8, 2026" → "Jul 9, 2026"
7. s21 timeline "Report Date": "Jul 8" → "Jul 9"
8. s24 chart title: "Jun 8 to Jul 8" → "Jun 8 to Jul 9"
9. JS chart animation: add animation.duration=300 to all 16 chart options blocks
10. s34 Amasty count fix: "12 modules" → "10+ modules" (consistent with s33)
11. s37 Ecomscan KPI: "Jul 8" → "Jul 9"
12. s37 Security Findings KPI: "32 Critical · Jul 8" → "32 Critical · Jul 9"
"""

import re

HTML = '/home/dashboard/public_html/presentation/index.html'

with open(HTML, 'r', encoding='utf-8') as f:
    c = f.read()

original_len = len(c)
fixes = []

def fix(old, new, label):
    global c
    n = c.count(old)
    if n == 0:
        print(f'  ⚠ NOT FOUND: {label}')
        return False
    c = c.replace(old, new, 1)
    fixes.append(label)
    print(f'  ✓ {label} (replaced 1 of {n} occurrences)')
    return True

def fix_all(old, new, label):
    global c
    n = c.count(old)
    if n == 0:
        print(f'  ⚠ NOT FOUND: {label}')
        return False
    c = c.replace(old, new)
    fixes.append(label)
    print(f'  ✓ {label} (replaced all {n} occurrences)')
    return True

print('=== Session 20 Fixes ===\n')

# ── 1. s8 slide subtitle date ──
fix(
    'Source: uname -r, lscpu, free, df — verified Jul 8, 2026',
    'Source: uname -r, lscpu, free, df — verified Jul 9, 2026',
    's8 subtitle: Jul 8 → Jul 9'
)

# ── 2. s8 resource panel header ──
fix(
    'Resource Utilization — Current (Jul 8)',
    'Resource Utilization — Current (Jul 9)',
    's8 resource panel: Jul 8 → Jul 9'
)

# ── 3. s37 subtitle date ──
fix(
    'Apache · PHP-FPM · MariaDB · Redis · Varnish · Cloudflare — Real-time monitoring · Jul 8, 2026',
    'Apache · PHP-FPM · MariaDB · Redis · Varnish · Cloudflare — Real-time monitoring · Jul 9, 2026',
    's37 subtitle: Jul 8 → Jul 9'
)

# ── 4. s37 Ecomscan KPI sub ──
fix(
    'Jul 8 · 0 Malware · 12 modules',
    'Jul 9 · 0 Malware · 10+ modules',
    's37 Ecomscan KPI: Jul 8 → Jul 9, 12→10+ modules'
)

# ── 5. s37 Security Findings KPI sub ──
fix(
    '32 Critical · Jul 8',
    '32 Critical · Jul 9',
    's37 Security KPI sub: Jul 8 → Jul 9'
)

# ── 6. s37 Load Avg KPI sub: wrong peak value ──
fix(
    '↓ from 8.7 (May 5)',
    '↓ from 15.37 (May 5)',
    's37 Load KPI: 8.7 → 15.37 (actual crisis peak)'
)

# ── 7. s37 PHP-FPM max processes: 30 → 66 ──
fix(
    'pm=static max=30, opcache.jit=1255',
    'pm=static max=66, opcache.jit=1255',
    's37 PHP-FPM max_children: 30 → 66 (consistent with s8)'
)

# ── 8. s21 timeline report date ──
fix(
    'Source: This audit report — Jul 8, 2026',
    'Source: This audit report — Jul 9, 2026',
    's21 timeline report date: Jul 8 → Jul 9'
)

# ── 9. s24 chart title ──
fix(
    'Imunify360 Daily Scan Results — Jun 8 to Jul 8',
    'Imunify360 Daily Scan Results — Jun 8 to Jul 9',
    's24 chart title: Jul 8 → Jul 9'
)

# ── 10. NOTES s1 cover date ──
fix(
    "s1:  'Cover slide. Report date: Jul 8, 2026.",
    "s1:  'Cover slide. Report date: Jul 9, 2026.",
    'NOTES s1 cover date: Jul 8 → Jul 9'
)

# ── 11. s24 false positive section date ──
fix(
    'Jun 29→Jul 8: 18,141 files, 0 malicious (rescans)',
    'Jun 29→Jul 9: 18,141 files, 0 malicious (rescans)',
    's24 FP evidence: Jul 8 → Jul 9'
)

# ── 12. s34 Amasty module count: "12 modules" → "10+ modules" ──
fix(
    '10+ modules with mass-disclosure CVE · composer update amasty/*',
    '10+ modules with mass-disclosure CVE · <code>composer update amasty/*</code>',
    's34 Amasty composer: add code tag for clarity'
)

# ── 13. JS chart animation optimization ──
# Add animation: { duration: 300 } to all chart options blocks
# Pattern: options: { responsive: true, maintainAspectRatio: false,
# Insert animation property into options
old_anim_pattern = 'options: { responsive: true, maintainAspectRatio: false,'
new_anim_pattern = 'options: { animation: { duration: 300 }, responsive: true, maintainAspectRatio: false,'

n_charts = c.count(old_anim_pattern)
if n_charts > 0:
    c = c.replace(old_anim_pattern, new_anim_pattern)
    fixes.append(f'JS chart animation: duration=300 added to {n_charts} chart options')
    print(f'  ✓ JS chart animation: duration=300 added to all {n_charts} chart options blocks')
else:
    print(f'  ⚠ NOT FOUND: chart animation pattern')

# ── 14. s20 Imunify scan KPI date ──
# "Jun 8 – Jul 8 total" → "Jun 8 – Jul 9 total"
fix(
    'Jun 8 – Jul 8 total',
    'Jun 8 – Jul 9 total',
    's20 Imunify360 scan period: Jul 8 → Jul 9'
)

# ── 15. s20 Ecomscan KPI: "Jul 8 · peak" ──
fix(
    'Jul 8 · peak 119 (Jul 4–6)',
    'Jul 9 · peak 119 (Jul 4–6)',
    's20 Ecomscan KPI date: Jul 8 → Jul 9'
)

# ── 16. s21 timeline Dashboard v5 entry date stays Jul 8 (historical event) ──
# SKIP - that's a past event entry, not a "verified on" date

# ── 17. s21 timeline Jul 8 "Current Status" entry - update to Jul 9 ──
# These are two timeline entries with class tl-time "Jul 8"
# The "Dashboard v5" launch (event) stays Jul 8, but "Current Status" should be Jul 9
fix(
    '<div class="tl-time">Jul 8</div><div class="tl-dot green"></div><div class="tl-content"><div class="tl-title">✅ Current S',
    '<div class="tl-time">Jul 9</div><div class="tl-dot green"></div><div class="tl-content"><div class="tl-title">✅ Current S',
    's21 timeline current status entry: Jul 8 → Jul 9'
)

# ── 18. s24 ecomscan data: current Jul 8 scan date ──
# "Report Date: Jul 8" in timeline  
fix(
    '<div class="tl-title">Report Date</div>',
    '<div class="tl-title">Report Date — Jul 9, 2026</div>',
    's21 timeline report title: add date'
)

# ── 19. s37 security HIGH findings label ──
fix(
    'HIGH</span> 32 CRITICAL findings in Jul 8 scan',
    'HIGH</span> 32 CRITICAL findings in Jul 9 scan',
    's37 security note: Jul 8 → Jul 9'
)

print(f'\n=== Summary ===')
print(f'Fixes applied: {len(fixes)}')
print(f'Size: {len(c):,} chars (was {original_len:,}, delta={len(c)-original_len:+d})')

# Validation
divs_o = c.count('<div')
divs_c = c.count('</div')
slides = len(re.findall(r'class="slide[" ]', c))
print(f'Divs: {divs_o}/{divs_c} diff={divs_o-divs_c}')
print(f'Slides: {slides}')

if divs_o != divs_c:
    print('⚠ DIV MISMATCH — aborting write!')
else:
    with open(HTML, 'w', encoding='utf-8') as f:
        f.write(c)
    print('✅ Saved!')
