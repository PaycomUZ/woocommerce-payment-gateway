<?php
/*
Plugin Name: Payme
Plugin URI:  http://paycom.uz
Description: Payme Checkout Plugin for WooCommerce
Version: 1.4.8
Author: richman@mail.ru, support@paycom.uz
Text Domain: payme
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', 'woocommerce_payme', 0);

if (!function_exists('getallheaders')) 
{ 
    function getallheaders() 
    { 
           $headers = []; 
       foreach ($_SERVER as $name => $value) 
       { 
           if (substr($name, 0, 5) == 'HTTP_') 
           { 
               $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value; 
           } 
       } 
       return $headers; 
    } 
} 

function woocommerce_payme()
{
    load_plugin_textdomain('payme', false, dirname(plugin_basename(__FILE__)) . '/lang/');

    // Do nothing, if WooCommerce is not available
    if (!class_exists('WC_Payment_Gateway'))
        return;

    // Do not re-declare class
    if (class_exists('WC_PAYME'))
        return;

    class WC_PAYME extends WC_Payment_Gateway
    {
        protected $merchant_id;
        protected $merchant_key;
        protected $checkout_url;
	protected $return_url;
	protected $complete_order;

        public function __construct()
        {
            $plugin_dir = plugin_dir_url(__FILE__);
            $this->id = 'payme';
            $this->title = 'Payme';
            $this->description = __('Payment system Payme', 'payme');
            $this->icon = apply_filters('woocommerce_payme_icon', '' . $plugin_dir . 'payme.png');
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            // Populate options from the saved settings
            $this->merchant_id = $this->get_option('merchant_id');
            $this->merchant_key = $this->get_option('merchant_key');
            $this->checkout_url = $this->get_option('checkout_url');
	    $this->return_url   = $this->get_option('return_url');
	    $this->complete_order   = $this->get_option('complete_order');

            add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_api_wc_' . $this->id, [$this, 'callback']);
        }
		
		function showMessage($content)
        {
            return '
        <h1>' . $this->msg['title'] . '</h1>
        <div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>
        ';
        }

        function showTitle($title)
        {
            return false;
        }

        public function admin_options()
        {
            ?>
            <h3><?php _e('Payme', 'payme'); ?></h3>

            <p><?php _e('Configure checkout settings', 'payme'); ?></p>

            <p>
                <strong><?php _e('Your Web Cash Endpoint URL to handle requests is:', 'payme'); ?></strong>
                <em><?= site_url('/?wc-api=wc_payme'); ?></em>
            </p>

            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
            <?php
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title' => __('Enable/Disable', 'payme'),
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'payme'),
                    'default' => 'yes'
                ],
		'complete_order' => [
                    'title' => __('Order auto complete', 'payme'),
                    'type' => 'checkbox',
                    'label' => __('If disabled, you have to manually change order status to COMPLETE after success payment', 'payme'),
                    'default' => 'yes'
                ],
                'merchant_id' => [
                    'title' => __('Merchant ID', 'payme'),
                    'type' => 'text',
                    'description' => __('Obtain and set Merchant ID from the Paycom Merchant Cabinet', 'payme'),
                    'default' => ''
                ],
                'merchant_key' => [
                    'title' => __('KEY', 'payme'),
                    'type' => 'text',
                    'description' => __('Obtain and set KEY from the Paycom Merchant Cabinet', 'payme'),
                    'default' => ''
                ],
                'checkout_url' => [
                    'title' => __('Checkout URL', 'payme'),
                    'type' => 'text',
                    'description' => __('Set Paycom Checkout URL to submit a payment', 'payme'),
                    'default' => 'https://checkout.paycom.uz'
                ],
				'return_url' => [
                    'title' => __('Return URL', 'payme'),
                    'type' => 'text',
                    'description' => __('Set Paycom return URL', 'payme'),
                    'default' => site_url('/cart/?payme_success=1')
                ]
            ];
        }

        public function generate_form($order_id)
        {
            // get order by id
            $order = new WC_Order($order_id);

            // convert an amount to the coins (Payme accepts only coins)
            $sum = $order->get_total() * 100;

            // format the amount
            $sum = number_format($sum, 0, '.', '');

            $description = sprintf(__('Payment for Order #%1$s', 'payme'), $order_id);

            $lang_codes = ['ru_RU' => 'ru', 'en_US' => 'en', 'uz_UZ' => 'uz'];
            $lang = isset($lang_codes[get_locale()]) ? $lang_codes[get_locale()] : 'en';

            $label_pay = __('Pay', 'payme');
            $label_cancel = __('Cancel payment and return back', 'payme');
	    $callbackUrl=$this->return_url.'/'.$order_id.'/?key='.$order->get_order_key();	

            $form = <<<FORM
<form action="{$this->checkout_url}" method="POST" id="payme_form">
<input type="hidden" name="account[order_id]" value="$order_id">
<input type="hidden" name="amount" value="$sum">
<input type="hidden" name="merchant" value="{$this->merchant_id}">
<input type="hidden" name="callback" value="{$callbackUrl}">
<input type="hidden" name="lang" value="$lang">
<input type="hidden" name="description" value="$description">
<input type="submit" class="button alt" id="submit_payme_form" value="$label_pay">
<a class="button cancel" href="{$order->get_cancel_order_url()}">$label_cancel</a>
</form>
FORM;

            return $form;
        }

        public function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return [
                'result' => 'success',
                'redirect' => add_query_arg(
                    'order_pay',
                    $order->get_id(),
                    add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true))
                )
            ];

        }

        public function receipt_page($order_id)
        {
            echo '<p>' . __('Thank you for your order, press "Pay" button to continue.', 'payme') . '</p>';
            echo $this->generate_form($order_id);
        }

        /**
         * Endpoint method. This method handles requests from Paycom.
         */
        public function callback()
        {
            // Parse payload
            $payload = json_decode(file_get_contents('php://input'), true);

            if (json_last_error() !== JSON_ERROR_NONE) { // handle Parse error
                $this->respond($this->error_invalid_json());
            }

            // Authorize client
            $headers = getallheaders();
			
			$v=html_entity_decode($this->merchant_key);
			$encoded_credentials = base64_encode("Paycom:".$v);
            //$encoded_credentials = base64_encode("Paycom:{$this->merchant_key}");
            if (!$headers || // there is no headers
                !isset($headers['Authorization']) || // there is no Authorization
                !preg_match('/^\s*Basic\s+(\S+)\s*$/i', $headers['Authorization'], $matches) || // invalid Authorization value
                $matches[1] != $encoded_credentials // invalid credentials
            ) {
                $this->respond($this->error_authorization($payload));
            }

            // Execute appropriate method
            $response = method_exists($this, $payload['method'])
                ? $this->{$payload['method']}($payload)
                : $this->error_unknown_method($payload);

            // Respond with result
            $this->respond($response);
        }

        /**
         * Responds and terminates request processing.
         * @param array $response specified response
         */
        private function respond($response)
        {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }

            echo json_encode($response);
            die();
        }

        /**
         * Gets order instance by id.
         * @param array $payload request payload
         * @return WC_Order found order by id
         */
        private function get_order(array $payload)
        {
            try {
                return new WC_Order($payload['params']['account']['order_id']);
            } catch (Exception $ex) {
                $this->respond($this->error_order_id($payload));
            }
        }

        /**
         * Gets order instance by transaction id.
         * @param array $payload request payload
         * @return WC_Order found order by id
         */
        private function get_order_by_transaction($payload)
        {
            global $wpdb;

            try {
                $prepared_sql = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_value = '%s' AND meta_key = '_payme_transaction_id'", $payload['params']['id']);
                $order_id = $wpdb->get_var($prepared_sql);
                return new WC_Order($order_id);
            } catch (Exception $ex) {
                $this->respond($this->error_transaction($payload));
            }
        }

        /**
         * Converts amount to coins.
         * @param float $amount amount value.
         * @return int Amount representation in coins.
         */
        private function amount_to_coin($amount)
        {
            return 100 * number_format($amount, 2, '.', '');
        }

        /**
         * Gets current timestamp in milliseconds.
         * @return float current timestamp in ms.
         */
        private function current_timestamp()
        {
            return round(microtime(true) * 1000);
        }

        /**
         * Get order's create time.
         * @param WC_Order $order order
         * @return float create time as timestamp
         */
        private function get_create_time(WC_Order $order)
        {
            return (double)get_post_meta($order->get_id(), '_payme_create_time', true);
        }

        /**
         * Get order's perform time.
         * @param WC_Order $order order
         * @return float perform time as timestamp
         */
        private function get_perform_time(WC_Order $order)
        {
            return (double)get_post_meta($order->get_id(), '_payme_perform_time', true);
        }

        /**
         * Get order's cancel time.
         * @param WC_Order $order order
         * @return float cancel time as timestamp
         */
        private function get_cancel_time(WC_Order $order)
        {
            return (double)get_post_meta($order->get_id(), '_payme_cancel_time', true);
        }

        /**
         * Get order's transaction id
         * @param WC_Order $order order
         * @return string saved transaction id
         */
        private function get_transaction_id(WC_Order $order)
        {
            return (string)get_post_meta($order->get_id(), '_payme_transaction_id', true);
        }
		
		private function get_cencel_reason(WC_Order $order)
        {
           $b_v=(int)get_post_meta($order->get_id(), '_cancel_reason', true);
		   
		   if ($b_v)  return $b_v;
		   else return null;
        }

        private function CheckPerformTransaction($payload)
        {
            $order = $this->get_order($payload);
            $amount = $this->amount_to_coin($order->get_total());

            if ($amount != $payload['params']['amount']) {
                $response = $this->error_amount($payload);
            } else {
                $response = [
                    'id' => $payload['id'],
                    'result' => [
                        'allow' => true
                    ],
                    'error' => null
                ];
            }

            return $response;
        }

        private function CreateTransaction($payload)
        {
            $order = $this->get_order($payload);
            $amount = $this->amount_to_coin($order->get_total());

            if ($amount != $payload['params']['amount']) {
                $response = $this->error_amount($payload);
            } else {
                $create_time = $this->current_timestamp();
                $transaction_id = $payload['params']['id'];
                $saved_transaction_id = $this->get_transaction_id($order);

                if ($order->get_status() == "pending") { // handle new transaction
                    // Save time and transaction id
                    add_post_meta($order->get_id(), '_payme_create_time',    $create_time,    true);
                    add_post_meta($order->get_id(), '_payme_transaction_id', $transaction_id, true);

                    // Change order's status to Processing
                    $order->update_status('processing');

                    $response = [
                        "id" => $payload['id'],
                        "result" => [
                            "create_time" => /*$create_time*/ $this->get_create_time($order),
                            "transaction" => "000" . $order->get_id(),
                            "state" => 1
                        ]
                    ];
                } elseif ($order->get_status() == "processing" && $transaction_id == $saved_transaction_id) { // handle existing transaction
                    $response = [
                        "id" => $payload['id'],
                        "result" => [
                            "create_time" => /*$create_time*/ $this->get_create_time($order),
                            "transaction" => "000" . $order->get_id(),
                            "state" => 1
                        ]
                    ];
                } elseif ($order->get_status() == "processing" && $transaction_id !== $saved_transaction_id) { // handle new transaction with the same order
                    $response = $this->error_has_another_transaction($payload);
                } else {
                    $response = $this->error_unknown($payload);
                }
            }

            return $response;
        }

        private function PerformTransaction($payload)
        {
            $perform_time = $this->current_timestamp();
            $order = $this->get_order_by_transaction($payload);

            if ($order->get_status() == "processing") { // handle new Perform request
                // Save perform time
                add_post_meta($order->get_id(), '_payme_perform_time', $perform_time, true);

                $response = [
                    "id" => $payload['id'],
                    "result" => [
                        "transaction" => "000" . $order->get_id(),
                        "perform_time" => $this->get_perform_time($order),
                        "state" => 2
                    ]
                ];
		if ($this->complete_order === 'yes'){
		 	// Mark order as completed
                	$order->update_status('completed');
		}
		
                $order->payment_complete($payload['params']['id']);
				
            } elseif ($order->get_status() == "completed") { // handle existing Perform request
                $response = [
                    "id" => $payload['id'],
                    "result" => [
                        "transaction" => "000" . $order->get_id(),
                        "perform_time" => $this->get_perform_time($order),
                        "state" => 2
                    ]
                ];
            } elseif ($order->get_status() == "cancelled" || $order->get_status() == "refunded") { // handle cancelled order
                $response = $this->error_cancelled_transaction($payload);
            } else {
                $response = $this->error_unknown($payload);
            }

            return $response;
        }

        private function CheckTransaction($payload)
        {
            $transaction_id = $payload['params']['id'];
            $order = $this->get_order_by_transaction($payload);

            // Get transaction id from the order
            $saved_transaction_id = $this->get_transaction_id($order);

            $response = [
                "id" => $payload['id'],
                "result" => [
                    "create_time"  => $this->get_create_time($order),
                    "perform_time" => (is_null($this->get_perform_time($order)) ? 0: $this->get_perform_time($order) ) ,
                    "cancel_time"  => (is_null($this->get_cancel_time ($order)) ? 0: $this->get_cancel_time($order) ) ,
                    "transaction"  => "000" . $order->get_id(),
                    "state"        => null,
                    "reason"       => (is_null($this->get_cencel_reason($order)) ? null: $this->get_cencel_reason($order) )
                ],
                "error" => null
            ];

            if ($transaction_id == $saved_transaction_id) {
				
                switch ($order->get_status()) {
					 
					case 'processing': $response['result']['state'] = 1;  break;
                    case 'completed':  $response['result']['state'] = 2;  break;
                    case 'cancelled':  $response['result']['state'] = -1; break;
                    case 'refunded':   $response['result']['state'] = -2; break;
					
                    default: $response = $this->error_transaction($payload); break;
                }
            } else {
                $response = $this->error_transaction($payload);
            }

            return $response;
        }

        private function CancelTransaction($payload)
        {
            $order = $this->get_order_by_transaction($payload);

            $transaction_id = $payload['params']['id'];
            $saved_transaction_id = $this->get_transaction_id($order);

            if ($transaction_id == $saved_transaction_id) {

                $cancel_time = $this->current_timestamp();

                $response = [
                    "id" => $payload['id'],
                    "result" => [
                        "transaction" => "000" . $order->get_id(),
                        "cancel_time" => $cancel_time,
                        "state" => null
                    ]
                ];

                switch ($order->get_status()) {
                    case 'pending':
                        add_post_meta($order->get_id(), '_payme_cancel_time', $cancel_time, true); // Save cancel time
                        $order->update_status('cancelled'); // Change status to Cancelled
                        $response['result']['state'] = -1;
						
						if (update_post_meta($order->get_id(), '_cancel_reason', $payload['params']['reason'])) {
							add_post_meta   ($order->get_id(), '_cancel_reason', $payload['params']['reason'], true);
						}
                        break;
					case 'processing':
                        add_post_meta($order->get_id(), '_payme_cancel_time', $cancel_time, true); // Save cancel time
                        $order->update_status('cancelled'); // Change status to Cancelled
                        $response['result']['state'] = -1;
						if (update_post_meta($order->get_id(), '_cancel_reason', $payload['params']['reason'])) {
							add_post_meta   ($order->get_id(), '_cancel_reason', $payload['params']['reason'], true);
						}
                        break;	

                    case 'completed':
                        add_post_meta($order->get_id(), '_payme_cancel_time', $cancel_time, true); // Save cancel time
                        $order->update_status('refunded'); // Change status to Refunded
                        $response['result']['state'] = -2;
						if (update_post_meta($order->get_id(), '_cancel_reason', $payload['params']['reason'])) {
							add_post_meta   ($order->get_id(), '_cancel_reason', $payload['params']['reason'], true);
						}
                        break;

                    case 'cancelled':
                        $response['result']['cancel_time'] = $this->get_cancel_time($order);
                        $response['result']['state'] = -1;
                        break;

                    case 'refunded':
                        $response['result']['cancel_time'] = $this->get_cancel_time($order);
                        $response['result']['state'] = -2;
                        break;

                    default:
                        $response = $this->error_cancel($payload);
                        break;
                }
            } else {
                $response = $this->error_transaction($payload);
            }

            return $response;
        }

        private function ChangePassword($payload)
        {
            if ($payload['params']['password'] != $this->merchant_key) {
                $woo_options = get_option('woocommerce_payme_settings');

                if (!$woo_options) { // No options found
                    return $this->error_password($payload);
                }

                // Save new password
                $woo_options['merchant_key'] = $payload['params']['password'];
                $is_success = update_option('woocommerce_payme_settings', $woo_options);

                if (!$is_success) { // Couldn't save new password
                    return $this->error_password($payload);
                }

                return [
                    "id" => $payload['id'],
                    "result" => ["success" => true],
                    "error" => null
                ];
            }

            // Same password or something wrong
            return $this->error_password($payload);
        }

        private function error_password($payload)
        {
            $response = [
                "error" => [
                    "code" => -32400,
                    "message" => [
                        "ru" => __('Cannot change the password', 'payme'),
                        "uz" => __('Cannot change the password', 'payme'),
                        "en" => __('Cannot change the password', 'payme')
                    ],
                    "data" => "password"
                ],
                "result" => null,
                "id" => $payload['id']
            ];

            return $response;
        }

        private function error_invalid_json()
        {
            $response = [
                "error" => [
                    "code" => -32700,
                    "message" => [
                        "ru" => __('Could not parse JSON', 'payme'),
                        "uz" => __('Could not parse JSON', 'payme'),
                        "en" => __('Could not parse JSON', 'payme')
                    ],
                    "data" => null
                ],
                "result" => null,
                "id" => 0
            ];

            return $response;
        }

        private function error_order_id($payload)
        {
            $response = [
                "error" => [
                    "code" => -31099,
                    "message" => [
                        "ru" => __('Order number cannot be found', 'payme'),
                        "uz" => __('Order number cannot be found', 'payme'),
                        "en" => __('Order number cannot be found', 'payme')
                    ],
                    "data" => "order"
                ],
                "result" => null,
                "id" => $payload['id']
            ];

            return $response;
        }

        private function error_has_another_transaction($payload)
        {
            $response = [
                "error" => [
                    "code" => -31099,
                    "message" => [
                        "ru" => __('Other transaction for this order is in progress', 'payme'),
                        "uz" => __('Other transaction for this order is in progress', 'payme'),
                        "en" => __('Other transaction for this order is in progress', 'payme')
                    ],
                    "data" => "order"
                ],
                "result" => null,
                "id" => $payload['id']
            ];

            return $response;
        }

        private function error_amount($payload)
        {
            $response = [
                "error" => [
                    "code" => -31001,
                    "message" => [
                        "ru" => __('Order amount is incorrect', 'payme'),
                        "uz" => __('Order amount is incorrect', 'payme'),
                        "en" => __('Order amount is incorrect', 'payme')
                    ],
                    "data" => "amount"
                ],
                "result" => null,
                "id" => $payload['id']
            ];

            return $response;
        }

        private function error_unknown($payload)
        {
            $response = [
                "error" => [
                    "code" => -31008,
                    "message" => [
                        "ru" => __('Unknown error', 'payme'),
                        "uz" => __('Unknown error', 'payme'),
                        "en" => __('Unknown error', 'payme')
                    ],
                    "data" => null
                ],
                "result" => null,
                "id" => $payload['id']
            ];

            return $response;
        }

        private function error_unknown_method($payload)
        {
            $response = [
                "error" => [
                    "code" => -32601,
                    "message" => [
                        "ru" => __('Unknown method', 'payme'),
                        "uz" => __('Unknown method', 'payme'),
                        "en" => __('Unknown method', 'payme')
                    ],
                    "data" => $payload['method']
                ],
                "result" => null,
                "id" => $payload['id']
            ];

            return $response;
        }

        private function error_transaction($payload)
        {
            $response = [
                "error" => [
                    "code" => -31003,
                    "message" => [
                        "ru" => __('Transaction number is wrong', 'payme'),
                        "uz" => __('Transaction number is wrong', 'payme'),
                        "en" => __('Transaction number is wrong', 'payme')
                    ],
                    "data" => "id"
                ],
                "result" => null,
                "id" => $payload['id']
            ];

            return $response;
        }

        private function error_cancelled_transaction($payload)
        {
            $response = [
                "error" => [
                    "code" => -31008,
                    "message" => [
                        "ru" => __('Transaction was cancelled or refunded', 'payme'),
                        "uz" => __('Transaction was cancelled or refunded', 'payme'),
                        "en" => __('Transaction was cancelled or refunded', 'payme')
                    ],
                    "data" => "order"
                ],
                "result" => null,
                "id" => $payload['id']
            ];

            return $response;
        }

        private function error_cancel($payload)
        {
            $response = [
                "error" => [
                    "code" => -31007,
                    "message" => [
                        "ru" => __('It is impossible to cancel. The order is completed', 'payme'),
                        "uz" => __('It is impossible to cancel. The order is completed', 'payme'),
                        "en" => __('It is impossible to cancel. The order is completed', 'payme')
                    ],
                    "data" => "order"
                ],
                "result" => null,
                "id" => $payload['id']
            ];

            return $response;
        }

        private function error_authorization($payload)
        {
            $response = [
                "error" =>
                    [
                        "code" => -32504,
                        "message" => [
                            "ru" => __('Error during authorization', 'payme'),
                            "uz" => __('Error during authorization', 'payme'),
                            "en" => __('Error during authorization', 'payme')
                        ],
                        "data" => null
                    ],
                "result" => null,
                "id" => $payload['id']
            ];

            return $response;
        }
    }

    // Register new Gateway

    function add_payme_gateway($methods)
    {
        $methods[] = 'WC_PAYME';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_payme_gateway');
}

/////////////// success page

add_filter('query_vars', 'payme_success_query_vars');
function payme_success_query_vars($query_vars)
{	
	$query_vars[] = 'payme_success';
	$query_vars[] = 'order_id';
    return $query_vars;
}


add_action('parse_request', 'payme_success_parse_request');
function payme_success_parse_request(&$wp)
{
	if (array_key_exists('payme_success', $wp->query_vars)) {

         $order = new WC_Order($wp->query_vars['order_id']);

		$a = new WC_PAYME();
        add_action('the_title',   array($a, 'showTitle'));
        add_action('the_content', array($a, 'showMessage'));

        if ($wp->query_vars['payme_success'] == 1) {

			if ($order->get_status() == "pending") {
			/*
			$a->msg['title']   =  __('Payment not paid', 'payme');
            $a->msg['message'] =  __('An error occurred during payment. Try again or contact your administrator.', 'payme');
            $a->msg['class']   = 'woocommerce_message woocommerce_message_info';
			*/
			wp_redirect($order->get_cancel_order_url());
			} else {

            $a->msg['title']   =  __('Payment successfully paid', 'payme');
            $a->msg['message'] =  __('Thank you for your purchase!', 'payme');
            $a->msg['class']   = 'woocommerce_message woocommerce_message_info';
            WC()->cart->empty_cart();
			}
           
        } else {

            $a->msg['title']   =  __('Payment not paid', 'payme');
            $a->msg['message'] =  __('An error occurred during payment. Try again or contact your administrator.', 'payme');
            $a->msg['class']   = 'woocommerce_message woocommerce_message_info';
        }
    }
    return;
}

/////////////// success page end

?>
