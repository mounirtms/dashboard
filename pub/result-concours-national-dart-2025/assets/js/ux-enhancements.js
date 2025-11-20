// Enhanced UX and Performance Optimizations
(function() {
    'use strict';
    
    // Lazy loading images for better performance
    function initLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            observer.unobserve(img);
                        }
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }
    
    // Keyboard shortcuts for better UX
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Esc to close modals
            if (e.key === 'Escape') {
                if (typeof closeDetailModal === 'function') closeDetailModal();
                if (typeof closeRatingModal === 'function') closeRatingModal();
            }
            
            // Ctrl+F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            // Ctrl+K for quick actions
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const adminToolbar = document.getElementById('adminToolbar');
                if (adminToolbar && adminToolbar.style.display !== 'none') {
                    const firstBtn = adminToolbar.querySelector('button');
                    if (firstBtn) firstBtn.focus();
                }
            }
        });
    }
    
    // Auto-save form state
    function initFormStatePersistence() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput && !searchInput.value) {
            const savedSearch = sessionStorage.getItem('lastSearch');
            if (savedSearch) searchInput.value = savedSearch;
        }
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                sessionStorage.setItem('lastSearch', this.value);
            });
        }
    }
    
    // Show loading overlay during AJAX operations
    window.setLoading = function(show) {
        let overlay = document.getElementById('loadingOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner"></div>';
            document.body.appendChild(overlay);
        }
        overlay.classList.toggle('show', show);
    };
    
    // Enhanced notification system
    const originalShowNotification = window.showNotification;
    window.showNotification = function(msg, type) {
        const notif = document.getElementById('notification');
        if (!notif) return;
        
        notif.textContent = msg;
        notif.className = `notification ${type}`;
        notif.style.display = 'block';
        
        // Auto-dismiss after 4 seconds
        setTimeout(() => {
            notif.style.animation = 'slideInRight 0.3s ease reverse';
            setTimeout(() => {
                notif.style.display = 'none';
                notif.style.animation = '';
            }, 300);
        }, 4000);
    };
    
    // Smooth scrolling for pagination
    function initSmoothScroll() {
        document.querySelectorAll('.pagination-link').forEach(link => {
            link.addEventListener('click', function(e) {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        });
    }
    
    // Enhanced table interactions
    function initTableEnhancements() {
        const table = document.querySelector('.data-table');
        if (!table) return;
        
        // Row click to view details (except on action buttons)
        table.addEventListener('click', function(e) {
            const row = e.target.closest('tr[data-id]');
            if (!row) return;
            
            // Don't trigger if clicking on buttons or checkboxes
            if (e.target.closest('button, input[type="checkbox"], a')) return;
            
            const id = parseInt(row.getAttribute('data-id'));
            if (id && typeof openDetailModal === 'function') {
                openDetailModal(id);
            }
        });
        
        // Shift-click for range selection
        let lastChecked = null;
        table.addEventListener('click', function(e) {
            if (!e.target.classList.contains('row-checkbox')) return;
            
            if (e.shiftKey && lastChecked) {
                const checkboxes = Array.from(document.querySelectorAll('.row-checkbox'));
                const start = checkboxes.indexOf(lastChecked);
                const end = checkboxes.indexOf(e.target);
                const range = checkboxes.slice(Math.min(start, end), Math.max(start, end) + 1);
                
                range.forEach(cb => {
                    cb.checked = lastChecked.checked;
                    const tr = cb.closest('tr');
                    if (tr) tr.classList.toggle('selected', cb.checked);
                });
            }
            
            lastChecked = e.target;
        });
    }
    
    // Performance monitoring (optional)
    function initPerformanceMonitoring() {
        if ('performance' in window && 'PerformanceObserver' in window) {
            try {
                // Monitor long tasks
                const observer = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (entry.duration > 50) {
                            console.warn('Long task detected:', entry.duration.toFixed(2) + 'ms');
                        }
                    }
                });
                observer.observe({ entryTypes: ['longtask'] });
            } catch (e) {
                // PerformanceObserver not fully supported
            }
        }
    }
    
    // Improved card interactions
    function initCardEnhancements() {
        document.querySelectorAll('.card').forEach(card => {
            // Add hover effect for better UX
            card.addEventListener('mouseenter', function() {
                this.style.zIndex = '10';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.zIndex = '';
            });
        });
    }
    
    // Initialize all enhancements
    function init() {
        initLazyLoading();
        initKeyboardShortcuts();
        initFormStatePersistence();
        initSmoothScroll();
        initTableEnhancements();
        initCardEnhancements();
        
        // Only init performance monitoring in development
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            initPerformanceMonitoring();
        }
        
        console.log('✓ UX enhancements initialized');
    }
    
    // Wait for DOM and other scripts
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Export utilities
    window.UXUtils = {
        setLoading: window.setLoading,
        showNotification: window.showNotification
    };
})();
