<?php
	function process_gift_through_omeda($products_to_gift, $order, $order_id, $customer_id, $products_list, $filler_products_id,$omeda_customer_id) {
		global $c ,$directory, $wpdb;

		$log_to_database_build = array();

		$log_to_database_build['order_id'] = $order_id;
		$log_to_database_build['user_id'] = $customer_id;

		try {
			foreach ($products_to_gift as $md5_key => $md5_value) {
				$gift_order_shipping_md5 = $md5_key;

			    foreach ($products_to_gift[$gift_order_shipping_md5] as $gift_uniqu_id_key => $gift_uniqu_id_value) {
			    	$gift_order_uniqu_id = $gift_uniqu_id_key;

			    	$gift_order_products = ($products_to_gift[$gift_order_shipping_md5][$gift_uniqu_id_key]['products']);

			    	$gift_order_shipping = ($products_to_gift[$gift_order_shipping_md5][$gift_uniqu_id_key]['shipping']);

			    	try {
				    	$gift_company_name = $gift_order_shipping['company_name'];
				    	$gift_email_address = $gift_order_shipping['email_address'];
				    	$gift_first_name = $gift_order_shipping['first_name'];
				    	$gift_last_name = $gift_order_shipping['last_name'];
				    	$gift_country_name = $gift_order_shipping['country_name'];
				    	$gift_state_name = $gift_order_shipping['state_name'];
				    	$gift_street_address = $gift_order_shipping['street_address'];
				    	$gift_street_second_address = $gift_order_shipping['street_second_address'];
				    	$gift_city_name = $gift_order_shipping['city_name'];
				    	$gift_zip_code = $gift_order_shipping['zip_code'];

				    	$gift_adhoc_sku = $gift_order_products['adhoc_sku'];
						$gift_adhoc_product_id = $gift_order_products['adhoc_product_id'];
						$gift_adhoc_variation_id = $gift_order_products['adhoc_variation_id'];
						$gift_adhoc_behaviors = $gift_order_products['adhoc_behaviors'];

				    	unset($gift_order_products['adhoc_sku']);
						unset($gift_order_products['adhoc_product_id']);
						unset($gift_order_products['adhoc_variation_id']);
						unset($gift_order_products['adhoc_behaviors']);

						if (strtolower($gift_country_name) == 'us') {
							$marketing_country = '_US';
						} else if (strtolower($gift_country_name) == 'ca') {
							$marketing_country = '_CA';
						} else {
							$marketing_country = '_INT';
						}

						if(!empty($gift_adhoc_variation_id)) {
							$gift_adhoc_product_id = $gift_adhoc_variation_id;
						}

						$darwin_marketing_code = $wpdb->get_var($wpdb->prepare('select pm.meta_value from wp_postmeta as pm WHERE pm.post_id = %d AND pm.meta_key = "_darwin_marketing_code"', array($gift_adhoc_product_id)));
		
						if (!$darwin_marketing_code && $darwin_marketing_code != null) {
							throw new Exception("no darwin marketing code for gift order");
						}

						$useShippingInfo = function($type) use ($order) {
							$shippingGetter = "get_shipping_$type";
							$billingGetter = "get_billing_$type";
							return $order->$shippingGetter() ?: $order->$billingGetter();
						};

				    	$payload = array(
							'ExternalCustomerId' => $order_id . '_' . $gift_uniqu_id_key,
							'ExternalCustomerIdNamespace' => 'WooCommOrd',
							'PromoCode' => $darwin_marketing_code,
							'FirstName' => ($gift_first_name != '' && $gift_first_name != null && $gift_first_name != false ? $gift_first_name : $useShippingInfo('first_name')),
							'LastName' => ($gift_last_name != '' && $gift_last_name != null && $gift_last_name != false ? $gift_last_name : $useShippingInfo('last_name')),
							'DonorId'  => $omeda_customer_id,
							'Addresses' => array([
								'Company' => ($gift_company_name != '' && $gift_company_name != null && $gift_company_name != false ? $gift_company_name : $useShippingInfo('company')),
								'Street' => ($gift_street_address != '' && $gift_street_address != null && $gift_street_address != false ? $gift_street_address : $useShippingInfo('address_1')),
								'ApartmentMailStop' => ($gift_street_second_address != '' && $gift_street_second_address != null && $gift_street_second_address != false ? $gift_street_second_address : $useShippingInfo('address_2')),
								'City' => ($gift_city_name != '' && $gift_city_name != null && $gift_city_name != false ? $gift_city_name : $useShippingInfo('city')),
								'Region' => ($gift_state_name != '' && $gift_state_name != null && $gift_state_name != false ? $gift_state_name : $useShippingInfo('state')),
								'PostalCode' => ($gift_zip_code != '' && $gift_zip_code != null && $gift_zip_code != false ? $gift_zip_code : $useShippingInfo('postcode')),
								'CountryCode' => ($gift_country_name != '' && $gift_country_name != null && $gift_country_name != false ? $gift_country_name : $useShippingInfo('country')),
								'AddressProducts' => join(",", $products_list)
							]),
							'BillingInformation' => array(
								'CreditCardNumber' => '4111111111111111',
								'ExpirationDate' => '0226',
								'CardSecurityCode' => '111',
								'NameOnCard' => "{$gift_first_name} {$gift_last_name}",
								'DoCharge' => 'False',
								'BillingCompany' => ($gift_company_name != '' && $gift_company_name != null && $gift_company_name != false ? $gift_company_name : $useShippingInfo('company')),
								'BillingStreet' => ($gift_street_address != '' && $gift_street_address != null && $gift_street_address != false ? $gift_street_address : $useShippingInfo('address_1')),
								'BillingApartmentMailStop' =>($gift_street_second_address != '' && $gift_street_second_address != null && $gift_street_second_address != false ? $gift_street_second_address : $useShippingInfo('address_2')),
								'BillingCity' => ($gift_city_name != '' && $gift_city_name != null && $gift_city_name != false ? $gift_city_name : $useShippingInfo('city')),
								'BillingRegion' => ($gift_state_name != '' && $gift_state_name != null && $gift_state_name != false ? $gift_state_name : $useShippingInfo('state')),
								'BillingPostalCode' => ($gift_zip_code != '' && $gift_zip_code != null && $gift_zip_code != false ? $gift_zip_code : $useShippingInfo('postcode')),
								'BillingCountryCode' => ($gift_country_name != '' && $gift_country_name != null && $gift_country_name != false ? $gift_country_name : $useShippingInfo('country')),
								'Comment1' => $customer_id,
								'Comment2' => $order->get_payment_method_title(),
								'DepositDate' => date('Y-m-d ', strtotime('-1 days')),
								'AuthCode' => $order->get_transaction_id() ?: bin2hex(random_bytes(10))
							),
							'Emails' => array([
								'EmailAddress' => $gift_email_address
							]),
							'Phones' => array([
								'Number' => $order->get_billing_phone()
							]),
							'Products' => array(
								$gift_order_products
							),
							'CustomerBehaviors' => array([
								'BehaviorId' => $filler_products_id[1],
								'BehaviorDate' => date('Y-m-d H:i:s'),
								'BehaviorAttributes' => $gift_adhoc_behaviors
							])
						);

						$amount = $gift_order_products['Amount'];
						$amountPaid = $gift_order_products['AmountPaid'];

						if (empty($amount) || $amount === '0.00' || empty($amountPaid) || $amountPaid === '0.00' || $amountPaid === 0) {
							unset($payload['BillingInformation'], $payload['Phones']);
						}

						if(strpos($gift_adhoc_sku, 'MEM') !== false) {
							unset($payload['Products']);
							unset($payload['BillingInformation']);
						}
					} catch (exception $e) { 
						$log_to_database_build['sent_receive_message']['error']['payload'] = 'unable to build payload to send to omeda:' . $e->getMessage();
						throw new Exception("hard stop error");
					}


					$send_decode_omeda_payload_status = 500;

					try {
						$filtered_payload = filterPayload($payload);

						$jsonPayload = json_encode($filtered_payload);

						$send_payload_to_omeda = omedaCurl("{$c('ENDPOINT')}{$c('OMEDA_DIRECTORY')['save_customer_and_order_paid']}", $jsonPayload);

						$send_decode_omeda_payload_status = $send_payload_to_omeda[1];

						if($send_decode_omeda_payload_status == 400) {
							$grab_omeda_errors = json_decode($send_payload_to_omeda[0], true);
							throw new Exception("error at omeda: " . json_encode($grab_omeda_errors));
						}

						if($send_decode_omeda_payload_status == 404) {
							$grab_omeda_errors = json_decode($send_payload_to_omeda[0], true);
							throw new Exception("error at omeda: " . json_encode($grab_omeda_errors));
						}

						if($send_decode_omeda_payload_status !== 200) {
							throw new Exception("general error at omeda");
						}

						$send_decode_omeda_payload = json_decode($send_payload_to_omeda[0], true);

						if(!isset($jsonPayload)) {
							throw new Exception("payload sent is empty");
						}

						if(!isset($send_decode_omeda_payload)) {
							throw new Exception("payload received is empty");
						}
					} catch (exception $e) { 
						$log_to_database_build['sent_receive_message']['error']['payload'] = 'error with payload: ' . $e->getMessage();
					}

					if($send_decode_omeda_payload_status == 200) {
						$log_to_database_build['sent_receive_submission_id'] = end($send_decode_omeda_payload);
						$log_to_database_build['response_payload'] = $send_decode_omeda_payload;

						if(isset($send_decode_omeda_payload['Errors'])) {
							$log_to_database_build['sent_receive_message']['error']['omeda'] = 'omeda sent error(s)';
						}

						try{
							$omeda_response_data = $send_decode_omeda_payload['ResponseInfo'][0];
						} catch (Exception $e) {
							$log_to_database_build['sent_receive_message']['error']['payload'] = 'issue with response info:' . $e->getMessage();
						}
					}

					$log_to_database_build['transaction_id'] = $omeda_response_data['TransactionId'];
					$log_to_database_build['sent_payload'] = $jsonPayload;

					if(isset($filtered_payload['Products'][0]['OmedaProductId'])) {
						$log_to_database_build['sku'] = $filtered_payload['Products'][0]['OmedaProductId'];
					}

					if(isset($filtered_payload['Products'][0]['Sku'])) {
						$sku = $filtered_payload['Products'][0]['Sku'];

						$product_id = $wpdb->get_var($wpdb->prepare("
						    SELECT post_id
						    FROM {$wpdb->prefix}postmeta
						    WHERE meta_key = '_sku'
						    AND meta_value = %s
						    LIMIT 1
						", $sku));

						if($product_id) {
							$sku = $product_id;
						}

						$log_to_database_build['sku'] = $sku;
					}

					if(isset($filtered_payload['CustomerBehaviors'][0]['BehaviorAttributes'])) {
						$sent_behaviors = $filtered_payload['CustomerBehaviors'][0]['BehaviorAttributes'];
						foreach ($sent_behaviors as $attribute) {
					        if ($attribute['BehaviorAttributeTypeId'] === 19) {
					           	$uniq_sku = $attribute['BehaviorAttributeValue'];
					           	break;
					        }
					    }

					    if(!empty($uniq_sku)) {
					    	$product_id = $wpdb->get_var($wpdb->prepare('select pm.post_id from wp_postmeta as pm WHERE pm.meta_value = %d AND pm.meta_key = "_darwin_marketing_code"', array($uniq_sku)));

					    	$log_to_database_build['sku'] = $product_id;
					    }
					}

					run_report($log_to_database_build, true);
			    }
			}
		} catch (Exception $e) { $log_to_database_build['sent_receive_message']['error']['stop'] = 'hard stop due to: ' . $e->getMessage(); run_report($log_to_database_build, true); }
	}
?>