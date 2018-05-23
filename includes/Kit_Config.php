<?php
/*
 Plugin Name: Payme
 Plugin URI:  http://paycom.uz
 Description: Payme Checkout Plugin for WooCommerce
 Author: Игамбердиев Бахтиёр Хабибулаевич admin@xxi.uz
 license: http://skill.uz/license-agreement.txt
 Text Domain: Kit_Config
 */

 class Kit_Config extends WC_Payment_Gateway
    {
        protected $merchant_id;
        protected $merchant_key;
        protected $merchant_key_test;
        protected $checkout_url;        
        protected $checkout_url_test;
		protected $StatusTest;
		protected $callback_timeout;
		protected $Redirect;
		protected $view_batten;
        public function __construct()
        {
            $plugin_dir = plugin_dir_url(__FILE__);
            $this->id = 'payme';
            $this->title = 'Payme';
            $this->description = __('Платежная система Payme', 'payme');
            $this->icon = apply_filters('woocommerce_payme_icon', '' . $plugin_dir . 'payme.png');
            $this->icon=str_replace('includes/','', $this->icon);
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            // Populate options from the saved settings
            $this->merchant_id = $this->get_option('merchant_id');
            $this->merchant_key = $this->get_option('merchant_key');
            $this->checkout_url = $this->get_option('checkout_url');
            $this->checkout_url_test = $this->get_option('checkout_url_test');
            $this->merchant_key_test = $this->get_option('merchant_key_test');
            $this->Enabled = $this->get_option('enabled');
            $this->StatusTest = $this->get_option('enabled_test');
            $this->callback_timeout = $this->get_option('callback_pay');
            $this->Redirect = $this->get_option('redirect');
            $this->view_batten = $this->get_option('view_batten');
            
            add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_api_wc_' . $this->id, [$this, 'callback']);
        }
		
        public function admin_options()
        {
			$this->DefConfig();
        	?>
            <h3><img style="height: 50px;" src="/wp-content/plugins/woocommerce-payment-Kit/payme.png" /></h3>

            <p><?php _e('Настройте параметры оплаты', 'payme'); ?></p>
            <p>Настройки для кассы можно взять из кабинета <a href="https://paycom.uz/">поставщика</a> Раздел Параметры для разработчика</p>

            <div>
                <div style="float: left;margin-top: 50px;color: #23282d; margin-right: 25px;"><strong><?=_e('Endpoint Url', 'payme'); ?></strong></div>
                <div style="float: left;
						    padding: 40px 40px 0px 40px;
						    font-size: 28px;
						    color: #fff;
						    background-color: #816ff1;">
						    <em><?= site_url('/?wc-api=Kit_Callback'); ?></em>
					<p class="description" style="margin-top: 40px; color: #fff;">
					Данные URL необходимо внести в настроеки кассы в кабинете поставщика
					</p>
				</div>
            </div>
			
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
            <?php
        }
		private function DefConfig(){
			
            $db_group = array(
            		'DB_HOST'=>DB_HOST,
            		'DB_PORT'=>'3306',
            		'DB_NAME'=>DB_NAME,
            		'DB_USER'=>DB_USER,
            		'DB_PASS'=>DB_PASSWORD,
            		'CHARSET'=>DB_CHARSET,
            		'CHARSETCOLAT'=>DB_CHARSET
            );
            
            if(!empty($_REQUEST['save']))
            {
            	$_REQUEST['groups']['payme']['fields']['merchant_id']['value'] 		= $_REQUEST['woocommerce_payme_merchant_id'];
            	$_REQUEST['groups']['payme']['fields']['merchant_key_test']['value']= $_REQUEST['woocommerce_payme_merchant_key_test'];
            	$_REQUEST['groups']['payme']['fields']['merchant_key']['value']		= $_REQUEST['woocommerce_payme_merchant_key'];
            	$_REQUEST['groups']['payme']['fields']['checkout_url']['value']		= $_REQUEST['woocommerce_payme_checkout_url'];
            	$_REQUEST['groups']['payme']['fields']['endpoint_url']['value']		= site_url('/?wc-api=Kit_Callback');
            	$_REQUEST['groups']['payme']['fields']['status_test']['value']		= $_REQUEST['woocommerce_payme_enabled_test']==1?'Y':'N';
            	$_REQUEST['groups']['payme']['fields']['status_tovar']['value']		= $_REQUEST['woocommerce_payme_status_tovar']==1?'Y':'N';
            	$_REQUEST['groups']['payme']['fields']['callback_pay']['value']		= $_REQUEST['woocommerce_payme_callback_pay'];
            	$_REQUEST['groups']['payme']['fields']['redirect']['value']			= $_REQUEST['woocommerce_payme_redirect'];
            	
            	include __DIR__.'/UniversalKernel/IndexConfigCreate.php';
            }
		}
        public function init_form_fields()
        {
			//exit(TABLE_PREFIX);
            $this->form_fields = [
            		'enabled' => [
            				'title' => 'Активировать',//__('Enable/Disable', 'payme'),
            				'type' => 'checkbox',
            				'label' => __('Enabled', 'payme'),
            				'default' => 'yes'
            		],            		
            		'enabled_test' => [
	                    'title' => 'Включить режим тестирования',//__('Enable/Disable', 'payme'),
	                    'type' => 'checkbox',
	                    'label' => __('Enabled', 'payme'),
	                    'default' => 'yes'
	                ],
            		'redirect' => [
            				'title' => 'Перенаправление URL',
            				'type' => 'hidden',
            				'description' => site_url('/?wc-api=OrderReturn'),
            				'default' => site_url('/?wc-api=OrderReturn')
            		],
            		'merchant_id' => [
            				'title' => __('Merchant ID', 'payme'),
            				'type' => 'text',
            				'description' => __('Obtain and set Merchant ID from the Paycom Merchant Cabinet', 'payme'),
            				'default' => ''
            		],
            		'merchant_key' => [
            				'title' => __('Ключ - пароль кассы', 'payme'),
            				'type' => 'text',
            				'description' => __('Obtain and set KEY from the Paycom Merchant Cabinet', 'payme'),
            				'default' => ''
            		],
            		'merchant_key_test' => [
            				'title' => 'Ключ - пароль для тестов',
            				'type' => 'text',
            				'description' => __('Obtain and set KEY from the Paycom Merchant Cabinet', 'payme'),
            				'default' => ''
            		],
            		'checkout_url' => [
            				'title' => 'Введите URL-адрес шлюза',//__('Checkout URL', 'payme'),
            				'type' => 'text',
            				'description' => __('Set Paycom Checkout URL to submit a payment', 'payme'),
            				'default' => 'https://checkout.paycom.uz'
            		],
            		'checkout_url_test' => [
	           				'title' => 'Введите URL-адрес шлюза для теста',
	           				'type' => 'text',
	           				'description' => '',
	           				'default' => 'https://test.paycom.uz'
	           		],
            		
            		'callback_checkout_url' => [
            				'title' => 'Url для Payme:',
            				'type' => 'text',
            				'label' => 'Данные URL необходим для размещения ссылки на Ваш магазин в прилжении Payme в разделе Оплаты',
            				'default' => site_url('/?payme=pay')
            		],
            		            		
	            	'callback_pay' => [
	            			'title' => 'Вернуться после оплаты через:',
	            			'type' => 'select',
	            			'options'=>array('0'=>'Моментально','15000'=>'15 секунд','30000'=>'30 секунд','60000'=>'60 секунд'),
	            			'label' => 'Вернуться после оплаты через:',
	            			'class' => 'email_type wc-enhanced-select',
	            			'default' => '0'
	            	],
	            		'status_tovar' => [
	            				'title' => 'Добавить в чек данные о товарах',
	            				'type' => 'checkbox',
	            				'label' => 'Добавить в чек данные о товарах',
	            				'default' => 'yes'
	            		],
	            	
	            	'view_batten' => [
	            			'title' => 'Текст на кнопке:',
	            			'type' => 'text',
	            			'label' => '',
	            			'default' => 'Отправить'
	            	]
            ];
        }

        public function generate_form($order_id)
        {
            // get order by id
            $order = new WC_Order($order_id);

            // convert an amount to the coins (Payme accepts only coins)
            if(isset($order->order_total))
            	$sum = $order->order_total * 100;
            else
            	$sum = $order->get_total() * 100;

            // format the amount
            $sum = number_format($sum, 0, '.', '');

            $description = sprintf(__('Payment for Order #%1$s', 'payme'), $order_id);

            $lang_codes = ['ru_RU' => 'ru', 'en_US' => 'en', 'uz_UZ' => 'uz'];
            $lang = isset($lang_codes[get_locale()]) ? $lang_codes[get_locale()] : 'en';
			
            if(empty($this->view_batten))
            	$label_pay = __('Pay', 'payme');
            else 
            	$label_pay = $this->view_batten;
            $label_cancel = __('Cancel payment and return back', 'payme');
            
            //$this->StatusTest = 'Y';//$this->getConfigData('status_test');
            if($this->StatusTest == 'yes' or $this->StatusTest == 1)
            {
            	$merchantUrl = $this->checkout_url_test;
            	$this->StatusTest = 'Y';
            }
            else
            {
            	$merchantUrl = $this->checkout_url;
            	$this->StatusTest = 'N';
            }
            
            $callback_timeout 	= $this->callback_timeout;//$this->getConfigData('callback_pay');
            $Get = array(
            		'Amount'=>$sum,
            		'OrderId'=>$order_id,
            		'CmsOrderId'=>$order_id,
            		'IsFlagTest'=>$this->StatusTest,
            		'Lang'=>$lang
            );
            
            $port = '3306';
            $db_group = array(
            		'DB_HOST'=>DB_HOST,
            		'DB_PORT'=>$port,
            		'DB_NAME'=>DB_NAME,
            		'DB_USER'=>DB_USER,
            		'DB_PASS'=>DB_PASSWORD,
            		'CHARSET'=>DB_CHARSET,
            		'CHARSETCOLAT'=>DB_CHARSET
            );
            
            $return = include __DIR__.'/UniversalKernel/IndexInsertOrder.php';
           
            //$Url = "{$merchantUrl}/".base64_encode("m={$this->merchant_id};ac.order_id={$order_id};a={$sum};l=ru;c={$this->Redirect}&order_id={$order_id};ct={$callback_timeout}");
            $Url = $merchantUrl;
            $fields = array(
            		'merchant'       	  	=> $this->merchant_id,  				// Идентификатор WEB Кассы
            		'amount'            	=> $sum,								// Сумма платежа в тиинах
            		'account[order_id]' 	=> $order_id,							// Поля Объекта Account
            		// НЕ ОБЯЗАТЕЛЬНЫЕ ПОЛЯ
            		'lang'					=> 'ru',								//Язык. Доступные значения: ru|uz|en Другие значения игнорируются Значение по умолчанию ru
            		'currency'				=> 860, 								// Валюта. Доступные значения: 643|840|860|978 Другие значения игнорируются Значение по умолчанию 860 Коды валют в ISO формате
            		'callback'				=> $this->Redirect, 					// URL возврата после оплаты или отмены платежа. :transaction - id транзакции или "null" если транзакцию не удалось создадь :account.{field} - поля объекта Account
            		'callback_timeout'		=> $callback_timeout 					// Таймаут после успешного платежа в милисекундах.
            );
            
            if($pmconfigs['payment_kit_payme_status_tovar'])
            	$fields['detail'] = base64_encode(json_encode($detail));	// Объект детализации платежа
            
            $form = '<form name="payme" id="paymentform" action="'.$host.'" method="POST">';
            foreach ($fields as $key=>$value){
            	$form .=  '<input type="hidden" name="'.$key.'" value="'.$value.'">';
            }
            
           
           // print_r($return); exit($Url);
            $form = 
<<<FORM
	<form action="{$Url}" method="POST" id="payme_form">
		{$form}
		<button class="button alt" id="submit_payme_form">$label_pay</button>
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
                    'order',
                    $order->get_id(),
                    add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay')))
                )
            ];
        }

        public function receipt_page($order_id)
        {
            echo '<p>' . __('Thank you for your order, press "Pay" button to continue.', 'payme') . '</p>';
            echo $this->generate_form($order_id);
        }

    }
?>