# TechnoStationery Dashboard — Webapp Scripts

## Data Fetcher

### `get_all_real_data.py`
Fetches production data from Magento REST API.
- Requires valid token in `/home/dashboard/public_html/config/magento_credentials.json`
- Outputs `fresh_data_2026h1.json` and `real_data.json`
- Usage: `python3 get_all_real_data.py`

### `fetch_h1_data.sh`
Shell script to bulk-fetch H1 2026 CMD_Done orders (5 pages × 100).
- Usage: `bash fetch_h1_data.sh`
- Output: `orders_page{1-5}.json` (temp, gitignored)

## Data Files

### `real_data.json`
Ground truth production snapshot (last updated 2026-07-12):
- CMD_Done H1 2026: **498**
- Revenue: **2,784,169 DZD** (2.78M)
- AOV: **5,591 DZD**
- Cancel rate: **35.2%** (288/819)
- Monthly: [116, 69, 74, 81, 88, 70]

### `fresh_data_2026h1.json`
Full wilaya breakdown for H1 2026 CMD_Done orders.

## Token Refresh
If API returns 403, regenerate token:
```bash
curl -s -X POST https://technostationery.com/rest/V1/integration/admin/token \
  -H "Content-Type: application/json" \
  -d '{"username":"mabbot","password":"@dM1n$#@2o26MaBB0T"}'
```
Update `config/magento_credentials.json` with the new token.

## Presentation
The presentation is at `../presentation/index.php` (v6.4.1).
- Auth-free test copy: `../presentation/test_view.html`
- Live URL: https://dashboard.technostationery.com/presentation/
