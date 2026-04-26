<?php
/**
 * Jimee — Admin Product Fields
 * Standalone metaboxes for Ingrédients + Conseils d'utilisation.
 * Visible, clear, separate from WC tabs — like short/long description.
 */

// ── 1. Register metaboxes ──
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'jimee_ingredients_box',
        '🧪 Ingrédients (liste INCI)',
        'jimee_ingredients_metabox',
        'product',
        'normal',
        'high'
    );
    add_meta_box(
        'jimee_usage_box',
        '📋 Conseils d\'utilisation',
        'jimee_usage_metabox',
        'product',
        'normal',
        'high'
    );
});

// ── 2. Ingrédients metabox ──
function jimee_ingredients_metabox( $post ) {
    $value = get_post_meta( $post->ID, '_jimee_ingredients', true );
    wp_nonce_field( 'jimee_save_fields', 'jimee_fields_nonce' );
    ?>
    <p class="description" style="margin-bottom:8px;">
        Collez la liste INCI complète du produit (celle qui figure sur l'emballage). Séparez les ingrédients par des virgules.
    </p>
    <textarea name="jimee_ingredients" rows="5" style="width:100%; font-family:monospace; font-size:13px;" placeholder="Aqua, Glycerin, Niacinamide, Butyrospermum Parkii Butter..."><?php echo esc_textarea( $value ); ?></textarea>
    <?php
}

// ── 3. Conseils d'utilisation metabox ──
function jimee_usage_metabox( $post ) {
    $value = get_post_meta( $post->ID, '_jimee_usage', true );
    ?>
    <p class="description" style="margin-bottom:8px;">
        Expliquez comment utiliser le produit. Vous pouvez utiliser des listes, du gras, etc.
    </p>
    <?php
    wp_editor( $value, 'jimee_usage_editor', [
        'textarea_name' => 'jimee_usage',
        'textarea_rows' => 8,
        'media_buttons' => false,
        'teeny'         => false,
        'quicktags'     => true,
    ]);
}

// ── 4. Save ──
add_action( 'woocommerce_process_product_meta', function( $post_id ) {
    if ( ! isset( $_POST['jimee_fields_nonce'] ) || ! wp_verify_nonce( $_POST['jimee_fields_nonce'], 'jimee_save_fields' ) ) return;

    if ( isset( $_POST['jimee_ingredients'] ) ) {
        update_post_meta( $post_id, '_jimee_ingredients', sanitize_textarea_field( $_POST['jimee_ingredients'] ) );
    }
    if ( isset( $_POST['jimee_usage'] ) ) {
        update_post_meta( $post_id, '_jimee_usage', wp_kses_post( $_POST['jimee_usage'] ) );
    }
});

// Also save on standard post save (not just WC)
add_action( 'save_post_product', function( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! isset( $_POST['jimee_fields_nonce'] ) || ! wp_verify_nonce( $_POST['jimee_fields_nonce'], 'jimee_save_fields' ) ) return;

    if ( isset( $_POST['jimee_ingredients'] ) ) {
        update_post_meta( $post_id, '_jimee_ingredients', sanitize_textarea_field( $_POST['jimee_ingredients'] ) );
    }
    if ( isset( $_POST['jimee_usage'] ) ) {
        update_post_meta( $post_id, '_jimee_usage', wp_kses_post( $_POST['jimee_usage'] ) );
    }
}, 10, 1 );

// ── 5. CSV Export: add columns ──
add_filter( 'woocommerce_product_export_column_names', 'jimee_add_export_columns' );
add_filter( 'woocommerce_product_export_product_default_columns', 'jimee_add_export_columns' );
function jimee_add_export_columns( $columns ) {
    $columns['jimee_ingredients'] = 'Ingrédients (INCI)';
    $columns['jimee_usage']       = "Conseils d'utilisation";
    return $columns;
}

add_filter( 'woocommerce_product_export_product_column_jimee_ingredients', function( $value, $product ) {
    return get_post_meta( $product->get_id(), '_jimee_ingredients', true );
}, 10, 2 );

add_filter( 'woocommerce_product_export_product_column_jimee_usage', function( $value, $product ) {
    return get_post_meta( $product->get_id(), '_jimee_usage', true );
}, 10, 2 );

// ── 6. CSV Import: map columns ──
add_filter( 'woocommerce_csv_product_import_mapping_options', function( $options ) {
    $options['jimee_ingredients'] = 'Ingrédients (INCI)';
    $options['jimee_usage']       = "Conseils d'utilisation";
    return $options;
});

add_filter( 'woocommerce_csv_product_import_mapping_default_columns', function( $columns ) {
    $columns['Ingrédients (INCI)']       = 'jimee_ingredients';
    $columns["Conseils d'utilisation"]    = 'jimee_usage';
    return $columns;
});

add_filter( 'woocommerce_product_import_pre_insert_product_object', function( $product, $data ) {
    if ( isset( $data['jimee_ingredients'] ) ) {
        $product->update_meta_data( '_jimee_ingredients', sanitize_textarea_field( $data['jimee_ingredients'] ) );
    }
    if ( isset( $data['jimee_usage'] ) ) {
        $product->update_meta_data( '_jimee_usage', wp_kses_post( $data['jimee_usage'] ) );
    }
    return $product;
}, 10, 2 );

// ── 7. Custom columns in product list ──
add_filter( 'manage_edit-product_columns', function( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        // Insert after 'name' column
        if ( $key === 'name' ) {
            $new['jimee_photo']       = 'Photos';
            $new['jimee_inci']        = 'INCI';
            $new['jimee_conseils']    = 'Conseils';
        }
    }
    return $new;
});

add_action( 'manage_product_posts_custom_column', function( $column, $post_id ) {
    switch ( $column ) {
        case 'jimee_photo':
            $thumb = get_post_thumbnail_id( $post_id );
            $gallery = get_post_meta( $post_id, '_product_image_gallery', true );
            $gallery_count = $gallery ? count( array_filter( explode( ',', $gallery ) ) ) : 0;
            if ( $thumb ) {
                echo '<span style="color:#2E7D32;font-weight:500" title="Principale OK + ' . $gallery_count . ' secondaire(s)">OK +' . $gallery_count . '</span>';
            } else {
                echo '<span style="color:#8B0000;font-weight:500" title="Photo manquante">Non</span>';
            }
            break;

        case 'jimee_inci':
            $val = get_post_meta( $post_id, '_jimee_ingredients', true );
            echo $val
                ? '<span style="color:#2E7D32;font-size:16px" title="' . esc_attr( mb_substr( $val, 0, 80 ) ) . '…">✓</span>'
                : '<span style="color:#8B0000;font-size:16px" title="Ingrédients manquants">✗</span>';
            break;

        case 'jimee_conseils':
            $val = get_post_meta( $post_id, '_jimee_usage', true );
            echo $val
                ? '<span style="color:#2E7D32;font-size:16px" title="Conseils remplis">✓</span>'
                : '<span style="color:#999;font-size:16px" title="Conseils auto (fallback)">~</span>';
            break;

    }
}, 10, 2 );

// Make columns narrow
add_action( 'admin_head', function() {
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'edit-product' ) {
        echo '<style>
            .column-jimee_photo, .column-jimee_inci, .column-jimee_conseils {
                width: 58px !important; text-align: center !important;
            }
            .column-jimee_photo span, .column-jimee_inci span, .column-jimee_conseils span { cursor: help; }
        </style>';
    }
});

// ── 8. Sortable/filterable by completeness ──
add_action( 'restrict_manage_posts', function() {
    global $typenow;
    if ( $typenow !== 'product' ) return;
    $selected = $_GET['jimee_filter'] ?? '';
    ?>
    <select name="jimee_filter">
        <option value="">Fiche produit</option>
        <option value="no_inci" <?php selected( $selected, 'no_inci' ); ?>>Sans ingrédients</option>
        <option value="no_usage" <?php selected( $selected, 'no_usage' ); ?>>Sans conseils</option>
        <option value="no_photo" <?php selected( $selected, 'no_photo' ); ?>>Sans photo</option>
        <option value="complete" <?php selected( $selected, 'complete' ); ?>>Fiche complète</option>
    </select>
    <?php
});

add_filter( 'parse_query', function( $query ) {
    global $typenow;
    if ( $typenow !== 'product' || ! is_admin() ) return;
    $filter = $_GET['jimee_filter'] ?? '';
    if ( ! $filter ) return;

    $meta = $query->get( 'meta_query' ) ?: [];

    switch ( $filter ) {
        case 'no_inci':
            $meta[] = [
                'relation' => 'OR',
                [ 'key' => '_jimee_ingredients', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_jimee_ingredients', 'value' => '', 'compare' => '=' ],
            ];
            break;
        case 'no_usage':
            $meta[] = [
                'relation' => 'OR',
                [ 'key' => '_jimee_usage', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_jimee_usage', 'value' => '', 'compare' => '=' ],
            ];
            break;
        case 'no_photo':
            $meta[] = [
                'relation' => 'OR',
                [ 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_thumbnail_id', 'value' => '0', 'compare' => '=' ],
            ];
            break;
        case 'complete':
            $meta[] = [ 'key' => '_jimee_ingredients', 'value' => '', 'compare' => '!=' ];
            $meta[] = [ 'key' => '_jimee_usage', 'value' => '', 'compare' => '!=' ];
            $meta[] = [ 'key' => '_thumbnail_id', 'value' => '0', 'compare' => '!=' ];
            break;
    }

    $query->set( 'meta_query', $meta );
});
