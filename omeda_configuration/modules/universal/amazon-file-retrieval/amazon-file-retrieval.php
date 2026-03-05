<?php

	/*
	Plugin Name: Amazon File Retrieval
	Plugin URI: 
	Description: Retrieval of Amazon Web Services (AWS) files from shortcode.
	Version: 0.0.1
	Author: James
	Author URI: 
	License: 
	*/

	if( !defined( 'ABSPATH' ) ){
        exit;
    }

    if (!function_exists('validate_url')) {
	    function validate_url($url) {
	        return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
	    }
    }


    function execute_on_shortcode() {
	    if(isset($_GET['quick_grab'])) {
		    if (!str_contains($_GET['quick_grab'], 'amazon')) {
			    die('locked to specific shortcode');
		    }

		    $shortcode_content = (do_shortcode($_GET['quick_grab']));

		    if(!empty($shortcode_content) && isset($shortcode_content) && validate_url($shortcode_content)) {
			    header('Location: ' . $shortcode_content);
		    }

		    die('Shortcode failed. Re-check URL. Did you leave out a bracket: "]"?');
	    }
    }

    add_action('admin_init', 'execute_on_shortcode');

    if (!function_exists('validate_url')) {
        function validate_url($url) {
            return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
        }
    }


    function execute_on_pdf_shortcode() {
        if(isset($_GET['pdf_grab']) && isset($_GET['fO1rzxW9ne6srgVN4gC7']) && $_GET['fO1rzxW9ne6srgVN4gC7'] == 'Trqg34Hck41Nd0ppQapt') {
            if (!str_contains($_GET['pdf_grab'], 'amazon')) {
                die('locked to specific shortcode');
            }

            if (!str_contains($_GET['pdf_grab'], 'watermarked')) {
                die('locked to specific shortcode');
            }

            $shortcode_content = (do_shortcode($_GET['pdf_grab']));

            if(!empty($shortcode_content) && isset($shortcode_content) && validate_url($shortcode_content)) {
                header('Location: ' . $shortcode_content);
            }

            die('Shortcode failed. Re-check URL. Did you leave out a bracket: "]"?');
        }
    }

    add_action('init', 'execute_on_pdf_shortcode');