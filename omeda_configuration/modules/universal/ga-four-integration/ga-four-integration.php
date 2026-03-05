<?php

/*
Plugin Name: Google Analytics 4 Integration
Plugin URI:
Description: Integrate Google Analytics 4 tracking with WooCommerce.
Version: 1.0
Author:  James
Author URI: http://midtc.com/
License:
*/

// Exit if accessed directly.
if (! defined('ABSPATH')) exit;

include($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

require_once('ga-four-integration-functions.php');

// Enqueue scripts and styles
add_action('init', 'ga_four_integration_wp_enqueue', 0);
add_action('admin_init', 'ga_four_integration_admin_wp_enqueue', 0);

// WP Admin-related actions
add_action('admin_init', 'ga_four_integration_save');
add_action('admin_menu', 'custom_script_editor_menu');
add_action('admin_init', 'ga_four_integration_api');
add_action('admin_enqueue_scripts', 'enqueue_admin_scripts');

// Frontend-related actions
add_action('template_redirect', 'ga_four_purchase');
add_action('wp_head', 'inject_ga_four_script_into_header');
add_action('wp', 'ga_four_viewing_cart');
add_action('wp', 'ga_four_begin_checkout');
add_action('wp', 'ga_four_viewing_item');

// REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('ga/v1', '/request/', [
        'methods' => ['GET'],
        'permission_callback' => '__return_true',
        'callback' => 'ga_four_api_request_callback',
    ]);
});

// WooCommerce-related actions
add_action('woocommerce_before_shop_loop', 'ga_four_view_item_list');
add_action('woocommerce_cart_item_removed', 'ga_four_remove_from_cart', 10, 2);