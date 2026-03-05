<?php

	/*
	Plugin Name: Cancellation Action
	Plugin URI:
	Description: Manages situations in which a user tries to terminate their subscription.
	Version: 1.0
	Author:  James
	Author URI: http://midtc.com/
	License:
	*/

	if (! defined('ABSPATH')) exit;

	include($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');

	function cancellation_action_generate_coupon($sub = 3900362, $experation = "7", $amount = 10) {
		global $woocommerce;

		$uniCode = 'CAR' . str_replace(['0', 'F'], '', strtoupper(uniqid()));

		$coupon = new WC_Coupon($uniCode);
		$status = json_decode($coupon, true)['status'];

		if (!empty($status)) {
			return cancellation_action_generate_coupon($sub, $experation, $amount);
		}

		// Load the subscription to get user info
		$subscription = wcs_get_subscription($sub);
		if (!$subscription) return false;

		$user_email = $subscription->get_billing_email();

		$coupon = new WC_Coupon();
		$coupon->set_code($uniCode);
		$coupon->set_description("[AUTO] Subscription cancellation retention attempt for #{$sub}.");
		$coupon->set_discount_type('recurring_fee');
		$coupon->set_amount($amount);
		$coupon->set_date_expires(date('Y-m-d', strtotime("+{$experation} days"))); // Correct format for WC
		$coupon->set_free_shipping(false);
		$coupon->set_individual_use(true);
		$coupon->set_exclude_sale_items(false);
		$coupon->set_usage_limit(1);
		$coupon->set_usage_limit_per_user(1);
		$coupon->add_meta_data('_wcs_number_payments', 1, true);

		// Restrict coupon to user's email
		if (!empty($user_email)) {
			$coupon->set_email_restrictions([$user_email]);
		}

		// Limit to all subscription product types
		$subscription_types = [
			'subscription',
			'downloadable_subscription',
			'virtual_subscription',
			'variable-subscription'
		];

		$subscription_product_ids = wc_get_products([
			'status' => 'publish',
			'type'   => $subscription_types,
			'return' => 'ids',
			'limit'  => -1,
		]);

		if (!empty($subscription_product_ids)) {
			$coupon->set_product_ids($subscription_product_ids);
		}

		// Only for initial payment
		$coupon->add_meta_data('_wcs_restrict_to_initial_payment', 'yes', true);

		$coupon->save();

		// Save coupon to subscription
		cancellation_action_update_sub_meta_data($sub, $uniCode);

		return $uniCode;
	}

    function cancellation_action_update_sub_meta_data($sub, $code) {
    	update_post_meta($sub, '_cancellation_action_coupon_code_generated', $code);
    }

	function cancellation_action_after_my_account($subscription) {
		if (!is_a($subscription, 'WC_Subscription')) {
			return;
		}

	    $subscription_number = $subscription->get_id();

	    $product_item = $subscription->get_items();
	    $iscanceled = $cancellation_action = false;

	    foreach ($product_item as $item) {
	        $product_id = $item->get_product_id();
	        if($product_id) {
	        	$product_obj = wc_get_product($product_id);
	        	$sku = $product_obj->get_sku();
	        	
	        	if($sku = 'SDMEM') {
	        		$cancellation_action = true;
	        	}
	        }
	    }
    	
    	if(!$cancellation_action) {
    		return;
    	}

    	$subscription = wcs_get_subscription($subscription_number);

		if ($subscription && ($subscription->get_status() === 'cancelled' || $subscription->get_status() === 'pending_cancel')) {
			$iscanceled = true;
		}

		?>
		<div id="myModal" class="modal">
		  <div class="modal-overlay">
		    <div class="modal-content">
			<div class="overlay-content"> <!-- New overlay content -->
				<div class="loader"></div>
			</div>
		      <div class="modal-header">
		        <h4 style="font-family: 'Roboto Flex' !important;" id="cancellation-action-modal-title"></h4>
		        <span class="close" id="closeModal" style="font-size: 2.125rem !important;">&times;</span>
		      </div>
		      <div class="modal-body" id="cancellation-action-modal-body"></div>
		      <div class="modal-footer">
		        <div class="modal-buttons">
		          <button id="continueButton">Continue</button>
		          <button id="cancelButton">Cancel</button>
		        </div>
		      </div>
		    </div>
		  </div>
		</div>
		<?php
	    wp_enqueue_style('cancellation-action-style', plugin_dir_url(__FILE__) . 'assets/css/style.css?' . time());

		$meta_key = '_cancellation_action_coupon_code_generated';
		$cancellation_action_coupon = get_post_meta($subscription_number, $meta_key, true);

		if (!$cancellation_action_coupon) {
			$cancellation_action_coupon = cancellation_action_generate_coupon($subscription_number, 30, 10);

			add_post_meta($subscription_number, $meta_key, $cancellation_action_coupon);
		}

	    if (!isset($_COOKIE['cancellationAction'])) {
			$data_to_pass = array(
			    'cancellation_action_wp_nonce' => wp_create_nonce('cancellation_action_wp_nonce'),
			);
		} else {
			$cancellation_action_cookie = json_decode(stripslashes($_COOKIE['cancellationAction']), true);

			if($cancellation_action_cookie) {
				$cancellation_action_error = $cancellation_action_cookie['error'];
				$cancellation_action_msg = $cancellation_action_cookie['msg'];
				$cancellation_action_nonce = $cancellation_action_cookie['wp_nonce'];
				
				if(wp_verify_nonce($cancellation_action_nonce, 'cancellation_action_wp_nonce')) {
					$data_to_pass = array(
			    		'cancellation_action_coupon_generation' => $cancellation_action_coupon,
					);
				}
			}
		}

		wp_enqueue_script('cancellation-action-script', plugin_dir_url(__FILE__) . 'assets/js/script.js?' . time(), array('jquery'), '1.0', true);
		wp_localize_script('cancellation-action-script', 'cancellation_action', $data_to_pass);
	}

	add_action('woocommerce_subscription_details_after_subscription_table', 'cancellation_action_after_my_account');
