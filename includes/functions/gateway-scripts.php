<?php
add_action('wp_enqueue_scripts', 'culqi_payment_enqueue_scripts');

function culqi_payment_enqueue_scripts() {
    wp_enqueue_style( 'culqi-v4-gateway', plugin_dir_url(__FILE__) . '../../assets/css/gateway.css', [], PLUGIN_VERSION );
    
    if (is_checkout()) {
        // La validación de Culqi ahora se hace 100% por backend (Server-to-Server)
        // por lo que ya no es necesario inyectar JS de tokenización externo.
    }
}

/**
 * Inyectar id="culqi-gateway" en el <ul> de métodos de pago de WooCommerce.
 * Esto nos permite usar #culqi-gateway como raíz CSS con máxima especificidad,
 * garantizando que nuestros estilos siempre ganen sobre themes y WooCommerce.
 */
add_action('wp_footer', 'culqi_inject_gateway_id');
function culqi_inject_gateway_id() {
    if (!is_checkout()) return;
    ?>
    <script>
        (function() {
            function applyId() {
                var ul = document.querySelector('ul.wc_payment_methods.payment_methods');
                if (ul && !ul.id) {
                    ul.id = 'culqi-gateway';
                }
            }
            // Aplicar inmediatamente y después de cada actualización AJAX del checkout
            applyId();
            jQuery(document.body).on('updated_checkout', applyId);
        })();
    </script>
    <?php
}
