#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Comprehensive production data fetch via Magento REST API
"""
import json, subprocess

CREDS = json.load(open('/home/dashboard/public_html/config/magento_credentials.json'))
TOKEN = CREDS['prod']['token']
BASE = "https://technostationery.com"

def api(url):
    cmd = ['curl', '-s', '-g', '-m', '30', '-H', f'Authorization: Bearer {TOKEN}', url]
    p = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    out, _ = p.communicate()
    raw = out.decode('utf-8', errors='replace')
    try:
        return json.loads(raw)
    except:
        return {'_raw': raw[:200]}

def count_orders(year_from, month_from, year_to, month_to, status=None):
    """Count orders in date range with optional status filter"""
    from_val = f'{year_from}-{month_from:02d}-01'
    import calendar
    last_day = calendar.monthrange(year_to, month_to)[1]
    to_val = f'{year_to}-{month_to:02d}-{last_day:02d} 23:59:59'
    
    url = (f'{BASE}/rest/V1/orders'
           f'?searchCriteria[filter_groups][0][filters][0][field]=created_at'
           f'&searchCriteria[filter_groups][0][filters][0][value]={from_val}'
           f'&searchCriteria[filter_groups][0][filters][0][condition_type]=gteq'
           f'&searchCriteria[filter_groups][1][filters][0][field]=created_at'
           f'&searchCriteria[filter_groups][1][filters][0][value]={to_val}'
           f'&searchCriteria[filter_groups][1][filters][0][condition_type]=lteq'
           f'&searchCriteria[pageSize]=1&fields=total_count')
    if status:
        url += (f'&searchCriteria[filter_groups][2][filters][0][field]=status'
                f'&searchCriteria[filter_groups][2][filters][0][value]={status}')
    r = api(url)
    return r.get('total_count', 0)

def count_status(status):
    url = (f'{BASE}/rest/V1/orders'
           f'?searchCriteria[filter_groups][0][filters][0][field]=status'
           f'&searchCriteria[filter_groups][0][filters][0][value]={status}'
           f'&searchCriteria[pageSize]=1&fields=total_count')
    r = api(url)
    return r.get('total_count', 0)

print("="*65)
print("REAL PRODUCTION DATA — technostationery.com")
print(f"API Token: ...{TOKEN[-20:]}")
print("="*65)

# ── All-time totals
all_orders = api(f'{BASE}/rest/V1/orders?searchCriteria[pageSize]=1&fields=total_count').get('total_count', 0)
all_customers = api(f'{BASE}/rest/V1/customers/search?searchCriteria[pageSize]=1&fields=total_count').get('total_count', 0)
all_products = api(f'{BASE}/rest/V1/products?searchCriteria[pageSize]=1&fields=total_count').get('total_count', 0)
print(f"\nAll-time totals:")
print(f"  Orders total: {all_orders}")
print(f"  Customers:    {all_customers}")
print(f"  Products:     {all_products}")

# ── All-time by status
print(f"\nAll-time by status:")
statuses = ['CMD_Done', 'Annulee_a_la_confirmation', 'Annulee_a_la_preparation',
            'Annulee_a_la_livraison', 'pending', 'processing', 'complete', 'holded', 'canceled']
status_counts = {}
for s in statuses:
    c = count_status(s)
    status_counts[s] = c
    print(f"  {s}: {c}")

# ── H1 2026
print(f"\nH1 2026 (Jan-Jun 2026):")
h1_all = count_orders(2026, 1, 2026, 6)
h1_done = count_orders(2026, 1, 2026, 6, 'CMD_Done')
h1_ann_c = count_orders(2026, 1, 2026, 6, 'Annulee_a_la_confirmation')
h1_ann_p = count_orders(2026, 1, 2026, 6, 'Annulee_a_la_preparation')
h1_ann_l = count_orders(2026, 1, 2026, 6, 'Annulee_a_la_livraison')
h1_pend = count_orders(2026, 1, 2026, 6, 'pending')
h1_proc = count_orders(2026, 1, 2026, 6, 'processing')
h1_cancel = h1_ann_c + h1_ann_p + h1_ann_l
print(f"  Total:                  {h1_all}")
print(f"  CMD_Done:               {h1_done}")
print(f"  Annulee_a_la_conf:      {h1_ann_c}")
print(f"  Annulee_a_la_prep:      {h1_ann_p}")
print(f"  Annulee_a_la_livr:      {h1_ann_l}")
print(f"  Pending:                {h1_pend}")
print(f"  Processing:             {h1_proc}")
print(f"  Total cancelled:        {h1_cancel}")
cancel_rate = round(h1_cancel/h1_all*100, 1) if h1_all else 0
done_rate = round(h1_done/h1_all*100, 1) if h1_all else 0
print(f"  Cancel rate:            {cancel_rate}%")
print(f"  CMD_Done rate:          {done_rate}%")

# ── H1 2025
print(f"\nH1 2025 (Jan-Jun 2025):")
h1_25_all = count_orders(2025, 1, 2025, 6)
h1_25_done = count_orders(2025, 1, 2025, 6, 'CMD_Done')
h1_25_ann_c = count_orders(2025, 1, 2025, 6, 'Annulee_a_la_confirmation')
h1_25_ann_p = count_orders(2025, 1, 2025, 6, 'Annulee_a_la_preparation')
h1_25_ann_l = count_orders(2025, 1, 2025, 6, 'Annulee_a_la_livraison')
h1_25_cancel = h1_25_ann_c + h1_25_ann_p + h1_25_ann_l
print(f"  Total:                  {h1_25_all}")
print(f"  CMD_Done:               {h1_25_done}")
print(f"  Cancelled:              {h1_25_cancel}")
print(f"  Cancel rate:            {round(h1_25_cancel/h1_25_all*100,1) if h1_25_all else 0}%")

# ── Monthly CMD_Done H1 2026
print(f"\nMonthly CMD_Done H1 2026:")
months_26 = []
month_names = ['Jan','Feb','Mar','Apr','May','Jun']
for m in range(1, 7):
    c = count_orders(2026, m, 2026, m, 'CMD_Done')
    months_26.append(c)
    print(f"  {month_names[m-1]}: {c}")
print(f"  Total: {sum(months_26)}")

# ── Monthly CMD_Done H1 2025
print(f"\nMonthly CMD_Done H1 2025:")
months_25 = []
for m in range(1, 7):
    c = count_orders(2025, m, 2025, m, 'CMD_Done')
    months_25.append(c)
    print(f"  {month_names[m-1]}: {c}")
print(f"  Total: {sum(months_25)}")

# ── Annual CMD_Done
print(f"\nAnnual CMD_Done (real data):")
annual_done = {}
annual_all = {}
for yr in [2022, 2023, 2024, 2025]:
    done = count_orders(yr, 1, yr, 12, 'CMD_Done')
    total = count_orders(yr, 1, yr, 12)
    annual_done[yr] = done
    annual_all[yr] = total
    print(f"  {yr}: {done} CMD_Done / {total} total")
annual_done[2026] = h1_done
annual_all[2026] = h1_all
print(f"  2026 H1: {h1_done} CMD_Done / {h1_all} total")

# ── Monthly all orders H1 2026 (for charts)
print(f"\nMonthly ALL orders H1 2026:")
months_all_26 = []
for m in range(1, 7):
    c = count_orders(2026, m, 2026, m)
    months_all_26.append(c)
    print(f"  {month_names[m-1]}: {c}")

# ── Get sample orders to compute AOV
print(f"\nSample of recent orders for AOV calculation:")
sample_url = (f'{BASE}/rest/V1/orders'
              f'?searchCriteria[filter_groups][0][filters][0][field]=status'
              f'&searchCriteria[filter_groups][0][filters][0][value]=CMD_Done'
              f'&searchCriteria[filter_groups][1][filters][0][field]=created_at'
              f'&searchCriteria[filter_groups][1][filters][0][value]=2026-01-01'
              f'&searchCriteria[filter_groups][1][filters][0][condition_type]=gteq'
              f'&searchCriteria[pageSize]=100'
              f'&fields=items[entity_id,grand_total,created_at,status]'
              f'&searchCriteria[sortOrders][0][field]=created_at'
              f'&searchCriteria[sortOrders][0][direction]=DESC')
sample = api(sample_url)
if 'items' in sample:
    totals = [float(o.get('grand_total', 0)) for o in sample['items'] if float(o.get('grand_total',0)) > 0]
    if totals:
        avg = sum(totals)/len(totals)
        print(f"  Sample size: {len(totals)} orders")
        print(f"  AOV H1 2026: {avg:,.0f} DZD")
        print(f"  Min: {min(totals):,.0f} DZD  Max: {max(totals):,.0f} DZD")
else:
    print(f"  Sample error: {str(sample)[:100]}")

# ── Save all data
results = {
    'all_time': {
        'total_orders': all_orders,
        'total_customers': all_customers,
        'total_products': all_products,
    },
    'status_counts_all_time': status_counts,
    'h1_2026': {
        'total': h1_all,
        'CMD_Done': h1_done,
        'Annulee_conf': h1_ann_c,
        'Annulee_prep': h1_ann_p,
        'Annulee_livr': h1_ann_l,
        'pending': h1_pend,
        'processing': h1_proc,
        'cancel_total': h1_cancel,
        'cancel_rate_pct': cancel_rate,
        'done_rate_pct': done_rate,
    },
    'h1_2025': {
        'total': h1_25_all,
        'CMD_Done': h1_25_done,
        'cancel_total': h1_25_cancel,
        'cancel_rate_pct': round(h1_25_cancel/h1_25_all*100, 1) if h1_25_all else 0,
    },
    'monthly_cmd_done_2026': months_26,
    'monthly_cmd_done_2025': months_25,
    'monthly_all_orders_2026': months_all_26,
    'annual_done': {str(k): v for k,v in annual_done.items()},
    'annual_all': {str(k): v for k,v in annual_all.items()},
}
json.dump(results, open('/home/dashboard/public_html/webapp/real_data.json', 'w'), indent=2)
print(f"\n{'='*65}")
print("Saved to real_data.json")
print("="*65)
