<?php
    /*
    Plugin Name: Omeda Configuration
    Plugin URI: 
    Description: A plugin developed by MIDTC to enhance various WordPress and Woocommerce functions.
    Version: 0.0.1
    Author: Navjyoti
    Author URI: 
    License: 
    */

    if( !defined( 'ABSPATH' ) ){
        exit; // Exit if accessed directly
    }

    // ---------------------- DEFINE OMEDA -------------------- //

    function INCLUDE_OMEDA() {
        ob_start();
        require(plugin_dir_path( __FILE__ ) . 'includes/omeda/config.php');
        $omeda_config = ob_get_clean();

        return $omeda_config;
    }

    // ------------------- END DEFINE OMEDA ------------------- //

    // -------------------- START MODULES ------------------- //

    $development_environment = strpos(get_site_url(), 'dev.') !== false;
    $property = explode('.', parse_url(get_site_url(), PHP_URL_HOST))[count(explode('.', parse_url(get_site_url(), PHP_URL_HOST))) - 2];

    $universal_modules = [
        'newsletter-signup' => true,
        'pdf-protect' => true,
        'subscription-opt' => true,
        'transactional-emails' => true,
        'step-up-pricing' => !$development_environment,
        'empty-cart-ui-change' => true,
        'ga-four-integration' => true,
        'general-ledger-field' => true,
        'amazon-file-retrieval' => true,
        'vimeo-support' => true,
        'cancellation-action' => true,
        'holiday-coupon-generator' => true,
        'omeda-panel' => true,
        'omeda-staging' => true,
        'email-router' => true,
        'stripe-script-manager' => true,
        'yith-anti-fraud-mods' => true,
        'account-verification' => true,
        'omedia-olytics-handler' => true,
        'print-to-digital'       => true,
    ];

    $reliant_modules = [
        'newsletter-modals' => true,
        'amp-custom-theme' => true,
        'single-sign-on' => true,
        'shipping-rules' => true
    ];

    define('UNIVERSAL_MODULES', $universal_modules);
    define('RELIANT_MODULES', $reliant_modules);

    foreach ($universal_modules as $universal_module => $flag) {
        try {
            if ($flag) include plugin_dir_path(__FILE__) . "modules/universal/$universal_module/$universal_module.php";
        } catch (Exception $e) { }
    }

    foreach ($reliant_modules as $reliant_module => $flag) {
        try {
            if ($flag) include plugin_dir_path(__FILE__) . "modules/reliant/$property/$reliant_module/$reliant_module.php";
        } catch (Exception $e) { }
    }

    // --------------------- END MODULES -------------------- //