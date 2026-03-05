<?php

	if (!function_exists('digital_to_print_omeda_customer_id_lookup')) {
		function digital_to_print_omeda_customer_id_lookup($print_id) {
			global $c;

			static $omeda_included = false;
		    
		    if (!$omeda_included) {
		        INCLUDE_OMEDA();
		        $omeda_included = true;
		    }

		    $url = str_replace('{postaladdressId}', $print_id, "{$c('ENDPOINT')}{$c('OMEDA_DIRECTORY')['customer_lookup_by_postal_address']}");
	 		$send_print_id_payload = omedaCurl($url);
	        $response_decode = json_decode($send_print_id_payload[0], true);

	        if(!(isset($response_decode) && !empty($response_decode))) {
				return false;
			}

			$customer_id = $response_decode['Id'];

			if($customer_id) {
				return $customer_id;
			}

			return false;
		}
	}

	function get_print_to_digital_by_customer_id($customer_id) {
	    global $wpdb;
	    
	    $table_name = 'midtc_print_to_digital';
	    
	   $query = $wpdb->prepare("
		    SELECT *, FROM_UNIXTIME(claimed_date) AS formatted_claimed_date
		    FROM $table_name 
		    WHERE customer_id = %d
		    LIMIT 1
		", $customer_id);

		$result = $wpdb->get_row($query, ARRAY_A);

	    
	    if ($result) {
	        return $result;
	    } else {
	        return false;
	    }
	}

	function get_print_to_digital_by_email_address($email_address) {
	    global $wpdb;
	    
	    $table_name = 'midtc_print_to_digital';
	    
	    $query = $wpdb->prepare("
	        SELECT * 
	        FROM $table_name 
	        WHERE email_address = %s
	        LIMIT 1
	    ", $email_address);
	    
	    $result = $wpdb->get_row($query, ARRAY_A);
	    
	    if ($result) {
	        return $result;
	    } else {
	        return false;
	    }
	}

	function update_print_to_digital($customer_id, $data) {
	    global $wpdb;
	    
	    $table_name = 'midtc_print_to_digital';
	    
	    $where = array( 'customer_id' => $customer_id );
	    
	    $data = array_map( 'sanitize_text_field', $data );

	    $updated = $wpdb->update( $table_name, $data, $where );
	    
	    return $updated !== false;
	}

	add_action('template_redirect', 'digital_to_print_api');

	function digital_to_print_api() {
		$request = isset($_GET['request']) ? sanitize_text_field($_GET['request']) : '';

		if(empty($request)) {
			return;
		}

		if(!($request == 'rsvp')) {
			return;
		}

		$current_user = wp_get_current_user();

		$output = [
		    'error' => true,
		    'msg' => 'An unknown error occurred.'
		];

		if (!($current_user && current_user_can('administrator'))) {
		    $output['error'] = true;
			$output['msg'] = 'You do not have administrator privileges. Unable to proceed due to a permissions issue.';

			die(json_encode($output));
		}

		$query = sanitize_text_field($_GET['query']);

		if($query == 'replace') {
			$postal_id = isset($_GET['postal_id']) ? sanitize_text_field($_GET['postal_id']) : '';
			$email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';

			if(isset($postal_id) && !empty($postal_id) && (!isset($email) || empty($email))) {
				$new_email = isset($_GET['new_email']) ? sanitize_email($_GET['new_email']) : '';

				if(empty($new_email)) {
					$output['error'] = true;
					$output['msg'] = 'Please verify all parameters.';

					die(json_encode($output));
				}

				$customer_info_by_postal_id = digital_to_print_omeda_customer_id_lookup($postal_id);

				if (!$customer_info_by_postal_id) {
				    $output['error'] = true;
				    $output['msg'] = "Couldn't locate any customer information for the provided postal ID from Omeda.";
				    die(json_encode($output));
				}

				$result = get_print_to_digital_by_customer_id($customer_info_by_postal_id);

				if(!$result) {
					$output['error'] = true;
					$output['msg'] = 'Couldn\'t find any results using that postal ID. Maybe the postal ID doesn\'t exist?';

					die(json_encode($output));
				}

				$find_customer_by_email_address = get_print_to_digital_by_email_address($new_email);

				if($find_customer_by_email_address) {
					$output['error'] = true;
					$output['msg'] = 'The new email address provided is invalid as it\'s already associated with a user. Results can be found in the result key.';
					$output['result'] = $find_customer_by_email_address;

					die(json_encode($output));
				}

				$data_to_update = array(
				    'email_address' => $new_email,
				);

				$update_successful = update_print_to_digital($customer_info_by_postal_id, $data_to_update);

				if ($update_successful) {
				   	$output['error'] = false;
				    $output['msg'] = 'User successfully updated!';
				} else {
				    $output['error'] = true;
				    $output['msg'] = 'An unexpected problem arose while trying to update this user.';
				}

				die(json_encode($output));
			}

			if(isset($email) && !empty($email) && (!isset($postal_id) || empty($postal_id))) {
				$new_postal_id = isset($_GET['new_postal_id']) ? sanitize_text_field($_GET['new_postal_id']) : '';

				if(empty($new_postal_id)) {
					$output['error'] = true;
					$output['msg'] = 'Please verify all parameters.';

					die(json_encode($output));
				}

				$find_customer_by_email_address = get_print_to_digital_by_email_address($email);

				if(!$find_customer_by_email_address) {
					$output['error'] = true;
					$output['msg'] = 'Couldn\'t find any results using that email. Maybe the email doesn\'t exist?';

					die(json_encode($output));
				}

				$customer_info_by_postal_id = digital_to_print_omeda_customer_id_lookup($new_postal_id);

				if (!$customer_info_by_postal_id) {
				    $output['error'] = true;
				    $output['msg'] = "Couldn't locate any customer information for the provided postal ID from Omeda.";
				    die(json_encode($output));
				}

				$find_customer_by_postal_id = get_print_to_digital_by_customer_id($customer_info_by_postal_id);

				if($find_customer_by_postal_id) {
					$output['error'] = true;
					$output['msg'] = 'The new postal ID provided is invalid as it\'s already associated with a user. Results can be found in the result key.';
					$output['result'] = $find_customer_by_postal_id;

					die(json_encode($output));
				}

				$data_to_update = array(
				    'customer_id' => $customer_info_by_postal_id,
				);

				$update_successful = update_print_to_digital($find_customer_by_email_address['customer_id'], $data_to_update);

				if ($update_successful) {
				   	$output['error'] = false;
				    $output['msg'] = 'User successfully updated!';
				} else {
				    $output['error'] = true;
				    $output['msg'] = 'An unexpected problem arose while trying to update this user.';
				}

				die(json_encode($output));
			}
		}

		if($query == 'search') {
			$postal_id = isset($_GET['postal_id']) ? sanitize_text_field($_GET['postal_id']) : '';
			$email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';

			if(isset($postal_id) && !empty($postal_id) && (!isset($email) || empty($email))) {
				$find_customer_by_postal_id = digital_to_print_omeda_customer_id_lookup($postal_id);

				if(!$find_customer_by_postal_id) {
					$output['error'] = true;
					$output['msg'] = 'Couldn\'t locate any customer information for that postal ID from Omeda.';

					die(json_encode($output));
				}

				$result = get_print_to_digital_by_customer_id($find_customer_by_postal_id);

				if(!$result) {
					$output['error'] = true;
					$output['msg'] = 'Couldn\'t find any results using that postal ID. Maybe the postal ID doesn\'t exist?';

					die(json_encode($output));
				} else {
					$output['error'] = false;
					$output['msg'] = 'Successfully found a match!';
					$output['result'] = $result;
				}

				die(json_encode($output));
			}

			if(isset($email) && !empty($email) && (!isset($postal_id) || empty($postal_id))) {
				$find_customer_by_email_address = get_print_to_digital_by_email_address($email);

				if(!$find_customer_by_email_address) {
					$output['error'] = true;
					$output['msg'] = 'Couldn\'t find any results using that email. Maybe the email doesn\'t exist?';

					die(json_encode($output));
				} else {
					$output['error'] = false;
					$output['msg'] = 'Successfully found a match!';
					$output['result'] = $find_customer_by_email_address;
				}

				die(json_encode($output));
			}
		}
	}