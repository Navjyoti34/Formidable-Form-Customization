<?php

/*
Plugin Name: Stripe Script Manager
Plugin URI:
Description: Integrate Stripe.js into to the header: https://docs.stripe.com/js
Version: 1.0
Author:  James
Author URI: http://midtc.com/
License:
*/

// Exit if accessed directly.
if (! defined('ABSPATH')) exit;

class Stripe_Script_Manager {
    private static $stripe_public_key;

    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_stripe_script'));
    }

    public static function enqueue_stripe_script() {
        if (!wp_script_is('stripe-js', 'enqueued')) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
        }

        $stripe_public_key = self::get_stripe_public_key();
        if ($stripe_public_key) {
            wp_add_inline_script('stripe-js', 'var stripe = Stripe("' . esc_js($stripe_public_key) . '");');
        }
    }

    private static function get_stripe_public_key() {
        $stripe_settings = get_option('woocommerce_stripe_settings');
        if ($stripe_settings) {
            if (is_serialized($stripe_settings)) {
                $stripe_settings = maybe_unserialize($stripe_settings);
            }

            if (isset($stripe_settings['publishable_key'])) {
                return $stripe_settings['publishable_key'];
            }
        }

        return false;
    }
}

add_action('init', array('Stripe_Script_Manager', 'init'));