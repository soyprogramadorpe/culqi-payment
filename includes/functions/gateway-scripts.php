<?php
add_action('wp_enqueue_scripts', 'culqi_payment_enqueue_scripts');

function culqi_payment_enqueue_scripts() {
    wp_enqueue_style( 'culqi-v4-gateway', plugin_dir_url(__FILE__) . '../../assets/css/gateway.css', [], PLUGIN_VERSION );
    
    if (is_checkout()) {
        // La validación de Culqi ahora se hace 100% por backend (Server-to-Server)
        // por lo que ya no es necesario inyectar JS de tokenización externo.
    }
}
