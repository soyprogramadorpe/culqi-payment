<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_Culqi_Yape extends WC_Payment_Gateway
{
    protected $yape_logo;
    
    public function __construct()
    {
        $this->id = 'culqi_yape';
        $this->icon = plugin_dir_url(dirname(__FILE__)) . 'assets/images/yape.svg';
        $this->yape_logo = plugin_dir_url(dirname(__FILE__)) . 'assets/images/yape.svg';
        $this->has_fields = true;
        $this->method_title = 'Yape (Culqi Server-to-Server)';
        $this->method_description = 'Integra pagos con Yape mediante Culqi usando API directa sin dependencias Frontend.';
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        
        // Heredar llaves de la pasarela principal de Culqi V4 para no duplicar configuración
        $culqi_settings = get_option('woocommerce_culqi_v4_settings', array());
        $this->testmode = isset($culqi_settings['testmode']) && $culqi_settings['testmode'] === 'yes';
        $this->public_key = $this->testmode ? ($culqi_settings['test_public_key'] ?? '') : ($culqi_settings['live_public_key'] ?? '');
        $this->secret_key = $this->testmode ? ($culqi_settings['test_private_key'] ?? '') : ($culqi_settings['live_private_key'] ?? '');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Habilitar/Deshabilitar',
                'label' => 'Habilitar pagos con Yape',
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'title' => array(
                'title' => 'Título',
                'type' => 'text',
                'description' => 'El título que el usuario ve durante el checkout.',
                'default' => 'Yape',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Descripción',
                'type' => 'textarea',
                'description' => 'La descripción que el usuario ve durante el checkout.',
                'default' => 'Paga de forma rápida y segura con Yape.',
            ),
            'info_keys' => array(
                'title' => 'Llaves API',
                'type' => 'title',
                'description' => '<em>Nota: Las llaves Públicas y Privadas se heredan automáticamente de la configuración principal de <strong>Culqi Payment Server-to-Server (Tarjetas)</strong>. No necesitas ingresarlas de nuevo.</em>'
            )
        );
    }

    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
        
        // Mostrar aviso si el plugin principal está en modo pruebas
        if ($this->testmode) {
            echo '<div class="culqi-yape-sandbox-notice">';
            echo '<strong>Modo Pruebas Activo</strong><br>Usa el celular: <code>900 000 001</code> y OTP: <code>123456</code> (los campos se han rellenado automáticamente).';
            echo '</div>';
        }

        echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form culqi-fieldset">';
        do_action('woocommerce_credit_card_form_start', $this->id);
        
        $default_phone = $this->testmode ? '900 000 001' : '';
        $default_otp = $this->testmode ? rand(100000, 999999) : '';
        ?>
            <div class="form-row form-row-wide culqi-field-group">
                <label class="culqi-field-label">Número de Celular <span class="required">*</span></label>
                <input type="tel" name="culqi_yape_phone" id="culqi-yape-phone" class="input-text culqi-field-input" autocomplete="tel" placeholder="987 654 321" maxlength="11" value="<?php echo esc_attr($default_phone); ?>">
            </div>
            
            <div class="form-row form-row-wide culqi-field-group">
                <label class="culqi-field-label">Código de Aprobación (OTP) <span class="required">*</span></label>
                <input type="tel" name="culqi_yape_otp" id="culqi-yape-otp" class="input-text culqi-field-input" placeholder="123456" maxlength="6" value="<?php echo esc_attr($default_otp); ?>">
                <small class="culqi-yape-hint">Abre tu app Yape, ve al menú superior izquierdo y selecciona <strong>"Código de Aprobación"</strong>.</small>
            </div>
            <div class="clear"></div>
            
            <script>
                jQuery(document).ready(function($) {
                    $('#culqi-yape-phone').on('input', function() {
                        let val = $(this).val().replace(/\D/g, '');
                        // Opcional: Formato 999 999 999
                        let formatted = val.match(/.{1,3}/g);
                        if (formatted) {
                            $(this).val(formatted.join(' '));
                        }
                    });
                    
                    $('#culqi-yape-otp').on('input', function() {
                        let val = $(this).val().replace(/\D/g, '');
                        $(this).val(val);
                    });
                });
            </script>
        </fieldset>
        <?php
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        
        $yape_phone = isset($_POST['culqi_yape_phone']) ? sanitize_text_field($_POST['culqi_yape_phone']) : '';
        $yape_otp = isset($_POST['culqi_yape_otp']) ? sanitize_text_field($_POST['culqi_yape_otp']) : '';

        $yape_phone = str_replace(' ', '', $yape_phone);

        if (empty($yape_phone) || empty($yape_otp)) {
            wc_add_notice(__('Error: Debes ingresar tu número de celular y el código de aprobación de Yape.', 'culqi'), 'error');
            return;
        }

        if (strlen($yape_phone) !== 9 || strlen($yape_otp) !== 6) {
            wc_add_notice(__('Error: El número de celular debe tener 9 dígitos y el código de aprobación 6 dígitos.', 'culqi'), 'error');
            return;
        }

        if (empty($this->public_key) || empty($this->secret_key)) {
            wc_add_notice(__('Error: Faltan las llaves de configuración de Culqi.', 'culqi'), 'error');
            return;
        }

        // --- PASO 1: GENERAR TOKEN YAPE VÍA API ---
        $token_api_url = 'https://secure.culqi.com/v2/tokens/yape';
        $token_body = array(
            "number_phone" => $yape_phone,
            "otp" => $yape_otp,
            "amount" => round($order->get_total() * 100),
            // Metadata opcional, Yape no exige email para el token, pero se puede enviar
            // En la documentación de Culqi v2, el body para yape es number_phone, otp, amount
        );

        $headers_token = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->public_key,
        );

        $token_response = wp_remote_post($token_api_url, array(
            'method'    => 'POST',
            'body'      => wp_json_encode($token_body),
            'timeout'   => 30,
            'headers'   => $headers_token,
        ));

        if (is_wp_error($token_response)) {
            wc_add_notice(__('Error de conexión con Culqi al generar el token Yape.', 'culqi'), 'error');
            return;
        }

        $token_body_response = json_decode(wp_remote_retrieve_body($token_response), true);
        
        if (!isset($token_body_response['id'])) {
            $error_msg = 'Error al validar Yape.';
            if (isset($token_body_response['user_message'])) {
                $error_msg = $token_body_response['user_message'];
            } elseif (isset($token_body_response['merchant_message'])) {
                $error_msg = 'Error Técnico: ' . $token_body_response['merchant_message'];
            }
            wc_add_notice($error_msg, 'error');
            return;
        }

        $token_id = $token_body_response['id'];

        // --- PASO 2: REALIZAR EL CARGO ---
        $api_url = 'https://api.culqi.com/v2/charges';
        
        $body = array(
            "amount" => round($order->get_total() * 100),
            "currency_code" => $order->get_currency(),
            "email" => $order->get_billing_email(),
            "source_id" => $token_id,
            "antifraud_details" => array(
                "first_name" => $order->get_billing_first_name(),
                "last_name" => $order->get_billing_last_name(),
                "phone_number" => $order->get_billing_phone()
            )
        );

        $headers_charge = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->secret_key,
        );

        $response = wp_remote_post($api_url, array(
            'method'    => 'POST',
            'body'      => wp_json_encode($body),
            'timeout'   => 45,
            'headers'   => $headers_charge,
        ));

        if (is_wp_error($response)) {
            wc_add_notice(__('Error de conexión con Culqi al procesar el cargo.', 'culqi'), 'error');
            return;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        // Caso: Éxito Directo (Yape no usa 3DS)
        if (isset($response_body['object']) && $response_body['object'] === 'charge') {
            $order->payment_complete($response_body['id']);
            $order->add_order_note(sprintf(__('Pago Yape exitoso con Culqi (ID: %s)', 'culqi'), $response_body['id']));
            WC()->cart->empty_cart();

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        // Caso: Error en Cargo
        $error_message = isset($response_body['user_message']) ? $response_body['user_message'] : __('El pago con Yape fue rechazado.', 'culqi');
        if (isset($response_body['merchant_message'])) {
            $error_message .= ' (' . $response_body['merchant_message'] . ')';
        }
        wc_add_notice($error_message, 'error');
        return;
    }

    public function get_icon() 
    {
		?>
			<script>
				jQuery('label[for="payment_method_culqi_yape"]').contents().filter(function() {
					return this.nodeType === 3;
				}).first().remove();
			</script>

            <div class="wc-culqi-yape-container">
			    <span class="wc-culqi-yape-title"><?php echo esc_html($this->title); ?></span>
                <img class="wc-culqi-yape-icon" src="<?php echo esc_url( $this->yape_logo ); ?>" alt="Yape" />
            </div>
		<?php
    }
}
