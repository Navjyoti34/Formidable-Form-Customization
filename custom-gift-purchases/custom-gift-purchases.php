<?php

/*
Plugin Name: Custom Gift Purchases
Plugin URI:
Description:  custom Grants the ability to gift purchases in checkout.
Version: 1.1
Author:  Navjyoti
License:
*/

if (! defined('ABSPATH')) exit;
include($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');

$current_url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$current_url = explode('/', $current_url);
$current_url = array_filter($current_url);
$find_url = array_search('one-page-checkout', $current_url);

if($find_url) {
	return;
}

include(plugin_dir_path(__FILE__) . 'includes/config.php');
include(plugin_dir_path(__FILE__) . 'includes/functions.php');
include(plugin_dir_path(__FILE__) . 'classes/class-form-builder.php');

include(plugin_dir_path(__FILE__) . 'account-creation-management.php');
include(plugin_dir_path(__FILE__) . 'move-subscription.php');
include(plugin_dir_path(__FILE__) . 'woo-admin-orders.php');
include(plugin_dir_path(__FILE__) . 'watch-for-giftee.php');
include(plugin_dir_path(__FILE__) . 'email-manager.php');
include(plugin_dir_path(__FILE__) . 'omeda-gift-processing.php');
include(plugin_dir_path(__FILE__) . 'hide-extended-subs.php');

// [MAIN] Init hook that executes on each page
add_action('init', 'check_user_meta');
add_action('init', 'clear_user_woo_cache');

// [MAIN] Hook to schedule event upon plugin activation
register_activation_hook(__FILE__, 'gfp_create_order_download_permissions_table');
register_activation_hook(__FILE__, 'setup_gift_scheduling_event');
register_activation_hook(__FILE__, 'gfp_update_order_dl_permissions');
register_activation_hook(__FILE__, 'gfp_create_learndash_permissions_table');

// [MAIN] Woocommerce hooks
add_action('woocommerce_edit_account_form_start', 'add_ability_to_edit_username_account_form');
add_action('woocommerce_before_edit_account_form', 'add_custom_styles_to_account_details');
add_action('woocommerce_save_account_details', 'save_username_change', 10, 1);
add_filter('woocommerce_product_needs_shipping', 'maybe_each_product_needs_shipping', 10, 2);
add_action('woocommerce_checkout_process', 'cust_woocommerce_form_validation');
add_action('woocommerce_after_checkout_validation', 'remove_duplicate_errors', 10, 2 );
add_action('woocommerce_after_order_notes', 'add_custom_checkout_hidden_field');
add_filter('woocommerce_shipping_fields', 'required_field_override');
add_filter('woocommerce_customer_get_downloadable_products', 'gift_inclusion_downloadable_products', 9999, 1);
add_action('woocommerce_checkout_update_order_meta', 'process_order_pick_gifts');
add_action('woocommerce_checkout_process', 'check_possible_duplicate_subscription_purchase');
add_action('woocommerce_checkout_before_customer_details', 'inject_gift_assets', 10, 2);
add_action('woocommerce_cart_item_removed', 'custom_cart_item_removed_callback', 10, 2);
add_action('woocommerce_thankyou', 'gift_order_confirmation_action', 10, 1);
add_action('woocommerce_thankyou', 'gfp_process_learndash_gifts', 5, 1);
add_action('woocommerce_order_status_changed', 'gift_on_order_status_change', 10, 4);

// [MAIN] Hook to check gifts that are awaiting processing
add_action('process_gift_scheduled_orders_hook', 'process_gift_scheduled_orders');

// [MAIN] Admin area hooks
add_action('add_meta_boxes', 'process_order_meta_box');
add_action('admin_notices', 'add_gift_general_notification' );
