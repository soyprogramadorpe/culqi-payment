<?php
/*
Plugin Name: Culqi Payment Server-to-Server
Plugin URI: https://soyprogramador.pe
Description: Culqi V4 con integración pura por API Server-to-Server, campos nativos sin modales ni dependencias JS.
Version: 4.0.0
Author: soyprogramador.pe
Author URI: https://soyprogramador.pe
Developer: soyprogramador.pe
Developer URI: https://soyprogramador.pe
License: GPLv2 or later
Text Domain: culqi-v4
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 5.6
Requires PHP: 7.0
WC requires at least: 2.6.11
WC tested up to: 3.0.0
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once plugin_dir_path(__FILE__) . 'constants.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions/gateway-scripts.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions/webhook.php';
// Activation Hook
register_activation_hook(__FILE__, 'culqi_v4_payment_activate');
function culqi_v4_payment_activate() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('WooCommerce is required to use the Culqi Payment Gateway plugin. Please install and activate WooCommerce.');
    }
}

// Deactivation Hook
register_deactivation_hook(__FILE__, 'culqi_v4_payment_deactivate');
function culqi_v4_payment_deactivate() {
    // culqi_delete_table();
}

add_action('plugins_loaded', 'culqi_v4_gateway_init', 11);

function culqi_v4_gateway_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Include the Culqi Payment Gateway Classes
    require_once plugin_dir_path(__FILE__) . 'includes/class-culqi-v4-gateway.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-culqi-yape-gateway.php';
    require_once plugin_dir_path(__FILE__) . 'includes/functions/process-3ds.php';
    // Add the gateways to WooCommerce
    add_filter('woocommerce_payment_gateways', 'culqi_v4_add_culqi_gateway');
    
    function culqi_v4_add_culqi_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Culqi_V4'; // Payment Gateway class
        $methods[] = 'WC_Gateway_Culqi_Yape'; // Yape Gateway class
        return $methods;
    }
}

use Automattic\WooCommerce\Utilities\FeaturesUtil;

add_action('before_woocommerce_init', function () {
    if (class_exists(FeaturesUtil::class)) {
        FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__);
    }

    if (class_exists(FeaturesUtil::class)) {
        FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__);
    }
});
