#!/usr/bin/env python3
"""Comprehensive Magento data fetcher — Python 3.6 compatible, curl -g"""
import json, subprocess, time, sys
from datetime import datetime

BASE = "https://technostationery.com/rest/V1"
CREDS = "/home/dashboard/public_html/config/magento_credentials.json"
with open(CREDS) as f:
    TOKEN = json.load(f)["prod"]["token"]

def curl(path_with_params):
    url = "%s/%s" % (BASE, path_with_params)
    r = subprocess.run(
        ["curl", "-sg", "-H", "Authorization: Bearer " + TOKEN,
         "-H", "Content-Type: application/json", url],
        stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=30
    )
    out = r.stdout.decode("utf-8", errors="replace")
    try:
        return json.loads(out)
    except Exception as e:
        sys.stderr.write("  BAD: %s | %s\n" % (url[-60:], out[:80]))
        return None

def count(entity="orders", status=None, from_d=None, to_d=None):
    fi = 0
    p = "searchCriteria[pageSize]=1&searchCriteria[currentPage]=1"
    if status:
        p += "&searchCriteria[filterGroups][%d][filters][0][field]=status" % fi
        p += "&searchCriteria[filterGroups][%d][filters][0][value]=%s" % (fi, status)
        p += "&searchCriteria[filterGroups][%d][filters][0][condition_type]=eq" % fi
        fi += 1
    if from_d:
        p += "&searchCriteria[filterGroups][%d][filters][0][field]=created_at" % fi
        p += "&searchCriteria[filterGroups][%d][filters][0][value]=%sT00:00:00" % (fi, from_d)
        p += "&searchCriteria[filterGroups][%d][filters][0][condition_type]=gteq" % fi
        fi += 1
    if to_d:
        p += "&searchCriteria[filterGroups][%d][filters][0][field]=created_at" % fi
        p += "&searchCriteria[filterGroups][%d][filters][0][value]=%sT00:00:00" % (fi, to_d)
        p += "&searchCriteria[filterGroups][%d][filters][0][condition_type]=lt" % fi
    r = curl("%s?%s" % (entity, p))
    return r.get("total_count", 0) if r else 0

def fetch_page(entity="orders", status=None, from_d=None, to_d=None, page=1, psize=100, fields=None):
    fi = 0
    p = "searchCriteria[pageSize]=%d&searchCriteria[currentPage]=%d" % (psize, page)
    if status:
        p += "&searchCriteria[filterGroups][%d][filters][0][field]=status" % fi
        p += "&searchCriteria[filterGroups][%d][filters][0][value]=%s" % (fi, status)
        p += "&searchCriteria[filterGroups][%d][filters][0][condition_type]=eq" % fi
        fi += 1
    if from_d:
        p += "&searchCriteria[filterGroups][%d][filters][0][field]=created_at" % fi
        p += "&searchCriteria[filterGroups][%d][filters][0][value]=%sT00:00:00" % (fi, from_d)
        p += "&searchCriteria[filterGroups][%d][filters][0][condition_type]=gteq" % fi
        fi += 1
    if to_d:
        p += "&searchCriteria[filterGroups][%d][filters][0][field]=created_at" % fi
        p += "&searchCriteria[filterGroups][%d][filters][0][value]=%sT00:00:00" % (fi, to_d)
        p += "&searchCriteria[filterGroups][%d][filters][0][condition_type]=lt" % fi
        fi += 1
    if fields:
        p += "&fields=%s" % fields
    return curl("%s?%s" % (entity, p))

def customers_count(from_d=None, to_d=None):
    fi = 0
    p = "searchCriteria[pageSize]=1&searchCriteria[currentPage]=1"
    if from_d:
        p += "&searchCriteria[filterGroups][%d][filters][0][field]=created_at" % fi
        p += "&searchCriteria[filterGroups][%d][filters][0][value]=%sT00:00:00" % (fi, from_d)
        p += "&searchCriteria[filterGroups][%d][filters][0][condition_type]=gteq" % fi
        fi += 1
    if to_d:
        p += "&searchCriteria[filterGroups][%d][filters][0][field]=created_at" % fi
        p += "&searchCriteria[filterGroups][%d][filters][0][value]=%sT00:00:00" % (fi, to_d)
        p += "&searchCriteria[filterGroups][%d][filters][0][condition_type]=lt" % fi
    r = curl("customers/search?%s" % p)
    return r.get("total_count", 0) if r else 0

print("=" * 60)
print("MAGENTO COMPREHENSIVE DATA FETCH v3")
print("Date: %s UTC" % datetime.utcnow().strftime("%Y-%m-%d %H:%M"))
print("=" * 60)

data = {
    "meta": {"fetched_at": datetime.utcnow().isoformat()+"Z", "source": "Magento REST API v1"},
    "orders": {"yearly": {}, "h1_by_year": {}, "status_totals": {}},
    "customers": {"yearly": {}, "total": 0, "h1_by_year": {}},
    "products": {"total": 0},
    "audit": {"issues": [], "kpis": {}}
}

STATUSES = ["CMD_Done", "Annulee_a_la_confirmation", "Annulee_a_la_preparation",
            "Annulee_a_la_livraison", "processing", "pending", "complete", "canceled", "holded"]
YEARS = [2022, 2023, 2024, 2025, 2026]
MONTHS = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"]

# ─── 1. Orders yearly ──────────────────────────────────────────────
print("\n[1/4] ORDERS — yearly breakdown")
for year in YEARS:
    print("\n  ── %d ──" % year)
    fy = "%d-01-01" % year
    ty = "%d-01-01" % (year+1)
    total = count("orders", from_d=fy, to_d=ty); time.sleep(0.1)
    print("  Total: %d" % total)

    by_status = {}
    for s in STATUSES:
        c = count("orders", status=s, from_d=fy, to_d=ty); time.sleep(0.1)
        by_status[s] = c
        if c: print("    %s: %d" % (s, c))

    monthly = {}
    for m in range(1,13):
        mf = "%d-%02d-01" % (year, m)
        nm = m+1 if m<12 else 1; ny = year if m<12 else year+1
        mt = "%d-%02d-01" % (ny, nm)
        c = count("orders", status="CMD_Done", from_d=mf, to_d=mt); time.sleep(0.08)
        monthly[m] = c
    print("  CMD_Done monthly: %s" % list(monthly.values()))

    # Revenue + wilaya
    rev = 0.0; page = 1; orders_list = []
    fields = "items[grand_total,billing_address],total_count"
    while True:
        r = fetch_page("orders", status="CMD_Done", from_d=fy, to_d=ty, page=page, psize=100, fields=fields)
        if not r or not r.get("items"): break
        items = r["items"]
        rev += sum(float(o.get("grand_total", 0)) for o in items)
        orders_list.extend(items)
        if len(items) < 100: break
        page += 1; time.sleep(0.15)

    done = by_status.get("CMD_Done", 0)
    aov = round(rev/done) if done else 0

    wilaya_counts = {}
    for o in orders_list:
        addr = o.get("billing_address") or {}
        region = addr.get("region") or addr.get("city") or "Unknown"
        wilaya_counts[region] = wilaya_counts.get(region, 0) + 1
    top_wilaya = sorted(wilaya_counts.items(), key=lambda x: -x[1])[:15]

    data["orders"]["yearly"][str(year)] = {
        "total": total, "by_status": by_status,
        "monthly_cmd_done": monthly,
        "revenue_dzd": round(rev, 2), "aov_dzd": aov,
        "top_wilaya": dict(top_wilaya)
    }
    print("  Revenue: %s DZD | AOV: %s DZD" % ("{:,.0f}".format(rev), "{:,}".format(aov)))
    if top_wilaya: print("  Top 3 wilaya: %s" % top_wilaya[:3])

# ─── 2. H1 YoY ─────────────────────────────────────────────────────
print("\n[2/4] H1 YEAR-OVER-YEAR")
for year in YEARS:
    fy = "%d-01-01" % year; ty = "%d-07-01" % year
    done = count("orders", status="CMD_Done", from_d=fy, to_d=ty); time.sleep(0.1)
    ac   = count("orders", status="Annulee_a_la_confirmation", from_d=fy, to_d=ty); time.sleep(0.1)
    ap   = count("orders", status="Annulee_a_la_preparation", from_d=fy, to_d=ty); time.sleep(0.1)
    al   = count("orders", status="Annulee_a_la_livraison", from_d=fy, to_d=ty); time.sleep(0.1)
    cancelled = ac + ap + al
    meaningful = done + cancelled
    cr = round(cancelled/meaningful*100,1) if meaningful else 0

    rev = 0.0; page = 1
    while True:
        r = fetch_page("orders", status="CMD_Done", from_d=fy, to_d=ty,
                       page=page, psize=100, fields="items[grand_total],total_count")
        if not r or not r.get("items"): break
        rev += sum(float(o.get("grand_total",0)) for o in r["items"])
        if len(r["items"]) < 100: break
        page += 1; time.sleep(0.15)

    aov = round(rev/done) if done else 0
    monthly_h1 = {}
    for m in range(1,7):
        mf = "%d-%02d-01" % (year, m)
        mt = "%d-%02d-01" % (year, m+1) if m<6 else "%d-07-01" % year
        monthly_h1[m] = count("orders", status="CMD_Done", from_d=mf, to_d=mt); time.sleep(0.08)

    data["orders"]["h1_by_year"][str(year)] = {
        "cmd_done": done, "cancelled": cancelled,
        "ann_confirmation": ac, "ann_preparation": ap, "ann_livraison": al,
        "cancel_rate_pct": cr, "revenue_dzd": round(rev, 2), "aov_dzd": aov,
        "monthly": monthly_h1
    }
    print("  H1 %d: %d CMD_Done | %d cancelled (%s%%) | %s DZD | AOV %s" % (
        year, done, cancelled, cr, "{:,.0f}".format(rev), "{:,}".format(aov)))

# ─── 3. Customers ──────────────────────────────────────────────────
print("\n[3/4] CUSTOMERS")
tc = customers_count(); time.sleep(0.1)
data["customers"]["total"] = tc
print("  Total: %d" % tc)
for year in YEARS:
    c = customers_count(from_d="%d-01-01"%year, to_d="%d-01-01"%(year+1)); time.sleep(0.1)
    data["customers"]["yearly"][str(year)] = c
    print("  %d: %d new" % (year, c))
for year in YEARS:
    c = customers_count(from_d="%d-01-01"%year, to_d="%d-07-01"%year); time.sleep(0.1)
    data["customers"]["h1_by_year"][str(year)] = c
    print("  H1 %d: %d" % (year, c))

# ─── 4. Products ───────────────────────────────────────────────────
print("\n[4/4] PRODUCTS")
r = curl("products?searchCriteria[pageSize]=1&searchCriteria[currentPage]=1"); time.sleep(0.1)
tp = r.get("total_count", 0) if r else 0
data["products"]["total"] = tp
print("  Total: %d" % tp)
for sv, lb in [("1","enabled"),("2","disabled")]:
    p = "products?searchCriteria[pageSize]=1&searchCriteria[currentPage]=1"
    p += "&searchCriteria[filterGroups][0][filters][0][field]=status"
    p += "&searchCriteria[filterGroups][0][filters][0][value]=%s" % sv
    p += "&searchCriteria[filterGroups][0][filters][0][condition_type]=eq"
    r2 = curl(p); time.sleep(0.1)
    cnt = r2.get("total_count",0) if r2 else 0
    data["products"][lb] = cnt
    print("  %s: %d" % (lb, cnt))

# Low stock
r = curl("stockItems/lowStock?scopeId=0&qty=1&currentPage=1&pageSize=1"); time.sleep(0.1)
if r and "total_count" in r:
    data["products"]["low_stock"] = r["total_count"]
    print("  Low stock (qty<=1): %d" % r["total_count"])

# ─── 5. Audit KPIs ─────────────────────────────────────────────────
print("\n[5/5] AUDIT KPIs")
h1 = data["orders"]["h1_by_year"]
yoy = {}
for i in range(1, len(YEARS)):
    py, cy = str(YEARS[i-1]), str(YEARS[i])
    pd, cd = h1[py]["cmd_done"], h1[cy]["cmd_done"]
    yoy["H1_%s_%s" % (py, cy)] = round((cd-pd)/pd*100,1) if pd else 0

h1m26 = h1["2026"]["monthly"]
best_m  = max(h1m26, key=lambda k: h1m26[k])
worst_m = min(h1m26, key=lambda k: h1m26[k] if h1m26[k] > 0 else 9999)

data["audit"]["kpis"] = {
    "total_orders_alltime": sum(data["orders"]["yearly"][str(y)]["total"] for y in YEARS),
    "total_cmd_done_alltime": sum(data["orders"]["yearly"][str(y)]["by_status"].get("CMD_Done",0) for y in YEARS),
    "h1_2026": h1["2026"],
    "yoy_h1_growth": yoy,
    "best_month_h1_2026": MONTHS[best_m-1],
    "worst_month_h1_2026": MONTHS[worst_m-1],
    "total_customers": data["customers"]["total"],
    "total_products": tp,
}

issues = []
cr26 = h1["2026"]["cancel_rate_pct"]
if cr26 > 30: issues.append({"severity":"HIGH","issue":"Cancel rate %.1f%% > 30%% threshold" % cr26,"action":"Review confirmation process"})
for yr in YEARS[1:]:
    py, cy = str(yr-1), str(yr)
    g = yoy.get("H1_%s_%s" % (py,cy), 0)
    if g < 0:   issues.append({"severity":"HIGH","issue":"H1 %d orders declined %.1f%%" % (yr,g),"action":"Urgent: check demand & marketing"})
    elif g < 5: issues.append({"severity":"MEDIUM","issue":"H1 %d growth only %.1f%%" % (yr,g),"action":"Accelerate acquisition"})
    elif g > 50: issues.append({"severity":"INFO","issue":"H1 %d exceptional growth +%.1f%%" % (yr,g),"action":"Sustain and scale"})

data["audit"]["issues"] = issues

OUT = "/home/dashboard/public_html/webapp/magento_data.json"
with open(OUT, "w") as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print("\n" + "=" * 60)
print("FINAL SUMMARY")
print("=" * 60)
k = data["audit"]["kpis"]
print("All-time orders:    %s" % "{:,}".format(k["total_orders_alltime"]))
print("All-time CMD_Done:  %s" % "{:,}".format(k["total_cmd_done_alltime"]))
h26 = h1["2026"]
print("H1 2026 CMD_Done:   %d" % h26["cmd_done"])
print("H1 2026 Revenue:    %s DZD" % "{:,.0f}".format(h26["revenue_dzd"]))
print("H1 2026 AOV:        %s DZD" % "{:,}".format(h26["aov_dzd"]))
print("H1 2026 Cancel:     %s%%" % h26["cancel_rate_pct"])
print("H1 2026 Monthly:    %s" % list(h26["monthly"].values()))
print("YoY H1 growth:      %s" % yoy)
print("Total customers:    %s" % "{:,}".format(data["customers"]["total"]))
print("Total products:     %s" % "{:,}".format(tp))
print("\nAudit Issues: %d" % len(issues))
for iss in issues:
    print("  [%s] %s" % (iss["severity"], iss["issue"]))
print("\n✓ Saved: %s" % OUT)
