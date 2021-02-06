<?php
/**
 * Plugin Name:     Modify WooCommerce Order
 * Plugin URI:      www.penguinet.it
 * Description:     Enable the users to modify an already placed and paid order
 * Author:          Erika Gili
 * Author URI:      www.penguinet.it
 * Text Domain:     modify-woocommerce-order
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         Dottxado/ModifyWooOrder
 */

namespace Dottxado\ModifyWooOrder;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Check if the dependency of this plugin is satisfied
 *
 * @return bool
 */
function check_dependency(): bool {
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . '/wp-admin/includes/plugin.php';
	}
	$site_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
	$dependency   = 'woocommerce/woocommerce.php';
	if ( is_multisite() ) {

		if ( is_plugin_active_for_network( $dependency ) ) {
			return true;
		}
	}
	if ( stripos( implode( $site_plugins ), $dependency ) ) {
		return true;
	}

	return false;
}

/**
 * Admin notice to alert that this plugin requires WooCommerce
 */
function notice_woo_not_active() {
	$class   = 'notice notice-error';
	$message = __( 'Modify Order requires WooCommerce activated.', 'modify-woocommerce-order' );

	wp_kses(
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ),
		array(
			'div' => array(
				'class' => array(),
			),
			'p'   => array(),
		)
	);
}

if ( ! check_dependency() ) {
	add_action( 'admin_notices', 'Dottxado\ModifyWooOrder\notice_woo_not_active' );

	return;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}
require_once __DIR__ . '/vendor/autoload.php';

AdminPanel::get_instance();
CustomerManagement::get_instance();
WooCommerceManagement::get_instance();
