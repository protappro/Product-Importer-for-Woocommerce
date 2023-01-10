<?php 

/**
 * Plugin Name: Product Importer for Woocommerce
 * Plugin URI: https://karkraftmkiv.com/
 * Description: Import bulk product data from CSV and also upload product images based on the product SKU.
 * Author: Rileymarkiv
 * Author URI: https://karkraftmkiv.com/
 * Version: 1.0.1
 * Tested up to: 5.8
 * License: GNU General Public License v3.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	return;
}
add_action('plugins_loaded', 'wc_product_importer_setting_init', 11);
function wc_product_importer_setting_init() {
   require_once(plugin_basename('classes/wc_product_importer_setting.php'));
}