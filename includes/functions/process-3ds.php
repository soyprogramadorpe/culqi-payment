<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_culqi_v4_process_3ds_charge', 'culqi_v4_process_3ds_charge');
add_action('wp_ajax_nopriv_culqi_v4_process_3ds_charge', 'culqi_v4_process_3ds_charge');

function culqi_v4_process_3ds_charge() {
    check_ajax_referer('culqi_v4_nonce', 'nonce');

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $parameters3DS = isset($_POST['parameters3DS']) ? $_POST['parameters3DS'] : null;

    if (!$order_id || !$parameters3DS) {
        wp_send_json_error(array('message' => 'Faltan parámetros requeridos.'));
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(array('message' => 'Orden no encontrada.'));
    }

    $token_id = $order->get_meta('_culqi_3ds_token');
    if (empty($token_id)) {
        wp_send_json_error(array('message' => 'Token 3DS no encontrado.'));
    }

    $gateway = new WC_Gateway_Culqi_V4();
    $testmode = $gateway->get_option('testmode') === 'yes';
    $secret_key = $testmode ? $gateway->get_option('test_private_key') : $gateway->get_option('live_private_key');

    $api_url = 'https://api.culqi.com/v2/charges';
    
    $body = array(
        "amount" => round($order->get_total() * 100),
        "currency_code" => $order->get_currency(),
        "email" => $order->get_billing_email(),
        "source_id" => $token_id,
        "authentication_3DS" => $parameters3DS,
        "antifraud_details" => array(
            "first_name" => $order->get_billing_first_name(),
            "last_name" => $order->get_billing_last_name(),
            "phone_number" => $order->get_billing_phone()
        )
    );

    $response = wp_remote_post($api_url, array(
        'method'    => 'POST',
        'body'      => wp_json_encode($body),
        'timeout'   => 45,
        'headers'   => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $secret_key,
        ),
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Error de conexión con Culqi al procesar 3DS.'));
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($response_body['object']) && $response_body['object'] === 'charge') {
        $order->payment_complete($response_body['id']);
        $order->add_order_note(sprintf(__('Pago exitoso con Culqi 3DS (ID: %s)', 'culqi'), $response_body['id']));
        WC()->cart->empty_cart();

        wp_send_json_success(array(
            'redirect' => $gateway->get_return_url($order)
        ));
    }

    $error_message = isset($response_body['user_message']) ? $response_body['user_message'] : 'Error al procesar el pago 3DS.';
    if (isset($response_body['merchant_message'])) {
        $error_message .= ' (' . $response_body['merchant_message'] . ')';
    }

    $order->update_status('failed', 'Error 3DS: ' . $error_message);
    
    wp_send_json_error(array('message' => $error_message));
}
