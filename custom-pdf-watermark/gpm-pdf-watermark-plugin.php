<?php
/*
Plugin Name: GPM PDF Watermark Plugin
Description: Adds a watermark to PDFs dynamically using PDF-LIB JavaScript library.
Version: 1.0
Author: Asentech Developer
License: GPL-2.0-or-later
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (!function_exists('gpm_check_url')) {
    function gpm_check_url($url) {
        return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
    }
}

// Enqueue JavaScript
add_action('wp_enqueue_scripts', 'enqueue_scripts');   
function enqueue_scripts() {
    wp_enqueue_script('pdf-lib', 'https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js', [], '1.17.1', true);
    wp_enqueue_script('pdf-watermark', plugin_dir_url(__FILE__) . 'assets/js/pdf-watermark.js', ['pdf-lib'], time(), true);
    wp_localize_script('pdf-watermark', 'PDFWatermarkData', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('secure_nonce'),
    ]);
}

// Enqueue watermark.css in the plugin
function enqueue_watermark_css() {
    // Define the path to the CSS file
    $css_file_url = plugin_dir_url(__FILE__) . 'assets/css/watermark.css';

    // Enqueue the CSS file
    wp_enqueue_style('watermark-css', $css_file_url, array(), time(), 'all');
}
add_action('wp_enqueue_scripts', 'enqueue_watermark_css');

function hex_encode($data) {
    return bin2hex($data);
}

function hex_decode($data) {
    return hex2bin($data);
}

function handle_secure_data($input, $isEncryption) {
    $keyHash = "6327d814f3ccb7a356cb0f7ccc31c9552c85016b";
    $secureKey = substr(hash('sha256', $keyHash, true), 0, 32); // 32 bytes for AES-256-CBC

    if ($isEncryption) {
        $encodedInput = hex_encode($input);
        
        // Secure IV generation using random_bytes
        $initialVector = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));

        $cipherText = openssl_encrypt($encodedInput, 'aes-256-cbc', $secureKey, 0, $initialVector);
        return hex_encode($cipherText . '::' . hex_encode($initialVector));
    }

    list($cipherText, $initialVector) = explode('::', hex_decode($input), 2);
    return hex_decode(openssl_decrypt($cipherText, 'aes-256-cbc', $secureKey, 0, hex_decode($initialVector)));
}

function downloadPDF($sourceUrl, $fileName) {
    // Get the WordPress uploads directory
    $uploadDir = wp_upload_dir();
    $uploadsPath = $uploadDir['basedir'] . '/pdf_files/'; // Directory for storing PDFs

    // Ensure the pdf_files directory exists
    if (!is_dir($uploadsPath)) {
        wp_mkdir_p($uploadsPath);
    }

    // Ensure the filename includes .pdf extension
    if (pathinfo($fileName, PATHINFO_EXTENSION) !== 'pdf') {
        $fileName .= '.pdf';
    }

    // Set the file path
    $filePath = $uploadsPath . $fileName;

    // Download the file
    $fileContents = file_get_contents($sourceUrl);
    if ($fileContents === false) {
        wp_die('Failed to download the PDF file.');
    }

    // Save the file locally
    file_put_contents($filePath, $fileContents);

    // Return the public URL to the file
    $publicUrl = $uploadDir['baseurl'] . '/pdf_files/' . $fileName;
    return $publicUrl;
}

add_action('wp_ajax_log_download', 'log_download');
add_action('wp_ajax_nopriv_log_download', 'log_download');
function log_download() {
    // Verify the nonce
    check_ajax_referer('secure_nonce', 'security');

    // Check if the user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in.');
    }

    // Get the permission ID from the request
    $permission_id = isset($_POST['permission_id']) ? sanitize_text_field($_POST['permission_id']) : '';
    if (!$permission_id) {
        wp_send_json_error('Permission ID missing.');
    }

    // Retrieve the download object using the permission ID
    $download = new WC_Customer_Download($permission_id);

    if (!$download->get_id()) {
        wp_send_json_error('Invalid permission ID.');
    }

    // Validate the current user matches the download owner
    $current_user_id = get_current_user_id();
    $order = wc_get_order($download->get_order_id());

    if (!$order || $order->get_user_id() !== $current_user_id) {
        wp_send_json_error('Unauthorized access.');
    }

    // Track the download (this will update download count, remaining downloads, and log the download)
    try {
        $download->track_download(); // This will handle incrementing the download count and logging the download
        wp_send_json_success('Download tracked successfully.');
    } catch (Exception $e) {
        wp_send_json_error('Error tracking download: ' . $e->getMessage());
    }
}

add_action('template_redirect', 'handle_pdf_file_download');
function handle_pdf_file_download() {
    // Check if the current page is the download page
    if (is_page('download-pdf-file')) {
        try {
            global $wpdb;
            // Retrieve cookies if they exist
            $pdf_download_link = isset($_COOKIE['pdf_download_link']) ? sanitize_text_field($_COOKIE['pdf_download_link']) : '';
            $pdf_watermark_message = isset($_COOKIE['pdf_watermark_message']) ? sanitize_text_field($_COOKIE['pdf_watermark_message']) : '';
            $email = isset($_COOKIE['email']) ? sanitize_text_field($_COOKIE['email']) : '';
            $current_time = isset($_COOKIE['current_time']) ? sanitize_text_field($_COOKIE['current_time']) : '';

            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_die(__('You must be logged in to download files.', 'woocommerce'), __('Download Error', 'woocommerce'), 403);
            }

            parse_str(parse_url($pdf_download_link, PHP_URL_QUERY), $query_params);

            $product_id = $query_params['download_file'];
            $download_id=$query_params['key'];
            $download_pdf_files = get_post_meta($product_id, '_downloadable_files', true);

            $download_permission = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions 
                     WHERE download_id = %s AND product_id = %d  AND user_id = %d",
                    $download_id,
                    $product_id,
                    $user_id
                )
            );
            if(!empty($download_permission)){
                $permission_id=$download_permission->permission_id;
            }
            $is_pdf_file = false;

            if (!empty($download_pdf_files)) {
                $download_pdf_files = maybe_unserialize($download_pdf_files);

                if (!empty($download_pdf_files)) {
                    $download_pdf_files = maybe_unserialize($download_pdf_files);
                    if (isset($download_pdf_files[$download_id])) {
                        $file_data = $download_pdf_files[$download_id];
                        if (isset( $file_data['file']) && strpos($file_data['file'], 'amazon_s3') !== false) {
                            $aws_s3_shortcode = $file_data['file'];
                            $aws_s3_shortcode_content = (do_shortcode($aws_s3_shortcode));
                            if(!empty($aws_s3_shortcode_content) && isset($aws_s3_shortcode_content) && gpm_check_url($aws_s3_shortcode_content)) {
                                $pdf_location = $aws_s3_shortcode_content;
                            }
                            if( strpos($file_data['file'], '.pdf') !== false ){
                                $is_pdf_file = true;
                            }
                        }
                    }
                }
            }

            if(!$is_pdf_file) {
                throw new Exception("invalid status");
            }
            
            $parsedUrl = parse_url($pdf_location);
            
            $path = $parsedUrl['path'];
            $fileName = basename($path);
            $pdf_local = downloadPDF($pdf_location, time().'_'.$fileName);

            setcookie("pdf_download_link", "", time() - 3600, "/");
            setcookie("pdf_watermark_message", "", time() - 3600, "/");
            setcookie("email", "", time() - 3600, "/");
            setcookie("current_time", "", time() - 3600, "/");

            //Code added to pass PDF file data to olytics using cookie
            $product = wc_get_product($product_id);
            $productTitle = $product->get_name();
            $c_name = 'store_products';
            $exp_time = time() + (86400 * 30); // 30 days from now
            if(function_exists('gpm_get_product_type_for_olytics')){                
                $product_type=gpm_get_product_type_for_olytics($product_id);
            }
            $olytics_data = array(
                'PDF' => $productTitle                
            );
            if(!empty($product_type)){
                $olytics_data['Action']=$product_type.' Download';
            }
            setcookie($c_name, json_encode($olytics_data), $exp_time, "/");

            if (!empty($pdf_local)) {
                setcookie("downloadPdfFilePath", $pdf_local, time() + (86400 * 30), "/");
                setcookie("order_permission", $permission_id, time() + (86400 * 30), "/");
                setcookie("pdfWatermark", $pdf_watermark_message, time() + (86400 * 30), "/");
                setcookie("pdfFileName", $fileName, time() + (86400 * 30), "/");
                wp_redirect('/my-account/downloads/');
                exit;
            } else {
                wp_die('PDF file not found.');
            }
        } catch(Exception $e) { 
            echo $e->getMessage();
        }

        exit;
    }
}

 // Handle PDF download and redirect to custom page
add_action('init', 'handle_pdf_download', -1);
function handle_pdf_download() {

    if(isset($_GET['downloadPdf'])) {
        try {
            global $wpdb;
            $pdf = urldecode($_GET['downloadPdf']);
            $decrypt_pdf_get = handle_secure_data($pdf, false);
            $decrypted_pdf_url = explode('|', $decrypt_pdf_get);

            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_die(__('You must be logged in to download files.', 'woocommerce'), __('Download Error', 'woocommerce'), 403);
            }

            $pdf_location = $decrypted_pdf_url[0];

            if(empty($pdf_location) || !gpm_check_url($pdf_location)) {
                throw new Exception("unable to obtain pdf location"); // for some reason can't get pdf location
            }

            if(!(count($decrypted_pdf_url) >= 4)) {
                throw new Exception("unable to obtain variables"); // looking for pdf location, watermark, email and time - without those throw error
            }

            $pdf_watermark_message = $decrypted_pdf_url[1];
            $email = $decrypted_pdf_url[2];
            $current_time = $decrypted_pdf_url[3];

            parse_str(parse_url($pdf_location, PHP_URL_QUERY), $query_params);

            $product_id = $query_params['download_file'];
            $download_id=$query_params['key'];
            $download_pdf_files = get_post_meta($product_id, '_downloadable_files', true);

            $download_permission = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions 
                     WHERE download_id = %s AND product_id = %d  AND user_id = %d",
                    $download_id,
                    $product_id,
                    $user_id
                )
            );
            if(!empty($download_permission)){
                $permission_id=$download_permission->permission_id;
            }
            $is_pdf_file = false;

            if (!empty($download_pdf_files)) {
                $download_pdf_files = maybe_unserialize($download_pdf_files);

                if (!empty($download_pdf_files)) {
                    $download_pdf_files = maybe_unserialize($download_pdf_files);
                    if (isset($download_pdf_files[$download_id])) {
                        $file_data = $download_pdf_files[$download_id];
                        if (isset( $file_data['file']) && strpos($file_data['file'], 'amazon_s3') !== false) {
                            $aws_s3_shortcode = $file_data['file'];
                            $aws_s3_shortcode_content = (do_shortcode($aws_s3_shortcode));
                            if(!empty($aws_s3_shortcode_content) && isset($aws_s3_shortcode_content) && gpm_check_url($aws_s3_shortcode_content)) {
                                $pdf_location = $aws_s3_shortcode_content;
                            }
                            if( strpos($file_data['file'], '.pdf') !== false ){
                                $is_pdf_file = true;
                            }
                        }
                    }
                }
            }

            if(!$is_pdf_file) {
                throw new Exception("invalid status");
            }
            
            $parsedUrl = parse_url($pdf_location);
            
            $path = $parsedUrl['path'];
            $fileName = basename($path);
            $pdf_local = downloadPDF($pdf_location, time().'_'.$fileName);

            //Code added to pass PDF file data to olytics using cookie
            $product = wc_get_product($product_id);
            $productTitle = $product->get_name();
            $c_name = 'store_products';
            $exp_time = time() + (86400 * 30); // 30 days from now
            if(function_exists('gpm_get_product_type_for_olytics')){                
                $product_type=gpm_get_product_type_for_olytics($product_id);
            }
            $olytics_data = array(
                'PDF' => $productTitle                
            );
            if(!empty($product_type)){
                $olytics_data['Action']=$product_type.' Download';
            }
            setcookie($c_name, json_encode($olytics_data), $exp_time, "/");

            if (!empty($pdf_local)) {
                setcookie("downloadPdfFilePath", $pdf_local, time() + (86400 * 30), "/");
                setcookie("order_permission", $permission_id, time() + (86400 * 30), "/");
                setcookie("pdfWatermark", $pdf_watermark_message, time() + (86400 * 30), "/");
                setcookie("pdfFileName", $fileName, time() + (86400 * 30), "/");
                wp_redirect('/my-account/downloads/');
               // wp_redirect('/my-account/downloads/?file=' . urlencode($pdf_location));
                exit;
            } else {
                wp_die('PDF file not found.');
            }
        } catch(Exception $e) { 
            echo $e->getMessage();
        }
    }

    if(isset($_GET['download_file'], $_GET['order']) && (isset($_GET['email']) || isset( $_GET['uid']))) {

        if(isset($_GET['override'])) {
            return;
        }

        try {
            global $post;
            global $woocommerce;
            global $product;

            $download_file_var = $_GET['download_file'];
            $order_reference = $_GET['order'];

            $user_id_param = $user_email_param = $security_key_param = "";

            if(isset($_GET['uid'])) {
                $user_id_param = "&uid=" . $_GET['uid'];
            }

            if(isset($_GET['email'])) {
                $user_email_param = "&email=" . $_GET['email'];
            }

            if(isset($_GET['key'])) {
                $security_key_param = "&key=" . $_GET['key'];
                $pdf_file_download_id=$_GET['key'];
            }

            $order_id = (wc_get_order_id_by_order_key(wc_clean(wp_unslash($_GET['order']))));
            
            $current_user = wp_get_current_user();
            $email = $current_user->user_email;

            $pdf_watermark_message = 'Downloaded by ' . $email . ' #' . $order_id;

            $pdf_download_link = get_bloginfo('url') . "/?download_file={$download_file_var}&order={$order_reference}{$user_email_param}{$user_id_param}{$security_key_param}&override=true";
           
            $download_pdf_files = get_post_meta($download_file_var, '_downloadable_files', true);
            
            $is_pdf_file = false;

            if (!empty($download_pdf_files)) {
                $download_pdf_files = maybe_unserialize($download_pdf_files);
                
                if (isset($download_pdf_files[$pdf_file_download_id])) {
                    $file_data = $download_pdf_files[$pdf_file_download_id];
                    if (isset( $file_data['file']) && strpos($file_data['file'], 'amazon_s3') !== false) {
                        if( strpos($file_data['file'], '.pdf') !== false ){
                            $is_pdf_file = true;
                        }
                    }
                }
            }
            if(!$is_pdf_file) {
                return;
            }
            
            $current_time = time();

            setcookie("pdf_download_link", $pdf_download_link, time() + (86400 * 30), "/");
            setcookie("pdf_watermark_message", $pdf_watermark_message, time() + (86400 * 30), "/");
            setcookie("email", $email, time() + (86400 * 30), "/");
            setcookie("current_time", $current_time, time() + (86400 * 30), "/");

            $pdf_download_url = site_url()."/download-pdf-file/";
            // Redirect using header
            die(header("Location: " . $pdf_download_url));

        } catch(Exception $e) { return; }
    }

    if (isset($_GET['archive_download_file'])) {
        // Ensure WooCommerce is loaded
        if (!class_exists('WooCommerce')) {
            return; // WooCommerce is not active
        }
        global $wpdb;
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;
          
        // Retrieve query parameters
        $download_id = sanitize_text_field($_GET['archive_download_file']);
        $product_id = absint($_GET['product']);
        $user_id = get_current_user_id();
        
        // Check if user is logged in
        if (!$user_id) {
            wp_die(__('You must be logged in to download files.', 'woocommerce'), __('Download Error', 'woocommerce'), 403);
        }
        
        $download_permission = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions 
                 WHERE download_id = %s AND product_id = %d  AND user_id = %d",
                $download_id,
                $product_id,
                $user_id
            )
        );

        $download_pdf_files = get_post_meta($product_id, '_downloadable_files', true);  

        $is_pdf_file = false;
        $pdf_location;
        if (!empty($download_pdf_files)) {
            $download_pdf_files = maybe_unserialize($download_pdf_files);
            if (isset($download_pdf_files[$download_id])) {
                $file_data = $download_pdf_files[$download_id];
                if (isset( $file_data['file']) && strpos($file_data['file'], 'amazon_s3') !== false) {
                    $aws_s3_shortcode = $file_data['file'];
                    $aws_s3_shortcode_content = (do_shortcode($aws_s3_shortcode));
                    if(!empty($aws_s3_shortcode_content) && isset($aws_s3_shortcode_content) && gpm_check_url($aws_s3_shortcode_content)) {
                        $pdf_location = $aws_s3_shortcode_content;
                    }
                    if( strpos($file_data['file'], '.pdf') !== false ){
                        $is_pdf_file = true;
                    }
                }
            }
        }
        if($is_pdf_file){
            $pdf_watermark_message = 'Downloaded by ' . $email . ' #' . $download_permission->order_id;
            $permission_id=$download_permission->permission_id;
            $parsedUrl = parse_url($pdf_location);
            $path = $parsedUrl['path'];
            $fileName = basename($path);
            $pdf_local = downloadPDF($pdf_location, time().'_'.$fileName);
            if (!empty($pdf_local)) {
                setcookie("pdf_location", $pdf_location, time() + (86400 * 30), "/");
                setcookie("order_permission", $permission_id, time() + (86400 * 30), "/");
                setcookie("downloadPdfFilePath", $pdf_local, time() + (86400 * 30), "/");
                setcookie("pdfWatermark", $pdf_watermark_message, time() + (86400 * 30), "/");
                setcookie("pdfFileName", $fileName, time() + (86400 * 30), "/");
                wp_redirect('/my-account/downloads/');
                exit;
            } else {
                wp_die('PDF file not found.');
            }
        } else {
            return;
        }
    }
}

// Register the AJAX action for logged-in users
add_action('wp_ajax_delete_pdf_file', 'delete_pdf_file');

function delete_pdf_file() {
    // Verify the request for security
    if (!isset($_POST['fileUrl']) || !current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized request', 403);
    }

    // Decode and sanitize the file URL
    $fileUrl = esc_url_raw($_POST['fileUrl']);
    $filePath = str_replace(home_url(), ABSPATH, $fileUrl);

    // Check if the file exists and delete it
    if (file_exists($filePath)) {
        unlink($filePath);
        wp_send_json_success('File deleted successfully.');
    } else {
        wp_send_json_error('File not found.');
    }
}

// Register the cron job
function register_pdf_cleanup_cron() {
    if (!wp_next_scheduled('cleanup_pdf_files_event')) {
        wp_schedule_event(time(), 'every_five_minutes', 'cleanup_pdf_files_event');
    }
}
add_action('wp', 'register_pdf_cleanup_cron');

// Clear scheduled event on plugin deactivation
function clear_pdf_cleanup_cron() {
    $timestamp = wp_next_scheduled('cleanup_pdf_files_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'cleanup_pdf_files_event');
    }
}
register_deactivation_hook(__FILE__, 'clear_pdf_cleanup_cron');

// Add a custom interval for cron jobs
function add_five_minute_cron_interval($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => 300, // 300 seconds = 5 minutes
        'display'  => __('Every 5 Minutes'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'add_five_minute_cron_interval');

// Hook into the cron job
add_action('cleanup_pdf_files_event', 'delete_old_pdf_files');

function delete_old_pdf_files() {
    // Get the WordPress uploads directory
    $uploadDir = wp_upload_dir();
    $pdfDir = $uploadDir['basedir'] . '/pdf_files/';

    // Check if the directory exists
    if (!is_dir($pdfDir)) {
        return;
    }

    // Get all PDF files in the directory
    $pdfFiles = glob($pdfDir . '*.pdf');

    // Current time
    $currentTime = time();

    foreach ($pdfFiles as $filePath) {
        // Get the file's last modified time
        $fileLastModified = filemtime($filePath);

        // Check if the file is older than 5 minutes
        if (($currentTime - $fileLastModified) > 300) {
            // Delete the file
            unlink($filePath);
        }
    }
}