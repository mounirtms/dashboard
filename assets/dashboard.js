const MONITOR_API = '/api/monitor.php';
const DASH_API    = '/api/dashboard.php';
const AUTH_API    = '/api/auth.php';
let scriptData = {};

// Monkey-patch fetch to handle PHP shebang issue globally
const originalFetch = window.fetch;
window.fetch = async function(...args) {
  const response = await originalFetch.apply(this, args);
  const originalJson = response.json.bind(response);
  response.json = async function() {
    try {
      const text = await response.text();
      // Handle case where PHP shebang or other text precedes JSON
      const jsonStr = text.replace(/^[^{]*/, '').trim();
      return JSON.parse(jsonStr);
    } catch(e) {
      console.error('JSON parse error:', e);
      return {};
    }
  };
  return response;
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
document.querySelectorAll('.tab').forEach(t => {
  t.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(x => x.classList.remove('active'));
    t.classList.add('active');
    document.getElementById('tab-' + t.dataset.tab).classList.add('active');
    if (t.dataset.tab === 'scripts' && Object.keys(scriptData).length === 0) loadScripts();
    if (t.dataset.tab === 'dbhealth') loadDbHealth();
    if (t.dataset.tab === 'infrastructure') loadInfrastructure();
    if (t.dataset.tab === 'commerce') loadCommerce();
    if (t.dataset.tab === 'telegram') {
      loadTelegramConfig();
      loadAlerts();
    }
  });
});

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

    const memClass = d.memory.used_pct > 85 ? 'red' : d.memory.used_pct > 65 ? 'yellow' : 'green';
    document.getElementById('metrics-row').innerHTML = `
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
      <div class="card">
        <h3>Disk (/home)</h3>
        <div class="metric">${d.disk.pct}</div>
        <div style="font-size:0.78rem;color:var(--muted)">${d.disk.used} used / ${d.disk.total} total — ${d.disk.free} free</div>
      </div>
      <div class="card">
        <h3>Processes &amp; DB</h3>
        <div style="font-size:0.9rem">PHP-FPM: <strong>${d.processes.php_fpm}</strong> &nbsp; HTTPD: <strong>${d.processes.httpd}</strong></div>
        <div style="font-size:0.78rem;color:var(--muted);margin-top:4px">Messenger: ${d.processes.messenger} | Zombies: ${d.processes.zombies}</div>
        <div style="font-size:0.78rem;color:var(--muted)">DB: ${d.database.connections} conn / ${d.database.running} running</div>
      </div>
    `;

    // Processes table
    let ph = '';
    (d.top_processes || []).forEach(p => {
      const c = p.cpu > 50 ? 'red' : p.cpu > 20 ? 'yellow' : 'green';
      ph += `<tr><td>${p.pid}</td><td><span class="badge ${c}">${p.cpu}%</span></td><td>${p.mem}%</td><td>${p.time}</td><td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.cmd}</td></tr>`;
    });
    document.querySelector('#top-proc-table tbody').innerHTML = ph || '<tr><td colspan="5" class="loading">No processes</td></tr>';

    // Services
    let sh = '';
    Object.entries(d.services || {}).forEach(([k, v]) => {
      const cls = v === 'running' ? 'green' : v === 'dead' ? 'red' : 'yellow';
      sh += `<div class="svc-card"><span class="status-dot ${cls}" style="flex-shrink:0"></span><span class="svc-name">${k}</span><span class="badge ${cls}">${v}</span></div>`;
    });
    document.getElementById('services-list').innerHTML = sh;
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

// Background refresh for heavy infrastructure data (every 60 seconds)
setInterval(() => {
  loadInfrastructure();
}, 60000);

// Background refresh for commerce data (every 30 seconds)
setInterval(() => {
  loadCommerce();
}, 30000);

// ── Infrastructure Tab ──
async function loadInfrastructure() {
  try {
    const [redisR, esR, varnishR, sysR, phpfpmR, cfR] = await Promise.all([
      fetch(MONITOR_API + '?action=redis'),
      fetch(MONITOR_API + '?action=elasticsearch'),
      fetch(MONITOR_API + '?action=varnish'),
      fetch(MONITOR_API + '?action=system_advanced'),
      fetch(MONITOR_API + '?action=phpfpm_pools'),
      fetch(MONITOR_API + '?action=cloudflare')
    ]);

    const redis = redisR.ok ? await redisR.json() : null;
    const es = esR.ok ? await esR.json() : null;
    const varnish = varnishR.ok ? await varnishR.json() : null;
    const sys = sysR.ok ? await sysR.json() : null;
    const phpfpm = phpfpmR.ok ? await phpfpmR.json() : null;
    const cf = cfR.ok ? await cfR.json() : null;

    renderRedis(redis);
    renderElasticsearch(es);
    renderVarnish(varnish);
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

function renderVarnish(data) {
  const el = document.getElementById('varnish-content');
  if (!data || data.error) {
    el.innerHTML = '<div class="loading">Varnish unavailable</div>';
    return;
  }

  const hitRate = data.hit_ratio || 0;
  const hitClass = hitRate > 80 ? 'green' : hitRate > 50 ? 'yellow' : 'red';
  const storagePct = data.storage.total_bytes > 0 ? ((data.storage.used_bytes / data.storage.total_bytes) * 100).toFixed(1) : 0;
  const storageClass = storagePct > 90 ? 'red' : storagePct > 70 ? 'yellow' : 'green';

  let html = `
    <div class="infra-stat"><span class="infra-label">Hit Ratio</span><span class="infra-val ${hitClass}">${hitRate}%</span></div>
    <div class="progress-bar"><div class="fill ${hitClass}" style="width:${Math.min(hitRate, 100)}%"></div></div>
    <div class="infra-stat"><span class="infra-label">Hits</span><span class="infra-val green">${(data.hits || 0).toLocaleString()}</span></div>
    <div class="infra-stat"><span class="infra-label">Misses</span><span class="infra-val yellow">${(data.misses || 0).toLocaleString()}</span></div>
    <div class="infra-stat"><span class="infra-label">Req/sec</span><span class="infra-val">${(data.req_per_sec || 0).toLocaleString()}</span></div>
    <div class="infra-stat"><span class="infra-label">Storage</span><span class="infra-val">${data.storage.used || 0} / ${data.storage.total || 0}</span></div>
    <div class="progress-bar"><div class="fill ${storageClass}" style="width:${Math.min(storagePct, 100)}%"></div></div>
    <div class="infra-stat"><span class="infra-label">Backend Failures</span><span class="infra-val">${data.backend_failures || 0}</span></div>
    <div class="infra-stat"><span class="infra-label">Evictions</span><span class="infra-val">${(data.evictions || 0).toLocaleString()}</span></div>
  `;

  // Device type breakdown
  if (data.device_types) {
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

  // Online customers
  document.getElementById('commerce-online').textContent = data?.online_customers || 0;

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

(async function init() {
  const auth = await checkAuth();
  if (!auth) return;
  refresh();
  setInterval(refresh, 30000);
})();
