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
 * Detectar cuando el cliente vuelve a la página order-pay después de un pago fallido.
 * Usamos el query param ?culqi_error=1 que inyecta el JS al redirigir desde un error 3DS,
 * o verificamos si la orden tiene status 'failed'.
 * wc_add_notice() se muestra arriba de todo en la página, como notice nativo de WooCommerce.
 */
add_action('wp', 'culqi_detect_payment_error');
function culqi_detect_payment_error() {
    if (!is_wc_endpoint_url('order-pay')) return;
    if (!isset($_GET['culqi_error']) || $_GET['culqi_error'] !== '1') return;

    wc_add_notice(
        __('⚠️ El intento de pago anterior no pudo ser procesado. Por favor, verifica que tu tarjeta tenga fondos suficientes, esté habilitada para compras por internet o intenta con otro medio de pago.', 'culqi'),
        'error'
    );
}

/**
 * Mostrar resumen de datos del cliente en la página order-pay.
 * Incluye: número de orden, fecha, nombre, facturación y envío.
 */
add_action('before_woocommerce_pay', 'culqi_order_pay_customer_details');
function culqi_order_pay_customer_details() {
    global $wp;
    $order_id = isset($wp->query_vars['order-pay']) ? absint($wp->query_vars['order-pay']) : 0;
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $billing_name  = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    $billing_email = $order->get_billing_email();
    $billing_phone = $order->get_billing_phone();
    $order_date    = wc_format_datetime($order->get_date_created());

    // Construir dirección sin el nombre (evitar duplicado)
    $billing_parts = array_filter(array(
        $order->get_billing_address_1(),
        $order->get_billing_address_2(),
        $order->get_billing_city(),
        $order->get_billing_state(),
        $order->get_billing_postcode(),
    ));
    $billing_addr = implode(', ', $billing_parts);
    ?>
    <div class="culqi-orderpay">
        <div class="culqi-orderpay__header">
            <div class="culqi-orderpay__badge">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                <?php echo esc_html__('Pedido', 'culqi'); ?> <strong>#<?php echo esc_html($order->get_order_number()); ?></strong>
            </div>
            <div class="culqi-orderpay__meta">
                <span><?php echo esc_html($order_date); ?></span>
                <span class="culqi-orderpay__total"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></span>
            </div>
        </div>

        <div class="culqi-orderpay__body">
            <div class="culqi-orderpay__card">
                <div class="culqi-orderpay__card-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div class="culqi-orderpay__card-content">
                    <span class="culqi-orderpay__card-label"><?php esc_html_e('Facturación', 'culqi'); ?></span>
                    <strong><?php echo esc_html($billing_name); ?></strong>
                    <?php if ($billing_email) : ?>
                        <span class="culqi-orderpay__detail"><?php echo esc_html($billing_email); ?></span>
                    <?php endif; ?>
                    <?php if ($billing_phone) : ?>
                        <span class="culqi-orderpay__detail"><?php echo esc_html($billing_phone); ?></span>
                    <?php endif; ?>
                    <?php if ($billing_addr) : ?>
                        <span class="culqi-orderpay__detail culqi-orderpay__addr"><?php echo esc_html($billing_addr); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Inyectar id="culqi-gateway" en el <ul> de métodos de pago de WooCommerce.
 * Esto nos permite usar #culqi-gateway como raíz CSS con máxima especificidad,
 * garantizando que nuestros estilos siempre ganen sobre themes y WooCommerce.
 * Nota: is_checkout() NO cubre la página order-pay, por eso también usamos is_wc_endpoint_url().
 */
add_action('wp_footer', 'culqi_inject_gateway_id');
function culqi_inject_gateway_id() {
    if (!is_checkout() && !is_wc_endpoint_url('order-pay')) return;
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
