<?php
/**
 * Cart Page — Jimee Cosmetics override
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.1.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' ); ?>

<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
	<?php do_action( 'woocommerce_before_cart_table' ); ?>

	<div class="jimee-cart-items">
		<?php do_action( 'woocommerce_before_cart_contents' ); ?>

		<?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
			$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
			$product_name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );

			if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) continue;

			$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
			$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
			$brand_terms = wp_get_post_terms( $product_id, 'product_brand' );
			$brand = ! empty( $brand_terms ) ? $brand_terms[0]->name : '';
		?>
		<div class="jimee-cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
			<?php
			echo apply_filters(
				'woocommerce_cart_item_remove_link',
				sprintf(
					'<a role="button" href="%s" class="jimee-cart-remove" aria-label="%s" data-product_id="%s" data-product_sku="%s"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></a>',
					esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
					esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) ),
					esc_attr( $product_id ),
					esc_attr( $_product->get_sku() )
				),
				$cart_item_key
			);
			?>

			<div class="jimee-cart-thumb">
				<?php if ( $product_permalink ) : ?>
					<a href="<?php echo esc_url( $product_permalink ); ?>"><?php echo $thumbnail; ?></a>
				<?php else : ?>
					<?php echo $thumbnail; ?>
				<?php endif; ?>
			</div>

			<div class="jimee-cart-info">
				<?php if ( $brand ) : ?>
					<div class="jimee-cart-brand"><?php echo esc_html( strtoupper( $brand ) ); ?></div>
				<?php endif; ?>
				<div class="jimee-cart-name">
					<?php if ( $product_permalink ) : ?>
						<a href="<?php echo esc_url( $product_permalink ); ?>"><?php echo esc_html( $_product->get_name() ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $_product->get_name() ); ?>
					<?php endif; ?>
				</div>
				<div class="jimee-cart-unit-price">
					<?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); ?>
				</div>
				<?php
				echo wc_get_formatted_cart_item_data( $cart_item );
				if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
					echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>', $product_id ) );
				}
				?>
			</div>

			<div class="jimee-cart-qty">
				<?php
				$min_quantity = $_product->is_sold_individually() ? 1 : 0;
				$max_quantity = $_product->is_sold_individually() ? 1 : $_product->get_max_purchase_quantity();
				$product_quantity = woocommerce_quantity_input( [
					'input_name'   => "cart[{$cart_item_key}][qty]",
					'input_value'  => $cart_item['quantity'],
					'max_value'    => $max_quantity,
					'min_value'    => $min_quantity,
					'product_name' => $product_name,
				], $_product, false );
				echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item );
				?>
			</div>

			<div class="jimee-cart-subtotal">
				<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?>
			</div>
		</div>
		<?php endforeach; ?>

		<?php do_action( 'woocommerce_cart_contents' ); ?>
	</div>

	<!-- Coupon + actions -->
	<div class="jimee-cart-actions">
		<?php if ( wc_coupons_enabled() ) : ?>
		<div class="jimee-coupon">
			<input type="text" name="coupon_code" id="coupon_code" value="" placeholder="Code promo (ex: JIMEE15)" />
			<button type="submit" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>">Appliquer</button>
		</div>
		<?php endif; ?>
		<button type="submit" class="jimee-update-cart" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>">Mettre à jour le panier</button>
		<?php do_action( 'woocommerce_cart_actions' ); ?>
		<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
	</div>

	<?php do_action( 'woocommerce_after_cart_contents' ); ?>
	<?php do_action( 'woocommerce_after_cart_table' ); ?>
</form>

<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>

<div class="cart-collaterals">
	<?php do_action( 'woocommerce_cart_collaterals' ); ?>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
