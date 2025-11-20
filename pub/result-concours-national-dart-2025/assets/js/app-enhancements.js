// Enhancements: table selection, select-all, view persistence, batch-gating
(function(){
    function initTableSelection(){
        const table = document.getElementById('tableView');
        const selectAll = document.getElementById('selectAllCheckbox');
        if (!table) return;
        // attach row checkbox handlers
        function bindRowCheckboxes(){
            const rows = Array.from(document.querySelectorAll('#tableView tbody tr'));
            rows.forEach(tr => {
                const cb = tr.querySelector('.row-checkbox');
                if (!cb) return;
                cb.removeEventListener('change', rowCheckboxChanged);
                cb.addEventListener('change', rowCheckboxChanged);
            });
            updateSelectAllState();
        }
        function rowCheckboxChanged(e){
            const tr = this.closest('tr');
            if (!tr) return;
            if (this.checked) tr.classList.add('selected'); else tr.classList.remove('selected');
            updateSelectAllState();
        }
        function updateSelectAllState(){
            if (!selectAll) return;
            const checkboxes = Array.from(document.querySelectorAll('#tableView tbody .row-checkbox'));
            if (checkboxes.length === 0) { selectAll.checked = false; selectAll.indeterminate = false; return; }
            const checked = checkboxes.filter(c=>c.checked).length;
            selectAll.checked = checked === checkboxes.length;
            selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
        }
        if (selectAll){
            selectAll.removeEventListener('change', selectAllHandler);
            selectAll.addEventListener('change', selectAllHandler);
        }
        function selectAllHandler(e){
            const checkboxes = Array.from(document.querySelectorAll('#tableView tbody .row-checkbox'));
            checkboxes.forEach(c=>{ c.checked = !!e.target.checked; const tr = c.closest('tr'); if (tr) tr.classList.toggle('selected', e.target.checked); });
        }
        // expose rebind
        bindRowCheckboxes();
        // also rebind after any content changes (for example pagination repopulates rows)
        // simple MutationObserver on tbody
        const tbody = table.querySelector('tbody');
        if (tbody) {
            const mo = new MutationObserver(()=>{ setTimeout(bindRowCheckboxes, 50); });
            mo.observe(tbody, { childList: true, subtree: true });
        }
    }

    function initViewPersistence(){
        const btnCards = document.getElementById('viewCards');
        const btnTable = document.getElementById('viewTable');
        const cards = document.getElementById('cardsView');
        const table = document.getElementById('tableView');
        if (!btnCards || !btnTable || !cards || !table) return;
        const saved = localStorage.getItem('viewMode');
        if (saved === 'table') {
            cards.style.display = 'none';
            table.style.display = 'block';
            btnCards.classList.remove('active'); btnTable.classList.add('active');
        } else if (saved === 'cards') {
            cards.style.display = 'grid';
            table.style.display = 'none';
            btnTable.classList.remove('active'); btnCards.classList.add('active');
        }
        btnCards.addEventListener('click', ()=>{ localStorage.setItem('viewMode','cards'); cards.style.display='grid'; table.style.display='none'; });
        btnTable.addEventListener('click', ()=>{ localStorage.setItem('viewMode','table'); cards.style.display='none'; table.style.display='block'; });
    }

    function initBatchGating(){
        const applyBtn = document.getElementById('btnApplyCategory');
        const batchSel = document.getElementById('batchCategorySelect');
        const table = document.getElementById('tableView');
        if (!applyBtn) return;
        function updateState(){
            const visibleTable = table && window.getComputedStyle(table).display !== 'none';
            applyBtn.disabled = !visibleTable;
        }
        // ensure clicking when not table shows a message
        applyBtn.addEventListener('click', function(e){
            const visibleTable = table && window.getComputedStyle(table).display !== 'none';
            if (!visibleTable) { showNotification('La catégorisation en masse n\'est disponible qu\'en mode tableau.', 'error'); e.preventDefault(); return; }
            // proceed: collect selected and send bulk request (client-side behavior already exists elsewhere)
            const cat = batchSel ? batchSel.value : '';
            if (!cat) { showNotification('Choisissez une catégorie', 'error'); return; }
            const checked = Array.from(document.querySelectorAll('#tableView tbody .row-checkbox:checked')).map(i=>i.value);
            if (checked.length === 0) { showNotification('Aucune entrée sélectionnée', 'error'); return; }
            // send a single bulk request to server if endpoint exists, otherwise fallback to per-id
            const f = new FormData();
            f.append('action','set_category_bulk');
            f.append('category', cat);
            f.append('ids', JSON.stringify(checked));
            if (window.csrfToken) f.append('csrf_token', window.csrfToken);
            setLoading(true);
            fetch(window.location.href, { method: 'POST', body: f })
                .then(r=>r.json())
                .then(data=>{
                    if (data && data.success) {
                        // update UI
                        checked.forEach(id=>{
                            const tr = document.querySelector(`#tableView tbody tr[data-id='${id}']`);
                            if (tr) {
                                const catCell = tr.querySelector('td:nth-child(8)'); if (catCell) catCell.textContent = cat;
                                const cb = tr.querySelector('.row-checkbox'); if (cb) cb.checked = false; tr.classList.remove('selected');
                            }
                            document.querySelectorAll(`[data-id="${id}"] .card-badge`).forEach(b=>b.textContent = cat);
                        });
                        showNotification('Catégorie appliquée ✓', 'success');
                    } else {
                        // fallback to per-id if bulk not supported
                        return Promise.reject(data && data.message ? data.message : 'bulk failed');
                    }
                })
                .catch(err=>{
                    console.warn('Bulk apply failed, falling back', err);
                    // fallback: per-id requests
                    Promise.all(checked.map(id=>{
                        const ff = new FormData(); ff.append('action','set_category'); ff.append('answer_id', id); ff.append('category', cat); if (window.csrfToken) ff.append('csrf_token', window.csrfToken);
                        return fetch(window.location.href, { method: 'POST', body: ff }).then(r=>r.json()).catch(()=>({success:false}));
                    })).then(()=>{
                        checked.forEach(id=>{
                            const tr = document.querySelector(`#tableView tbody tr[data-id='${id}']`);
                            if (tr) {
                                const catCell = tr.querySelector('td:nth-child(8)'); if (catCell) catCell.textContent = cat;
                                const cb = tr.querySelector('.row-checkbox'); if (cb) cb.checked = false; tr.classList.remove('selected');
                            }
                            document.querySelectorAll(`[data-id="${id}"] .card-badge`).forEach(b=>b.textContent = cat);
                        });
                        showNotification('Catégorie appliquée ✓', 'success');
                    }).catch(e=>{ showNotification('Erreur lors de l\'application', 'error'); });
                })
                .finally(()=>setLoading(false));
        });
        // initial state
        setTimeout(updateState, 200);
        // also observe table visibility changes
        const obs = new MutationObserver(()=>updateState());
        if (table) obs.observe(table, { attributes: true, attributeFilter: ['style', 'class'] });
    }

    // initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function(){
        try { initTableSelection(); } catch(e){console.warn('initTableSelection failed', e);} 
        try { initViewPersistence(); } catch(e){console.warn('initViewPersistence failed', e);} 
        try { initBatchGating(); } catch(e){console.warn('initBatchGating failed', e);} 
    });
})();
