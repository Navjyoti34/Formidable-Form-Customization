<?php

/*
Plugin Name: Newsletter Modals
Plugin URI:
Description: Allows access to newsletter modals.
Version: 1.0
Author:  James
Author URI: http://midtc.com/
License:
*/

if (! defined('ABSPATH')) exit;

include($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');

function newsletter_modal_url_pattern_check($wp) {
    $request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if (!(stripos($request_path, 'modal') !== false && (stripos($request_path, 'template') !== false) || stripos($request_path, 'thank') !== false)) {
        return;
    }

    $pathSegments = explode('/', trim($request_path, '/'));

    if (count($pathSegments) > 0 && in_array($pathSegments[0], ['modal-template-b', 'mobal-b-template', 'modal-template-d', 'mobal-d-template', 'modal-template-c', 'mobal-c-template', 'modal-template', 'modal-thank-you'])) {
        $template_file = plugin_dir_path(__FILE__) . 'templates/' . $pathSegments[0] . '.php';

        if (file_exists($template_file)) {
            include($template_file);
            exit();
        }
    } else {
        return;
    }
}

add_action('parse_request', 'newsletter_modal_url_pattern_check');

?>
