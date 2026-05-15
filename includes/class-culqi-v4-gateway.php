<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_Culqi_V4 extends WC_Payment_Gateway
{
    protected $culqi_logo, $payment_methods;
    public function __construct()
    {
        $this->id = 'culqi_v4';
        $this->icon = PLUGIN_CULQI_URL . 'assets/images/cards.svg';
		$this->culqi_logo = PLUGIN_CULQI_URL . 'assets/images/culqi-logo.svg';
        $this->has_fields = true;
        $this->method_title = 'Culqi V4 (API Backend Puro)';
        $this->payment_methods = 'Medios de pago';
        $this->method_description = 'Integración pura con Culqi vía API Server-to-Server. Cero dependencias JS, soporte RSA opcional.';
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->public_key = $this->get_option('public_key');
        $this->secret_key = $this->get_option('secret_key');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_blocks_payment_gateway_support', array($this, 'add_blocks_support'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
    }

    public function add_blocks_support($gateways) 
    {
        $gateways[] = $this->id;
        return $gateways;
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'label' => 'Enable Culqi V3 Payment Gateway',
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'default' => 'Tarjeta de Crédito/Débito (Culqi V3)',
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'default' => 'Paga de forma segura con tu tarjeta de crédito o débito.',
            ),
            'testmode' => array(
                'title'       => 'Entorno',
                'label'       => 'Habilitar modo de Pruebas (Integración)',
                'type'        => 'checkbox',
                'description' => 'Marca esta casilla para hacer pagos de prueba. Desmárcala para producción.',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'rsa_id' => array(
                'title' => 'ID de Llave RSA (Opcional)',
                'type' => 'text',
                'description' => 'Si forzaste RSA en Culqi, pega el ID de la llave.',
                'default' => '',
            ),
            'rsa_public_key' => array(
                'title' => 'Llave Pública RSA (Opcional)',
                'type' => 'textarea',
                'description' => 'Si forzaste RSA en Culqi, pega el contenido de la llave pública.',
                'default' => '',
            ),
            'test_public_key' => array(
                'title' => 'Llave Pública de Pruebas (pk_test)',
                'type' => 'text',
                'description' => 'Tu llave pública de Integración de Culqi.',
                'default' => '',
            ),
            'test_private_key' => array(
                'title' => 'Llave Privada de Pruebas (sk_test)',
                'type' => 'password',
                'description' => 'Tu llave privada de Integración de Culqi.',
                'default' => '',
            ),
            'live_public_key' => array(
                'title' => 'Llave Pública de Producción (pk_live)',
                'type' => 'text',
                'description' => 'Tu llave pública de Producción de Culqi.',
                'default' => '',
            ),
            'live_private_key' => array(
                'title' => 'Llave Privada de Producción (sk_live)',
                'type' => 'password',
                'description' => 'Tu llave privada de Producción de Culqi.',
                'default' => '',
            ),

        );
    }

    public function payment_fields() {
        if ( $this->description ) {
            echo wpautop( wptexturize( $this->description ) );
        }
        
        $test_mode = $this->get_option('testmode');
        if ($test_mode === 'yes') {
            ?>
            <div class="culqi-test-cards" style="margin-top:15px; padding:15px; background:#fff3cd; border:1px solid #ffe69c; border-radius:5px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color: #856404; font-size: 13px;">🧪 Tarjetas de Prueba (Solo entorno Test)</label>
                <select id="culqi-test-cards-select" style="width:100%; border-radius:4px; border:1px solid #ffe69c; background:#fff; font-size:13px;">
                    <option value="">-- Selecciona una tarjeta de prueba --</option>
                    <optgroup label="Flujo 3DS (Autenticación)">
                        <option value="4456530000001096|07/30|111">Visa - Éxito CON Challenge</option>
                        <option value="4456530000001005|07/30|111">Visa - Éxito SIN Challenge (Frictionless)</option>
                        <option value="5200000000001096|12/30|111">MasterCard - Éxito CON Challenge</option>
                        <option value="5200000000001005|12/30|111">MasterCard - Éxito SIN Challenge</option>
                        <option value="4456530000001070|07/30|111">Visa - Fallo por Timeout</option>
                    </optgroup>
                    <optgroup label="Compras Directas (Sin 3DS)">
                        <option value="4111111111111111|09/30|123">Visa Débito - Venta Exitosa</option>
                        <option value="4111111110101113|09/30|123">Visa Crédito - Venta Exitosa</option>
                        <option value="5111111111111118|12/30|039">MasterCard - Venta Exitosa</option>
                        <option value="371212121212122|12/30|2841">Amex - Venta Exitosa</option>
                        <option value="36001212121210|12/30|964">Diners Club - Venta Exitosa</option>
                    </optgroup>
                    <optgroup label="Casos de Error">
                        <option value="4111110000000021|09/30|123">CULQ0001 - Fondos Insuficientes</option>
                        <option value="4000020000000000|10/30|354">stolen_card - Tarjeta Robada</option>
                        <option value="4000040000000008|03/30|295">insufficient_funds - Sin fondos</option>
                    </optgroup>
                </select>
            </div>
            <script>
                jQuery(document).ready(function($) {
                    $('#culqi-test-cards-select').on('change', function() {
                        let val = $(this).val();
                        if (val) {
                            let parts = val.split('|');
                            $('#culqi-card-number').val(parts[0]).trigger('input');
                            $('#culqi-card-expiry').val(parts[1]).trigger('input');
                            $('#culqi-card-cvv').val(parts[2]).trigger('input');
                            $('#culqi-card-email').val('review@culqi.com').trigger('input');
                        } else {
                            $('#culqi-card-number, #culqi-card-expiry, #culqi-card-cvv, #culqi-card-email').val('').trigger('input');
                        }
                    });
                });
            </script>
            <?php
        }

        echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent; margin-top: 15px;">';
        
        do_action('woocommerce_credit_card_form_start', $this->id);
        ?>
            <div class="form-row form-row-wide" style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color: #444;">Número de tarjeta <span class="required">*</span></label>
                <input type="text" name="culqi_card_number" id="culqi-card-number" class="input-text" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="•••• •••• •••• ••••" style="height: 45px; border-radius: 5px; width: 100%;">
            </div>
            
            <div style="display:flex; gap:15px;">
                <div class="form-row form-row-first" style="flex:1;">
                    <label style="display:block; margin-bottom:5px; font-weight:600; color: #444;">Vencimiento <span class="required">*</span></label>
                    <input type="text" name="culqi_card_expiry" id="culqi-card-expiry" class="input-text" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="MM/YY" style="height: 45px; border-radius: 5px; width: 100%;">
                </div>
                
                <div class="form-row form-row-last" style="flex:1;">
                    <label style="display:block; margin-bottom:5px; font-weight:600; color: #444;">CVV <span class="required">*</span></label>
                    <input type="password" name="culqi_card_cvv" id="culqi-card-cvv" class="input-text" autocomplete="cc-csc" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="CVV" style="height: 45px; border-radius: 5px; width: 100%;" maxlength="4">
                </div>
            </div>
            
            <div class="form-row form-row-wide" style="margin-top:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600; color: #444;">Correo electrónico <span class="required">*</span></label>
                <input type="email" name="culqi_card_email" id="culqi-card-email" class="input-text" placeholder="tucorreo@ejemplo.com" style="height: 45px; border-radius: 5px; width: 100%;">
            </div>
            <div class="clear"></div>

            <input type="hidden" name="culqi_device_id" id="culqi_device_id" value="" />
        </fieldset>

        <script>
            jQuery(document).ready(function($) {
                const cardLogos = {
                    'visa': 'url("<?php echo esc_js(plugin_dir_url(dirname(__FILE__)) . "assets/images/visa.svg"); ?>")',
                    'mastercard': 'url("<?php echo esc_js(plugin_dir_url(dirname(__FILE__)) . "assets/images/mastercard.svg"); ?>")',
                    'amex': 'url("<?php echo esc_js(plugin_dir_url(dirname(__FILE__)) . "assets/images/amex.svg"); ?>")',
                    'diners': 'url("<?php echo esc_js(plugin_dir_url(dirname(__FILE__)) . "assets/images/dinersclub.svg"); ?>")'
                };

                $(document).off('input', '#culqi-card-number').on('input', '#culqi-card-number', function() {
                    let val = $(this).val().replace(/\D/g, '');
                    let formatted = val.match(/.{1,4}/g);
                    if (formatted) {
                        $(this).val(formatted.join(' '));
                    }
                    let bgImage = 'none';
                    if (val.match(/^4/)) bgImage = cardLogos['visa'];
                    else if (val.match(/^(5[1-5]|2[2-7])/)) bgImage = cardLogos['mastercard'];
                    else if (val.match(/^3[47]/)) bgImage = cardLogos['amex'];
                    else if (val.match(/^3(?:0[0-5]|[68])/)) bgImage = cardLogos['diners'];

                    $(this).css({
                        'background-image': bgImage,
                        'background-repeat': 'no-repeat',
                        'background-position': 'right 15px center',
                        'background-size': '38px',
                        'transition': 'background-image 0.3s ease-in-out'
                    });
                });

                $(document).off('input', '#culqi-card-expiry').on('input', '#culqi-card-expiry', function() {
                    let val = $(this).val().replace(/\D/g, '');
                    if (val.length > 2) {
                        val = val.substring(0, 2) + '/' + val.substring(2, 4);
                    }
                    $(this).val(val);
                });
            });
        </script>
        <?php
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        
        $card_number = isset($_POST['culqi_card_number']) ? sanitize_text_field($_POST['culqi_card_number']) : '';
        $card_expiry = isset($_POST['culqi_card_expiry']) ? sanitize_text_field($_POST['culqi_card_expiry']) : '';
        $card_cvv = isset($_POST['culqi_card_cvv']) ? sanitize_text_field($_POST['culqi_card_cvv']) : '';
        $card_email = isset($_POST['culqi_card_email']) ? sanitize_email($_POST['culqi_card_email']) : '';

        // Limpieza de datos
        $card_number = str_replace(array(' ', '-'), '', $card_number);

        // Extraer mes y año
        $exp_month = '';
        $exp_year = '';
        if (strpos($card_expiry, '/') !== false) {
            $parts = explode('/', $card_expiry);
            $exp_month = trim($parts[0]);
            $exp_year = trim($parts[1]);
            if (strlen($exp_year) === 2) {
                $exp_year = '20' . $exp_year;
            }
        }

        if (empty($card_number) || empty($exp_month) || empty($exp_year) || empty($card_cvv)) {
            wc_add_notice(__('Error: Debes completar todos los datos de la tarjeta.', 'culqi'), 'error');
            return;
        }

        if (empty($card_email)) {
            $card_email = $order->get_billing_email();
        }

        // --- PASO 1: GENERAR TOKEN VÍA API SERVER-TO-SERVER ---
        $testmode = $this->get_option('testmode') === 'yes';
        $public_key = $testmode ? $this->get_option('test_public_key') : $this->get_option('live_public_key');
        $secret_key = $testmode ? $this->get_option('test_private_key') : $this->get_option('live_private_key');
        
        $rsa_id = $this->get_option('rsa_id');
        $rsa_pk = $this->get_option('rsa_public_key');

        $token_api_url = 'https://api.culqi.com/v2/tokens';
        $token_body = array(
            "card_number" => $card_number,
            "cvv" => $card_cvv,
            "expiration_month" => $exp_month,
            "expiration_year" => $exp_year,
            "email" => $card_email
        );

        $headers_token = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $public_key,
        );
        $payload_token = wp_json_encode($token_body);

        if (!empty($rsa_id) && !empty($rsa_pk)) {
            $headers_token['x-culqi-rsa-id'] = $rsa_id;
            $payload_token = wp_json_encode($this->encrypt_payload($token_body, $rsa_pk));
            $token_api_url = 'https://secure.culqi.com/v2/tokens';
        }

        $token_response = wp_remote_post($token_api_url, array(
            'method'    => 'POST',
            'body'      => $payload_token,
            'timeout'   => 30,
            'headers'   => $headers_token,
        ));

        if (is_wp_error($token_response)) {
            wc_add_notice(__('Error de conexión con Culqi al validar tarjeta.', 'culqi'), 'error');
            return;
        }

        $token_body_response = json_decode(wp_remote_retrieve_body($token_response), true);
        
        if (!isset($token_body_response['id'])) {
            $error_msg = 'Error desconocido al validar la tarjeta.';
            if (isset($token_body_response['user_message'])) {
                $error_msg = $token_body_response['user_message'];
            } elseif (isset($token_body_response['merchant_message'])) {
                $error_msg = 'Error Técnico: ' . $token_body_response['merchant_message'];
            }
            
            // Si quieres ver el JSON completo devuelto por Culqi para depurar, descomenta la siguiente línea:
            // $error_msg .= ' | RAW: ' . wp_remote_retrieve_body($token_response);

            wc_add_notice($error_msg, 'error');
            return;
        }

        $token_id = $token_body_response['id'];

        // --- PASO 2: REALIZAR EL CARGO ---
        $api_url = 'https://api.culqi.com/v2/charges';
        $device_id = isset($_POST['culqi_device_id']) ? sanitize_text_field($_POST['culqi_device_id']) : '';
        
        $body = array(
            "amount" => round($order->get_total() * 100),
            "currency_code" => $order->get_currency(),
            "email" => $card_email,
            "source_id" => $token_id,
            "antifraud_details" => array(
                "first_name" => $order->get_billing_first_name(),
                "last_name" => $order->get_billing_last_name(),
                "phone_number" => $order->get_billing_phone(),
                "device_finger_print_id" => $device_id
            )
        );

        $headers_charge = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $secret_key,
        );
        $payload_charge = wp_json_encode($body);

        if (!empty($rsa_id) && !empty($rsa_pk)) {
            $headers_charge['x-culqi-rsa-id'] = $rsa_id;
            $payload_charge = wp_json_encode($this->encrypt_payload($body, $rsa_pk));
        }

        $response = wp_remote_post($api_url, array(
            'method'    => 'POST',
            'body'      => $payload_charge,
            'timeout'   => 45,
            'headers'   => $headers_charge,
        ));

        if (is_wp_error($response)) {
            wc_add_notice(__('Error de conexión con Culqi.', 'culqi'), 'error');
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        // Caso 1: Requiere 3DS (Cod. 200 o 201 con action_code = REVIEW)
        if (isset($response_body['action_code']) && $response_body['action_code'] === 'REVIEW') {
            $order->update_meta_data('_culqi_3ds_token', $token_id);
            $order->update_status('pending', __('Pendiente de autenticación 3DS.', 'culqi'));
            $order->save();

            // Para el flujo 3DS, redirigimos a la página de pago del pedido (Receipt Page)
            // donde cargaremos el iframe de Culqi 3DS.
            return array(
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        // Caso 2: Éxito Directo (Sin 3DS)
        if (isset($response_body['object']) && $response_body['object'] === 'charge') {
            $order->payment_complete($response_body['id']);
            $order->add_order_note(sprintf(__('Pago exitoso con Culqi (ID: %s)', 'culqi'), $response_body['id']));
            WC()->cart->empty_cart();

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        // Caso 3: Error
        $error_message = isset($response_body['user_message']) ? $response_body['user_message'] : __('La tarjeta fue rechazada por el banco emisor.', 'culqi');
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
				jQuery('label[for="payment_method_culqi_v4"]').contents().filter(function() {
					return this.nodeType === 3;
				}).first().remove();
			</script>
			<style>
				.wc-culqi-container {
					width: 100%;
					display: flex;
					align-items: center;
					justify-content: space-between;
				}
				.wc-culqi-icon {
    				margin-left: 8px !important;
					height: 1.3em !important;
				}
				.wc-culqi-title {
					float: none !important;
					display: inline-block;
					margin-left: 0 !important;
                    height: 25px;
				}
                li.payment_method_culqi_v4 label {
                    width: 100%;
                    display: flex !important;
                    align-items: center;
                    justify-content: space-between;
                }
			</style>
            <div class="wc-culqi-container">
			    <img class="wc-culqi-title" src="<?php echo esc_url( $this->culqi_logo ); ?>" alt="Culqi" />
                <img class="wc-culqi-icon" src="<?php echo esc_url( PLUGIN_CULQI_URL . 'assets/images/cards.svg' ); ?>" alt="Cards" />
            </div>
		<?php
    }

    private function encrypt_payload($data, $public_key) {
        // Encriptar payload con RSA-AES (Culqi spec)
        if (!class_exists('\phpseclib3\Crypt\RSA') && !class_exists('\phpseclib\Crypt\RSA')) {
            // Si phpseclib no está disponible, intentar devolverlo sin encriptar
            return $data;
        }

        $key = openssl_random_pseudo_bytes(32);
        $iv = openssl_random_pseudo_bytes(16);
        $message = wp_json_encode($data);

        $tag = '';
        $ciphertext = openssl_encrypt($message, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        $encrypted_message = base64_encode($ciphertext . $tag);

        if (class_exists('\phpseclib3\Crypt\RSA')) {
            $rsa = \phpseclib3\Crypt\PublicKeyLoader::load($public_key)
                    ->withPadding(\phpseclib3\Crypt\RSA::ENCRYPTION_OAEP)
                    ->withHash('sha256')
                    ->withMGFHash('sha256');
            $encrypted_key = base64_encode($rsa->encrypt($key));
            $encrypted_iv = base64_encode($rsa->encrypt($iv));
        } else {
            $rsa = new \phpseclib\Crypt\RSA();
            $rsa->loadKey($public_key);
            $rsa->setEncryptionMode(\phpseclib\Crypt\RSA::ENCRYPTION_OAEP);
            $rsa->setHash('sha256');
            $rsa->setMGFHash('sha256');
            $encrypted_key = base64_encode($rsa->encrypt($key));
            $encrypted_iv = base64_encode($rsa->encrypt($iv));
        }

        return array(
            "encrypted_data" => $encrypted_message,
            "encrypted_key" => $encrypted_key,
            "encrypted_iv" => $encrypted_iv
        );
    }

    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
        $token_id = $order->get_meta('_culqi_3ds_token');
        
        if (empty($token_id)) {
            echo '<p>Error: No se encontró el token 3DS para esta orden.</p>';
            return;
        }

        $testmode = $this->get_option('testmode') === 'yes';
        $public_key = $testmode ? $this->get_option('test_public_key') : $this->get_option('live_public_key');
        
        ?>
        <div id="culqi-3ds-container" style="text-align:center; padding: 20px;">
            <h3>Validación de Seguridad 3DS</h3>
            <p>Tu banco requiere una validación adicional para completar la compra. Por favor, espera mientras cargamos la ventana de autenticación...</p>
            <div id="culqi-3ds-loader" style="margin-top:20px;">Cargando...</div>
        </div>

        <script src="https://js.culqi.com/v3"></script>
        <script src="https://3ds.culqi.com"></script>
        <script>
        jQuery(document).ready(function($) {
            Culqi3DS.publicKey = "<?php echo esc_js($public_key); ?>";
            Culqi3DS.settings = {
                charge: {
                    totalAmount: <?php echo round($order->get_total() * 100); ?>,
                    returnUrl: "<?php echo esc_js($this->get_return_url($order)); ?>"
                },
                card: {
                    email: "<?php echo esc_js($order->get_billing_email()); ?>",
                }
            };
            
            Culqi3DS.initAuthentication("<?php echo esc_js($token_id); ?>");

            let isProcessing3DS = false;

            window.addEventListener("message", function(event) {
                if (isProcessing3DS) return;

                // Aceptar orígenes conocidos o el mismo dominio (Frictionless flow usa postMessage local)
                let isAllowed = event.origin.includes("culqi.com") || 
                                event.origin.includes("cardinalcommerce.com") || 
                                event.origin.includes("cybersource.com") || 
                                event.origin === window.location.origin;

                if (isAllowed) {
                    let data = event.data;
                    
                    if (typeof data === "string") {
                        try {
                            data = JSON.parse(data);
                        } catch (e) {
                            return;
                        }
                    }

                    // Ignorar eventos de carga
                    if (data && data.loading === true) return;

                    // Manejar errores explícitos del objeto local o status FAILED
                    if (data && ((data.error) || (data.status && data.status.indexOf('FAILED') !== -1))) {
                        alert('La autenticación de tu banco falló. Por favor intenta de nuevo.');
                        let redirectUrl = "<?php echo esc_js($order->get_checkout_payment_url(false)); ?>";
                        window.location.href = redirectUrl.replace(/&amp;/g, '&');
                        return;
                    }

                    let paramsToSend = null;

                    // Formato antiguo o Checkout V4
                    if (data && data.parameters3DS) {
                        paramsToSend = data.parameters3DS;
                    } 
                    // Formato directo de Cardinal Commerce (3ds.culqi.com)
                    else if (data && data.status === 'AUTHENTICATION_SUCCESSFUL' && data.consumerAuthenticationInformation) {
                        let authInfo = data.consumerAuthenticationInformation;
                        paramsToSend = {
                            eci: authInfo.eci || authInfo.eciRaw || "05",
                            xid: authInfo.xid,
                            cavv: authInfo.cavv,
                            directoryServerTransactionId: authInfo.directoryServerTransactionId,
                            protocolVersion: authInfo.specificationVersion || "2.2.0"
                        };
                    } else if (data && data.action === 'authentication_notified') {
                        // Fallback por si acaso Culqi maneja el cobro por detrás
                        paramsToSend = 'notified';
                    }

                    if (paramsToSend) {
                        isProcessing3DS = true;
                        $('#culqi-3ds-loader').html('<strong style="color:green;">Validación completada. Procesando el cargo en el servidor...</strong>');
                        
                        $.post('<?php echo admin_url("admin-ajax.php"); ?>', {
                            action: 'culqi_v4_process_3ds_charge',
                            order_id: <?php echo $order_id; ?>,
                            parameters3DS: paramsToSend,
                            nonce: '<?php echo wp_create_nonce("culqi_v4_nonce"); ?>'
                        }, function(response) {
                            if (response.success) {
                                window.location.href = response.data.redirect;
                            } else {
                                alert('Error al procesar el cargo: ' + response.data.message);
                                isProcessing3DS = false;
                                let errorRedirect = "<?php echo esc_js($order->get_checkout_payment_url(false)); ?>";
                                window.location.href = errorRedirect.replace(/&amp;/g, '&');
                            }
                        }).fail(function(xhr, status, error) {
                            alert('Error de conexión AJAX al procesar el cargo 3DS: ' + error);
                            window.location.reload();
                        });
                    }
                }
            });
        });
        </script>
        <?php
    }
}
