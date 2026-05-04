let currentRatingId = null;
let currentRatingValue = 0;
let selectedEntryId = null;
// Initialize from server-provided flags when available
let isAuthenticated = (typeof window !== 'undefined' && window.serverAuth) ? Boolean(window.serverAuth) : false;
let userEmail = (typeof window !== 'undefined' && window.serverEmail) ? window.serverEmail : null;
const allAnswers = window.allAnswersData || [];

// initialize from server-provided values
if (typeof window.serverAuth !== 'undefined') {
    isAuthenticated = !!window.serverAuth;
}
if (typeof window.serverEmail !== 'undefined') {
    userEmail = window.serverEmail || null;
}

// Forward declarations - make functions globally available BEFORE they're defined
function openDetailModal(id) {}
function closeDetailModal() {}
function openRatingModal(id, rating) {}
function closeRatingModal() {}
function saveRating() {}
function updateCardRating(id, rating) {}
function deleteEntry(id) {}
function downloadEntry(id) {}
function logout() {}
function applyFilters() {}
function showNotification(msg, type) {}
function updateHeaderButtons() {}
function openImageFullscreen(src) {}

// Assign to window
window.openDetailModal = openDetailModal;
window.closeDetailModal = closeDetailModal;
window.openRatingModal = openRatingModal;
window.closeRatingModal = closeRatingModal;
window.saveRating = saveRating;
window.updateCardRating = updateCardRating;
window.deleteEntry = deleteEntry;
window.downloadEntry = downloadEntry;
window.logout = logout;
window.applyFilters = applyFilters;
window.showNotification = showNotification;
window.updateHeaderButtons = updateHeaderButtons;
window.openImageFullscreen = openImageFullscreen;

// Early sign-in button setup (before Firebase SDK loads)
// This ensures the button is clickable as soon as the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    try {
        const fbSignBtn = document.getElementById('fbSignBtn');
        try {
            console.log('[doFirebaseSignIn] Starting Firebase sign-in flow');
                    idToken = tokenResult.token; // Firebase ID token (server can accept Google id_token only)
                } catch (e) {
                    console.warn('Error getting ID token', e);
                }

                userEmail = user.email || null;
                isAuthenticated = true;
                updateAuthUI();
                showNotification('✓ Connecté: ' + (userEmail || ''), 'success');

                // If we have OAuth credential stored in sessionStorage by sign-in flow, use it
                const googleIdToken = sessionStorage.getItem('google_id_token');
                if (googleIdToken) {
                    // send to server for login verification
                    const form = new FormData();
                    form.append('action','login');
                    form.append('id_token', googleIdToken);
                    if (window.csrfToken) form.append('csrf_token', window.csrfToken);
                    fetch(window.location.href, { method: 'POST', body: form }).catch(()=>{});
                    sessionStorage.removeItem('google_id_token');
                }
            } else {
                isAuthenticated = false;
                userEmail = null;
                updateAuthUI();
            }
        });
    } catch (e) {
        console.error('Firebase init error', e);
    }
}

async function doFirebaseSignIn() {
    debugger
    try {
        console.log('[doFirebaseSignIn] Starting Firebase sign-in flow');
                    // batch categorize controls
                    const batchSel = document.getElementById('batchCategorySelect');
                    const applyBtn = document.getElementById('btnApplyCategory');
                    if (applyBtn && batchSel) {
                        applyBtn.addEventListener('click', async function(){
                            const cat = batchSel.value;
                            if (!cat) { showNotification('Choisissez une catégorie', 'error'); return; }
                            // collect selected row ids
                            const checked = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(i=>i.value);
                            if (checked.length === 0) { showNotification('Aucune entrée sélectionnée', 'error'); return; }
                            setLoading(true);
                            try {
                                // send set_category for each selected id
                                await Promise.all(checked.map(id=>{
                                    const f = new FormData();
                                    f.append('action','set_category');
                                    f.append('answer_id', id);
                                    f.append('category', cat);
                                    if (window.csrfToken) f.append('csrf_token', window.csrfToken);
                                    return fetch(window.location.href, { method: 'POST', body: f }).then(r=>r.json()).catch(()=>({success:false}));
                                }));
                                // update UI
                                checked.forEach(id => {
                                    // update table row category cell
                                    const tr = document.querySelector(`tr[data-id=\"${id}\"]`);
                                    if (tr) {
                                        const catCell = tr.querySelector('td:nth-child(8)');
                                        if (catCell) catCell.textContent = cat;
                                        tr.classList.remove('selected');
                                        const cb = tr.querySelector('.row-checkbox'); if (cb) cb.checked = false;
                                    }
                                    // update card badge if present
                                    document.querySelectorAll(`[data-id=\"${id}\"] .card-badge`).forEach(b=>b.textContent = cat);
                                });
                                showNotification('Catégorie appliquée ✓', 'success');
                            } catch(e) { showNotification('Erreur lors de l\'application', 'error'); }
                            finally { setLoading(false); }
                        });
                    }
        // Ensure firebase SDK is available; if not, try to load it dynamically
        if (typeof firebase === 'undefined' || !firebase.auth) {
            console.warn('[doFirebaseSignIn] Firebase SDK not present. Attempting to load dynamically');
            await new Promise((resolve, reject) => {
                let loaded = 0;
                const scripts = [
                    'https://www.gstatic.com/firebasejs/9.22.2/firebase-app-compat.js',
                    'https://www.gstatic.com/firebasejs/9.22.2/firebase-auth-compat.js'
                ];
                scripts.forEach(src => {
                    const s = document.createElement('script');
                    s.src = src;
                    s.async = true;
                    s.onload = () => { loaded++; if (loaded === scripts.length) resolve(); };
                    s.onerror = (e) => { console.error('[doFirebaseSignIn] Error loading', src, e); reject(new Error('Failed to load firebase scripts')); };
                    document.head.appendChild(s);
                });
            }).catch(err => {
                console.error('[doFirebaseSignIn] Dynamic load failed', err);
                showNotification('Impossible de charger les scripts d\'authentification. Vérifiez les bloqueurs.', 'error');
                throw err;
            });
        }

        if (!window.firebaseInitialized && window.firebaseConfig) {
            try { await initFirebaseAuth(); window.firebaseInitialized = true; } catch(e) { console.warn('initFirebaseAuth error', e); }
        }

        const provider = new firebase.auth.GoogleAuthProvider();
        provider.addScope('email');
        provider.setCustomParameters({ prompt: 'select_account' });
        const result = await firebase.auth().signInWithPopup(provider);
        console.log('[doFirebaseSignIn] Popup completed, user:', result.user.email);
        // credential contains the Google ID token
        const credential = result.credential;
        const googleIdToken = credential && credential.idToken ? credential.idToken : null;
        if (googleIdToken) {
            console.log('[doFirebaseSignIn] Got Google ID token, sending to server');
            // store temporarily and send to server to create session
            const form = new FormData();
            form.append('action','login');
            form.append('id_token', googleIdToken);
            if (window.csrfToken) form.append('csrf_token', window.csrfToken);
            // send and await
            const resp = await fetch(window.location.href, { method: 'POST', body: form });
            try { const data = await resp.json(); if (data.success) { userEmail = data.email || result.user.email; isAuthenticated = true; updateAuthUI(); } }
            catch(e) { console.warn('login response parse error', e); }
        }
        // Keep googleIdToken in sessionStorage briefly so onAuthStateChanged can use it (if needed)
        if (googleIdToken) sessionStorage.setItem('google_id_token', googleIdToken);
    } catch (e) {
        console.error('[doFirebaseSignIn] Error:', e);
        // If popup is blocked by extension or browser, notify the user with instruction
        if (e && e.code && e.code === 'auth/popup-blocked') {
            showNotification('Popup bloquée : autorisez les popups pour ce site', 'error');
        } else {
            showNotification('Erreur d\'authentification — regardez la console (Ctrl+Shift+J)', 'error');
        }
    }
}

function updateAuthUI() {
    const userInfo = document.getElementById('userInfo');
    const logoutBtn = document.getElementById('logoutBtn');
    const signContainer = document.getElementById('googleSignInBtn');
    const adminToolbar = document.getElementById('adminToolbar');
    
    if (isAuthenticated) {
        if (userInfo) {
            userInfo.textContent = '✓ ' + userEmail;
            userInfo.style.display = 'block';
        }
        if (logoutBtn) logoutBtn.style.display = 'block';
        if (signContainer) signContainer.style.display = 'none';
        if (adminToolbar) adminToolbar.style.display = 'flex';
        
        // Show authenticated-only elements
        document.querySelectorAll('.card-actions, .btn-download, .btn-delete, .btn-rate').forEach(el => {
            el.style.display = '';
        });
        document.querySelectorAll('.row-checkbox, #selectAllCheckbox').forEach(el => {
            const parentTd = el.closest('td');
            const parentTh = el.closest('th');
            if (parentTd) parentTd.style.display = '';
            if (parentTh) parentTh.style.display = '';
        });
        // Show Actions column in table
        document.querySelectorAll('th').forEach(th => {
            if (th.textContent.trim() === 'Actions') th.style.display = '';
        });
        document.querySelectorAll('tr[data-id] td:last-child').forEach(td => {
            if (td.querySelector('.btn-rate, .btn-download, .btn-delete')) {
                td.style.display = '';
            }
        });
    } else {
        if (userInfo) userInfo.style.display = 'none';
        if (logoutBtn) logoutBtn.style.display = 'none';
        if (signContainer) signContainer.style.display = 'flex';
        if (adminToolbar) adminToolbar.style.display = 'none';
        
        // Hide authenticated-only elements
        document.querySelectorAll('.card-actions').forEach(el => {
            el.style.display = 'none';
        });
        document.querySelectorAll('.row-checkbox, #selectAllCheckbox').forEach(el => {
            const parentTd = el.closest('td');
            const parentTh = el.closest('th');
            if (parentTd) parentTd.style.display = 'none';
            if (parentTh) parentTh.style.display = 'none';
        });
        // Hide Actions column in table
        document.querySelectorAll('th').forEach(th => {
            if (th.textContent.trim() === 'Actions') th.style.display = 'none';
        });
        document.querySelectorAll('tr[data-id] td:last-child').forEach(td => {
            if (td.querySelector('.btn-rate, .btn-download, .btn-delete')) {
                td.style.display = 'none';
            }
        });
    }
    updateHeaderButtons();
}

function logout() {
    // call server to destroy session
    const form = new FormData();
    form.append('action', 'logout');
    if (window.csrfToken) form.append('csrf_token', window.csrfToken);
    setLoading(true);
    fetch(window.location.href, { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            isAuthenticated = false;
            userEmail = null;
            updateAuthUI();
            showNotification(data.message || 'Déconnecté', 'info');
        })
        .catch(() => {
            isAuthenticated = false;
            userEmail = null;
            updateAuthUI();
            showNotification('Erreur lors de la déconnexion', 'error');
        })
        .finally(() => setLoading(false));
}

function setLoading(show) {
    const loader = document.getElementById('loadingIndicator');
    if (!loader) return;
    loader.style.display = show ? 'block' : 'none';
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        try {
            if (typeof google !== 'undefined' && google.accounts) {
                // Only render sign-in button when server does not already have an authenticated session
                if (!window.serverAuth) {
                } else {
                    // hide sign-in container when already authenticated server-side
                    const btn = document.getElementById('googleSignInBtn');
                    if (btn) btn.style.display = 'none';
                }
            }
        } catch (e) {
            console.log('Google Sign-In initialization:', e.message);
        }
        
            // initialize firebase auth and update UI
            initFirebaseAuth().then(()=>updateAuthUI());
    }, 500);
});

// Render small charts in footer
function renderCharts() {
    const data = window.chartData || {};
    const container = document.querySelector('footer .stats-grid');
    if (!container) return;

    // wilaya chart
    const wilaya = data.wilaya || {};
    const ratings = data.ratings || {};
    const categories = data.categories || {};

    const chartHtml = `
        <div class="stat-card" style="grid-column: span 2;">
            <div class="stat-card-title">Top Wilayas</div>
            <div class="chart-list">
                ${Object.entries(wilaya).map(([k,v])=>{
                    const pct = data.totals && data.totals.overall ? Math.round(v / data.totals.overall * 100) : 0;
                    return `<div class="chart-row"><div class="chart-label">${h(k)}</div><div class="chart-bar"><div class="chart-fill" style="width:${pct}%"></div></div><div class="chart-value">${v}</div></div>`;
                }).join('')}
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Distribution des notes</div>
            <div class="rating-dist">
                ${Object.keys(ratings).sort((a,b)=>b-a).map(r=>{
                    const c = ratings[r] || 0; const pct = data.totals && data.totals.total ? Math.round(c / data.totals.total * 100) : 0;
                    return `<div class="rating-row"><div class="rating-label">${r}★</div><div class="rating-bar"><div class="rating-fill" style="width:${pct}%"></div></div><div class="rating-count">${c}</div></div>`;
                }).join('')}
            </div>
        </div>
    `;

    // append to container
    container.insertAdjacentHTML('beforeend', chartHtml);
}

// small helper for escaping
function h(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

document.addEventListener('DOMContentLoaded', function() {
    // show admin toolbar if server authenticated
    try {
        if (window.serverAuth) {
            const tb = document.getElementById('adminToolbar');
            if (tb) tb.style.display = 'flex';
            // set perPage from query string
            const qs = new URLSearchParams(window.location.search);
            const per = qs.get('per_page') || '20';
            const sel = document.getElementById('perPageSelect');
            if (sel) sel.value = per;
            // attach handlers
            const btnAll = document.getElementById('btnExportAll');
            if (btnAll) btnAll.addEventListener('click', ()=>{ window.open(window.location.pathname + '?export=csv', '_blank'); });
            const btnRated = document.getElementById('btnExportRated');
            if (btnRated) btnRated.addEventListener('click', ()=>{ window.open(window.location.pathname + '?export=csv&sort=rating_high', '_blank'); });
            if (sel) sel.addEventListener('change', function(){ qs.set('per_page', this.value); qs.set('page', 1); window.location.search = qs.toString(); });
        }
    } catch(e) { console.error(e); }

    renderCharts();
});

// Filtering with debounce for better performance
let filterTimeout;
function debounceFilter(callback, delay = 500) {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(callback, delay);
}

document.getElementById('searchInput').addEventListener('input', function() {
    debounceFilter(applyFilters);
});
document.getElementById('wilayaFilter').addEventListener('change', applyFilters);
document.getElementById('dimensionFilter').addEventListener('change', applyFilters);
document.getElementById('categoryFilter').addEventListener('change', applyFilters);
document.getElementById('ratingFilter').addEventListener('change', applyFilters);

function applyFilters() {
    const params = new URLSearchParams(window.location.search);
    const search = document.getElementById('searchInput').value.trim();
    const wilaya = document.getElementById('wilayaFilter').value;
    const dimension = document.getElementById('dimensionFilter').value;
    const category = document.getElementById('categoryFilter') ? document.getElementById('categoryFilter').value : '';
    const minRating = document.getElementById('ratingFilter').value;

    if (search) params.set('search', search); else params.delete('search');
    if (wilaya) params.set('filter_wilaya', wilaya); else params.delete('filter_wilaya');
    if (dimension) params.set('filter_dimension', dimension); else params.delete('filter_dimension');
    if (category) params.set('filter_category', category); else params.delete('filter_category');
    if (minRating) params.set('min_rating', minRating); else params.delete('min_rating');
    params.delete('page');

    window.location.search = params.toString();
}

// Sorting
document.getElementById('sortBy').addEventListener('change', function() {
    window.location.href = '?sort=' + this.value;
});

// View toggle with persistence
document.getElementById('viewCards').addEventListener('click', function() {
    document.getElementById('cardsView').style.display = 'grid';
    document.getElementById('tableView').style.display = 'none';
    document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    localStorage.setItem('viewMode', 'cards');
});

document.getElementById('viewTable').addEventListener('click', function() {
    document.getElementById('cardsView').style.display = 'none';
    document.getElementById('tableView').style.display = 'block';
    document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    localStorage.setItem('viewMode', 'table');
});

// Restore view mode on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('viewMode');
    if (savedView === 'table') {
        document.getElementById('viewTable').click();
    }
});

// Detail Modal
function openDetailModal(id) {
    const answer = allAnswers.find(a => a.id === id);
    if (!answer) return;
    
    // set current selection for header actions
    selectedEntryId = id;
    window.selectedEntryId = selectedEntryId;
    updateHeaderButtons();
    
    const html = `
        <div class="detail-grid">
            ${answer.photo ? `
                <div class="detail-image">
                    <img id="detailMainImage" src="/pub/media/amasty/amcustomform/${answer.photo}" 
                         alt="${answer.title}"
                         style="cursor:pointer"
                         title="Afficher en plein écran"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22400%22 height=%22300%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2220%22 fill=%22%23999%22 text-anchor=%22middle%22 dy=%22.3em%22%3EImage not found%3C/text%3E%3C/svg%3E'">
                </div>
            ` : ''}
            
            <div class="detail-section">
                <div class="detail-label">Titre de l'œuvre</div>
                <div class="detail-value">${answer.title}</div>
            </div>
            
            <div class="detail-section">
                <div class="detail-label">Artiste</div>
                <div class="detail-value">${answer.firstname} ${answer.lastname}</div>
            </div>
            
            <div class="detail-section">
                <div class="detail-label">Wilaya</div>
                <div class="detail-value">${answer.wilaya}</div>
            </div>
            
            <div class="detail-section">
                <div class="detail-label">Dimension</div>
                <div class="detail-value">${answer.dimension}</div>
            </div>
            
            <div class="detail-section">
                <div class="detail-label">Catégorie</div>
                <div class="detail-value">
                    ${window.serverAuth ? `
                        <select id="detailCategorySelect">
                            <option value="">Uncategorized</option>
                            <option value="Painting" ${answer.category==='Painting' ? 'selected' : ''}>Painting</option>
                            <option value="Sculpture" ${answer.category==='Sculpture' ? 'selected' : ''}>Sculpture</option>
                            <option value="Photography" ${answer.category==='Photography' ? 'selected' : ''}>Photography</option>
                            <option value="Other" ${answer.category==='Other' ? 'selected' : ''}>Other</option>
                        </select>
                        <button class="btn-small" id="saveCategoryBtn">Save</button>
                    ` : `
                        ${answer.category}
                    `}
                </div>
            </div>
            
            <div class="detail-section">
                <div class="detail-label">Note</div>
                <div class="detail-value">${answer.rating > 0 ? answer.rating.toFixed(1) + ' ⭐' : 'Non notée'}</div>
            </div>
            
            <div class="detail-section">
                <div class="detail-label">Techniques utilisées</div>
                <div class="detail-value">${answer.techniques}</div>
            </div>
            
            <div class="detail-section">
                <div class="detail-label">Source d'inspiration</div>
                <div class="detail-value">${answer.source}</div>
            </div>
            
            <div class="detail-section">
                <div class="detail-label">Email</div>
                <div class="detail-value"><a href="mailto:${answer.email}" style="color:#ff8a00;text-decoration:none;">${answer.email}</a></div>
            </div>
            
            <div class="detail-section">
                <div class="detail-label">Téléphone</div>
                <div class="detail-value">${answer.phone1}</div>
            </div>
            
            <div class="detail-section">
                <div class="detail-label">Âge</div>
                <div class="detail-value">${answer.age}</div>
            </div>
            
            <div class="detail-section">
                <div class="detail-label">Date de soumission</div>
                <div class="detail-value">${new Date(answer.created_at).toLocaleDateString('fr-FR', {year:'numeric',month:'long',day:'numeric',hour:'2-digit',minute:'2-digit'})}</div>
            </div>
        </div>
    `;
    
    document.getElementById('detailTitle').textContent = answer.title;
    document.getElementById('detailBody').innerHTML = html;
    document.getElementById('detailModal').classList.add('show');

    // attach fullscreen behavior to main image
    const imgEl = document.getElementById('detailMainImage');
    if (imgEl) {
        imgEl.addEventListener('click', function() {
            openImageFullscreen(this.src);
        });
    }

    // setup save category handler if auth
    if (window.serverAuth) {
        const saveBtn = document.getElementById('saveCategoryBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                const sel = document.getElementById('detailCategorySelect');
                const category = sel ? sel.value : '';
                const form = new FormData();
                form.append('action', 'set_category');
                form.append('answer_id', id);
                form.append('category', category);
                if (window.csrfToken) form.append('csrf_token', window.csrfToken);
                fetch(window.location.href, { method: 'POST', body: form })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            // update in-memory data and UI
                            answer.category = category;
                            document.querySelectorAll(`[data-id="${id}"] .card-badge`).forEach(b => b.textContent = category);
                            showNotification('Catégorie mise à jour', 'success');
                        } else {
                            showNotification(data.message || 'Erreur', 'error');
                        }
                    })
                    .catch(e => showNotification('Erreur: ' + e.message, 'error'));
            });
        }
    }
    updateHeaderButtons();
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('show');
}

// Rating Modal
function openRatingModal(id, rating) {
    if (!isAuthenticated) {
        showNotification('Veuillez vous connecter pour noter', 'error');
        return;
    }
    currentRatingId = id;
    currentRatingValue = rating;
    updateStars(rating);
    document.getElementById('ratingModal').classList.add('show');
}

function closeRatingModal() {
    document.getElementById('ratingModal').classList.remove('show');
}

function updateStars(rating) {
    document.querySelectorAll('#modalStars i').forEach((star, idx) => {
        if (idx < rating) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
}

document.querySelectorAll('#modalStars i').forEach(star => {
    star.addEventListener('click', function() {
        currentRatingValue = parseInt(this.getAttribute('data-rating'));
        updateStars(currentRatingValue);
    });
});

function saveRating() {
    if (!currentRatingId || currentRatingValue === 0) return;
    
    const formData = new FormData();
    formData.append('action', 'rate');
    formData.append('answer_id', currentRatingId);
    formData.append('rating', currentRatingValue);
    if (window.csrfToken) formData.append('csrf_token', window.csrfToken);
    setLoading(true);
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                updateCardRating(currentRatingId, currentRatingValue);
                showNotification('Note enregistrée ✓', 'success');
                closeRatingModal();
            } else {
                showNotification(data.message || 'Erreur', 'error');
            }
        })
        .catch(e => showNotification('Erreur: ' + e.message, 'error'))
        .finally(() => setLoading(false));
}

function updateCardRating(id, rating) {
    document.querySelectorAll(`[data-id="${id}"]`).forEach(el => {
        el.setAttribute('data-rating', rating);
        const ratingSpan = el.querySelector('.card-rating span');
        if (ratingSpan) ratingSpan.textContent = rating.toFixed(1);
        const stars = el.querySelectorAll('.stars i');
        stars.forEach((s, idx) => {
            if (idx < rating) s.classList.add('filled');
            else s.classList.remove('filled');
        });
    });
}

// Delete Entry
function deleteEntry(id) {
    if (!isAuthenticated) {
        showNotification('Veuillez vous connecter pour supprimer', 'error');
        return;
    }
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette entrée ?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('answer_id', id);
    if (window.csrfToken) formData.append('csrf_token', window.csrfToken);
    setLoading(true);
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll(`[data-id="${id}"]`).forEach(el => {
                    el.style.transition = 'opacity 0.3s ease';
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 350);
                });
                showNotification('Entrée supprimée ✓', 'success');
            } else {
                showNotification(data.message || 'Erreur', 'error');
            }
        })
        .catch(e => showNotification('Erreur: ' + e.message, 'error'))
        .finally(() => setLoading(false));
}

// Download Entry
function downloadEntry(id) {
    // Prefer server-side single-entry export (CSV) when authenticated
    if (isAuthenticated) {
        const exportUrl = window.location.pathname + '?export=csv&id=' + encodeURIComponent(id);
        // open in new tab to trigger download
        window.open(exportUrl, '_blank');
        showNotification('Téléchargement démarré', 'success');
        return;
    }

    // fallback: client-side JSON download
    const answer = allAnswers.find(a => a.id === id);
    if (!answer) return;

    const data = {
        title: answer.title,
        artist: answer.firstname + ' ' + answer.lastname,
        wilaya: answer.wilaya,
        dimension: answer.dimension,
        techniques: answer.techniques,
        inspiration: answer.source,
        email: answer.email,
        phone: answer.phone1,
        rating: answer.rating,
        date: answer.created_at
    };

    const json = JSON.stringify(data, null, 2);
    const blob = new Blob([json], { type: 'application/json;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `artwork-${id}-${Date.now()}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showNotification('Téléchargement en cours ✓', 'success');
}

// Notifications
function showNotification(msg, type) {
    const notif = document.getElementById('notification');
    notif.textContent = msg;
    notif.className = `notification ${type}`;
    notif.style.display = 'block';
    setTimeout(() => {
        notif.style.animation = 'slideInRight 0.3s ease reverse';
        setTimeout(() => notif.style.display = 'none', 300);
    }, 3000);
}

// helper: update header buttons state based on selection/auth
function updateHeaderButtons() {
    const rateBtn = document.getElementById('btnRateHeader');
    const downloadBtn = document.getElementById('btnDownloadHeader');
    const deleteBtn = document.getElementById('btnDeleteHeader');
    const hasSelection = !!selectedEntryId;
    if (rateBtn) { rateBtn.disabled = !hasSelection || !isAuthenticated; rateBtn.classList.toggle('disabled', !hasSelection || !isAuthenticated); }
    if (downloadBtn) { downloadBtn.disabled = !hasSelection; downloadBtn.classList.toggle('disabled', !hasSelection); }
    if (deleteBtn) { deleteBtn.disabled = !hasSelection || !isAuthenticated; deleteBtn.classList.toggle('disabled', !hasSelection || !isAuthenticated); }
}

// helper: show an image full-screen overlay
function openImageFullscreen(src) {
    const overlay = document.createElement('div');
    overlay.id = 'imgFullscreenOverlay';
    overlay.style.position = 'fixed';
    overlay.style.top = '0'; overlay.style.left = '0'; overlay.style.width = '100%'; overlay.style.height = '100%';
    overlay.style.background = 'rgba(0,0,0,0.95)';
    overlay.style.zIndex = '3000';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.cursor = 'zoom-out';
    const img = document.createElement('img');
    img.src = src;
    img.style.maxWidth = '95%';
    img.style.maxHeight = '95%';
    img.style.objectFit = 'contain';
    img.style.boxShadow = '0 20px 60px rgba(0,0,0,0.6)';
    overlay.appendChild(img);
    overlay.addEventListener('click', ()=> { overlay.remove(); });
    document.body.appendChild(overlay);
}

// wire header action buttons and selection behavior
document.addEventListener('DOMContentLoaded', function() {
    try {
        const rateBtn = document.getElementById('btnRateHeader');
        const downloadBtn = document.getElementById('btnDownloadHeader');
        const deleteBtn = document.getElementById('btnDeleteHeader');

        function headerAction(action) {
            if (!selectedEntryId) { showNotification('Sélectionnez d\'abord une entrée', 'error'); return; }
            if (action === 'rate') openRatingModal(selectedEntryId, 0);
            if (action === 'download') downloadEntry(selectedEntryId);
            if (action === 'delete') deleteEntry(selectedEntryId);
        }

        if (rateBtn) rateBtn.addEventListener('click', ()=> headerAction('rate'));
        if (downloadBtn) downloadBtn.addEventListener('click', ()=> headerAction('download'));
        if (deleteBtn) deleteBtn.addEventListener('click', ()=> headerAction('delete'));

        // when clicking a card, set selection (so header buttons operate on it)
        document.querySelectorAll('[data-id]').forEach(el=>{
            el.addEventListener('click', function(e){
                const target = e.target;
                if (target && (target.closest('.btn-small') || target.closest('button'))) return;
                
                // remove selection from previous card
                document.querySelectorAll('[data-id]').forEach(c => c.classList.remove('selected'));
                
                // set new selection
                selectedEntryId = this.getAttribute('data-id');
                
                                // add visual selection indicator
                                this.classList.add('selected');
                
                window.selectedEntryId = selectedEntryId;
                updateHeaderButtons();
            });
        });

        updateHeaderButtons();
    } catch (e) { console.warn('Header wiring init error', e); }
});

// Close modals on outside click
document.getElementById('detailModal').addEventListener('click', e => {
    if (e.target.id === 'detailModal') closeDetailModal();
});

document.getElementById('ratingModal').addEventListener('click', e => {
    if (e.target.id === 'ratingModal') closeRatingModal();
});
