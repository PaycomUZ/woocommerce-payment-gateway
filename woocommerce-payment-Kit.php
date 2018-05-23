<?php
/*
Plugin Name: KiT Payme
Plugin URI: http://paycom.uz
Description: <a href="https://business.payme.uz/">Payme</a> Checkout Plugin for WooCommerce. Услуги лицензированы. Лицензия на эксплуатацию и оказание услуг сетей передачи данных АА№0005816, выдана Министерством по развитию информационных технологий и коммуникации Республики Узбекистан 08 апреля 2016 года.
Author: Игамбердиев Бахтиёр Хабибулаевич admin@xxi.uz https://t.me/SkillUz
Version: 2.0.25-beta
Author URI: https://t.me/Skill_Uz
*/

// Prevent direct access
if (!defined('ABSPATH')) exit;


define('TABLE_PREFIX', $table_prefix);
define('WP_HTTP_BLOCK_EXTERNAL', false);

add_action('plugins_loaded', 'Kit_Woocommerce_Payme', 0);

//Fix support php-fpm
if (!function_exists('getallheaders'))
{
	function getallheaders()
	{
		$headers = '';
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


function Kit_Woocommerce_Payme()
{
    load_plugin_textdomain('payme', false, dirname(plugin_basename(__FILE__)) . '/lang/');
    // Do nothing, if WooCommerce is not available
    if (!class_exists('WC_Payment_Gateway'))
        return;

    // Do not re-declare class
    $wc_api = isset($_GET['wc-api'])?$_GET['wc-api']:null;
    if($wc_api == 'Kit_Callback')
    {
    	include_once __DIR__.'/includes/Kit_Callback.php';
    	if (!class_exists('Kit_Callback'))
    		return;
    	function add_Kit_Woocommerce_Payme_gateway($methods)
    	{
    		$methods[] = 'Kit_Callback';
    		return $methods;
    	}
    }
    else if($wc_api == 'OrderReturn'){
    	include_once __DIR__.'/includes/Kit_Callback.php';
    	if (!class_exists('Kit_Callback'))
    		return;
    	function add_Kit_Woocommerce_Payme_gateway($methods)
    	{
    		$methods[] = 'Kit_Callback';
    		return $methods;
    	}
    }
    else   
    {
    	include_once __DIR__.'/includes/Kit_Config.php';
    	if (!class_exists('Kit_Config'))
    		return;
    	function add_Kit_Woocommerce_Payme_gateway($methods)
    	{
    		$methods[] = 'Kit_Config';
    		return $methods;
    	}
    }
    // Register new Gateway
    add_filter('woocommerce_payment_gateways', 'add_Kit_Woocommerce_Payme_gateway');
}


?>