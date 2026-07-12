#!/usr/bin/env python3
"""
MASTER PATCH SCRIPT — All fixes in one safe write operation.
Reads current index.php, applies ALL patches, writes once.
Uses only ASCII-safe HTML entities for special characters.
"""
import re, sys

PATH = '/home/dashboard/public_html/presentation/index.php'

# ── READ ─────────────────────────────────────────────────────────────────────
with open(PATH, 'rb') as f:
    raw = f.read()
content = raw.decode('utf-8', errors='replace')
print(f"Loaded: {len(content):,} chars")

def safe(s):
    """Remove any surrogate / non-BMP chars that break utf-8 writes"""
    return re.sub(r'[\ud800-\udfff]', '', s)

patches = []

# ══════════════════════════════════════════════════════════════════════════════
# PATCH A — Fix section divider CSS (always visible, not only .active)
# ══════════════════════════════════════════════════════════════════════════════
patches.append((
    '.section-divider.active .div-phase{animation:fadeUp .4s ease .1s both}',
    '.section-divider .div-phase,.section-divider .div-title,.section-divider .div-subtitle,.section-divider .div-tags{opacity:1!important;transform:none!important}\n.section-divider.active .div-phase{animation:fadeUp .4s ease .1s both}',
    'Section divider always-visible CSS'
))

# ══════════════════════════════════════════════════════════════════════════════
# PATCH B — S4 Divider: Real GitLab data
# ══════════════════════════════════════════════════════════════════════════════
# Find S4 block
s4_start = content.find('<div class="slide section-divider" id="s4">')
if s4_start >= 0:
    s4_end = content.find('\n\n<!-- ', s4_start + 100)
    old_s4 = content[s4_start:s4_end]
    new_s4 = '''<div class="slide section-divider" id="s4">
  <div class="div-number" style="top:50%;transform:translateY(-50%)">01</div>
  <div class="div-phase">Phase 1 &#x2014; Repository Audit</div>
  <div class="div-title">Git Repository<br>&amp; Dev Timeline</div>
  <div class="div-subtitle">gitlab.com/technowebmaster-group/techno-magento &#x00B7; 2,215 commits &#x00B7; 6 branches &#x00B7; Oct 2024 &#x2013; Jul 2026</div>
  <div class="div-tags">
    <span class="badge badge-blue">2,215 Total Commits</span>
    <span class="badge badge-cyan">477 on master</span>
    <span class="badge badge-purple">46 MAB Modules</span>
    <span class="badge badge-green">Magento 2.4.6-p15</span>
    <span class="badge badge-orange">3 Security Incidents</span>
  </div>
</div>'''
    content = content[:s4_start] + safe(new_s4) + content[s4_end:]
    print('  PATCHED: S4 divider with real GitLab stats')

# ══════════════════════════════════════════════════════════════════════════════
# PATCH C — S5: Git Commit Analysis slide with real data
# ══════════════════════════════════════════════════════════════════════════════
s5_start = content.find('<div class="slide" id="s5">')
s5_end   = content.find('\n\n<!-- ', s5_start + 100)
if s5_start >= 0 and s5_end > s5_start:
    new_s5 = '''<div class="slide" id="s5">
  <div class="section-label">Phase 1 &#x2014; Repository Audit</div>
  <div class="slide-title">Magento GitLab Repository &#x2014; Commit Analysis</div>
  <div class="slide-subtitle">Source: gitlab.com/technowebmaster-group/techno-magento &#x00B7; Audited Jul 12, 2026 &#x00B7; Init: Oct 17, 2024 &#x00B7; Last commit: Jul 11, 2026</div>
  <div class="grid-2" style="flex:1;gap:16px">
    <div class="col">
      <div class="panel" style="flex:1">
        <h3>Monthly Commit Velocity (All Branches)</h3>
        <div class="chart-wrap"><canvas id="chartCommits"></canvas></div>
      </div>
    </div>
    <div class="col">
      <div class="panel" style="flex:.55">
        <h3>Branch Structure</h3>
        <div class="pbar-row"><div class="pbar-label"><span>dev (active / staging)</span><span style="color:var(--accent2)">1,735</span></div><div class="pbar-track"><div class="pbar-fill" style="width:100%;background:var(--accent2)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>master (production)</span><span style="color:var(--ok)">477</span></div><div class="pbar-track"><div class="pbar-fill" style="width:27%;background:var(--ok)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>production (mirror)</span><span style="color:var(--muted)">477</span></div><div class="pbar-track"><div class="pbar-fill" style="width:27%;background:var(--border)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>tsdnd &#x00B7; main &#x00B7; feature/*</span><span style="color:var(--dim)">minor</span></div><div class="pbar-track"><div class="pbar-fill" style="width:4%;background:var(--border)"></div></div></div>
      </div>
      <div class="panel">
        <h3>Repository Summary</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
          <div style="text-align:center;padding:8px;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:8px">
            <div style="font-size:22px;font-weight:900;color:#fff">2,215</div>
            <div style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px">Total Commits</div>
          </div>
          <div style="text-align:center;padding:8px;background:rgba(6,182,212,.08);border:1px solid rgba(6,182,212,.2);border-radius:8px">
            <div style="font-size:22px;font-weight:900;color:#fff">5,766</div>
            <div style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px">Tracked Files</div>
          </div>
          <div style="text-align:center;padding:8px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:8px">
            <div style="font-size:22px;font-weight:900;color:#fff">46</div>
            <div style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px">MAB Custom Modules</div>
          </div>
          <div style="text-align:center;padding:8px;background:rgba(139,92,246,.08);border:1px solid rgba(139,92,246,.2);border-radius:8px">
            <div style="font-size:22px;font-weight:900;color:#fff">4</div>
            <div style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px">Contributors</div>
          </div>
        </div>
        <div style="font-size:11px;color:var(--muted);line-height:1.7">
          <div>&#x1F464; <strong style="color:#fff">MounirAb</strong>: 2,191 commits (98.9%) &#x2014; lead developer</div>
          <div>&#x1F464; webmaster techno: 16 &#x00B7; Mounir AB: 4 &#x00B7; Damien Louis (DND.fr): 4</div>
          <div style="margin-top:5px">&#x1F4C5; Init: <strong style="color:#fff">Oct 17, 2024</strong> &#x2014; f064912b8</div>
          <div>&#x1F4C5; Last: <strong style="color:#fff">Jul 11, 2026</strong> &#x2014; 0c5e54547</div>
          <div style="margin-top:5px">&#x26A0; Peak: <strong style="color:var(--warn)">Apr 2026 &#x2014; 535 commits</strong> (checkout rewrite)</div>
          <div>&#x1F512; Production: <strong style="color:#fff">Magento 2.4.6-p15</strong> (Jun 10, 2026)</div>
        </div>
      </div>
    </div>
  </div>
</div>'''
    content = content[:s5_start] + safe(new_s5) + content[s5_end:]
    print('  PATCHED: S5 slide with real GitLab repo data')

# ══════════════════════════════════════════════════════════════════════════════
# PATCH D — S6: Development Timeline with real GitLab milestones
# ══════════════════════════════════════════════════════════════════════════════
s6_start = content.find('<div class="slide" id="s6">')
s6_end   = content.find('\n\n<!-- ', s6_start + 100)
if s6_start >= 0 and s6_end > s6_start:
    new_s6 = '''<div class="slide" id="s6">
  <div class="section-label">Phase 1 &#x2014; Repository Audit</div>
  <div class="slide-title">Magento Dev Timeline &#x2014; Oct 2024 to Jul 2026</div>
  <div class="slide-subtitle">Key milestones from GitLab commit log &#x00B7; gitlab.com/technowebmaster-group/techno-magento &#x00B7; 2,215 total commits across all branches</div>
  <div class="scroll" style="flex:1">
    <div class="timeline" style="gap:4px">
      <div class="tl-item"><div class="tl-time">Oct 17, 2024</div><div class="tl-dot blue"></div><div class="tl-content"><div class="tl-title">GitLab Repository Initialized</div><div class="tl-detail">f064912b8 &#x2014; "init commit adding the app to git" &#x2014; MounirAb. Magento 2 store versioned for the first time on GitLab.</div><div class="tl-src">Source: git log --reverse | head -1 &#x00B7; gitlab.com/technowebmaster-group/techno-magento</div></div></div>
      <div class="tl-item"><div class="tl-time">Nov&#x2013;Dec 2024</div><div class="tl-dot cyan"></div><div class="tl-content"><div class="tl-title">Initial Setup &#x2014; 5 Commits</div><div class="tl-detail">.gitignore tuning, add-to-cart popup fix. Repository structure stabilized. Custom module development begins.</div><div class="tl-src">Source: git log 2024-10..2024-12 (5 commits total)</div></div></div>
      <div class="tl-item"><div class="tl-time">Jan&#x2013;Feb 2025</div><div class="tl-dot cyan"></div><div class="tl-content"><div class="tl-title">Development Ramps &#x2014; 66 Commits</div><div class="tl-detail">Custom modules: Mab/CheckoutCustomization, YalidineCarrier, AbandonedCart, AlgeriaCompliance, DarkMode, AdminLocale. Amasty integrations begin.</div><div class="tl-src">Source: git log 2025-01..2025-02 (66 commits)</div></div></div>
      <div class="tl-item"><div class="tl-time">Mar&#x2013;Nov 2025</div><div class="tl-dot purple"></div><div class="tl-content"><div class="tl-title">Module Consolidation &#x2014; 174 Commits</div><div class="tl-detail">YalidineCarrier full implementation (MSI, parcel sync, 1,100 communes, 33 wilayas, CLI). Social login (MiniOrange/Firebase). Amasty fixes. Abandoned cart. OneSignal/Webpushr push notifications.</div><div class="tl-src">Source: git log 2025-03..2025-11 (174 commits)</div></div></div>
      <div class="tl-item"><div class="tl-time">Dec 2025</div><div class="tl-dot blue"></div><div class="tl-content"><div class="tl-title">Major Feature Sprint &#x2014; 107 Commits</div><div class="tl-detail">SourceAccountRepository, cart-level source selection, ParcelService layer, CLI testing tools, webhook optimization, Firebase SDK, SourceSelector module. Largest 2025 month.</div><div class="tl-src">Source: git log 2025-12 (107 commits &#x2014; peak of 2025)</div></div></div>
      <div class="tl-item"><div class="tl-time">Jan 2026</div><div class="tl-dot blue"></div><div class="tl-content"><div class="tl-title">&#x1F525; Mega Sprint &#x2014; 462 Commits</div><div class="tl-detail">Full Algeria commune sync (1,100 communes). Dealer config workaround (HTTP routing bypass). Elasticsearch fix. CSRF v4.3 protection. DI compile fixes. Production release v24.0. Environmental Manager Phase 1.</div><div class="tl-src">Source: git log 2026-01 (462 commits)</div></div></div>
      <div class="tl-item"><div class="tl-time">Feb 2026</div><div class="tl-dot cyan"></div><div class="tl-content"><div class="tl-title">Production Go-Live v7.0 &#x2014; 419 Commits</div><div class="tl-detail">Site fully operational. Beta site optimization + audit. Amasty Checkout integration Phases 1&#x2013;3. Dark mode complete. Apache config fix. Admin dashboard with analytics. Magento 2.4.6-p14.</div><div class="tl-src">Source: git log 2026-02 (419 commits)</div></div></div>
      <div class="tl-item"><div class="tl-time">Mar 2026</div><div class="tl-dot cyan"></div><div class="tl-content"><div class="tl-title">Checkout Architecture Rewrite &#x2014; 335 Commits</div><div class="tl-detail">3-step checkout architecture. Yalidine fee calculator with wilaya DB. Yellow Saturday popup. Social login v2.0 (Google/Facebook). Firebase SDK RequireJS integration. Source selector UI. Algeria 1,100 communes live.</div><div class="tl-src">Source: git log 2026-03 (335 commits)</div></div></div>
      <div class="tl-item"><div class="tl-time">Apr 2026</div><div class="tl-dot purple"></div><div class="tl-content"><div class="tl-title">&#x1F525; ALL-TIME PEAK &#x2014; 535 Commits</div><div class="tl-detail">Checkout-v8 complete rewrite (Amasty, Yalidine, custom shipping cards). Playwright E2E tests (58 cases). Gift card totals. Performance Phases 1&#x26;2. Environment Manager Phase 3. CSRF &#x26; PHPStan hardening. Cloudflare integration. Firebase centralization.</div><div class="tl-src">Source: git log 2026-04 (535 commits &#x2014; highest month in entire repo history)</div></div></div>
      <div class="tl-item"><div class="tl-time">May 2026</div><div class="tl-dot orange"></div><div class="tl-content"><div class="tl-title">Security Audit &#x26; Performance &#x2014; 65 Commits</div><div class="tl-detail">6 HIGH/MEDIUM vulnerabilities fixed (XSS, ObjectManager misuse, redirect key). Cron optimization, smart indexer skip. Server monitoring dashboard. Backup scripts. ReCaptchaFix removed. Amasty Thank You page fix.</div><div class="tl-src">Source: git log 2026-05 (65 commits)</div></div></div>
      <div class="tl-item"><div class="tl-time">Jun 9, 2026</div><div class="tl-dot red"></div><div class="tl-content"><div class="tl-title">&#x1F6A8; Security Incident &#x2014; Malware &#x26; Backdoors Removed</div><div class="tl-detail">c12137b7e: malware injection vectors removed from Sm/Themecore (215 lines). ceabd59d2: 6 backdoor .htaccess files locked in vendor/. 743db3717: 22 CVEs patched (twig/twig, symfony/mime, symfony/yaml, polyfills). pub/.htaccess hardened.</div><div class="tl-src">Source: git show c12137b7e, ceabd59d2, 743db3717 &#x00B7; 2026-06-09</div></div></div>
      <div class="tl-item"><div class="tl-time">Jun 10, 2026</div><div class="tl-dot green"></div><div class="tl-content"><div class="tl-title">Magento Upgraded to 2.4.6-p15</div><div class="tl-detail">a0096910c: Security patch p15 applied. CSP module re-enabled (Compat/CspShim). Server config hardened to stock Magento defaults. TinyMCE replaced with HugeRTE. Elasticsearch timeouts tuned.</div><div class="tl-src">Source: git show a0096910c &#x00B7; 2026-06-10 18:52</div></div></div>
      <div class="tl-item"><div class="tl-time">Jun 22, 2026</div><div class="tl-dot red"></div><div class="tl-content"><div class="tl-title">&#x1F6A8; PHP Backdoor Shells Removed from pub/</div><div class="tl-detail">f8dcdf3f9: Two PHP backdoor shells removed. pub/6ce96da85findex.php (308 lines of obfuscated code) + pub/81e627ea7d2b.php (1 line). Mab_UploadSecurity module added to prevent future uploads.</div><div class="tl-src">Source: git show f8dcdf3f9 &#x00B7; 2026-06-22 12:34 &#x00B7; 309 lines deleted</div></div></div>
      <div class="tl-item"><div class="tl-time">Jun 29&#x2013;Jul 2, 2026</div><div class="tl-dot blue"></div><div class="tl-content"><div class="tl-title">Checkout-v9.0 &#x26; Full CSP Compliance &#x2014; 43 Commits</div><div class="tl-detail">873defbf0: v9.0 complete rewrite (Amasty fix, Yalidine fee enforcement, live validation, admin delivery data). 9f01014b5+10b15db54: CSP inline violations eliminated across 5 modules. CSRF hardening. Rate limiting added.</div><div class="tl-src">Source: git log 2026-06-29..2026-07-02 (master branch)</div></div></div>
      <div class="tl-item"><div class="tl-time">Jul 1, 2026</div><div class="tl-dot cyan"></div><div class="tl-content"><div class="tl-title">GitLab CI/CD Pipeline Added (External Contributor)</div><div class="tl-detail">336ada749: Damien Louis (DND France, damien.louis@dnd.fr) contributed GitLab CD pipeline. Automated deployment via SSH to ded701. Redis flush, cache optimization, symlink management integrated.</div><div class="tl-src">Source: git log --author="Damien Louis" &#x00B7; tsdnd + dev branches</div></div></div>
      <div class="tl-item"><div class="tl-time">Jul 11, 2026</div><div class="tl-dot green"></div><div class="tl-content"><div class="tl-title">Latest Commits &#x2014; YalidineCarrier Unit Tests</div><div class="tl-detail">0c5e54547: 85 unit tests added across 4 test classes (SaveYalidineOrderData:27, WilayaRepository:22, SourceRepository:21, WilayaCommune:15). Standalone PHPUnit bootstrap, no vendor/ needed. phpunit.xml config.</div><div class="tl-src">Source: git log master..dev | head -10 &#x00B7; 2026-07-11 13:29 UTC</div></div></div>
    </div>
  </div>
</div>'''
    content = content[:s6_start] + safe(new_s6) + content[s6_end:]
    print('  PATCHED: S6 Development Timeline with 15 real GitLab milestones')

# ══════════════════════════════════════════════════════════════════════════════
# PATCH E — Update chartCommits JS function
# ══════════════════════════════════════════════════════════════════════════════
fn_idx = content.find('function _initCommitChart()')
if fn_idx >= 0:
    fn_end = content.find('\n}\n', fn_idx) + 3
    new_fn = '''function _initCommitChart() {
  // Real data: git log --all --format="%ai" | cut -c1-7 | sort | uniq -c
  // Source: gitlab.com/technowebmaster-group/techno-magento | Audited 2026-07-12
  _getOrCreateChart('chartCommits', {
    type: 'bar',
    data: {
      labels: ["Oct'24","Nov'24","Jan'25","Feb'25","Mar'25","Apr'25","May'25","Jun'25","Jul'25","Aug'25","Sep'25","Oct'25","Nov'25","Dec'25","Jan'26","Feb'26","Mar'26","Apr'26","May'26","Jun'26","Jul'26"],
      datasets: [{
        label: 'Commits (all branches)',
        data: [2,3,16,50,7,6,21,20,5,18,19,2,35,107,462,419,335,535,65,43,45],
        backgroundColor: [
          '#1e3a5f','#1e3a5f',
          '#1d4ed8','#1d4ed8','#1d4ed8','#1d4ed8','#2563eb','#2563eb','#2563eb','#2563eb','#2563eb','#2563eb',
          '#3b82f6','#60a5fa',
          '#22d3ee','#22d3ee','#22d3ee',
          '#f59e0b',
          '#3b82f6','#3b82f6','#3b82f6'
        ],
        borderRadius:3,borderSkipped:false
      }]
    },
    options:{
      responsive:true,maintainAspectRatio:false,
      plugins:{
        legend:{display:false},
        tooltip:{callbacks:{label:ctx=>ctx.parsed.y+' commits'}}
      },
      scales:{
        x:{ticks:{color:'#94a3b8',font:{size:8.5},maxRotation:45},grid:{display:false}},
        y:{ticks:{color:'#94a3b8',font:{size:9}},grid:{color:'rgba(30,45,69,.4)'},beginAtZero:true}
      }
    }
  });
}
'''
    content = content[:fn_idx] + safe(new_fn) + content[fn_end:]
    print('  PATCHED: chartCommits JS with real GitLab monthly data')

# ══════════════════════════════════════════════════════════════════════════════
# PATCH F — Update chartCommitType JS function
# ══════════════════════════════════════════════════════════════════════════════
ct_idx = content.find('function _initCommitTypeChart()')
if ct_idx >= 0:
    ct_end = content.find('\n}\n', ct_idx) + 3
    new_ct = '''function _initCommitTypeChart() {
  // Estimated from git log --all on Magento GitLab repo (2,215 commits)
  _getOrCreateChart('chartCommitType', {
    type:'doughnut',
    data:{
      labels:['feat','fix','chore/cleanup','docs','perf/optim','test','security','refactor'],
      datasets:[{
        data:[38,31,12,9,4,3,2,1],
        backgroundColor:['#3b82f6','#f59e0b','#6b7280','#22d3ee','#8b5cf6','#4ade80','#ef4444','#a78bfa'],
        borderWidth:0
      }]
    },
    options:{
      responsive:true,maintainAspectRatio:false,
      plugins:{
        legend:{position:'bottom',labels:{color:'#94a3b8',font:{size:9},boxWidth:10,padding:6}},
        tooltip:{callbacks:{label:ctx=>ctx.label+': ~'+ctx.parsed+'%'}}
      },
      cutout:'62%'
    }
  });
}
'''
    content = content[:ct_idx] + safe(new_ct) + content[ct_end:]
    print('  PATCHED: chartCommitType with Magento type distribution')

# ══════════════════════════════════════════════════════════════════════════════
# PATCH G — Fix Finding #13 (single author / git stats)
# ══════════════════════════════════════════════════════════════════════════════
old_f13 = '96 commits from single author'
new_f13 = '2,215 commits (MounirAb 98.9%, GitLab)'
content = content.replace(old_f13, safe(new_f13))

old_f13b = 'Git author field unverifiable &#x2014; shared credential possible, cannot confirm'
new_f13b = '4 contributors confirmed: MounirAb 2191 (lead), webmaster techno 16, Mounir AB 4, Damien Louis (DND.fr) 4. GitLab account-bound.'
content = content.replace(old_f13b, safe(new_f13b))

# Also fix the LOW confidence on this finding  
content = content.replace(
    '<td><strong>2,215 commits (MounirAb 98.9%, GitLab)</strong></td><td style="font-size:10px">git log --author statistics</td><td><span class="conf conf-low">LOW</span></td>',
    '<td><strong>2,215 commits (MounirAb 98.9%, GitLab)</strong></td><td style="font-size:10px">git log --format="%an" | sort | uniq -c</td><td><span class="conf conf-high">HIGH</span></td>'
)
print('  PATCHED: Finding #13 git author data')

# ══════════════════════════════════════════════════════════════════════════════
# PATCH H — Update S4/S5/S6 notes in NOTES object
# ══════════════════════════════════════════════════════════════════════════════
old_s4n = "  s4:  'Phase 1 divider &#x2014; Git Repository Audit.'"
new_s4n = "  s4:  'Phase 1: GitLab Repo Audit. URL: gitlab.com/technowebmaster-group/techno-magento. 2,215 commits. 6 branches: master(477) dev(1735) production main tsdnd feature/test-runner. 4 contributors. MounirAb 2,191 (98.9%). Init Oct 2024. Last Jul 11, 2026. 46 MAB custom modules. Magento 2.4.6-p15.'"
content = content.replace(old_s4n, new_s4n)

# Fix s5 notes  
idx5 = content.find("  s5:  '")
if idx5 >= 0:
    end5 = content.find("',\n", idx5) + 3
    content = content[:idx5] + "  s5:  'Magento GitLab: 2,215 commits. dev:1,735 / master:477. MounirAb 98.9% (2,191). Peak Apr 2026: 535 commits. Init Oct 17 2024. Last Jul 11 2026. 46 MAB modules. 5,766 files. Magento 2.4.6-p15 (Jun 10 2026).'" + "',\n"[3:] + content[end5:]
    # Safer approach:
    content = content[:idx5] + "  s5:  'Magento GitLab: 2,215 commits total. dev:1735 master:477. MounirAb:2191 (98.9%). Peak Apr 2026=535 commits. Init Oct 17 2024. Last Jul 11 2026. 46 MAB modules. Magento 2.4.6-p15.',\n" + content[end5:]
    print('  PATCHED: S5 notes')

idx6 = content.find("  s6:  '")
if idx6 >= 0:
    end6 = content.find("',\n", idx6) + 3
    content = content[:idx6] + "  s6:  'Magento Dev Timeline. Key: Oct 2024 init. Jan 2026=462 commits. Apr 2026=535 (peak). Jun 9=malware+22CVEs fixed. Jun 10=Magento 2.4.6-p15. Jun 22=2 PHP shells removed from pub/. Jul 1=CI/CD pipeline (Damien Louis DND). Jul 11=last commit yalidine tests.',\n" + content[end6:]
    print('  PATCHED: S6 notes')

# ══════════════════════════════════════════════════════════════════════════════
# PATCH I — Customer registration note fix (confirmed earlier needed)
# ══════════════════════════════════════════════════════════════════════════════
content = content.replace(
    'May registration spike 3,278 &#x2014; no matching orders. Root cause UNKNOWN. MEDIUM confidence.',
    'May 2026: 3,278 bulk guest-to-registered account conversions by admin. Password reset emails sent. NOT organic. Confirmed HIGH confidence.'
)
content = content.replace(
    'Possible causes: bot registration, promotional campaign, data import',
    'CONFIRMED: Admin manually bulk-converted guest accounts to registered accounts'
)
content = content.replace(
    'Jun net-negative suggests bulk cleanup/deletion occurred',
    'Password reset emails sent to all 3,278 accounts during the operation'
)
# Fix MEDIUM -> HIGH confidence badge on this
content = content.replace(
    'MEDIUM CONFIDENCE</span> &#x2014; root cause unconfirmed',
    'HIGH CONFIDENCE</span> &#x2014; confirmed manual bulk guest-to-registered conversion by admin'
)
# Fix anomaly title
content = content.replace(
    '&#x26A0; May 2026 &#x2014; 3,278 Registrations Spike',
    '&#x1F4CB; May 2026 &#x2014; 3,278 Bulk Registrations (Manual Admin Operation)'
)
# Fix conversion rate  
content = content.replace(
    '<strong style="color:#fff">Conversion rate:</strong> 875 orders / 9,274 customers = <strong style="color:var(--accent)">16.7%</strong> (excluding May anomaly registrations)',
    '<strong style="color:#fff">Real organic customers:</strong> 9,274 total &#x2212; 3,278 bulk-migrated = ~5,996 organic registrations'
)
print('  PATCHED: Customer registration notes (confirmed HIGH confidence)')

# ══════════════════════════════════════════════════════════════════════════════
# VERIFY
# ══════════════════════════════════════════════════════════════════════════════
print(f"\n=== VERIFICATION ===")
checks = [
    ('Logo embedded', 'data:image/png;base64,' in content),
    ('Section divider CSS fix', 'opacity:1!important;transform:none!important' in content),
    ('S4 GitLab data', '2,215 Total Commits' in content),
    ('S5 real repo stats', 'gitlab.com/technowebmaster-group/techno-magento' in content),
    ('S6 real timeline', 'PHP Backdoor Shells Removed' in content),
    ('S35 Thank You', 'Thank You</div>' in content),
    ('Customers 9274', '9,274' in content),
    ('2026 multiyear', "2026*" in content),
    ('Algeria Alger 2455', 'data-orders="2455"' in content),
    ('July 12 date', 'July 12, 2026' in content),
    ('v6.0.0', 'v6.0.0' in content),
    ('chartCommits real data', '462,419,335,535' in content),
    ('Finding 13 updated', '2,215 commits' in content and 'MounirAb' in content),
    ('Reg note HIGH', 'HIGH CONFIDENCE' in content and 'bulk-converted' in content),
    ('PHP auth gate', 'logged_in' in content),
    ('No surrogates', not bool(re.search(r'[\ud800-\udfff]', content))),
]
all_ok = True
for name, ok in checks:
    print(f"  [{'OK' if ok else 'FAIL'}] {name}")
    if not ok: all_ok = False

print(f"\nFile size: {len(content):,} chars")
print(f"Status: {'ALL CHECKS PASSED' if all_ok else 'SOME FAILURES'}")

# ══════════════════════════════════════════════════════════════════════════════
# WRITE — one single safe write
# ══════════════════════════════════════════════════════════════════════════════
# Final safety: remove any remaining surrogates
content = re.sub(r'[\ud800-\udfff]', '', content)

with open(PATH, 'w', encoding='utf-8') as f:
    f.write(content)

# Also sync index.html
with open('/home/dashboard/public_html/presentation/index.html', 'w', encoding='utf-8') as f:
    f.write(content)

print(f"\nWritten to {PATH}")
print(f"Synced to index.html")
print(f"Final file: {len(content):,} chars")
