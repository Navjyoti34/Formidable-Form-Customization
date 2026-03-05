<?php

/*
Plugin Name: Step Up Pricing
Plugin URI:
Description: Ability to up pricing on subscriptions.
Version: 1.0
Author:  James
Author URI: http://midtc.com/
License:
*/

// Exit if accessed directly.
if (! defined('ABSPATH')) exit;

include($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');

add_action('admin_menu', 'step_up_pricing_add_menu_item');
add_action('admin_init', 'step_up_pricing_wp_enqueue', 0);

function step_up_pricing_get_product_details($product_id) {
    $product = wc_get_product($product_id);
    
    if(!$product) {
        return $product_id;
    }

    $title = $product->get_title();
    $permalink = get_permalink($product_id);
    $image = wp_get_attachment_image_src($product->get_image_id(), 'full');

    $attributes = [
        'title' => $title,
        'link' => $permalink,
        'image' => $image[0],
    ];

    return '<a href="' . $attributes['link'] . '" target="_blank">' . $attributes['title'] . '</a>';
}

function step_up_pricing_get_rule_for_product($product_id) {
    global $wpdb;

    $table_name = 'midtc_step_up_pricing_rules';

    $product_id = intval($product_id);

    $sql = "SELECT rule_title, rule FROM $table_name WHERE FIND_IN_SET(" . intval($product_id) . ", products) > 0";

    $rule = $wpdb->get_row($sql);

    

    if ($rule) {
        $rule_array = html_entity_decode($rule->rule);
        $rule_title = html_entity_decode($rule->rule_title);
        return array('rule' => $rule_array, 'title' => $rule_title);
    }

    return false;
}

function step_up_pricing_check_eligibility($subscription_id, $product_id, $price, $is_international, $is_canadian) {
    $rule_json = step_up_pricing_get_rule_for_product($product_id);

    if(!$rule_json) {
        return false;
    }

    $rule_title = $rule_json['title'];
    $rule_json = $rule_json['rule'];
    $matching_rule = False;

    if ($rule_json !== null) {
        $rule_array = json_decode($rule_json, true);

        foreach ($rule_array as $expression => $price_rules) {
            if (str_contains($expression, '-')) { 
                list($low, $high) = explode('-', $expression);
                $low = floatval($low);
                $high = floatval($high);

                if ($price >= $low && $price <= $high) {
                    $matching_rule = $price_rules;
                    break;
                }
            } else {
                $operator = '';
                $value = '';

                if (str_contains($expression, '>=')) { 
                    $operator = '>=';
                    $value = end(explode('>=', $expression));
                } elseif (str_contains($expression, '>')) { 
                    $operator = '>';
                    $value = end(explode('>', $expression));
                } elseif (str_contains($expression, '<=')) { 
                    $operator = '<=';
                    $value = end(explode('<=', $expression));
                } elseif (str_contains($expression, '<')) { 
                    $operator = '<';
                    $value = end(explode('<', $expression));
                }

                if ($operator && is_numeric($value)) {
                    $value = floatval($value);

                    if (
                        ($operator === '>' && $price > $value) ||
                        ($operator === '>=' && $price >= $value) ||
                        ($operator === '<' && $price < $value) ||
                        ($operator === '<=' && $price <= $value)
                    ) {
                        $matching_rule = $price_rules;
                        break;
                    }
                }
            }
        }
    }

    if ($matching_rule !== null && $matching_rule) {
        $international_rules = False;

        if($is_canadian || $is_international) {
            $international_rules = $matching_rule;
            foreach($international_rules as $index => $value) {
                if($is_canadian) {
                    if($rule_title == 'Rule One') {
                        $international_rules[$index] = $value + 8.00;
                    }

                    if($rule_title == 'Rule Two') {
                        $international_rules[$index] = $value + 12.00;
                    }

                    if($rule_title == 'Rule Three') {
                        $international_rules[$index] = $value + 8.00;
                    }
                }

                if($is_international) {
                    if($rule_title == 'Rule One') {
                        $international_rules[$index] = $value + 12.00;
                    }

                    if($rule_title == 'Rule Two') {
                        $international_rules[$index] = $value + 18.00;
                    }

                    if($rule_title == 'Rule Three') {
                        $international_rules[$index] = $value + 12.00;
                    }
                }
            }
        }

        return array('rule' => $matching_rule, 'title' => $rule_title, 'international_rules' => $international_rules);
    } else {
        return False;
    }
}

function step_up_pricing_is_international_subscription($subscription_id) {
    $default_country = 'US';

    $subscription = wcs_get_subscription($subscription_id);

    if (!$subscription) {
        return false;
    }

    $shipping_country = $subscription->get_shipping_country();
    $billing_country = $subscription->get_billing_country();

    $country_to_check = !empty($shipping_country) ? $shipping_country : $billing_country;

    if ($country_to_check && strtoupper($country_to_check) !== strtoupper($default_country)) {
        return true;
    }

    return false;
}

function step_up_pricing_is_from_canada($subscription_id) {
    $canada_country_code = 'CA';

    $subscription = wcs_get_subscription($subscription_id);

    if (!$subscription) {
        return false;
    }

    $shipping_country = $subscription->get_shipping_country();
    $billing_country = $subscription->get_billing_country();

    $country_to_check = !empty($shipping_country) ? $shipping_country : $billing_country;

    if ($country_to_check && strtoupper($country_to_check) === strtoupper($canada_country_code)) {
        return true;
    }

    return false;
}

function update_subscription_item_prices($subscription_id, $items, $prices, $rules, $round = 1) {
    global $woocommerce;

    $itemsArray = explode(",", $items);
    $pricesArray = explode(",", $prices);

    if (empty($itemsArray)) {
        return;
    }

    foreach ($itemsArray as $key => $item) {

        $rulesJSON = json_decode($rules[intval($item)], true);

        if(empty($rulesJSON)) {
            $new_price = $rules[$round];
        } else {
            if (!(isset($rulesJSON[$round]))) {
                $new_price = end($rulesJSON);
            } else {
                $new_price = $rulesJSON[$round];
            }
        }

        if(empty($new_price)) {
            continue;
        }

        if(intval($new_price) <= 0) {
            continue;
        }

        $subscription_obj = wcs_get_subscription($subscription_id);

        foreach ($subscription_obj->get_items() as $item_id => $order_item) {
            if (json_decode($order_item, true)['product_id'] != $item) continue;

            $order_item->set_subtotal($new_price);
            $order_item->set_total($new_price);

            $order_item->save();

            $subscription_obj->calculate_totals();
            $subscription_obj->save();
        }
    }

    $subscription = wcs_get_subscription($subscription_id);

    $subscription_total = $subscription->get_total();

    return $subscription_total;
}

function step_up_title_case($str) {
    $words = explode(' ', strtolower($str));
    $title_case_words = array_map('ucfirst', $words);
    return implode(' ', $title_case_words);
}

function step_up_on_subscription_renewal($subscription_id, $old_status, $new_status) {
    if ($old_status !== $new_status && $new_status === 'active') {
        $midtc_stepup_option_name = 'midtc_stepup_pricing';
        $subscription_json = json_decode($subscription_id, true);
        $subscription_id = $subscription_json['id'];

        $midtc_stepup_option_value = get_post_meta($subscription_id, $midtc_stepup_option_name, true);

        if (empty($midtc_stepup_option_value)) {
            return;
        }

        update_post_meta(
            $subscription_id,
            $midtc_stepup_option_name,
            array(
                'round' => isset($midtc_stepup_option_value['round']) && $midtc_stepup_option_value['round'] !== null
                    ? $midtc_stepup_option_value['round'] 
                    : null,
                'orginal_prices' => isset($midtc_stepup_option_value['orginal_prices']) && $midtc_stepup_option_value['orginal_prices'] !== null
                    ? $midtc_stepup_option_value['orginal_prices'] 
                    : null
            )
        );
    }
}

add_action('woocommerce_subscription_status_updated', 'step_up_on_subscription_renewal', 10, 3);

function send_out_step_up_email($subscription_id = '012345', $new_price = '34.53', $email = 'jamesh@midtc.com', $publication_name = 'example pub', $round = 1, $renewal_date = '', $test_email = False) {
    global $c;

    $publication_name_array = explode(', ', ucwords(str_replace('subscription', '', strtolower(strip_tags($publication_name)))));

    $publication_name_array = array_map('rtrim', $publication_name_array);

    if (count($publication_name_array) > 1) {
        $email_subject = "Your Subscriptions are About to Renew!";
        
        if (count($publication_name_array) == 2) {
            $publication_name = implode(' and ', $publication_name_array);
        } elseif (count($publication_name_array) > 2) {
            $lastElement = array_pop($publication_name_array);
            $publication_name_array[] = "and " . $lastElement;
            $publication_name = implode(', ', $publication_name_array);
        }
    } else {
        $publication_name = $publication_name_array[0];
        $email_subject = "Your $publication_name Subscription is About to Renew!";
    }

    $email_subject = step_up_title_case(strtolower($email_subject));

    if ($test_email) {
        $user_id = get_current_user_id();
    } else {
        $user_id = get_user_by('email', $email);

        if (!$user_id) {
            $user_id = get_user_by('billing_email', $email);
        }

        if($user_id) {
            $user_id = $user_id->ID;
        }

        if (!$user_id) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'usermeta';

            $user_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT user_id FROM $table_name WHERE meta_key = %s AND meta_value = %s",
                    'billing_email',
                    $email
                )
            );
        }
    }
    if (!empty($renewal_date)) {
        $date = new DateTime($renewal_date);
        // Format the date to the desired format
        $renewal_date = $date->format('F j, Y');
    }

    $first_name = get_user_meta($user_id, 'first_name', true) ?: get_user_meta($user_id, 'billing_first_name', true) ?: 'Valued Customer';
    $last_name = get_user_meta($user_id, 'last_name', true) ?: get_user_meta($user_id, 'billing_last_name', true) ?: '';

    $to_whom = empty($last_name) ? $first_name : "$first_name $last_name";

    $sites_track_id = [
        'artistsnetwork.com' => 'GPM230728042',
        'interweave.com' => 'GPM230728043',
        'quiltingdaily.com' => 'GPM230728044',
        'sewdaily.com' => 'GPM230728041'
    ];

    $omeda_track_id = (function($properties) {
        $current_host = preg_replace('/^www\./', '', implode('.', array_slice(explode('.', $_SERVER['HTTP_HOST']), -2)));
        if (isset($properties[$current_host]) && !empty($properties[$current_host])) {
            return $properties[$current_host];
        } else {
            return false;
        }
    })($sites_track_id);

    $current_site_url = '<a href="' . get_site_url() . '" target="_blank">' . get_bloginfo('name') . '</a>';
    date_default_timezone_set('America/New_York');
    $date_sent = date('F j, Y @ g:i:s A');

    $support_url = '<a href="https://goldenpeakmedia.com/support-ticket">submit a customer service ticket</a>';
    //$email = 'jamesh@midtc.com';
    $logo = esc_url( wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' )[0] );
    $default_message = "
        <p><img style='width:300; height:50px' src='$logo'/></p>
        <p></p>
        <p><b>Happy anniversary from <i>$publication_name</i>!</b></p>
        <hr></hr>
        <p>Subscription ID#: $subscription_id</br>Subscription term to be renewed: $round Year</br>Amount your card will be charged: $$new_price</p>

        <hr></hr>

        <p>To: $to_whom,</p>

        <p>Thank you for being a subscriber to <i>$publication_name</i>! We hope you are enjoying your <i>$publication_name</i> subscription and are looking forward to the year ahead!</p>

        <p>This is a reminder that your current subscription term is ending, but as part of the renewal program for which you signed up, you will automatically be renewed on $renewal_date at the guaranteed savings rate listed above.</p>

        <p>There's nothing you need to do to renew. We will simply charge your credit card at the rate shown or send you an invoice if we do not have a credit card on file.</p>

        <p>If you do not wish to continue your subscription, <i>log into $current_site_url, navigate to Account Settings, select My Subscriptions, click on the subscription ID# and then click cancel. If you need assistance or to cancel with a refund, please $support_url</i>. If you are not 100% satisfied with <i>$publication_name</i>, we'll send you a full refund on all unmailed issues, no questions asked!</p>

        <p>Your Automatic Renewal Program service will continue until you tell us to stop - so you'll never miss an inspiring issue.</p>

        <p>Thanks for subscribing!<br>Sincerely,<br>Renee Allen for <i>$publication_name</i></p>";


    $html_content = $default_message . $html_content;

    $fields = array(
        'TrackId' => $omeda_track_id,
        'EmailAddress' => $email,
        'FirstName' => $first_name,
        'LastName' => $last_name,
        'Subject' => $email_subject,
        'HtmlContent' => $html_content,
        'Preference' => 'HTML'
    );

    echo INCLUDE_OMEDA();

    $omedaCall = omedaCurl("{$c('ON_DEMAND_ENDPOINT')}{$c('OMEDA_DIRECTORY')['send_email']}", json_encode($fields));

    return $omedaCall;
}

//add_action('admin_init', 'send_out_step_up_email');
//add_action('admin_init', 'f');

add_action('process_step_up_pricing_hook', 'step_up_pricing_overview_hook_call');

function step_up_pricing_overview_hook_call() {
    @step_up_pricing_overview(True);
}

add_action('wp_loaded', 'step_up_pricing_scheduling_event');

function step_up_pricing_scheduling_event() {
    if(!wp_next_scheduled('process_step_up_pricing_hook')) {
        wp_schedule_event(time(), 'hourly', 'process_step_up_pricing_hook');
    }
}

/*
function f() {
    $subscription_id = '1323570';
    $midtc_stepup_option_name = 'midtc_stepup_pricing';
    $midtc_stepup_option_value = get_post_meta($subscription_id, $midtc_stepup_option_name, true);
    update_post_meta(
        $subscription_id,
        $midtc_stepup_option_name,
        array(
            'status' => $midtc_stepup_option_value['status'],
            'stage' => '0',
            'round' => '4', //$midtc_stepup_option_value['round'],
            'orginal_prices' => $midtc_stepup_option_value['orginal_prices']
        )
    );
}
*/

function step_up_is_user_admin($user_id) {
    return user_can($user_id, 'manage_options');
}

function is_subscription_belongs_to_admin($subscription_id) {
    $subscription = wcs_get_subscription($subscription_id);

    if ($subscription) {
        $user_id = $subscription->get_user_id();

        if ($user_id && step_up_is_user_admin($user_id)) {
            return true;
        }
    }

    return false;
}

function step_up_pricing_overview($hook = False) {
	global $wpdb;

	$query = "
		SELECT 
		    subscriptions.ID, 
		    next_payment.meta_value AS next_payment_date, 
		    DATEDIFF(DATE(next_payment.meta_value), CURDATE()) AS days_remaining,
		    GROUP_CONCAT(itemmeta.meta_value SEPARATOR ',') AS items,
		    GROUP_CONCAT(pricemeta.meta_value SEPARATOR ',') AS prices
		FROM wp_posts AS subscriptions
		JOIN wp_postmeta AS next_payment ON subscriptions.ID = next_payment.post_id
		JOIN wp_woocommerce_order_items AS order_items ON subscriptions.ID = order_items.order_id
		JOIN wp_woocommerce_order_itemmeta AS itemmeta ON order_items.order_item_id = itemmeta.order_item_id
		LEFT JOIN wp_woocommerce_order_itemmeta AS pricemeta ON order_items.order_item_id = pricemeta.order_item_id
		WHERE subscriptions.post_type = 'shop_subscription'
		AND subscriptions.post_status = 'wc-active'
		AND next_payment.meta_key = '_schedule_next_payment'
		AND itemmeta.meta_key = '_product_id'
		AND pricemeta.meta_key = '_line_total'
		AND DATE(next_payment.meta_value) <= DATE_ADD(CURDATE(), INTERVAL 366 DAY)
		AND DATE(next_payment.meta_value) >= CURDATE()
		GROUP BY subscriptions.ID
		ORDER BY days_remaining ASC
	";


	$results = $wpdb->get_results($query);

    if(!$hook) {

        echo '<div id="set-up-pricing-content"><h1>Step Up Pricing Overview</h1><div id="step-up-pricing-table">';

        $message = "Step up pricing hook not setup as of right now.";
        
        if ( $timestamp = wp_next_scheduled( 'process_step_up_pricing_hook' ) ) {
            $datetime_utc = new DateTime();
            $datetime_utc->setTimestamp( $timestamp );
            $datetime_utc->setTimezone( new DateTimeZone( 'UTC' ) );
            $datetime_est = clone $datetime_utc;
            $datetime_est->setTimezone( new DateTimeZone( 'America/New_York' ) );
            $date_string = $datetime_est->format( 'Y-m-d h:i:s A' );
            $message = 'The next scheduled check for step up pricing is to run @ ' . $date_string . ' EST.';
        }

        echo "<div class='notice notice-warning step-up-pricing'><p>$message</p></div>";
        echo '<table class="wp-list-table widefat fixed striped table-view-list">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="manage-column column-primary">Subscription ID</th>';
        echo '<th class="manage-column" style="display:none;">Orginal Order ID</th>';
        echo '<th class="manage-column">Next Payment Date</th>';
        echo '<th class="manage-column">Days Remaining</th>';
        echo '<th class="manage-column">Items</th>';
        echo '<th class="manage-column">Current Price(s)</th>';
        echo '<th class="manage-column">Orginal Price(s)</th>';
        echo '<th class="manage-column">Next Price</th>';
        echo '<th class="manage-column">Rule</th>';
        echo '<th class="manage-column">International</th>';
        echo '<th class="manage-column">Canadian</th>';
        echo '<th class="manage-column">International Next Price</th>';
        echo '<th class="manage-column">Stage</th>';
        echo '<th class="manage-column">Round</th>';
        echo '<th class="manage-column">Status</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody id="the-list">';

    }

	if ($results) {
	    
	   foreach ($results as $result) {
            $multiple_items = explode(',', $result->items);
            $multiple_prices = explode(',', $result->prices);

            $subscription_id = $result->ID;

            $midtc_stepup_option_name = 'midtc_stepup_pricing';
            //delete_option('midtc_stepup_pricing');
            //delete_post_meta($subscription_id, $midtc_stepup_option_name);
            //die();

            $midtc_stepup_option_name = 'midtc_stepup_pricing';
            $midtc_stepup_option_value = get_post_meta($subscription_id, $midtc_stepup_option_name, true);



            if(isset($midtc_stepup_option_value['orginal_prices']) && $midtc_stepup_option_value['orginal_prices'] !== null) {
                $multiple_prices = explode(',', $midtc_stepup_option_value['orginal_prices']);
            } else {
                update_post_meta(
                    $subscription_id,
                    $midtc_stepup_option_name,
                    array(
                        'status' => isset($midtc_stepup_option_value['status']) && $midtc_stepup_option_value['status'] !== null
                            ? $midtc_stepup_option_value['status']
                            : null,
                        'stage' => isset($midtc_stepup_option_value['stage']) && $midtc_stepup_option_value['stage'] !== null
                            ? $midtc_stepup_option_value['stage']
                            : null,
                        'round' => isset($midtc_stepup_option_value['round']) && $midtc_stepup_option_value['round'] !== null
                            ? $midtc_stepup_option_value['round']
                            : null,
                        'orginal_prices' => $result->prices
                    )
                );
            }

            $midtc_stepup_option_value = get_post_meta($subscription_id, $midtc_stepup_option_name, true);

            $items = array();
            $items_product_id = array();
            $prices = array();
            $valid_rules = array();
            $rule_titles = array();
            $international_rules = array();

            $is_international = step_up_pricing_is_international_subscription($result->ID);
            $is_canadian = step_up_pricing_is_from_canada($result->ID);

            if (count($multiple_items) > 1) {
                foreach ($multiple_items as $index => $item) {
                    if (!array_key_exists($index, $multiple_prices)) {
                        continue;
                    }

                    $valid_rules_check = step_up_pricing_check_eligibility($subscription_id, $item, $multiple_prices[$index], $is_international, $is_canadian);

                    if (!$valid_rules_check) {
                        continue;
                    }

                    $items[] = step_up_pricing_get_product_details($item);
                    $items_product_id[] = $item;
                    $prices[] = $multiple_prices[$index];
                    $valid_rules[$item] = json_encode($valid_rules_check['rule'], JSON_PRETTY_PRINT);
                    $rule_titles[] = $valid_rules_check['title'];
                    $international_rules[$item] = json_encode($valid_rules_check['international_rules'], JSON_PRETTY_PRINT);
                }

                $items = !empty($items) ? implode(', ', $items) : '';
                $items_product_id = !empty($items_product_id) ? implode(', ', $items_product_id) : '';
                $prices = !empty($prices) ? implode(', ', $prices) : '';
                $current_prices = $result->prices;

                $allFalse = true;

                foreach ($international_rules as $key => $value) {
                    if ($value !== 'false') {
                        $allFalse = false;
                        break;
                    }
                }

                $rules = $allFalse ? $valid_rules : $international_rules;

                $valid_rules = !empty($valid_rules) ? implode(', ', $valid_rules) : '';
                $rule_titles = !empty($rule_titles) ? implode(', ', $rule_titles) : '';
                $international_rules = !empty($international_rules) ? implode(', ', $international_rules) : '';
                $international_or_standard = $allFalse ? 'N/A' : $international_rules;
            } else {
                $items = step_up_pricing_get_product_details($result->items);
                $items_product_id = $result->items;
                $current_prices = $result->prices;

                if(isset($midtc_stepup_option_value['orginal_prices']) && $midtc_stepup_option_value['orginal_prices'] !== null) {
                    $prices = $midtc_stepup_option_value['orginal_prices'];
                } else {
                    $prices = $result->prices;
                }
            
                $valid_rules_check = step_up_pricing_check_eligibility($subscription_id, $result->items, $prices, $is_international, $is_canadian);

                if (!$valid_rules_check) {
                    continue;
                }

                $allFalse = true;

                if(is_array($valid_rules_check['international_rules'])) {
                    foreach ($valid_rules_check['international_rules'] as $value) {
                        if ($value !== false) {
                            $allFalse = false;
                            break;
                        }
                    }
                }


                $rules = $allFalse ? $valid_rules_check['rule'] : $valid_rules_check['international_rules'];

                $valid_rules = json_encode($valid_rules_check['rule'], JSON_PRETTY_PRINT);
                $rule_titles = $valid_rules_check['title'];

                $international_rules = json_encode($valid_rules_check['international_rules'], JSON_PRETTY_PRINT);
                $international_or_standard = $allFalse ? 'N/A' : $international_rules;
            }

            if (empty($valid_rules)) {
                continue;
            }

            /*if(!(is_subscription_belongs_to_admin($subscription_id))) {
                continue;
            }
            */

            $query = $wpdb->prepare("SELECT post_parent FROM $wpdb->posts WHERE ID = %d", $subscription_id);
            $order_id = $wpdb->get_var($query);

            $days_remaining = $result->days_remaining;

            $stage = $status = 0;

            // $to_email = 'jamesh@midtc.com';

            $order = wc_get_order($order_id);

            if(!$order) {
                error_log('[STEP-UP] Cannot send email for order #' . $order_id . ' and subscription #' . $subscription_id . ' due to issue getting order details.');
                continue;
            }

            $to_email = $order->get_billing_email();
            
            if(!(isset($midtc_stepup_option_value['stage']))) {
                if($days_remaining > 45) {
                    update_post_meta(
                        $subscription_id,
                        $midtc_stepup_option_name,
                        array(
                            'status' => 'Waiting for 45 day mark to send out renewal email.',
                            'stage' => 0,
                            'round' => isset($midtc_stepup_option_value['round']) && $midtc_stepup_option_value['round'] !== null
                                ? $midtc_stepup_option_value['round']
                                : null,
                            'orginal_prices' => isset($midtc_stepup_option_value['orginal_prices']) && $midtc_stepup_option_value['orginal_prices'] !== null
                                ? $midtc_stepup_option_value['orginal_prices'] 
                                : null
                        )
                    );
                } else {
                    update_post_meta(
                        $subscription_id,
                        $midtc_stepup_option_name,
                        array(
                            'status' => 'Preparing sending of renewal email.',
                            'stage' => 0,
                            'round' => isset($midtc_stepup_option_value['round']) && $midtc_stepup_option_value['round'] !== null
                                ? $midtc_stepup_option_value['round']
                                : null,
                            'orginal_prices' => isset($midtc_stepup_option_value['orginal_prices']) && $midtc_stepup_option_value['orginal_prices'] !== null
                                ? $midtc_stepup_option_value['orginal_prices'] 
                                : null
                        )
                    );
                }
                $midtc_stepup_option_value = get_post_meta($subscription_id, $midtc_stepup_option_name, true);
            }

            if($days_remaining < 45) {
                if(isset($midtc_stepup_option_value['stage']) && $midtc_stepup_option_value['stage'] == 0) {

                    update_post_meta(
                        $subscription_id,
                        $midtc_stepup_option_name,
                        array(
                            'status' => 'Sending out renewal email to ' . $to_email . '.',
                            'stage' => 1, // 1
                            'orginal_prices' => isset($midtc_stepup_option_value['orginal_prices']) && $midtc_stepup_option_value['orginal_prices'] !== null
                                ? $midtc_stepup_option_value['orginal_prices'] 
                                : null
                        )
                    );
                    $round = isset($midtc_stepup_option_value['round']) && $midtc_stepup_option_value['round'] !== null ? ($midtc_stepup_option_value['round']+=1) : 1;

                    if(isset($midtc_stepup_option_value['orginal_prices']) && $midtc_stepup_option_value['orginal_prices'] !== null) {
                        $prices = $midtc_stepup_option_value['orginal_prices'];
                    } else {
                        $prices = $result->prices;
                    }

                    $updated_price = update_subscription_item_prices($subscription_id, $items_product_id, $prices, $rules, $round);
                    update_post_meta(
                        $subscription_id,
                        $midtc_stepup_option_name,
                        array(
                            'status' => $midtc_stepup_option_value['status'],
                            'stage' =>  $midtc_stepup_option_value['stage'],
                            'round' => $round,
                            'orginal_prices' => isset($midtc_stepup_option_value['orginal_prices']) && $midtc_stepup_option_value['orginal_prices'] !== null
                                ? $midtc_stepup_option_value['orginal_prices'] 
                                : null
                        )
                    );
                    $call_send_out_step_up_email = send_out_step_up_email($subscription_id, $updated_price, $to_email, $items, $round, $result->next_payment_date, False);
                    $midtc_stepup_option_value = get_post_meta($subscription_id, $midtc_stepup_option_name, true);

                    date_default_timezone_set('America/New_York');
                    $date_sent = date('F j, Y @ g:i:s A');
                    $meta_value = 'Renewal email sent on ' . $date_sent;

                    $send_decode_omeda_payload_status = $call_send_out_step_up_email[1];
                    $send_decode_omeda_payload = json_decode($call_send_out_step_up_email[0], true);

                    if($send_decode_omeda_payload_status == 400) {
                        $meta_value = 'Unable to send email on ' . $date_sent . ' due to 400 error ';
                    }

                    if($send_decode_omeda_payload_status == 404) {
                        $meta_value = 'Unable to send email on ' . $date_sent . ' due to 404 error ';
                    }

                    if($send_decode_omeda_payload_status !== 200) {
                        $meta_value = 'Unable to send email on ' . $date_sent . ' due to general error ';
                    }

                    if(!isset($send_decode_omeda_payload)) {
                        $meta_value = 'Unable to send email on ' . $date_sent . ' due to unknown error ';
                    }

                    $meta_value .= ' (' . $send_decode_omeda_payload['SubmissionId'] . ').';

                    if(empty($send_decode_omeda_payload['SubmissionId'])) {
                        error_log('Error in sending step-up-email' . $send_decode_omeda_payload);
                    } else {
                        update_post_meta(
                            $subscription_id,
                            $midtc_stepup_option_name,
                            array(
                                'status' => 'Renewal email sent to <b>' . $to_email . '</b> on <b>' . $date_sent . '</b> (' . $send_decode_omeda_payload['SubmissionId'] . '). Waiting for renewal to take place.',
                                'stage' => 2, // 2
                                'round' => isset($midtc_stepup_option_value['round']) && $midtc_stepup_option_value['round'] !== null
                                    ? $midtc_stepup_option_value['round']
                                    : null,
                                'orginal_prices' => isset($midtc_stepup_option_value['orginal_prices']) && $midtc_stepup_option_value['orginal_prices'] !== null
                                    ? $midtc_stepup_option_value['orginal_prices'] 
                                    : null
                            )
                        );
                    }
                }
            }
            

            $stage = $midtc_stepup_option_value['stage'];
            $status = $midtc_stepup_option_value['status'];
            $round = isset($midtc_stepup_option_value['round']) && $midtc_stepup_option_value['round'] !== null
                                ? $midtc_stepup_option_value['round']
                                : 'N/A';
            $orginal_prices = isset($midtc_stepup_option_value['orginal_prices']) && $midtc_stepup_option_value['orginal_prices'] !== null
                                ? $midtc_stepup_option_value['orginal_prices']
                                : 'N/A';

            if(!$hook) {
                echo '<tr>';
                echo '<td class="column-primary" data-colname="Subscription ID"><a href="/wp-admin/post.php?post=' . $subscription_id . '&action=edit" taget="_blank">' . $subscription_id . '</a></td>';
                echo '<td data-colname="Orginal Order ID" style="display:none;"><a href="/wp-admin/post.php?post=' . $order_id . '&action=edit" taget="_blank">' . ($order_id) . '</a></td>';
                echo '<td data-colname="Next Payment Date">' . $result->next_payment_date . '</td>';
                echo '<td data-colname="Days Remaining">' . $days_remaining . '</td>';
                echo '<td data-colname="Items">' . $items . '</td>';
                echo '<td data-colname="Current Price(s)">' . $current_prices . '</td>';
                echo '<td data-colname="Orginal Price(s)">' . $orginal_prices . '</td>';
                echo '<td data-colname="Next Price">' . esc_html($valid_rules) . '</td>';
                echo '<td data-colname="Rule">Following <i>' . strtolower($rule_titles) . '</i>.</td>';
                echo '<td data-colname="International">' . ($is_international ? 'Yes' : 'No') . '</td>';
                echo '<td data-colname="Canadian">' . ($is_canadian ? 'Yes' : 'No') . '</td>';
                echo '<td data-colname="International Next Price">' . $international_or_standard . '</td>';
                echo '<td data-colname="Stage">' . $stage . '</td>';
                echo '<td data-colname="Round">' . $round . '</td>';
                echo '<td data-colname="Status">' . $status . '</td>';
                echo '</tr>';
            }
        }
	    
	} else {
        if(!$hook) {
	       echo 'No active subscriptions found.';
       }
	}
    
    if(!$hook) {
        echo '</tbody>';
        echo '</table>';
        echo '</div></div>';
    }
}

function step_up_pricing_add_menu_item() {
    require_once plugin_dir_path(__FILE__) . 'classes/step-up-pricing-rules.php';
    //require_once plugin_dir_path(__FILE__) . 'classes/step-up-pricing-email.php';

    add_menu_page(
        'Step Up Pricing',
        'Step Up Pricing',
        'manage_options',
        'step_up_pricing_overview',
        'step_up_pricing_overview',
        'dashicons-admin-generic',
        30
    );

    add_submenu_page(
        'step_up_pricing_overview',
        'Overview',
        'Overview',
        'manage_options',
        'step_up_pricing_overview',
        0
    );
    
    $stepUpPricingRules = new stepUpPricingRules();
    $stepUpPricingRules->add_admin_menu();

    //$stepUpPricingEmail = new stepUpPricingEmail();
    //$stepUpPricingEmail->add_admin_menu();
}

function step_up_pricing_wp_enqueue() {
	if ( isset( $_GET['page'] ) && $_GET['page'] === 'step_up_pricing_overview' ) {
		wp_enqueue_style('step-up-pricing-admin-style', plugin_dir_url(__FILE__) . 'assets/css/step-up-pricing-admin.css?' . time());
    	#wp_enqueue_script('transactional-emails-admin-script', plugin_dir_url(__FILE__) . 'assets/js/transactional-emails-admin.js?' . time());
	}
}
