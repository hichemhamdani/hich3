/**
 * Jimee Cosmetics — Homepage JS
 * Hero slider, filter tabs, brand carousel.
 */
(function () {
    'use strict';

    /* ========== HERO SLIDER ========== */
    var slides = document.querySelectorAll('.hero-slide');
    var dots = document.querySelectorAll('.hero-dot');
    var currentSlide = 0;

    function goToSlide(n) {
        if (!slides.length) return;
        slides[currentSlide].classList.remove('active');
        if (dots[currentSlide]) dots[currentSlide].classList.remove('active');
        currentSlide = n;
        slides[currentSlide].classList.add('active');
        if (dots[currentSlide]) dots[currentSlide].classList.add('active');
    }

    if (slides.length > 1) {
        setInterval(function () {
            goToSlide((currentSlide + 1) % slides.length);
        }, 5000);

        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () { goToSlide(i); });
        });
    }

    /* ========== FILTER TABS ========== */
    document.querySelectorAll('.filter-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelector('.filter-tab.active')?.classList.remove('active');
            tab.classList.add('active');
            var filter = tab.dataset.filter;
            document.querySelectorAll('#bestSellers .product-card').forEach(function (card) {
                if (filter === 'all' || card.dataset.category === filter) {
                    card.style.display = '';
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(12px)';
                    requestAnimationFrame(function () {
                        card.style.transition = 'all .4s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    });
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    /* ========== NEWSLETTER FORM ========== */
    var nlForm = document.getElementById('newsletterForm');
    if (nlForm) {
        nlForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var input = nlForm.querySelector('input[type="email"]');
            var btn = nlForm.querySelector('button');
            var email = input.value.trim();
            if (!email) return;
            btn.textContent = 'Envoi...';
            btn.disabled = true;
            fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'jimee_newsletter', email: email }),
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            }).then(function() {
                btn.textContent = 'Merci !';
                input.value = '';
                setTimeout(function() { btn.textContent = "S'inscrire"; btn.disabled = false; }, 3000);
            }).catch(function() {
                btn.textContent = "S'inscrire";
                btn.disabled = false;
            });
        });
    }

})();
