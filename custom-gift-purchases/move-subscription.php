<?php

// This will move a gifted subscription into the holding user's - as defined by the option 'gifter_user_made' - possession.

function push_out_subscription($subscription_obj, $product, $days, $giftee_email = '') {
	$start_date = gmdate('Y-m-d H:i:s', strtotime("+{$days} Days"));
	$dates = array(
		'trial_end'    => WC_Subscriptions_Product::get_trial_expiration_date($product, $start_date ),
		'next_payment' => WC_Subscriptions_Product::get_first_renewal_payment_date($product, $start_date ),
		'end'          => WC_Subscriptions_Product::get_expiration_date($product, $start_date ),
	);

	return $subscription_obj->update_dates($dates);
}

function process_gift_scheduled_orders() {
	global $wpdb;

	$find_future_dated_orders_query = "
	    (SELECT DISTINCT pm.post_id
	    FROM {$wpdb->prefix}posts AS p
	    JOIN {$wpdb->prefix}postmeta AS pm ON p.ID = pm.post_id
        JOIN {$wpdb->prefix}postmeta AS pm1 ON p.ID = pm1.post_id
	    WHERE p.post_type IN ( 'shop_subscription')
	        AND p.post_status IN ('wc-active')
	        AND pm.meta_key LIKE '_gft_stage_%'
	        AND pm.meta_value not in(20,30,50)
			AND pm1.meta_key LIKE '_gft_fd_%'
	        AND pm1.meta_value <= UNIX_TIMESTAMP()
			);
	";
	// UNIX_TIMESTAMP()
	$learndash_fd_query="SELECT DISTINCT p.ID FROM 
	gpm_gfp_learndash_permissions gfp 
	join {$wpdb->prefix}posts as p on p.ID=gfp.order_id
	where gifted=0";

	$find_future_dated_learndash_results=$wpdb->get_col($learndash_fd_query);
	$find_future_dated_orders_results = $wpdb->get_col($find_future_dated_orders_query);

	if($find_future_dated_learndash_results){
		 foreach ($find_future_dated_learndash_results as $ld_order_id) {
		gfp_process_learndash_gifts($ld_order_id, false);
		if(empty(get_post_meta($ld_order_id, "_gft_notify", true))) {
		email_builder($ld_order_id);
		}
		 }
	}
	if ($find_future_dated_orders_results) {
	    foreach ($find_future_dated_orders_results as $order_id) {
			extends_update_status($order_id,0,0, true);
			$nl_order_id='';
			try {
				$subscription_obj = wcs_get_subscription($order_id);
				if (!empty($subscription_obj)) {
					$nl_order_id = $subscription_obj->parent_id;
				}
			} catch (Exception $e) { }
			if(empty(get_post_meta($nl_order_id, "_gft_notify", true))) {
			email_builder($nl_order_id);
			}
	    }
	}
}

function change_subscription_owner($subscription, $user_id, $giftee_email = '', $parent_order = '', $stage_info = '', $current_stage = '', $force_gift = false, $future_dated = false, $giftProductID ='', $parent_id ='') {
	$order_instance = wc_get_order($subscription);
	$order_user_id = $order_instance->get_user_id();
	if (
	    ($order_user_id == get_option('gifter_user_made') && $current_stage == 10 && !$force_gift) ||
	    ($order_user_id == get_option('gifter_user_made') && $current_stage == 40 && $future_dated)
	) {
	    return false;
	}
	
	$u = new WP_User($user_id);
	$u->remove_role('customer');
	$u->add_role('subscriber');
	
	$order_instance->set_customer_id($user_id);

	try {
		WCS_Customer_Store::instance()->delete_cache_for_user($user_id);
	} catch (Exception $e) { }

	if($user_id == get_option('gifter_user_made')) {
		$theGifter_user_id = get_option('gifter_user_made');
		$theGifter = get_user_by("id", $theGifter_user_id)->display_name;
		gift_transfer_membership($subscription, $order_user_id, $theGifter_user_id);
		gfp_leave_order_notes($subscription, "Sub is a gift, but can not find user to assign to. Moved to user '{$theGifter}' until gift is claimed by '{$giftee_email}' systematically.");
		update_post_meta($parent_order, $stage_info, esc_sql(10));
		if($future_dated) {
			if($current_stage != 40) {
				gfp_leave_order_notes($subscription, "Sub future dated for '{$future_dated}'.");
			}
			update_post_meta($parent_order, $stage_info, esc_sql(40));
		}
	} else {
		// === Giftee path ===
		try {
			$status = wcs_get_subscription(is_array($subscription) ? $subscription->id : $subscription)->get_status();
		} catch (Exception $e) {
		    $status = 'not active';
		}

		if ($status === 'active') {
			$giftee_user_id = get_user_by('email', $giftee_email)->ID;
			$subID = is_array($subscription) ? $subscription->id : $subscription;
			$get_parent_order = $order_instance->get_parent();
			$parent_order_user_id = $get_parent_order->get_user_id();
			// Get all subscriptions for the gifter
    		$gifter_subscriptions = wcs_get_users_subscriptions( $parent_order_user_id );
    		$has_other_active = false;
			foreach ( $gifter_subscriptions as $sub_id => $sub ) {		
				if ( $sub_id == $subID ) {
					continue;
				}
				// Check if subscription is active
				if ( $sub->has_status( 'active' ) ) {

					// Loop through items in this subscription
					foreach ( $sub->get_items() as $item ) {
						$product_id = $item->get_product_id();
						if ( intval($product_id) === intval($giftProductID) ) {
							$has_other_active = true;
							break 2; // break out of both loops
						}
					}
				}
			}
			// Only create membership if gifter has at least one other active subscription
			if ( $has_other_active ) {
				gfp_create_membership_for_giftee($subscription, $giftee_user_id, $parent_order_user_id, $giftProductID, $parent_id);
				update_post_meta($parent_order, $stage_info, esc_sql(20));
				update_post_meta($subscription, $stage_info, esc_sql(20));
			} else {
				gfp_leave_order_notes($subscription, "Sub is a gift claimed by '{$giftee_email}' systematically.");						    
		    	gfp_transfer_membership_via_woo($subscription, $giftee_user_id);	
		    	gift_transfer_membership($subscription, $order_user_id, $giftee_user_id);
				switch_next_payment_end_date($subscription);
				update_post_meta($parent_order, $stage_info, esc_sql(20));
				update_post_meta($subscription, $stage_info, esc_sql(20));
				
			}	
		    
		} else {
		    gfp_leave_order_notes($subscription, "Sub is not active, unable to gift.");
		}
	}
	update_post_meta($subscription, '_requires_manual_renewal', true);
	$order_instance->save();
}

function gfp_leave_order_notes($identification, $note) {
	$order_instance = wc_get_order($identification);
    $order_instance->add_order_note(__("[GFP] $note"));
}

function gft_get_membership_postid($subID, $gifter_user_id){
	global $wpdb;
	$postID = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT pm.post_id
             FROM {$wpdb->prefix}postmeta AS pm
             JOIN {$wpdb->prefix}posts AS p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_subscription_id' 
             AND pm.meta_value = %d
             AND p.post_author = %d",
            $subID,
            $gifter_user_id
        )
    );
	return $postID;
}

function gfp_create_membership_for_giftee($subscription, $target_user_id, $gifter_user_id, $giftProductID, $parent_id) {
    global $wpdb;

    $subID = is_array($subscription) ? $subscription->id : $subscription;

    // Get all memberships linked to this subscription (candidate memberships)
    $postID = gft_get_membership_postid($subID, $gifter_user_id);

	if(!empty($postID)){
        if ($target_user = get_user_by('id', $target_user_id)) {
            if ($user_membership = wc_memberships_get_user_membership($postID)) {

                try {
                    // Get gifter’s original order
                    $giftee_order_id = $user_membership->get_order_id();

                    // --- Step 1: Unlink subscription and order from gifter's membership ---
                    delete_post_meta($postID, '_subscription_id');
                    delete_post_meta($postID, '_order_id');

                    // Restore gifter's original subscription and order
                    gfp_restore_gifter_membership($gifter_user_id, $giftProductID, $parent_id, $postID, $subID);

                    // --- Step 2: Create new membership for giftee ---
                    $new_membership = wc_memberships_create_user_membership(array(
                        'plan_id'        => $user_membership->get_plan_id(),
                        'user_id'        => $target_user->ID,
                        'product_id'     => $user_membership->get_product_id(),
                        'order_id'       => $giftee_order_id,
                        'status'         => 'active',
                        'subscription_id'=> $subID,
                        'start_date'     => gmdate('Y-m-d H:i:s'), // start now
                        'end_date'       => $user_membership->get_end_date('mysql'),
                    ));

                    if ($new_membership && !is_wp_error($new_membership)) {
                        $new_membership_id = $new_membership->get_id();

						$variationID = $giftProductID; // fallback to parent if not found
						$subscription_obj = wcs_get_subscription($subID);
						if ( $subscription_obj ) {
							foreach ( $subscription_obj->get_items() as $item ) {
								$product = $item->get_product();
								if ( $product && $product->is_type('variation') ) {
									$parent_id = $product->get_parent_id();
									// Check if this variation belongs to the parent product 
									if ( $parent_id == $giftProductID ) {
										$variationID = $product->get_id();
										break;
									}
								}
							}
						}

						//update subscription and membership with end date
						$period = WC_Subscriptions_Product::get_period($variationID);
						$interval = WC_Subscriptions_Product::get_interval($variationID);
						$current_payment = date("Y-m-d H:i:s");
						$new_sub_mem_end_date = date('Y-m-d H:i:s', strtotime("+{$interval} {$period}s", strtotime($current_payment)));
						$current_subscription_obj = (wcs_get_subscription($subID));
						$dates = array(
							'next_payment' => '',
							'end'          => $new_sub_mem_end_date,
						);
						$current_subscription_obj->update_dates($dates);
						$current_subscription_obj->save();
						update_post_meta($new_membership_id, '_end_date', $new_sub_mem_end_date );
						
                        // Link subscription + order to giftee membership
                        update_post_meta($new_membership_id, '_subscription_id', $subID);
                        update_post_meta($new_membership_id, '_order_id', $giftee_order_id);

                        gfp_leave_order_notes($subID, "New membership {$new_membership_id} created for giftee user ID {$target_user->ID}.");
                    } else {
                        gfp_leave_order_notes($subID, "Failed to create new membership for giftee user ID {$target_user->ID}.");
                    }

                } catch (Exception $e) { 
                    gfp_leave_order_notes($subID, "Error while creating membership for giftee: " . $e->getMessage());
                }
            }
        }
    }
}

function gfp_restore_gifter_membership($gifter_user_id, $giftProductID, $parent_id, $postID, $subID) {
    // Get active subscription(s) for this user for the membership product
    $subscriptions = wcs_get_users_subscriptions($gifter_user_id);
    $original_subscription = null;
    foreach ($subscriptions as $subscription) {
		//check product, status and exclude same given subscription ID
        if ($subscription->has_product($giftProductID) && $subscription->get_status() === 'active' && $subscription->get_id() != $subID) {
            $original_subscription = $subscription;
            break;
        }
    }

    if (!$original_subscription) {
        return; // no active subscription found for this product
    }

    $subscription_id = $original_subscription->get_id();
    $order_id = $original_subscription->get_parent_id(); // usually the original order
	update_post_meta($postID, '_subscription_id', $subscription_id);
	update_post_meta($postID, '_order_id', $order_id);
	gfp_leave_order_notes($order_id,"Restored original subscription {$subscription_id} and order {$order_id} to gifter membership {$postID}.");

}

function gfp_transfer_membership_via_woo($subscription, $target_user_id) {
    global $wpdb;

    $subID = is_array($subscription) ? $subscription->id : $subscription;

    $post_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT pm.post_id
            FROM {$wpdb->prefix}postmeta AS pm
            JOIN {$wpdb->prefix}posts AS p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_subscription_id' AND pm.meta_value = %d",
            $subID
        )
    );

    foreach ($post_ids as $postID) {
        if ($target_user = get_user_by('id', $target_user_id)) {
            if ($user_membership = wc_memberships_get_user_membership($postID)) {
                try {
                    $user_membership->transfer_ownership($target_user);
                    gfp_leave_order_notes($subID, "Membership transfered through WooCommerce.");
                } catch (Exception $e) { }
            }
        }
    }
}

function gift_transfer_membership($subscription, $old_user_id, $new_user_id) {
	global $wpdb;

	$subID = is_array($subscription) ? $subscription->id : $subscription;

	$subscription_obj = wcs_get_subscription($subID);

	$subscriptionItems = $subscription_obj->get_items();
	$order_instance = wc_get_order($subscription);

	foreach ($subscriptionItems as $item) {
		$product_id = ($item['product_id']);
		$variation_id = ($item['variation_id']);

		$find_whether_membership_product_query = "
		    SELECT post_id
				FROM wp_postmeta
				WHERE post_id IN (
				    SELECT post_id
				    FROM wp_postmeta
				    WHERE meta_key = '_product_ids' AND meta_value LIKE '%{$product_id}%'
				)
				AND meta_key = '_members_area_sections' AND meta_value IS NOT NULL;
		";

		$find_whether_membership_product_results = $wpdb->get_results($find_whether_membership_product_query);

		if ($find_whether_membership_product_results) {
    		foreach ($find_whether_membership_product_results as $whether_membership_product_result) {
    			$membership_product_post_id = $whether_membership_product_result->post_id;

				$move_membership_to_giftee = "
				    UPDATE {$wpdb->prefix}posts
				    SET post_author = {$new_user_id}
				    WHERE post_author = {$old_user_id}
				      AND post_type = 'wc_user_membership'
				      AND post_parent = $membership_product_post_id;
				";

				$move_membership_to_giftee_result = $wpdb->query($move_membership_to_giftee);

				if ($move_membership_to_giftee_result === false) {
				   $order_instance->add_order_note(__("Issue in moving membership to correct user."));
				} else {
				   $order_instance->add_order_note(__("Successfully moved membership to designated user."));
				}
			}
		}
	}
}

add_action('woocommerce_subscription_status_updated', 'process_gift_scheduled_orders', 100, 0);

function switch_next_payment_end_date($subscription) {
    $subID = is_array($subscription) ? $subscription['id'] : $subscription;
    $subscription_obj = wcs_get_subscription($subID);
    if (! $subscription_obj) {
        return;
    }

    foreach ($subscription_obj->get_items() as $item) {
        $product_id   = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $product = wc_get_product($variation_id ? $variation_id : $product_id);
        if (! $product) {
            continue;
        }

        $period   = WC_Subscriptions_Product::get_period($product);
        $interval = WC_Subscriptions_Product::get_interval($product);

        $sub_next_payment = $subscription_obj->get_date('start');
        if (! $sub_next_payment) {
            continue;
        }

        $end_date = date('Y-m-d H:i:s', strtotime("+{$interval} {$period}s", strtotime($sub_next_payment)));

        $subscription_obj->add_order_note(__("Adjusted subscription end date to {$end_date}, preventing auto-renewal for gifter."));

        $subscription_obj->update_dates([
			'next_payment' => '',
            'end' => $end_date,
        ]);
        $subscription_obj->save();
    }
}


function extends_update_status($subscription, $new_status = '', $old_status = '', $force_gift = false) {
	try {
		$subID = is_array($subscription) ? $subscription->id : $subscription;

		$subscription_obj = wcs_get_subscription($subID);

		if(empty($subscription_obj)) {
			throw new Exception('not a subscription');
		}

		$parentOrder = $subscription_obj->parent_id;
		if($subscription_obj->get_user_id() === get_option('gifter_user_made') && !$force_gift) {
			return;
		}

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
		if (empty($findHasSubOrders)) {
			return;
		}
		foreach($findHasSubOrders['exists'] as $exists=>$value) {
			$findDetails = explode("_", $exists);
			$id = array_slice($findDetails, -2, 1)[0];
			$uniqID = end($findDetails);

			$stageInfo = "_gft_stage_{$id}_{$uniqID}";

			$giftShippingDetails = json_decode($findHasSubOrders['parse']["_gft_sd_{$id}_{$uniqID}"]);
			$giftProductID = $findHasSubOrders['parse']["_gft_p_{$id}_{$uniqID}"];
			$giftProduct = wc_get_product( $giftProductID );

			//avoid to moved subscription
			if ( ! $giftProduct || ! $giftProduct->is_type( array( 'subscription', 'variable-subscription', 'subscription_variation' ) ) ) {
				continue;
			}
			if ( $giftProduct && $giftProduct->is_type( 'variation' ) ) {
				$parent_id = $giftProduct->get_parent_id();
			}else {
    			$parent_id = $giftProduct->get_id(); // It's already a main 
			}

			$hasStage = get_post_meta($subID, "_gft_stage_{$id}_{$uniqID}", true);

			$gifteeEmail = $giftShippingDetails->email_address;

			$futureDated = get_post_meta($parentOrder, "_gft_fd_{$id}_{$uniqID}", true);
			if(!$futureDated) {
				continue;
			}

			try {
				$current_date = CURRENT_TIME;
				if(!empty($futureDated) && $futureDated >= $current_date) {
					change_subscription_owner($subID, get_option('gifter_user_made'), $gifteeEmail, $parentOrder, $stageInfo, $hasStage, $force_gift, $giftProductID,$parent_id);
					continue;
				}
			} catch(Exception $e) { }
			if(!empty($hasStage) && ($hasStage == 20 || $hasStage == 30)) {
				continue;
			}
			if(email_exists($gifteeEmail)) {
				global $wpdb;

				$new_user_id = $user_id = get_user_by("email", $gifteeEmail)->ID;

				$subscription_id = $wpdb->get_var("
					SELECT DISTINCT p.ID as subscription_id
					FROM {$wpdb->prefix}posts AS p
					JOIN {$wpdb->prefix}postmeta AS pm 
					ON p.ID = pm.post_id
					JOIN {$wpdb->prefix}woocommerce_order_items AS oi 
					ON oi.order_id = p.ID
					JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS oim 
					ON oi.order_item_id = oim.order_item_id
					WHERE p.post_type = 'shop_subscription'
					AND p.post_status in ('wc-active','pending-cancel')
					AND pm.meta_key = '_customer_user'
					AND pm.meta_value = '$user_id'
					AND oim.meta_key IN ('_product_id')
					AND oim.meta_value = '$parent_id'
					AND p.ID !='$subID'
				");
				$giftGiven = false;
				$period = WC_Subscriptions_Product::get_period($giftProductID);
				$interval = WC_Subscriptions_Product::get_interval($giftProductID);
				if(!empty($subscription_id)){
					$current_subscription_obj = (wcs_get_subscription($subscription_id));
					$current_subscription_status = $current_subscription_obj->get_status();
					if($current_subscription_status=='active'){
					$current_subscriptionItems = $current_subscription_obj->get_items();
					foreach ($current_subscriptionItems as $item) {
						$product_id = ($item['product_id']);
						$variation_id = ($item['variation_id']);

						if($product_id == $parent_id) {
							$giftGiven = true;
							$endexist = false;
							$current_sub_next_payment  = $current_subscription_obj->get_date('end');
							if(!empty($current_sub_next_payment)){
								$endexist = true;
							}
							if(empty($current_sub_next_payment )) {
								$current_sub_next_payment  = $current_subscription_obj->get_date('next_payment');
							}

							if(empty($current_sub_next_payment)) {
								$current_sub_next_payment = date("Y-m-d H:i:s");
							}
							
							$start_date = date('Y-m-d H:i:s', strtotime("+{$interval} {$period}s", strtotime($current_sub_next_payment)));

							if ( $endexist ) {
								// End date case
								$dates = array(
									'trial_end'    => WC_Subscriptions_Product::get_trial_expiration_date( $giftProductID, $start_date ),
									'next_payment' => '',
									'end'          => $start_date,
								);
							} else {
								// Next payment case
								$dates = array(
									'trial_end'    => WC_Subscriptions_Product::get_trial_expiration_date( $giftProductID, $start_date ),
									'next_payment' => $start_date,
									'end'          => '',
								);
							}

							$current_subscription_obj->add_order_note(__("Sub gift for '{$gifteeEmail}', but user has a sub - time added systematically from subscription '{$subscription_id}'."));

							$current_subscription_obj->update_dates($dates);

							$current_subscription_obj->save();

							$order_instance = wc_get_order($subID);
							$old_user_id = $order_instance->get_user_id();

							//gfp_transfer_membership_via_woo($subscription_id, $new_user_id);
							//gift_transfer_membership($current_subscription_obj, $old_user_id, $new_user_id);
							update_post_meta($subID, 'expired_subscription_due_to_gifting',true);
							
							update_post_meta($subID, $stageInfo, 30);
							$current_time = current_time( 'timestamp', true ); // GMT
							$subdates = array(
								'next_payment' => '',
								'end'          => date('Y-m-d H:i:s', strtotime($subscription_obj->get_date('last_payment')))
								// 'cancelled', date( 'Y-m-d H:i:s', $current_time )
							);
							$get_parent_order = $order_instance->get_parent();
							$parent_order_user_id = $get_parent_order->get_user_id();

							$postID = gft_get_membership_postid($subID, $parent_order_user_id);
							gfp_restore_gifter_membership($parent_order_user_id, $giftProductID, $parent_id, $postID, $subID);
							//Code to update giftee membership to expired as gifter don't have this membership and subscription as he gift to someone.
							$subscription_obj->add_order_note(__("Updated the newly gifted subscription '{$subID}' to end at the current time, as the subscription duration was already extended in sub '{$subscription_id}'"));
							$subscription_obj->update_dates($subdates);

							$subscription_obj->save();
							$expire_new_subscription = "
							UPDATE {$wpdb->prefix}posts
							SET post_status = 'wc-expired'
							WHERE ID = {$subID}
							AND post_type = 'shop_subscription';
							";
							$expire_new_subscription_result = $wpdb->query($expire_new_subscription);

							if ($expire_new_subscription_result === false) {
							$subscription_obj->add_order_note(__("Issue in updating subscription status."));
							} else {
							$subscription_obj->add_order_note(__("Successfully moved current subscription to expired."));
							}

							$expire_new_membership = "
								UPDATE {$wpdb->prefix}posts
								SET post_status = 'wcm-expired'
								WHERE ID IN (
											SELECT post_id
											FROM {$wpdb->prefix}postmeta
											WHERE meta_key = '_subscription_id'
											AND meta_value = {$subID}
										)
										AND post_type = 'wc_user_membership'";
							$wpdb->query($expire_new_membership);							
						}
					}
					}else{
						$giftGiven = true;
						extend_membership_only($subID,$period,$interval);						
						update_post_meta($subID, $stageInfo, 30);
					}
				}
				if(!$giftGiven) {
					change_subscription_owner($subID, $user_id, $gifteeEmail, $parentOrder, $stageInfo, $hasStage, $force_gift, $giftProductID, $parent_id);

				}
			} else {
				change_subscription_owner($subID, get_option('gifter_user_made'), $gifteeEmail, $parentOrder, $stageInfo, $hasStage, $force_gift, $giftProductID, $parent_id);
			}
		}
	} catch (Exception $e) { }
}


function extend_membership_only($subscription, $interval, $period) {    
	global $wpdb;

    $subID = is_object($subscription) ? $subscription->id : $subscription;
	$subscription_obj = wcs_get_subscription($subID);

    $postID = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT pm.post_id
            FROM {$wpdb->prefix}postmeta AS pm
            JOIN {$wpdb->prefix}posts AS p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_subscription_id' AND pm.meta_value = %d",
            $subID
        )
    );

    if(!empty($postID)){
            if ($membership = wc_memberships_get_user_membership($postID)) {
                $membership_id = $membership->get_id();
				$user_id = $membership->get_user_id();
				$current_expiration_date = method_exists( $membership, 'get_end_date' ) ? $membership->get_end_date() : null;
				$current_expiration = $current_expiration_date ? strtotime( $current_expiration_date ) : time();
				$new_expiration = strtotime("+$interval $period", $current_expiration);
				$new_expiration = strtotime("+$interval $period", $current_expiration);
				$new_expiration_date = date('Y-m-d H:i:s', $new_expiration);
				update_post_meta( $membership_id, '_end_date', $new_expiration_date );
				$subscription_obj->add_order_note(__("Membership '{$membership_id}' has been extended, but subscription '{$subID}' was not extended because its status is 'pending cancellation'."));
				$membership->add_note("Membership extended by $interval $period (new expiration: $new_expiration_date) due to receiving a gift.");

				if ( method_exists( $membership, 'refresh_status' ) ) {
					$membership->refresh_status();
				}
			}
    }
}

function wp_gpm_custom_logger( $message = '' ) {
    // Auto-detect file and line
    $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 )[0];
    $file = isset( $backtrace['file'] ) ? wp_basename( $backtrace['file'] ) : 'unknown';
    $line = isset( $backtrace['line'] ) ? $backtrace['line'] : 0;

    // Serialize message if it's an array or object
    if ( is_array( $message ) || is_object( $message ) ) {
        $message = maybe_serialize( $message );
    }

    // Build log string
    $log_entry = sprintf(
        "[%s] %s (Line %d): %s%s",
        current_time( 'mysql' ),
        $file,
        $line,
        $message,
        PHP_EOL
    );

    // Log file path (same directory as this file)
    $log_file = __DIR__ . '/gpm_custom_logs.log';

    // Append log entry to file
    file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
}