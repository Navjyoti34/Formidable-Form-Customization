<?php

/*
Plugin Name: Woo Cart UI Change
Plugin URI:
Description: Update UI of Woo Cart.
Version: 1.0
Author:  James
Author URI: http://midtc.com/
License:
*/

// Exit if accessed directly.
if (! defined('ABSPATH')) exit;

include($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

function custom_empty_cart_message( $message ) {

	$custom_message  = '<div class="col-12 offset-md-1 col-md-10"><p class="cart-empty">';
    $custom_message .= 'Your cart is currently empty.';
    if (isset($_GET['p']) && $_GET['p'] === 'abandoned_cart') {
        if (!is_user_logged_in() && get_option('midtc_transactional_emails') && !empty(get_option('midtc_transactional_emails'))) {
            $custom_message .= "<br/>Please <a href='" . home_url('my-account/') . "?p=abandoned_cart&redirect_to=cart' style='font-size: inherit;'>log into your account</a> to continue your purchase.";
        }
    }
    return $custom_message . '</p></div>';
}

function custom_after_login_action($user_login, $user) {
    if(!(get_option('midtc_transactional_emails') && !empty(get_option('midtc_transactional_emails')))) {
        return;
    }
    $queryString = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';

    $fullReferrer = ($queryString ? '?' . $queryString : '');

    $queryString = parse_url($fullReferrer, PHP_URL_QUERY);
    parse_str($queryString, $parameters);

    if (isset($parameters['p']) && isset($parameters['redirect_to'])) {
        if($parameters['redirect_to'] == 'cart' && $parameters['p'] == 'abandoned_cart') {
            die(header("Location: " . home_url('cart/')));
        }
    }
}
add_action('wp_login', 'custom_after_login_action', 10, 2);

add_filter( 'wc_empty_cart_message', 'custom_empty_cart_message' );