<?php

// This adds information to determine whether a subscription is a gift or not in the admin panel of Woo.

add_filter('manage_edit-shop_subscription_columns', 'webtoffee_alter_order_columns', 30);

function webtoffee_alter_order_columns($columns) {

    $new_columns = ( is_array($columns) ) ? $columns : array();

    unset($new_columns['order_title']);

    $res_array = array_slice($new_columns, 0, 2, true) + array("custom_order_title" => "Subscription") +  array_slice($new_columns, 1, count($new_columns)-1, true);

    return $res_array;
}

add_action('manage_shop_subscription_posts_custom_column', 'webtoffee_alter_order_number_columns', 10, 2);

function is_it_a_gift($subscription) {
    $subscription_obj = wcs_get_subscription($subscription);

    $parentOrder = $subscription_obj->parent_id;

    $subscriptionData = $subscription_obj->meta_data;
    $subscriptionItems = $subscription_obj->get_items();

    $foundVariations = array();

    foreach($subscriptionItems as $item) {
        array_push($foundVariations, $item['variation_id']);
    }

    $findHasSubOrders = array();

    foreach($subscriptionData as $v) {
        $findHasSubOrders['parse'][$v->key] = $v->value;
        if(in_array($v->value, $foundVariations)) {
            $findHasSubOrders['exists'][$v->key] = $v->value;
        }
    }



    if(isset($findHasSubOrders['exists'])) {
        $find_existing = $findHasSubOrders['exists'];

        foreach($find_existing as $exists=>$value) {
            $findDetails = explode("_", $exists);
            $id = array_slice($findDetails, -2, 1)[0];
            $uniqID = end($findDetails);

            $stageInfo = "_gft_stage_{$id}_{$uniqID}";

            $giftShippingDetails = json_decode($findHasSubOrders['parse']["_gft_sd_{$id}_{$uniqID}"]);
            $giftProductID = $findHasSubOrders['parse']["_gft_p_{$id}_{$uniqID}"];

            $hasStage = get_post_meta($parentOrder, "_gft_stage_{$id}_{$uniqID}", true);

            $gifteeEmail = $giftShippingDetails->email_address;

            return $gifteeEmail;
        }
    }
}

function webtoffee_alter_order_number_columns($column, $post_ID) {

    global $post, $woocommerce, $the_order;
    $order = (wc_get_order(wcs_get_subscription($post_ID)->parent_id));

    if(!$order) {
        return;
    }

    $billingFirstName = ($order->get_billing_first_name() . '') ?? null;
    $billingLastName = ($order->get_billing_last_name() . '') ?? null;
    $billingAddressOne = ($order->get_billing_address_1()) ?? null;
    $billingAddressTwo = ('<br/>' . $order->get_billing_address_2()) ?? null;
    $billingAddressCity = ($order->get_billing_city(). ', ') ?? null;
    $billingAddressState = ($order->get_billing_state() . ' ') ?? null;
    $billingAddressZIP = ($order->get_billing_postcode()) ?? null;
    $billingEmailAddress = ('<br/><br/>Email: ' . $order->get_billing_email()) ?? null;

    if($billingFirstName) {
        $orderName = $billingFirstName;
    }

    if ($billingFirstName && $billingLastName) {
            $orderName = $billingFirstName . ' ' . $billingLastName;
    }

    $orderPlacedBy = [$order->get_user_id(), $orderName];

    $orderName = "<a href='/wp-admin/user-edit.php?user_id={$orderPlacedBy[0]}' target='_blank'>{$orderName}</a>";


    $endContxt = "";

    if ($column === 'custom_order_title') {
        $gift = is_it_a_gift($post_ID);

        if($gift) {
            $purchaser = "<a href='/wp-admin/user-edit.php?user_id={$orderPlacedBy[0]}' target='_blank'>{$orderName}</a>";

            $giftee_user = get_user_by("email", $gift);

            if(!empty($giftee_user)) {
                 $gifteeID = get_user_by("email", $gift)->ID;

                if($gifteeID) {
                    $gift = "<a href='/wp-admin/user-edit.php?user_id={$gifteeID}' target='_blank'>{$gift}</a>";
                }
            }

            $orderName = $gift;
            $endContxt = " by <strong>{$purchaser}</strong>";
        }

        $toolTip = "Billing: {$orderPlacedBy[1]}<br/>{$billingAddressOne}{$billingAddressTwo}<br/>{$billingAddressCity}{$billingAddressState}{$billingAddressZIP}{$billingEmailAddress}";
        $context = "<a href='/wp-admin/post.php?post={$post_ID}&action=edit' target='_blank'><strong>#{$post_ID}</strong></a> for <strong>{$orderName}</strong>{$endContxt}";

        echo '<span style="margin-bottom:2px;" class="tips" data-tip="' . __($toolTip, 'mypluginname') . '"><span>' . $context . '</span></span><br />';
    }
}