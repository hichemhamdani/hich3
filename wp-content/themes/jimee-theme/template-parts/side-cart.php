<!-- SIDE CART -->
<div class="cart-overlay" id="cartOverlay"></div>
<div class="side-cart" id="sideCart">
    <div class="cart-header">
        <h3>Mon panier (<span id="cartItemCount"><?php echo function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : '0'; ?></span>)</h3>
        <button class="cart-close" id="cartClose" aria-label="Fermer le panier">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <?php
    if ( function_exists( 'jimee_render_side_cart_content' ) ) {
        jimee_render_side_cart_content();
    }
    ?>
</div>

<script>
function jimeeCartRemove(key, el) {
    var item = el.closest('.cart-item');
    if (item) {
        item.style.transition = 'opacity .3s, max-height .4s';
        item.style.opacity = '0';
        item.style.maxHeight = item.offsetHeight + 'px';
        setTimeout(function() { item.style.maxHeight = '0'; item.style.padding = '0'; item.style.margin = '0'; item.style.overflow = 'hidden'; }, 200);
    }
    fetch('/?wc-ajax=remove_from_cart', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'cart_item_key=' + encodeURIComponent(key)
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (res.fragments) {
            jimeeApplyFragments(res.fragments);
        }
    });
}
var _jimeeQtyBusy = false;
function jimeeCartQty(key, delta) {
    if (_jimeeQtyBusy) return;
    _jimeeQtyBusy = true;

    var item = document.querySelector('.cart-item[data-key="' + key + '"]');
    if (!item) { _jimeeQtyBusy = false; return; }
    var span = item.querySelector('.qty-selector span');
    var current = parseInt(span.textContent, 10) || 1;
    var newQty = current + delta;
    if (newQty < 1) newQty = 0;

    /* Disable buttons during request */
    var btns = item.querySelectorAll('.qty-selector button');
    btns.forEach(function(b) { b.disabled = true; });
    span.textContent = newQty || '…';

    var fd = new FormData();
    fd.append('action', 'jimee_update_cart_qty');
    fd.append('cart_item_key', key);
    fd.append('quantity', newQty);

    fetch('<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>', {
        method: 'POST',
        body: fd
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (res.success) {
            /* Replace entire cartContent */
            var cartContent = document.querySelector('#cartContent');
            if (cartContent && res.data.cart_html) {
                var tmp = document.createElement('div');
                tmp.innerHTML = res.data.cart_html;
                var newContent = tmp.querySelector('#cartContent');
                if (newContent) {
                    cartContent.innerHTML = newContent.innerHTML;
                }
            }
            /* Update header badge */
            var badge = document.querySelector('#cartItemCount');
            if (badge) badge.textContent = res.data.count;
            var headerBadge = document.querySelector('.cart-count');
            if (headerBadge) {
                headerBadge.textContent = res.data.count;
                headerBadge.style.display = res.data.count > 0 ? '' : 'none';
            }
        }
        _jimeeQtyBusy = false;
    }).catch(function() { _jimeeQtyBusy = false; });
}

/* Apply WC fragments to our custom elements */
function jimeeApplyFragments(fragments) {
    Object.keys(fragments).forEach(function(selector) {
        var el = document.querySelector(selector);
        if (el) {
            var tmp = document.createElement('div');
            tmp.innerHTML = fragments[selector];
            if (tmp.firstElementChild) {
                el.replaceWith(tmp.firstElementChild);
            } else {
                el.innerHTML = fragments[selector];
            }
        }
    });
}
</script>
