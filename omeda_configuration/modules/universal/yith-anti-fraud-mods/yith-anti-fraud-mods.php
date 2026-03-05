<?php

    function yith_anti_fraud_mods_enqueue_admin_scripts() {
        wp_enqueue_style('sweetalert2-admin-css', 'https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/7.28.2/sweetalert2.min.css');

        wp_enqueue_script('sweetalert2-admin-js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), null, true);

        wp_enqueue_script('yith-anti-fraud-mods-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js?cache=' . time(), array(), null, true);

        $yith_anti_fraud_mods_whitelist_emails = get_option("yith_anti_fraud_mods_whitelist_emails");

        $whitelist_emails = get_option("yith_anti_fraud_mods_whitelist_emails");
        
        $custom_data = array(
            'emails' => !empty($whitelist_emails) ? implode("\n", $whitelist_emails) : ''
        );

        wp_localize_script('yith-anti-fraud-mods-admin', 'YITHWhiteList', $custom_data);
    }


    add_action('admin_init', 'yith_anti_fraud_mods_init');

    function yith_anti_fraud_mods_init() {
        if (isset($_GET['page']) && $_GET['page'] === 'yith-wc-anti-fraud') {
            add_action('admin_enqueue_scripts', 'yith_anti_fraud_mods_enqueue_admin_scripts');
        }
    }

    add_action('rest_api_init', function () {
        register_rest_route('yith/v1', '/bypass/', [
            'methods' => ['POST'],
            'permission_callback' => '__return_true',
            'callback' => 'yith_anti_fraud_mods_callback',
        ]);
    });

    function yith_anti_fraud_mods_callback(WP_REST_Request $request) {
        $emails = sanitize_text_field($request->get_param('emails'));

        $output = [
            'success' => false,
            'msg' => 'Parameter issue.',
        ];

        $email_array = explode(',', $emails);

        $update_result = update_option('yith_anti_fraud_mods_whitelist_emails', $email_array);

        if ($update_result) {
            $output = [
                'success' => true,
                'msg' => 'Option updated successfully.',
                'emails' => $email_array,
            ];
        } else {
            $output['msg'] = 'Failed to update option.';
        }


        return new WP_REST_Response($output, 200);
    }

    function get_order_total_before_coupons($order) {
        if (!$order) {
            return false;
        }

        $total_after_discounts = $order->get_total();

        $discount_amount = $order->get_discount_total();

        $total_before_coupons = $total_after_discounts + $discount_amount;

        return $total_before_coupons;
    }

    function get_completed_orders_last_30_days_excluding_current_week($user_id) {
        $completed_orders = wc_get_orders(array(
            'limit'       => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'customer'    => $user_id,
            'status'      => 'completed',
            'date_query'  => array(
                array(
                    'after'     => date('Y-m-d', strtotime('-30 days')),
                    'inclusive' => true,
                ),
            ),
            'meta_query' => array(
                array(
                    'key'     => '_order_total',
                    'value'   => '0',
                    'compare' => '!=',
                ),
            ),
        ));

        $orders_info = array();

        if (!empty($completed_orders)) {
            foreach ($completed_orders as $completed_order) {
                $completion_date = strtotime($completed_order->get_date_completed());
                $seven_days_ago = strtotime('-7 days');

                if ($completion_date < $seven_days_ago) {
                    $order_id = $completed_order->get_id();
                    $order_date_created = $completed_order->get_date_created();
                    $orders_info[] = array(
                        'order_id'      => $order_id,
                        'date_created'  => $order_date_created
                    );
                }
            }
        }
        
        return $orders_info;
    }

    function get_last_completed_order_id_for_user($user_id) {
        $orders = wc_get_orders(array(
            'limit'     => 1,
            'orderby'   => 'date',
            'order'     => 'DESC',
            'customer'  => $user_id,
            'status'    => 'completed',
            'meta_query' => array(
                array(
                    'key'     => '_order_total',
                    'value'   => '0',
                    'compare' => '!=',
                ),
            ),
        ));

        if (!empty($orders)) {
            $order = reset($orders);
            $order_id = $order->get_id();
            $order_date_created = $order->get_date_created();

            return array(
                'order_id' => $order_id,
                'date_created' => $order_date_created
            );
        } else {
            return false;
        }
    }

    function is_subscription_renewal($order, $meta_key = '_subscription_renewal') {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}postmeta
            WHERE meta_key = %s
            AND post_id = %d
        ", $meta_key, $order->get_id());

        $count = $wpdb->get_var($query);

        return $count;
    }

    function get_order_created_via($order, $meta_key = '_created_via') {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT meta_value
            FROM {$wpdb->prefix}postmeta
            WHERE meta_key = %s
            AND post_id = %d
        ", $meta_key, $order->get_id());

        $meta_value = $wpdb->get_var($query);

        return $meta_value;
    }

    if (!function_exists('my_ywaf_can_skip_check_order')) {
        function my_ywaf_can_skip_check_order($skip, $order) {
            $user_id = $order->get_customer_id();
            $order_id = $order->get_id();

            $meta_key = '_yith_order_check_and_skip';

            $current_value = get_post_meta($order_id, $meta_key, true);

            if (!empty($current_value)) {
                return true;
            }

            if(get_order_created_via($order) == 'admin') {
                $order->add_order_note('Bypassing YITH anti-fraud checks due to being made in admin panel.');
                update_post_meta($order_id, $meta_key, true);
                return true;
            }

            if(!(is_subscription_renewal($order) === "0")) {
                $order->add_order_note('Bypassing YITH anti-fraud checks due to being a renewal order.');
                update_post_meta($order_id, $meta_key, true);
                return true;
            }

            $payment_title = strtolower($order->get_payment_method_title());

            if (strpos($payment_title, 'paypal') !== false) {
                $order->add_order_note('Bypassing YITH anti-fraud checks due to payment made via PayPal.');
                update_post_meta($order_id, $meta_key, true);
                return true;
            }

            $order_total = get_order_total_before_coupons($order);

            $order_total_int = (int)$order_total;
            
            if ($order_total_int === 0) {
                $order->add_order_note('Avoiding YITH anti-fraud verifications because order value is $0 prior to any discounts being applied.');
                update_post_meta($order_id, $meta_key, true);
                return true;
            }

            $user_info = get_userdata($user_id);

            if ($user_info) {
                $user_email = $user_info->user_email;
                $whitelist_emails = get_option("yith_anti_fraud_mods_whitelist_emails");
                
                if(!empty($whitelist_emails) && in_array($user_email, $whitelist_emails)) {
                    $order->add_order_note('Skipping YITH anti-fraud checks based on email being in whitelist.');
                    update_post_meta($order_id, $meta_key, true);
                    return true;
                }
            }

            if (user_can($user_id, 'administrator')) {
                $order->add_order_note('Skipping YITH anti-fraud checks based on user role.');
                update_post_meta($order_id, $meta_key, true);
                return true;
            }

            $last_order = get_last_completed_order_id_for_user($user_id);

            if ($last_order) {
                $current_date = new DateTime();
                $date_difference = $current_date->diff($last_order['date_created']);

                if ($date_difference->days >= 3) {
                    $order->add_order_note('Bypassing YITH anti-fraud check for order, as has a completed order older than three days.');
                    update_post_meta($order_id, $meta_key, true);
                    return true;
                }
            }

            $_30_day_order_check = get_completed_orders_last_30_days_excluding_current_week($user_id);

            if ($_30_day_order_check && count($_30_day_order_check) >= 1) {
                $order->add_order_note('Skipping the YITH anti-fraud check for this order because there\'s a completed order within the past 30 days, excluding the current week.');
                update_post_meta($order_id, $meta_key, true);
                return true;
            }

            return $skip;
        }

        add_filter('ywaf_can_skip_check_order', 'my_ywaf_can_skip_check_order', -1, 2);
    }