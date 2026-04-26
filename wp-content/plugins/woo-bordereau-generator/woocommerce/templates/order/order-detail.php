<?php
defined( 'ABSPATH' ) || exit;
?>

<style>
.bdt-result-wrap {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    max-width: 640px;
    margin: 2rem auto;
    padding: 0 1rem;
}
.bdt-provider-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #f4f4f4;
    border-radius: 100px;
    padding: 0.35rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: #555;
    margin-bottom: 1.5rem;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}
.bdt-tracking-num {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-weight: 700;
    color: #111;
    font-size: 1rem;
}
.bdt-result-title {
    font-size: 1.5rem !important;
    font-weight: 800 !important;
    color: #111 !important;
    margin: 0 0 1.5rem !important;
    letter-spacing: -0.02em !important;
}
.bdt-timeline {
    list-style: none !important;
    margin: 0 !important;
    padding: 0 !important;
    position: relative;
}
.bdt-timeline::before {
    content: '';
    position: absolute;
    left: 9px;
    top: 8px;
    bottom: 8px;
    width: 2px;
    background: #e5e5e5;
}
.bdt-timeline li {
    position: relative;
    padding: 0 0 1.5rem 2.25rem;
}
.bdt-timeline li:last-child {
    padding-bottom: 0;
}
.bdt-timeline-dot {
    position: absolute;
    left: 0;
    top: 4px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
}
.bdt-timeline li:first-child .bdt-timeline-dot {
    background: #111;
    border-color: #111;
}
.bdt-timeline-dot-inner {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #bbb;
}
.bdt-timeline li:first-child .bdt-timeline-dot-inner {
    background: #fff;
}
.bdt-event-status {
    font-size: 0.95rem;
    font-weight: 600;
    color: #111;
    margin: 0 0 0.2rem;
    line-height: 1.3;
}
.bdt-event-date {
    font-size: 0.8rem;
    color: #aaa;
    margin: 0;
}
.bdt-event-location {
    font-size: 0.8rem;
    color: #888;
    margin: 0.2rem 0 0;
}
</style>

<div class="bdt-result-wrap">
    <div class="bdt-provider-badge">
        📦 <?php echo esc_html( $selected_provider['name'] ); ?>
        &nbsp;·&nbsp;
        <span class="bdt-tracking-num"><?php echo esc_html( strtoupper( $tracking_number ) ); ?></span>
    </div>

    <h2 class="bdt-result-title"><?php esc_html_e( 'Suivi du colis', 'woo-bordereau-generator' ); ?></h2>

    <?php if ( ! empty( $tracking ) ) : ?>
    <ul class="bdt-timeline">
        <?php foreach ( $tracking as $event ) : ?>
        <li>
            <div class="bdt-timeline-dot">
                <div class="bdt-timeline-dot-inner"></div>
            </div>
            <p class="bdt-event-status"><?php echo esc_html( $event['status'] ); ?></p>
            <p class="bdt-event-date"><time><?php echo esc_html( $event['date'] ); ?></time></p>
            <?php if ( ! empty( $event['commune'] ) || ! empty( $event['wilaya'] ) ) : ?>
                <p class="bdt-event-location"><?php echo esc_html( trim( ( $event['commune'] ?? '' ) . ' — ' . ( $event['wilaya'] ?? '' ), ' — ' ) ); ?></p>
            <?php elseif ( ! empty( $event['location'] ) ) : ?>
                <p class="bdt-event-location"><?php echo esc_html( $event['location'] ); ?></p>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else : ?>
        <p style="color:#aaa;font-size:0.9rem;"><?php esc_html_e( 'Aucun événement de suivi disponible.', 'woo-bordereau-generator' ); ?></p>
    <?php endif; ?>
</div>
