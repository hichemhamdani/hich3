<div class="wrap">
    <div id="icon-users" class="icon32"></div>
    <h2> <?php echo __( 'Orders List', 'woo-bordereau-generator' ); ?> </h2>
    <form id="bulk-actions-form" method="get">
        <?php $orders_list_table->display(); ?>
    </form>
</div>