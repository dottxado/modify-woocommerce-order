<?php //phpcs:disable WordPress.Files.FileName.InvalidClassFileName

namespace Dottxado\ModifyWooOrder;

use WC_Order;

/**
 * Trait OrderChecks
 *
 * @package Dottxado\ModifyWooOrder
 */
trait OrderChecks {

	/**
	 * Check if the cart session is yet in the editing time window
	 *
	 * @param WC_Order $order The order to edit.
	 *
	 * @return bool
	 */
	private function check_valid_time_window( WC_Order $order ): bool {
		return time() - $order->get_date_created()->getTimestamp() < AdminPanel::get_time_to_edit();
	}

	/**
	 * Get if the order is a modification of another order
	 *
	 * @param WC_Order $order The order to check.
	 *
	 * @return bool
	 */
	private function is_a_modification( WC_Order $order ): bool {
		$old_order_id = get_post_meta( $order->get_id(), '_edit_order', true );
		if ( empty( $old_order_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if an order can be modified
	 *
	 * An order can be modified if:
	 *   * it's placed by a registered user,
	 *   * the status of the order is the one configured,
	 *   * the order is in the enabled time window,
	 *   * the total amount of the order is greater than 0,
	 *   * the order is not a modification of another order.
	 *
	 * @param WC_Order $order The order to check.
	 *
	 * @return bool
	 */
	private function can_order_be_modified( WC_Order $order ): bool {
		if ( 0 !== $order->get_customer_id() && $order->has_status( AdminPanel::get_status() ) && $this->check_valid_time_window( $order ) && $order->get_total() > 0 && ! $this->is_a_modification( $order ) ) {
			return true;
		}

		return false;
	}
}
