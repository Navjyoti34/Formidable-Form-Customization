<?php

function send_out_review_email($email, $template_name, $additionalmergedVariables = array(), $test_email = True) {
	global $c;
	echo INCLUDE_OMEDA();
	$avail_templates = json_decode(get_option('transactional_emails_manager_templates'), true);

	if(!$avail_templates[$template_name]){
		return false;
	}

	$first_name = $additionalmergedVariables[ 'first_name'] ?? 'John';
	$last_name = $additionalmergedVariables['last_name'] ?? 'Smith';
	$trackId  = $avail_templates[$template_name]['track_id'];
	$html_content = file_get_contents($avail_templates[$template_name]['file']);
	$email_subject = stripslashes($avail_templates[$template_name]['email_subject']);

	if($test_email) {
		$mergedVariables[0] = ['product_name' => 'Test Product', 'product_url'=>'https://www.interweave.com/product/vintage-pink-cardigan-knitting-pattern-download/', 'product_image_url'=> 'https://www.interweave.com/wp-content/uploads/2008/03/EP0076.jpg.webp'];
		$template_name = str_replace("_", " ", ucwords($template_name, "_"));
		$current_site_url = get_site_url();
		$site_name = get_bloginfo('name');
		$date_sent = (new DateTime(null, new DateTimeZone('America/New_York')))->format('F j, Y @ g:i:s A');

		$default_message = '<p><span style="color: red; font-weight: bold;">Attention</span>: <strong>This is a test email that was sent out on ' . $date_sent . ' manually using the <a href="' . $current_site_url . '/wp-admin/admin.php?page=transactional_emails_manager" target="_blank">Transactional Emails Manager</a> page of <a href="' . $current_site_url . '" target="_blank">' . $site_name . '</a>. If you are experiencing issues with image display, please ensure that your email client is not blocking external images. If the issue persists, <i>double-check</i> that all images in the template are correctly linked. Thank you for your attention. Below you will find the contents of the "' . $template_name . '" template.</strong></p>';

		$email_subject = '[OMEDA] ' . $email_subject . ' [EMAIL TEST]';
		$html_content = $default_message . $html_content;
	} else {
        $mergedVariables[0] = $additionalmergedVariables;
    }

	$fields = array(
		'TrackId' => $trackId,
		'EmailAddress' => $email,
		'FirstName' => $first_name,
		'LastName' => $last_name,
		'Subject' => $email_subject,
		'HtmlContent' => $html_content,
		'Preference' => 'HTML',
		'MergeVariables' => $mergedVariables
   	);
     
	$omedaCall = omedaCurl("{$c('ON_DEMAND_ENDPOINT')}{$c('OMEDA_DIRECTORY')['send_email']}", json_encode($fields));
    // var_dump($fields,$omedaCall);
    // die;

	return $omedaCall;
}
// Create custom tables on plugin activation
register_activation_hook( __FILE__, 'product_review_reminder_emails_install' );

function product_review_reminder_emails_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_review_reminder_emails_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id int UNSIGNED NOT NULL,
        product_id int UNSIGNED NOT NULL,
        email_sent_status varchar(20) NOT NULL,
        timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        send_on datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY order_id_product_id (order_id, product_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

//Prevention code to create custom table in case if the plugin is already activated.
add_action('admin_init', function () {
    if (! get_option('product_review_reminder_emails_log')) { 
        product_review_reminder_emails_install();
        update_option('product_review_reminder_emails_log', true);
    }
});

// Hook into WooCommerce order completion
add_action('woocommerce_order_status_completed', 'schedule_product_review_reminder_email');

function schedule_product_review_reminder_email($order_id) {
    // Get the order object
    $order = wc_get_order($order_id);
    $user_id = $order->get_customer_id();

    // Get the products in the order
    $items = $order->get_items();
    $course['anytime-course'] = ['on-demand-courses-video-featured-products'];
    $course['digital-n-product'] = ['digital-magazines', 'artists-magazine-digital', 'special-issues', 'pastel-journal-digital', 'southwest-art-digital', 'watercolor-artist-digital', 'additional-magazines-digital', 'digital-magazine-featured-products', 'video-featured-products', 'video', 'subject', 'abstract-subject', 'animals-wildlife-subject', 'building-architecture-subject', 'color-doodle-subject', 'figure-subject', 'still-life-floral-subject', 'landscape-subject', 'plein-air-subject', 'portraits-people-subject', 'seascape-subject', 'art-media', 'acrylic-art-media', 'colored-pencil-art-media', 'drawing-sketching-art-media', 'mixed-media-art-media', 'pastel-art-media', 'oil-art-media', 'watercolor-art-media'];
    $course['print'] = ['print-magazines', 'artists-magazine-print', 'southwest-art-print', 'pastel-journal-print', 'watercolor-artist-print', 'additional-magazine-print', 'print-magazine-featured-products'];
    $course['interactive-course'] = ['paint-along','paint-along-video-featured-products','interactive-workshop'];
    //$course['paint-along'] = ['paint-along','paint-along-video-featured-products'];

    // Loop through order items to check if any of them match the target product
    foreach ($items as $item) {
        $product_id = $item->get_product_id();
        $product = wc_get_product($product_id);
        $work_shop_enddate = $product->get_attribute('Workshop End date');
        $prod_categories = get_product_category_slugs($product_id);
        $reminder = '';
        $reminder_on_course_end = false;
        //Check if product belongs to Digital & Product
        if(!empty( $work_shop_enddate )){
            $reminder = date('Y-m-d', strtotime($work_shop_enddate)) . ' +1 day';              
        }elseif (!empty(array_intersect($prod_categories, $course['digital-n-product']))) {
            //set reminder to 7+ days
            $reminder = '+7 days';
        } elseif(!empty(array_intersect($prod_categories, $course['anytime-course']))){ // Check if product belongs to Anytime Course.
            $reminder = '+3 weeks';
        } elseif(!empty(array_intersect($prod_categories, $course['print']))){ // Check if product belongs to Print.
            $reminder = '+5 weeks';
        /*} elseif(!empty(array_intersect($prod_categories, $course['paint-along']))){ // check if product belongs to paint-along.
            update_user_meta( $user_id, '_product_review_reminder_sent_' . $product_id, ['order_id'=>$order_id, 'type'=>'paint-along']);
            $reminder_on_course_end = true;
            $reminder = '+2 months';*/
        } elseif(!empty(array_intersect($prod_categories, $course['interactive-course']))){ // check if product belongs to Interactive Course.
            //get associated courses with the product.
			$courses = get_post_meta($product_id, '_related_course');
			if ( is_array( $courses ) ) {
				foreach($courses[0] as $course_id ) {
					update_user_meta( $user_id, '_product_review_reminder_sent_' . $course_id, [ 'product_id' => $product_id, 'order_id' => $order_id, 'type' => 'interactive-course' ] );		
				}
			} else {
				update_user_meta( $user_id, '_product_review_reminder_sent_' . $product_id, ['order_id'=>$order_id, 'type'=>'interactive-course']);
			}
            
            $reminder = '+1 day';  
            $reminder_on_course_end = true;
        }
        // Check if this product matches the target product
        if (!empty($reminder)) {
            if (strpos($reminder, 'minutes') !== false) {            
                $send_time = time() + ((int) filter_var($reminder, FILTER_SANITIZE_NUMBER_INT) * 60);
            } else {
                $send_time = strtotime($reminder, time());
            }
            wp_schedule_single_event($send_time, 'send_product_review_reminder_email_event', array($order_id, $product_id));
            update_post_meta($order_id, '_product_review_reminder_sent_' . $product_id, true);
            log_product_review_reminder_email_status($order_id, $product_id, 'pending', $send_time);
        }
    }
}

// Callback function to send the product review reminder email
function send_product_review_reminder_email_callback($order_id, $product_id) {
    global $wpdb;
     // Get the order object
     $order = wc_get_order($order_id); 
     $product = wc_get_product($product_id);
 
     if(!$order || !$product ) {
         return false;
     }
      // Get the customer's email
     $email = $order->get_billing_email();   
     $user = get_user_by('email', $email);
     $user_id = $user->ID;   
     $email_sent = get_user_meta($email,'_product_review_reminder_sent_user' . $order_id, true);
     if ($email_sent) {
         error_log("Skipping email: User $user_email has already received a review request.");
         return;
     }
     $findGifts = $wpdb->get_results($wpdb->prepare(
         "SELECT * FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_key LIKE '_gft_p_%%' AND (meta_value = %d OR meta_value = %d)",
         $order_id,
         $product_id,
         $product_id
 
     ));
     
     foreach ($findGifts as $giftMeta) {
         $checkID = explode("_", $giftMeta->meta_key);
         $checkIDSplit = $checkID[count($checkID) - 2];
         $uniqID = end($checkID);
         $grabShippingData = get_post_meta($order_id, "_gft_sd_{$checkIDSplit}_{$uniqID}", true);
         
         if (!empty($grabShippingData)) {
             $shippingData = json_decode($grabShippingData);
             if (!empty($shippingData->email_address)) {
                 $gifteeEmail = sanitize_email($shippingData->email_address);
                 break; // Stop at the first valid giftee email
             }
         }
     }
   
     if ($product) {
         $additionalmergedVariables['first_name'] = $order->get_billing_first_name();
         $additionalmergedVariables['last_name'] = $order->get_billing_last_name();
         $additionalmergedVariables['product_name'] = $product->get_name(); // Get product name
         $additionalmergedVariables['product_url'] = get_permalink($product_id).'#tab-reviews'; // Get product page URL
         $additionalmergedVariables['product_image_url'] = wp_get_attachment_image_src(get_post_thumbnail_id($product_id), 'medium')[0]; // Get product image URL
         $additionalmergedVariables['confirmunsubscribelink'] = '';
         $additionalmergedVariables['preferencepagelink'] = '';
 
     }
     $recipientEmail = !empty($gifteeEmail) ? $gifteeEmail : $email;
     $send_payload_to_omeda = send_out_review_email($recipientEmail, 'ratings_reviews', $additionalmergedVariables, false); 
     if ($send_payload_to_omeda[1] == 200) {
         update_user_meta($user_id, '_product_review_reminder_sent_user' . $order_id, true);
         log_product_review_reminder_email_status($order_id, $product_id, 'sent');
     } else {
         log_product_review_reminder_email_status($order_id, $product_id, 'in progress');
     }
 }

// Hook into the scheduled event
add_action('send_product_review_reminder_email_event', 'send_product_review_reminder_email_callback', 10, 2);

// Function to log the product review reminder email status
function log_product_review_reminder_email_status($order_id, $product_id, $status, $timestamp = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_review_reminder_emails_log';
    $send_on = (!empty($timestamp) && is_numeric($timestamp)) ? date('Y-m-d H:i:s', (int)$timestamp) : null;

    // Check if the order and product already exist in the log table
    $existing_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE order_id = %d AND product_id = %d", $order_id, $product_id));

    if ($existing_entry) {
        $update_values = [
            'email_sent_status' => $status,
            'timestamp' => current_time('mysql'),
        ];
        if ($send_on) {
            $update_values['send_on'] = $send_on;
        }

        // Update the existing entry
        $wpdb->update(
            $table_name,
            $update_values,
            array('order_id' => $order_id, 'product_id' => $product_id)
        );
    } else {
        // Insert a new entry
        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'product_id' => $product_id,
                'email_sent_status' => $status,
                'timestamp' => current_time('mysql'),
                'send_on' => $send_on,
            )
        );
    }
}


// Function to display the log in admin page/dashboard
function product_review_reminder_emails_log_page() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'product_review_reminder_emails_log';
    $items_per_page = 20;

    // Get the current page
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    // Calculate the offset for the query
    $offset = ($current_page - 1) * $items_per_page;
    // Get the total number of items
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    // Fetch the rows for the current page
    $results = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_name ORDER BY id DESC LIMIT %d OFFSET %d", $items_per_page, $offset),
        ARRAY_A
    );
    // Set up pagination arguments
    $pagination_args = array(
        'base'      => add_query_arg('paged', '%#%', admin_url('admin.php?page=transactional_emails_manager#ratingnreview')),
        'format'    => '&paged=%#%',
        'current'   => $current_page,
        'total'     => ceil($total_items / $items_per_page),
        'prev_text' => __('« Prev'),
        'next_text' => __('Next »'),
    );

    echo '<div class="wrap">';
    // Display pagination
    echo '<div class="pagination-links tablenav-pages">' . paginate_links($pagination_args) . '</div>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Order ID</th>';
    echo '<th>Product ID</th>';
    echo '<th>Status</th>';
    echo '<th>Schedule Date/Time</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    // Display the rows
    foreach ($results as $row) {
        echo "<tr>";
        echo "<td><a href='" . admin_url('post.php?post=' . $row['order_id'] . '&action=edit') . "'>{$row['order_id']}</a></td>";
        echo "<td><a href='" . get_permalink($row['product_id']) . "'>" . get_the_title($row['product_id']) . "</a></td>";
        echo "<td>{$row['email_sent_status']}</td>";
        echo "<td>{$row['send_on']}</td>";
        echo "</tr>";
    }

    echo '</tbody>';
    echo '</table>';
    // Display pagination again at the bottom
    echo '<div class="pagination-links tablenav-pages bottom">' . paginate_links($pagination_args) . '</div>';
    echo '</div>';
}

/**
 * Get all category slugs of a product by product ID.
 *
 * @param int $product_id The ID of the product.
 * @return array An array of category slugs.
 */
function get_product_category_slugs($product_id) {
    // Get the terms associated with the product in the 'product_cat' taxonomy
    $terms = wp_get_post_terms($product_id, 'product_cat');
    
    // Extract the slugs from the terms
    $category_slugs = array();
    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            $category_slugs[] = $term->slug;
        }
    }
    
    return $category_slugs;
}