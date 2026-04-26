/* Jimee Cosmetics — Checkout v2.1 */
jQuery(function ($) {
  'use strict';

  /* ── Auth toggle (login / register panels) ── */
  $(document).on('click', '.jimee-auth-option', function () {
    var target = $(this).data('target');
    var panel = $('#jimee-' + target + '-panel');
    var isVisible = panel.is(':visible');
    $('.jimee-auth-panel').slideUp(200);
    if (!isVisible) panel.slideDown(250);
  });

  /* ── Payment method highlight ── */
  $(document.body).on('updated_checkout payment_method_selected', function () {
    $('#payment .payment_methods li').each(function () {
      $(this).toggleClass('jimee-payment-active', $(this).find('input:checked').length > 0);
    });
  });

  /* ── Mobile review accordion ── */
  $(document).on('click', '.jimee-review-toggle', function () {
    var expanded = $(this).attr('aria-expanded') === 'true';
    $(this).attr('aria-expanded', !expanded);
    $(this).next('.jimee-review-content').slideToggle(250);
  });

  /* ── Mobile sticky CTA → trigger form submit ── */
  $(document).on('click', '#jimee-mobile-submit', function () {
    var $btn = $('form.checkout #place_order');
    if ($btn.length) $btn.trigger('click');
  });

  /* ── Coupon AJAX (uses jimeeCheckout localized data) ── */
  $(document).on('click', '#jimee-apply-coupon', function () {
    var $btn = $(this);
    var $input = $('#jimee-coupon-code');
    var $msg = $('#jimee-coupon-msg');
    var code = $input.val().trim();

    if (!code) {
      $msg.text('Veuillez entrer un code promo').removeClass('success').addClass('error');
      return;
    }

    $btn.prop('disabled', true).text('...');
    $msg.text('').removeClass('success error');

    $.ajax({
      url: jimeeCheckout.ajax_url,
      type: 'POST',
      data: {
        action: 'jimee_apply_coupon',
        coupon_code: code,
        security: jimeeCheckout.coupon_nonce
      },
      success: function (res) {
        if (res.success) {
          $msg.text('\u2713 Code ' + code.toUpperCase() + ' appliqué !').removeClass('error').addClass('success');
          $input.val('');
          $(document.body).trigger('update_checkout');
        } else {
          $msg.text(res.data || 'Code promo invalide').removeClass('success').addClass('error');
        }
      },
      error: function () {
        $msg.text('Erreur, veuillez réessayer').removeClass('success').addClass('error');
      },
      complete: function () {
        $btn.prop('disabled', false).text('OK');
      }
    });
  });

  /* Enter key on coupon input */
  $(document).on('keypress', '#jimee-coupon-code', function (e) {
    if (e.which === 13) {
      e.preventDefault();
      $('#jimee-apply-coupon').trigger('click');
    }
  });

  /* ── Recap totals are updated automatically via WC fragments ── */

  /* ── Hide postcode if it reappears via plugin ── */
  function hidePostcode() {
    $('#billing_postcode_field').hide();
  }
  hidePostcode();
  $(document.body).on('updated_checkout', hidePostcode);
});
