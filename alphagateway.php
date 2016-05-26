<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*
Plugin Name: Alpha Bank gateway - WooCommerce Gateway
Plugin URI: http://www.gateway.com/
Description: Extends WooCommerce by Adding custom gateway
Version: 1.0
Author: Antreas gribas
Author URI: http://www.antreasgribas.gr/
*/

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'gateway_init', 0 );
function gateway_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	include_once( 'alphagateway_class.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'add_mygateway' );
	function add_mygateway( $methods ) {
		$methods[] = 'WC_Gateway_Alpha';
		return $methods;
	}
}

// Add custom action links
/*add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'spyr_authorizenet_aim_action_links' );
function spyr_authorizenet_aim_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'spyr-authorizenet-aim' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );	
}*/