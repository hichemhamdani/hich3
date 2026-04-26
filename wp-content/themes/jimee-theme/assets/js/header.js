/**
 * Jimee Cosmetics — Header JS
 * Sticky scroll, mobile menu, side cart, search overlay.
 */

(function() {
    'use strict';

    /* ========== STICKY HEADER ========== */
    const header = document.getElementById('header');
    if (header) {
        window.addEventListener('scroll', function() {
            header.classList.toggle('scrolled', window.scrollY > 10);
        }, { passive: true });
    }

    /* ========== MOBILE MENU ========== */
    const mobileMenu    = document.getElementById('mobileMenu');
    const menuOverlay   = document.getElementById('mobileMenuOverlay');
    const menuToggle    = document.getElementById('menuToggle');
    const menuClose     = document.getElementById('mobileMenuClose');

    function openMobileMenu() {
        if (!mobileMenu || !menuOverlay) return;
        mobileMenu.classList.add('open');
        menuOverlay.classList.add('open');
        document.documentElement.classList.add('menu-open');
    }

    function closeMobileMenu() {
        if (!mobileMenu || !menuOverlay) return;
        mobileMenu.classList.remove('open');
        menuOverlay.classList.remove('open');
        document.documentElement.classList.remove('menu-open');
    }

    if (menuToggle) menuToggle.addEventListener('click', openMobileMenu);
    if (menuClose)  menuClose.addEventListener('click', closeMobileMenu);
    if (menuOverlay) menuOverlay.addEventListener('click', closeMobileMenu);

    // Submenu navigation (event delegation for reliability)
    document.addEventListener('click', function(e) {
        // Open submenu
        var openBtn = e.target.closest('[data-submenu]');
        if (openBtn) {
            e.preventDefault();
            e.stopPropagation();
            var sub = document.getElementById(openBtn.getAttribute('data-submenu'));
            if (sub) sub.classList.add('open');
            return;
        }
        // Close submenu (back button)
        var closeBtn = e.target.closest('[data-close]');
        if (closeBtn) {
            e.preventDefault();
            e.stopPropagation();
            var sub = document.getElementById(closeBtn.getAttribute('data-close'));
            if (sub) sub.classList.remove('open');
            return;
        }
    });

    /* ========== SIDE CART ========== */
    var sideCart    = document.getElementById('sideCart');
    var cartOverlay = document.getElementById('cartOverlay');
    var cartToggle  = document.getElementById('cartToggleBtn');
    var cartClose   = document.getElementById('cartClose');
    var cartContinue = document.getElementById('cartContinue');

    function toggleCart() {
        if (!sideCart || !cartOverlay) return;
        var isOpen = sideCart.classList.toggle('open');
        cartOverlay.classList.toggle('open');
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    if (cartToggle) cartToggle.addEventListener('click', toggleCart);
    if (cartClose) cartClose.addEventListener('click', toggleCart);
    if (cartOverlay) cartOverlay.addEventListener('click', toggleCart);
    if (cartContinue) cartContinue.addEventListener('click', toggleCart);

    // Expose globally for WooCommerce fragments
    window.toggleCart = toggleCart;

    /* ========== LIVE SEARCH DROPDOWN ========== */
    var searchInput    = document.getElementById('headerSearchInput');
    var searchDropdown = document.getElementById('searchDropdown');
    var searchResults  = document.getElementById('searchResults');
    var searchSugg     = document.getElementById('searchSuggestions');
    var searchAllLink  = document.getElementById('searchAllLink');
    var searchTimer    = null;

    function openDropdown() {
        if (searchDropdown) searchDropdown.classList.add('open');
    }
    function closeDropdown() {
        if (searchDropdown) searchDropdown.classList.remove('open');
    }

    if (searchInput) {
        // Show dropdown on focus
        searchInput.addEventListener('focus', function() {
            openDropdown();
        });

        // Live search on input (debounced 300ms)
        searchInput.addEventListener('input', function() {
            var q = this.value.trim();
            clearTimeout(searchTimer);

            if (q.length < 2) {
                // Show suggestions, hide results
                if (searchSugg) searchSugg.style.display = '';
                if (searchResults) searchResults.style.display = 'none';
                if (searchAllLink) searchAllLink.style.display = 'none';
                openDropdown();
                return;
            }

            searchTimer = setTimeout(function() {
                var data = new FormData();
                data.append('action', 'jimee_live_search');
                data.append('q', q);

                fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res.success) return;
                        if (searchSugg) searchSugg.style.display = 'none';
                        if (searchResults) {
                            searchResults.innerHTML = res.data.html || '<div style="padding:20px;text-align:center;color:#999;font-size:13px">Aucun résultat</div>';
                            searchResults.style.display = '';
                        }
                        if (searchAllLink && res.data.count > 6) {
                            searchAllLink.href = res.data.url;
                            searchAllLink.textContent = 'Voir les ' + res.data.count + ' résultats';
                            searchAllLink.style.display = '';
                        } else if (searchAllLink) {
                            if (res.data.count > 0) {
                                searchAllLink.href = res.data.url;
                                searchAllLink.textContent = 'Voir tous les résultats';
                                searchAllLink.style.display = '';
                            } else {
                                searchAllLink.style.display = 'none';
                            }
                        }
                        openDropdown();
                    });
            }, 300);
        });
    }

    // Close dropdown on click outside
    document.addEventListener('click', function(e) {
        var wrapper = document.getElementById('searchWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            closeDropdown();
        }
    });

    // ESC key closes overlays
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDropdown();
            closeMobileMenu();
            closeSearchOverlay();
            if (sideCart && sideCart.classList.contains('open')) toggleCart();
        }
    });

    /* ========== SEARCH OVERLAY (mobile full-screen) ========== */
    var overlay        = document.getElementById('searchOverlay');
    var overlayClose   = document.getElementById('searchOverlayClose');
    var overlayInput   = document.getElementById('searchInput');
    var overlayResults = document.getElementById('searchOverlayResults');
    var overlaySugg    = document.getElementById('searchOverlaySuggestions');
    var overlayAllLink = document.getElementById('searchOverlayAllLink');
    var overlayTimer   = null;

    function openSearchOverlay() {
        if (!overlay) return;
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        if (overlayInput) setTimeout(function() { overlayInput.focus(); }, 300);
    }

    function closeSearchOverlay() {
        if (!overlay) return;
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    // Open from search wrapper click on mobile (when search-bar is hidden)
    var searchWrap = document.getElementById('searchWrapper');
    if (searchWrap) {
        searchWrap.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                openSearchOverlay();
            }
        });
    }

    if (overlayClose) overlayClose.addEventListener('click', closeSearchOverlay);

    // Live search in overlay
    if (overlayInput) {
        overlayInput.addEventListener('input', function() {
            var q = this.value.trim();
            clearTimeout(overlayTimer);

            if (q.length < 2) {
                if (overlaySugg) overlaySugg.style.display = '';
                if (overlayResults) overlayResults.style.display = 'none';
                if (overlayAllLink) overlayAllLink.style.display = 'none';
                return;
            }

            overlayTimer = setTimeout(function() {
                var data = new FormData();
                data.append('action', 'jimee_live_search');
                data.append('q', q);

                fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res.success) return;
                        if (overlaySugg) overlaySugg.style.display = 'none';
                        if (overlayResults) {
                            overlayResults.innerHTML = res.data.html || '<div style="padding:20px;text-align:center;color:#999;font-size:13px">Aucun résultat</div>';
                            overlayResults.style.display = '';
                        }
                        if (overlayAllLink && res.data.count > 0) {
                            overlayAllLink.href = res.data.url;
                            overlayAllLink.textContent = res.data.count > 6
                                ? 'Voir les ' + res.data.count + ' résultats'
                                : 'Voir tous les résultats';
                            overlayAllLink.style.display = '';
                        } else if (overlayAllLink) {
                            overlayAllLink.style.display = 'none';
                        }
                    });
            }, 300);
        });
    }

    /* ========== MOBILE MENU SEARCH (AJAX) ========== */
    var mobileSearchInput   = document.getElementById('mobileSearchInput');
    var mobileSearchResults = document.getElementById('mobileSearchResults');
    var mobileSearchTimer   = null;

    if (mobileSearchInput) {
        mobileSearchInput.addEventListener('input', function() {
            var q = this.value.trim();
            clearTimeout(mobileSearchTimer);

            if (q.length < 2) {
                if (mobileSearchResults) mobileSearchResults.style.display = 'none';
                return;
            }

            mobileSearchTimer = setTimeout(function() {
                var data = new FormData();
                data.append('action', 'jimee_live_search');
                data.append('q', q);

                fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res.success || !mobileSearchResults) return;
                        var html = '';
                        if (res.data.html) {
                            html = '<div class="mobile-search-items">' + res.data.html + '</div>';
                        } else {
                            html = '<div style="padding:16px;text-align:center;color:#999;font-size:13px">Aucun résultat</div>';
                        }
                        if (res.data.count > 0) {
                            html += '<a href="' + res.data.url + '" class="mobile-search-all">Voir les ' + res.data.count + ' résultats</a>';
                        }
                        mobileSearchResults.innerHTML = html;
                        mobileSearchResults.style.display = '';
                    });
            }, 300);
        });
    }

    /* ========== SCROLL REVEAL ========== */
    var reveals = document.querySelectorAll('.reveal');
    if (reveals.length > 0) {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        reveals.forEach(function(el) { observer.observe(el); });
    }

    /* ========== ADD TO CART (product cards) ========== */
    // Capture phase — fires BEFORE the <a> can navigate
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-add-to-cart]');
        if (!btn) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var pid = btn.dataset.addToCart;
        if (!pid || btn.classList.contains('loading')) return;

        btn.classList.add('loading');

        fetch('/?wc-ajax=add_to_cart', {
            method: 'POST',
            body: new URLSearchParams({ product_id: pid, quantity: 1 }),
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btn.classList.remove('loading');
            if (!res.fragments) return;

            // Apply WC fragments (side cart + counts)
            if (typeof jimeeApplyFragments === 'function') {
                jimeeApplyFragments(res.fragments);
            }

            // Success feedback
            btn.classList.add('added');
            setTimeout(function() { btn.classList.remove('added'); }, 1500);

            // Open side cart
            if (sideCart && !sideCart.classList.contains('open')) {
                toggleCart();
            }
        })
        .catch(function() {
            btn.classList.remove('loading');
        });
    }, true); // <-- capture phase

    /* ========== SINGLE PRODUCT ADD TO CART — intercept WC events ========== */
    if (typeof jQuery !== 'undefined') {
        jQuery(document.body).on('added_to_cart', function(e, fragments) {
            if (fragments && typeof jimeeApplyFragments === 'function') {
                jimeeApplyFragments(fragments);
            }
            // Open side cart
            if (sideCart && !sideCart.classList.contains('open')) {
                toggleCart();
            }
        });
    }

})();
