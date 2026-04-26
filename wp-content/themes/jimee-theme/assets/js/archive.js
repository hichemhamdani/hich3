/**
 * Jimee Cosmetics — Archive (catégorie + marque)
 * Filter drawer, infinite scroll AJAX, tri.
 */
(function () {
    'use strict';

    var grid    = document.getElementById('productGrid');
    var trigger = document.getElementById('loadTrigger');
    var spinner = document.getElementById('loadingSpinner');
    var endMsg  = document.getElementById('endMessage');
    var archive = document.querySelector('.jimee-archive');
    if (!archive) return;

    var TERM_ID  = parseInt(archive.dataset.term, 10);
    var TAXONOMY = archive.dataset.taxonomy || 'product_cat';
    var TOTAL    = parseInt(archive.dataset.total, 10);
    var PER_PAGE = 8;
    var offset   = grid ? grid.children.length : 0;
    var loading  = false;

    /* ══════════════════════════════════════════════
       1. FILTER DRAWER — open/close
       ══════════════════════════════════════════════ */
    var drawer    = document.getElementById('filterDrawer');
    var overlay   = document.getElementById('filterDrawerOverlay');
    var toggleBtn = document.getElementById('filterToggle');
    var closeBtn  = document.getElementById('filterDrawerClose');
    var applyBtn  = document.getElementById('filterApply');
    var resetBtn  = document.getElementById('filterReset');

    function openDrawer(scrollToId) {
        if (!drawer || !overlay) return;
        drawer.classList.add('open');
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        if (scrollToId) {
            var section = document.getElementById(scrollToId);
            if (section) setTimeout(function() { section.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 300);
        }
    }

    function closeDrawer() {
        if (!drawer || !overlay) return;
        drawer.classList.remove('open');
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (toggleBtn) toggleBtn.addEventListener('click', function() { openDrawer(); });
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (overlay) overlay.addEventListener('click', closeDrawer);

    // ESC closes drawer
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) closeDrawer();
    });

    /* ══════════════════════════════════════════════
       2. SEARCH WITHIN DRAWER (filter brands/categories)
       ══════════════════════════════════════════════ */
    var taxSearch = document.getElementById('filterTaxSearch');
    if (taxSearch) {
        taxSearch.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            document.querySelectorAll('#filterTaxList .filter-checkbox').forEach(function(cb) {
                var name = cb.getAttribute('data-name') || '';
                cb.classList.toggle('hidden', q.length > 0 && name.indexOf(q) === -1);
            });
        });
    }

    /* ══════════════════════════════════════════════
       3. APPLY / RESET FILTERS
       ══════════════════════════════════════════════ */
    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            closeDrawer();
            applyFilters();
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (drawer) drawer.querySelectorAll('input[type="checkbox"]').forEach(function(cb) { cb.checked = false; });
            updatePillStates();
            closeDrawer();
            applyFilters();
        });
    }

    function updatePillStates() {
        if (!drawer) return;
        var hasTax = drawer.querySelectorAll('[data-filter="cross"] input:checked').length > 0;
        var hasSize = drawer.querySelectorAll('[data-filter="size"] input:checked').length > 0;
        var hasLabels = drawer.querySelectorAll('[data-filter="labels"] input:checked').length > 0;
        if (toggleBtn) toggleBtn.classList.toggle('active', hasTax || hasSize || hasLabels);
    }

    /* ══════════════════════════════════════════════
       4. INFINITE SCROLL — AJAX product loading
       ══════════════════════════════════════════════ */
    function loadMore() {
        if (loading || offset >= TOTAL) return;
        loading = true;
        if (spinner) spinner.classList.add('active');

        var data = new FormData();
        data.append('action', 'jimee_load_products');
        data.append('nonce', jimeeArchive.nonce);
        data.append('term_id', TERM_ID);
        data.append('taxonomy', TAXONOMY);
        data.append('offset', offset);
        data.append('per_page', PER_PAGE);
        data.append('orderby', document.getElementById('sortSelect') ? document.getElementById('sortSelect').value : 'default');

        // Cross-taxonomy filters (brands or categories)
        if (drawer) {
            drawer.querySelectorAll('[data-filter="cross"] input:checked').forEach(function(cb) {
                data.append('filters[]', cb.value);
            });

            var sizeRanges = [];
            drawer.querySelectorAll('[data-filter="size"] input:checked').forEach(function(cb) {
                sizeRanges.push(cb.value);
            });
            if (sizeRanges.length > 0) {
                data.append('size_ranges', JSON.stringify(sizeRanges));
            }

            // Labels (Bio, Vegan)
            drawer.querySelectorAll('[data-filter="labels"] input:checked').forEach(function(cb) {
                data.append('labels[]', cb.value);
            });
        }

        fetch(jimeeArchive.ajax_url, { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (spinner) spinner.classList.remove('active');
                loading = false;
                if (res.success && res.data.html) {
                    var temp = document.createElement('div');
                    temp.innerHTML = res.data.html;
                    var cards = temp.children;
                    var delay = 0;
                    while (cards.length > 0) {
                        var card = cards[0];
                        card.style.animationDelay = delay * 60 + 'ms';
                        grid.appendChild(card);
                        delay++;
                    }
                    offset = grid.children.length;
                    if (window.JimeeWishlist) window.JimeeWishlist.updateUI();
                }
                if (!res.data.has_more || offset >= TOTAL) {
                    if (endMsg) endMsg.classList.add('active');
                    observer.disconnect();
                }
                // Update total if returned
                if (res.data.total !== undefined) TOTAL = res.data.total;
            })
            .catch(function() {
                if (spinner) spinner.classList.remove('active');
                loading = false;
            });
    }

    var observer = new IntersectionObserver(function(entries) {
        if (entries[0].isIntersecting) loadMore();
    }, { rootMargin: '300px' });

    if (trigger && offset < TOTAL) {
        observer.observe(trigger);
    } else if (endMsg && offset >= TOTAL) {
        endMsg.classList.add('active');
    }

    function applyFilters() {
        offset = 0;
        TOTAL = 99999; // Reset to allow loading
        if (grid) grid.innerHTML = '';
        if (endMsg) endMsg.classList.remove('active');
        if (trigger) observer.observe(trigger);
        updatePillStates();
        loadMore();
    }

    /* ══════════════════════════════════════════════
       5. SORT
       ══════════════════════════════════════════════ */
    var sortSelect = document.getElementById('sortSelect');
    if (sortSelect) sortSelect.addEventListener('change', applyFilters);

    /* ══════════════════════════════════════════════
       6. PREFETCH + PAGE TRANSITIONS
       ══════════════════════════════════════════════ */
    document.querySelectorAll('.pill[href]').forEach(function(pill) {
        pill.addEventListener('touchstart', function() {
            if (pill.dataset.prefetched) return;
            var link = document.createElement('link');
            link.rel = 'prefetch'; link.href = pill.href;
            document.head.appendChild(link);
            pill.dataset.prefetched = '1';
        }, { passive: true });
    });

    if (!('startViewTransition' in document)) {
        document.querySelectorAll('.pill[href]').forEach(function(pill) {
            pill.addEventListener('click', function(e) {
                if (pill.classList.contains('active')) { e.preventDefault(); return; }
                e.preventDefault();
                document.body.classList.add('jimee-navigating');
                sessionStorage.setItem('jimee-nav', '1');
                setTimeout(function() { window.location.href = pill.href; }, 150);
            });
        });
        if (sessionStorage.getItem('jimee-nav')) {
            document.body.classList.add('jimee-entering');
            sessionStorage.removeItem('jimee-nav');
            requestAnimationFrame(function() {
                requestAnimationFrame(function() { document.body.classList.remove('jimee-entering'); });
            });
        }
    }

    /* ══════════════════════════════════════════════
       7. PROMO CAROUSEL
       ══════════════════════════════════════════════ */
    var promoTrack = document.getElementById('promoTrack');
    var prevBtn = document.querySelector('.promo-prev');
    var nextBtn = document.querySelector('.promo-next');

    function scrollPromo(dir) {
        if (!promoTrack) return;
        var card = promoTrack.querySelector('.promo-card');
        if (card) promoTrack.scrollBy({ left: dir * (card.offsetWidth + 16), behavior: 'smooth' });
    }

    if (prevBtn) prevBtn.addEventListener('click', function() { scrollPromo(-1); });
    if (nextBtn) nextBtn.addEventListener('click', function() { scrollPromo(1); });

})();
