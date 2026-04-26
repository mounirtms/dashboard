# Cloudflare CDN Setup Guide
## Expected Impact: +15-25 Lighthouse points

### Step 1: Sign Up and Add Site
1. Go to https://dash.cloudflare.com/sign-up
2. Enter your email and create password
3. Click "Add a Site"
4. Enter: `technostationery.com`
5. Select "Free" plan
6. Click "Continue"

### Step 2: Update Nameservers
Cloudflare will provide you with nameservers like:
- `allen.ns.cloudflare.com`
- `linda.ns.cloudflare.com`

Update your domain's nameservers at your registrar:
1. Log in to your domain registrar (where you bought the domain)
2. Find DNS/Nameserver settings
3. Replace existing nameservers with Cloudflare's
4. Save changes
5. Wait 5-30 minutes for propagation

### Step 3: Cloudflare Optimization Settings

#### Speed Settings:
1. Go to **Speed** → **Optimization**
2. Enable:
   - ✅ Auto Minify: Check HTML, CSS, JavaScript
   - ✅ Brotli compression
   - ✅ Early Hints
   - ✅ Rocket Loader (defer JavaScript)
   - ✅ Mirage (image optimization)

#### Caching Settings:
1. Go to **Caching** → **Configuration**
2. Set Caching Level: **Standard**
3. Browser Cache TTL: **1 year**
4. Enable:
   - ✅ Always Online

#### Page Rules (Free plan: 3 rules):
Create these page rules in order:

**Rule 1: Cache Static Assets**
- URL: `technostationery.com/pub/static/*`
- Settings:
  - Cache Level: Cache Everything
  - Edge Cache TTL: 1 month
  - Browser Cache TTL: 1 year

**Rule 2: Cache Media**
- URL: `technostationery.com/pub/media/*`
- Settings:
  - Cache Level: Cache Everything
  - Edge Cache TTL: 1 month
  - Browser Cache TTL: 1 year

**Rule 3: Bypass Cache for Checkout/Account**
- URL: `technostationery.com/checkout/*`
- Settings:
  - Cache Level: Bypass

#### SSL/TLS Settings:
1. Go to **SSL/TLS**
2. Set encryption mode: **Full (strict)**
3. Enable:
   - ✅ Always Use HTTPS
   - ✅ Automatic HTTPS Rewrites

#### Security Settings:
1. Go to **Security** → **Settings**
2. Security Level: **Medium**
3. Challenge Passage: **30 minutes**
4. Enable:
   - ✅ Browser Integrity Check

### Step 4: Verify Setup
After nameservers propagate:
1. Visit your site: `https://technostationery.com`
2. Check if Cloudflare is active:
   ```bash
   curl -I https://technostationery.com | grep -i "cf-"
   ```
   You should see headers like `cf-cache-status`, `cf-ray`, etc.

### Step 5: Purge Cache
After setup:
1. Go to **Caching** → **Configuration**
2. Click "Purge Everything"
3. Wait 30 seconds
4. Test your site

### Step 6: Run Lighthouse
```bash
cd /home/technadminy7/public_html
./scripts/lighthouse_audit.sh
```

### Expected Results:
- **Before Cloudflare**: Lighthouse 15/100
- **After Cloudflare**: Lighthouse 30-45/100 (+15-30 points)
- **TTFB Improvement**: 3.1s → 1.5-2.0s
- **Asset Load Time**: -40-60% reduction
- **Global Performance**: Much faster for international users

### Additional Optimizations (Optional):
- **Cloudflare Images**: Automatic WebP conversion, lazy loading
- **Argo Smart Routing**: Faster routing (paid, $5/month)
- **Polish**: Automatic image optimization (paid)
- **Zaraz**: Faster third-party script loading

### Troubleshooting:
- **Site not loading**: Check nameserver propagation (up to 48h)
- **Mixed content warnings**: Enable "Automatic HTTPS Rewrites"
- **Assets not loading**: Check page rules, verify cache settings
- **Admin panel issues**: Add bypass rule for `/admin/*`

### Support:
- Cloudflare Docs: https://developers.cloudflare.com/
- Community: https://community.cloudflare.com/
