<?php
	/*
	Plugin Name: Vimeo Support
	Plugin URI: 
	Description: Vimeo integration with customizable styles and JS.
	Version: 0.0.1
	Author: James
	Author URI: 
	License: 
	*/

    add_theme_support( 'custom-background', array('wp-head-callback' => 'cssInjection'));

    function cssInjection() {
        echo '<style>';
        ?>
        .fluid-width-video-wrapper {
            min-width: 260px !important;
            min-height: 155px !important;
            margin-top: 0px !important;
            margin-bottom: 0px !important;
        }

        .flowplayer-embed-container:empty {
            display:none !important;
        }

        .flowplayer-embed-container:blank {
            display:none !important;
        }

        .container .learndash-wrapper .ld-tabs-content iframe {
            box-shadow: unset !important;
            border-radius: unset !important;
        }
        <?php
        echo '</style>';
    }

    function jsFooterInjection() {
    ?>
        <script src="https://player.vimeo.com/api/player.js"></script>
    <?php
    }

    add_action('wp_footer', 'jsFooterInjection');
