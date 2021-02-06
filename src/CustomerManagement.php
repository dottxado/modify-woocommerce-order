<?php //phpcs:disable WordPress.Files.FileName.InvalidClassFileName

namespace Dottxado\ModifyWooOrder;

use WC_Cart;
use WC_Order;

/**
 * Class CustomerManagement
 *
 * @package Dottxado\ModifyWooOrder
 */
class CustomerManagement {

	use OrderChecks;

	/**
	 * Singleton instance
	 *
	 * @var CustomerManagement $instance This instance.
	 */
	private static $instance = null;

	/**
	 * CustomerManagement constructor.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_before_cart', array( $this, 'show_editing_conditions' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'show_editing_conditions' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'display_link_to_order_details' ) );
		add_action( 'wp', array( $this, 'notice_with_remaining_time' ) );
		add_action(
			'woocommerce_review_order_after_order_total',
			array(
				$this,
				'show_me_difference_to_be_refunded',
			),
			20
		);
		add_filter(
			'woocommerce_my_account_my_orders_actions',
			array(
				$this,
				'add_edit_order_action',
			),
			50,
			2
		);
	}

	/**
	 * Get the singleton instance
	 *
	 * @return CustomerManagement
	 */
	public static function get_instance(): CustomerManagement {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Enqueue javascript to display an animated countdown
	 */
	public function enqueue_scripts(): void {
		wp_enqueue_script(
			'modify-woocommerce-order-countdown',
			plugin_dir_url( dirname( __FILE__ ) ) . 'js/countdown.js',
			array( 'jquery' ),
			filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'js/countdown.js' ),
			true
		);
	}

	/**
	 * Display cart notice with the conditions of editing the order
	 */
	public function show_editing_conditions(): void {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}
		$edited = WC()->session->get( 'edit_order' );
		if ( empty( $edited ) ) {
			return;
		}
		$order  = new WC_Order( $edited );
		$credit = $order->get_total();
		/* translators: the placeholder is the money credit applied to the new cart */
		wc_print_notice( sprintf( __( 'You have a credit of %1$s. You can change your order for a credit equal to or greater than %2$s. You can change your order only once. If you do not make changes, we will ship the original order.', 'modify-woocommerce-order' ), wc_price( $credit ), wc_price( $credit ) ), 'notice' );
	}

	/**
	 * Display the link to modify the order in the Thank You page
	 *
	 * @param int $order_id The order placed.
	 */
	public function display_link_to_order_details( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $this->can_order_be_modified( $order ) ) {
			return;
		}
		$link                = wc_get_endpoint_url( 'orders', '', get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) );
		$time_left_timestamp = AdminPanel::get_time_to_edit() - ( time() - $order->get_date_created()->getTimestamp() );
		$conditions          = AdminPanel::get_conditions();
		$time_left           = date( 'i:s', $time_left_timestamp );
		echo '<div class="modify-woocommerce-order-edit-order-link">';

		echo wp_kses(
		/* translators: the placeholder is the link to the account page */
			sprintf( __( '%1$s Modify my order * %2$s', 'modify-woocommerce-order' ), '<a href="' . $link . '">', '</a>' ),
			array(
				'a' => array(
					'href' => array(),
				),
			)
		);

		echo wp_kses(
		/* translators: the placeholder is the amount of time left to modify the order */
			'<p>' . sprintf( __( 'Time left %s', 'modify-woocommerce-order' ), '<span data-action="countdown">' . $time_left . '</span>' ) . '</p>',
			array(
				'p'    => array(),
				'span' => array(
					'data-action' => array(),
				),
			)
		);
		if ( ! empty( trim( $conditions ) ) ) {
			echo wp_kses(
				'<p>* ' . $conditions . '</p>',
				array(
					'p' => array(),
				)
			);
		}
		echo '</div>';
	}

	/**
	 * Display a store notice to let the user know how much time remains to modify the order
	 */
	public function notice_with_remaining_time(): void {
		if ( ! is_cart() ) {
			return;
		}

		$edited = WC()->session->get( 'edit_order' );
		if ( empty( $edited ) ) {
			return;
		}
		$order = wc_get_order( $edited );
		if ( ! $this->check_valid_time_window( $order ) ) {
			return;
		}
		$time_left_timestamp = AdminPanel::get_time_to_edit() - ( time() - $order->get_date_created()->getTimestamp() );
		$time_left           = date( 'i:s', $time_left_timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		/* translators: the placeholder is the amount of time left to modify the order */
		$message = sprintf( __( 'You have %s minutes to modify your order!', 'modify-woocommerce-order' ), '<span data-action="countdown">' . $time_left . '</span>' );
		wc_add_notice( $message, 'notice' );
	}

	/**
	 * Display the eventual refund amount
	 *
	 * The fee cannot be more than the order total, so I would like to let the user know that the difference will be refunded
	 */
	public function show_me_difference_to_be_refunded(): void {
		$edited = WC()->session->get( 'edit_order' );
		if ( ! empty( $edited ) ) {
			$new_order_fees = WC()->cart->get_fees();
			if ( isset( $new_order_fees['credit'] ) && abs( $new_order_fees['credit']->amount ) > abs( $new_order_fees['credit']->total ) ) {
				$refund = abs( $new_order_fees['credit']->amount - $new_order_fees['credit']->total );

				echo wp_kses(
				/* translators: the placeholder is the money refund applied to the new cart */
					'<tr class="refund-alert"><th></th><td>' . sprintf( __( 'The remaining %s will be refunded confirming this order.', 'modify-woocommerce-order' ), wc_price( $refund ) ) . '</td></tr>',
					array(
						'tr' => array(
							'class' => array(),
						),
						'th' => array(),
						'td' => array(),
					)
				);
			}
		}
	}

	/**
	 * Add a new order actions in My Account -> Orders page
	 *
	 * @param array $actions The actions that can be made on the order.
	 * @param WC_Order $order The order.
	 *
	 * @return array
	 */
	public function add_edit_order_action( array $actions, WC_Order $order ): array {
		if ( $this->can_order_be_modified( $order ) ) {
			$actions['edit-order'] = array(
				'url'  => wp_nonce_url(
					add_query_arg(
						array(
							'order_again' => $order->get_id(),
							'edit_order'  => $order->get_id(),
						)
					),
					'woocommerce-order_again'
				),
				'name' => __( 'Edit order', 'woocommerce' ),
			);
		}

		return $actions;
	}
}
