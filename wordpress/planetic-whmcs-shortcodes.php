<?php
/**
 * Plugin Name: Planetic Main Website Checkout Shortcodes
 * Description: Optional WordPress bridge shortcodes that send visitors to the Planetic Solutions main-site checkout flow. No WHMCS secrets are stored in WordPress.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function planetic_checkout_base_url(): string
{
    return rtrim((string) get_option('planetic_checkout_base_url', 'https://planeticsolution.com'), '/');
}

add_shortcode('planetic_domain_search', static function (): string {
    $action = esc_url(planetic_checkout_base_url() . '/domain-search');

    return '<form class="planetic-domain-search" action="' . $action . '" method="get">'
        . '<input type="text" name="domain" placeholder="Find your perfect domain" required>'
        . '<button type="submit">Search Domain</button>'
        . '</form>';
});

add_shortcode('planetic_checkout_button', static function (array $atts): string {
    $atts = shortcode_atts([
        'type' => 'bundle',
        'plan' => '',
        'label' => 'Get Started',
    ], $atts);

    $query = array_filter([
        'type' => sanitize_key($atts['type']),
        'plan' => sanitize_title($atts['plan']),
    ]);

    $url = esc_url(planetic_checkout_base_url() . '/checkout?' . http_build_query($query));
    return '<a class="planetic-checkout-button" href="' . $url . '">' . esc_html($atts['label']) . '</a>';
});

add_shortcode('planetic_checkout_embed', static function (): string {
    $url = esc_url(planetic_checkout_base_url() . '/checkout');
    return '<iframe title="Planetic Checkout" src="' . $url . '" style="width:100%;min-height:980px;border:0;"></iframe>';
});
