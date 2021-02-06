<?php //phpcs:disable WordPress.Files.FileName.InvalidClassFileName

namespace Dottxado\ModifyWooOrder;

use Exception;
use WC_Cart;
use WC_Log_Levels;
use WC_Order;

/**
 * Class WooCommerceManagement
 *
 * @package Dottxado\ModifyWooOrder
 */
class WooCommerceManagement {

	use OrderChecks;

	/**
	 * Singleton instance
	 *
	 * @var WooCommerceManagement $instance This instance.
	 */
	private static $instance = null;

	/**
	 * WooCommerceManagement constructor.
	 */
	private function __construct() {
		add_filter( 'woocommerce_valid_order_statuses_for_order_again', array( $this, 'order_again_statuses' ) );
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'detect_edit_order' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_edit_order' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'mark_order_in_time_window' ), 50 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'update_order_total' ), 20, 1 );
	}

	/**
	 * Get the singleton instance
	 *
	 * @return WooCommerceManagement
	 */
	public static function get_instance(): WooCommerceManagement {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Allow order again for configured status
	 *
	 * @param array $statuses The order statuses from WooCommerce.
	 *
	 * @return array
	 */
	public function order_again_statuses( array $statuses ): array {
		$statuses[] = AdminPanel::get_status();

		return $statuses;
	}

	/**
	 * Detect edit order action and store in session
	 *
	 * @param WC_Cart $cart The cart.
	 */
	public function detect_edit_order( WC_Cart $cart ): void {
		if ( ! isset( $_GET['_wpnonce'] ) || ( isset( $_GET['_wpnonce'] ) && ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'woocommerce-order_again' ) ) ) {
			return;
		}
		$user = wp_get_current_user();
		if ( 0 !== $user && isset( $_GET['edit_order'] ) ) {
			$order_id = absint( $_GET['edit_order'] );
			$order    = wc_get_order( $order_id );
			if ( $order instanceof WC_Order && $order->get_customer_id() === $user->ID ) {
				WC()->session->set( 'edit_order', absint( $_GET['edit_order'] ) );
				$this->add_woo_log( 'Started editing order ' . absint( $_GET['edit_order'] ) );
			}
		}
	}

	/**
	 * Save Order Action if New Order is Placed
	 *
	 * @param int $order_id The order id.
	 */
	public function save_edit_order( int $order_id ): void {
		$edited = WC()->session->get( 'edit_order' );
		if ( empty( $edited ) ) {
			return;
		}
		$new_order      = new WC_Order( $order_id );
		$old_order      = new WC_Order( $edited );
		$new_order_edit = $new_order->get_edit_order_url();
		$old_order_edit = $old_order->get_edit_order_url();
		// update this new order.
		update_post_meta( $order_id, '_edit_order', $edited );
		/* translators: the placeholder is the old order number before the modification */
		$new_order->add_order_note( sprintf( __( 'Order placed after editing. Old order number: %s', 'modify-woocommerce-order' ), '<a href="' . $old_order_edit . '">' . $edited . '</a>' ) );
		// cancel previous order.
		/* translators: the placeholder is the new order number */
		$old_order->update_status( 'cancelled', sprintf( __( 'Order cancelled after editing. New order number: %s', 'modify-woocommerce-order' ), ' <a href="' . $new_order_edit . '">' . $order_id . '</a> - ' ) );
		WC()->session->set( 'edit_order', '' );
		$this->add_woo_log( 'Edited order placed! Old order id ' . $edited . ' - New order id ' . $order_id );
		try {
			$this->refund_cancelled_order( $old_order, $new_order );
		} catch ( Exception $e ) {
			$this->send_email_for_refund_error( $old_order );
			$message = __( 'Impossible to load WooCommerce data, the old order is cancelled but not refunded nor restocked', 'modify-woocommerce-order' );
			$this->add_note_modify_error( $message, $old_order );
			$this->send_email_for_refund_error( $old_order );
			$this->add_woo_log( $message . ' - ORDER ID: ' . $old_order->get_id(), WC_Log_Levels::ERROR );
		}
	}

	/**
	 * Mark the order as the user can yet modify into the WooCommerce orders dashboard.
	 *
	 * @param string $column The column name.
	 */
	public function mark_order_in_time_window( string $column ): void {
		global $the_order;
		if ( 'order_status' !== $column ) {
			return;
		}

		if ( $this->can_order_be_modified( $the_order ) ) {
			echo '<span class="dashicons dashicons-edit" title="' . esc_html__( 'This order can yet be modified by the user!', 'modify-woocommerce-order' ) . '"></span>';
		}
	}

	/**
	 * Calculate new total if edited order
	 *
	 * @param WC_Cart $cart The cart.
	 */
	public function update_order_total( WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$edited = WC()->session->get( 'edit_order' );
		if ( empty( $edited ) ) {
			return;
		}
		$order = new WC_Order( $edited );
		if ( $this->check_valid_time_window( $order ) ) {
			$credit = - 1 * $order->get_total();
			$cart->add_fee( __( 'Credit', 'modify-woocommerce-order' ), $credit );
		} else {
			WC()->session->set( 'edit_order', '' );
		}
	}

	/**
	 * Add an info log into the WooCommerce logs
	 *
	 * @param string $message The message to add into the logs.
	 * @param string $log_level The log level.
	 */
	private function add_woo_log( string $message, string $log_level = WC_Log_Levels::INFO ): void {
		$log = wc_get_logger();
		$log->log( $log_level, $message, array( 'source' => 'order_edit' ) );
	}

	/**
	 * Refund eventual difference in the two orders and restock the items
	 *
	 * @param WC_Order $old_order The old order to modify.
	 * @param WC_Order $new_order The new order.
	 *
	 * @throws Exception When `WC_Data_Store::load` validation fails in wc_get_order_item_meta.
	 */
	private function refund_cancelled_order( WC_Order $old_order, WC_Order $new_order ): void {
		$line_items = $this->get_line_items( $old_order );
		$amount     = $this->get_refund_amount( $old_order, $new_order );

		if ( $amount <= 0 ) {
			$this->only_restock( $old_order, $line_items );
		} else {
			$this->refund_and_restock( $amount, $old_order, $line_items );
		}
	}

	/**
	 * Refund the order and restock the items.
	 *
	 * @param float $amount The amount to be refunded.
	 * @param WC_Order $order The order to be cancelled.
	 * @param array $line_items The line items of the order to be restocked.
	 */
	private function refund_and_restock( float $amount, WC_Order $order, array $line_items ): void {
		$args = array(
			'amount'         => $amount,
			'reason'         => 'Modified order by user',
			'order_id'       => $order->get_id(),
			'line_items'     => $line_items,
			'refund_payment' => true,
			'restock_items'  => true,
		);
		try {
			$result = wc_create_refund( $args );
			if ( is_wp_error( $result ) ) {
				$this->send_email_for_refund_error( $order );
				$message = __( 'First (of two) attempt to create refund: ', 'modify-woocommerce-order' ) . $result->get_error_message();
				$this->add_note_modify_error( $message, $order );
				$this->add_woo_log( $message . ' - ORDER ID: ' . $order->get_id(), WC_Log_Levels::ERROR );
				unset( $args['refund_payment'] );
				$result = wc_create_refund( $args );
				if ( is_wp_error( $result ) ) {
					$message = __( 'Last (of two) attempt to create refund and to do the restock (without automatic refund of the amount): ', 'modify-woocommerce-order' ) . $result->get_error_message();
					$this->add_note_modify_error( $message, $order );
					$this->send_email_for_refund_error( $order );
					$this->add_woo_log( $message . ' - ORDER ID: ' . $order->get_id(), WC_Log_Levels::ERROR );
				} else {
					$message = __( 'You have to manually issue the refund of the amount', 'modify-woocommerce-order' );
					$this->add_note_modify_error( $message, $order );
					$this->send_email_for_refund_error( $order );
					$this->add_woo_log( $message . ' - ORDER ID: ' . $order->get_id(), WC_Log_Levels::ERROR );
				}
			} else {
				$this->add_woo_log( __( 'Refund and restock done!', 'modify-woocommerce-order' ) . ' - ORDER ID: ' . $order->get_id() );
			}
		} catch ( Exception $e ) {
			$this->add_note_modify_error( $e->getMessage(), $order );
			$this->send_email_for_refund_error( $order );
			$this->add_woo_log( $e->getMessage() . ' - ORDER ID: ' . $order->get_id(), WC_Log_Levels::ERROR );
		}
	}

	/**
	 * Restock the items of an order
	 *
	 * @param WC_Order $order The order to cancel.
	 * @param array $line_items The line items to restock.
	 */
	private function only_restock( WC_Order $order, array $line_items ): void {
		$args = array(
			'amount'         => 0,
			'reason'         => 'Modified order by user',
			'order_id'       => $order->get_id(),
			'line_items'     => $line_items,
			'refund_payment' => false,
			'restock_items'  => true,
		);
		try {
			$result = wc_create_refund( $args );
			if ( is_wp_error( $result ) ) {
				$this->send_email_for_refund_error( $order );
				$message = __( 'Error attempting to restock: ', 'modify-woocommerce-order' ) . $result->get_error_message();
				$this->add_note_modify_error( $message, $order );
				$this->add_woo_log( $message . ' - ORDER ID: ' . $order->get_id(), WC_Log_Levels::ERROR );
			} else {
				$this->add_woo_log( __( 'Restock done!', 'modify-woocommerce-order' ) . ' - ORDER ID: ' . $order->get_id() );
			}
		} catch ( Exception $e ) {
			$this->add_note_modify_error( $e->getMessage(), $order );
			$this->send_email_for_refund_error( $order );
			$this->add_woo_log( $e->getMessage() . ' - ORDER ID: ' . $order->get_id(), WC_Log_Levels::ERROR );
		}
	}

	/**
	 * Get the refund amount taking into consideration the discounts and the shipping cost
	 *
	 * @param WC_Order $old_order The order to be modified.
	 * @param WC_Order $new_order The new order.
	 *
	 * @return float
	 * @throws Exception When `WC_Data_Store::load` validation fails in wc_get_order_item_meta.
	 */
	private function get_refund_amount( WC_Order $old_order, WC_Order $new_order ): float {
		$new_order_amount = 0;
		$new_order_items  = $new_order->get_items();
		if ( $new_order_items ) {
			foreach ( $new_order_items as $item_id => $item ) {
				$new_order_amount = wc_format_decimal( $new_order_amount ) + wc_format_decimal( wc_get_order_item_meta( $item_id, '_line_total' ) );
			}
		}

		$old_order_coupon_total = $old_order->get_discount_total();

		return ( (float) $old_order->get_subtotal() + (float) $old_order->get_shipping_total() - $old_order_coupon_total ) - ( (float) $new_order_amount + (float) $new_order->get_shipping_total() );
	}

	/**
	 * Get the line items of an order
	 *
	 * @param WC_Order $order The order where to get the line items.
	 *
	 * @return array
	 * @throws Exception When `WC_Data_Store::load` validation fails in wc_get_order_item_meta.
	 */
	private function get_line_items( WC_Order $order ): array {
		$line_items  = array();
		$order_items = $order->get_items();
		if ( $order_items ) {
			foreach ( $order_items as $item_id => $item ) {
				$refund_tax = 0;
				$tax_data   = wc_get_order_item_meta( $item_id, '_line_tax_data' );
				$quantity   = wc_get_order_item_meta( $item_id, '_qty' );
				if ( is_array( $tax_data ) ) {
					$refund_tax = array_map( 'wc_format_decimal', $tax_data['total'] );
				}

				$line_items[ $item_id ] = array(
					'qty'          => $quantity,
					'refund_total' => 0,
					'refund_tax'   => $refund_tax,
				);
			}
		}

		return $line_items;
	}

	/**
	 * Log the error message of the refund
	 *
	 * @param string $message The message to log.
	 * @param WC_Order $order The order the message is related to.
	 */
	private function add_note_modify_error( string $message, WC_Order $order ): void {
		/* translators: the placeholder is the error message from WooCommerce */
		$order->add_order_note( sprintf( __( 'Error while modifying order - %s', 'modify-woocommerce-order' ), $message ) );
	}

	/**
	 * Send email to administrator about the refund error
	 *
	 * @param WC_Order $order The order the message is related to.
	 */
	private function send_email_for_refund_error( WC_Order $order ): void {
		$to = get_option( 'admin_email' );
		/* translators: the placeholder is the order ID */
		$subject = sprintf( __( 'Error on refund for order %s', 'modify-woocommerce-order' ), $order->get_id() );
		/* translators: the placeholder is the link to the order to refund */
		$body = sprintf( __( 'There was an error while refunding the order, please check %1$s order notes %2$s for all information.', 'modify-woocommerce-order' ), '<a href="' . $order->get_edit_order_url() . '">', '</a>' );
		wp_mail( $to, $subject, $body );
	}
}
