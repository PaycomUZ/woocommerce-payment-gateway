<?php
/*
  Plugin Name: Payme
  Plugin URI:  http://maorif.com
  Description: Payme Plugin for WooCommerce
  Version: 1.0.1
  Author: richman@mail.ru
 */
if ( ! defined( 'ABSPATH' ) ) exit;
add_action('plugins_loaded', 'woocommerce_payme', 0);
function woocommerce_payme(){
    load_plugin_textdomain( 'payme', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
    if (!class_exists('WC_Payment_Gateway'))
        return;
    if(class_exists('WC_PAYME'))
        return;
    class WC_PAYME extends WC_Payment_Gateway{
        public function __construct()
		{
            $plugin_dir = plugin_dir_url(__FILE__);
            global $woocommerce;
            $this->id = 'payme';
            $this->icon = apply_filters('woocommerce_payme_icon', ''.$plugin_dir.'payme.png');
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();
            $this->public_key = $this->get_option('public_key');
            $this->secret_key = $this->get_option('secret_key');
            $this->title = 'Payme';
            $this->description = __('Payment system Payme', 'payme');
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'callback'));
        }
        public function admin_options()
		{
            ?>
            <h3><?php _e('PAYME', 'payme'); ?></h3>
            <p><?php _e('Setup payments parameters.', 'payme'); ?></p>
            <table class="form-table">
                <?php
                $this->generate_settings_html();
                ?>
            </table>
            <?php
        }
        function init_form_fields()
		{
            $this->form_fields = array('enabled' => array('title' => __('Enable/Disable', 'payme'),'type' => 'checkbox','label' => __('Enabled', 'payme'),'default' => 'yes'),
                'public_key' => array('title' => __('MERCHANT ID', 'payme'),'type' => 'text','description' => __('Copy Merchant ID from your account page in Payme system', 'payme'),'default' => ''),'secret_key' => array('title' => __('KEY', 'woocommerce'),'type' => 'text','description' => __('Copy KEY from your account page in Payme system', 'payme'),'default' => ''));
        }
        public function generate_form($order_id)
		{
            $order = new WC_Order( $order_id );
            $sum = number_format($order->order_total, 0, '.', '');
            $sum=$sum*100;
            $desc = __('Payment for Order №', 'payme') . $order_id;
            $locale = $cur_locale == 'ru_RU'?'ru':'en';
            return
                '<form action="https://checkout.paycom.uz" method="POST" id="payme_form">'.
                '<input type="hidden" name="account[order_id]" value="' .  $order_id . '" />'.
                '<input type="hidden" name="amount" value="' .  $sum . '" />'.
                '<input type="hidden" name="merchant" value="' . $this->public_key . '" />'.
                '<input type="hidden" name="lang" value="' . "ru" . '" />'.
                '<input type="submit" class="button alt" id="submit_payme_form" value="'.__('Pay', 'payme').'" />
				<a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel payment and return back to card', 'payme').'</a>'."\n".
                '</form>';
        }
        function process_payment($order_id)
		{
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay')))));
        }
        function receipt_page($order)
		{
            echo '<p>'.__('Thank you for your order, press button to pay.', 'payme').'</p>';
            echo $this->generate_form($order);
        }
        function callback()
		{
			$responsedata = (array)json_decode(file_get_contents('php://input'), true);
          	$headers = getallheaders();
			$code=base64_encode("Paycom:".$this->secret_key);
			if (!$headers || !isset($headers['Authorization']) ||  !preg_match('/^\s*Basic\s+(\S+)\s*$/i', $headers['Authorization'], $matches) || $matches[1] != $code ) {
               $result = $this->authorization( $responsedata );
               echo json_encode($result);
			   die();
            }else{
				$order = new WC_Order( $order );
				$method = '';
				$params = array();
				$method = $responsedata['method'];
					switch ($method) {
						case 'CheckPerformTransaction':
							$result = $this->CheckPerformTransaction( $responsedata );
							break;
						case 'CreateTransaction':
							$result = $this->CreateTransaction( $responsedata );
							break;
						case 'PerformTransaction':
							$result = $this->PerformTransaction( $responsedata );
							break;
						case 'CancelTransaction':
							$result = $this->CancelTransaction( $responsedata );
							break;
						case 'CheckTransaction':
							$result = $this->CheckTransaction( $responsedata );
							break;
						case 'ChangePassword':
							$result = $this->ChangePassword( $responsedata );
							break;
						default:
							$result = $this->error_unknown( $responsedata );
							break;
					}
				echo json_encode($result);
				die();
            }
        }
        function CheckPerformTransaction( $responsedata )
        {
			try{
				$order = new WC_Order($responsedata['params']['account']['order_id'] );
			}
			catch (Exception $ex) {
				$result = $this->error_order_id( $responsedata );
			}
			$sum = number_format($order->order_total, 0, '.','');
			$sum=$sum*100;
			if (!$order->id){
				$result = $this->error_order_id( $responsedata );
			}elseif ($sum != $responsedata['params']['amount']) {
				$result = $this->error_amount( $responsedata );
			}else{
				$result = array('result' =>array('allow' => true));
			}
			return $result;
        }
 		function CreateTransaction( $responsedata )
        {
			$create_time=round(microtime(true) * 1000);
			$transaction_id=$responsedata['params']['id'];
			try {
				$order = new WC_Order($responsedata['params']['account']['order_id'] );
          	}
          	catch (Exception $ex) {
          		$result = $this->error_order_id( $responsedata );
          	}
		  	$sum = number_format($order->order_total, 0, '.','');
            $sum=$sum*100;
          	if (!$order->id){
          		$result = $this->error_order_id( $responsedata );
          	}elseif ($sum != $responsedata['params']['amount']) {
          		$result = $this->error_amount( $responsedata );
            }else{
				if ($order->status=="pending"){
					add_post_meta($order->id, '_payme_create_time', $create_time, true);
                	add_post_meta($order->id, '_payme_transaction_id', $transaction_id, true);
                	$order->update_status( 'processing' );
                	$result = array("id" => $responsedata['id'],"result" => array("create_time" =>$create_time,	"transaction"=>"000".$order->id, "state"=>1));
				}elseif ($order->status=="processing" && $transaction_id==get_post_meta($order->id, '_payme_transaction_id', true)){
					$result = array("id" => $responsedata['id'],"result" => array("create_time" =>$create_time, "transaction"=>"000".$order->id, "state"=>1));
				}elseif ($order->status=="processing" && $transaction_id!==get_post_meta($order->id, '_payme_transaction_id', true)){
					$result= array("error" => array("code"=>-31099,"message"=>array("ru"=>__('Error during CreateTransaction_ru', 'payme'),"uz"=>__('Error during CreateTransaction_uz', 'payme'), "en"=>__('Error during CreateTransaction_en', 'payme')),"data"=>"order"),"result"=>null,"id" => $responsedata['id']);
				}else{
					$result = $this->error_unknown( $responsedata );
				}
            }
            return $result;
        }
		function PerformTransaction( $responsedata )
        {
			$perform_time=round(microtime(true) * 1000);
			$id=$responsedata['params']['id'];
			global $wpdb;
			$order_id_by_trans = $wpdb->get_var( $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_value = '$id' AND meta_key = '_payme_transaction_id'","") );
			$order = new WC_Order($order_id_by_trans );
			if ($order->status=="processing"){
				add_post_meta($order->id, '_payme_perform_time', $perform_time, true);
				$result = array("id" => $responsedata['id'],"result" => array("transaction"=>"000".$order->id,"perform_time"=>(int)get_post_meta($order->id, '_payme_perform_time', true),"state"=>2));
            	$order->update_status( 'completed' );
           		$order->payment_complete( $responsedata['tran'] );
           		$order->reduce_order_stock();
			}elseif ($order->status=="completed"){
				$result = array("id" => $responsedata['id'],"result" => array("transaction"=>"000".$order->id,"perform_time"=>(int)get_post_meta($order->id, '_payme_perform_time', true),"state"=>2));
			}elseif ($order_id_by_trans==null){
				$result = $this->error_transaction( $responsedata );
			}elseif ($order->status!="cancelled" or $order->status!="refunded"){
				$result = array("error" => array("code"=>-31008,"message"=>array("ru"=>__('Transaction was cancelled or refunded_ru', 'payme'),"uz"=>__('Transaction was cancelled or refunded_uz', 'payme'),"en"=>__('Transaction was cancelled or refunded_en', 'payme')),"data"=>"order"),"result"=>null,"id" => $responsedata['id']);
			}else{
				$result = $this->error_unknown( $responsedata );
			}
			return $result;
        }
		function CheckTransaction( $responsedata )
        {
        $id=$responsedata['params']['id'];
        global $wpdb;
    	$order_id_by_trans = $wpdb->get_var( $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_value = '$id' AND meta_key = '_payme_transaction_id'",""));
        $order = new WC_Order($order_id_by_trans);
			if ($order->status=="processing" && $id==get_post_meta($order->id, '_payme_transaction_id', true)){
				$result = array("id" => $responsedata['id'],"result" => array("create_time"=>(int)get_post_meta($order->id, '_payme_create_time', true),"perform_time"=>0,"cancel_time"=>0,  "transaction"=>"000".$order->id,"state"=>1,"reason"=>null));
			}elseif ($order->status=="completed" && $id==get_post_meta($order->id, '_payme_transaction_id', true)){
				$result = array("id" => $responsedata['id'],"result" => array("create_time"=>(int)get_post_meta($order->id, '_payme_create_time', true),"perform_time"=>(int)get_post_meta($order->id, '_payme_perform_time', true),"cancel_time"=>0,"transaction"=>"000".$order->id,"state"=>2,"reason"=>null));
			}elseif ($order->status=="cancelled" && $id==get_post_meta($order->id, '_payme_transaction_id', true)){
				$result = array("id" => $responsedata['id'],"result" => array("create_time"=>(int)get_post_meta($order->id, '_payme_create_time', true),"perform_time"=>0,
				"cancel_time"=>(int)get_post_meta($order->id, '_payme_cancel_time', true),"transaction"=>"000".$order->id,"state"=>-1,"reason"=>2));
			}elseif ($order->status=="refunded" && $id==get_post_meta($order->id, '_payme_transaction_id', true)){
				$result = array("id" => $responsedata['id'],"result" => array("create_time"=>(int)get_post_meta($order->id, '_payme_create_time', true),"perform_time"=>(int)get_post_meta($order->id, '_payme_perform_time', true),"cancel_time"=>(int)get_post_meta($order->id, '_payme_cancel_time', true),"transaction"=>"000".$order->id,    "state"=>-2,"reason"=>5));
			}else{
				$result = $this->error_transaction( $responsedata );
			}
			return $result;
        }
        function CancelTransaction( $responsedata )
        {
			$cancel_time=round(microtime(true) * 1000);
			$id=$responsedata['params']['id'];
			global $wpdb;
			$order_id_by_trans = $wpdb->get_var( $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_value = '$id' AND meta_key = '_payme_transaction_id'",""));
			$order = new WC_Order($order_id_by_trans);
			if ($order->status=="pending" && $id==get_post_meta($order->id, '_payme_transaction_id', true)){
				add_post_meta($order->id, '_payme_cancel_time', $cancel_time, true);
				$order->update_status( 'cancelled' );
				$result = array("id" => $responsedata['id'],"result" => array("transaction"=>"000".$order->id,"cancel_time"=>(int)get_post_meta($order->id, '_payme_cancel_time', true),"state"=>-1));
			}elseif ($order->status=="completed" && $id==get_post_meta($order->id, '_payme_transaction_id', true)){
				add_post_meta($order->id, '_payme_cancel_time', $cancel_time, true);
				$order->update_status( 'refunded' );
				$result = array("id" => $responsedata['id'],"result" => array("transaction"=>"000".$order->id,"cancel_time"=>(int)get_post_meta($order->id, '_payme_cancel_time', true),"state"=>-2));
			}elseif ($order->status=="cancelled"){
				$result = array("id" => $responsedata['id'],"result" => array("transaction"=>"000".$order->id,"cancel_time"=>(int)get_post_meta($order->id, '_payme_cancel_time', true),"state"=>-1));
			}elseif ($order->status=="refunded"){
				$result = array("id" => $responsedata['id'],"result" => array("transaction"=>"000".$order->id,"cancel_time"=>(int)get_post_meta($order->id, '_payme_cancel_time', true),"state"=>-2));
			}elseif ($id!=get_post_meta($order->id, '_payme_transaction_id', true)){
				$result = $this->error_transaction( $responsedata );
			}else{
				$result = $this->cancel_error( $responsedata );
			}
			return $result;
        }
		function ChangePassword( $responsedata )
		{
			if($responsedata['params']['password'] !=$this->secret_key){
				$woo_options=get_option( 'woocommerce_payme_settings', $default = false );
				$woo_options['secret_key']=$responsedata['params']['password'];
				update_option( 'woocommerce_payme_settings', $woo_options);
				$result = array("id" => $responsedata['id'],"result" => array("success" => true));
			}
			return $result;
		}
		function error_order_id($responsedata)
		{
			$result = array("error" => array("code"=>-31099,"message"=>array("ru"=> __('Order number cannot be not found_ru', 'payme'),"uz"=>__('Order number cannot be not found_uz', 'payme'),"en"=>__('Order number cannot be not found_en', 'payme')),"data"=>"order"),"result"=>null,"id" => $responsedata['id']);
			return $result;
        }
		function error_amount($responsedata){
			$result = array("error" => array("code"=>-31001,"message"=>array("ru"=>__('Order ammount is incorrect_ru', 'payme'),"uz"=>__('Order ammount is incorrect_uz', 'payme'),
			"en"=>__('Order ammount is incorrect_en', 'payme')),"data"=>"order"),"result"=>null,"id" => $responsedata['id']);
			return $result;
        }
		function error_unknown($responsedata){
			$result = array("error" => array("code"=>-31008,"message"=>array("ru"=>__('Unknown error_ru', 'payme'),"uz"=>__('Unknown error_uz', 'payme'),"en"=>__('Unknown error_en', 'payme')),"data"=>null),"result"=>null,"id" => $responsedata['id']);
			return $result;
        }
		function error_transaction($responsedata){
			$result = array("error" => array("code"=>-31003,"message"=>array("ru"=>__('Transaction number is wrong_ru', 'payme'),"uz"=>__('Transaction number is wrong_uz', 'payme'),
			"en"=>__('Transaction number is wrong_en', 'payme')),"data"=>"order"),"result"=>null,"id" => $responsedata['id']);
			return $result;
        }
		function cancel_error( $responsedata)
        {
			$result = array("error" => array("code"=>-31007,"message"=>array("ru"=>__('It is impossible to cancel. The order is complited_ru', 'payme'),"uz"=>__('It is impossible to cancel. The order is complited_uz', 'payme'),"en"=>__('It is impossible to cancel. The order is complited_en', 'payme')),"data"=>"order"),"result"=>null,"id" => $responsedata['id']);
            return $result;
        }
        function authorization( $responsedata )
        {
			$result = array("error" => array("code"=>-32504,"message"=>array("ru"=>__('Error during authorization_ru', 'payme'),"uz"=>__('Error during authorization_uz', 'payme'),
			"en"=>__('Error during authorization_en', 'payme')),"data"=>null),"result"=>null,"id" => $responsedata['id']);
            return $result;
        }
    }
    function add_payme_gateway($methods){
		$methods[] = 'WC_PAYME';
		return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'add_payme_gateway');
}
?>