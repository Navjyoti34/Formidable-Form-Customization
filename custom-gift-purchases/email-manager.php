<?php

// This will manage emails sent to clients regarding gift purchases.

function so_39251827_remove_subscription_details( $order, $sent_to_admin, $plain_text, $email ) {
	if(!empty(get_post_meta($order->get_id(), '_gft_gp', true))) {
		remove_action( 'woocommerce_email_after_order_table', array( 'WC_Subscriptions_Order', 'add_sub_info_email' ), 15, 3 );
	}
}
  
function bbloomer_add_content_specific_email( $order, $sent_to_admin, $plain_text, $email ) {
	$order_id = $order->get_id();
	if(!empty(get_post_meta($order_id, '_gft_gp', true))) {
		echo "<h2 class='email-upsell-title'>Your Order Contained Gifts!</h2><p class='email-upsell-p'>We've also let the recipients of the gift(s) in your order know their item(s) are ready for redemption.</p>";
		if(empty(get_post_meta($order_id, "_gft_notify", true))) {
			email_builder($order_id);
		}		
	}
}

function email_builder($id) {
	global $wpdb;

	$order = wc_get_order($id);
	$items = $order->get_items();
    
	foreach ($items as $item_id => $item) {
		$product = $item->get_product();

		if ($product === false) {
			error_log("Invalid product for item ID: {$item_id}");
			continue;
		}

		if ($product->is_type('variation')) {
			$variation_id = $product->get_id(); // Store variation ID
			$product_id = $product->get_parent_id();
		} else {
			$product_id = $product->get_id();
			$variation_id = null;
		}

        $gifteeFirstName = $gifteeEmail = $gifteeLastName = $giftMessage = "";

		$gifteeFirstName = $billingFirstName = $order->get_billing_first_name();
		$billingLastName = $order->get_billing_last_name();
        $gifteremail = $order->get_billing_email();

		try {
			$findGifts = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_key LIKE '_gft_p_%%' AND (meta_value = %d OR meta_value = %d)",
				$id,
				$product_id,
				$variation_id ? $variation_id : $product_id
			));
			if (!$findGifts || count($findGifts) === 0) {
				throw new Exception('not a gift - next');
			}
            // Loop for all giftee
			foreach ($findGifts as $giftMeta) {
				$checkID = explode("_", $giftMeta->meta_key);
				$checkIDSplit = $checkID[count($checkID) - 2];
				$uniqID = end($checkID);

				$bundle_product = get_post_meta($order->get_id(), "_gft_bundle_{$checkIDSplit}_{$uniqID}", true);

                if($bundle_product) {
                    throw new Exception('bundle product do not email');
                }

                $notified = get_post_meta($order->get_id(), "_gft_notify_{$checkIDSplit}_{$uniqID}", true);

				if($notified) {
                    throw new Exception('already notified');
                }

				$grabShippingData = get_post_meta($id, "_gft_sd_{$checkIDSplit}_{$uniqID}", true);
				$giftMessage = get_post_meta($id, "_gft_msg_{$checkIDSplit}_{$uniqID}", true);
				$futureDateGift = get_post_meta($id, "_gft_fd_{$checkIDSplit}_{$uniqID}", true);

				$current_date = CURRENT_TIME;

				// Skip future-dated gifts
				if (!empty($futureDateGift) && $futureDateGift >= $current_date) {
					error_log("Skipping future-dated gift for recipient: {$uniqID}");
					continue;
				}

				$shippingData = json_decode($grabShippingData);

                $gifteeEmail = sanitize_email($shippingData->email_address);
				$gifteeFirstName = sanitize_text_field($shippingData->first_name);
				$gifteeLastName = sanitize_text_field($shippingData->last_name);	

				update_post_meta($id, "_gft_notify_{$checkIDSplit}_{$uniqID}", true);

				$futureDateGiftArray = array("future_date" => $futureDateGift, "complete" => true);

				update_post_meta($order->get_id(), "_gft_df_{$checkIDSplit}_{$uniqID}", json_encode($futureDateGiftArray));

				// Determine product details
				$productName = $product->get_title();
                $productLink = $product->get_permalink();

                $siteName = get_bloginfo('name');
                $siteURL = get_bloginfo('url');

				$isVirtual = false;
		        $isSub = false;

                if($product->is_virtual()) {
                    $isVirtual = true;
                }

                if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
                    $isSub = true;
                    $product = $item->get_product();
                }

				$downloads = array();
				if ($product->is_downloadable()) {
					foreach ($product->get_downloads() as $file_id => $file) {
						$downloads[] = do_shortcode($file['file']); // Execute S3 shortcode
					}
				}

				// Email content
				$emailHeader = "Thanks for shopping with us";
				$emailTitle = "Your order from {$order->get_billing_email()} is complete.";
				$beforeOrderTable = "We have finished processing your order.";
				$afterOrderTable = "For any issues, please contact support.";

				if (!empty($gifteeEmail)) {
					$emailHeader = 'Just for you!';
					$emailTitle = "{$billingFirstName} Sent You a Gift!";
					$beforeOrderTable = "{$billingFirstName} {$billingLastName} has sent you a gift! Here are the details.";
				}
				if(!empty($giftMessage)) {
					$giftMessage = "<p>{$billingFirstName} also left a message: <i>{$giftMessage}</i><p>";
				}
				$afterOrderTable = "";

				if(empty($downloads)) {
			$beforeOrderTable = "We have finished processing your order. your gift should arrive in the mail in 10-14 business days. International orders can take 3-5 weeks depending on location. You can learn more about your gift by clicking the product's title below.";
			$afterOrderTable = "If you have questions or problems downloading your gift, <a href='https://peakmediaproperties.zendesk.com/hc/en-us/requests/new' target='_blank'>click here</a> to contact us.";

			if($isSub) {
				$beforeOrderTable = "We have finished processing your order. You should expect to receive your first issue in the mail within 4-6 weeks of the purchase date, depending upon your location. For additional magazine subscription questions - <a href='https://peakmediaproperties.zendesk.com/hc/en-us/categories/360001988632-Magazine-Questions'>click Here</a>.";
				$afterOrderTable = "If you have questions or problems downloading your gift, <a href='https://peakmediaproperties.zendesk.com/hc/en-us/requests/new' target='_blank'>click here</a> to contact us.";
			}

			if(!empty($gifteeEmail)) {
				$beforeOrderTable = "{$billingFirstName}{$billingLastName} has sent you a gift! your gift should arrive in the mail in 10-14 business days. International orders can take 3-5 weeks depending on location. You can learn more about your gift by clicking the product's title below.";
				$afterOrderTable = "If you have questions or problems downloading your gift, <a href='https://peakmediaproperties.zendesk.com/hc/en-us/requests/new' target='_blank'>click here</a> to contact us.";

				if($isSub) {
					$beforeOrderTable = "{$billingFirstName}{$billingLastName} has sent you a gift! You should expect to receive your first issue in the mail within 4-6 weeks of the purchase date, depending upon your location. For additional magazine subscription questions - <a href='https://peakmediaproperties.zendesk.com/hc/en-us/categories/360001988632-Magazine-Questions'>click Here</a>.";
					$afterOrderTable = "If you have questions or problems downloading your gift, <a href='https://peakmediaproperties.zendesk.com/hc/en-us/requests/new' target='_blank'>click here</a> to contact us.";
				}
			}
		}
		$bundle_products = "";

		if($product->is_type('bundle')) {

			$beforeOrderTable = "{$billingFirstName}{$billingLastName} has sent you a gift!";

			foreach ($product->get_bundled_items()  as $bundled_item_id => $bundled_item ) {
				$product_bundle_item = $bundled_item->get_product_id();

				$product_name = $bundled_item->get_title();

				$bundle_products .= <<<EOT
				<tr class="x_order_item"><td class="x_td" align="left" style="color:#999; border:1px solid #e5e5e5; padding:12px; text-align:left; vertical-align:middle; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif; word-wrap:break-word"><a href="{$productLink}" target="_blank">{$product_name}</a> </td><td class="x_td" align="left" style="color:#999; border:1px solid #e5e5e5; padding:12px; text-align:left; vertical-align:middle; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif">1 </td></tr>
		EOT;
			}
		}
		$sites_faqs = [
		    'artistsnetwork.com' => 'https://peakmediaproperties.zendesk.com/hc/en-us/categories/360001994111-Artists-Network',
		    'interweave.com' => 'https://peakmediaproperties.zendesk.com/hc/en-us/categories/360001988672-Interweave',
		    'quiltingdaily.com' => 'https://peakmediaproperties.zendesk.com/hc/en-us/categories/360001996751-Quilting-Daily',
		    'sewdaily.com' => 'https://peakmediaproperties.zendesk.com/hc/en-us/categories/360004606051-Sew-Daily',
		];

		$faqs = (function($properties) {
		    $current_host = preg_replace('/^www\./', '', implode('.', array_slice(explode('.', $_SERVER['HTTP_HOST']), -2)));
		    if (isset($properties[$current_host]) && !empty($properties[$current_host])) {
		        return $properties[$current_host];
		    } else {
		        return "";
		    }
		})($sites_faqs);
		if(!empty($downloads) || !empty($bundle_products) || $isSub) {
			if(!empty($gifteeEmail)) {
				$freshAccount = createGiftee($gifteeEmail);
				
				$credsTable = <<<EOT
				<div style="margin-bottom:20px"><table class="x_td" cellspacing="0" cellpadding="6" border="1" width="100%" style="color:#999; border:1px solid #e5e5e5; vertical-align:middle; width:100%; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif"><thead><tr><th class="x_td" scope="col" align="left" style="color:#999; border:1px solid #e5e5e5; vertical-align:middle; padding:12px; text-align:left">Username</th><th class="x_td" scope="col" align="left" style="color:#999; border:1px solid #e5e5e5; vertical-align:middle; padding:12px; text-align:left">Password</th></tr></thead><tbody><tr class="x_order_item"><td class="x_td" align="left" style="color:#999; border:1px solid #e5e5e5; padding:12px; text-align:left; vertical-align:middle; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif; word-wrap:break-word">{$freshAccount[2]} </td><td class="x_td" align="left" style="color:#999; border:1px solid #e5e5e5; padding:12px; text-align:left; vertical-align:middle; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif">{$freshAccount[1]} </td></tr></tbody></table></div>
EOT;

				
				if(is_null($freshAccount[1])) {
					$credsTable = "";
				}
			}

			$beforeOrderTable = "Thank you for your purchase! You can access your purchase by <a href='{$siteURL}/my-account/downloads' target='_blank'>clicking here</a>. You can learn more about your gift by clicking the product's title below.";
			$afterOrderTable = "If you have questions or problems downloading your gift, <a href='https://peakmediaproperties.zendesk.com/hc/en-us/requests/new' target='_blank'>click here</a> to contact us.";

			if(!empty($gifteeEmail)) {
				$beforeOrderTable = "{$billingFirstName}{$billingLastName} has sent you a gift! Since your gift is digital, <a href='{$siteURL}/my-account/downloads' target='_blank'>click here</a> to receive your gift. <br/><br/><br/><br/>You will need to login to your account to download your gift. If you do NOT have an account, you will be prompted to create one. Please use the email address associated with this email to create your account.";
				$afterOrderTable = "If you have questions or problems downloading your gift, <a href='https://peakmediaproperties.zendesk.com/hc/en-us/requests/new' target='_blank'>click here</a> to contact us.";
			}

			if($isSub) {
				$beforeOrderTable = "{$billingFirstName}{$billingLastName} has sent you a gift! You can access your gift by logging into your {$freshAccount[0]} by <a href='{$siteURL}/my-account/downloads' target='_blank'>clicking here</a>.";
				$afterOrderTable = "If you have questions or problems downloading your gift, <a href='https://peakmediaproperties.zendesk.com/hc/en-us/requests/new' target='_blank'>click here</a> to contact us.";
			}

		}
		$beforeOrderTable .= $giftMessage;

		if(isset($credsTable) && !empty($credsTable)) {
			$beforeOrderTable .= "{$credsTable} You can learn more about your gift by clicking the product's title below.";
		}

				// Render email and send
				$emailMsg = <<<EOT
							Hi {$gifteeFirstName},

			{$beforeOrderTable}
			<div style="margin-bottom:20px"><table class="x_td" cellspacing="0" cellpadding="6" border="1" width="100%" style="color:#999; border:1px solid #e5e5e5; vertical-align:middle; width:100%; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif"><thead><tr><th class="x_td" scope="col" align="left" style="color:#999; border:1px solid #e5e5e5; vertical-align:middle; padding:12px; text-align:left">Product</th><th class="x_td" scope="col" align="left" style="color:#999; border:1px solid #e5e5e5; vertical-align:middle; padding:12px; text-align:left">Quantity</th></tr></thead><tbody><tr class="x_order_item"><td class="x_td" align="left" style="color:#999; border:1px solid #e5e5e5; padding:12px; text-align:left; vertical-align:middle; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif; word-wrap:break-word"><a href="{$siteURL}/my-account/downloads/" target="_blank">{$productName}</a> </td><td class="x_td" align="left" style="color:#999; border:1px solid #e5e5e5; padding:12px; text-align:left; vertical-align:middle; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif">1 </td></tr>{$bundle_products}</tbody></table></div>

			See our <a href="{$faqs}" target="_blank">FAQs</a> for instruction on how to access your gift. {$afterOrderTable}

			Regards,

			The {$siteName} Team
EOT;

				dispatch_email($id, $emailMsg, $emailHeader, $emailTitle, $gifteeEmail);
				$gifterEmailHeader = "Your Gift Has Been Sent!";
                $gifterEmailTitle = "Gift Confirmation for {$gifteeFirstName}";
                $gifterBeforeOrderTable = "Your gift to {$gifteeFirstName} has been successfully sent!";
                $gifterAfterOrderTable = "For any issues, please contact support.";

                $gifterEmailBody = <<<EOT
                Hi {$gifterFirstName},

                {$gifterBeforeOrderTable}
                <p><strong>Gift: </strong> <a href="{$productLink}">{$productName}</a></p>
                <p><strong>Recipient Name: </strong> {$gifteeFirstName} {$gifteeLastName}</p>
            	<div style="margin-bottom:20px"><table class="x_td" cellspacing="0" cellpadding="6" border="1" width="100%" style="color:#999; border:1px solid #e5e5e5; vertical-align:middle; width:100%; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif"><thead><tr><th class="x_td" scope="col" align="left" style="color:#999; border:1px solid #e5e5e5; vertical-align:middle; padding:12px; text-align:left">Product</th><th class="x_td" scope="col" align="left" style="color:#999; border:1px solid #e5e5e5; vertical-align:middle; padding:12px; text-align:left">Quantity</th></tr></thead><tbody><tr class="x_order_item"><td class="x_td" align="left" style="color:#999; border:1px solid #e5e5e5; padding:12px; text-align:left; vertical-align:middle; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif; word-wrap:break-word"><a href="{$siteURL}/my-account/downloads/" target="_blank">{$productName}</a> </td><td class="x_td" align="left" style="color:#999; border:1px solid #e5e5e5; padding:12px; text-align:left; vertical-align:middle; font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif">1 </td></tr>{$bundle_products}</tbody></table></div>
                <p>{$gifterAfterOrderTable}</p>
                Regards,<br>
                The {$siteName} Team
EOT;

                if (!empty($gifteremail)) {
					error_log("Mail sent to GIFTER EMAIL: ${$gifteremail}");
                    dispatch_email($id, $gifterEmailBody, $gifterEmailHeader, $gifterEmailTitle, $gifteremail);
			}
			}
		} catch (Exception $e) {
			error_log("Error processing order ID: {$id}, Error: {$e->getMessage()}");
		}
	}
}

function dispatch_email($order_id, $emailMsg, $header, $title, $gifteeEmail) {
	global $woocommerce;

	$mailer = $woocommerce->mailer();
	$message_body = __($emailMsg);
	$message = $mailer->wrap_message($header, $message_body );
	$mailer->send( esc_attr($gifteeEmail), $title, $message );
}

function removing_customer_details_in_emails( $order, $sent_to_admin, $plain_text, $email ) {
	if(!empty(get_post_meta($order->get_id(), '_gft_gp', true))) {
		remove_action( 'woocommerce_email_customer_details', array( WC()->mailer(), 'email_addresses' ), 20, 3 );
	}
}

function remove_downloads_section_from_refunded_order_emails( $order, $sent_to_admin, $plain_text, $email ) {
	if(!empty(get_post_meta($order->get_id(), '_gft_gp', true))) {
		remove_action( 'woocommerce_email_order_details', array( WC()->mailer(), 'order_downloads' ), 10 );
	}
}

add_action( 'woocommerce_email_after_order_table', 'so_39251827_remove_subscription_details', 5, 4 );
add_action( 'woocommerce_email_order_details', 'remove_downloads_section_from_refunded_order_emails', 1, 4 );
add_action( 'woocommerce_email_customer_details', 'removing_customer_details_in_emails', 5, 4 );
add_action( 'woocommerce_email_order_details', 'bbloomer_add_content_specific_email', 20, 4 );
