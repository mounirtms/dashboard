#!/usr/bin/env python3
"""
Fix presentation s5 with REAL GitLab Magento repo data.

Real data from gitlab.com/technowebmaster-group/techno-magento (master branch):
- Total commits (master): 481
- By Mounir (as 'Mounir Abderrahmani' + 'Dev Environment' = same person): 480
- Date range: Nov 3, 2025 → Jul 8, 2026
- H2 2025 (Jul-Dec): 43 commits
- H1 2026 (Jan-Jun): 434 commits  ← the audit period
- Jul 2026 so far: 5 commits
- Monthly breakdown: Nov'25=35, Dec'25=8, Jan'26=31, Feb'26=80(peak), Mar'26=25, Apr'26=278+12=290, May'26=9, Jun'26=10, Jul'26=5
- Note: Apr 2026 had 266 Dev Environment + 12 Mounir = 278 total (big sprint: checkout customization)
- Peak: Feb 2026 = 80 commits (Feb 15 crisis: Amasty conflicts, site down, checkout restoration)
- Branches: master (production), production, dev, tsdnd, main, feature/test-runner
- CI/CD: GitLab pipeline, ded701-runner-production, deploys to tsdnd/dev/production
"""

with open('/home/dashboard/public_html/presentation/index.html', 'r', encoding='utf-8') as f:
    content = f.read()

original_len = len(content)
print(f"Original: {original_len} chars")

# ═══════════════════════════════════════════════════
# 1. Fix s5 slide subtitle
# ═══════════════════════════════════════════════════
old_s5_sub = '<div class="slide-subtitle">Source: git log · mounirtms/dashboard (Magento + Dashboard platform) · 94 total commits · GitHub→GitLab migration · CI/CD deployed to tsdnd</div>'
new_s5_sub = '<div class="slide-subtitle">Source: git log · gitlab.com/technowebmaster-group/techno-magento (master) · 481 commits · 6 branches · GitLab CI/CD runner ded701 · tsdnd / dev / production deploys</div>'
content = content.replace(old_s5_sub, new_s5_sub)
print("✓ s5 subtitle updated")

# ═══════════════════════════════════════════════════
# 2. Fix s5 Repository Summary panel
# ═══════════════════════════════════════════════════
old_summary = '<div class="pbar-row"><div class="pbar-label"><span>Total commits (master)</span><span style="color:#60a5fa">94</span></div><div class="pbar-track"><div class="pbar-fill" style="width:94%;background:var(--accent)"></div></div></div>'
new_summary = '<div class="pbar-row"><div class="pbar-label"><span>Total commits (master)</span><span style="color:#60a5fa">481</span></div><div class="pbar-track"><div class="pbar-fill" style="width:100%;background:var(--accent)"></div></div></div>'
content = content.replace(old_summary, new_summary)
print("✓ s5 total commits bar updated to 481")

# ═══════════════════════════════════════════════════
# 3. Fix s5 branch/commit type bars
# ═══════════════════════════════════════════════════
old_type_bars = '''<div style="font-size:10px;color:var(--muted);margin-bottom:5px;padding:5px;background:rgba(59,130,246,.05);border-radius:4px;border-left:2px solid #3b82f6"><strong style="color:#60a5fa">Branches:</strong> master (production) · main (protected PR base) · genspark_ai_developer (AI dev)</div><div class="pbar-row"><div class="pbar-label"><span>feat: features (+2 S12)</span><span>32</span></div><div class="pbar-track"><div class="pbar-fill" style="width:32%;background:var(--ok)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>fix commits</span><span>26</span></div><div class="pbar-track"><div class="pbar-fill" style="width:28%;background:var(--warn)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>docs/chore/other</span><span>37</span></div><div class="pbar-track"><div class="pbar-fill" style="width:40%;background:var(--accent3)"></div></div></div>'''
new_type_bars = '''<div style="font-size:10px;color:var(--muted);margin-bottom:5px;padding:5px;background:rgba(59,130,246,.05);border-radius:4px;border-left:2px solid #3b82f6"><strong style="color:#60a5fa">Branches:</strong> master (production) · production · dev · tsdnd (test deploy) · main · feature/test-runner</div><div class="pbar-row"><div class="pbar-label"><span>feat: features</span><span>76</span></div><div class="pbar-track"><div class="pbar-fill" style="width:76%;background:var(--ok)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>fix: bug fixes</span><span>112</span></div><div class="pbar-track"><div class="pbar-fill" style="width:100%;background:var(--warn)"></div></div></div>
        <div class="pbar-row"><div class="pbar-label"><span>docs / chore / perf / other</span><span>293</span></div><div class="pbar-track"><div class="pbar-fill" style="width:100%;background:var(--accent3)"></div></div></div>'''
content = content.replace(old_type_bars, new_type_bars)
print("✓ s5 commit type bars updated")

# ═══════════════════════════════════════════════════
# 4. Fix s5 branch info footer block
# ═══════════════════════════════════════════════════
old_branch_footer = '''<div style="margin-top:8px;font-size:11px;color:var(--muted)">
          <div style="margin-bottom:3px">🏷️ Tag: <strong style="color:#fff">emergency-fix-20260502</strong> — May 2, 2026</div>
          <div style="margin-bottom:3px">🌿 Active: <strong style="color:#4ade80">master</strong> (production) · <strong style="color:#a78bfa">genspark_ai_developer</strong> (dashboard AI)</div>
          <div style="margin-bottom:3px">🚀 Migrated: <strong style="color:#f59e0b">GitHub → GitLab</strong> · CI/CD pipeline · deploy to tsdnd/public_html tested</div>
          <div>🛒 Key: Magento 2.4.7-p3 · MariaDB pooling · SSH hardening · Checkout custom · Dashboard v4.3.0</div>
        </div>'''
new_branch_footer = '''<div style="margin-top:8px;font-size:11px;color:var(--muted)">
          <div style="margin-bottom:3px">🌿 Active: <strong style="color:#4ade80">master</strong> (prod) · <strong style="color:#f59e0b">production</strong> · <strong style="color:#22d3ee">dev</strong> · <strong style="color:#a78bfa">tsdnd</strong></div>
          <div style="margin-bottom:3px">🚀 CI/CD: <strong style="color:#f59e0b">GitLab Runner ded701</strong> · build → release artifact → SSH deploy</div>
          <div style="margin-bottom:3px">🧪 Tested deploy: <strong style="color:#4ade80">tsdnd.technostationery.com ✓</strong> (manual trigger, Magento release success)</div>
          <div>🛒 Stack: Magento 2.4.6-p15 · PHP 8.2 · MariaDB 10.6 · Elasticsearch · Custom checkout</div>
        </div>'''
content = content.replace(old_branch_footer, new_branch_footer)
print("✓ s5 branch footer updated")

# ═══════════════════════════════════════════════════
# 5. Fix s5 Chart.js — real monthly data from GitLab repo
# ═══════════════════════════════════════════════════
# Real data: Nov'25=35, Dec'25=8, Jan'26=31, Feb'26=80, Mar'26=25, Apr'26=290, May'26=9, Jun'26=10
old_chart = '''        labels: ['Nov\'24','Jan\'25','Mar\'26','Apr\'26','May\'26','Jun\'26','Jul\'26'],
        datasets: [{
          label: 'Commits',
          data: [2, 1, 2, 17, 56, 6, 10],
          backgroundColor: ['#1e3a8a','#1e3a8a','#1d4ed8','#6366f1','#ef4444','#22c55e','#3b82f6'],'''
new_chart = '''        labels: ['Nov\'25','Dec\'25','Jan\'26','Feb\'26','Mar\'26','Apr\'26','May\'26','Jun\'26','Jul\'26'],
        datasets: [{
          label: 'Commits',
          data: [35, 8, 31, 80, 25, 290, 9, 10, 5],
          backgroundColor: ['#1e3a8a','#172554','#1d4ed8','#ef4444','#2563eb','#7c3aed','#1d4ed8','#22c55e','#3b82f6'],'''
content = content.replace(old_chart, new_chart)
print("✓ s5 chart data updated with real GitLab data (Nov'25→Jul'26)")

# ═══════════════════════════════════════════════════
# 6. Fix s5 doughnut chart — real commit type distribution
# ═══════════════════════════════════════════════════
old_doughnut = '''        labels: ['feat (30)','fix (26)','docs (9)','chore (4)','other (24)'],
        datasets: [{ data: [30, 26, 9, 4, 24],'''
new_doughnut = '''        labels: ['fix (112)','docs (147)','feat (76)','perf (11)','chore/ci/other (135)'],
        datasets: [{ data: [112, 147, 76, 11, 135],'''
content = content.replace(old_doughnut, new_doughnut)
print("✓ s5 doughnut chart updated with real type distribution")

# ═══════════════════════════════════════════════════
# 7. Fix KPI slide s2 — git commits KPI
# ═══════════════════════════════════════════════════
# Find the git commits KPI card and update from 94 to 481
old_kpi_git = '<div class="kpi-val">94</div><div class="kpi-sub">Git commits · master</div>'
new_kpi_git = '<div class="kpi-val">481</div><div class="kpi-sub">Git commits · master branch</div>'
if old_kpi_git in content:
    content = content.replace(old_kpi_git, new_kpi_git)
    print("✓ s2 KPI git commits updated: 94 → 481")
else:
    # Try broader match
    import re
    m = re.search(r'(<div class="kpi-val">)94(</div><div class="kpi-sub">Git commits)', content)
    if m:
        content = content[:m.start()] + '<div class="kpi-val">481</div><div class="kpi-sub">Git commits' + content[m.end():]
        print("✓ s2 KPI git commits updated (broad match): 94 → 481")
    else:
        print("⚠ s2 KPI git commits not found — skipping")

# ═══════════════════════════════════════════════════
# 8. Fix Phase 1 section divider — commit count
# ═══════════════════════════════════════════════════
old_divider = '94 commits · GitLab CI/CD'
new_divider = '481 commits · GitLab CI/CD'
content = content.replace(old_divider, new_divider)
print("✓ Phase 1 divider updated: 94 → 481")

# ═══════════════════════════════════════════════════
# 9. Fix s36 H1 comparison — commits row
# Real data: H2 2025 = 43 commits, H1 2026 = 434 commits
# Growth: from 43 → 434 = +909%
# ═══════════════════════════════════════════════════
old_s36_commits = 'Git Commits (H1) 1 / 81 ▲+8000%'
new_s36_commits = 'Git Commits (H1) 43 / 434 ▲+909%'
content = content.replace(old_s36_commits, new_s36_commits)
print("✓ s36 commits row updated: H2 2025=43, H1 2026=434, +909%")

# ═══════════════════════════════════════════════════
# 10. Fix s36 KPI card for Git Commits
# ═══════════════════════════════════════════════════
old_s36_kpi = '<span style="color:#60a5fa">94</span>'
new_s36_kpi = '<span style="color:#60a5fa">481</span>'
# Be careful — only replace the one in s36 context
# Find it near "H1 Semester"
idx_s36 = content.find('id="s36"')
if idx_s36 > -1:
    # Replace in the s36 slice only (next 5000 chars after s36)
    s36_slice = content[idx_s36:idx_s36+6000]
    if old_s36_kpi in s36_slice:
        s36_new = s36_slice.replace(old_s36_kpi, new_s36_kpi, 1)
        content = content[:idx_s36] + s36_new + content[idx_s36+6000:]
        print("✓ s36 KPI git commits updated: 94 → 481")
    else:
        print("⚠ s36 KPI git commits not found in s36 slice")

# ═══════════════════════════════════════════════════
# 11. Fix s35 closing stats line — 94→481
# ═══════════════════════════════════════════════════
old_s35_stats = '38 slides · 8 phases · 94 git commits · 43 security findings · 91 ecomscan vulns'
new_s35_stats = '38 slides · 8 phases · 481 Magento git commits · 43 security findings · 91 ecomscan vulns'
content = content.replace(old_s35_stats, new_s35_stats)
print("✓ s35 closing stats updated: 94 → 481")

# ═══════════════════════════════════════════════════
# 12. Fix evidence matrix — 94→481
# ═══════════════════════════════════════════════════
old_evidence = '94 commits from single author (Mounir Abderrahmani)'
new_evidence = '481 commits on master · 480 by Mounir Abderrahmani (2 aliases: personal + dev@technostationery.com)'
content = content.replace(old_evidence, new_evidence)
print("✓ Evidence matrix updated: 94 → 481")

# ═══════════════════════════════════════════════════
# 13. Fix s5 speaker notes
# ═══════════════════════════════════════════════════
old_s5_notes = "s5:  'Git analysis: 94 total commits (master branch). Repo is the Magento+Dashboard platform. 3 branches. Migrated to GitLab with CI/CD pipeline. emergency-fix-20260502 tag. May 2026 peak: 56 commits (crisis + shipping fixes).'"
new_s5_notes = "s5:  'Real GitLab Magento repo audit: gitlab.com/technowebmaster-group/techno-magento. 481 total commits on master. Author: Mounir Abderrahmani (2 aliases). 6 branches. Feb 2026 peak: 80 commits (Feb 15 Amasty crisis, site down). Apr 2026 peak: 290 commits (checkout customization sprint). H2 2025=43, H1 2026=434 (+909%). CI/CD: GitLab Runner ded701, tsdnd test deploy successful.'"
content = content.replace(old_s5_notes, new_s5_notes)
print("✓ s5 speaker notes updated")

# ═══════════════════════════════════════════════════
# 14. Fix s36 notes
# ═══════════════════════════════════════════════════
# Update the H1 comparison note in speaker notes if present
old_s36_note = "s36: " # find dynamically
idx_s36_note = content.find("s36: '")
if idx_s36_note > -1:
    end_idx = content.find("',\n", idx_s36_note)
    if end_idx == -1:
        end_idx = content.find("'\n", idx_s36_note)
    old_note_full = content[idx_s36_note:end_idx+2]
    new_note_full = "s36: 'H1 Semester Comparison. GitLab Magento repo: H2 2025 = 43 commits, H1 2026 = 434 commits (+909%). Feb 2026 peak=80 (Amasty crisis), Apr 2026=290 (checkout sprint). Total users: H1 2025 baseline, H1 2026 = 8,521 (+75.8% incl 3,278 Mounir-converted)."
    content = content[:idx_s36_note] + new_note_full + "'" + content[end_idx+1:]
    print("✓ s36 notes updated")

# ═══════════════════════════════════════════════════
# 15. Fix Timeline slide s6 — update Dashboard v5 entry to reference GitLab repo
# ═══════════════════════════════════════════════════
old_tl_v5 = '<div class="tl-title">Dashboard v5 — Geo fix, Real-data Patches, Tunings Applied</div><div class="tl-detail">Algeria map 58-wilaya accurate lat/lon rewrite. Presentation 42-patch pass: real git/ecomscan/security data. SettingsPage v4.3.0-TSM (c0934e53). Server tuning corrections from audit reports.</div>'
new_tl_v5 = '<div class="tl-title">Dashboard v5 — GitLab Magento Audit, Geo Choropleth, Presentation Overhaul</div><div class="tl-detail">GitLab repo audit: 481 commits, 6 branches, CI/CD runner verified. Algeria map rebuilt as real polygon choropleth. Presentation real-data patches: commits 481, H1 2026=434 (+909%). s38 Dashboard monitoring slide added.</div>'
content = content.replace(old_tl_v5, new_tl_v5)
print("✓ Timeline s6 Dashboard v5 entry updated")

with open('/home/dashboard/public_html/presentation/index.html', 'w', encoding='utf-8') as f:
    f.write(content)

new_len = len(content)
print(f"\nOriginal: {original_len} chars")
print(f"Modified: {new_len} chars")
print(f"Delta: {new_len - original_len:+d} chars")
print("✓ Written successfully")
