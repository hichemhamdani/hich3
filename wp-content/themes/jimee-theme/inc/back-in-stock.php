<?php
/**
 * Back in Stock Notifications — Jimee Cosmetics.
 *
 * - Custom table jimee_stock_notify
 * - AJAX subscribe (logged-in: 1 click, guest: email field + optional account creation)
 * - Auto email when product goes back in stock
 * - Admin column on product list
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   DB TABLE — Create on theme activation / init
   ============================================================ */

add_action( 'after_switch_theme', 'jimee_stock_notify_create_table' );
add_action( 'init', 'jimee_stock_notify_maybe_create_table', 5 );

function jimee_stock_notify_maybe_create_table() {
    if ( get_option( 'jimee_stock_notify_db_version' ) !== '1.0' ) {
        jimee_stock_notify_create_table();
    }
}

function jimee_stock_notify_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'jimee_stock_notify';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_id BIGINT UNSIGNED NOT NULL,
        email VARCHAR(200) NOT NULL,
        user_id BIGINT UNSIGNED DEFAULT 0,
        status VARCHAR(20) DEFAULT 'waiting',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        notified_at DATETIME DEFAULT NULL,
        KEY product_id (product_id),
        KEY email (email),
        KEY status (status)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    update_option( 'jimee_stock_notify_db_version', '1.0' );
}

/* ============================================================
   AJAX — Subscribe to notification
   ============================================================ */

add_action( 'wp_ajax_jimee_stock_notify', 'jimee_stock_notify_handler' );
add_action( 'wp_ajax_nopriv_jimee_stock_notify', 'jimee_stock_notify_handler' );

function jimee_stock_notify_handler() {
    check_ajax_referer( 'jimee_stock_notify', 'security' );
    global $wpdb;
    $table = $wpdb->prefix . 'jimee_stock_notify';

    $product_id = absint( $_POST['product_id'] ?? 0 );
    $email      = sanitize_email( $_POST['email'] ?? '' );
    $create_account = ! empty( $_POST['create_account'] );

    if ( ! $product_id || ! is_email( $email ) ) {
        wp_send_json_error( 'Email invalide.' );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        wp_send_json_error( 'Produit introuvable.' );
    }

    // Check if already subscribed
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM $table WHERE product_id = %d AND email = %s AND status = 'waiting'",
        $product_id, $email
    ) );

    if ( $exists ) {
        wp_send_json_success( 'Vous êtes déjà inscrit pour ce produit.' );
    }

    $user_id = 0;
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
    } elseif ( email_exists( $email ) ) {
        // Email already has an account
        $existing = get_user_by( 'email', $email );
        $user_id = $existing->ID;
    } elseif ( $create_account ) {
        $first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
        $last_name  = sanitize_text_field( $_POST['last_name'] ?? '' );
        $phone      = sanitize_text_field( $_POST['phone'] ?? '' );
        $password   = $_POST['password'] ?? '';

        if ( strlen( $password ) < 8 ) {
            wp_send_json_error( 'Le mot de passe doit contenir au moins 8 caracteres.' );
        }

        $username = sanitize_user( current( explode( '@', $email ) ), true );
        $counter = 1;
        $base = $username;
        while ( username_exists( $username ) ) {
            $username = $base . $counter++;
        }

        $new_user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $new_user_id ) ) {
            wp_send_json_error( 'Erreur lors de la creation du compte.' );
        }

        $user_id = $new_user_id;
        wp_update_user( [
            'ID'           => $new_user_id,
            'role'         => 'customer',
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => trim( "$first_name $last_name" ) ?: $username,
        ] );

        // Save phone as billing_phone
        if ( $phone ) {
            update_user_meta( $new_user_id, 'billing_phone', $phone );
        }

        // Auto-login
        wp_set_current_user( $new_user_id );
        wp_set_auth_cookie( $new_user_id, true );
    }

    $wpdb->insert( $table, [
        'product_id' => $product_id,
        'email'      => $email,
        'user_id'    => $user_id,
        'status'     => 'waiting',
    ] );

    wp_send_json_success( 'Vous serez notifié dès que ce produit sera de retour !' );
}

/* ============================================================
   AUTO NOTIFY — When product comes back in stock
   ============================================================ */

add_action( 'woocommerce_product_set_stock_status', 'jimee_stock_notify_check', 10, 3 );
function jimee_stock_notify_check( $product_id, $status, $product ) {
    if ( $status !== 'instock' ) return;

    global $wpdb;
    $table = $wpdb->prefix . 'jimee_stock_notify';

    $subscribers = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table WHERE product_id = %d AND status = 'waiting'",
        $product_id
    ) );

    if ( empty( $subscribers ) ) return;

    $product_name = $product->get_name();
    $product_url  = get_permalink( $product_id );
    $product_img  = get_the_post_thumbnail_url( $product_id, 'thumbnail' );
    $price        = number_format( (float) $product->get_price(), 0, ',', ' ' ) . ' DA';
    $logo_url     = get_template_directory_uri() . '/assets/img/logo-jimee-cosmetics-noir.png';
    $site_url     = home_url( '/' );

    $brand_terms = wp_get_post_terms( $product_id, 'product_brand' );
    $brand = ! empty( $brand_terms ) ? $brand_terms[0]->name : '';

    foreach ( $subscribers as $sub ) {
        $subject = $product_name . ' est de retour en stock !';

        $body = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#F8F6F3;font-family:Poppins,Helvetica,Arial,sans-serif">
        <div style="max-width:560px;margin:0 auto;padding:32px 16px">
            <div style="text-align:center;margin-bottom:24px">
                <a href="' . esc_url( $site_url ) . '"><img src="' . esc_url( $logo_url ) . '" alt="Jimee Cosmetics" style="height:36px;width:auto"></a>
            </div>
            <div style="background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.06)">
                <div style="background:#000;padding:24px 28px;text-align:center">
                    <h1 style="margin:0;font-size:20px;font-weight:300;color:#fff;font-family:Poppins,Helvetica,Arial,sans-serif">Bonne nouvelle !</h1>
                    <p style="margin:8px 0 0;font-size:13px;color:rgba(255,255,255,.6)">Un produit que vous attendiez est de retour</p>
                </div>
                <div style="padding:28px;text-align:center">
                    ' . ( $product_img ? '<img src="' . esc_url( $product_img ) . '" alt="" style="width:120px;height:120px;object-fit:contain;border-radius:12px;margin-bottom:16px">' : '' ) . '
                    ' . ( $brand ? '<div style="font-size:11px;font-weight:600;letter-spacing:1.5px;color:#999;text-transform:uppercase;margin-bottom:4px">' . esc_html( $brand ) . '</div>' : '' ) . '
                    <div style="font-size:16px;font-weight:600;color:#000;margin-bottom:8px">' . esc_html( $product_name ) . '</div>
                    <div style="font-size:18px;font-weight:700;color:#000;margin-bottom:24px">' . $price . '</div>
                    <a href="' . esc_url( $product_url ) . '" style="display:inline-block;padding:14px 36px;background:#000;color:#fff;border-radius:999px;font-size:14px;font-weight:600;text-decoration:none;font-family:Poppins,Helvetica,Arial,sans-serif">Voir le produit</a>
                </div>
            </div>
            <div style="text-align:center;padding:24px 0;font-size:11px;color:#999">Jimee Cosmetics — Kouba, Alger</div>
        </div></body></html>';

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        wp_mail( $sub->email, $subject, $body, $headers );

        // Mark as notified
        $wpdb->update( $table,
            [ 'status' => 'notified', 'notified_at' => current_time( 'mysql' ) ],
            [ 'id' => $sub->id ]
        );
    }
}

/* ============================================================
   ADMIN — Column on product list
   ============================================================ */

add_filter( 'manage_edit-product_columns', 'jimee_stock_notify_column', 90 );
function jimee_stock_notify_column( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'is_in_stock' ) {
            $new['jimee_notify'] = 'Notifications';
        }
    }
    return $new;
}

add_action( 'manage_product_posts_custom_column', 'jimee_stock_notify_column_content', 10, 2 );
function jimee_stock_notify_column_content( $column, $post_id ) {
    if ( $column !== 'jimee_notify' ) return;
    global $wpdb;
    $table = $wpdb->prefix . 'jimee_stock_notify';
    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE product_id = %d AND status = 'waiting'", $post_id
    ) );
    if ( $count > 0 ) {
        echo '<span style="background:#000;color:#fff;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600">' . $count . '</span>';
    } else {
        echo '<span style="color:#ccc">—</span>';
    }
}

/* ============================================================
   ADMIN — Metabox on single product (list of subscribers)
   ============================================================ */

add_action( 'add_meta_boxes', 'jimee_stock_notify_metabox' );
function jimee_stock_notify_metabox() {
    add_meta_box(
        'jimee_stock_notify',
        'Notifications de retour en stock',
        'jimee_stock_notify_metabox_render',
        'product',
        'side',
        'default'
    );
}

function jimee_stock_notify_metabox_render( $post ) {
    global $wpdb;
    $table = $wpdb->prefix . 'jimee_stock_notify';
    $subs = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table WHERE product_id = %d ORDER BY status ASC, created_at DESC", $post->ID
    ) );

    if ( empty( $subs ) ) {
        echo '<p style="color:#999;font-size:13px">Aucune inscription pour ce produit.</p>';
        return;
    }

    $waiting = array_filter( $subs, function( $s ) { return $s->status === 'waiting'; } );
    $notified = array_filter( $subs, function( $s ) { return $s->status === 'notified'; } );

    echo '<p style="font-size:13px;margin-bottom:12px"><strong>' . count( $waiting ) . '</strong> en attente · <strong>' . count( $notified ) . '</strong> notifié(s)</p>';

    echo '<div style="max-height:200px;overflow-y:auto">';
    foreach ( $subs as $s ) {
        $status_dot = $s->status === 'waiting'
            ? '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#D4AF37;margin-right:6px"></span>'
            : '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#2E7D32;margin-right:6px"></span>';
        $date = date_i18n( 'j M Y', strtotime( $s->created_at ) );
        echo '<div style="padding:6px 0;border-bottom:1px solid #f0f0f0;font-size:12px">';
        echo $status_dot . esc_html( $s->email ) . ' <span style="color:#999">· ' . $date . '</span>';
        echo '</div>';
    }
    echo '</div>';
}
