<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_api_culqi_webhook', 'culqi_webhook_handler');

function culqi_webhook_handler() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_send_json_error(array('message' => 'Solo se aceptan peticiones POST.'), 405);
    }

    $raw_body = file_get_contents('php://input');
    $payload = json_decode($raw_body, true);

    if (!$payload || !isset($payload['object']) || !in_array($payload['object'], array('event', 'refund', 'charge', 'order'))) {
        wp_send_json_error(array('message' => 'Payload inválido o no reconocido por Culqi.'), 400);
    }

    // Validar credenciales si están configuradas en la pasarela
    $gateway_settings = get_option('woocommerce_culqi_v4_settings', array());
    $auth_user = isset($gateway_settings['webhook_user']) ? trim($gateway_settings['webhook_user']) : '';
    $auth_pass = isset($gateway_settings['webhook_password']) ? trim($gateway_settings['webhook_password']) : '';

    if (!empty($auth_user) || !empty($auth_pass)) {
        $req_user = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
        $req_pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

        // Si PHP_AUTH_USER no se pobló, revisar el header Authorization
        if (empty($req_user) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
            if (strpos(strtolower($auth_header), 'basic ') === 0) {
                $base64_cred = substr($auth_header, 6);
                $decoded = base64_decode($base64_cred);
                if (strpos($decoded, ':') !== false) {
                    list($req_user, $req_pass) = explode(':', $decoded, 2);
                }
            }
        }

        if ($req_user !== $auth_user || $req_pass !== $auth_pass) {
            wp_send_json_error(array('message' => 'Autenticación fallida.'), 401);
        }
    }

    $event_type = isset($payload['type']) ? $payload['type'] : '';
    
    // Si el payload principal es directamente el objeto (sin wrapper event), usarlo directo
    if ($payload['object'] !== 'event') {
        $data_object = $payload;
        if (empty($event_type)) {
            if ($payload['object'] === 'refund') $event_type = 'refund.creation.succeeded';
            if ($payload['object'] === 'charge') $event_type = 'charge.creation.succeeded';
        }
    } else {
        $data_param = isset($payload['data']) ? $payload['data'] : array();
        if (is_string($data_param)) {
            $data_param = json_decode($data_param, true);
        }
        $data_object = is_array($data_param) ? $data_param : array();
    }

    if (empty($data_object) || !isset($data_object['id'])) {
        wp_send_json_success(array('message' => 'Evento sin objeto de datos identificable. Ignorado.'));
    }

    $culqi_id = $data_object['id']; // ID del cargo (chr_...), orden (ord_...) o devolución (ref_...)
    $object_type = isset($data_object['object']) ? $data_object['object'] : '';
    
    // Identificar orden de WooCommerce
    $order = null;
    $order_id = 0;

    if (isset($data_object['metadata']) && isset($data_object['metadata']['order_id'])) {
        $order_id = intval($data_object['metadata']['order_id']);
        if ($order_id > 0) {
            $order = wc_get_order($order_id);
        }
    }

    $target_charge_id = isset($data_object['chargeId']) ? $data_object['chargeId'] : (isset($data_object['charge_id']) ? $data_object['charge_id'] : '');

    // Si no se encontró por metadata, buscar por transaction_id o _culqi_charge_id
    if (!$order && $object_type === 'refund' && !empty($target_charge_id)) {
        $orders = wc_get_orders(array(
            'meta_key' => '_transaction_id',
            'meta_value' => $target_charge_id,
            'limit' => 1,
        ));
        if (!empty($orders)) {
            $order = $orders[0];
        } else {
            $orders = wc_get_orders(array(
                'meta_key' => '_culqi_charge_id',
                'meta_value' => $target_charge_id,
                'limit' => 1,
            ));
            if (!empty($orders)) {
                $order = $orders[0];
            }
        }
    }

    if (!$order && ($object_type === 'charge' || $object_type === 'order')) {
        $orders = wc_get_orders(array(
            'meta_key' => '_transaction_id',
            'meta_value' => $culqi_id,
            'limit' => 1,
        ));
        if (!empty($orders)) {
            $order = $orders[0];
        } else {
            $orders = wc_get_orders(array(
                'meta_key' => '_culqi_charge_id',
                'meta_value' => $culqi_id,
                'limit' => 1,
            ));
            if (!empty($orders)) {
                $order = $orders[0];
            }
        }
    }

    if (!$order) {
        wp_send_json_success(array(
            'message' => 'No se encontró la orden asociada en WooCommerce para el ID: ' . $culqi_id
        ));
    }

    // Procesar según el tipo de evento
    if ($event_type === 'order.status.changed' || $event_type === 'charge.creation.succeeded') {
        $should_complete = false;
        if ($object_type === 'charge') {
            $status = isset($data_object['status']) ? strtoupper($data_object['status']) : '';
            if ($status === 'CAPTURED' || empty($data_object['error'])) {
                $should_complete = true;
            }
        } elseif ($object_type === 'order') {
            $state = isset($data_object['state']) ? strtolower($data_object['state']) : '';
            if ($state === 'paid') {
                $should_complete = true;
            }
        }

        if ($should_complete) {
            if ($order->needs_payment() || in_array($order->get_status(), array('pending', 'failed', 'on-hold'))) {
                $order->payment_complete($culqi_id);
                $order->add_order_note(sprintf(__('Pago confirmado y completado vía Webhook de Culqi (%s ID: %s)', 'culqi'), strtoupper($object_type), $culqi_id));
            }
        }
    } elseif ($event_type === 'refund.creation.succeeded') {
        if ($object_type === 'refund') {
            $amount_refunded = isset($data_object['amount']) ? round($data_object['amount'] / 100, 2) : 0;
            $reason = isset($data_object['reason']) ? $data_object['reason'] : 'Sin especificar';
            
            $note = sprintf(__('Devolución registrada en Culqi vía Webhook. ID Devolución: %s | Monto: S/ %s | Motivo: %s', 'culqi'), $culqi_id, number_format($amount_refunded, 2), $reason);
            
            if ($amount_refunded > 0 && round($amount_refunded, 2) >= round((float) $order->get_total(), 2)) {
                $order->update_status('refunded', $note);
            } else {
                $order->add_order_note($note);
            }
        }
    }

    wp_send_json_success(array('message' => 'Evento procesado correctamente.'));
}
