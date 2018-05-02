<?php
/*
Plugin Name: Payme
Plugin URI:  http://paycom.uz
Description: Payme Checkout Plugin for WooCommerce
Version: 2.0.0
Author: Игамбердиев Бахтиёр Хабибулаевич admin@xxi.uz
license: http://skill.uz/license-agreement.txt
Text Domain: Kit_Callback
 */

if (!defined('ABSPATH')) exit;

class Kit_Callback extends WC_Payment_Gateway
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
		$this->style = apply_filters('woocommerce_payme_icon', '' . $plugin_dir . 'UniversalKernel/Core/View/css/');
		// Populate options from the saved settings
		$this->merchant_id 			= $this->get_option('merchant_id');
		$this->merchant_key 		= $this->get_option('merchant_key');
		$this->checkout_url 		= $this->get_option('checkout_url');
		$this->checkout_url_test 	= $this->get_option('checkout_url_test');
		$this->merchant_key_test 	= $this->get_option('merchant_key_test');
		$this->StatusTest 			= $this->get_option('enabled');
		$this->callback_timeout 	= $this->get_option('callback_pay');
		$this->Redirect 			= $this->get_option('redirect');
		$this->view_batten 			= $this->get_option('view_batten');
		if($_GET['wc-api'] == 'OrderReturn')
	   		$this->OrderReturn();
		else 
			$this->Callback();
    }
    
    private function Callback(){
		$db_group = array(
			'DB_HOST'=>DB_HOST,
			'DB_PORT'=>'3306',
			'DB_NAME'=>DB_NAME,
			'DB_USER'=>DB_USER,
			'DB_PASS'=>DB_PASSWORD,
			'CHARSET'=>DB_CHARSET,
			'CHARSETCOLAT'=>DB_CHARSET
		);
    	include_once __DIR__.'/UniversalKernel/IndexCallback.php';
    	exit;
    }
    
    private function OrderReturn(){
    	if($this->StatusTest == 'Y')
    		$merchantUrl = $this->checkout_url_test;
    	else
    		$checkoutUrl = $this->checkout_url;
    	 
    	$merchantId 		= $this->merchant_id;
    	$merchantKey 		= $this->merchant_key;
    	
    	$callback_timeout 	= $this->callback_timeout;
    	$Redirect		  	= $this->Redirect;
    
    	$db_group = array(
			'DB_HOST'=>DB_HOST,
			'DB_PORT'=>'3306',
			'DB_NAME'=>DB_NAME,
			'DB_USER'=>DB_USER,
			'DB_PASS'=>DB_PASSWORD,
			'CHARSET'=>DB_CHARSET,
			'CHARSETCOLAT'=>DB_CHARSET
		);
    	$paymeform = include_once __DIR__.'/UniversalKernel/IndexOrderReturn.php';
    	$paymeform = str_replace('/pub/static/version1516790298/frontend/Magento/luma/en_US/KiT_Payme/css/', $this->style, $paymeform);
    	exit($paymeform);
    	return $paymeform;
    }
}
?>