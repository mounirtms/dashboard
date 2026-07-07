# Comprehensive Test Results - 20260503-163215

## Test Overview

| Test | Status | Details |
|------|--------|---------|
| PIM Homepage | ✅ PASS | Returns 302 redirect to login |
| PIM Login Page | ✅ PASS | Returns 200, form loads correctly |
| CSS File | ⚠️ ISSUE | Returns 404 but content exists (101KB) |
| Database | ✅ PASS | MariaDB connection successful |
| Elasticsearch | ✅ PASS | Cluster status: yellow, 1 node |
| API Endpoints | ✅ PASS | Returns 401 as expected (needs OAuth) |
| Dashboard | ✅ PASS | Returns 200 OK |
| Session Audit | ✅ PASS | No session errors, Redis healthy |

## Authentication Tests

| Credential | Result |
|------------|--------|
| admin/PimAdmin2026! | Login failed (still on login page) |
| adminreset/PassWord1234 | Login failed (redirects back to login) |
| bot/@dM1n$#@2025B0T | Login failed |
| **All attempts redirect back to login** | ❌ ISSUE |

## Issues Found

1. **Login Form Issue**: All login attempts fail - redirects back to login page
2. **CSS File**: Returns 404 status but serves content (misconfigured rewrite rule)
3. **Console Errors**: 19 errors in browser console (cdn-cgi/rum failures)
4. **Failed Requests**: 3 failed cdn-cgi/rum requests
5. **Products Page**: Returns 401 (authentication needed)
6. **PHP Tests**: 16/16 tests in run-all-tests.sh failed (missing/incompatible test files)

## System Health

- **Load Average**: 2.54, 2.87, 2.81
- **Memory**: 20Gi/31Gi used
- **MySQL**: OK
- **Elasticsearch**: OK (yellow status - 4 unassigned shards)
- **Redis Memory**: 441.90M / 4.0GB

## Files Generated

- pim_test_comprehensive.log
- health_check.log
- playwright_browser_test.log
- pim_diagnostic.log
- database_test.log
- elasticsearch_test.log
- api_test.log
- session_audit.log
- pim_test_screenshot.png
- test_report_2026-05-03_16-35-30.html
- test_report_2026-05-03_16-35-30.json
