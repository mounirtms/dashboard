#!/usr/bin/env python3
"""
update_presentation.py — Apply real Magento data to all presentation files
==========================================================================
Reads magento_data.json and applies updates to:
  - presentation/index.php
  - presentation/index.html
  - presentation/test_view.html

Changes applied:
  1. Cancel rate: 35.2% → 36.6% (correct formula: cancelled/(cmd_done+cancelled))
  2. S17b table: replace estimated values with real API data
  3. S17b chart: fix revenue line data (real values from API)
  4. S17b kpi cards: fix YoY % labels
  5. All-time totals: 7,788 → 7,117 total orders (real count)
  6. Narrator text: s11, s13, s17b updated with correct figures
  7. YoY H1 table: fix AOV reference (H1 2025: 6,199 not 6,200 est.)
  8. 2026 total orders: include in subtitle

Run: python3 webapp/update_presentation.py
"""
import json, re, shutil, os
from datetime import datetime

# ── Load real data ──────────────────────────────────────────────────────────
BASE = '/home/dashboard/public_html'
data_file = f'{BASE}/webapp/magento_data.json'
with open(data_file) as f:
    data = json.load(f)

oy   = data['orders']['yearly']
h1   = data['orders']['h1_by_year']
cust = data['customers']['yearly']
prod = data['products']
kpis = data['audit']['kpis']
audit_issues = data['audit']['issues']

# ── Key values ─────────────────────────────────────────────────────────────
total_orders    = sum(v['total'] for v in oy.values())            # 7,117
cmd_done_all    = kpis['total_cmd_done_alltime']                   # 4,484
alltime_rev     = sum(v['revenue_dzd'] for v in oy.values())
alltime_rev_m   = round(alltime_rev / 1_000_000, 1)               # 28.6

# H1 2026
h1_2026         = h1['2026']
cancel_rate     = h1_2026['cancel_rate_pct']                       # 36.6
cancelled_h1    = h1_2026['cancelled']                             # 288
cmd_done_h1     = h1_2026['cmd_done']                             # 498
meaningful_h1   = cmd_done_h1 + cancelled_h1                       # 786

# Yearly CMD_Done (real)
cmd_2022 = oy['2022']['by_status']['CMD_Done']  # 311
cmd_2023 = oy['2023']['by_status']['CMD_Done']  # 1359
cmd_2024 = oy['2024']['by_status']['CMD_Done']  # 1163
cmd_2025 = oy['2025']['by_status']['CMD_Done']  # 1133
cmd_2026_h1 = oy['2026']['by_status']['CMD_Done']  # 518 full-year partial, use h1=498
# H1 2026 cmd_done = 498 per H1 data (more accurate for H1 chart)

# Revenue by year (real, in M DZD)
rev_2022 = round(oy['2022']['revenue_dzd'] / 1_000_000, 2)  # 2.30
rev_2023 = round(oy['2023']['revenue_dzd'] / 1_000_000, 2)  # 7.76
rev_2024 = round(oy['2024']['revenue_dzd'] / 1_000_000, 2)  # 8.25
rev_2025 = round(oy['2025']['revenue_dzd'] / 1_000_000, 2)  # 7.43
rev_2026_h1 = round(h1_2026['revenue_dzd'] / 1_000_000, 2) # 2.78

# AOV by year (real)
aov_2022 = oy['2022']['aov_dzd']  # 7406
aov_2023 = oy['2023']['aov_dzd']  # 5707
aov_2024 = oy['2024']['aov_dzd']  # 7098
aov_2025 = oy['2025']['aov_dzd']  # 6560
aov_2026_h1 = h1_2026['aov_dzd'] # 5591

# Customers by year (real)
c_2022 = cust.get('2022', 0)  # 1077
c_2023 = cust.get('2023', 0)  # 1204
c_2024 = cust.get('2024', 0)  # 838
c_2025 = cust.get('2025', 0)  # 577

# YoY CMD_Done changes
yoy_2023 = round((cmd_2023 - cmd_2022) / cmd_2022 * 100, 0)  # +337%
yoy_2024 = round((cmd_2024 - cmd_2023) / cmd_2023 * 100, 1)  # -14.4%
yoy_2025 = round((cmd_2025 - cmd_2024) / cmd_2024 * 100, 1)  # -2.6%

# Cancellation breakdown
ann_conf = kpis['h1_2026']['ann_confirmation']   # 164
ann_prep = kpis['h1_2026']['ann_preparation']    # 80
ann_liv  = kpis['h1_2026']['ann_livraison']      # 44
ann_other = cancelled_h1 - ann_conf - ann_prep - ann_liv  # 0 or remainder

# Cancel rate breakdown %
ann_conf_pct = round(ann_conf / cancelled_h1 * 100, 1)  # 56.9%

print("=" * 60)
print("REAL DATA SUMMARY")
print("=" * 60)
print(f"Total orders:      {total_orders:,}")
print(f"All CMD_Done:      {cmd_done_all:,}")
print(f"All-time revenue:  {alltime_rev_m}M DZD")
print(f"Cancel rate H1 26: {cancel_rate}%")
print(f"CMD_Done by year: 2022={cmd_2022} 2023={cmd_2023} 2024={cmd_2024} 2025={cmd_2025} 2026H1={cmd_done_h1}")
print(f"Revenue by year:  2022={rev_2022}M 2023={rev_2023}M 2024={rev_2024}M 2025={rev_2025}M 2026H1={rev_2026_h1}M")
print(f"AOV by year:      2022={aov_2022} 2023={aov_2023} 2024={aov_2024} 2025={aov_2025} 2026H1={aov_2026_h1}")
print(f"Customers:        2022={c_2022} 2023={c_2023} 2024={c_2024} 2025={c_2025}")
print(f"Annulation breakdown: conf={ann_conf} prep={ann_prep} liv={ann_liv} other={ann_other}")
print()

# ── Apply substitutions ──────────────────────────────────────────────────────
PRES_FILES = [
    f'{BASE}/presentation/index.php',
    f'{BASE}/presentation/index.html',
    f'{BASE}/presentation/test_view.html',
]

def apply_updates(content):
    """Apply all real-data replacements to presentation content."""
    c = content

    # ── 1. Cancel rate 35.2% → 36.6% (all occurrences) ──────────────────
    # HTML visible: 35.2% → 36.6%
    c = c.replace('35.2%', '36.6%')
    c = c.replace('35.2 %', '36.6%')

    # Cancel rate denominator explanation: 288 cancelled / 819 total → / 786 meaningful
    c = c.replace(
        '288 cancelled / 819 total',
        f'{cancelled_h1} cancelled / {meaningful_h1} orders actifs'
    )
    # 819 total orders in subtitle
    c = c.replace('819 total orders', f'{h1_2026["cmd_done"] + h1_2026["cancelled"]} ordres actifs H1 2026')
    c = c.replace('819 orders H1 2026', f'{h1_2026["cmd_done"] + h1_2026["cancelled"]} ordres actifs H1 2026')
    c = c.replace('819 total created', f'786 ordres actifs (498 CMD_Done + 288 annulés)')

    # ── 2. Total orders: 7,788 → 7,117 ──────────────────────────────────
    c = c.replace('7,788 total orders', f'{total_orders:,} total orders')
    c = c.replace('7,788 total orders', f'{total_orders:,} total orders')
    c = c.replace('7,788 total', f'{total_orders:,} total')
    c = c.replace('7788', f'{total_orders}')

    # All-time: 2,365 cancelled → correct number
    total_cancelled_all = sum(
        v['by_status'].get('Annulee_a_la_confirmation', 0) +
        v['by_status'].get('Annulee_a_la_preparation', 0) +
        v['by_status'].get('Annulee_a_la_livraison', 0) +
        v['by_status'].get('canceled', 0)
        for v in oy.values()
    )
    if total_cancelled_all > 0:
        c = c.replace(
            f'2,365 cancelled ({cancel_rate}%',
            f'{total_cancelled_all:,} cancelled ({cancel_rate}%'
        )
        c = c.replace('2,365 cancelled (35.2%', f'{total_cancelled_all:,} cancelled ({cancel_rate}%')
        c = c.replace('2,365 cancelled (36.6%', f'{total_cancelled_all:,} cancelled ({cancel_rate}%')

    # ── 3. S17b kpi-card revenue labels (estimated → real) ──────────────
    c = c.replace('Rev: 1.71M DZD', f'Rev: {rev_2022}M DZD · AOV {aov_2022:,}')
    c = c.replace('▲ +94% · 13.35M DZD', f'▲ +{int(yoy_2023)}% · {rev_2023}M DZD')
    c = c.replace('▲ +41% · 37.52M DZD 🏆', f'▲ -{abs(yoy_2024)}% · {rev_2024}M DZD 🏆')
    c = c.replace('▼ −20% · 18.13M DZD', f'▼ {yoy_2025}% · {rev_2025}M DZD')
    c = c.replace('CMD_Done H1 · 2.78M DZD', f'H1 2026 · {rev_2026_h1}M DZD')

    # ── 4. S17b table rows (replace estimated with real) ────────────────
    # 2022 row
    c = c.replace(
        '<tr><td>2022</td><td class="num">311</td><td class="num">1.71</td><td class="num">5,500</td><td class="num">est.</td></tr>',
        f'<tr><td>2022</td><td class="num">311</td><td class="num">{rev_2022}</td><td class="num">{aov_2022:,}</td><td class="num">{c_2022:,}</td></tr>'
    )
    # 2023 row
    c = c.replace(
        '<tr><td>2023</td><td class="num">1,359</td><td class="num">8.43</td><td class="num">6,200</td><td class="num">est.</td></tr>',
        f'<tr><td>2023</td><td class="num">1,359</td><td class="num">{rev_2023}</td><td class="num">{aov_2023:,}</td><td class="num">{c_2023:,}</td></tr>'
    )
    # 2024 row
    c = c.replace(
        '<tr><td style="color:#f59e0b">2024</td><td class="num" style="color:#f87171">1,163</td><td class="num" style="color:#f87171">6.75</td><td class="num">5,804</td><td class="num">est.</td></tr>',
        f'<tr><td style="color:#f59e0b">2024</td><td class="num" style="color:#f87171">1,163</td><td class="num" style="color:#f87171">{rev_2024}</td><td class="num">{aov_2024:,}</td><td class="num">{c_2024:,}</td></tr>'
    )
    # 2025 row
    c = c.replace(
        '<tr><td style="color:#22c55e">2025 full</td><td class="num" style="color:#22c55e">1,133</td><td class="num" style="color:#94a3b8">6.68</td><td class="num" style="color:#94a3b8">5,902</td><td class="num" style="color:#64748b">111</td></tr>',
        f'<tr><td style="color:#22c55e">2025 full</td><td class="num" style="color:#22c55e">1,133</td><td class="num" style="color:#94a3b8">{rev_2025}</td><td class="num" style="color:#94a3b8">{aov_2025:,}</td><td class="num" style="color:#64748b">{c_2025:,}</td></tr>'
    )

    # ── 5. Chart revenue data line ────────────────────────────────────────
    c = c.replace(
        'data: [1.71, 8.43, 6.75, 6.68, 2.90],',
        f'data: [{rev_2022}, {rev_2023}, {rev_2024}, {rev_2025}, {rev_2026_h1}],'
    )

    # ── 6. H1 YoY comparison: AOV reference fix (6,200 est → 6,199 real) ─
    c = c.replace(
        '<td class="num">6,200 DZD</td><td class="num">5,591 DZD</td>',
        f'<td class="num">{aov_2025:,} DZD</td><td class="num">{aov_2026_h1:,} DZD</td>'
    )
    # Also fix the delta
    aov_delta = round((aov_2026_h1 - aov_2025) / aov_2025 * 100, 1)
    c = c.replace(
        '&#x25BC; -9.8%</span></td>',
        f'&#x25BC; {aov_delta}%</span></td>'
    )

    # ── 7. Narrator / voiceover scripts ──────────────────────────────────
    # s2 cancel rate
    c = c.replace(
        'Cancel rate 35.2% — NORMAL for Algerian COD (industry 30-50%).',
        f'Cancel rate {cancel_rate}% — NORMAL for Algerian COD (industry 30-50%).'
    )
    # s11 total orders + cancelled count
    c = c.replace(
        f'MariaDB prod: 7,788 total orders. 4,484 CMD_Done (valid).',
        f'MariaDB prod: {total_orders:,} total orders. {cmd_done_all:,} CMD_Done (valid).'
    )
    # s12 cancel rate
    c = c.replace(
        f'288 cancelled (35.2%). Jun peak in cancellations (43.9%).',
        f'288 cancelled ({cancel_rate}%). Jun peak in cancellations (43.9%).'
    )
    c = c.replace(
        '288 cancelled (36.6%). Jun peak',
        '288 cancelled (36.6%). Jun peak'
    )
    # s13 cancel breakdown
    c = c.replace(
        f'Annulee_a_la_confirmation=163(55.6%)',
        f'Annulee_a_la_confirmation={ann_conf}({ann_conf_pct}%)'
    )
    # s17 AOV H1 2025 reference
    c = c.replace(
        'AOV H1 2025=6,200 vs H1 2026=5,591 (-9.8%).',
        f'AOV H1 2025={aov_2025:,} vs H1 2026={aov_2026_h1:,} ({aov_delta}%).'
    )
    # s17b revenue data
    c = c.replace(
        "Revenue: 2022=2.3M, 2023=7.8M, 2024=8.3M, 2025=7.4M.",
        f"Revenue: 2022={rev_2022}M, 2023={rev_2023}M, 2024={rev_2024}M, 2025={rev_2025}M."
    )
    # Main div subtitle (7,788 → 7,117)
    c = c.replace(
        f'7,788 total orders · 4,484 CMD_Done · 9,275 customers · 2022–Jul 2026 · MariaDB prod · Cancel rate 35.2% (Algerian COD)',
        f'{total_orders:,} total orders · {cmd_done_all:,} CMD_Done · 9,275 customers · 2022–Jul 2026 · MariaDB prod · Cancel rate {cancel_rate}% (Algerian COD)'
    )
    c = c.replace(
        f'7,117 total orders · 4,484 CMD_Done · 9,275 customers · 2022–Jul 2026 · MariaDB prod · Cancel rate 36.6% (Algerian COD)',
        f'{total_orders:,} total orders · {cmd_done_all:,} CMD_Done · 9,275 customers · 2022–Jul 2026 · MariaDB prod · Cancel rate {cancel_rate}% (Algerian COD)'
    )

    # ── 8. S13 cancel breakdown section: 293 → real number ───────────────
    c = c.replace(
        '293 annulations / 819 orders.',
        f'{cancelled_h1} annulations / {meaningful_h1} orders actifs.'
    )

    # ── 9. S13 kpi-val for cancel rate ───────────────────────────────────
    # Already handled by global 35.2% → 36.6% replacement above

    # ── 10. 28.4% comparison column (S17 H1 comparison table) ──────────
    # "28.4%" appears as H1 2025 cancel rate — that's also stale
    # Real: H1 2025 cancel rate from data
    cancel_h1_2025 = h1['2025']['cancel_rate_pct']  # 13.1%
    # Actually the 28.4% was "H1 2025 cancel rate" vs "35.2% H1 2026"
    # With real data: H1 2025=13.1% and H1 2026=36.6%
    c = c.replace(
        '<td class="num">28.4%</td><td class="num">36.6%</td>',
        f'<td class="num">{cancel_h1_2025}%</td><td class="num">{cancel_rate}%</td>'
    )
    c = c.replace(
        '<td class="num">28.4%</td><td class="num">35.2%</td>',
        f'<td class="num">{cancel_h1_2025}%</td><td class="num">{cancel_rate}%</td>'
    )
    # Delta label: COD normal
    c = c.replace(
        '&#x25B2; +8.2pp</span>',
        f'&#x25B2; +{round(cancel_rate - cancel_h1_2025, 1)}pp</span>'
    )

    # ── 11. 2022 revenue: 1.71M vs real ──────────────────────────────────
    # Global 1.71M → real value for non-S17b uses
    c = c.replace('1.71M DZD', f'{rev_2022}M DZD')

    return c


# ── Process files ─────────────────────────────────────────────────────────────
changed_total = 0
for filepath in PRES_FILES:
    if not os.path.exists(filepath):
        print(f"SKIP (not found): {filepath}")
        continue

    with open(filepath, 'r', encoding='utf-8') as f:
        original = f.read()

    updated = apply_updates(original)
    changes = sum(1 for a, b in zip(original.splitlines(), updated.splitlines()) if a != b)
    # Count insertions/deletions properly
    changes = len([l for l in original.split('\n') if '35.2%' in l or '7,788' in l or '1.71' in l
                    or '8.43' in l or '6.75' in l or '6.68' in l or '5,500' in l or '6,200' in l
                    or '5,804' in l or '5,902' in l or '819 total' in l or '293 ann' in l
                    or '28.4%' in l or '2,365' in l])

    if original == updated:
        print(f"NO CHANGE: {os.path.basename(filepath)}")
        continue

    # Backup
    backup = filepath + f'.bak.{datetime.now().strftime("%Y%m%d%H%M%S")}'
    shutil.copy2(filepath, backup)

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(updated)

    # Count actual lines changed
    orig_lines = original.splitlines()
    upd_lines  = updated.splitlines()
    diff_count = sum(1 for a, b in zip(orig_lines, upd_lines) if a != b)
    diff_count += abs(len(orig_lines) - len(upd_lines))

    print(f"✓ UPDATED: {os.path.basename(filepath)} — {diff_count} lines changed (backup: {os.path.basename(backup)})")
    changed_total += diff_count

print()
print("=" * 60)
print(f"Total lines changed across all files: {changed_total}")
print()
print("KEY CHANGES APPLIED:")
print(f"  Cancel rate:    35.2% → 36.6%  (formula: {cancelled_h1}/{meaningful_h1})")
print(f"  Total orders:   7,788 → {total_orders:,}")
print(f"  2022 revenue:   1.71M → {rev_2022}M (real API)")
print(f"  2023 revenue:   8.43M → {rev_2023}M (real API)")
print(f"  2024 revenue:   6.75M → {rev_2024}M (real API)")
print(f"  2025 revenue:   6.68M → {rev_2025}M (real API)")
print(f"  2022 AOV:       5,500 est → {aov_2022:,} (real)")
print(f"  2023 AOV:       6,200 est → {aov_2023:,} (real)")
print(f"  2024 AOV:       5,804 est → {aov_2024:,} (real)")
print(f"  2025 AOV:       5,902 est → {aov_2025:,} (real)")
print(f"  2022 customers: est → {c_2022:,} (real)")
print(f"  2023 customers: est → {c_2023:,} (real)")
print(f"  H1 2025 cancel: 28.4% est → {h1['2025']['cancel_rate_pct']}% (real)")
print(f"  Ann confirmation: 163 → {ann_conf} ({ann_conf_pct}%)")
print()
