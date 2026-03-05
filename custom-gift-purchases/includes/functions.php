<?php

// Quick array search
function a($p, $h) {
    return $p[$h] ?? null;
}

function gfp_gather_gifted_products($order_id) {
    global $wpdb;

    $query = $wpdb->prepare(
        "SELECT meta_value
        FROM wp_postmeta
        WHERE post_id = {$order_id}
        AND meta_key LIKE '_gft_p_%';"
    );

    $results = $wpdb->get_results($query);

    $meta_values_array = array();
    foreach ($results as $result) {
        $meta_values_array[] = $result->meta_value;
    }

    return $meta_values_array;
}

function subtractArraysKeepDuplicates($array1, $array2) {
    $result = [];

    $counts2 = array_count_values($array2);

    foreach ($array1 as $value) {
        if (in_array($value, $array2)) {
            $key = array_search($value, $array2);
            unset($array2[$key]);
        } else {
            $result[] = $value;
        }
    }

    return $result;
}

function gfp_get_order_product_ids($order_id) {
    $order_product_ids = [];

    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item) {
        $gift_product_id = $item->get_product_id();
        $gift_product_obj = wc_get_product($gift_product_id);

        if ($gift_product_obj->is_type('bundle')) {
            foreach ($gift_product_obj->get_bundled_items() as $bundled_item) {
                $bundled_product_id = $bundled_item->get_product_id();
                $order_product_ids[] = $bundled_product_id;
            }
        } else {
            $order_product_ids[] = $gift_product_id;
        }
    }

    return $order_product_ids;
}

function writeContentToFile($content, $filename) {
    $filePath = plugin_dir_path(__FILE__) . $filename;

    // Check if the file already exists
    if (file_exists($filePath)) {
        // Append the content to the existing file
        $fileWriteResult = file_put_contents($filePath, $content, FILE_APPEND);
    } else {
        // Create the file and write the content to it
        $fileWriteResult = file_put_contents($filePath, $content);
    }
}

function gfp_process_learndash_gifts($order_id, $on_thank_you = true) {
    global $wpdb;

    // Fetch the Learndash gift data
    $learndash_gift = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM gpm_gfp_learndash_permissions WHERE order_id = %d",
            $order_id
        )
    );

    if ($learndash_gift) {
        $gifter_user_id = gift_get_user_by_order_id($order_id)->ID;

        // Prepare an array of product IDs from the order
        $order_product_ids = gfp_get_order_product_ids($order_id);

        // Gather product IDs of gifted products
        $order_gifted_product_ids = gfp_gather_gifted_products($order_id);

        // Identify product IDs that should be removed
        $product_id_removal_keys = subtractArraysKeepDuplicates(
            $order_product_ids,
            $order_gifted_product_ids
        );

        // Unserialize the gifted courses
        $courses_being_gifted = unserialize($learndash_gift->courses);

        // Fetch courses the gifter currently has access to
        $courses_gifter_has = learndash_get_user_course_access_list($gifter_user_id);

        // Initialize an array to keep courses gifter should remain enrolled
        $courses_gifter_keep_enrolled = [];

        $order = wc_get_order($order_id);

        foreach ($courses_being_gifted as $course_id) {

            if (get_post_type($course_id) === 'sfwd-courses') {
                $target_user_id = get_user_by('email', $learndash_gift->user_email);

                if ($target_user_id) {
                    $gift_activation_time = strtotime(date('Y-m-d', strtotime($learndash_gift->activation_date)));
                    $current_time = strtotime(date('Y-m-d'));

                    if(!($gift_activation_time == $current_time)) {
                        if(!($gift_activation_time < $current_time)) {
                            continue;
                        }
                    }

                    ld_update_course_access($target_user_id->ID, $course_id, false);

                    $wpdb->query($wpdb->prepare(
                        "UPDATE gpm_gfp_learndash_permissions
                        SET gifted = '1'
                        WHERE id = %d",
                        $learndash_gift->id
                    ));
                }

                if(!$on_thank_you) {
                    continue;
                }

                if (!in_array($course_id, $courses_gifter_has)) {
                    // Course ID not in gifter courses, check if it should be removed

                    if(!empty($product_id_removal_keys)) {
                        $post_ids_string = implode(', ', $product_id_removal_keys);

                        $query = $wpdb->prepare(
                            "SELECT meta_value FROM {$wpdb->postmeta}
                            WHERE post_id IN ({$post_ids_string}) AND meta_key = '_related_course'"
                        );

                        $results = $wpdb->get_results($query);

                        if ($results) {
                            foreach ($results as $result) {
                                $meta_value = unserialize($result->meta_value);

                                if (in_array($course_id, $meta_value)) {
                                    $courses_gifter_keep_enrolled[] = $course_id;
                                }
                            }
                        }
                    }
                }

                if (!(in_array($course_id, $courses_gifter_keep_enrolled))) {
                    $ld_course_assigned = ld_course_access_from($course_id, $gifter_user_id);

                    if($ld_course_assigned != 0) {
                        $default_server_timezone = date_default_timezone_get();
                        $date_order_created_timestamp = strtotime($order->get_date_created());
                        $server_date_order_created = (new DateTime)->setTimestamp($date_order_created_timestamp)->setTimezone(new DateTimeZone(date_default_timezone_get()));
                        $server_date_order_created_timestamp = $server_date_order_created->getTimestamp();

                        $course_access_date = ld_course_access_from($course_id, $gifter_user_id);

                        $difference_between_course_access_and_order_creation = $course_access_date - $server_date_order_created_timestamp;

                        if ($difference_between_course_access_and_order_creation >= 0 && $difference_between_course_access_and_order_creation <= 25) {
                            ld_update_course_access($gifter_user_id, $course_id, true);
                        }
                    }
                }
            }
        }
    }
}

function gfp_update_non_subscription_memberships() {
    global $wpdb;

    $giftMemberships = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT meta_key, meta_value, post_id
            FROM {$wpdb->postmeta}
            WHERE `meta_key` LIKE %s",
            '%' . $wpdb->esc_like('_gft_p_') . '%'
        )
    );

    if (!empty($giftMemberships)) {
        foreach ($giftMemberships as $giftMembership) {
            $post_id = $giftMembership->post_id;

            //gift_process_non_subscription_memberships($post_id);
        }
    }
}

function gift_order_confirmation_action($order_id) {
    //gift_process_non_subscription_memberships($order_id);
}

function gift_get_user_by_order_id($order_id) {
    $order = wc_get_order($order_id);

    if ($order) {
        $user_id = $order->get_customer_id();

        if ($user_id) {
            $user = get_user_by('ID', $user_id);

            if ($user) {
                return $user;
            }
        }
    }

    return False;
}

function gift_on_order_status_change($order_id, $old_status, $new_status, $order)
{
    if ($new_status === 'completed') {
        //gift_process_non_subscription_memberships($order_id);
    }
}

// Process memberships that are not tied to a subscription.
function gift_process_non_subscription_memberships($order_id) {
    global $wpdb;

    $meta_key = "gpm_gift_membership_completed";
    $gift_contains_membership = get_post_meta($order_id, $meta_key, true);
    if ($gift_contains_membership) {
        return;
    }
    $order = wc_get_order($order_id);  
    if ($order->get_status() === 'on-hold') {
        return;
    }
    $straight_memberships = [];
    $giftMemberships = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT meta_key, meta_value
            FROM {$wpdb->postmeta}
            WHERE `meta_key` LIKE %s
            AND `post_id` = %d",
            '%' . $wpdb->esc_like('_gft_p_') . '%',
            $order_id
        )
    );
    foreach ($giftMemberships as $gift) {
        $gift_uniq_id = $gift->meta_key; 
        $gift_product_id = $gift->meta_value; 
        $parent_id = wp_get_post_parent_id($gift_product_id); 
        $product_id_to_check = $parent_id ? $parent_id : $gift_product_id;    
        $last_part = end(explode('_gft_p_', $gift_uniq_id));
        if (!$last_part) {
            continue;
        }    
        $meta_key = "_gft_sd_" . $last_part;
        $gift_shipping = get_post_meta($order_id, $meta_key, true);    
        if ($gift_shipping === false || $gift_shipping === '') {
            continue;
        }    
        $gift_future_dated = get_post_meta($order_id, "_gft_fd_" . $last_part, true) ?? time();
        if ($gift_future_dated > time()) {
            continue;
        }    
        $gift_shipping = json_decode($gift_shipping, true);
        $giftee_email_address = $gift_shipping['email_address'];    
        $md5 = md5($product_id_to_check . $order_id . $giftee_email_address . $gift_future_dated);
        $captcher_giftee_user = get_user_by('email', $giftee_email_address) ?: get_user_by('id', get_option('gifter_user_made'));
    
        $query_for_membership_id = $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id
            FROM {$wpdb->prefix}postmeta pm
            WHERE pm.meta_key = '_order_id' AND pm.meta_value = %d
              AND EXISTS (
                SELECT 1
                FROM {$wpdb->prefix}postmeta pm2
                WHERE pm2.post_id = pm.post_id
                  AND pm2.meta_key = '_product_id' AND (pm2.meta_value = %d OR pm2.meta_value = %d)
              )
            LIMIT 1;",
            $order_id,
            $gift_product_id,
            $product_id_to_check
        ));    
        if (!$query_for_membership_id) {
            $user_id = gift_get_user_by_order_id($order_id)->ID;
            $user_1_memberships = wc_memberships_get_user_memberships($user_id);
            $user_2_memberships = wc_memberships_get_user_memberships($captcher_giftee_user->ID);
    
            $user_1_plan_ids = array_map(fn($membership) => $membership->get_plan_id(), $user_1_memberships);
            $user_2_plan_ids = array_map(fn($membership) => $membership->get_plan_id(), $user_2_memberships);
            $common_plan_ids = array_intersect($user_1_plan_ids, $user_2_plan_ids);
    
            $user_memberships = array_filter($user_1_memberships, fn($membership) => in_array($membership->get_plan_id(), $common_plan_ids));
    
            usort($user_memberships, fn($a, $b) => strtotime($b->get_start_date()) - strtotime($a->get_start_date()));
    
            if ($user_memberships) {
                foreach ($user_memberships as $membership) {
                    $membership_plan_id = $membership->get_plan_id();
                    $membership_id = $membership->get_id();
    
                    $query_for_product_ids = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_product_ids' LIMIT 1",
                        $membership_plan_id
                    ));
    
                    if ($query_for_product_ids) {
                        $meta_key = $query_for_product_ids->meta_key;
                        $meta_value = maybe_unserialize($query_for_product_ids->meta_value);
                        
                        if (in_array($product_id_to_check, $meta_value)) {
                            if ($user_membership = wc_memberships_get_user_membership($membership_id)) {
                                $giftee_user_id = $captcher_giftee_user->ID;
                                $giftee_username = $captcher_giftee_user->display_name;
                                $gifter_user_made = get_option('gifter_user_made');
    
                                try {
                                    $user_membership->transfer_ownership($giftee_user_id);
    
                                    if ($giftee_user_id != $gifter_user_made) {
                                        gfp_leave_order_notes($order_id, "Membership #{$membership_id} successfully transferred to user {$giftee_username}.");
                                        unset($straight_memberships[$md5]);
                                    } else {
                                        gfp_leave_order_notes($order_id, "Cannot find user to assign to. Membership #{$membership_id} successfully transferred to '{$giftee_username}' until gift is claimed by '{$giftee_email_address}' systematically.");
                                    }
                                } catch (Exception $e) {
                                    continue;
                                }
                            }
                            break;
                        }
                    }
                }
            }
        }
    
        if ($query_for_membership_id) {
            $membership_held_by = get_post_field('post_author', $query_for_membership_id);
            if ($user_membership = wc_memberships_get_user_membership($query_for_membership_id)) {
                $giftee_user_id = $captcher_giftee_user->ID;
                $giftee_username = $captcher_giftee_user->display_name;
    
                try {
                    $user_membership->transfer_ownership($giftee_user_id);
    
                    if ($giftee_user_id != get_option('gifter_user_made')) {
                        gfp_leave_order_notes($order_id, "Membership #{$query_for_membership_id} successfully transferred to user {$giftee_username}.");
                        unset($straight_memberships[$md5]);
                    } else {
                        gfp_leave_order_notes($order_id, "Cannot find user to assign to. Membership #{$query_for_membership_id} successfully transferred to '{$giftee_username}' until gift is claimed by '{$giftee_email_address}' systematically.");
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
    }
    
    $memberships_left_to_gift = count($straight_memberships);
    
    if ($memberships_left_to_gift === 0) {
        update_post_meta($order_id, 'gpm_gift_membership_completed', true);
    }
    
}


// create gfp download permissions table
function gfp_create_order_download_permissions_table() {
    global $wpdb;
    $table_name = 'gpm_gfp_order_download_permissions';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE `$table_name` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `product_id` INT(11) NOT NULL,
            `user_email` VARCHAR(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `order_id` INT(11) DEFAULT NULL,
            `activation_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_unique_product_user` (`product_id`, `user_email`, `order_id`)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// create gfp order table
function gfp_create_learndash_permissions_table() {
    global $wpdb;
    $table_name = 'gpm_gfp_learndash_permissions';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE `$table_name` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `courses` TEXT COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `product_id` INT(11) NOT NULL,
            `user_email` VARCHAR(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
            `order_id` INT(11) DEFAULT NULL,
            `activation_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `gifted` TINYINT(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// update order download permissions
function gfp_update_order_dl_permissions() {
    global $wpdb;

    $findSubProducts = "
        SELECT p.ID, pm.meta_key, pm.meta_value
        FROM wp_posts AS p
        LEFT JOIN wp_postmeta AS pm ON p.ID = pm.post_id
        WHERE (
            p.post_type = 'shop_subscription' OR (
                p.post_type = 'shop_order'
                AND (p.post_status = 'wc-completed' OR p.post_status = 'wc-on-hold')
            )
        )
        AND pm.meta_key LIKE '_gft_sd_%';
    ";

    $results = $wpdb->get_results($findSubProducts);

    if(empty($results)) {
        return;
    }

    foreach ($results as $row) {
        $postID = $row->ID;
        $metaKey = $row->meta_key;
        $metaValue = $row->meta_value;
        
        $email_address = json_decode($metaValue, true)['email_address'];

        $checkID = explode("_", $metaKey);

        if (is_array($checkID) && count($checkID) >= 2) {
            $checkIDSplit = $checkID[count($checkID) - 2];
            $uniqID = end($checkID);

            $grabGiftData = get_post_meta($postID, "_gft_p_{$checkIDSplit}_{$uniqID}", true);

            if ($grabGiftData) {
                $table_name = 'gpm_gfp_order_download_permissions';
                
                $wpdb->query(
                    $wpdb->prepare(
                        "INSERT IGNORE INTO $table_name (product_id, user_email, order_id) VALUES (%d, %s, %d)",
                        $grabGiftData,
                        $email_address,
                        $postID
                    )
                );
            }
        }
    }
}

// add event that checks gifts being scheduled
function setup_gift_scheduling_event() {
    if(!wp_next_scheduled('process_gift_scheduled_orders_hook')) {
        wp_schedule_event(time(), 'hourly', 'process_gift_scheduled_orders_hook');
    }
}

// when cart item is removed - delete cookie
function custom_cart_item_removed_callback($cart_item_key, $cart) {
    $cookie_name = 'ggp-'. $cart_item_key;
    setcookie($cookie_name, '', time() - 3600, '/');
    unset($_COOKIE[$cookie_name]);
}

// a notification for checking the scheduled gifts
function add_gift_general_notification() {
    if ( isset( $_GET['post_type'] ) && $_GET['post_type'] == 'shop_order' ) {
        $message = "Gift for peak check scheduled orders hook not setup as of right now.";
        if ( $timestamp = wp_next_scheduled( 'process_gift_scheduled_orders_hook' ) ) { // update this - hook isn't be called hourly
            $datetime_utc = new DateTime();
            $datetime_utc->setTimestamp( $timestamp );
            $datetime_utc->setTimezone( new DateTimeZone( 'UTC' ) );
            $datetime_est = clone $datetime_utc;
            $datetime_est->setTimezone( new DateTimeZone( 'America/New_York' ) );
            $date_string = $datetime_est->format( 'Y-m-d h:i:s A' );
            $message = 'The next scheduled orders check for Gift for peak is scheduled to run @ ' . $date_string . ' EST.';
        }
        echo "<div class='notice notice-warning'><p>$message</p></div>";
    }
}

// Checks the user's meta to be sure whether the account can change their username
function check_user_meta() {
    $user_id = get_current_user_id();
    $meta_key = 'gift_for_peak_can_change_username';
    $meta_value = get_user_meta($user_id, $meta_key, true);

    if($meta_value === 'true') {
        $current_url = home_url(add_query_arg(NULL, NULL));

        if(strpos($current_url, '/my-account/edit-account/') === false) {
            die(header('Location: /my-account/edit-account/'));
        }
    }
}

// Add the custom CSS style to account details page
function add_custom_styles_to_account_details() {
    wp_enqueue_style('gpm-account-details-style', plugin_dir_url(__FILE__) . '../assets/css/gpm-account-details-style.css?' . time());
}

// Add the ability to edit an account's username on the accounts details page
function add_ability_to_edit_username_account_form() {
    $user_id = get_current_user_id();
    $meta_key = 'gift_for_peak_can_change_username';
    $meta_value = get_user_meta($user_id, $meta_key, true);

    if ($meta_value !== 'true') {
        $meta_data = array(
            'highlight_input' => '',
            'disable_input' => 'disabled',
            'hide_notification' => 'd-none',
            'additional_input_verbiage' => ' and isn\'t changeable',
       );
    } else {
        $meta_data = array(
            'highlight_input' => 'highlighted',
            'disable_input' => '',
            'hide_notification' => '',
            'additional_input_verbiage' => '',
       );
    }

    ?>
    <div class="notification info <?php echo esc_attr($meta_data['hide_notification']); ?>">Your account has been created with the username below. You can change it or keep it, but please remember to click "Save Changes" to continue.</div>
    <div class="notification info <?php echo esc_attr($meta_data['hide_notification']); ?>">Your password has been generated automatically. It is recommended that you consider changing it too.</div>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="username"><?php _e('Username', 'woocommerce'); ?> <span class="required">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text <?php echo esc_attr($meta_data['highlight_input']); ?>" name="username" id="username" value="<?php echo esc_attr(wp_get_current_user()->user_login); ?>" <?php echo esc_attr($meta_data['disable_input']); ?> />
        <span><em>This will be used to log into your <?php echo esc_attr(get_bloginfo('name')); ?> account<?php echo esc_attr($meta_data['additional_input_verbiage']); ?>.</em></span>
    </p>
    <?php
}

// Process a username change
function save_username_change($user_id) {
    global $wpdb;

    $meta_key = 'gift_for_peak_can_change_username';
    $meta_value = get_user_meta($user_id, $meta_key, true);

    if($meta_value === 'false') {
        return;
    }

    if(isset($_POST['username'])) {
        $username_sent = sanitize_text_field($_POST['username']);
        $current_username = get_userdata($user_id)->user_login;

        if($username_sent != $current_username) {
            if (!preg_match('/^[^0-9][a-zA-Z0-9]{5,17}$/', $username_sent)) {
                wc_add_notice( 'Sorry, the username you entered does not meet the required criteria. Usernames must be between 6 and 18 characters in length, and can only contain letters and numbers. Usernames cannot begin with a number, and must be unique.', 'error' );
                return;
            }

            $check_username_exists = username_exists($username_sent);

            if($check_username_exists) {
                wc_add_notice( 'It looks like that username is already registered to another account.', 'error' );
                return;
            }
        }

        $wpdb->update($wpdb->users, array('user_login' => $username_sent), array('ID' => $user_id));
        update_user_meta($user_id, $meta_key, 'false');
    }
}

// Determin whether each product needs shipping
function maybe_each_product_needs_shipping($is_virtual, $product) {
    return !$product->is_virtual();
}

// Woocommerce checks cache first, which can cause issues, so keep it clear
function clear_user_woo_cache($user_id = null) {
    global $woocommerce, $product, $post;
    
    try {
        if(null == $user_id && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }

        if($user_id == 0) {
            return false;
        }

        if (class_exists('WCS_Customer_Store')) {
            WCS_Customer_Store::instance()->delete_cache_for_user($user_id);
        }
    } catch (Exception $e) { }
}

// Remove duplicate errors found on checkout process
function remove_duplicate_errors( $data, $errors ) {
    $wc_notices = (array) WC()->session->get( 'wc_notices' );
    
    if ( ! empty( $errors->get_error_codes() ) ) {
        foreach ( $errors->get_error_codes() as $code ) {
            $notices = $errors->get_error_messages( $code );
            foreach ( $notices as $notice ) {
                if (! in_array( $notice, $wc_notices ) ) {
                    $errors->remove( $code, $notice );
                }
            }
        }
    }
}

// Update post meta key
function update_meta_key($old_key=null, $new_key=null){
    global $wpdb;

    $query = "UPDATE ".$wpdb->prefix."postmeta SET meta_key = '".$new_key."' WHERE meta_key = '".$old_key."'";
    $results = $wpdb->get_results($query, ARRAY_A);

    return $results;
}

// Add a custom hidden field to checkout
function add_custom_checkout_hidden_field($checkout) {
    echo '<div id="gift-hidden-fields">';
    echo '
        <div id="gift-purchase-hidden-field">
            <input type="hidden" class="input-hidden" name="physical_gift_purchase" id="physical_gift_purchase" value="">
        </div>
        <div id="gift-purchase-list-hidden-field">
            <input type="hidden" class="input-hidden" name="gift_purchase_list" id="gift_purchase_list" value="">
        </div>
    ';

    $current_user = wp_get_current_user();

    if ($current_user && $current_user->ID !== 0) {
        $email = $current_user->user_email;

        echo '
            <div id="gift-current-email-hidden-field">
                <input type="hidden" class="input-hidden" name="gift_current_user_email" id="gift-current-user-email" value="' . $email . '">
            </div>
        ';
    }
    echo '</div>';
}

// Unsetting required fields - override function below
function required_field_override($fields) {
    foreach (SHIPPING_BILLING_FIELDS as $type => $fields_array) {
        foreach ($fields_array as $field) {
            if (isset($fields[$type . '_' . $field])) {
                unset($fields[$type . '_' . $field]['required']);
            }
        }
    }

    return $fields;
}

// Custom Woocommerce form validation
function cust_woocommerce_form_validation() {
    $payment_method = $_POST['payment_method'];

    if(!isset($payment_method)) {
        return;
    }

    if(!($payment_method == 'paypal_express')) {

        $total_items = WC()->cart->get_cart_contents_count();

    	foreach (SHIPPING_BILLING_FIELDS['billing'] as $field) {
    		if(empty($_POST[$field])) {
    			$field_ = ucwords(str_replace("_", " ", preg_replace('/[0-9]+/', '', $field)));
    			wc_add_notice(__("<strong>{$field_}</strong> is a required field."), 'error');
    		}
    	}

        if (!empty($_POST['gift_purchase_list'])) {
            $gift_items = explode(',', $_POST['gift_purchase_list']); // Convert string to array
            $gift_count = count($gift_items); // Count the elements in the array
        }

    	foreach (SHIPPING_BILLING_FIELDS['shipping'] as $field) {
    		if(!empty($_POST['physical_gift_purchase']) && ($_POST['physical_gift_purchase'] === 'true') && !empty($_POST['gift_purchase_list']) && $gift_count == $total_items ) {
    			$_POST[$field] = '';
    		} else {
    			print_r($_POST[SHIPPING_BILLING_FIELDS['billing'][array_search($field, SHIPPING_BILLING_FIELDS['billing'])]]);
    			if(!(isset($_POST['ship_to_different_address']) && $_POST['ship_to_different_address'] === true)) {
    				if (empty($_POST[$field])) {
    				    $_POST[$field] = $_POST[SHIPPING_BILLING_FIELDS['billing'][array_search($field, SHIPPING_BILLING_FIELDS['billing'])]];
    				}
    			}

    			if(empty($_POST[$field])) {
    				$field_ = ucwords(str_replace("_", " ", preg_replace('/[0-9]+/', '', $field)));
    				wc_add_notice(__("<strong>{$field_}</strong> is a required field."), 'error');
    			}
    		}
    	}
    }
}

// Proccess add of meta box in admin area
function process_order_meta_box() {
    add_meta_box('gift_order_shipping_addresses', 'Orders as Gifts Information', 'order_meta_box', 'shop_order', 'advanced', 'high');
}

// Meta box contents to add in admin area
function order_meta_box() {
    global $post, $wpdb;

    $f = 'a';
    $productID = $post->ID;

    $findProducts = $wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->prefix}postmeta WHERE `meta_key` LIKE '%%_gft_p_%%' AND `post_id` = '{$productID}'");

    echo '<style>#gift_order_shipping_addresses td:empty {display: none !important} #gift_order_shipping_addresses tr {display: block !important}</style><div id="order_data" class="panel woocommerce-order-data"><div class="order_data_column_container" style="display: flex;justify-content: left;align-items: center;flex-wrap: wrap;margin: 0 auto;">';

    foreach($findProducts as $product) {
        $productKey = $product->meta_key;
        $productOrder = $product->post_id;
        $product_ID = $product->meta_value;
        $productObj = wc_get_product($product_ID);

        $productShippingDetails = str_replace("_gft_p_", "_gft_sd_", $productKey);
        $productDateSendDetails = str_replace("_gft_p_", "_gft_fd_", $productKey);
        $productNotifyDetails = str_replace("_gft_p_", "_gft_notify_", $productKey);

        $productDateSend = get_post_meta($productOrder, $productDateSendDetails, true);
        $sendVerbiage = 'Gift to be s';

        if(!$productDateSend) {
            $productDateSendDetails = str_replace("_gft_p_", "_gft_df_", $productKey);
            $productDateSendJSON = json_decode(get_post_meta($productOrder, $productDateSendDetails, true), true);
            $sendVerbiage = 'Gift s';

            $productDateSend = $productDateSendJSON['future_date'];
        }

        $productName = $productObj->get_title();
        $productLink = $productObj->get_permalink();
        
        $productShipping = get_post_meta($productOrder, $productShippingDetails, true);
        $productNotify = get_post_meta($productOrder, $productNotifyDetails, true);

        $productDateSendReadable = date('l, Y-m-d', strtotime('@' . $productDateSend));

        $gifteeNotified = 'No';

        if($productNotify) {
            $gifteeNotified = 'Yes';
        }

        $productShippingDetailsDecode = json_decode($productShipping, true);

        $gifteeEmailDetails = strtolower($productShippingDetailsDecode['email_address']);

        $user = get_user_by('email', $gifteeEmailDetails);

        $gifteeRegistered = 'No';

        if ($user) {
            $gifteeRegistered = '<a href="/wp-admin/user-edit.php?user_id=' . $user->ID . '" target="_blank">Yes</a>';
        }

        echo <<<EOT
            <div class="order_data_column" style="width: auto !important; padding: 5px !important;">
                <table style="border:1px solid #c3c4c7;padding-right:10px;padding-left:10px;height:100% !important;">
                    <tbody>
                        <tr>
                            <td><strong><a href="{$productLink}" target="_blank">{$productName}</a> x 1</strong><br /></td>
                        </tr>
                        <tr>
                            <td><strong>Giftee registered:</strong> {$gifteeRegistered}<br /></td>
                        </tr>
                        <tr>
                            <td><strong>Giftee notified:</strong> {$gifteeNotified}<br /></td>
                        </tr>
                        <tr>
                            <td><strong>{$sendVerbiage}ent:</strong> {$productDateSendReadable} <br /><br /></td>
                        </tr>
                        <tr>
                            <td>{$f($productShippingDetailsDecode, 'first_name')} {$f($productShippingDetailsDecode, 'last_name')}</td>
                        </tr>
                        <tr>
                            <td>{$gifteeEmailDetails}</td>
                        </tr>
                        <tr>
                            <td>{$f($productShippingDetailsDecode, 'company_name')}</td>
                        </tr>
                        <tr>
                            <td>{$f($productShippingDetailsDecode, 'street_address')}</td>
                        </tr>
                        <tr>
                            <td>{$f($productShippingDetailsDecode, 'street_second_address')}</td>
                        </tr>
                        <tr>
                            <td>{$f($productShippingDetailsDecode, 'city_name')} {$f($productShippingDetailsDecode, 'state_name')} {$f($productShippingDetailsDecode, 'zip_code')}</td>
                        </tr>
                        <tr>
                            <td>{$f($productShippingDetailsDecode, 'country_name')}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
EOT;
    }

    echo '</div></div>';
    
}

function gift_array_unique_by_key($array, $key) {
    $temp_array = array();
    $result_array = array();

    foreach ($array as $item) {
        if (!in_array($item[$key], $temp_array)) {
            $temp_array[] = $item[$key];
            $result_array[] = $item;
        }
    }

    return $result_array;
}

function gift_filter_given_products($order_id, $product_id) {
    global $wpdb;
    
    $result = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT user_email
            FROM gpm_gfp_order_download_permissions
            WHERE order_id = {$order_id} AND product_id = {$product_id}
            UNION
            SELECT user_email
            FROM gpm_gfp_learndash_permissions
            WHERE order_id = {$order_id} AND product_id = {$product_id}
            LIMIT 1"
        )
    );
    
    if($result) {
        $current_user_email = wp_get_current_user()->user_email;

        if($result != $current_user_email) {
            return $result;
        }
    }

    return False;
}

// Include gifted digital products in the account details downloads
function gift_inclusion_downloadable_products($downloads) {
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;
    $sorted_downloads = [];

    global $wpdb;

    $order_ids = array_unique(array_column($downloads, 'order_id'));

    if (!empty($order_ids)) {
        $order_ids_placeholder = implode(',', array_fill(0, count($order_ids), '%d'));

        $findProducts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.post_id
                FROM {$wpdb->prefix}postmeta AS pm
                WHERE pm.meta_key LIKE '%%_gft_p_%%' AND pm.post_id IN ($order_ids_placeholder)",
                ...$order_ids
            )
        );

        $grouped_products = array_fill_keys($order_ids, false);

        foreach ($findProducts as $product) {
            $grouped_products[$product->post_id] = true;
        }

        foreach ($downloads as $key => $download) {
            $order_id = $download['order_id'];

            if (!$grouped_products[$order_id]) {
                $sorted_downloads[$download['download_id']] = $download;
            }
        }
    }

    try {
        $table_name = 'gpm_gfp_order_download_permissions';

        $completeDownloadList = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_email = %s",
                $user_email
            )
        );

        $table_name = 'gpm_gfp_learndash_permissions';

        $completeLearnDashDownloadList = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_email = %s",
                $user_email
            )
        );

        $completeDownloadList = array_merge($completeDownloadList, $completeLearnDashDownloadList);

        if (empty($completeDownloadList)) {
            return false;
        }

        $sorted_downloads = [];

        foreach ($completeDownloadList as $gift) {
            $gift_product_obj = wc_get_product($gift->product_id);
            $gift_order_id = $gift->order_id;
            $gift_activation_date = $gift->activation_date;

            if(!(empty($gift_order_id))) {
                $order = wc_get_order($gift_order_id);

                if (!($order && ($order->get_status() === 'completed' || $order->get_status() === 'active'))) {
                    continue;
                }
            }

            $gift_activation_time = strtotime(date('Y-m-d', strtotime($gift_activation_date)));
            $current_time = strtotime(date('Y-m-d'));

            if(!($gift_activation_time == $current_time)) {
                if(!($gift_activation_time < $current_time)) {
                    continue;
                }
            }

            if (empty($gift_activation_date)) {
                continue;
            }

            // Check if the product is a bundled product
            if ($gift_product_obj->is_type('bundle')) {
                $bundled_items = $gift_product_obj->get_bundled_items();

                foreach ($bundled_items as $bundled_item_id => $bundled_item) {
                    $bundled_product_id = $bundled_item->get_product_id();
                    $gift_bundle_product_obj = wc_get_product($bundled_product_id);

                    if($gift_bundle_product_obj && $gift_bundle_product_obj->is_downloadable()) {
                        $gift_bundle_product_title = $gift_bundle_product_obj->get_title();
                        $gift_bundle_product_link = $gift_bundle_product_obj->get_permalink();
                        $email = $user_email;

                         foreach ($gift_bundle_product_obj->get_downloads() as $file_id => $file) {
                            $download_link = $file['file'];

                            if ((defined('UNIVERSAL_MODULES') && UNIVERSAL_MODULES['pdf-protect'] === true) && pathinfo(parse_url($download_link, PHP_URL_PATH), PATHINFO_EXTENSION) === 'pdf') {
                                $watermark = 'Downloaded by ' . $email . ' #';
                                $download_link = get_bloginfo('url') . '/?downloadPdf=' . urlencode(handle_secure_data($download_link . '|' . $watermark . '|' . $email . '|' . time(), true));
                            }

                            $sorted_downloads[] = [
                                'download_url' => $download_link,
                                'product_name' => $gift_bundle_product_title,
                                'product_url' => $gift_bundle_product_link,
                                'download_id'=>$file_id,
                                'download_name' => $gift_bundle_product_title,
                                'file' => ['name' => $gift_bundle_product_title],
                            ];
                        }
                    }
                }
            }

            if(!empty($sorted_downloads)) {
                $sorted_downloads = gift_array_unique_by_key($sorted_downloads, 'download_id');
            }

            if ($gift_product_obj && $gift_product_obj->is_downloadable()) {

                $productName = $gift_product_obj->get_title();
                $productLink = $gift_product_obj->get_permalink();
                $email = $user_email;

                foreach ($gift_product_obj->get_downloads() as $file_id => $file) {
                    $download_link = $file['file'];

                    if ((defined('UNIVERSAL_MODULES') && UNIVERSAL_MODULES['pdf-protect'] === true) && pathinfo(parse_url($download_link, PHP_URL_PATH), PATHINFO_EXTENSION) === 'pdf') {
                        $watermark = 'Downloaded by ' . $email . ' #';
                        $download_link = get_bloginfo('url') . '/?downloadPdf=' . urlencode(handle_secure_data($download_link . '|' . $watermark . '|' . $email . '|' . time(), true));
                    }

                    $sorted_downloads[] = [
                        'download_url' => $download_link,
                        'product_name' => $productName,
                        'product_url' => $productLink,
                        'download_id'=>$file_id,
                        'download_name' => $productName,
                        'file' => ['name' => $productName],
                    ];
                }
            }
        }
    } catch (Exception $e) { }

    return $sorted_downloads;
}

// Retrieve gift data from cookies
function get_gift_data($decodeCookie, $giftEnd, $order_id) {
    $product_id = $decodeCookie['id-' . $giftEnd];
    $product = wc_get_product($product_id);

    $giftData = [
        "product" => [
            "id" => $product_id,
            "obj" => $product,
            "physical" => $product->is_virtual(),
            "bundle" => $product->is_type('bundle'),
        ],
        "shipping" => $giftData["product"]["physical"] ? [
            "email_address" => $decodeCookie['gsem-' . $giftEnd] ?? null,
            "first_name" => $decodeCookie['gsfn-' . $giftEnd] ?? null,
            "last_name" => $decodeCookie['gsln-' . $giftEnd] ?? null,
        ] : [
            "company_name" => $decodeCookie['gscn-' . $giftEnd] ?? null,
            "email_address" => $decodeCookie['gsem-' . $giftEnd] ?? null,
            "first_name" => $decodeCookie['gsfn-' . $giftEnd] ?? null,
            "last_name" => $decodeCookie['gsln-' . $giftEnd] ?? null,
            "country_name" => $decodeCookie['gsrcn-' . $giftEnd] ?? null,
            "state_name" => $decodeCookie['gsrs-' . $giftEnd] ?? null,
            "street_address" => $decodeCookie['gsrsa-' . $giftEnd] ?? null,
            "street_second_address" => $decodeCookie['gsrsas-' . $giftEnd] ?? null,
            "city_name" => $decodeCookie['gsrt-' . $giftEnd] ?? null,
            "zip_code" => $decodeCookie['gsrz-' . $giftEnd] ?? null,
        ],
    ];

    $activation_date = date('Y-m-d H:i:s', strtotime($decodeCookie['gsfd-' . $giftEnd])) ?? date('Y-m-d H:i:s');

    $product_ids = [];

    $email_address = $giftData["shipping"]["email_address"];

    if ($giftData["product"]["bundle"]) {
        foreach ($giftData["product"]["obj"]->get_bundled_items() as $bundled_item_id => $bundled_item) {
            $bundled_product_id = $bundled_item->get_product_id();
            $product_ids[] = $bundled_product_id;
        }
    } else {
        $product_ids[] = $product_id;
    }

    foreach($product_ids as $product_id) {
        global $wpdb;

        $query_whether_product_ld = $wpdb->get_var(
            "SELECT meta_value
            FROM $wpdb->postmeta
            WHERE post_id = {$product_id}
            AND meta_key = '_related_course'"
        );

        if($query_whether_product_ld !== null) {
            $gfp_learndash_permissions_table = 'gpm_gfp_learndash_permissions';
            $gifter_user_id = gift_get_user_by_order_id($order_id)->ID;

            $gifter_current_ld_courses = serialize(learndash_get_user_course_access_list($gifter_user_id));

            $insert_learndash_gift = $wpdb->prepare(
                "INSERT INTO $gfp_learndash_permissions_table (courses, product_id, user_email, order_id, activation_date)
                VALUES (%s, %d, %s, %d, %s)",
                $query_whether_product_ld, $product_id, $email_address, $order_id, $activation_date
            );

            $wpdb->query($insert_learndash_gift);
        } else {
            $product = wc_get_product($product_id);

            if ($email_address && $product && $product->is_downloadable()) {
                $table_name = 'gpm_gfp_order_download_permissions';
                                
                $wpdb->query(
                    $wpdb->prepare(
                        "INSERT IGNORE INTO $table_name (product_id, user_email, order_id, activation_date) VALUES (%d, %s, %d, %s)",
                        $product_id,
                        $email_address,
                        $order_id,
                        $activation_date
                    )
                );
            }
        }
    }

    return $giftData;
}

// Picks order apart to find gifted items
function process_order_pick_gifts($order_id) { 
    global $woocommerce, $wpdb;
    // grab woo order details
    $theorder = new WC_Order($order_id);
    #$theorder = wc_get_order(10487081);
    $items = $theorder->get_items();
    
    $productIDs = array();

    // put product ids into array
    foreach ($items as $item) {
        $shotShoot = $item['variation_id'];

        if(!$shotShoot) {
            $shotShoot= $item->get_product_id();
        }

        array_push($productIDs, $shotShoot);
    }

    $cookie_name1 = $_COOKIE;

    foreach((preg_grep('/^ggp-/', array_keys($cookie_name1))) as $value) {
        $decodeCookie = json_decode(stripslashes($cookie_name1[$value]), true);
        $uniqID = uniqid();

        foreach($decodeCookie as $cookie => $cookievalue) {
            if (in_array($cookievalue, $productIDs)) {
                $explodeCookie = explode('-', $cookie);
                try {
                    $giftID = $explodeCookie[2];
                    $giftKey = $explodeCookie[1];

                    if ($decodeCookie['gft-' . $giftKey . '-' . $giftID]) {
                        $giftEnd = $giftKey . '-' . $giftID;

                        $giftData = get_gift_data($decodeCookie, $giftEnd, $order_id);

                        $gift_product_id = esc_sql($giftData["product"]["id"]);

                        $data = [
                            "_gft_p_{$giftID}_{$uniqID}" => $gift_product_id,
                            "_gft_gp" => esc_sql($_POST['physical_gift_purchase']),
                            "_gft_msg_{$giftID}_{$uniqID}" => esc_sql($decodeCookie['gsms-' . $giftEnd]),
                            "_gft_sd_{$giftID}_{$uniqID}" => esc_sql(json_encode($giftData["shipping"])),
                            "_gft_stage_{$giftID}_{$uniqID}" => 0,
                            "_gft_fd_{$giftID}_{$uniqID}" => esc_sql(strtotime($decodeCookie['gsfd-' . $giftEnd])),
                        ];                      

                        $results = $wpdb->get_results("SELECT * FROM `wp_wdr_rules` WHERE filters LIKE '%" . $gift_product_id . "%'");

                        if ($results) {
                            foreach ($results as $row) {
                                $unserialized_filter_data = json_decode($row->filters, true);
                                $unserialized_adjustment_data = json_decode($row->buy_x_get_y_adjustments, true);

                                if(empty($unserialized_adjustment_data) && !array_key_exists('mode', $unserialized_adjustment_data)) {
                                    continue;
                                }

                                if($unserialized_adjustment_data['mode'] != 'auto_add') {
                                    continue;
                                }

                                if (is_array($unserialized_filter_data)) {
                                    foreach ($unserialized_filter_data as $key => $value) {
                                        if($value['method'] == 'in_list') {
                                            if (in_array($gift_product_id, $value['value'])) {
                                                $ranges = $unserialized_adjustment_data['ranges'];

                                                foreach ($ranges as $range) {
                                                    if($range['from'] == $range['to']) {
                                                        $products_to_auto_give = $range['products'];
                                                        foreach ($products_to_auto_give as $products_to_give) {
                                                            $auto_giftID = rand(199, 999);
                                                            $gift_data = [
                                                                "_gft_p_{$auto_giftID}_{$uniqID}" => $products_to_give,
                                                                "_gft_gp" => esc_sql($_POST['physical_gift_purchase']),
                                                                "_gft_msg_{$auto_giftID}_{$uniqID}" => esc_sql($decodeCookie['gsms-' . $giftEnd]),
                                                                "_gft_sd_{$auto_giftID}_{$uniqID}" => esc_sql(json_encode($giftData["shipping"])),
                                                                "_gft_stage_{$auto_giftID}_{$uniqID}" => 0,
                                                                "_gft_fd_{$auto_giftID}_{$uniqID}" => esc_sql(strtotime($decodeCookie['gsfd-' . $giftEnd])),
                                                                "_gft_ag_{$auto_giftID}_{$uniqID}" => true,
                                                            ];

                                                            foreach ($gift_data as $meta_key => $meta_value) {
                                                                update_post_meta($order_id, $meta_key, $meta_value);
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        foreach ($data as $meta_key => $meta_value) {
                            update_post_meta($order_id, $meta_key, $meta_value);
                        }

                        if ($giftData["product"]["bundle"]) {
                            $count = 1;
                            foreach ($giftData["product"]["obj"]->get_bundled_items() as $bundled_item_id => $bundled_item) {
                                $product = $bundled_item->get_product_id();
                                $giftID_sub_id = $giftID + $count;

                                $data = [
                                    "_gft_p_{$giftID_sub_id}_{$uniqID}" => $product,
                                    "_gft_gp" => esc_sql($_POST['physical_gift_purchase']),
                                    "_gft_msg_{$giftID_sub_id}_{$uniqID}" => esc_sql($decodeCookie['gsms-' . $giftEnd]),
                                    "_gft_sd_{$giftID_sub_id}_{$uniqID}" => esc_sql(json_encode($giftData["shipping"])),
                                    "_gft_stage_{$giftID_sub_id}_{$uniqID}" => 0,
                                    "_gft_bundle_{$giftID_sub_id}_{$uniqID}" => 1,
                                    "_gft_fd_{$giftID_sub_id}_{$uniqID}" => esc_sql(strtotime($decodeCookie['gsfd-' . $giftEnd])),
                                ];

                                foreach ($data as $key => $value) {
                                    update_post_meta($order_id, $key, $value);
                                }

                                $count++;
                            }
                        }

                        setcookie("ggp-{$giftKey}", "", time() - 3600);
                    }
                } catch (Exception $e) {}
            }
        }
    }
}

// Checks and grabs user's active subscriptions
function has_active_subscription($user_id=null) {
    if(null == $user_id && is_user_logged_in()) 
        $user_id = get_current_user_id();

    if($user_id == 0) 
        return false;

    global $wpdb;

    $count_subscriptions = $wpdb->get_results("
        SELECT *
        FROM {$wpdb->prefix}posts as p
        JOIN {$wpdb->prefix}postmeta as pm 
            ON p.ID = pm.post_id
        WHERE p.post_type = 'shop_subscription' 
        AND p.post_status = 'wc-active'
        AND pm.meta_key = '_customer_user' 
        AND pm.meta_value > 0
        AND pm.meta_value = '$user_id'
    ");

    $subscription_products_id = array();

    foreach($count_subscriptions as $sub) {
        $subscription_id = $sub->ID;

        $subscription = wc_get_order($subscription_id);

        foreach($subscription->get_items() as $item_id => $product_subscription) {
            $product_id = $product_subscription->get_product_id();
            array_push($subscription_products_id, $product_id);
        }
    }

    return [(count($subscription_products_id) == 0 ? false : true), $subscription_products_id];
}

// Check whether user trying to make another subscription purchase
function check_possible_duplicate_subscription_purchase() {
    // Get all product IDs from the cart
    $cart_product_ids = array_map(
        function($cart_item) {
            return $cart_item['product_id'];
        },
        WC()->cart->get_cart()
    );
    
    // Get a list of product IDs that are not gifted
    $not_gifted = $cart_product_ids;
    if (!empty($_POST['gift_purchase_list'])) {
        $gifted_items = explode(",", $_POST['gift_purchase_list']);
        $not_gifted = array_diff($cart_product_ids, $gifted_items);
    }
    
    // Check for active subscriptions on non-gifted items
    list($has_subscription, $active_subscriptions) = has_active_subscription();
    if (!empty($not_gifted) && !empty($active_subscriptions)) {
        $non_gifted_subscriptions = array_intersect($not_gifted, $active_subscriptions);
        if (!empty($non_gifted_subscriptions)) {
            throw new Exception('You already have a subscription to this product in your cart and cannot purchase again, unless you are gifting it to someone else.');
        }
    }
}

// Inject & build needed assets for front-end functionality
function inject_gift_assets() {
    // Import assets to gift form.
    wp_enqueue_script('gpm-gift-bootstrap', plugin_dir_url(__FILE__) . '../assets/js/bootstrap.bundle.min.js');
    wp_enqueue_script('gpm-gift-bootbox', plugin_dir_url(__FILE__) . '../assets/js/bootbox.min.js');
    wp_enqueue_script('gpm-gift-moment', plugin_dir_url(__FILE__) . '../assets/js/moment-with-locales.min.js');
    wp_enqueue_style('gpm-gift-style', plugin_dir_url(__FILE__) . '../assets/css/gpm-style.css?' . time());

    // Build gift form for physical orders.
    $physical_fields = [
        ['name' => 'gsfn', 'type' => 'text', 'class' => ['form-row form-row-first'], 'required' => true, 'label' => 'Recipient First name', 'placeholder' => __('Enter the gift recipient\'s first name','bs')],
        ['name' => 'gsln', 'type' => 'text', 'class' => ['form-row form-row-first'], 'required' => true, 'label' => 'Recipient Last name', 'placeholder' => __('Enter the gift recipient\'s last name','bs')],
        ['name' => 'gsem', 'type' => 'email', 'class' => ['form-row form-row-first'], 'required' => true, 'label' => 'Recipient Email address', 'placeholder' => __('Provide gift recipient\'s email','bs')],
        ['name' => 'gscn', 'type' => 'text', 'class' => ['form-row form-row-first'], 'required' => false, 'label' => 'Recipient Company name', 'placeholder' => __('Enter the recipient\'s company, if any','bs')],
        ['name' => 'gsrsas', 'type' => 'text', 'class' => ['form-row form-row-first'], 'required' => false, 'label' => 'Recipient Suite, Unit or Apartment', 'placeholder' => __('Enter suite, unit or apartment','bs')],
        ['name' => 'gsrsa', 'type' => 'text', 'class' => ['form-row form-row-first'], 'required' => true, 'label' => 'Recipient Street Address', 'placeholder' => __('Enter the gift recipient\'s address','bs')],        
        ['name' => 'gsrt', 'type' => 'text', 'class' => ['form-row form-row-first'], 'required' => true, 'label' => 'Recipient Town / City', 'placeholder' =>  __('Enter town/city','bs')],
        ['name' => 'gsrz', 'type' => 'text', 'class' => ['form-row form-row-first'], 'required' => true, 'label' => 'Recipient Postcode / ZIP', 'placeholder' => __(''), 'placeholder' =>  __('Enter postcode/zip','bs')],
        ['name' => 'gsrs', 'type' => 'select', 'class' => ['form-row form-row-first'], 'required' => true, 'label' => 'Recipient State', 'options' => (new WC_Countries())->get_states((new WC_Countries())->get_base_country()), 'description' =>  __('Enter state','bs')],
        ['name' => 'gsrcn', 'type' => 'select', 'class' => ['form-row form-row-first'], 'required' => true, 'label' => 'Recipient Country', 'options' => (new WC_Countries())->__get('countries'), 'default' => 'US', 'description' => __('Enter the gift recipient\'s country','bs')],    
        ['name' => 'gsms', 'type' => 'textarea', 'class' => ['form-row form-row-wide gift-textarea'], 'required' => false, 'maxlength' => 255, 'label' => 'Message for Gift', 'description' => __('Your item will be mailed according to the USPS guidelines.','bs'), 'placeholder' => 'Enter your gift message here']
    ];

    $physical_form = new FormBuilder('physical_gift_form', $physical_fields);
    $physical_form->build_form();
    
    // Build form for digital orders.
    $digital_fields = [
        ['name' => 'gsfn', 'type' => 'text', 'class' => ['form-row', 'form-row-first'], 'required' => true, 'label' => 'Recipient First name', 'placeholder' => __('Enter the gift recipient\'s first name','bs')],
            ['name' => 'gsln', 'type' => 'text', 'class' => ['form-row', 'form-row-first'], 'required' => true, 'label' => 'Recipient Last name', 'placeholder' => __('Enter the gift recipient\'s last name','bs')],
            ['name' => 'gsem', 'type' => 'email', 'class' => ['form-row', 'form-row-first'], 'required' => true, 'label' => 'Recipient Email address', 'placeholder' => __('Provide gift recipient\'s email','bs')],
            ['name' => 'gsfd', 'type' => 'date', 'class' => ['form-row', 'form-row-first'], 'required' => true, 'label' => 'When should we send this gift?', 'default' => date('Y-m-d'), 'custom_attributes' => ['onchange' => "dateFormat(this)", 'data-date' => '', 'data-date-format' => 'MM/DD/YYYY'], 'description' =>  __('Provide the date your item will be emailed.','bs')],
            ['name' => 'gsms', 'type' => 'textarea', 'class' => ['form-row', 'form-row-wide', 'gift-textarea'], 'required' => false, 'label' => 'Message for Gift', 'maxlength' => 255, 'placeholder' => 'Enter your gift message here']
    ];

    $digital_form = new FormBuilder('digital_gift_form', $digital_fields);
    $digital_form->build_form();

    // Import modal asset for form.
    wp_enqueue_script('gpmmodal', plugin_dir_url(__FILE__) . '../assets/js/gpm-modal.js?' . time());
}

?>
