/**
 * Jimee Cosmetics — Wishlist (localStorage)
 * No plugin dependency. Works for all users (logged in or not).
 */

(function() {
    'use strict';

    var STORAGE_KEY = 'jimee_wishlist';

    var JimeeWishlist = {
        getItems: function() {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
            } catch (e) { return []; }
        },

        toggle: function(productId) {
            var items = this.getItems();
            var idx = items.indexOf(productId);
            if (idx > -1) {
                items.splice(idx, 1); // Remove
            } else {
                items.push(productId); // Add
            }
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
            this.updateUI();
            return idx === -1; // true = added, false = removed
        },

        has: function(productId) {
            return this.getItems().indexOf(productId) > -1;
        },

        remove: function(productId) {
            var items = this.getItems().filter(function(id) { return id !== productId; });
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
            this.updateUI();
        },

        updateUI: function() {
            var items = this.getItems();

            // Update header count badge
            var countEls = document.querySelectorAll('#wishlistCount, .wishlist-count');
            countEls.forEach(function(el) {
                el.textContent = items.length;
                el.style.display = items.length > 0 ? 'flex' : 'none';
            });

            // Update all wishlist buttons on page
            document.querySelectorAll('.wishlist-btn[data-product-id]').forEach(function(btn) {
                var pid = parseInt(btn.getAttribute('data-product-id'), 10);
                btn.classList.toggle('liked', items.indexOf(pid) > -1);
            });
        },

        init: function() {
            var self = this;
            this.updateUI();

            // Delegate clicks on wishlist buttons
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.wishlist-btn[data-product-id]');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                var pid = parseInt(btn.getAttribute('data-product-id'), 10);
                if (pid) self.toggle(pid);
            });
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        JimeeWishlist.init();
    });

    // Expose globally for wishlist page
    window.JimeeWishlist = JimeeWishlist;

})();
