<?php
/**
 * Orders table header template
 * 
 * @var ShippingOrdersTable $this The table instance with access to $this->provider
 */

// Get statuses from provider's setting class
$statuses = [];
if (isset($this->provider['setting_class']) && class_exists($this->provider['setting_class'])) {
    $settings_class = new $this->provider['setting_class']($this->provider);
    if (method_exists($settings_class, 'get_status')) {
        $statuses = $settings_class->get_status();
    }
}

$current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
?>
<div class="alignleft actions">
    <div class="flex items-center space-x-2">
        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'wc-bordereau-generator-orders'); ?>">
        <input type="text" value="<?php echo esc_attr($_GET['query'] ?? ''); ?>" class="md:w-64 w-full" name="query" placeholder="<?php echo esc_attr__( 'Search by tracking', 'woo-bordereau-generator' ); ?>">
        <input type="text" value="<?php echo esc_attr($_GET['range'] ?? ''); ?>" class="md:w-64 w-full" id="date_range" name="range" placeholder="<?php echo esc_attr__( 'Date Range', 'woo-bordereau-generator' ); ?>">
        <select name="status" class="md:w-64 w-full" id="">
            <option value=""><?php echo esc_html__( 'Filter by status', 'woo-bordereau-generator' ); ?></option>
            <?php if (!empty($statuses)) : ?>
                <?php foreach ($statuses as $status_key => $status_label) : ?>
                    <option <?php selected($current_status, $status_label); ?> value="<?php echo esc_attr($status_label); ?>">
                        <?php echo esc_html($status_label); ?>
                    </option>
                <?php endforeach; ?>
            <?php else : ?>
                <!-- Fallback statuses if provider doesn't have get_status() -->
                <option <?php selected($current_status, 'Pas encore expédié'); ?> value="Pas encore expédié"><?php echo esc_html__('Pas encore expédié', 'woo-bordereau-generator'); ?></option>
                <option <?php selected($current_status, 'En préparation'); ?> value="En préparation"><?php echo esc_html__('En préparation', 'woo-bordereau-generator'); ?></option>
                <option <?php selected($current_status, 'Prêt à expédier'); ?> value="Prêt à expédier"><?php echo esc_html__('Prêt à expédier', 'woo-bordereau-generator'); ?></option>
                <option <?php selected($current_status, 'Expédié'); ?> value="Expédié"><?php echo esc_html__('Expédié', 'woo-bordereau-generator'); ?></option>
                <option <?php selected($current_status, 'En livraison'); ?> value="En livraison"><?php echo esc_html__('En livraison', 'woo-bordereau-generator'); ?></option>
                <option <?php selected($current_status, 'Livré'); ?> value="Livré"><?php echo esc_html__('Livré', 'woo-bordereau-generator'); ?></option>
                <option <?php selected($current_status, 'Retour'); ?> value="Retour"><?php echo esc_html__('Retour', 'woo-bordereau-generator'); ?></option>
            <?php endif; ?>
        </select>
        <input type="submit" name="filter_action" class="button ml-4" value="<?php echo esc_attr__('Filter', 'woo-bordereau-generator'); ?>">
    </div>
</div>
