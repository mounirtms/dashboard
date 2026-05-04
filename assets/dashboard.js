const MONITOR_API = '/api/monitor.php';
const DASH_API    = '/api/dashboard.php';
const AUTH_API    = '/api/auth.php';
let scriptData = {};

// Utility: Show toast notification
function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  const colors = {
    success: '#22c55e',
    error: '#ef4444',
    warning: '#eab308',
    info: '#3b82f6'
  };
  const bg = colors[type] || colors.info;
  toast.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 14px 24px;
    background: ${bg};
    color: ${type === 'warning' ? '#000' : '#fff'};
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
    z-index: 10000;
    animation: slideIn 0.3s ease-out;
    font-weight: 600;
    letter-spacing: 0.01em;
  `;
  document.body.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'slideOut 0.3s ease-out';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// Monkey-patch fetch to handle PHP shebang issue and empty responses
const originalFetch = window.fetch;
window.fetch = async function(...args) {
  try {
    const response = await originalFetch.apply(this, args);
    const originalJson = response.json.bind(response);
    response.json = async function() {
      try {
        const text = await response.text();
        if (!text || text.trim() === '') return {};
        // Handle case where PHP shebang or other text precedes JSON
        const firstBrace = text.indexOf('{');
        const firstBracket = text.indexOf('[');
        let start = -1;
        if (firstBrace !== -1 && (firstBracket === -1 || firstBrace < firstBracket)) start = firstBrace;
        else if (firstBracket !== -1) start = firstBracket;
        
        if (start === -1) return {};
        const jsonStr = text.substring(start).trim();
        // If multiple JSON objects are present, try to parse only the first one
        try {
            return JSON.parse(jsonStr);
        } catch(e) {
            // Try to find the last closing brace/bracket for valid JSON
            const lastBrace = jsonStr.lastIndexOf('}');
            const lastBracket = jsonStr.lastIndexOf(']');
            let end = -1;
            if (lastBrace !== -1 && (lastBracket === -1 || lastBrace > lastBracket)) end = lastBrace;
            else if (lastBracket !== -1) end = lastBracket;
            
            if (end !== -1) {
                return JSON.parse(jsonStr.substring(0, end + 1));
            }
            throw e;
        }
      } catch(e) {
        console.error('JSON parse error from ' + args[0], e);
        return {};
      }
    };
    return response;
  } catch(e) {
    console.error('Fetch error:', e);
    throw e;
  }
};

// ── Auth ──
async function checkAuth() {
  try {
    const r = await fetch(AUTH_API + '?action=check');
    const d = await r.json();
    if (!d.authenticated) { window.location.href = '/login.html'; return false; }
    if (d.user) {
      document.getElementById('user-badge').textContent = '👤 ' + (d.user.full_name || d.user.username);
    }
    return true;
  } catch(e) { window.location.href = '/login.html'; return false; }
}

// ── Tabs ──
function activateTab(tabName, pushHash = true) {
  if (!tabName) tabName = 'overview';
  document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(x => x.classList.remove('active'));
  const tabBtn = document.querySelector('.tab[data-tab="' + tabName + '"]');
  const tabContent = document.getElementById('tab-' + tabName);
  if (tabBtn) tabBtn.classList.add('active');
  if (tabContent) tabContent.classList.add('active');

  // Update hash for routing
  if (pushHash) {
    try { history.replaceState(null, '', '#/' + tabName); } catch(e) { location.hash = '#/' + tabName; }
  }

  // Lazy-load handlers
  if (tabName === 'scripts' && Object.keys(scriptData).length === 0) loadScripts();
  if (tabName === 'dbhealth') loadDbHealth();
  if (tabName === 'infrastructure') loadInfrastructure();
  if (tabName === 'commerce') { loadCommerce(); loadVisitorStats(); }
  if (tabName === 'telegram') { loadTelegramConfig(); loadAlerts(); }
  if (tabName === 'cloudflare') loadCloudflare();
  if (tabName === 'push') loadPushEnvironments();
  if (tabName === 'varnish-monitor') loadVarnishMonitor();
  if (tabName === 'pim-monitor') loadPimMonitor();
  if (tabName === 'cicd') loadCicdEnvironments();
}

// Attach click listeners that route via hash
document.querySelectorAll('.tab').forEach(t => {
  t.addEventListener('click', () => activateTab(t.dataset.tab, true));
});

// Activate from hash on load and on hashchange
function activateFromHash() {
  const h = location.hash || '';
  const m = h.match(/^#\/(.+)/);
  const tab = m ? m[1] : 'processes';
  activateTab(tab, false);
}
window.addEventListener('hashchange', activateFromHash);
window.addEventListener('DOMContentLoaded', activateFromHash);
// If script runs after DOMContentLoaded, ensure activation
if (document.readyState === 'complete' || document.readyState === 'interactive') activateFromHash();

// ── Visitor Stats ──
async function loadVisitorStats() {
  try {
    const r = await fetch('/api/visitors-count.php');
    if (!r.ok) return;
    const d = await r.json();
    
    const totalVisitors = d.all_sites?.total_visitors || 0;
    const onlineCustomers = d.all_sites?.online_customers || 0;
    
    // Update top metrics
    const totalEl = document.getElementById('total-visitors-metric');
    const onlineEl = document.getElementById('online-customers-metric');
    if (totalEl) totalEl.textContent = totalVisitors.toLocaleString();
    if (onlineEl) onlineEl.textContent = onlineCustomers.toLocaleString();
    
    const prodEl = document.getElementById('prod-visitors');
    const betaEl = document.getElementById('beta-visitors');
    if (prodEl) prodEl.textContent = 'Prod: ' + (d.production?.total_visitors || 0);
    if (betaEl) betaEl.textContent = 'Beta: ' + (d.beta?.total_visitors || 0);
    
    // Update commerce tab online customers
    const commerceOnline = document.getElementById('commerce-online');
    if (commerceOnline) commerceOnline.textContent = onlineCustomers;

    // Update compact header stats if present
    const headerVisitors = document.getElementById('header-visitors');
    const headerOnline = document.getElementById('header-online');
    if (headerVisitors) headerVisitors.textContent = (d.all_sites?.total_visitors || 0).toLocaleString();
    if (headerOnline) headerOnline.textContent = (d.all_sites?.online_customers || 0).toLocaleString();

  } catch(e) { 
    console.error('visitor stats', e); 
  }
}

// ── Overview ──
async function loadOverview() {
  try {
    const r = await fetch(MONITOR_API + '?action=overview');
    if (!r.ok) return;
    const d = await r.json();
    document.getElementById('last-update').textContent = 'Updated: ' + d.timestamp;
    const l = d.load['1min'];
    const dot = document.getElementById('status-dot');
    const txt = document.getElementById('status-text');
    if(l < 4) { dot.className='status-dot green'; txt.textContent='Normal'; }
    else if(l < 8) { dot.className='status-dot yellow'; txt.textContent='Elevated'; }
    else { dot.className='status-dot red'; txt.textContent='High Load: '+l.toFixed(2); }

    // Header quick stats
    const cpuEl = document.getElementById('header-cpu');
    const memEl = document.getElementById('header-mem');
    const swapEl = document.getElementById('header-swap');
    
    if (cpuEl) cpuEl.textContent = d.load['1min'].toFixed(2);
    if (memEl) memEl.textContent = d.memory.used_pct + '%';
    if (swapEl) swapEl.textContent = d.memory.swap_used_pct + '%';

    // Load visitor stats (will update header visitors/online)
    loadVisitorStats();

    const memClass = d.memory.used_pct > 85 ? 'red' : d.memory.used_pct > 65 ? 'yellow' : 'green';
    document.getElementById('metrics-row').innerHTML = `
      <div class="card">
        <h3>🌐 Current Visitors</h3>
        <div class="metric" id="total-visitors-metric">-</div>
        <div style="font-size:0.78rem;color:var(--muted);margin-top:5px">
          <span id="prod-visitors">Prod: -</span> | <span id="beta-visitors">Beta: -</span>
        </div>
      </div>
      <div class="card">
        <h3>👥 Online Customers</h3>
        <div class="metric" id="online-customers-metric">-</div>
        <div style="font-size:0.78rem;color:var(--muted);margin-top:5px">Active in last 15 min</div>
      </div>
      <div class="card">
        <h3>CPU Load (1m / 5m / 15m)</h3>
        <div class="metric">${d.load['1min'].toFixed(2)}</div>
        <div style="font-size:0.8rem;color:var(--muted)">${d.load['5min'].toFixed(2)} / ${d.load['15min'].toFixed(2)}</div>
      </div>
      <div class="card">
        <h3>Memory</h3>
        <div class="metric">${d.memory.used_pct}%</div>
        <div class="progress-bar"><div class="fill ${memClass}" style="width:${d.memory.used_pct}%"></div></div>
        <div style="font-size:0.78rem;color:var(--muted);margin-top:5px">${d.memory.available_mb} MB free / ${d.memory.total_mb} MB total${d.memory.swap_pct > 0 ? ' — Swap: '+d.memory.swap_pct+'%' : ''}</div>
      </div>
    `;

    // Populate dense metrics table for compact mode
    const denseTable = document.getElementById('metrics-dense-table');
    if (denseTable) {
      denseTable.querySelector('tbody').innerHTML = `
        <tr><td>Visitors</td><td>${d.all_sites?.total_visitors || 0}</td><td>Prod: ${d.production?.total_visitors || 0} • Beta: ${d.beta?.total_visitors || 0}</td></tr>
        <tr><td>Online</td><td>${d.all_sites?.online_customers || 0}</td><td>15 min window</td></tr>
        <tr><td>CPU 1m</td><td>${d.load['1min'].toFixed(2)}</td><td>5m: ${d.load['5min'].toFixed(2)}</td></tr>
        <tr><td>Memory</td><td>${d.memory.used_pct}%</td><td>${d.memory.available_mb} MB free</td></tr>
      `;
    }

    // Processes table
    let ph = '';
    (d.top_processes || []).forEach(p => {
      const c = p.cpu > 50 ? 'red' : p.cpu > 20 ? 'yellow' : 'green';
      ph += `<tr><td>${p.pid}</td><td><span class="badge ${c}">${p.cpu}%</span></td><td>${p.mem}%</td><td>${p.time}</td><td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.cmd}</td></tr>`;
    });
    // Fill both overview and detail processes tables
    const pOverview = document.querySelector('#top-proc-table-overview tbody');
    if (pOverview) pOverview.innerHTML = ph || '<tr><td colspan="5" class="loading">No processes</td></tr>';
    const pDetail = document.querySelector('#top-proc-table tbody');
    if (pDetail) pDetail.innerHTML = ph || '<tr><td colspan="5" class="loading">No processes</td></tr>';

    // Services
    let sh = '';
    Object.entries(d.services || {}).forEach(([k, v]) => {
      const cls = v === 'running' ? 'green' : v === 'dead' ? 'red' : 'yellow';
      sh += `<div class="svc-card"><span class="status-dot ${cls}" style="flex-shrink:0"></span><span class="svc-name">${k}</span><span class="badge ${cls}">${v}</span></div>`;
    });
    // Fill both overview and services views
    const svcOverview = document.getElementById('services-list-overview');
    if (svcOverview) svcOverview.innerHTML = sh;
    const svcList = document.getElementById('services-list');
    if (svcList) svcList.innerHTML = sh;

    // Footer summary with Varnish stats
    const f = document.getElementById('footer-stats');
    if (f) {
      const varnishInfo = d.varnish && d.varnish.status === 'active' 
        ? ` • Varnish ${d.varnish.hit_ratio}% hits`
        : '';
      f.innerHTML = `
        <div class="footer-stat">
          <span class="footer-stat-label">CPU</span>
          <span class="footer-stat-value">${d.load['1min'].toFixed(2)}</span>
        </div>
        <div class="footer-stat">
          <span class="footer-stat-label">Mem</span>
          <span class="footer-stat-value">${d.memory.used_pct}%</span>
        </div>
        ${d.varnish && d.varnish.status === 'active' ? `
          <div class="footer-stat">
            <span class="footer-stat-label">Varnish</span>
            <span class="footer-stat-value">${d.varnish.hit_ratio}%</span>
          </div>
        ` : ''}
      `;
    }

    // Add Varnish card to metrics if available
    if (d.varnish && d.varnish.status === 'active') {
      const card = `
        <div class="card">
          <h3>⚡ Varnish Cache</h3>
          <div class="metric">${d.varnish.hit_ratio}%</div>
          <div style="font-size:0.78rem;color:var(--muted);margin-top:5px">Hit Ratio • Storage: ${d.varnish.storage_pct}%</div>
        </div>
      `;
      document.getElementById('metrics-row').innerHTML += card;
    }

  } catch(e) { console.error('overview', e); }
}

async function loadSites() {
  try {
    const r = await fetch(MONITOR_API + '?action=sites');
    if (!r.ok) return;
    const d = await r.json();
    let h = '';
    Object.values(d.sites || {}).forEach(s => {
      h += `<tr>
        <td><strong>${s.name}</strong><br><span style="color:var(--muted);font-size:0.68rem">${s.path}</span></td>
        <td>${s.user}</td><td>${s.php_fpm_workers}</td>
        <td>${s.disk_usage}</td><td>${s.db_size || '—'}</td>
        <td>${s.magento_mode || '—'}</td><td>${s.cache_status || '—'}</td>
        <td><span class="badge ${s.exists ? 'green' : 'red'}">${s.exists ? 'Active' : 'Missing'}</span></td>
      </tr>`;
    });
    document.querySelector('#sites-table tbody').innerHTML = h || '<tr><td colspan="8" class="loading">No sites</td></tr>';
  } catch(e) { console.error('sites', e); }
}

async function loadCrons() {
  try {
    const r = await fetch(MONITOR_API + '?action=crons');
    if (!r.ok) return;
    const d = await r.json();
    document.getElementById('cron-count').textContent = d.total || 0;
    let h = '';
    (d.entries || []).forEach(e => {
      h += `<div style="padding:7px 0;border-bottom:1px solid rgba(30,41,59,0.5)">
        <div style="font-family:monospace;font-size:0.75rem;color:var(--cyan)">${e.schedule}</div>
        <div style="font-size:0.8rem;margin-top:2px">${(e.command||'').substring(0,130)}${(e.command||'').length>130?'...':''}</div>
        ${e.running > 0 ? `<span class="badge yellow" style="margin-top:3px">Running (${e.running})</span>` : ''}
        ${e.comment ? `<div style="font-size:0.7rem;color:var(--muted)">${e.comment}</div>` : ''}
      </div>`;
    });
    document.getElementById('cron-list').innerHTML = h || '<div class="loading">No cron jobs</div>';
  } catch(e) { console.error('crons', e); }
}

async function loadQueues() {
  try {
    const r = await fetch(MONITOR_API + '?action=queues');
    if (!r.ok) return;
    const d = await r.json();
    let ch = (d.consumers || []).map(c => `<div style="padding:4px 0;font-size:0.82rem">📋 ${c}</div>`).join('');
    document.getElementById('consumer-list').innerHTML = ch || '<div class="loading">No consumers</div>';
    let qh = Object.entries(d.queue_counts || {}).map(([q, n]) => {
      const cls = n > 100 ? 'red' : n > 10 ? 'yellow' : 'green';
      return `<div style="padding:4px 0;font-size:0.82rem">${q}: <span class="badge ${cls}">${n}</span></div>`;
    }).join('');
    document.getElementById('queue-pending').innerHTML = qh || '<div style="font-size:0.82rem;color:var(--green)">✓ No pending messages</div>';
  } catch(e) { console.error('queues', e); }
}

async function loadIndexers(env = 'prod') {
  try {
    const r = await fetch(MONITOR_API + '?action=indexer&env=' + env);
    if (!r.ok) return;
    const d = await r.json();
    let h = (d.indexers || []).map(i => {
      const cls = i.status.toLowerCase().includes('ready') ? 'green' : 'yellow';
      return `<tr><td><strong>${i.name}</strong></td><td>${i.title}</td><td><span class="badge ${cls}">${i.status}</span></td></tr>`;
    }).join('');
    document.querySelector('#indexer-table tbody').innerHTML = h || '<tr><td colspan="3" class="loading">No indexers found</td></tr>';
  } catch(e) { console.error('indexers', e); }
}

// ── Scripts ──
async function loadScripts() {
  document.getElementById('script-list').innerHTML = '<div class="loading">Loading scripts...</div>';
  try {
    const r = await fetch(DASH_API + '?action=scripts');
    if (!r.ok) return;
    const d = await r.json();
    scriptData = d.scripts || {};
    renderScriptCats(Object.keys(scriptData)[0]);
  } catch(e) {
    document.getElementById('script-list').innerHTML = '<div class="loading">Error loading scripts</div>';
  }
}

function renderScriptCats(activeCat) {
  const cats = Object.keys(scriptData);
  if (!cats.length) { document.getElementById('script-list').innerHTML = '<div class="loading">No scripts found</div>'; return; }
  if (!activeCat || !scriptData[activeCat]) activeCat = cats[0];
  document.getElementById('script-cat-tabs').innerHTML = cats.map(c =>
    `<div class="script-cat-tab ${c === activeCat ? 'active' : ''}" onclick="renderScriptCats('${c}')">${c} <span style="color:var(--muted);font-size:0.7rem">(${(scriptData[c]||[]).length})</span></div>`
  ).join('');
  const scripts = scriptData[activeCat] || [];
  if (!scripts.length) { document.getElementById('script-list').innerHTML = '<div class="loading">No scripts in this category</div>'; return; }
  document.getElementById('script-list').innerHTML = scripts.map(s => `
    <div class="script-row">
      <div>
        <div class="s-name">${s.name}</div>
        <div class="s-meta">Modified: ${s.modified} &nbsp;|&nbsp; Size: ${s.size}b &nbsp;|&nbsp; <span class="badge ${s.ext === 'php' ? 'blue' : 'green'}">${s.ext}</span></div>
      </div>
      <button class="btn btn-sm btn-primary" onclick="runScript('${activeCat}','${s.name}')">▶ Run</button>
    </div>
  `).join('');
}

async function runScript(category, name) {
  const modal = document.getElementById('output-modal');
  const output = document.getElementById('modal-output');
  document.getElementById('modal-title').textContent = '▶ ' + name;
  output.textContent = 'Running...';
  modal.classList.add('show');
  try {
    const r = await fetch(DASH_API + '?action=run&category=' + encodeURIComponent(category) + '&script=' + encodeURIComponent(name));
    const d = await r.json();
    output.textContent = 'Exit Code: ' + (d.result?.exit_code ?? '?') + '\n\n' + (d.result?.output || d.error || 'No output');
  } catch(e) { output.textContent = 'Error: ' + e.message; }
}

async function quickRun(category, name) {
  const out = document.getElementById('quick-output');
  out.style.display = 'block';
  out.textContent = 'Running ' + name + '...';
  try {
    const r = await fetch(DASH_API + '?action=run&category=' + encodeURIComponent(category) + '&script=' + encodeURIComponent(name));
    const d = await r.json();
    out.textContent = 'Exit: ' + (d.result?.exit_code ?? '?') + '\n' + (d.result?.output || d.error || '');
  } catch(e) { out.textContent = 'Error: ' + e.message; }
}

async function doCleanup(type) {
  const out = document.getElementById('action-output');
  out.style.display = 'block';
  out.textContent = 'Executing ' + type + '...';
  try {
    const r = await fetch(MONITOR_API + '?action=cleanup&type=' + type);
    const d = await r.json();
    out.textContent = JSON.stringify(d, null, 2);
    setTimeout(loadOverview, 3000);
  } catch(e) { out.textContent = 'Error: ' + e.message; }
}

async function loadDbStats() {
  try {
    const r = await fetch(DASH_API + '?action=database&env=prod');
    const d = await r.json();
    if (d.success && d.data) {
      document.getElementById('db-stats').innerHTML =
        `<strong>Production:</strong> ${d.data.size_mb} MB &nbsp;|&nbsp; ${d.data.table_count} tables &nbsp;|&nbsp; ${d.data.connections} connections`;
    }
  } catch(e) {}
}

function toggleCompact() {
  const isCompact = document.body.classList.toggle('compact');
  try { localStorage.setItem('dashboard_compact', isCompact ? '1' : '0'); } catch(e){}
  document.getElementById('compact-toggle').textContent = isCompact ? 'Compact ✓' : 'Compact';
}

// Apply persisted compact mode
try { if (localStorage.getItem('dashboard_compact') === '1') { document.body.classList.add('compact'); document.getElementById('compact-toggle').textContent = 'Compact ✓'; } } catch(e){}

// Header menu toggle
window.addEventListener('DOMContentLoaded', function(){
  const headerMenuBtn = document.getElementById('header-menu-btn');
  const headerMenu = document.getElementById('header-menu');
  if (headerMenuBtn && headerMenu) {
    headerMenuBtn.addEventListener('click', (e) => { e.stopPropagation(); headerMenu.classList.toggle('show'); });
    document.addEventListener('click', () => headerMenu.classList.remove('show'));
    headerMenu.addEventListener('click', e => e.stopPropagation());
  }
});

async function doLogout() {
  try { await fetch(AUTH_API + '?action=logout', { method: 'POST' }); } catch(e) {}
  window.location.href = '/login.html';
}

// ── Telegram Notifications ──
const TELEGRAM_TEST_API = '/api/telegram-test.php';
const TELEGRAM_USERS_API = '/api/telegram-users.php';

async function loadTelegramConfig() {
  try {
    // Load authorized users
    const r = await fetch(TELEGRAM_USERS_API + '?action=list');
    if (r.ok) {
      const d = await r.json();
      document.getElementById('bot-auth-count').textContent = d.count || 0;
      
      if (d.users && d.users.length > 0) {
        const html = d.users.map(u => `
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(30,41,59,0.4)">
            <div>
              <strong style="color:var(--text)">${u.name}</strong>
              <code style="margin-left:10px;color:var(--muted)">${u.chat_id}</code>
            </div>
            <button class="btn btn-danger btn-sm" onclick="removeTelegramUser(${u.chat_id})">🗑️ Remove</button>
          </div>
        `).join('');
        document.getElementById('authorized-users-list').innerHTML = html;
      } else {
        document.getElementById('authorized-users-list').innerHTML = '<p style="color:var(--muted)">No authorized users</p>';
      }
    }

    // Load logs
    const r2 = await fetch(TELEGRAM_USERS_API + '?action=logs&limit=30');
    if (r2.ok) {
      const d = await r2.json();
      if (d.logs && d.logs.length > 0) {
        const html = d.logs.slice(0, 30).map(log => {
          const statusColor = log.status === 'received' || log.status === 'executed' ? 'var(--green)' : 
                             log.status === 'error' ? 'var(--red)' : 'var(--yellow)';
          return `<div style="font-size:0.72rem;color:var(--muted);padding:2px 0">
            <span style="color:var(--cyan)">${log.timestamp}</span> | 
            <span style="color:${statusColor}">${log.status}</span> | 
            ${log.user} → ${log.command}
          </div>`;
        }).join('');
        document.getElementById('telegram-logs').innerHTML = html;
      } else {
        document.getElementById('telegram-logs').innerHTML = '<p>No activity yet</p>';
      }
    }
  } catch(e) {
    console.error('Failed to load Telegram config:', e);
  }
}

async function addTelegramUser() {
  const chatId = document.getElementById('tg-chat-id-input').value.trim();
  const name = document.getElementById('tg-user-name-input').value.trim();

  if (!chatId) {
    alert('Please enter a Chat ID');
    return;
  }

  const formData = new FormData();
  formData.append('chat_id', chatId);
  formData.append('name', name || 'Unknown');

  try {
    const r = await fetch(TELEGRAM_USERS_API + '?action=add', {
      method: 'POST',
      body: formData
    });
    const d = await r.json();

    if (d.success) {
      alert('✅ ' + d.message);
      document.getElementById('tg-chat-id-input').value = '';
      document.getElementById('tg-user-name-input').value = '';
      loadTelegramConfig();
    } else {
      alert('❌ ' + d.message);
    }
  } catch(e) {
    alert('❌ Error: ' + e.message);
  }
}

async function removeTelegramUser(chatId) {
  if (!confirm('Remove this user from authorized list?')) return;

  const formData = new FormData();
  formData.append('chat_id', chatId);

  try {
    const r = await fetch(TELEGRAM_USERS_API + '?action=remove', {
      method: 'POST',
      body: formData
    });
    const d = await r.json();

    if (d.success) {
      alert('✅ ' + d.message);
      loadTelegramConfig();
    } else {
      alert('❌ ' + d.message);
    }
  } catch(e) {
    alert('❌ Error: ' + e.message);
  }
}

async function testTelegram() {
  const out = document.getElementById('tg-output');
  out.style.display = 'block';
  out.textContent = 'Sending test message...';
  
  try {
    const r = await fetch(TELEGRAM_TEST_API + '?action=test');
    const d = await r.json();
    
    if (d.success) {
      out.style.color = 'var(--green)';
      out.textContent = '✅ Test message sent successfully! Check your Telegram.';
    } else {
      out.style.color = 'var(--red)';
      out.textContent = '❌ Failed: ' + d.message;
    }
  } catch(e) {
    out.style.color = 'var(--red)';
    out.textContent = '❌ Error: ' + e.message;
  }
}

// ── Customer Bot Functions ──
async function testCustomerBot() {
  const output = document.getElementById('tg-output');
  output.style.display = 'block';
  output.textContent = 'Testing customer bot...';
  
  try {
    const r = await fetch('/api/telegram/customer_test.php?action=test');
    const d = await r.json();
    
    if (d.success) {
      output.style.color = 'var(--green)';
      output.textContent = '✅ Customer bot is working!\nBot: ' + (d.bot_name || 'Unknown');
    } else {
      output.style.color = 'var(--red)';
      output.textContent = '❌ Failed: ' + (d.error || 'Unknown error');
    }
  } catch(e) {
    output.style.color = 'var(--red)';
    output.textContent = '❌ Error: ' + e.message;
  }
}

async function enableCustomerBot() {
  if (!confirm('Enable customer bot? Make sure you have added the bot token to config.php first.')) return;
  
  const output = document.getElementById('tg-output');
  output.style.display = 'block';
  output.textContent = 'Enabling customer bot...';
  
  try {
    const r = await fetch('/api/telegram/customer_test.php?action=enable', {
      method: 'POST'
    });
    const d = await r.json();
    
    if (d.success) {
      output.style.color = 'var(--green)';
      output.textContent = '✅ Customer bot enabled!\n\nNext steps:\n1. Add cron job: */1 * * * * php /home/dashboard/public_html/api/telegram/customer_poller.php\n2. Test the bot via Telegram';
      document.getElementById('customer-bot-enabled').textContent = 'Enabled';
      document.getElementById('customer-bot-dot').className = 'status-dot green';
    } else {
      output.style.color = 'var(--red)';
      output.textContent = '❌ Failed: ' + (d.error || 'Unknown error');
    }
  } catch(e) {
    output.style.color = 'var(--red)';
    output.textContent = '❌ Error: ' + e.message;
  }
}

// ── AI Report Functions ──
async function generateAIReport() {
  const reportType = document.getElementById('ai-report-type').value;
  const env = document.getElementById('ai-env').value;
  const output = document.getElementById('ai-report-output');
  
  output.style.display = 'block';
  output.textContent = `⏳ Generating AI ${reportType} report for ${env}...\n(This may take 1-2 minutes)`;
  output.style.color = 'var(--cyan)';
  
  try {
    const formData = new FormData();
    formData.append('action', 'ai_report');
    formData.append('type', reportType);
    formData.append('env', env);
    
    const r = await fetch('/api/telegram/utils/qodercli_api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!r.ok) {
      throw new Error('API request failed');
    }
    
    const d = await r.json();
    
    if (d.success) {
      output.style.color = 'var(--text)';
      output.style.whiteSpace = 'pre-wrap';
      output.textContent = d.report || 'No report generated';
    } else {
      output.style.color = 'var(--red)';
      output.textContent = '❌ Error: ' + (d.error || 'Unknown error');
    }
  } catch(e) {
    output.style.color = 'var(--red)';
    output.textContent = '❌ Error: ' + e.message;
  }
}

async function loadAICacheStats() {
  const output = document.getElementById('ai-cache-output');
  output.style.display = 'block';
  output.textContent = 'Loading cache stats...';
  
  try {
    const r = await fetch('/api/telegram/utils/qodercli_api.php?action=cache_stats');
    const d = await r.json();
    
    if (d.success) {
      output.style.color = 'var(--text)';
      output.innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:8px">
          <div><div style="font-size:0.72rem;color:var(--muted)">TOTAL REPORTS</div><div style="font-size:1.2rem;font-weight:700">${d.stats.total || 0}</div></div>
          <div><div style="font-size:0.72rem;color:var(--muted)">CACHE SIZE</div><div style="font-size:1.2rem;font-weight:700">${(d.stats.size / 1024).toFixed(1)} KB</div></div>
          <div><div style="font-size:0.72rem;color:var(--muted)">CACHE DIR</div><div style="font-size:0.8rem;font-family:monospace">/api/telegram/data/ai_cache/</div></div>
        </div>
      `;
    } else {
      output.style.color = 'var(--red)';
      output.textContent = '❌ Error: ' + (d.error || 'Unknown error');
    }
  } catch(e) {
    output.style.color = 'var(--red)';
    output.textContent = '❌ Error: ' + e.message;
  }
}

async function clearAICache() {
  if (!confirm('Clear all AI report cache?')) return;
  
  const output = document.getElementById('ai-cache-output');
  output.style.display = 'block';
  output.textContent = 'Clearing cache...';
  
  try {
    const r = await fetch('/api/telegram/utils/qodercli_api.php?action=clear_cache', {
      method: 'POST'
    });
    const d = await r.json();
    
    if (d.success) {
      output.style.color = 'var(--green)';
      output.textContent = '✅ Cache cleared successfully';
      // Reload stats if visible
      if (output.innerHTML) {
        loadAICacheStats();
      }
    } else {
      output.style.color = 'var(--red)';
      output.textContent = '❌ Error: ' + (d.error || 'Failed to clear cache');
    }
  } catch(e) {
    output.style.color = 'var(--red)';
    output.textContent = '❌ Error: ' + e.message;
  }
}

async function executeCacheAction(action, env) {
  const output = document.getElementById('cache-execution-output');
  output.style.display = 'block';
  output.style.color = 'var(--cyan)';
  output.textContent = `⏳ Executing cache:${action} for ${env}...\n(This may take 30-120 seconds)`;
  
  try {
    const formData = new FormData();
    formData.append('action', 'cache_' + action);
    formData.append('env', env);
    
    const r = await fetch('/api/telegram/utils/cache_api.php', {
      method: 'POST',
      body: formData
    });
    
    if (!r.ok) {
      throw new Error('API request failed');
    }
    
    const d = await r.json();
    
    if (d.success) {
      output.style.color = 'var(--green)';
      output.style.whiteSpace = 'pre-wrap';
      output.textContent = '✅ Success:\n\n' + (d.output || 'No output');
    } else {
      output.style.color = 'var(--red)';
      output.textContent = '❌ Error: ' + (d.error || 'Unknown error');
    }
  } catch(e) {
    output.style.color = 'var(--red)';
    output.textContent = '❌ Error: ' + e.message;
  }
}

// Load Telegram config when tab is activated
document.querySelector('[data-tab="telegram"]')?.addEventListener('click', () => {
  loadTelegramConfig();
});


async function loadDbHealth() {
  try {
    const r = await fetch(MONITOR_API + '?action=dbhealth');
    if (!r.ok) return;
    const d = await r.json();
    ['prod','beta'].forEach(env => {
      const db = d.databases[env];
      if (!db || db.error) {
        document.getElementById('dbh-'+env+'-stats').innerHTML = '<span class="badge red">Error</span>';
        return;
      }
      const fragPct = db.size_mb > 0 ? ((db.frag_mb / db.size_mb)*100).toFixed(1) : 0;
      const fragCls = fragPct > 15 ? 'red' : fragPct > 5 ? 'yellow' : 'green';
      document.getElementById('dbh-'+env+'-stats').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px">
          <div><div style="font-size:0.72rem;color:var(--muted)">SIZE</div><div style="font-size:1.4rem;font-weight:700">${db.size_mb} MB</div></div>
          <div><div style="font-size:0.72rem;color:var(--muted)">FRAGMENTATION</div><div style="font-size:1.4rem;font-weight:700"><span style="color:var(--${fragCls === 'red' ? 'red' : fragCls === 'yellow' ? 'yellow' : 'green'})">${db.frag_mb} MB</span></div></div>
          <div><div style="font-size:0.72rem;color:var(--muted)">CONNECTIONS</div><div style="font-size:1.1rem;font-weight:600">${db.connections} <span style="font-size:0.75rem;color:var(--muted)">(${db.running} running)</span></div></div>
          <div><div style="font-size:0.72rem;color:var(--muted)">SLOW QUERIES</div><div style="font-size:1.1rem;font-weight:600"><span class="badge ${db.slow_queries > 100 ? 'red' : db.slow_queries > 10 ? 'yellow' : 'green'}">${db.slow_queries}</span></div></div>
        </div>`;
    });
    // Fragmented tables
    const frags = d.databases.prod?.fragmented_tables || [];
    if (frags.length === 0) {
      document.querySelector('#frag-table tbody').innerHTML = '<tr><td colspan="5" style="color:var(--green)">✓ No significantly fragmented tables</td></tr>';
    } else {
      document.querySelector('#frag-table tbody').innerHTML = frags.map(t => {
        const fp = t.size_mb > 0 ? ((t.frag_mb / t.size_mb)*100).toFixed(1) : 0;
        const cls = fp > 20 ? 'red' : fp > 10 ? 'yellow' : 'green';
        return `<tr><td><strong>${t.table_name}</strong></td><td>${t.size_mb} MB</td><td><span class="badge ${cls}">${t.frag_mb} MB</span></td><td>${Number(t.table_rows).toLocaleString()}</td><td><span class="badge ${cls}">${fp}%</span></td></tr>`;
      }).join('');
    }
  } catch(e) { console.error('dbhealth', e); }
}

async function runOptimize() {
  const out = document.getElementById('optimize-output');
  out.style.display = 'block';
  out.textContent = 'Running OPTIMIZE on fragmented tables... (this may take a minute)';
  try {
    const r = await fetch(DASH_API + '?action=run&category=database&script=cleanup_database.php');
    const d = await r.json();
    out.textContent = 'Exit: ' + (d.result?.exit_code ?? '?') + '\n' + (d.result?.output || d.error || '');
    setTimeout(loadDbHealth, 3000);
  } catch(e) { out.textContent = 'Error: ' + e.message; }
}

function refresh() {
  loadOverview();
  loadSites();
  loadCrons();
  loadQueues();
  loadIndexers();
  loadDbStats();
  loadInfrastructure();
  loadCommerce();
}

// ── Infrastructure Tab ──
async function loadInfrastructure() {
  try {
    const [redisR, esR, varnishR, varnishStatsR, sysR, phpfpmR, cfR] = await Promise.all([
      fetch(MONITOR_API + '?action=redis'),
      fetch(MONITOR_API + '?action=elasticsearch'),
      fetch(MONITOR_API + '?action=varnish'),
      fetch('/api/varnish-stats.php'),
      fetch(MONITOR_API + '?action=system_advanced'),
      fetch(MONITOR_API + '?action=phpfpm_pools'),
      fetch(MONITOR_API + '?action=cloudflare')
    ]);

    const redis = redisR.ok ? await redisR.json() : null;
    const es = esR.ok ? await esR.json() : null;
    const varnish = varnishR.ok ? await varnishR.json() : null;
    const varnishStats = varnishStatsR.ok ? await varnishStatsR.json() : null;
    const sys = sysR.ok ? await sysR.json() : null;
    const phpfpm = phpfpmR.ok ? await phpfpmR.json() : null;
    const cf = cfR.ok ? await cfR.json() : null;

    renderRedis(redis);
    renderElasticsearch(es);
    renderVarnish(varnish, varnishStats);
    renderSystemAdvanced(sys);
    renderPhpFPM(phpfpm);
    renderCloudflare(cf);
  } catch(e) { console.error('infrastructure', e); }
}

function renderRedis(data) {
  const el = document.getElementById('redis-content');
  if (!data || data.error) {
    el.innerHTML = '<div class="loading">Redis unavailable</div>';
    return;
  }

  const memPct = data.memory.maxmemory > 0 ? ((data.memory.used_bytes / data.memory.maxmemory) * 100).toFixed(1) : 0;
  const memClass = memPct > 90 ? 'red' : memPct > 70 ? 'yellow' : 'green';
  const hitRate = data.stats.hit_rate.toFixed(1);
  const hitClass = hitRate > 80 ? 'green' : hitRate > 50 ? 'yellow' : 'red';

  let html = `
    <div class="infra-stat"><span class="infra-label">Memory</span><span class="infra-val">${(data.memory.used_mb || 0)} MB / ${(data.memory.maxmemory_mb || 0)} MB</span></div>
    <div class="progress-bar"><div class="fill ${memClass}" style="width:${Math.min(memPct, 100)}%"></div></div>
    <div class="infra-stat"><span class="infra-label">Hit Rate</span><span class="infra-val ${hitClass}">${hitRate}%</span></div>
    <div class="infra-stat"><span class="infra-label">Ops/sec</span><span class="infra-val">${(data.stats.ops_per_sec || 0).toLocaleString()}</span></div>
    <div class="infra-stat"><span class="infra-label">Keys</span><span class="infra-val">${(data.keyspace.total_keys || 0).toLocaleString()}</span></div>
    <div class="infra-stat"><span class="infra-label">Clients</span><span class="infra-val">${data.stats.connected_clients || 0}</span></div>
    <div class="infra-stat"><span class="infra-label">Evictions</span><span class="infra-val">${(data.stats.evicted_keys || 0).toLocaleString()}</span></div>
  `;

  if (data.keyspace.databases && Object.keys(data.keyspace.databases).length > 0) {
    html += '<div style="margin-top:10px;font-size:0.78rem;color:var(--muted)">DB Breakdown:</div><table style="margin-top:4px"><thead><tr><th>DB</th><th>Keys</th><th>Expires</th></tr></thead><tbody>';
    Object.entries(data.keyspace.databases).forEach(([db, info]) => {
      html += `<tr><td>${db}</td><td>${info.keys}</td><td>${info.expires || 0}</td></tr>`;
    });
    html += '</tbody></table>';
  }

  el.innerHTML = html;
}

function renderElasticsearch(data) {
  const el = document.getElementById('elasticsearch-content');
  if (!data || data.error) {
    el.innerHTML = '<div class="loading">Elasticsearch unavailable</div>';
    return;
  }

  const status = data.cluster.status || 'unknown';
  const statusClass = status === 'green' ? 'green' : status === 'yellow' ? 'yellow' : 'red';
  const jvmPct = data.nodes.jvm_heap_pct || 0;
  const jvmClass = jvmPct > 85 ? 'red' : jvmPct > 70 ? 'yellow' : 'green';

  let html = `
    <div class="infra-stat"><span class="infra-label">Cluster Status</span><span class="badge ${statusClass}">${status.toUpperCase()}</span></div>
    <div class="infra-stat"><span class="infra-label">Nodes</span><span class="infra-val">${data.cluster.number_of_nodes || 0}</span></div>
    <div class="infra-stat"><span class="infra-label">Shards</span><span class="infra-val">${data.cluster.active_shards || 0} (unassigned: ${data.cluster.unassigned_shards || 0})</span></div>
    <div class="infra-stat"><span class="infra-label">JVM Heap</span><span class="infra-val ${jvmClass}">${jvmPct}% of ${(data.nodes.jvm_heap_max_mb || 0)} MB</span></div>
    <div class="progress-bar"><div class="fill ${jvmClass}" style="width:${Math.min(jvmPct, 100)}%"></div></div>
    <div class="infra-stat"><span class="infra-label">GC Count</span><span class="infra-val">${(data.nodes.gc_count || 0).toLocaleString()}</span></div>
  `;

  if (data.indices && data.indices.length > 0) {
    html += '<div style="margin-top:10px;font-size:0.78rem;color:var(--muted)">Top Indices:</div><table style="margin-top:4px"><thead><tr><th>Index</th><th>Docs</th><th>Size</th></tr></thead><tbody>';
    data.indices.slice(0, 8).forEach(idx => {
      html += `<tr><td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${idx.index}</td><td>${Number(idx.docs_count || 0).toLocaleString()}</td><td>${idx.store_size || '0b'}</td></tr>`;
    });
    html += '</tbody></table>';
  }

  el.innerHTML = html;
}

function renderVarnish(data, varnishStats) {
  const el = document.getElementById('varnish-content');
  
  // Use enhanced stats API if available, fallback to monitor API
  const stats = varnishStats?.varnish || data;
  
  if (!stats || stats.error || data?.error) {
    el.innerHTML = '<div class="loading">Varnish unavailable</div>';
    return;
  }

  const hitRate = stats.hit_rate || stats.hit_ratio || 0;
  const hitClass = hitRate > 80 ? 'green' : hitRate > 50 ? 'yellow' : 'red';
  
  const hits = stats.cache_hits || stats.hits || 0;
  const misses = stats.cache_misses || stats.misses || 0;
  const objects = stats.cached_objects || 0;
  
  // Storage metrics
  const storage = stats.storage || data?.storage || {};
  const usedMB = storage.used_mb || 0;
  const availableMB = storage.available_mb || 0;
  const totalMB = usedMB + availableMB;
  const storagePct = totalMB > 0 ? ((usedMB / totalMB) * 100).toFixed(1) : 0;
  const storageClass = storagePct > 90 ? 'red' : storagePct > 70 ? 'yellow' : 'green';

  let html = `
    <div class="infra-stat"><span class="infra-label">Service Status</span><span class="badge ${stats.service_status === 'running' ? 'green' : 'red'}">${stats.service_status || 'unknown'}</span></div>
    <div class="infra-stat"><span class="infra-label">Hit Ratio</span><span class="infra-val ${hitClass}">${hitRate.toFixed(1)}%</span></div>
    <div class="progress-bar"><div class="fill ${hitClass}" style="width:${Math.min(hitRate, 100)}%"></div></div>
    <div class="infra-stat"><span class="infra-label">Cache Hits</span><span class="infra-val green">${hits.toLocaleString()}</span></div>
    <div class="infra-stat"><span class="infra-label">Cache Misses</span><span class="infra-val yellow">${misses.toLocaleString()}</span></div>
    <div class="infra-stat"><span class="infra-label">Cached Objects</span><span class="infra-val cyan">${objects.toLocaleString()}</span></div>
    <div class="infra-stat"><span class="infra-label">Storage Used</span><span class="infra-val">${usedMB.toFixed(1)} MB / ${totalMB.toFixed(1)} MB</span></div>
    <div class="progress-bar"><div class="fill ${storageClass}" style="width:${Math.min(storagePct, 100)}%"></div></div>
    <div class="infra-stat"><span class="infra-label">Backend Failures</span><span class="infra-val ${(stats.backend_fail || stats.backend_failures || 0) > 0 ? 'red' : 'green'}">${stats.backend_fail || stats.backend_failures || 0}</span></div>
  `;

  // Device type breakdown from enhanced API
  if (varnishStats?.devices) {
    const dt = varnishStats.devices;
    const total = parseInt(dt.total) || 0;
    html += `
      <div style="grid-column:1/-1;margin-top:8px;border-top:1px solid rgba(255,255,255,0.08);padding-top:8px">
        <div style="font-size:0.78rem;color:var(--muted);margin-bottom:6px">📱 Device Types (${total.toLocaleString()} tracked)</div>
        <div class="infra-stat"><span class="infra-label">📱 Mobile</span><span class="infra-val cyan">${(dt.mobile?.percentage || 0).toFixed(1)}% <span style="font-size:0.7rem;color:var(--muted)">(${(dt.mobile?.count || 0).toLocaleString()})</span></span></div>
        <div class="infra-stat"><span class="infra-label">📟 Tablet</span><span class="infra-val yellow">${(dt.tablet?.percentage || 0).toFixed(1)}% <span style="font-size:0.7rem;color:var(--muted)">(${(dt.tablet?.count || 0).toLocaleString()})</span></span></div>
        <div class="infra-stat"><span class="infra-label">🖥️ Desktop</span><span class="infra-val green">${(dt.desktop?.percentage || 0).toFixed(1)}% <span style="font-size:0.7rem;color:var(--muted)">(${(dt.desktop?.count || 0).toLocaleString()})</span></span></div>
      </div>
    `;
  } else if (data?.device_types) {
    // Fallback to old device types format
    const dt = data.device_types;
    html += `
      <div style="grid-column:1/-1;margin-top:8px;border-top:1px solid rgba(255,255,255,0.08);padding-top:8px">
        <div style="font-size:0.78rem;color:var(--muted);margin-bottom:6px">📱 Device Types (last ${data.total_device_requests || 0} requests)</div>
        <div class="infra-stat"><span class="infra-label">📱 Mobile</span><span class="infra-val cyan">${dt.mobile?.percentage || 0}% <span style="font-size:0.7rem;color:var(--muted)">(${(dt.mobile?.count || 0).toLocaleString()})</span></span></div>
        <div class="infra-stat"><span class="infra-label">📟 Tablet</span><span class="infra-val yellow">${dt.tablet?.percentage || 0}% <span style="font-size:0.7rem;color:var(--muted)">(${(dt.tablet?.count || 0).toLocaleString()})</span></span></div>
        <div class="infra-stat"><span class="infra-label">🖥️ Desktop</span><span class="infra-val green">${dt.desktop?.percentage || 0}% <span style="font-size:0.7rem;color:var(--muted)">(${(dt.desktop?.count || 0).toLocaleString()})</span></span></div>
      </div>
    `;
  }

  el.innerHTML = html;
}

async function cfAction(action, params = {}) {
  try {
    const formData = new URLSearchParams({ action2: action, ...params });
    const res = await fetch(MONITOR_API + '?action=cloudflare_action', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      loadInfrastructure();
    } else {
      showToast(data.message || 'Action failed', 'error');
    }
  } catch(e) {
    showToast('Cloudflare action failed: ' + e.message, 'error');
  }
}

function renderCloudflare(data) {
  const el = document.getElementById('cloudflare-content');
  if (!data || data.error) {
    el.innerHTML = '<div class="loading">Cloudflare unavailable</div>';
    return;
  }

  const z = data.zone || {};
  const s = data.settings || {};
  const a = data.analytics_totals || {};
  const fw = data.firewall || {};
  const days = data.analytics || [];
  const hours = data.hourly_analytics || [];
  const countries = data.countries || [];
  const statusCodes = data.status_codes || [];
  const topUrls = data.top_urls || [];
  const threatTypes = data.threat_types || [];
  const cacheHitRatio = data.cache_hit_ratio || 0;
  const bwFormatted = data.bandwidth_formatted || '0 B';
  const sslCert = data.ssl_certificate;

  const statusClass = z.status === 'active' ? 'green' : 'yellow';
  const sslClass = s.ssl === 'full' || s.ssl === 'strict' ? 'green' : s.ssl === 'flexible' ? 'yellow' : 'red';

  // Format numbers
  const fmtNum = (n) => (n || 0).toLocaleString();

  let html = `
    <div style="grid-column:1/-1;display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
      <div class="infra-stat"><span class="infra-label">Zone</span><span class="infra-val">${z.name || '-'}</span></div>
      <div class="infra-stat"><span class="infra-label">Status</span><span class="infra-val ${statusClass}">${z.status || 'unknown'}</span></div>
      <div class="infra-stat"><span class="infra-label">Plan</span><span class="infra-val">${z.plan || 'Free'}</span></div>
      <div class="infra-stat"><span class="infra-label">SSL</span><span class="infra-val ${sslClass}">${s.ssl || 'off'}</span></div>
      <div class="infra-stat"><span class="infra-label">Cache Level</span><span class="infra-val">${s.cache_level || '-'}</span></div>
      <div class="infra-stat"><span class="infra-label">Dev Mode</span><span class="infra-val ${s.development_mode === 'on' ? 'yellow' : 'green'}">${s.development_mode || 'off'}</span></div>
    </div>
  `;

  // SSL Certificate info
  if (sslCert) {
    const certClass = sslCert.status === 'active' ? 'green' : sslCert.days_left < 30 ? 'red' : 'yellow';
    const daysText = sslCert.days_left !== null ? sslCert.days_left + ' days left' : 'N/A';
    html += `<div class="infra-stat"><span class="infra-label">SSL Cert</span><span class="infra-val ${certClass}">${sslCert.status} (${daysText})</span></div>`;
  }

  // 7-Day Traffic Chart
  if (days.length > 0) {
    const maxReq = Math.max(...days.map(d => d.requests), 1);
    html += `
      <div style="grid-column:1/-1;margin-top:10px;border-top:1px solid rgba(255,255,255,0.08);padding-top:10px">
        <div style="font-size:0.78rem;color:var(--muted);margin-bottom:8px">📊 7-Day Traffic</div>
        <div style="display:flex;align-items:flex-end;gap:4px;height:60px">
          ${days.map(d => {
            const h = Math.max((d.requests / maxReq) * 55, 2);
            return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px" title="${d.date}: ${fmtNum(d.requests)} reqs, ${fmtNum(d.pageViews)} PV, ${d.threats} threats, ${fmtNum(d.uniques)} uniq">
              <div style="width:100%;height:${h}px;background:linear-gradient(to top,rgba(59,130,246,0.3),rgba(59,130,246,0.8));border-radius:3px 3px 0 0"></div>
              <div style="font-size:0.6rem;color:var(--muted)">${d.date.slice(5)}</div>
            </div>`;
          }).join('')}
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:10px">
          <div class="infra-stat"><span class="infra-label">Total Reqs</span><span class="infra-val cyan">${fmtNum(a.requests)}</span></div>
          <div class="infra-stat"><span class="infra-label">Page Views</span><span class="infra-val green">${fmtNum(a.pageViews)}</span></div>
          <div class="infra-stat"><span class="infra-label">Bandwidth</span><span class="infra-val">${bwFormatted}</span></div>
          <div class="infra-stat"><span class="infra-label">Uniques</span><span class="infra-val">${fmtNum(a.uniques)}</span></div>
        </div>
      </div>
    `;
  }

  // Cache Hit Ratio
  html += `
    <div style="grid-column:1/-1;margin-top:10px;border-top:1px solid rgba(255,255,255,0.08);padding-top:10px">
      <div style="font-size:0.78rem;color:var(--muted);margin-bottom:8px">⚡ Cache Performance</div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px">
        <div class="infra-stat">
          <span class="infra-label">Hit Ratio</span>
          <span class="infra-val ${cacheHitRatio > 80 ? 'green' : cacheHitRatio > 50 ? 'yellow' : 'red'}">${cacheHitRatio}%</span>
        </div>
        <div class="infra-stat"><span class="infra-label">Cached</span><span class="infra-val green">${fmtNum(a.cachedRequests)}</span></div>
        <div class="infra-stat"><span class="infra-label">Uncached</span><span class="infra-val red">${fmtNum(a.uncachedRequests)}</span></div>
        <div class="infra-stat"><span class="infra-label">Cached BW</span><span class="infra-val">${(a.cachedBytes / 1073741824).toFixed(1)} GB</span></div>
      </div>
      <div class="progress-bar" style="margin-top:8px;height:8px"><div class="fill ${cacheHitRatio > 80 ? 'green' : cacheHitRatio > 50 ? 'yellow' : 'red'}" style="width:${cacheHitRatio}%"></div></div>
    </div>
  `;

  // Hourly Traffic (last 24h)
  if (hours.length > 0) {
    const maxHr = Math.max(...hours.map(h => h.requests), 1);
    html += `
      <div style="grid-column:1/-1;margin-top:10px;border-top:1px solid rgba(255,255,255,0.08);padding-top:10px">
        <div style="font-size:0.78rem;color:var(--muted);margin-bottom:8px">🕐 24h Hourly Traffic</div>
        <div style="display:flex;align-items:flex-end;gap:2px;height:40px">
          ${hours.map(h => {
            const hrHeight = Math.max((h.requests / maxHr) * 35, 1);
            const time = h.datetime ? h.datetime.slice(11, 16) : '';
            return `<div style="flex:1;display:flex;flex-direction:column;align-items:center" title="${time}: ${fmtNum(h.requests)} reqs">
              <div style="width:100%;height:${hrHeight}px;background:rgba(34,197,94,0.6);border-radius:2px 2px 0 0"></div>
            </div>`;
          }).join('')}
        </div>
      </div>
    `;
  }

  // Countries
  if (countries.length > 0) {
    html += `
      <div style="grid-column:1/-1;margin-top:10px;border-top:1px solid rgba(255,255,255,0.08);padding-top:10px">
        <div style="font-size:0.78rem;color:var(--muted);margin-bottom:8px">🌍 Top Countries (7d)</div>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px">
          ${countries.slice(0, 8).map(c => `
            <div class="infra-stat">
              <span class="infra-label">${c.flag} ${c.name}</span>
              <span class="infra-val">${c.percentage}% (${fmtNum(c.requests)})</span>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  // HTTP Status Codes
  if (statusCodes.length > 0) {
    const totalStatus = statusCodes.reduce((sum, s) => sum + s.requests, 0);
    html += `
      <div style="grid-column:1/-1;margin-top:10px;border-top:1px solid rgba(255,255,255,0.08);padding-top:10px">
        <div style="font-size:0.78rem;color:var(--muted);margin-bottom:8px">📡 HTTP Status Distribution</div>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px">
          ${statusCodes.map(sc => {
            const pct = totalStatus > 0 ? ((sc.requests / totalStatus) * 100).toFixed(1) : 0;
            const cls = sc.class == 2 ? 'green' : sc.class == 3 ? 'cyan' : sc.class == 4 ? 'yellow' : sc.class == 5 ? 'red' : '';
            return `<div class="infra-stat"><span class="infra-label">${sc.label}</span><span class="infra-val ${cls}">${pct}% (${fmtNum(sc.requests)})</span></div>`;
          }).join('')}
        </div>
      </div>
    `;
  }

  // Top URLs
  if (topUrls.length > 0) {
    html += `
      <div style="grid-column:1/-1;margin-top:10px;border-top:1px solid rgba(255,255,255,0.08);padding-top:10px">
        <div style="font-size:0.78rem;color:var(--muted);margin-bottom:8px">🔗 Top URLs (7d)</div>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px">
          ${topUrls.slice(0, 6).map(u => `
            <div class="infra-stat">
              <span class="infra-label" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${u.path}">${u.path}</span>
              <span class="infra-val">${fmtNum(u.requests)} reqs</span>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  // Threats
  if (a.threats > 0 || threatTypes.length > 0) {
    html += `
      <div style="grid-column:1/-1;margin-top:10px;border-top:1px solid rgba(255,255,255,0.08);padding-top:10px">
        <div style="font-size:0.78rem;color:var(--muted);margin-bottom:8px">🛡️ Threats (7d)</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px">
          <div class="infra-stat"><span class="infra-label">Total Threats</span><span class="infra-val yellow">${fmtNum(a.threats)}</span></div>
          <div class="infra-stat"><span class="infra-label">Blocked</span><span class="infra-val red">${fmtNum(fw.blocked)}</span></div>
          <div class="infra-stat"><span class="infra-label">Challenged</span><span class="infra-val yellow">${fmtNum(fw.challenged)}</span></div>
        </div>
        ${threatTypes.length > 0 ? `
          <div style="margin-top:8px;display:grid;grid-template-columns:repeat(2,1fr);gap:4px">
            ${threatTypes.slice(0, 4).map(t => `<div class="infra-stat"><span class="infra-label">${t.type}</span><span class="infra-val red">${fmtNum(t.count)}</span></div>`).join('')}
          </div>
        ` : ''}
      </div>
    `;
  }

  // Quick Actions
  html += `
    <div style="grid-column:1/-1;margin-top:10px;border-top:1px solid rgba(255,255,255,0.08);padding-top:10px">
      <div style="font-size:0.78rem;color:var(--muted);margin-bottom:8px">⚡ Quick Actions</div>
      <div style="display:flex;flex-wrap:wrap;gap:6px">
        <button class="btn" onclick="cfAction('purge_all')" style="background:#f59e0b;color:#000;padding:4px 10px;font-size:0.75rem;border:none;border-radius:4px;cursor:pointer">Purge All</button>
        <button class="btn" onclick="cfAction('toggle_dev_mode',{value:'on'})" style="background:#3b82f6;color:#fff;padding:4px 10px;font-size:0.75rem;border:none;border-radius:4px;cursor:pointer">Dev Mode ON</button>
        <button class="btn" onclick="cfAction('toggle_dev_mode',{value:'off'})" style="background:#6b7280;color:#fff;padding:4px 10px;font-size:0.75rem;border:none;border-radius:4px;cursor:pointer">Dev Mode OFF</button>
        <button class="btn" onclick="cfAction('cache_level',{level:'aggressive'})" style="background:#10b981;color:#fff;padding:4px 10px;font-size:0.75rem;border:none;border-radius:4px;cursor:pointer">Cache Aggressive</button>
        <button class="btn" onclick="cfAction('cache_level',{level:'basic'})" style="background:#ef4444;color:#fff;padding:4px 10px;font-size:0.75rem;border:none;border-radius:4px;cursor:pointer">Cache Basic</button>
      </div>
    </div>

    <div style="grid-column:1/-1;margin-top:8px;border-top:1px solid rgba(255,255,255,0.08);padding-top:8px">
      <div style="font-size:0.78rem;color:var(--muted);margin-bottom:6px">🔧 Settings</div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px">
        <div class="infra-stat"><span class="infra-label">Always Online</span><span class="infra-val ${s.always_online === 'on' ? 'green' : 'red'}">${s.always_online || 'off'}</span></div>
        <div class="infra-stat"><span class="infra-label">Auto HTTPS</span><span class="infra-val ${s.automatic_https_rewrites === 'on' ? 'green' : 'red'}">${s.automatic_https_rewrites || 'off'}</span></div>
        <div class="infra-stat"><span class="infra-label">Security</span><span class="infra-val">${s.security_level || '-'}</span></div>
        <div class="infra-stat"><span class="infra-label">Brotli</span><span class="infra-val ${s.brotli === 'on' ? 'green' : 'red'}">${s.brotli || 'off'}</span></div>
        <div class="infra-stat"><span class="infra-label">HTTP/3</span><span class="infra-val ${s.http3 === 'on' ? 'green' : 'red'}">${s.http3 || 'off'}</span></div>
        <div class="infra-stat"><span class="infra-label">WAF</span><span class="infra-val ${s.waf === 'on' ? 'green' : 'red'}">${s.waf || 'off'}</span></div>
      </div>
    </div>
  `;

  el.innerHTML = html;
}

function renderSystemAdvanced(data) {
  const el = document.getElementById('system-advanced-content');
  if (!data || data.error) {
    el.innerHTML = '<div class="loading">System metrics unavailable</div>';
    return;
  }

  const net = data.network || {};
  const io = data.io || {};
  const sys = data.system || {};

  let html = `
    <div>
      <div style="font-size:0.78rem;color:var(--muted);margin-bottom:8px">📡 Network (enp1s0f0)</div>
      <div class="infra-stat"><span class="infra-label">RX</span><span class="infra-val green">${net.rx_rate || '0 B/s'}</span></div>
      <div class="infra-stat"><span class="infra-label">TX</span><span class="infra-val cyan">${net.tx_rate || '0 B/s'}</span></div>
      <div class="infra-stat"><span class="infra-label">Total RX</span><span class="infra-val">${net.total_rx || '0 B'}</span></div>
      <div class="infra-stat"><span class="infra-label">Total TX</span><span class="infra-val">${net.total_tx || '0 B'}</span></div>
    </div>
    <div>
      <div style="font-size:0.78rem;color:var(--muted);margin-bottom:8px">💾 Disk I/O (sda2)</div>
      <div class="infra-stat"><span class="infra-label">Read IOPS</span><span class="infra-val">${(io.read_iops || 0).toLocaleString()}</span></div>
      <div class="infra-stat"><span class="infra-label">Write IOPS</span><span class="infra-val">${(io.write_iops || 0).toLocaleString()}</span></div>
      <div class="infra-stat"><span class="infra-label">Read Rate</span><span class="infra-val">${io.read_rate || '0 B/s'}</span></div>
      <div class="infra-stat"><span class="infra-label">Write Rate</span><span class="infra-val">${io.write_rate || '0 B/s'}</span></div>
    </div>
    <div style="grid-column:1/-1">
      <div style="font-size:0.78rem;color:var(--muted);margin-bottom:8px">🖥️ System</div>
      <div class="infra-stat"><span class="infra-label">File Descriptors</span><span class="infra-val">${(sys.file_descriptors_used || 0).toLocaleString()} / ${(sys.file_descriptors_max || 65536).toLocaleString()}</span></div>
      <div class="infra-stat"><span class="infra-label">Uptime</span><span class="infra-val">${sys.uptime_human || 'Unknown'}</span></div>
    </div>
  `;

  el.innerHTML = html;
}

function renderPhpFPM(data) {
  const el = document.getElementById('phpfpm-content');
  if (!data || !data.pools || data.pools.length === 0) {
    el.innerHTML = '<div class="loading">No PHP-FPM pools found</div>';
    return;
  }

  let html = '<table><thead><tr><th>Pool</th><th>Max Children</th><th>Active Workers</th><th>Utilization</th></tr></thead><tbody>';
  data.pools.forEach(pool => {
    const utilPct = pool.utilization_pct || 0;
    const utilClass = utilPct > 90 ? 'red' : utilPct > 70 ? 'yellow' : 'green';
    html += `<tr>
      <td><strong>${pool.name}</strong></td>
      <td>${pool.max_children || 0}</td>
      <td>${pool.active_workers || 0}</td>
      <td><span class="badge ${utilClass}">${utilPct.toFixed(1)}%</span></td>
    </tr>`;
  });
  html += '</tbody></table>';

  el.innerHTML = html;
}

// ── Commerce Tab ──
async function loadCommerce() {
  try {
    const [prodR, betaR] = await Promise.all([
      fetch(DASH_API + '?action=magento-stats&env=prod'),
      fetch(DASH_API + '?action=magento-stats&env=beta')
    ]);

    const prod = prodR.ok ? await prodR.json() : null;
    const beta = betaR.ok ? await betaR.json() : null;

    renderCommerce(prod, beta);
  } catch(e) { console.error('commerce', e); }
}

function renderCommerce(prod, beta) {
  const data = prod?.success ? prod.data : null;

  // Today's orders
  document.getElementById('commerce-today-orders').textContent = data?.today_orders?.count || 0;
  document.getElementById('commerce-today-revenue').textContent = data?.today_orders?.revenue ? `$${Number(data.today_orders.revenue).toLocaleString()}` : '$0';

  // Last hour
  document.getElementById('commerce-hour-orders').textContent = data?.last_hour_orders?.count || 0;
  document.getElementById('commerce-hour-revenue').textContent = data?.last_hour_orders?.revenue ? `$${Number(data.last_hour_orders.revenue).toLocaleString()}` : '$0';

  // Active carts
  document.getElementById('commerce-active-carts').textContent = data?.active_carts?.count || 0;
  document.getElementById('commerce-carts-value').textContent = data?.active_carts?.value ? `$${Number(data.active_carts.value).toLocaleString()}` : '$0';

  // Online customers - will be updated by loadVisitorStats
  const onlineCustomers = data?.online_customers || 0;
  document.getElementById('commerce-online').textContent = onlineCustomers;

  // Recent orders table
  const orders = data?.recent_orders || [];
  if (orders.length === 0) {
    document.querySelector('#recent-orders-table tbody').innerHTML = '<tr><td colspan="4" class="loading">No recent orders</td></tr>';
  } else {
    let html = orders.slice(0, 5).map(o => {
      const statusClass = o.status === 'complete' ? 'green' : o.status === 'pending' ? 'yellow' : o.status === 'processing' ? 'blue' : 'red';
      return `<tr>
        <td><strong>${o.increment_id}</strong></td>
        <td><span class="badge ${statusClass}">${o.status}</span></td>
        <td>$${Number(o.grand_total || 0).toLocaleString()}</td>
        <td style="font-size:0.75rem">${o.created_at?.substring(0, 10) || ''}</td>
      </tr>`;
    }).join('');
    document.querySelector('#recent-orders-table tbody').innerHTML = html;
  }

  // Product stats
  const products = data?.products || {};
  document.getElementById('product-stats-content').innerHTML = `
    <div class="infra-stat"><span class="infra-label">Total Products</span><span class="infra-val">${products.total || 0}</span></div>
    <div class="infra-stat"><span class="infra-label">Enabled</span><span class="infra-val green">${products.enabled || 0}</span></div>
    <div class="infra-stat"><span class="infra-label">In Stock</span><span class="infra-val green">${products.in_stock || 0}</span></div>
    <div class="infra-stat"><span class="infra-label">Low Stock</span><span class="infra-val yellow">${products.low_stock || 0}</span></div>
    <div class="infra-stat"><span class="infra-label">Out of Stock</span><span class="infra-val red">${products.out_of_stock || 0}</span></div>
  `;

  // Customer stats
  const customers = data?.customers || {};
  document.getElementById('customer-stats-content').innerHTML = `
    <div class="card metric-card">
      <div class="metric-label">Total Customers</div>
      <div class="metric-value">${customers.total || 0}</div>
    </div>
    <div class="card metric-card">
      <div class="metric-label">New Today</div>
      <div class="metric-value">${customers.new_today || 0}</div>
      <div class="metric-sub"></div>
    </div>
    <div class="card metric-card">
      <div class="metric-label">New This Week</div>
      <div class="metric-value">${customers.new_this_week || 0}</div>
      <div class="metric-sub"></div>
    </div>
    <div class="card metric-card">
      <div class="metric-label">Beta Customers</div>
      <div class="metric-value">${beta?.success ? (beta.data.customers?.total || 0) : 0}</div>
      <div class="metric-sub"></div>
    </div>
  `;
}

// ── Alerts Tab ──
async function loadAlerts() {
  try {
    const r = await fetch(MONITOR_API + '?action=alerts');
    if (!r.ok) return;
    const d = await r.json();

    const alerts = d.alerts || [];
    const summary = d.summary || {};

    let html = '';
    if (alerts.length === 0) {
      html = '<div style="text-align:center;padding:20px;color:var(--muted)">No recent alerts</div>';
    } else {
      html = '<table><thead><tr><th>Time</th><th>Type</th><th>Key</th><th>Age</th></tr></thead><tbody>';
      alerts.slice(0, 20).forEach(a => {
        const ageMin = a.age_minutes || 0;
        const ageStr = ageMin < 60 ? `${ageMin}m ago` : `${(ageMin/60).toFixed(1)}h ago`;
        html += `<tr>
          <td style="font-size:0.75rem">${a.timestamp || ''}</td>
          <td><span class="badge ${a.type === 'critical' ? 'red' : a.type === 'warning' ? 'yellow' : 'blue'}">${a.type}</span></td>
          <td style="font-size:0.75rem;font-family:monospace">${a.alert_key || ''}</td>
          <td>${ageStr}</td>
        </tr>`;
      });
      html += '</tbody></table>';
    }

    // Insert at top of Notifications tab
    const notifTab = document.getElementById('tab-telegram');
    const existingAlerts = document.getElementById('alerts-section');
    if (!existingAlerts) {
      const alertDiv = document.createElement('div');
      alertDiv.id = 'alerts-section';
      alertDiv.className = 'card';
      alertDiv.style.cssText = 'grid-column:1/-1;margin-bottom:20px';
      alertDiv.innerHTML = `<h3>📊 Recent Alerts</h3><div style="margin-top:12px">${html}</div>
        <div style="margin-top:12px;font-size:0.82rem;color:var(--muted)">
          Total: <strong>${summary.total_sent || 0}</strong> | 
          Last hour: <strong>${summary.last_hour || 0}</strong> | 
          Dedup active: <strong>${summary.dedup_active || 0}</strong>
        </div>`;
      notifTab.insertBefore(alertDiv, notifTab.firstChild);
    } else {
      existingAlerts.innerHTML = `<h3>📊 Recent Alerts</h3><div style="margin-top:12px">${html}</div>
        <div style="margin-top:12px;font-size:0.82rem;color:var(--muted)">
          Total: <strong>${summary.total_sent || 0}</strong> | 
          Last hour: <strong>${summary.last_hour || 0}</strong> | 
          Dedup active: <strong>${summary.dedup_active || 0}</strong>
        </div>`;
    }
  } catch(e) { console.error('alerts', e); }
}

// Update tab click handlers

// ── Cloudflare Analytics ──
let cfDataCache = null;
let cfChartsInitialized = false;

async function loadCloudflare() {
  try {
    if (cfDataCache) {
      renderCloudflare(cfDataCache);
      return;
    }
    const r = await fetch(MONITOR_API + '?action=cloudflare');
    const d = await r.json();
    if (d.error) {
      document.getElementById('cf-zone-status').textContent = 'Error';
      document.getElementById('cf-plan').textContent = d.error;
      return;
    }
    cfDataCache = d;
    renderCloudflare(d);
  } catch(e) { console.error('Cloudflare', e); }
}

function renderCloudflare(d) {
  const z = d.zone || {};
  const totals = d.analytics_totals || {};
  const formatBytes = b => {
    if (!b) return '0 B';
    if (b >= 1073741824) return (b / 1073741824).toFixed(1) + ' GB';
    if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
    if (b >= 1024) return (b / 1024).toFixed(1) + ' KB';
    return b + ' B';
  };
  const formatNum = n => (n || 0).toLocaleString();

  // Overview metrics
  const statusColor = z.status === 'active' ? '🟢' : '🟡';
  document.getElementById('cf-zone-status').textContent = statusColor + ' ' + (z.status || '-').charAt(0).toUpperCase() + (z.status || '-').slice(1);
  document.getElementById('cf-plan').textContent = (z.plan || '-') + ' • ' + (z.name || '');

  document.getElementById('cf-requests').textContent = formatNum(totals.requests || 0);
  const days = (d.analytics || []).length;
  document.getElementById('cf-requests-sub').textContent = days + ' days tracked';

  document.getElementById('cf-bandwidth').textContent = formatBytes(totals.bytes || 0);
  const cachedBytes = totals.cachedBytes || 0;
  document.getElementById('cf-bandwidth-sub').textContent = 'Cached: ' + formatBytes(cachedBytes);

  const cacheRatio = d.cache_hit_ratio || 0;
  document.getElementById('cf-cache-ratio').textContent = cacheRatio + '%';
  document.getElementById('cf-cache-sub').textContent = cacheRatio > 80 ? '✅ Excellent' : cacheRatio > 50 ? '⚠️ Fair' : '❌ Poor';

  // SSL
  const ssl = d.ssl_certificate;
  const sslSettings = (d.settings || {}).ssl || 'off';
  let sslHtml = '<div><strong>Mode:</strong> <span style="color:var(--green)">' + sslSettings.toUpperCase() + '</span></div>';
  if (ssl) {
    const daysLeft = ssl.days_left;
    const statusColor = daysLeft < 30 ? 'var(--red)' : 'var(--green)';
    sslHtml += '<div><strong>Cert:</strong> <span style="color:' + statusColor + '">' + ssl.status + '</span></div>';
    sslHtml += '<div><strong>Expires:</strong> ' + (ssl.expires_on || '-') + '</div>';
    if (daysLeft !== null) sslHtml += '<div><strong>Days left:</strong> <span style="color:' + statusColor + '">' + daysLeft + '</span></div>';
    if (ssl.hostnames && ssl.hostnames.length) sslHtml += '<div style="margin-top:6px;font-size:0.78rem;font-family:monospace">' + ssl.hostnames.join(', ') + '</div>';
  }
  document.getElementById('cf-ssl-info').innerHTML = sslHtml;

  // Threats
  const threats = totals.threats || 0;
  let threatsHtml = '<div style="font-size:1.5rem;font-weight:700;color:' + (threats > 0 ? 'var(--red)' : 'var(--green)') + '">' + formatNum(threats) + '</div>';
  threatsHtml += '<div style="color:var(--muted);margin-top:4px">blocked in last ' + days + ' days</div>';
  const fw = d.firewall || {};
  if (fw.total) threatsHtml += '<div style="margin-top:8px"><strong>FW events:</strong> ' + formatNum(fw.total) + '</div>';
  if (fw.blocked) threatsHtml += '<div><strong style="color:var(--red)">Blocked:</strong> ' + formatNum(fw.blocked) + '</div>';
  if (fw.challenged) threatsHtml += '<div><strong style="color:var(--yellow)">Challenged:</strong> ' + formatNum(fw.challenged) + '</div>';
  document.getElementById('cf-threats-info').innerHTML = threatsHtml;

  // Settings
  const s = d.settings || {};
  const settingItems = [
    ['Brotli', s.brotli], ['HTTP/2', s.http2], ['HTTP/3', s.http3],
    ['IPv6', s.ipv6], ['WAF', s.waf], ['Always Online', s.always_online],
  ];
  let settingsHtml = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">';
  settingItems.forEach(([label, val]) => {
    const color = val === 'on' ? 'var(--green)' : val === 'off' ? 'var(--red)' : 'var(--muted)';
    settingsHtml += '<div style="font-size:0.82rem">' + label + ': <span style="color:' + color + '">' + val + '</span></div>';
  });
  settingsHtml += '</div>';
  const devMode = z.development_mode || 'off';
  const devColor = devMode === 'on' ? 'var(--yellow)' : 'var(--muted)';
  settingsHtml += '<div style="margin-top:10px;font-size:0.82rem">Dev Mode: <span style="color:' + devColor + '">' + devMode + '</span></div>';
  settingsHtml += '<div style="font-size:0.82rem">Browser TTL: ' + (Math.round((s.browser_cache_ttl || 0) / 3600)) + 'h</div>';
  document.getElementById('cf-settings-info').innerHTML = settingsHtml;

  // Firewall
  const fwEvents = (fw.events || []).slice(0, 5);
  let fwHtml = '';
  if (fwEvents.length) {
    fwEvents.forEach(ev => {
      const actionColor = ev.action === 'block' ? 'var(--red)' : 'var(--yellow)';
      fwHtml += '<div style="font-size:0.78rem;padding:4px 0;border-bottom:1px solid var(--border)">';
      fwHtml += '<span style="color:' + actionColor + '">' + ev.action + '</span> ';
      fwHtml += '<span style="color:var(--muted);font-family:monospace">' + (ev.source || '') + '</span>';
      fwHtml += '</div>';
    });
  } else {
    fwHtml = '<div style="color:var(--green);font-size:0.82rem">✅ No recent firewall events</div>';
  }
  document.getElementById('cf-firewall-info').innerHTML = fwHtml;

  // Charts
  renderCfDailyChart(d.analytics || []);
  renderCfHourlyChart(d.hourly_analytics || []);
  renderCfStatusChart(d.status_codes || []);
  renderCfCountries(d.countries || []);
  renderCfTopUrls(d.top_urls || []);
  renderCfThreatTypes(d.threat_types || []);
}

function renderCfDailyChart(analytics) {
  if (!analytics.length) return;
  const ctx = document.getElementById('cf-daily-chart').getContext('2d');
  if (cfChartsInitialized && window._cfDailyChart) window._cfDailyChart.destroy();
  window._cfDailyChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: analytics.map(a => a.date),
      datasets: [
        { label: 'Requests', data: analytics.map(a => a.requests), backgroundColor: 'rgba(59,130,246,0.7)', borderRadius: 4 },
        { label: 'Cached', data: analytics.map(a => a.cachedRequests), backgroundColor: 'rgba(34,197,94,0.7)', borderRadius: 4 },
        { label: 'Page Views', data: analytics.map(a => a.pageViews || 0), backgroundColor: 'rgba(168,85,247,0.7)', borderRadius: 4 },
      ]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top', labels: { color: '#94a3b8' } } }, scales: { x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(148,163,184,0.1)' } }, y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(148,163,184,0.1)' } } } }
  });
}

function renderCfHourlyChart(hourly) {
  if (!hourly.length) return;
  const ctx = document.getElementById('cf-hourly-chart').getContext('2d');
  if (cfChartsInitialized && window._cfHourlyChart) window._cfHourlyChart.destroy();
  window._cfHourlyChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: hourly.map(h => { const dt = new Date(h.datetime); return dt.getHours() + ':00'; }),
      datasets: [
        { label: 'Requests', data: hourly.map(h => h.requests), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true, tension: 0.4 },
        { label: 'Cached', data: hourly.map(h => h.cachedRequests), borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.1)', fill: true, tension: 0.4 },
      ]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top', labels: { color: '#94a3b8' } } }, scales: { x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(148,163,184,0.1)' } }, y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(148,163,184,0.1)' } } } }
  });
}

function renderCfStatusChart(codes) {
  if (!codes.length) return;
  const ctx = document.getElementById('cf-status-chart').getContext('2d');
  if (cfChartsInitialized && window._cfStatusChart) window._cfStatusChart.destroy();
  const colors = ['#22c55e', '#3b82f6', '#eab308', '#ef4444', '#a855f7'];
  window._cfStatusChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: codes.map(c => c.label),
      datasets: [{ data: codes.map(c => c.requests), backgroundColor: colors.slice(0, codes.length), borderWidth: 0 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { color: '#94a3b8' } } } }
  });
}

function renderCfCountries(countries) {
  const el = document.getElementById('cf-countries');
  if (!countries.length) { el.innerHTML = '<div style="color:var(--muted);font-size:0.82rem">No country data</div>'; return; }
  let html = '<table style="width:100%"><thead><tr><th>Country</th><th>Requests</th><th>%</th></tr></thead><tbody>';
  countries.slice(0, 10).forEach(c => {
    html += '<tr><td>' + c.flag + ' ' + c.name + '</td><td style="font-family:monospace">' + c.requests.toLocaleString() + '</td><td>' + c.percentage + '%</td></tr>';
  });
  html += '</tbody></table>';
  el.innerHTML = html;
}

function renderCfTopUrls(urls) {
  const el = document.getElementById('cf-top-urls');
  if (!urls.length) { el.innerHTML = '<div style="color:var(--muted);font-size:0.82rem">No URL data</div>'; return; }
  let html = '<table style="width:100%"><thead><tr><th>URL</th><th>Requests</th><th>Bytes</th></tr></thead><tbody>';
  urls.slice(0, 10).forEach(u => {
    const bytes = u.bytes >= 1048576 ? (u.bytes / 1048576).toFixed(1) + ' MB' : (u.bytes / 1024).toFixed(1) + ' KB';
    html += '<tr><td style="font-family:monospace;font-size:0.78rem">' + (u.path.length > 40 ? u.path.substring(0, 40) + '...' : u.path) + '</td><td style="font-family:monospace">' + u.requests.toLocaleString() + '</td><td>' + bytes + '</td></tr>';
  });
  html += '</tbody></table>';
  el.innerHTML = html;
}

function renderCfThreatTypes(threats) {
  const el = document.getElementById('cf-threat-types');
  if (!threats.length) { el.innerHTML = '<div style="color:var(--green);font-size:0.82rem">✅ No threats detected</div>'; return; }
  let html = '<div style="display:flex;flex-direction:column;gap:6px">';
  threats.forEach(t => {
    html += '<div style="display:flex;justify-content:space-between;font-size:0.82rem"><span>' + t.type + '</span><span style="font-family:monospace;color:var(--red)">' + t.count.toLocaleString() + '</span></div>';
  });
  html += '</div>';
  el.innerHTML = html;
}

async function cfAction(action) {
  const output = document.getElementById('cf-action-output');
  output.style.display = 'block';
  output.textContent = 'Executing ' + action + '...';
  try {
    const formData = new URLSearchParams({ action });
    const r = await fetch(MONITOR_API + '?action=cloudflare_action', { method: 'POST', body: formData });
    const d = await r.json();
    output.textContent = d.message || (d.success ? 'Success' : 'Failed');
    if (d.success) {
      cfDataCache = null; // Invalidate cache
      setTimeout(() => loadCloudflare(), 2000);
    }
  } catch(e) { output.textContent = 'Error: ' + e.message; }
}

// ── Load Chart.js dynamically ──
function loadChartJS() {
  return new Promise((resolve, reject) => {
    if (window.Chart) { resolve(); return; }
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js';
    s.onload = resolve;
    s.onerror = reject;
    document.head.appendChild(s);
  });
}

// Override loadCloudflare to ensure Chart.js is loaded
const _origLoadCloudflare = loadCloudflare;
loadCloudflare = async function() {
  try {
    await loadChartJS();
    cfChartsInitialized = true;
  } catch(e) { console.error('Chart.js load failed', e); }
  _origLoadCloudflare();
};

// ── Push Notifications (Webpushr) ──

async function loadPushEnvironments() {
  try {
    const r = await fetch('/api/webpushr.php?action=environments');
    const d = await r.json();
    if (!d.success) return;
    
    Object.keys(d.environments).forEach(env => {
      const cfg = d.environments[env];
      const el = document.getElementById('push-' + (env === 'production' ? 'prod' : env) + '-info');
      if (el) {
        el.innerHTML = '<div><strong>URL:</strong> <a href="' + cfg.url + '" target="_blank" style="color:var(--accent)">' + cfg.url + '</a></div>' +
          '<div style="margin-top:4px"><strong>Key:</strong> <code style="font-size:0.75rem">' + cfg.key_preview + '</code></div>' +
          '<div><strong>Token:</strong> <code style="font-size:0.75rem">' + cfg.token + '</code></div>';
      }
    });
  } catch(e) { console.error('Push env', e); }
}

async function pushSend() {
  const title = document.getElementById('push-title').value;
  const message = document.getElementById('push-message').value;
  const env = document.getElementById('push-env').value;
  const url = document.getElementById('push-url').value;
  const icon = document.getElementById('push-icon').value;
  
  if (!title || !message) { showToast('Title and message required', 'error'); return; }
  
  const output = document.getElementById('push-send-output');
  output.style.display = 'block';
  output.textContent = 'Sending...';
  
  try {
    const formData = new URLSearchParams({ action: 'send', env, title, message, target_url: url, icon });
    const r = await fetch('/api/webpushr.php', { method: 'POST', body: formData });
    const d = await r.json();
    
    if (d.error) {
      output.textContent = '❌ Error: ' + d.message;
    } else {
      output.textContent = '✅ Sent to ' + d.env + '! ' + JSON.stringify(d.data, null, 2);
      showToast('Push notification sent!', 'success');
    }
  } catch(e) { output.textContent = 'Error: ' + e.message; }
}

async function pushTest(env) {
  const outputId = 'push-test-' + (env === 'production' ? 'prod' : env);
  const output = document.getElementById(outputId);
  output.textContent = 'Sending test...';
  
  try {
    const formData = new URLSearchParams({ action: 'send_test', env, title: '🧪 Test Notification', message: 'This is a test push from the dashboard at ' + new Date().toLocaleTimeString() });
    const r = await fetch('/api/webpushr.php', { method: 'POST', body: formData });
    const d = await r.json();
    
    if (d.error) {
      output.innerHTML = '<span style="color:var(--red)">❌ ' + d.message + '</span>';
    } else {
      output.innerHTML = '<span style="color:var(--green)">✅ Sent at ' + new Date().toLocaleTimeString() + '</span>';
      showToast('Test push sent to ' + env, 'success');
    }
  } catch(e) { output.innerHTML = '<span style="color:var(--red)">Error: ' + e.message + '</span>'; }
}

async function pushSchedule() {
  const title = document.getElementById('push-sched-title').value;
  const message = document.getElementById('push-sched-message').value;
  const time = document.getElementById('push-sched-time').value;
  const env = document.getElementById('push-sched-env').value;
  const url = document.getElementById('push-sched-url').value;
  
  if (!title || !message || !time) { showToast('Title, message, and time required', 'error'); return; }
  
  const output = document.getElementById('push-sched-output');
  output.style.display = 'block';
  output.textContent = 'Scheduling...';
  
  const schedTime = new Date(time).toISOString().replace('Z', '').replace('T', ' ').substring(0, 16);
  
  try {
    const formData = new URLSearchParams({ action: 'send_scheduled', env, title, message, scheduled_time: schedTime, target_url: url });
    const r = await fetch('/api/webpushr.php', { method: 'POST', body: formData });
    const d = await r.json();
    
    if (d.error) {
      output.textContent = '❌ Error: ' + d.message;
    } else {
      output.textContent = '✅ Scheduled for ' + schedTime + ' on ' + env;
      showToast('Notification scheduled!', 'success');
    }
  } catch(e) { output.textContent = 'Error: ' + e.message; }
}

async function pushBulk() {
  const input = document.getElementById('push-bulk-input').value.trim();
  const env = document.getElementById('push-bulk-env').value;
  
  if (!input) { showToast('Enter notifications', 'error'); return; }
  
  const notifications = input.split('\n').filter(l => l.trim()).map(line => {
    const parts = line.split('|');
    return { title: parts[0] || 'Notification', message: parts[1] || '', target_url: parts[2] || '' };
  });
  
  const output = document.getElementById('push-bulk-output');
  output.style.display = 'block';
  output.textContent = 'Sending ' + notifications.length + ' notifications...';
  
  try {
    const formData = new URLSearchParams({ action: 'send_bulk', env, notifications: JSON.stringify(notifications) });
    const r = await fetch('/api/webpushr.php', { method: 'POST', body: formData });
    const d = await r.json();
    
    if (d.error) {
      output.textContent = '❌ Error: ' + d.message;
    } else {
      let text = '✅ Bulk Send Complete\nSent: ' + d.sent + ' | Failed: ' + d.failed + '\n\n';
      d.results.forEach(res => {
        text += (res.status === 'sent' ? '✅' : '❌') + ' ' + res.title + (res.error ? ' - ' + res.error : '') + '\n';
      });
      output.textContent = text;
      showToast('Bulk send: ' + d.sent + ' sent, ' + d.failed + ' failed', d.failed > 0 ? 'warning' : 'success');
    }
  } catch(e) { output.textContent = 'Error: ' + e.message; }
}

async function pushInstallModule(env) {
  const output = document.getElementById('push-module-output');
  output.style.display = 'block';
  output.textContent = 'Checking module status on ' + env + '...';
  
  try {
    const formData = new URLSearchParams({ action: 'test', env, type: 'module-status' });
    const r = await fetch('/api/cicd.php', { method: 'POST', body: formData });
    const d = await r.json();
    
    if (d.success) {
      output.textContent = 'Test started. Job ID: ' + d.job_id + '. Check CI/CD tab for output.';
    } else {
      output.textContent = 'Error: ' + (d.error || 'Unknown');
    }
  } catch(e) { output.textContent = 'Error: ' + e.message; }
}

// ── CI/CD Pipeline ──

function loadCicdEnvironments() {
  const betaInfo = document.getElementById('cicd-beta-info');
  const devInfo = document.getElementById('cicd-dev-info');
  
  if (betaInfo) {
    betaInfo.innerHTML = '<div><strong>URL:</strong> <a href="https://beta.technostationery.com" target="_blank" style="color:var(--accent)">beta.technostationery.com</a></div>' +
      '<div><strong>Path:</strong> <code style="font-size:0.75rem">/home/beta/public_html</code></div>' +
      '<div><strong>DB:</strong> <code style="font-size:0.75rem">beta_dBT8x12y22</code></div>' +
      '<div><strong>Redis:</strong> <code style="font-size:0.75rem">db0=cache, db1=FPC, db2=sessions</code></div>';
  }
  
  if (devInfo) {
    devInfo.innerHTML = '<div><strong>URL:</strong> <a href="https://dev.technostationery.com" target="_blank" style="color:var(--accent)">dev.technostationery.com</a></div>' +
      '<div><strong>Path:</strong> <code style="font-size:0.75rem">/home/dev/public_html</code></div>' +
      '<div><strong>DB:</strong> <code style="font-size:0.75rem">dev_dBT8x12y22</code></div>' +
      '<div><strong>Redis:</strong> <code style="font-size:0.75rem">db5=cache, db6=FPC, db7=sessions</code></div>';
  }
}

let cicdCurrentJobId = null;
let cicdPollInterval = null;

async function cicdBuild(env, type) {
  try {
    const formData = new URLSearchParams({ action: 'build', env, type });
    const r = await fetch('/api/cicd.php', { method: 'POST', body: formData });
    const d = await r.json();
    
    if (d.success) {
      cicdCurrentJobId = d.job_id;
      document.getElementById('cicd-job-id').value = d.job_id;
      showToast('Build started on ' + env, 'success');
      cicdPollJob(d.job_id);
    } else {
      showToast('Error: ' + d.error, 'error');
    }
  } catch(e) { showToast('Error: ' + e.message, 'error'); }
}

async function cicdTest(env, type) {
  try {
    const formData = new URLSearchParams({ action: 'test', env, type });
    const r = await fetch('/api/cicd.php', { method: 'POST', body: formData });
    const d = await r.json();
    
    if (d.success) {
      cicdCurrentJobId = d.job_id;
      document.getElementById('cicd-job-id').value = d.job_id;
      showToast('Test started on ' + env, 'success');
      cicdPollJob(d.job_id);
    } else {
      showToast('Error: ' + d.error, 'error');
    }
  } catch(e) { showToast('Error: ' + e.message, 'error'); }
}

async function cicdMigrateDB(mode) {
  const source = document.getElementById('cicd-db-source').value;
  const target = document.getElementById('cicd-db-target').value;
  
  if (source === target) { showToast('Source and target must be different', 'error'); return; }
  
  try {
    const formData = new URLSearchParams({ action: 'migrate_db', source, target, mode });
    const r = await fetch('/api/cicd.php', { method: 'POST', body: formData });
    const d = await r.json();
    
    if (d.success) {
      cicdCurrentJobId = d.job_id;
      document.getElementById('cicd-job-id').value = d.job_id;
      showToast('DB migration started: ' + source + ' → ' + target, 'success');
      cicdPollJob(d.job_id);
    } else {
      showToast('Error: ' + d.error, 'error');
    }
  } catch(e) { showToast('Error: ' + e.message, 'error'); }
}

async function cicdMigrateCode(scope) {
  const source = document.getElementById('cicd-code-source').value;
  const target = document.getElementById('cicd-code-target').value;
  
  if (source === target) { showToast('Source and target must be different', 'error'); return; }
  
  try {
    const formData = new URLSearchParams({ action: 'migrate_code', source, target, scope });
    const r = await fetch('/api/cicd.php', { method: 'POST', body: formData });
    const d = await r.json();
    
    if (d.success) {
      cicdCurrentJobId = d.job_id;
      document.getElementById('cicd-job-id').value = d.job_id;
      showToast('Code migration started: ' + source + ' → ' + target, 'success');
      cicdPollJob(d.job_id);
    } else {
      showToast('Error: ' + d.error, 'error');
    }
  } catch(e) { showToast('Error: ' + e.message, 'error'); }
}

async function cicdReindex(env) {
  try {
    const formData = new URLSearchParams({ action: 'reindex', env });
    const r = await fetch('/api/cicd.php', { method: 'POST', body: formData });
    const d = await r.json();
    
    if (d.success) {
      cicdCurrentJobId = d.job_id;
      document.getElementById('cicd-job-id').value = d.job_id;
      showToast('Reindex started on ' + env, 'success');
      cicdPollJob(d.job_id);
    } else {
      showToast('Error: ' + d.error, 'error');
    }
  } catch(e) { showToast('Error: ' + e.message, 'error'); }
}

async function cicdHealth(env) {
  try {
    const formData = new URLSearchParams({ action: 'health', env });
    const r = await fetch('/api/cicd.php', { method: 'POST', body: formData });
    const d = await r.json();
    
    if (d.success) {
      cicdCurrentJobId = d.job_id;
      document.getElementById('cicd-job-id').value = d.job_id;
      showToast('Health check started on ' + env, 'success');
      cicdPollJob(d.job_id);
    } else {
      showToast('Error: ' + d.error, 'error');
    }
  } catch(e) { showToast('Error: ' + e.message, 'error'); }
}

async function cicdModuleToggle(actionType) {
  const env = document.getElementById('cicd-module-env').value;
  const module = document.getElementById('cicd-module-name').value;
  
  if (!module) { showToast('Enter module name', 'error'); return; }
  
  try {
    const formData = new URLSearchParams({ action: 'module_toggle', env, module, action_type: actionType });
    const r = await fetch('/api/cicd.php', { method: 'POST', body: formData });
    const d = await r.json();
    
    if (d.success) {
      cicdCurrentJobId = d.job_id;
      document.getElementById('cicd-job-id').value = d.job_id;
      showToast('Module ' + actionType + ' started', 'success');
      cicdPollJob(d.job_id);
    } else {
      showToast('Error: ' + d.error, 'error');
    }
  } catch(e) { showToast('Error: ' + e.message, 'error'); }
}

async function cicdLoadJobs() {
  try {
    const r = await fetch('/api/cicd.php?action=jobs');
    const d = await r.json();
    if (!d.success) return;
    
    const container = document.getElementById('cicd-active-jobs');
    if (!d.jobs.length) {
      container.innerHTML = '<div style="color:var(--muted);font-size:0.82rem">No recent jobs</div>';
      return;
    }
    
    let html = '';
    d.jobs.slice(0, 15).forEach(j => {
      const statusColor = j.status === 'running' ? 'var(--yellow)' : j.status === 'success' ? 'var(--green)' : j.status === 'failed' ? 'var(--red)' : 'var(--muted)';
      const statusIcon = j.status === 'running' ? '🔄' : j.status === 'success' ? '✅' : j.status === 'failed' ? '❌' : '⏹️';
      html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);cursor:pointer;font-size:0.78rem" onclick="document.getElementById(\'cicd-job-id\').value=\'' + j.id + '\';cicdViewJob()">' +
        '<span>' + statusIcon + ' <strong>' + (j.type || '') + '</strong> - ' + (j.env || '') + (j.subtype ? '/' + j.subtype : '') + '</span>' +
        '<span style="color:' + statusColor + '">' + j.status + '</span>' +
        '<span style="color:var(--muted)">' + (j.timestamp || '') + '</span>' +
        '</div>';
    });
    container.innerHTML = html;
  } catch(e) { console.error('Jobs', e); }
}

async function cicdViewJob() {
  const jobId = document.getElementById('cicd-job-id').value;
  if (!jobId) return;
  cicdCurrentJobId = jobId;
  await cicdFetchJob(jobId);
}

async function cicdRefreshLog() {
  if (cicdCurrentJobId) await cicdFetchJob(cicdCurrentJobId);
}

async function cicdFetchJob(jobId) {
  try {
    const r = await fetch('/api/cicd.php?action=job_status&job_id=' + jobId);
    const d = await r.json();
    
    if (d.error) {
      document.getElementById('cicd-job-status').textContent = d.error;
      return;
    }
    
    const statusEl = document.getElementById('cicd-job-status');
    const statusColor = d.status === 'running' ? 'var(--yellow)' : d.status === 'success' ? 'var(--green)' : d.status === 'failed' ? 'var(--red)' : 'var(--muted)';
    statusEl.innerHTML = '<strong>Status:</strong> <span style="color:' + statusColor + '">' + d.status + '</span> | <strong>Type:</strong> ' + (d.type || '') + ' | <strong>Env:</strong> ' + (d.env || '') + ' | <strong>Log lines:</strong> ' + (d.log_total || 0);
    
    if (d.log && d.log.length) {
      document.getElementById('cicd-job-log').textContent = d.log.join('\n');
      const logEl = document.getElementById('cicd-job-log');
      logEl.scrollTop = logEl.scrollHeight;
    }
    
    if (d.running) {
      if (!cicdPollInterval) {
        cicdPollInterval = setInterval(() => cicdFetchJob(jobId), 3000);
      }
    } else {
      if (cicdPollInterval) {
        clearInterval(cicdPollInterval);
        cicdPollInterval = null;
      }
    }
  } catch(e) { console.error('Job fetch', e); }
}

function cicdPollJob(jobId) {
  cicdCurrentJobId = jobId;
  cicdFetchJob(jobId);
  if (cicdPollInterval) clearInterval(cicdPollInterval);
  cicdPollInterval = setInterval(() => cicdFetchJob(jobId), 3000);
  setTimeout(() => {
    if (cicdPollInterval) { clearInterval(cicdPollInterval); cicdPollInterval = null; }
  }, 600000);
}

(async function init() {
  const auth = await checkAuth();
  if (!auth) return;
  refresh();
  setInterval(refresh, 30000);
  // Refresh visitor stats every 30 seconds
  setInterval(loadVisitorStats, 30000);
})();


// ============================================================
// VARNISH MULTI-SITE MONITOR
// ============================================================
async function loadVarnishMonitor() {
  try {
    const r = await fetch('/api/varnish-persite.php?' + Date.now());
    const d = await r.json();
    renderVarnishMonitor(d);
  } catch(e) {
    document.getElementById('varnish-sites-grid').innerHTML = '<div class="loading">Error loading Varnish stats: ' + e.message + '</div>';
  }
}

function renderVarnishMonitor(d) {
  const g = d.global || {};
  const backends = d.backends || [];
  const hourly = d.hourly || [];

  // Global metrics
  const hr = g.hit_rate ?? 0;
  const hrClass = hr >= 80 ? 'green' : hr >= 50 ? 'yellow' : 'red';
  const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.innerHTML = val; };

  setEl('v-global-hitrate', `<span class="${hrClass}">${hr.toFixed(1)}%</span>`);
  setEl('v-global-target', hr >= 80 ? '✅ Meeting 80% target' : `⚠️ Below 80% target (${(80 - hr).toFixed(1)}% gap)`);
  setEl('v-global-hits', (g.hits || 0).toLocaleString());
  setEl('v-global-total', `/ ${(g.total || 0).toLocaleString()} total`);
  setEl('v-global-objects', (g.n_objects || 0).toLocaleString());
  const st = g.storage || {};
  setEl('v-global-storage', st.used || '—');
  setEl('v-global-storage-pct', st.pct_used ? `${st.pct_used}% of ${st.total || '6GB'}` : 'of 6GB');

  // Per-site cards
  const grid = document.getElementById('varnish-sites-grid');
  if (!backends.length) { grid.innerHTML = '<div class="loading">No backend data</div>'; return; }

  grid.innerHTML = backends.map(b => {
    const hr1h = b.hit_rate_1h;
    const hr24h = b.hit_rate_24h;
    const hClass = (v) => v == null ? 'muted' : v >= 80 ? 'green' : v >= 50 ? 'yellow' : 'red';
    const healthy = b.healthy !== false;
    const cacheable = b.cache !== false;
    const note = b.note || '';
    
    return `<div class="card" style="background:var(--card2);padding:14px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
        <span style="font-weight:600;font-size:0.9rem">${b.emoji || '🌐'} ${b.label || b.backend}</span>
        <span class="badge ${healthy ? 'green' : 'red'}">${healthy ? 'healthy' : 'down'}</span>
      </div>
      ${cacheable ? `
      <div style="margin-bottom:6px">
        <div style="font-size:0.72rem;color:var(--muted);margin-bottom:3px">1h Hit Rate</div>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="progress-bar" style="flex:1"><div class="fill ${hClass(hr1h)}" style="width:${Math.min(hr1h||0,100)}%"></div></div>
          <span class="infra-val ${hClass(hr1h)}" style="font-size:0.85rem;min-width:42px;text-align:right">${hr1h != null ? hr1h.toFixed(1)+'%' : '—'}</span>
        </div>
      </div>
      <div>
        <div style="font-size:0.72rem;color:var(--muted);margin-bottom:3px">24h Hit Rate</div>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="progress-bar" style="flex:1"><div class="fill ${hClass(hr24h)}" style="width:${Math.min(hr24h||0,100)}%"></div></div>
          <span class="infra-val ${hClass(hr24h)}" style="font-size:0.85rem;min-width:42px;text-align:right">${hr24h != null ? hr24h.toFixed(1)+'%' : '—'}</span>
        </div>
      </div>
      <div style="font-size:0.72rem;color:var(--muted);margin-top:6px">${(b.req_1h||0).toLocaleString()} req/1h · ${(b.req||0).toLocaleString()} backend req</div>
      ` : `<div style="text-align:center;padding:10px 0;color:var(--muted);font-size:0.8rem">⏭️ ${note || 'Cache bypassed'}</div>`}
    </div>`;
  }).join('');

  // Backend health table
  const tbody = document.querySelector('#varnish-backends-table tbody');
  tbody.innerHTML = backends.map(b => {
    const healthy = b.healthy !== false;
    const cacheable = b.cache !== false;
    const hr1h = b.hit_rate_1h;
    const hr24h = b.hit_rate_24h;
    const hClass = (v) => v == null ? '' : v >= 80 ? 'green' : v >= 50 ? 'yellow' : 'red';
    return `<tr>
      <td>${b.emoji || ''} ${b.label || b.backend}</td>
      <td><code style="font-size:0.75rem">${b.backend}</code></td>
      <td><span class="badge ${healthy ? 'green' : 'red'}">${healthy ? '✓ Up' : '✗ Down'}</span></td>
      <td>${(b.req || 0).toLocaleString()}</td>
      <td><span class="badge ${cacheable ? 'green' : 'gray'}">${cacheable ? '✓ Cached' : '⏭️ Pass'}</span></td>
      <td class="${hClass(hr1h)}">${hr1h != null ? hr1h.toFixed(1)+'%' : '—'}</td>
      <td class="${hClass(hr24h)}">${hr24h != null ? hr24h.toFixed(1)+'%' : '—'}</td>
    </tr>`;
  }).join('');

  // Hourly trend (ASCII-style bar chart in HTML)
  const chartEl = document.getElementById('varnish-hourly-chart');
  if (hourly.length) {
    const maxTotal = Math.max(...hourly.map(h => h.total), 1);
    chartEl.innerHTML = `<div style="display:flex;align-items:flex-end;gap:3px;height:80px;overflow-x:auto;padding-bottom:4px">
      ${hourly.map(h => {
        const pct = h.total > 0 ? (h.hit_rate / 100) : 0;
        const barH = Math.max(4, Math.round((h.total / maxTotal) * 70));
        const cls = h.hit_rate >= 80 ? '#4caf50' : h.hit_rate >= 50 ? '#ffc107' : '#f44336';
        return `<div title="${h.hour}: ${h.hit_rate}% hit rate (${h.total.toLocaleString()} req)" style="flex:1;min-width:18px;display:flex;flex-direction:column;align-items:center;cursor:pointer">
          <div style="font-size:0.6rem;color:${cls};margin-bottom:2px">${h.total > 0 ? h.hit_rate+'%' : ''}</div>
          <div style="width:100%;background:${cls};height:${barH}px;border-radius:2px 2px 0 0;opacity:${h.total > 0 ? 0.85 : 0.2}"></div>
          <div style="font-size:0.6rem;color:var(--muted);margin-top:2px;transform:rotate(-45deg);white-space:nowrap">${h.hour}</div>
        </div>`;
      }).join('')}
    </div>
    <div style="font-size:0.72rem;color:var(--muted);margin-top:18px;display:flex;gap:16px">
      <span style="color:#4caf50">■ ≥80% (target)</span>
      <span style="color:#ffc107">■ 50-79%</span>
      <span style="color:#f44336">■ <50%</span>
      <span>Bar height = relative traffic volume</span>
    </div>`;
  } else {
    chartEl.innerHTML = '<div style="color:var(--muted);font-size:0.85rem;padding:20px">No log data yet — data accumulates as traffic flows through Varnish</div>';
  }
}

async function varnishAction(action) {
  const outEl = document.getElementById('varnish-action-output');
  outEl.style.display = 'block';
  outEl.textContent = 'Running...';
  
  try {
    if (action === 'purge_all') {
      // Send BAN to Varnish admin
      const r = await fetch('/api/monitor.php?action=varnish_purge', { method: 'POST' });
      const d = await r.json();
      outEl.textContent = d.message || JSON.stringify(d);
    } else if (action === 'ban_static') {
      outEl.textContent = 'Banning static file cache (CSS/JS)...';
      // Use varnishadm ban
      const r = await fetch('/api/monitor.php?action=varnish_ban_static', { method: 'POST' });
      const d = await r.json();
      outEl.textContent = d.message || JSON.stringify(d);
    }
    setTimeout(() => loadVarnishMonitor(), 2000);
  } catch(e) {
    outEl.textContent = 'Error: ' + e.message;
  }
}

// ============================================================
// AKENEO PIM MONITOR
// ============================================================
async function loadPimMonitor() {
  try {
    const r = await fetch('/api/akeneo-monitor.php?' + Date.now());
    const d = await r.json();
    renderPimMonitor(d);
  } catch(e) {
    document.getElementById('pim-consumers-detail').innerHTML = '<div class="loading">Error: ' + e.message + '</div>';
  }
}

function renderPimMonitor(d) {
  // Health badge
  const health = d.health || 'unknown';
  const healthClass = health === 'healthy' ? 'green' : health === 'warning' ? 'yellow' : 'red';
  const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.innerHTML = val; };

  setEl('pim-health-badge', `<span class="${healthClass}" style="text-transform:uppercase;font-size:1.1rem">${health}</span>`);
  const issues = d.issues || [];
  setEl('pim-health-sub', issues.length ? `${issues.length} issue${issues.length>1?'s':''}` : 'All systems normal');

  // Issues card
  const issuesCard = document.getElementById('pim-issues-card');
  const issuesList = document.getElementById('pim-issues-list');
  if (issues.length) {
    issuesCard.style.display = 'block';
    issuesList.innerHTML = issues.map(i => `<div style="padding:6px 10px;background:var(--red-glow);border-left:3px solid var(--red);border-radius:0 4px 4px 0;margin-bottom:4px;font-size:0.85rem">⚠️ ${i}</div>`).join('');
  } else {
    issuesCard.style.display = 'none';
  }

  // Consumers
  const c = d.consumers || {};
  const consumers = c.consumers || {};
  const runCount = Object.values(consumers).filter(x => x.running).length;
  setEl('pim-consumers-count', `<span class="${runCount === 3 ? 'green' : 'red'}">${runCount}</span>`);
  
  setEl('pim-consumers-detail', Object.entries(consumers).map(([name, info]) => `
    <div class="infra-stat" style="margin-bottom:8px">
      <div style="flex:1">
        <div style="font-size:0.82rem;font-weight:500">${name.replace('akeneo_','')}</div>
        <div style="font-size:0.7rem;color:var(--muted)">${info.supervisor_line || 'Transport: ' + info.transport}</div>
      </div>
      <span class="badge ${info.running ? 'green' : 'red'}">${info.running ? '✓ Running' : '✗ Down'}</span>
    </div>
  `).join(''));

  // Elasticsearch
  const es = d.es || {};
  const esClass = es.status === 'green' ? 'green' : es.status === 'yellow' ? 'yellow' : 'red';
  setEl('pim-es-status', `<span class="${esClass}">${(es.status || 'unknown').toUpperCase()}</span>`);
  setEl('pim-es-shards', `${es.shards?.active || 0} active, ${es.shards?.unassigned || 0} unassigned`);
  
  const pimIndices = es.pim_indices || [];
  setEl('pim-es-detail', `
    <div class="infra-stat"><span class="infra-label">Cluster Status</span><span class="badge ${esClass}">${es.status || 'unknown'}</span></div>
    <div class="infra-stat"><span class="infra-label">Nodes</span><span class="infra-val">${es.nodes || 0}</span></div>
    <div class="infra-stat"><span class="infra-label">Active Shards</span><span class="infra-val green">${es.shards?.active || 0}</span></div>
    <div class="infra-stat"><span class="infra-label">Unassigned</span><span class="infra-val ${(es.shards?.unassigned||0)>0?'red':'green'}">${es.shards?.unassigned || 0}</span></div>
    ${pimIndices.length ? '<div style="margin-top:8px;font-size:0.75rem;color:var(--muted)">' + pimIndices.map(i => 
      `<div style="display:flex;justify-content:space-between;padding:2px 0"><span>${i.index?.replace('akeneo_pim_','')}</span><span style="color:${i.health==='green'?'var(--green)':'var(--yellow)'}">${i['docs.count']} docs · ${i['store.size']}</span></div>`
    ).join('') + '</div>' : ''}
  `);

  // Web health
  const web = d.web || {};
  setEl('pim-web-ms', `<span class="${web.response_ms < 2000 ? 'green' : web.response_ms < 5000 ? 'yellow' : 'red'}">${web.response_ms || '—'}</span>`);
  setEl('pim-web-status', `HTTP ${web.status_code || '—'} · ${web.ok ? '✓ Online' : '✗ Offline'}`);

  // Queues
  const queues = d.queues || {};
  if (queues.available) {
    const qrows = (queues.queues || []).map(q => 
      `<div class="infra-stat"><span class="infra-label">${q.queue_name}</span><span class="infra-val ${q.pending>0?'yellow':'green'}">${q.pending} pending · ${q.processing} processing</span></div>`
    ).join('') || '<div style="color:var(--muted);font-size:0.82rem">No messages in queue</div>';
    setEl('pim-queues-detail', qrows);
  } else {
    setEl('pim-queues-detail', `<div class="loading">${queues.error || 'DB unavailable'}</div>`);
  }

  // Recent jobs
  if (queues.available) {
    const statLabels = {1:'Pending',2:'In Progress',3:'Paused',4:'Completed',5:'Stopped',6:'Failed',7:'Abandoned',8:'Stopping'};
    const jobs24h = queues.jobs_24h || [];
    const failed = queues.failed_7d || 0;
    setEl('pim-jobs-detail', `
      <div style="margin-bottom:8px">
        <div style="font-size:0.75rem;color:var(--muted);margin-bottom:4px">24h job breakdown:</div>
        ${jobs24h.map(j => `<div class="infra-stat"><span class="infra-label">${statLabels[j.status]||'Status '+j.status}</span><span class="infra-val">${j.cnt}</span></div>`).join('') || '<div style="color:var(--muted);font-size:0.82rem">No jobs in last 24h</div>'}
      </div>
      <div class="infra-stat"><span class="infra-label">Failed (7d)</span><span class="infra-val ${failed>10?'red':failed>0?'yellow':'green'}">${failed}</span></div>
      <div style="margin-top:8px;font-size:0.72rem;color:var(--muted)">Recent executions:</div>
      ${(queues.recent_jobs||[]).slice(0,5).map(j => 
        `<div style="font-size:0.75rem;padding:3px 0;border-bottom:1px solid rgba(255,255,255,0.05)"><span style="color:${j.status==4?'var(--green)':j.status==6?'var(--red)':'var(--yellow)'}">${statLabels[j.status]||j.status}</span> <span style="color:var(--muted)">${j.job_name}</span> <span style="float:right;font-size:0.65rem">${(j.create_time||'').split('T')[0]}</span></div>`
      ).join('')}
    `);
  } else {
    setEl('pim-jobs-detail', `<div class="loading">${queues.error || 'DB unavailable'}</div>`);
  }

  // PHP-FPM
  const fpm = d.fpm || {};
  if (fpm.available) {
    setEl('pim-fpm-detail', `
      <div class="infra-stat"><span class="infra-label">Pool</span><span class="infra-val">${fpm.pool || 'pim'}</span></div>
      <div class="infra-stat"><span class="infra-label">Active Processes</span><span class="infra-val ${fpm.active_processes>5?'yellow':'green'}">${fpm.active_processes}</span></div>
      <div class="infra-stat"><span class="infra-label">Idle Processes</span><span class="infra-val">${fpm.idle_processes}</span></div>
      <div class="infra-stat"><span class="infra-label">Total</span><span class="infra-val">${fpm.total_processes}</span></div>
      <div class="infra-stat"><span class="infra-label">Slow Requests</span><span class="infra-val ${fpm.slow_requests>0?'yellow':'green'}">${fpm.slow_requests}</span></div>
    `);
  } else {
    setEl('pim-fpm-detail', '<div style="color:var(--muted)">FPM status endpoint not configured</div>');
  }

  // Cache
  const cache = d.cache || {};
  if (cache.available) {
    const ageH = cache.age_hours;
    setEl('pim-cache-detail', `
      <div class="infra-stat"><span class="infra-label">Cache Size</span><span class="infra-val">${cache.size_mb} MB</span></div>
      <div class="infra-stat"><span class="infra-label">Last Built</span><span class="infra-val ${ageH>24?'yellow':'green'}">${cache.last_built || '—'}</span></div>
      <div class="infra-stat"><span class="infra-label">Age</span><span class="infra-val ${ageH>48?'red':ageH>24?'yellow':'green'}">${ageH != null ? ageH + 'h ago' : '—'}</span></div>
    `);
  } else {
    setEl('pim-cache-detail', '<div style="color:var(--muted)">Cache directory not found</div>');
  }
}

async function pimAction(action) {
  const card = document.getElementById('pim-action-card');
  const outEl = document.getElementById('pim-action-output');
  card.style.display = 'block';
  outEl.textContent = `Running ${action}... (may take up to 2 minutes)`;
  
  try {
    const r = await fetch('/api/akeneo-monitor.php?action=' + action, { method: 'POST' });
    const d = await r.json();
    outEl.textContent = d.output || (d.success ? 'Done' : 'Error: ' + JSON.stringify(d));
    setTimeout(() => loadPimMonitor(), 3000);
  } catch(e) {
    outEl.textContent = 'Error: ' + e.message;
  }
}

// Performance monitoring
async function monitorAPIResponseTimes() {
  const actions = ['overview', 'varnish', 'redis'];
  const timings = {};
  
  for (const action of actions) {
    const start = performance.now();
    try {
      const r = await fetch(MONITOR_API + '?action=' + action);
      if (r.ok) await r.json();
      timings[action] = Math.round(performance.now() - start);
    } catch(e) {
      timings[action] = null;
    }
  }
  
  console.log('API Response Times:', timings);
  return timings;
}

// Auto-refresh monitoring data every 5 seconds
setInterval(() => {
  if (document.querySelector('.tab-content.active')?.id === 'tab-overview') {
    loadOverview();
  }
}, 5000);
