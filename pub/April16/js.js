document.addEventListener('DOMContentLoaded', function () {
    const csvFilePath = 'cleaned_data.csv';
    const albumGrid = document.getElementById('album-grid');
    const loadingElement = document.getElementById('loading');
    const errorElement = document.getElementById('error');
    const statsElement = document.getElementById('stats');
    const totalCountEl = document.getElementById('total-count');
    const yearCountEl = document.getElementById('year-count');
    const wilayaCountEl = document.getElementById('wilaya-count');
    
    const yearFilter = document.getElementById('year-filter');
    const categoryFilter = document.getElementById('category-filter');
    const searchFilter = document.getElementById('search-filter');
    
    const noResultsEl = document.getElementById('no-results');
    const modal = document.getElementById("imageModal");
    const modalImg = document.getElementById("fullImage");
    const captionText = document.getElementById("caption");

    let allParticipants = [];
    let filteredParticipants = [];
    const currentYear = 2026;
    
    // --- Performance Caches ---
    let categoryCache = {};
    let yearCache = {};
    let cacheValid = false;

    // --- Modal Handling ---
    albumGrid.addEventListener('click', (e) => {
        if (e.target.classList.contains('card-img')) {
            modal.style.display = "block";
            modalImg.src = e.target.src;
            modalImg.alt = e.target.alt;
            captionText.innerText = e.target.closest('.card').querySelector('.card-title').innerText;
            document.body.style.overflow = "hidden";
        }
    });

    document.querySelector(".modal-close").onclick = () => {
        modal.style.display = "none";
        document.body.style.overflow = "auto";
    };

    window.onclick = (e) => {
        if (e.target === modal) {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
        }
    };

    // --- Helper Functions ---
    function getVal(obj, keyword) {
        if (!obj) return '';
        const key = Object.keys(obj).find(k => k.toLowerCase().includes(keyword.toLowerCase()));
        return key ? obj[key].trim() : '';
    }

    function parseCSV(csvText) {
        const lines = csvText.split(/\r?\n/).filter(line => line.trim() !== '');
        if (lines.length === 0) return [];
        const headers = lines[0].split(',').map(h => h.trim().replace(/"/g, ''));
        return lines.slice(1).map(line => {
            const values = line.split(/,(?=(?:(?:[^"]*"){2})*[^"]*$)/).map(v => v.trim().replace(/"/g, ''));
            return headers.reduce((obj, header, i) => { 
                obj[header] = (values[i] || '').trim(); 
                return obj; 
            }, {});
        });
    }

    // --- Build Performance Caches ---
    function buildCaches() {
        categoryCache = { '6-8': [], '9-11': [], '12-14': [] };
        yearCache = { '2025': [], '2026': [] };
        
        allParticipants.forEach(p => {
            // Category cache by age range
            const ageRaw = p['Age / العمر'] || getVal(p, 'age');
            const age = parseInt(ageRaw);
            if (!isNaN(age)) {
                if (age >= 6 && age <= 8) categoryCache['6-8'].push(p);
                else if (age >= 9 && age <= 11) categoryCache['9-11'].push(p);
                else if (age >= 12 && age <= 14) categoryCache['12-14'].push(p);
            }
            
            // Year cache
            const date = getVal(p, 'created');
            if (date) {
                if (date.includes('2025')) yearCache['2025'].push(p);
                else if (date.includes('2026')) yearCache['2026'].push(p);
            }
        });
        
        cacheValid = true;
    }

    // --- Fast Filter with Caching ---
    function filterParticipants() {
        const yVal = yearFilter.value;
        const cVal = categoryFilter.value;
        const sVal = searchFilter.value.toLowerCase().trim();
        
        let candidates = allParticipants;
        
        // Use cached data when no search (much faster)
        if (!sVal) {
            if (cVal && yVal) {
                // Intersection of category + year using Set
                const catList = categoryCache[cVal] || [];
                const yearList = yearCache[yVal] || [];
                const yearSet = new Set(yearList);
                candidates = catList.filter(p => yearSet.has(p));
            } else if (cVal) {
                candidates = categoryCache[cVal] || [];
            } else if (yVal) {
                candidates = yearCache[yVal] || [];
            }
        } else {
            // Apply category/year first from cache, then search
            if (cVal) candidates = categoryCache[cVal] || [];
            else if (yVal) candidates = yearCache[yVal] || [];
            
            // Apply search filter
            if (sVal) {
                candidates = candidates.filter(p => {
                    const searchStr = JSON.stringify(p).toLowerCase();
                    return searchStr.includes(sVal);
                });
            }
        }
        
        filteredParticipants = candidates;
        updateDisplay();
    }

    // --- Get Category Badge ---
    function getCategoryBadge(ageRaw) {
        const age = parseInt(ageRaw);
        if (isNaN(age)) return '<span class="badge" style="background:#e5e7eb;color:#6b7280">N/A</span>';
        if (age >= 6 && age <= 8) return '<span class="badge badge-new">6-8 ans</span>';
        if (age >= 9 && age <= 11) return '<span class="badge" style="background:#dbeafe;color:#1d4ed8">9-11 ans</span>';
        if (age >= 12 && age <= 14) return '<span class="badge" style="background:#e9d5ff;color:#7c3aed">12-14 ans</span>';
        return `<span class="badge" style="background:#e5e7eb;color:#6b7280">${age} ans</span>`;
    }

    // --- Create Participant Card ---
    function createCard(p) {
        const name = `${getVal(p, 'prénom') || getVal(p, 'إسم') || 'Inconnu'} ${getVal(p, 'nom') || getVal(p, 'لقب') || ''}`.trim();
        const photo = getVal(p, 'photo') || getVal(p, 'صورة') || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(name) + '&background=0066cc&color=fff&size=300';
        const ageValue = p['Age / العمر'] || getVal(p, 'age') || 'N/A';
        const wilayaVal = getVal(p, 'wilaya') || 'N/A';
        const phoneValue = getVal(p, 'téléphone') || getVal(p, 'phone') || 'N/A';
        const dateVal = getVal(p, 'created') || '';

        return `
        <article class="card">
            <div class="card-img-wrapper">
                <img src="${photo}" class="card-img" alt="Portrait de ${name}" loading="lazy">
            </div>
            <div class="card-body">
                <h2 class="card-title">${name}</h2>
                <div class="info-list">
                    <div class="info-item">
                        <i class="fas fa-user-clock"></i>
                        <span>${getCategoryBadge(ageValue)} &nbsp;· ${ageValue} ans</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>${wilayaVal}</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <span>${phoneValue}</span>
                    </div>
                    ${dateVal ? `
                    <div class="info-item">
                        <i class="fas fa-calendar"></i>
                        <span style="color:var(--text-light)">${dateVal.split(' ')[0]}</span>
                    </div>` : ''}
                </div>
            </div>
        </article>`;
    }

    // --- Update Display ---
    function updateDisplay() {
        loadingElement.style.display = 'none';
        statsElement.style.display = 'flex';
        noResultsEl.style.display = filteredParticipants.length ? 'none' : 'block';
        
        if (filteredParticipants.length === 0) {
            noResultsEl.innerHTML = `
                <i class="fas fa-search-minus"></i>
                <p>Aucun participant trouvé avec ces critères</p>
            `;
        }
        
        albumGrid.innerHTML = filteredParticipants.map(p => createCard(p)).join('');
        
        // Update stats
        totalCountEl.innerText = allParticipants.length;
        wilayaCountEl.innerText = new Set(allParticipants.map(p => getVal(p, 'wilaya'))).size;
        yearCountEl.innerText = allParticipants.filter(p => getVal(p, 'created').includes(currentYear)).length;
    }

    // --- Initialization ---
    fetch(csvFilePath)
        .then(r => {
            if (!r.ok) throw new Error('Network response error');
            return r.text();
        })
        .then(text => {
            allParticipants = parseCSV(text);
            buildCaches();
            yearFilter.value = currentYear;
            filterParticipants();
        })
        .catch(err => {
            console.error('Error loading data:', err);
            loadingElement.style.display = 'none';
            errorElement.style.display = 'block';
            errorElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Erreur de chargement des données.`;
        });

    // --- Event Listeners ---
    const applyFilters = () => filterParticipants();
    const resetFilters = () => {
        yearFilter.value = currentYear;
        categoryFilter.value = '';
        searchFilter.value = '';
        filterParticipants();
    };

    document.getElementById('apply-filters').onclick = applyFilters;
    document.getElementById('reset-filters').onclick = resetFilters;
    
    yearFilter.addEventListener('change', filterParticipants);
    categoryFilter.addEventListener('change', filterParticipants);
    
    // Debounced search for smooth typing
    let searchTimeout;
    searchFilter.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(filterParticipants, 200);
    });
    
    // Clear search on Escape
    searchFilter.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') searchFilter.value = '';
        if (e.key === 'Enter') filterParticipants();
    });
});