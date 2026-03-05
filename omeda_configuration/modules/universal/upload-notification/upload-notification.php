<?php

    if( !defined( 'ABSPATH' ) ) {
        exit;
    }

    include($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

    function check_image_size_before_upload($file) {
        $referral_url = wp_get_referer();

        if($referral_url) {
            parse_str(parse_url($referral_url, PHP_URL_QUERY), $query_params);
            $post_id = intval($query_params['post'] ?? 0);
        }

        $max_file_size = 1048576; // 1 MB

        $file_size = $file['size'];

        if ($file_size > $max_file_size) {
            $custom_error_message = 'The uploaded image is too large. Please choose a smaller image (max 1 MB).';
            $file['error'] = $custom_error_message;

            if (isset($post_id) && $post_id > 0) {
                if (!session_id()) {
                    session_start();
                }

                $_SESSION['upload_error'][strval($post_id)] = array('message' => $custom_error_message, 'time' => time());
            }
        }

        return $file;
    }

    function upload_notification_block_editor_script() {
        $current_post = get_post();

        if (isset($current_post)) {
            wp_enqueue_script('upload-notification-script', plugin_dir_url(__FILE__) . 'assets/js/upload-notification-block-editor.js?' . time());
            localize_upload_notification_block_editor_script($current_post->ID);
        }
    }

    function localize_upload_notification_block_editor_script($postID) {
        wp_localize_script('upload-notification-script', 'uploadNotificationScript', array(
            'postID' => $postID
        ));
    }

    function upload_notifications_check() {
        if(!(isset($_GET['q']) && isset($_GET['post']) && $_GET['q'] == 'notifications')) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        if(isset(($_SESSION['upload_error']))) {
            if(isset(($_SESSION['upload_error'][strval($_GET['post'])]))) {
                $upload_error_session_message = $_SESSION['upload_error'][strval($_GET['post'])];

                $output['message'] = $upload_error_session_message['message'];
                $output['time'] = $upload_error_session_message['time'];

                $json = json_encode($output);

                unset($_SESSION['upload_error'][strval($_GET['post'])]);

                header('Content-Type: application/json');

                echo $json;
            }
        }

        exit();
    }

    add_action('enqueue_block_editor_assets', 'upload_notification_block_editor_script');
    add_action('admin_init', 'upload_notifications_check');
    add_filter('wp_handle_upload_prefilter', 'check_image_size_before_upload');