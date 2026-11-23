<?php
/**
 * Plugin Name: LeanCart Reorder Button
 * Plugin URI:  https://asbestosremovallab.com/
 * Description: Adds a “Reorder” button to each order on the My Account → Orders page. Products are added to the cart and the customer is taken to checkout.
 * Version:     1.0.0
 * Author:      LeanCart
 * Author URI:  https://leancart.dev
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: reorder-button
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEANCART_REORDER_BUTTON_DEMO_SITE' ) ) {
	define( 'LEANCART_REORDER_BUTTON_DEMO_SITE', 'https://asbestosremovallab.com' );
}

require_once __DIR__ . '/includes/class-leancart-framework.php';

final class LeanCart_Reorder_Button {

	const SLUG = 'reorder-button';

	public static function init() {
		LeanCart_Framework::boot( array(
			'slug'    => self::SLUG,
			'name'    => 'LeanCart Reorder Button',
			'version' => '1.0.0',
			'file'    => __FILE__,
			'site'    => LEANCART_REORDER_BUTTON_DEMO_SITE,
		) );

		// WooCommerce may load after these plugins; defer the check to runtime.
		add_action( 'admin_notices', array( __CLASS__, 'wc_notice' ) );

		load_plugin_textdomain( 'reorder-button', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'leancart_' . self::SLUG . '_settings_fields', array( __CLASS__, 'fields' ) );

		// WooCommerce may load after this plugin; hooks are registered once WC is ready.
		if ( LeanCart_Framework::is_wc() ) {
			self::hooks();
		} else {
			add_action( 'woocommerce_loaded', array( __CLASS__, 'hooks' ) );
		}
	}

	public static function wc_notice() {
		if ( LeanCart_Framework::is_wc() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( 'LeanCart Reorder Button' ) . '</strong>: ';
		esc_html_e( 'This plugin requires WooCommerce to be active.', 'reorder-button' );
		echo '</p></div>';
	}

	public static function fields() {
		LeanCart_Framework::field_text( 'label', __('Button text', 'reorder-button'), __('Example: Reorder', 'reorder-button'), 'text' );
	}


	public static function hooks() {
		add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'action' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ) );
	}

	public static function action( $actions, $order ) {
		$actions['lc_reorder'] = array(
			'url'  => wp_nonce_url( add_query_arg( 'lc_reorder', $order->get_id(), wc_get_account_endpoint_url( 'orders' ) ), 'lc_reorder_' . $order->get_id() ),
			'name' => LeanCart_Framework::setting( 'label', __( 'Reorder', 'reorder-button' ) ),
		);
		return $actions;
	}

	public static function handle() {
		if ( ! isset( $_GET['lc_reorder'] ) || ! is_user_logged_in() ) { return; }
		$id     = is_scalar( $_GET['lc_reorder'] ) ? absint( wp_unslash( $_GET['lc_reorder'] ) ) : 0;
		$nonce  = isset( $_GET['_wpnonce'] ) && is_scalar( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $id || ! wp_verify_nonce( $nonce, 'lc_reorder_' . $id ) ) { return; }
		$order = wc_get_order( $id );
		if ( ! $order ) { return; }
		if ( ! current_user_can( 'manage_woocommerce' ) && ( ! $order->get_customer_id() || (int) $order->get_customer_id() !== get_current_user_id() ) ) { return; }
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) { return; }
		$added = 0;
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$variation_id = $item->get_variation_id();
			if ( $product && $product->is_purchasable() && $product->is_in_stock() ) {
				WC()->cart->add_to_cart( $item->get_product_id(), $item->get_quantity(), $variation_id, $variation_id ? wc_get_product_variation_attributes( $variation_id ) : array() );
				$added++;
			}
		}
		if ( $added ) {
			wc_add_notice( sprintf( __( '%d products added to the cart.', 'reorder-button' ), $added ) );
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}
	}


}

LeanCart_Reorder_Button::init();

