/**
 * Jimee Cosmetics — Single Product JS
 * Gallery, zoom, quantity, add-to-cart, wishlist, scroll tracks, sticky bar, reveal.
 */
(function() {
    'use strict';

    /* ========== GALLERY THUMBNAIL SWITCH ========== */
    window.changeImage = function(thumb, imgUrl) {
        var mainImg = document.getElementById('mainImage');
        var galleryMain = document.getElementById('galleryMain');
        if (!mainImg) return;

        // Reset zoom
        if (galleryMain) {
            galleryMain.classList.remove('zoom-active');
            galleryMain.style.backgroundImage = '';
        }

        mainImg.style.opacity = '0';
        setTimeout(function() {
            mainImg.src = imgUrl;
            mainImg.style.opacity = '1';
        }, 200);

        document.querySelectorAll('.gallery-thumb').forEach(function(t) {
            t.classList.remove('active');
        });
        thumb.classList.add('active');
    };

    var galleryMain = document.getElementById('galleryMain');

    /* ========== MOBILE SWIPE GALLERY ========== */
    if (galleryMain && window.innerWidth <= 768) {
        var galleryImages = [];
        var currentIdx = 0;
        var mainImg = document.getElementById('mainImage');

        // Build image list from thumbnails data
        var thumbs = document.querySelectorAll('.gallery-thumb');
        thumbs.forEach(function(t) {
            var onclick = t.getAttribute('onclick');
            if (onclick) {
                var match = onclick.match(/'([^']+)'/);
                if (match) galleryImages.push(match[1]);
            }
        });

        // If no thumbnails, just use main image
        if (galleryImages.length === 0 && mainImg) {
            galleryImages.push(mainImg.src);
        }

        var dots = document.querySelectorAll('.gallery-dot');
        var touchStartX = 0;
        var touchEndX = 0;
        var isSwiping = false;

        function goToImage(idx) {
            if (idx < 0 || idx >= galleryImages.length || !mainImg) return;
            currentIdx = idx;
            mainImg.style.opacity = '0';
            setTimeout(function() {
                mainImg.src = galleryImages[currentIdx];
                mainImg.style.opacity = '1';
            }, 150);
            // Update dots
            dots.forEach(function(d, i) {
                d.classList.toggle('active', i === currentIdx);
            });
            // Update thumbs too (for when user switches back to desktop)
            thumbs.forEach(function(t, i) {
                t.classList.toggle('active', i === currentIdx);
            });
        }

        galleryMain.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
            isSwiping = true;
        }, { passive: true });

        galleryMain.addEventListener('touchmove', function(e) {
            if (!isSwiping) return;
            touchEndX = e.changedTouches[0].screenX;
        }, { passive: true });

        galleryMain.addEventListener('touchend', function() {
            if (!isSwiping) return;
            isSwiping = false;
            var diff = touchStartX - touchEndX;
            if (Math.abs(diff) > 50) { // Minimum swipe distance
                if (diff > 0) {
                    // Swipe left → next image
                    goToImage(currentIdx + 1);
                } else {
                    // Swipe right → prev image
                    goToImage(currentIdx - 1);
                }
            }
        });

        // Dot click navigation
        dots.forEach(function(dot, i) {
            dot.addEventListener('click', function() {
                goToImage(i);
            });
        });
    }

    /* ========== VARIATION SWATCHES ========== */
    var swatches = document.querySelectorAll('.pd-swatch');
    if (swatches.length) {
        var varLabel = document.getElementById('varLabel');
        var priceContainer = document.querySelector('.pd-price');

        function selectSwatch(swatch) {
            swatches.forEach(function(s) { s.classList.remove('active'); });
            swatch.classList.add('active');

            // Update label
            if (varLabel) varLabel.textContent = '— ' + swatch.dataset.name;

            // Update main image
            if (mainImg && swatch.dataset.img) {
                mainImg.style.opacity = '0';
                setTimeout(function() {
                    mainImg.src = swatch.dataset.img;
                    mainImg.style.opacity = '1';
                }, 200);
                if (galleryMain) {
                    galleryMain.classList.remove('zoom-active');
                    galleryMain.style.backgroundImage = '';
                }
            }

            // Update price
            if (priceContainer) {
                var p = parseInt(swatch.dataset.price);
                var r = parseInt(swatch.dataset.reg);
                var html = '';
                if (r > p && p > 0) {
                    var pct = Math.round((1 - p / r) * 100);
                    html = '<span class="pd-price-current">' + p.toLocaleString('fr-FR').replace(/\u202f/g, ' ') + ' DA</span>';
                    html += '<span class="pd-price-old">' + r.toLocaleString('fr-FR').replace(/\u202f/g, ' ') + ' DA</span>';
                    html += '<span class="pd-price-badge">-' + pct + '%</span>';
                } else {
                    html = '<span class="pd-price-current">' + (p || r).toLocaleString('fr-FR').replace(/\u202f/g, ' ') + ' DA</span>';
                }
                priceContainer.innerHTML = html;
            }

            // Update ATC button data
            var atcBtn = document.getElementById('mainAddToCart');
            if (atcBtn) {
                atcBtn.dataset.variationId = swatch.dataset.vid;
                atcBtn.dataset.variationAttr = swatch.dataset.val;
            }

            // Update sticky bar price
            var stickyPrice = document.querySelector('.sticky-atc-price');
            if (stickyPrice) {
                var p = parseInt(swatch.dataset.price) || parseInt(swatch.dataset.reg);
                stickyPrice.textContent = p.toLocaleString('fr-FR').replace(/\u202f/g, ' ') + ' DA';
            }
        }

        swatches.forEach(function(s) {
            s.addEventListener('click', function(e) {
                e.preventDefault();
                selectSwatch(this);
            });
        });

        // Auto-select first swatch
        selectSwatch(swatches[0]);
    }

    /* ========== QUANTITY +/- ========== */
    var qtyVal = document.getElementById('qtyVal');
    var quantity = 1;

    document.addEventListener('click', function(e) {
        if (e.target.closest('.qty-minus')) {
            quantity = Math.max(1, quantity - 1);
            if (qtyVal) qtyVal.textContent = quantity;
        }
        if (e.target.closest('.qty-plus')) {
            quantity = Math.min(99, quantity + 1);
            if (qtyVal) qtyVal.textContent = quantity;
        }
    });

    /* ========== ADD TO CART (AJAX) — CTA Capsule ========== */
    var mainAddBtn = document.getElementById('mainAddToCart');
    if (mainAddBtn) {
        mainAddBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var productId = this.getAttribute('data-product-id');
            if (!productId) return;

            var btn = this;
            var mainSpan = btn.querySelector('.cta-atc-main');
            var origText = mainSpan ? mainSpan.textContent : '';
            if (mainSpan) mainSpan.textContent = 'Ajout en cours...';

            var params = { quantity: quantity };
            // Variable product: WC AJAX expects variation_id as product_id
            if (btn.dataset.productType === 'variable' && btn.dataset.variationId) {
                params.product_id = btn.dataset.variationId;
            } else {
                params.product_id = productId;
            }
            // Add nonce if available (from wp_localize_script)
            if (window.jimeeProduct && jimeeProduct.nonce) {
                params._wpnonce = jimeeProduct.nonce;
            }

            fetch('/?wc-ajax=add_to_cart', {
                method: 'POST',
                body: new URLSearchParams(params),
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var capsule = btn.closest('.cta-capsule');
                if (data.error) {
                    if (mainSpan) mainSpan.textContent = 'Erreur';
                    setTimeout(function() { if (mainSpan) mainSpan.textContent = origText; }, 2500);
                    return;
                }
                if (mainSpan) mainSpan.textContent = 'Ajout\u00e9 !';
                if (capsule) capsule.style.background = '#2D8E4E';

                // Apply WC fragments (side cart + counts)
                if (data.fragments && typeof jimeeApplyFragments === 'function') {
                    jimeeApplyFragments(data.fragments);
                }

                // Open side cart
                if (typeof window.toggleCart === 'function') {
                    var sc = document.getElementById('sideCart');
                    if (sc && !sc.classList.contains('open')) {
                        window.toggleCart();
                    }
                }

                setTimeout(function() {
                    if (mainSpan) mainSpan.textContent = origText;
                    if (capsule) capsule.style.background = '';
                }, 2000);
            })
            .catch(function() {
                if (mainSpan) mainSpan.textContent = 'Erreur connexion';
                setTimeout(function() { if (mainSpan) mainSpan.textContent = origText; }, 2500);
            });
        });
    }

    /* ========== STICKY ADD TO CART — also AJAX ========== */
    var stickyBtn = document.querySelector('.sticky-atc-btn[data-product-id]');
    if (stickyBtn) {
        stickyBtn.addEventListener('click', function() {
            if (mainAddBtn) mainAddBtn.click();
        });
    }

    /* ========== STICKY ATC BAR SHOW/HIDE ========== */
    var stickyAtc = document.getElementById('stickyAtc');
    var ctaCapsule = document.getElementById('ctaCapsule');
    if (stickyAtc && ctaCapsule) {
        var observer = new IntersectionObserver(function(entries) {
            stickyAtc.classList.toggle('show', !entries[0].isIntersecting);
        }, { threshold: 0 });
        observer.observe(ctaCapsule);
    }

    /* ========== BUNDLE TOTAL + ADD ========== */
    window.updateBundleTotal = function() {
        var total = 0;
        document.querySelectorAll('.pd-bundle-check:checked').forEach(function(cb) {
            total += parseInt(cb.dataset.price) || 0;
        });
        var el = document.getElementById('bundleTotal');
        if (el) el.textContent = total.toLocaleString('fr-FR').replace(/\u202f/g, ' ') + ' DA';
    };
    // Init total on load
    updateBundleTotal();

    // Bundle add — event listener instead of inline onclick
    var bundleBtn = document.getElementById('bundleAddBtn');
    if (bundleBtn) {
        bundleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            jimeeAddBundle(this);
        });
    }

    window.jimeeAddBundle = function(btn) {
        var checks = document.querySelectorAll('.pd-bundle-check:checked');
        if (!checks.length) return;

        var origHTML = btn.innerHTML;
        btn.disabled = true;
        btn.textContent = 'Ajout en cours...';

        // Build list of items to add
        var items = [];
        checks.forEach(function(cb) {
            if (!cb.dataset.id) return;
            var params = { quantity: 1 };
            if (cb.dataset.type === 'variation') {
                params.product_id = cb.dataset.id;
            } else {
                params.product_id = cb.dataset.id;
            }
            items.push(params);
        });

        // Send requests SEQUENTIALLY (WC can't handle parallel cart writes)
        var chain = Promise.resolve();
        var lastResult = null;
        items.forEach(function(params) {
            chain = chain.then(function() {
                return fetch('/?wc-ajax=add_to_cart', {
                    method: 'POST',
                    body: new URLSearchParams(params),
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                }).then(function(r) { return r.json(); }).then(function(data) {
                    lastResult = data;
                    return data;
                });
            });
        });

        chain.then(function() {
            if (lastResult && lastResult.fragments && typeof jimeeApplyFragments === 'function') {
                jimeeApplyFragments(lastResult.fragments);
            }
            btn.innerHTML = '\u2713 Ajout\u00e9 !';
            btn.style.background = '#2D8E4E';
            if (typeof window.toggleCart === 'function') {
                var sc = document.getElementById('sideCart');
                if (sc && !sc.classList.contains('open')) window.toggleCart();
            }
            setTimeout(function() { btn.innerHTML = origHTML; btn.style.background = ''; btn.disabled = false; }, 2000);
        }).catch(function(err) {
            console.error('Bundle add error:', err);
            btn.innerHTML = origHTML;
            btn.disabled = false;
        });
    };

    /* ========== WISHLIST TOGGLE ========== */
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.pd-wishlist-btn');
        if (!btn) return;
        e.preventDefault();
        var pid = btn.getAttribute('data-product-id');
        if (!pid) return;

        var wishlist = JSON.parse(localStorage.getItem('jimee_wishlist') || '[]');
        var idx = wishlist.indexOf(pid);
        if (idx > -1) {
            wishlist.splice(idx, 1);
            btn.classList.remove('liked');
        } else {
            wishlist.push(pid);
            btn.classList.add('liked');
        }
        localStorage.setItem('jimee_wishlist', JSON.stringify(wishlist));

        // Update header wishlist count
        var badge = document.querySelector('.wishlist-count');
        if (badge) badge.textContent = wishlist.length;
    });

    // Init wishlist state on load
    var pdWishBtn = document.querySelector('.pd-wishlist-btn[data-product-id]');
    if (pdWishBtn) {
        var wishlist = JSON.parse(localStorage.getItem('jimee_wishlist') || '[]');
        if (wishlist.indexOf(pdWishBtn.getAttribute('data-product-id')) > -1) {
            pdWishBtn.classList.add('liked');
        }
    }

    /* ========== TABS ========== */
    window.jimeeTab = function(id) {
        document.querySelectorAll('.tab-btn').forEach(function(b, i) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
        var panel = document.getElementById('tab-' + id);
        if (panel) panel.classList.add('active');
        // Activate matching button
        document.querySelectorAll('.tab-btn').forEach(function(b) {
            if (b.getAttribute('onclick') && b.getAttribute('onclick').indexOf(id) > -1) b.classList.add('active');
        });
    };

    /* ========== SCROLL TRACK ARROWS ========== */
    document.querySelectorAll('.scroll-track-wrapper').forEach(function(wrapper) {
        var track = wrapper.querySelector('.scroll-track');
        var leftArrow = wrapper.querySelector('.scroll-arrow.left');
        var rightArrow = wrapper.querySelector('.scroll-arrow.right');
        if (!track) return;

        var scrollAmount = 300;

        function updateArrows() {
            if (!leftArrow || !rightArrow) return;
            leftArrow.classList.toggle('hidden', track.scrollLeft <= 10);
            rightArrow.classList.toggle('hidden', track.scrollLeft + track.clientWidth >= track.scrollWidth - 10);
        }

        if (leftArrow) {
            leftArrow.addEventListener('click', function() {
                track.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            });
        }
        if (rightArrow) {
            rightArrow.addEventListener('click', function() {
                track.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            });
        }

        track.addEventListener('scroll', updateArrows);
        updateArrows();
    });

    /* ========== STAGGERED CARD ENTRANCE ========== */
    var cardObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                var cards = entry.target.querySelectorAll('.product-card');
                cards.forEach(function(card, i) {
                    setTimeout(function() {
                        card.classList.add('card-visible');
                    }, i * 100);
                });
                cardObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.scroll-track').forEach(function(track) {
        cardObserver.observe(track);
    });

    /* ========== SCROLL REVEAL ========== */
    var revealObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.reveal').forEach(function(el) {
        revealObserver.observe(el);
    });

    /* ========== SPIN ANIMATION (for add-to-cart loading) ========== */
    var style = document.createElement('style');
    style.textContent = '@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}';
    document.head.appendChild(style);

})();
