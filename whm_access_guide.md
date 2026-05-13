# WHM Access Guide
**Date:** 2026-05-10 02:00 CET

---

## WHM Access Status

**WHM is running and accessible.** There is no configuration issue on the server.

### How to Access WHM

| Method | URL | Status |
|--------|-----|--------|
| Direct IP | `https://205.134.249.177:2087` | Working |
| Server hostname | `https://ded701.inmotionhosting.com:2087` | Working |
| Your domain | `https://technostationery.com:2087` | Works with SSL warning |

### SSL Certificate Warning

WHM uses the server's SSL certificate (`ded701.inmotionhosting.com`), not your domain's certificate. When accessing via `technostationery.com:2087`, your browser will show an SSL warning because the certificate doesn't match the domain.

**Solution:** Accept the SSL certificate warning in your browser, or access WHM via:
- `https://205.134.249.177:2087` (direct IP)
- `https://ded701.inmotionhosting.com:2087` (server hostname)

### cPanel Access

| Method | URL |
|--------|-----|
| cPanel | `https://205.134.249.177:2083` |
| Webmail | `https://205.134.249.177:2096` |

### Why Cloudflare Doesn't Affect WHM

Cloudflare only proxies traffic on specific ports:
- HTTP: 80, 8080, 8880, 2052, 2082, 2086, 2095
- HTTPS: 443, 2053, 2083, 2087, 2096, 8443, 8886

WHM runs on port **2087** which IS proxied by Cloudflare, but since WHM uses its own self-signed/server certificate, Cloudflare cannot proxy it properly. **WHM should always be accessed directly via IP or server hostname, not through Cloudflare.**

### Firewall Status

- **iptables:** Port 2087 is allowed (ACCEPT rule)
- **CSF Firewall:** Port 2087 is in TCP_IN whitelist
- **cPanel service:** Running (active for 5 days)

### Troubleshooting

If you still cannot access WHM:

1. **Check your IP isn't blocked by CSF:**
   ```
   ssh root@205.134.249.177
   csf -g YOUR_IP
   ```

2. **Restart cPanel service:**
   ```
   systemctl restart cpanel
   ```

3. **Check cPanel logs:**
   ```
   tail -f /usr/local/cpanel/logs/access_log
   ```

---

*WHM access investigation completed 2026-05-10 02:00 CET*
