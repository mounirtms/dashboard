#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Query real production Magento API for accurate stats
Uses the production API token from magento_credentials.json
"""
import json, subprocess, sys

CREDS = json.load(open('/home/dashboard/public_html/config/magento_credentials.json'))
TOKEN = CREDS['prod']['token']
BASE = "https://technostationery.com"

def api_get(path):
    cmd = ['curl', '-s', '-m', '30', '-H', f'Authorization: Bearer {TOKEN}', f'{BASE}/rest/V1/{path}']
    p = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    out, err = p.communicate()
    try:
        return json.loads(out.decode('utf-8'))
    except Exception as e:
        return {'_error': out.decode('utf-8', errors='replace')[:300], '_ex': str(e)}

def build_date_range_query(year_from, month_from, year_to, month_to, status=None, extra=''):
    """Build API query for date range with optional status filter"""
    from_val = f'{year_from}-{month_from:02d}-01 00:00:00'
    # Calculate end date
    import calendar
    last_day = calendar.monthrange(year_to, month_to)[1]
    to_val = f'{year_to}-{month_to:02d}-{last_day:02d} 23:59:59'
    
    q = f'orders?searchCriteria[pageSize]=1'
    q += f'&searchCriteria[filter_groups][0][filters][0][field]=created_at'
    q += f'&searchCriteria[filter_groups][0][filters][0][value]={from_val.replace(" ", "+")}'
    q += f'&searchCriteria[filter_groups][0][filters][0][condition_type]=gteq'
    q += f'&searchCriteria[filter_groups][1][filters][0][field]=created_at'
    q += f'&searchCriteria[filter_groups][1][filters][0][value]={to_val.replace(" ", "+")}'
    q += f'&searchCriteria[filter_groups][1][filters][0][condition_type]=lteq'
    if status:
        q += f'&searchCriteria[filter_groups][2][filters][0][field]=status'
        q += f'&searchCriteria[filter_groups][2][filters][0][value]={status}'
    q += '&fields=total_count'
    return q

def count(year_from, month_from, year_to, month_to, status=None):
    q = build_date_range_query(year_from, month_from, year_to, month_to, status)
    r = api_get(q)
    return r.get('total_count', 0)

def count_year(year, status=None):
    return count(year, 1, year, 12, status)

print("="*60)
print("REAL PRODUCTION DATA — TechnoStationery Magento")
print("="*60)

print("\n--- TOTAL ALL TIME ---")
r = api_get('orders?searchCriteria[pageSize]=1&fields=total_count')
total_all = r.get('total_count', 0)
print(f"All orders ever: {total_all}")

print("\n--- H1 2026 by Status (Jan 1 - Jun 30, 2026) ---")
h1_all    = count(2026, 1, 2026, 6)
h1_done   = count(2026, 1, 2026, 6, 'CMD_Done')
h1_ann_c  = count(2026, 1, 2026, 6, 'Annulee_a_la_confirmation')
h1_ann_p  = count(2026, 1, 2026, 6, 'Annulee_a_la_preparation')
h1_ann_l  = count(2026, 1, 2026, 6, 'Annulee_a_la_livraison')
h1_pend   = count(2026, 1, 2026, 6, 'pending')
h1_cancel = h1_ann_c + h1_ann_p + h1_ann_l
print(f"Total H1 2026:          {h1_all}")
print(f"CMD_Done:               {h1_done}")
print(f"Annulee_a_la_conf:      {h1_ann_c}")
print(f"Annulee_a_la_prep:      {h1_ann_p}")
print(f"Annulee_a_la_livr:      {h1_ann_l}")
print(f"Pending:                {h1_pend}")
print(f"Total cancel:           {h1_cancel}")
print(f"Cancel rate:            {h1_cancel/h1_all*100:.1f}%" if h1_all else "N/A")
print(f"CMD_Done %:             {h1_done/h1_all*100:.1f}%" if h1_all else "N/A")

print("\n--- H1 2026 Monthly CMD_Done ---")
monthly_2026 = []
monthly_labels = ['Jan','Feb','Mar','Apr','May','Jun']
for m in range(1, 7):
    c = count(2026, m, 2026, m, 'CMD_Done')
    monthly_2026.append(c)
    print(f"  {monthly_labels[m-1]} 2026: {c}")
print(f"  Total: {sum(monthly_2026)}")

print("\n--- H1 2025 Monthly CMD_Done (comparison) ---")
monthly_2025 = []
for m in range(1, 7):
    c = count(2025, m, 2025, m, 'CMD_Done')
    monthly_2025.append(c)
    print(f"  {monthly_labels[m-1]} 2025: {c}")
print(f"  Total: {sum(monthly_2025)}")

print("\n--- Annual CMD_Done (full years) ---")
annual_done = {}
for yr in [2022, 2023, 2024, 2025]:
    c = count_year(yr, 'CMD_Done')
    annual_done[yr] = c
    print(f"  {yr}: {c}")
annual_done[2026] = h1_done
print(f"  2026 H1: {h1_done}")

print("\n--- Annual ALL orders (for cancel rate context) ---")
annual_all = {}
for yr in [2022, 2023, 2024, 2025]:
    c = count_year(yr)
    annual_all[yr] = c
    print(f"  {yr} total: {c}")

print("\n--- Customer counts ---")
c_all = api_get('customers/search?searchCriteria[pageSize]=1&fields=total_count')
print(f"Total customers: {c_all.get('total_count', '?')}")

# Customers created in 2025
c_2025q = ('customers/search?searchCriteria[pageSize]=1'
           '&searchCriteria[filter_groups][0][filters][0][field]=created_at'
           '&searchCriteria[filter_groups][0][filters][0][value]=2025-01-01+00:00:00'
           '&searchCriteria[filter_groups][0][filters][0][condition_type]=gteq'
           '&searchCriteria[filter_groups][1][filters][0][field]=created_at'
           '&searchCriteria[filter_groups][1][filters][0][value]=2025-12-31+23:59:59'
           '&searchCriteria[filter_groups][1][filters][0][condition_type]=lteq'
           '&fields=total_count')
c_2025 = api_get(c_2025q)
print(f"Customers created 2025: {c_2025.get('total_count', '?')}")

c_2026q = ('customers/search?searchCriteria[pageSize]=1'
           '&searchCriteria[filter_groups][0][filters][0][field]=created_at'
           '&searchCriteria[filter_groups][0][filters][0][value]=2026-01-01+00:00:00'
           '&searchCriteria[filter_groups][0][filters][0][value]=2026-01-01+00:00:00'
           '&searchCriteria[filter_groups][0][filters][0][condition_type]=gteq'
           '&fields=total_count')
c_2026 = api_get(c_2026q)
print(f"Customers created 2026+: {c_2026.get('total_count', '?')}")

print("\n--- Products catalog ---")
prods = api_get('products?searchCriteria[pageSize]=1&fields=total_count')
print(f"Total products: {prods.get('total_count', '?')}")

prods_enabled = api_get('products?searchCriteria[pageSize]=1'
    '&searchCriteria[filter_groups][0][filters][0][field]=status'
    '&searchCriteria[filter_groups][0][filters][0][value]=1'
    '&fields=total_count')
print(f"Enabled products: {prods_enabled.get('total_count', '?')}")

print("\n" + "="*60)
print("DATA COMPLETE")
print("="*60)

# Save results for use in patch script
results = {
    'total_all_time': total_all,
    'h1_2026': {
        'total': h1_all,
        'CMD_Done': h1_done,
        'Annulee_conf': h1_ann_c,
        'Annulee_prep': h1_ann_p,
        'Annulee_livr': h1_ann_l,
        'pending': h1_pend,
        'cancel_total': h1_cancel,
        'cancel_rate_pct': round(h1_cancel/h1_all*100, 1) if h1_all else 0,
    },
    'monthly_2026': monthly_2026,
    'monthly_2025': monthly_2025,
    'annual_done': annual_done,
    'annual_all': annual_all,
}
json.dump(results, open('/home/dashboard/public_html/webapp/real_data.json', 'w'), indent=2)
print("\nSaved to real_data.json")
