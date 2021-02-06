# Modify WooCommerce Order

Enable the users to modify a placed and paid order during a time window, managing the refund if the user modifies the
order toward a lower order amount. It takes into account also the applied coupons and the shipping amount. It works only
with WooCommerce activated.

## Configuration

This plugin enables all the orders in the status "Processing" to be modified by a registered user inside a specific time
window of 15 minutes after placing it. It only needs the administrator to add a text to explain the conditions to the
users. In the future developments I can open also the configuration of the order status and the time window.

## How it works

This plugin uses the WooCommerce functionality of reissuing an order, creating a cart from another order. I have added
some checks to know if the user is modifying an order, to know that the old order needs to be cancelled, it's stock
refilled and it's amount eventually refunded. The amount of the difference between the two orders is treated like a
WooCommerce fee named "Credit" to the users.

In case a refund must be issued, because the new order has a lower amount, the plugin tries to issue an automatic
refund, but, if the payment gateway doesn't support it, the plugin will send an email to the administrator alerting that
the refund must be issued manually.

The plugin logs the status of the orders in both the order notes (linking the two orders and reporting the status of the
restock and the refund), and into the WooCommerce system logs, into an "edit_order" file.

The plugin also marks an order that **can** be modified by the user into the WooCommerce orders dashboard with a pencil
icon beside the order status.

## Next development steps
These points need further analysis or simply need to be done into the refactoring process of this plugin:
- I would like the administrator to customize also the time window duration and the status of the order that can be
  modified: I need to refactor all the parts where the remaining time is displayed, while for the status I think that I
  will use a select field, so that it will be configured only one status, but I would love to have feedbacks about it;
- I would like to integrate the administration panel inside the WooCommerce settings, instead that as a submenu page of
  WooCommerce menu;
- I would like to manage better the "modify conditions" provided by the administrator, displaying them not only in the
  cart but also in the WooCommerce banners, where now I have a fixed text;
- I would display in the WooCommerce orders dashboard also if an order is actively being modified. 
