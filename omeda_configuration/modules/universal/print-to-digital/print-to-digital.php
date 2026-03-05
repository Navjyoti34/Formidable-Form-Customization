<?php

	/*
	Plugin Name: Print to Digital
	Plugin URI:
	Description: Handle the conversion from print magazine to digital.
	Version: 1.0
	Author:  James
	Author URI: http://midtc.com/
	License:
	*/

	if (! defined('ABSPATH')) exit;

	include($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');
	include(plugin_dir_path(__FILE__).'/includes/api.php');

	add_shortcode('digital_to_print_form', 'digital_to_print_form_callback');

	function digital_to_print_password_strength_check($password) {
	    // $output = ['error' => false, 'msg' => '', 'register' => true];

	    if (strlen($password) < 8) {
	        $output['error'] = true;
	        $output['msg'] = 'Password must be at least 8 characters long.';
	        return $output;
	    }

		/*
	    if (!preg_match('/[A-Z]/', $password)) {
	        $output['error'] = true;
	        $output['msg'] = 'Password must contain at least one uppercase letter.';
	        return $output;
	    }

	    if (!preg_match('/[a-z]/', $password)) {
	        $output['error'] = true;
	        $output['msg'] = 'Password must contain at least one lowercase letter.';
	        return $output;
	    }

	    if (!preg_match('/[0-9]/', $password)) {
	        $output['error'] = true;
	        $output['msg'] = 'Password must contain at least one digit.';
	        return $output;
	    }

	    if (!preg_match('/[!@#\$%\^&\*\(\)_\+\-=\[\]\{\};:\'",<>\.\?\\|~]/', $password)) {
	        $output['error'] = true;
	        $output['msg'] = 'Password must contain at least one special character.';
	        return $output;
	    } */

	    return $output;
	}

	function digital_to_print_username_check($username) {
	    // $output = ['error' => false, 'msg' => '', 'register' => true];

	    if (!preg_match('/^[^0-9][a-zA-Z0-9]{5,17}$/', $username)) {
	    	$output['register'] = true;
	    	$output['error'] = true;
	        $output['msg'] = 'Sorry, the username you entered does not meet the required criteria. Usernames must be between 6 and 18 characters in length, and can only contain letters and numbers. Usernames cannot begin with a number, and must be unique.';
	        return $output;
        }

        $check_username_exists = username_exists($username);

        if($check_username_exists) {
			$output['register'] = true;
            $output['error'] = true;
	        $output['msg'] = 'It looks like that username is already registered to another account.';
            return $output;
        }

	    return $output;
	}

	function digital_to_print_create_new_user($first_name, $last_name, $email, $password) {
		$create_username_from_email = explode('@', $email)[0];
		$try_username = $format_created_username = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $create_username_from_email));
		$check_username_exists = username_exists($format_created_username);

		while($check_username_exists) {
			$try_username = $format_created_username . rand(10, 99);
			$check_username_exists = username_exists($try_username);
		}

		$id = wp_create_user($try_username, $password, $email);

		if (is_wp_error($id)) {
			//log_to_file_pd($id->get_error_message());
			error_log("❌ User creation failed: " . $id->get_error_message());
			return false;
		}

		$user = new WP_User($id);
		wp_update_user([
			'ID' => $user->ID,
			'first_name' => $first_name,
			'last_name' => $last_name
		]);

		return $user->ID;
}


	function digital_to_print_check_existing_user($email_address) {
		$user = get_user_by('email', $email_address);

		if($user) {
			return $user;
		} else {
			return false;
		}
	}

	function digital_to_print_check_membership_claim_status($print_id) {
	    global $wpdb;

	    $sql = $wpdb->prepare("SELECT customer_id FROM `midtc_print_to_digital` WHERE `customer_id` = %d AND `claimed` = 0 LIMIT 1", $print_id);

	    $result = $wpdb->get_var($sql);

	    if (!$result) {
	        return true;
	    } else {
	        return false;
	    }
	}

	
	function digital_to_print_check_email_exists($email) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'art_competition';

		// Prepare query to find email with claim_sale = 0
		$sql = $wpdb->prepare("
			SELECT email 
			FROM $table_name 
			WHERE email = %s AND claim_sale = 0 
			LIMIT 1
		", $email);

		$result = $wpdb->get_var($sql);

		if ($result) {
			return true; // Email exists and claim_sale is 0
		} else {
			return false; // Not allowed to submit
		}
	}

	function digital_to_print_update_claim_status($user_id, $customer_id, $print_id_used) {
		global $wpdb;

		$current_epoch = time();

		$sql = $wpdb->prepare(
			"UPDATE midtc_print_to_digital 
			SET `claimed` = %d, `claimed_date` = %d , `postal_id_used` = %d
			WHERE `customer_id` = %d",
			$user_id,
			$current_epoch,
			$print_id_used,
			$customer_id
		);

		$result = $wpdb->query($sql);

		if ($result !== false) {
			return true;
		} else {
			return false;
		}
	}

	function digital_to_print_display_countries($param) {
		$html = '<select style="width:100%;" id="countrySelect" name="' . $param . '">';

		$countries = (new WC_Countries())->__get('countries');

		foreach ($countries as $code => $name) {
			$selected = ($code === 'US') ? 'selected' : '';
			$html .= '<option value="' . $code . '" ' . $selected . '>' . $name . '</option>';
		}

		$html .= '</select>';

		return $html;
	}

	add_action('init', 'digital_to_print_state_call');

	function digital_to_print_state_call() {
		$country = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : null;
		$digital = isset($_GET['digital']) ? sanitize_text_field($_GET['digital']) : null;

		if (!(isset($country) && !empty($country) && isset($digital) && $digital === 'true')) {
			return;
		}

		$states = (new WC_Countries())->get_states($country);

		if (!$states) {
			echo json_encode(['error' => true]);
			die();
		}

		echo json_encode($states);

		die();
	}

	function digital_to_print_display_states($param) {
		$selected_state = (isset($_POST['form-state']) ? sanitize_text_field($_POST['form-state']) : null);
		$html = '<select style="width:100%;" name="' . $param . '"><option value=""';

		if (is_null($selected_state) || empty($selected_state)) {
			$html .= ' disabled selected hidden>Select an option</option>';
		} else {
			$html .= ' disabled hidden>Select an option</option>';
		}

		$countries = (new WC_Countries())->get_states((new WC_Countries())->get_base_country());

		foreach ($countries as $code => $name) {
			if (!empty($selected_state) && $code == $selected_state) {
				$html .= '<option value="' . $code . '" selected>' . $name . '</option>';
				continue;
			}

			$html .= '<option value="' . $code . '">' . $name . '</option>';
		}

		$html .= '</select>';

		return $html;
	}

	function digital_to_print_update_billing($user_id, $user_info) {
		[$first_name, $last_name, $street_address_one, $street_address_two, $city, $country, $state, $postal_code] = $user_info;

		$billing_address = array(
			'billing_first_name' => $first_name,
			'billing_last_name' => $last_name,
			'billing_address_1' => $street_address_one,
			'billing_address_2' => $street_address_two,
			'billing_city' => $city,
			'billing_postcode' => $postal_code,
			'billing_country' => $country,
			'billing_state' => $state,
		);

		foreach ($billing_address as $key => $value) {
			update_user_meta($user_id, $key, $value);
		}
	}

	function digital_to_print_form_callback() { 
		wp_enqueue_script('digital-to-print-script', plugin_dir_url(__FILE__) . 'assets/js/script.js?' . time());
		wp_enqueue_style('digital-to-print-style', plugin_dir_url(__FILE__) . 'assets/css/style.css?' . time());

		$output = array('error' => true, 'msg' => '');

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['print_to_digital']) || isset($_POST['print_to_digital_account_registration']))) {

			[$first_name, $last_name, $email_address] = [
				isset($_POST['form-first-name']) ? sanitize_text_field($_POST['form-first-name']) : null,
				isset($_POST['form-last-name']) ? sanitize_text_field($_POST['form-last-name']) : null,
				isset($_POST['form-email']) ? sanitize_email($_POST['form-email']) : null,
			];

			[$street_address_one, $street_address_two, $city, $country, $state, $postal_code] = [
				isset($_POST['form-street-address-one']) ? sanitize_text_field($_POST['form-street-address-one']) : null,
				isset($_POST['form-street-address-two']) ? sanitize_text_field($_POST['form-street-address-two']) : null,
				isset($_POST['form-city']) ? sanitize_text_field($_POST['form-city']) : null,
				isset($_POST['form-country']) ? sanitize_text_field($_POST['form-country']) : null,
				isset($_POST['form-state']) ? sanitize_text_field($_POST['form-state']) : null,
				isset($_POST['form-postal-code']) ? sanitize_text_field($_POST['form-postal-code']) : null,
			];
			$output = [
				'error' => true,
				'msg' => 'An unknown error occurred.',
			];

			if (!($street_address_one && $city && $country && $postal_code)) {
				$output['msg'] = "Please take a moment to check your address information below.";
			} else {
				if ($first_name && $last_name && $email_address) {
					$has_email = digital_to_print_check_email_exists($email_address);
					if (!$has_email) {
						$output['msg'] = 'Email address not eligible or already claimed.';
						// return;
					}
					$process_lock = false;

					if(!$process_lock && $has_email) {
						$current_user = digital_to_print_check_existing_user($email_address);
						
						if (isset($_POST['print_to_digital']) && $_POST['print_to_digital'] == 'print_to_digital') {
							if ($current_user) {
								//$update_claim_status = digital_to_print_update_claim_status($current_user->ID, $print_id, $orginal_print_id);

								digital_to_print_send_out_email($email_address, null, null, 'GPM240318014', 'Congratulations on Claiming Your Artists Network Membership', null, false);
								digital_to_print_update_billing($current_user->ID, [$first_name, $last_name, $street_address_one, $street_address_two, $city, $country, $state, $postal_code]);
								//give_user_subscription(10640684, $current_user->ID);
								$assign_membership = digital_to_print_assign_membership(1071542, $current_user->ID,[$email_address,$first_name, $last_name, $street_address_one, $street_address_two, $city, $country, $state, $postal_code]);

								if(!$assign_membership) {
									$output['msg'] = 'Something went wrong. Please review and try again.';
								} else {
									global $wpdb;
									$table_name = $wpdb->prefix . 'art_competition';
									
									$claim_sale = $wpdb->get_var(
										$wpdb->prepare("SELECT claim_sale FROM $table_name WHERE email = %s", $current_user->user_email)
									);
									if ($claim_sale === null) {
										error_log("Email $current_user->user_email not found in table.");

										$output = [
											'error' => true,
											'msg'   => 'Your email address was not found in our records.',
										];
									}elseif ($claim_sale == 0) {
										// Step 2: Update claim_sale to 1
										$updated = $wpdb->update(
											$table_name,
												[
													'claim_sale' => 1,
													'user_id'    => $current_user->ID
												],
												['email' => $current_user->user_email],
												['%d', '%d'],  // claim_sale and user_id are both integers
												['%s']         // email is a string
										);
									//update_user_meta($current_user->ID, $user_profile_meta_key, array('techniques' => $techniques, 'project_interest' => $project_interest));
									update_user_meta($current_user->ID, 'midtc_rsvp_user_redirect', esc_url(home_url('/my-account/members-area/')));
									digital_to_print_set_cookie('rsvp_claim_response_' . uniqid(), array('error' => false, 'msg' => "Congratulations and welcome to Artists Network Membership! You'll need to login with the email address " . $current_user->user_email . " and a password for this this account."));

									$output = [
										'error' => false,
										'access' => true,
										'login_required' => true,
										'msg' => "Congratulations and welcome to Artists Network Membership! You'll need to login with the email address",
									];
									}else{
										if (is_user_logged_in()) {
											$msg = "You have already claimed Artists Network Membership.";
											$login_required = false;
										} else {
											$msg = "You have already claimed Artists Network Membership. Please log in to access it.";
											$login_required = true;
										}
										digital_to_print_set_cookie('rsvp_claim_response_' . uniqid(), array(
											'error' => true,
											'msg' => $msg
										));
										$output = [
											'error' => true,
											'access' => true,
											'login_required' => $login_required,
											'msg' => $msg,
										];											
									}								                
								}
							} else {
								$output = [
									'error' => false,
									'register' => true,
									'msg' => 'In order to activate your Artist Network Membership, please take a moment to review the information below. If everything appears to be correct, simply click the registration button to create your account.',
								];
							}
						} elseif (isset($_POST['print_to_digital_account_registration']) && $_POST['print_to_digital_account_registration'] == 'print_to_digital_account_registration') {
								if ($current_user) {			
									//$update_claim_status = digital_to_print_update_claim_status($current_user->ID, $print_id, $orginal_print_id);

									digital_to_print_send_out_email($email_address, null, null, 'GPM240318014', 'Congratulations on Claiming Your Artists Network Membership', null, false);
									digital_to_print_update_billing($current_user->ID, [$first_name, $last_name, $street_address_one, $street_address_two, $city, $country, $state, $postal_code]);
									//give_user_subscription(10640684, $current_user->ID);
									$assign_membership = digital_to_print_assign_membership(1071542, $current_user->ID,[$email_address,$first_name, $last_name, $street_address_one, $street_address_two, $city, $country, $state, $postal_code]);
							
									if(!$assign_membership) {
										$output['msg'] = 'Something went wrong. Please review and try again.';
									} else {
										// update_user_meta($current_user->ID, $user_profile_meta_key, array('techniques' => $techniques, 'project_interest' => $project_interest));

										$output = [
											'error' => false,
											'access' => true,
											'msg' => 'Your account has been created and your membership has been successfully claimed! Please click the link below to access it!',
										];
									}
								} else {
									[$username, $password, $password_confirmation] = [
										isset($_POST['form-username-field']) ? sanitize_text_field($_POST['form-username-field']) : null,
										isset($_POST['form-password-field']) ? sanitize_text_field($_POST['form-password-field']) : null,
										isset($_POST['form-password-confirmation-field']) ? sanitize_text_field($_POST['form-password-confirmation-field']) : null
									];

									if(!($password == $password_confirmation)) {
										$output = [
											'register' => true,
											'msg' => 'The passwords you have entered do not match. Please re-enter them.',
										];
									} else {


										$check_username = digital_to_print_username_check($username);

										if($check_username['error'] == false) {
											$check_password_strength = digital_to_print_password_strength_check($password);

											if($check_password_strength['error'] == false) {
												global $wpdb;
												$table_name = $wpdb->prefix . 'art_competition';
												$email = $current_user->user_email;
												$claim_sale = $wpdb->get_var(
													$wpdb->prepare("SELECT claim_sale FROM $table_name WHERE email = %s", $email_address)
												);

												$create_user = digital_to_print_create_new_user($first_name, $last_name, $email_address, $password);

												if ($create_user) {
													$user = get_userdata($create_user);
													$user_id = $user->ID;

													//$update_claim_status = digital_to_print_update_claim_status($current_user->ID, $print_id, $orginal_print_id);
													
													digital_to_print_send_out_email($email_address, null, null, 'GPM240318014', 'Congratulations on Claiming Your Artists Network Membership', null, false);

													digital_to_print_update_billing($user_id, [$first_name, $last_name, $street_address_one, $street_address_two, $city, $country, $state, $postal_code]);
					
													$assign_membership = digital_to_print_assign_membership(1071542, $user_id,[$email_address,$first_name, $last_name, $street_address_one, $street_address_two, $city, $country, $state, $postal_code]);

													if(!$assign_membership) {
														$output['msg'] = 'Something went wrong. Please review and try again.';
													} else {
														global $wpdb;
														$table_name = $wpdb->prefix . 'art_competition';
														$email = $user->user_email;;
														$claim_sale = $wpdb->get_var(
															$wpdb->prepare("SELECT claim_sale FROM $table_name WHERE email = %s", $email)
														);
														if ($claim_sale === null) {
															error_log("Email $email not found in table.");

															$output = [
																'error' => true,
																'msg'   => 'Your email address was not found in our records.',
															];
														}elseif ($claim_sale == 0) {
															// Step 2: Update claim_sale to 1
															$updated = $wpdb->update(
															$table_name,
																[
																	'claim_sale' => 1,
																	'user_id'    => $user_id
																],
																['email' => $email],
																['%d', '%d'],  // claim_sale and user_id are both integers
																['%s']         // email is a string
															);
														
														update_user_meta($user_id, 'midtc_rsvp_user_redirect', esc_url(home_url('/my-account/members-area/')));
														digital_to_print_set_cookie('rsvp_claim_response_' . uniqid(), array('error' => false, 'msg' => "Congratulations and welcome to Artists Network Membership! You'll need to login with the email address " . $email . " and a password for this this account."));

														$output = [
															'error' => false,
															'access' => true,
															'login_required' => true,
															'msg' => "Congratulations and welcome to Artists Network Membership! You'll need to login with the email address",
														];
														}else{
															if (is_user_logged_in()) {
																$msg = "You have already claimed Artists Network Membership.";
																$login_required = false;
															} else {
																$msg = "You have already claimed Artists Network Membership. Please log in to access it.";
																$login_required = true;
															}
															digital_to_print_set_cookie('rsvp_claim_response_' . uniqid(), array(
																'error' => true,
																'msg' => $msg
															));
															$output = [
																'error' => true,
																'access' => true,
																'login_required' => $login_required,
																'msg' => $msg,
															];											
														}								                
													}
												} else {
													$output = [
														'register' => true,
														'msg' => 'An error occurred while registering your account. Please double-check the provided information and attempt registration again.',
													];
												}
											} else {
												$output = $check_password_strength;
											}
										} else {											
											$output = $check_username;
										}
									}
								}
							}
					}
				} else {
					if (isset($_POST['print_to_digital_account_registration']) && $_POST['print_to_digital_account_registration'] == 'print_to_digital_account_registration') {
						$output['register'] = true;
					}
					$output['msg'] = 'Please make sure to fill in all the required fields correctly.';
				}
			}
		}
			
		function generateForm($output) {
			$formType = empty($output['access']) ? (empty($output['register']) ? 'default' : 'register') : 'access';
			$html_content .= '<h1 id="title" style="text-align:center;">Claim your membership to Artists Network</h1>';

			$html_content .= '
				<div style="margin-bottom:50px; text-align:center;">
					<p style="font-weight:bold !important">Congratulations on your art competition win. You have won a 1-year access to the Artists Network Membership, a $99.99 value. <br/>Please fill out the form below to claim your digital Membership and 1 year Artists Magazine Print Subscription.</p>
				</div>
				<div class="page-wrapper my-account mb membership-redemption" style="margin-top: 20px;">
					<div class="container" role="main">
						<div id="address_form">
							<div class="notification ' . (empty($output['msg']) ? 'd-none' : '') . ($output['error'] ? ' danger' : (isset($output['register']) ? ' info' : ' success')) . '">' . $output['msg'] . '</div>';

			if ($formType === 'access') {
				if(!empty($output['login_required']) && $output['login_required']) {
					die(header('Location: ' . esc_url(home_url('my-account/')) . '?p=login_required'));
				} else {
					die(header('Location: ' . esc_url(site_url('/my-account/members-area/'))));
				}
				
				$html_content .= '<button type="button" class="button" name="claim" id="claim" value="claim_account">Claim</button></div></div></div>';
			} else {
				$fields = [
					['label' => 'First Name', 'param' => 'form-first-name', 'placeholder' => 'Jane', 'description' => '', 'required' => true],
					['label' => 'Last Name', 'param' => 'form-last-name', 'placeholder' => 'Doe', 'description' => '', 'required' => true],
					['label' => 'Email', 'param' => 'form-email', 'placeholder' => 'johndoe@email.com', 'description' => '', 'type' => 'email', 'required' => true],
					['label' => 'Street Address', 'param' => 'form-street-address-one', 'placeholder' => '124 Main Street', 'description' => '', 'type' => 'text', 'required' => true],
					['label' => 'Apt, suite, ect. (optional)', 'param' => 'form-street-address-two', 'placeholder' => 'Apt 1A', 'description' => '', 'type' => 'text', 'required' => false],
					['label' => 'City', 'param' => 'form-city', 'placeholder' => 'New Town', 'description' => '', 'type' => 'text', 'required' => true],
					['label' => 'Country', 'param' => 'form-country', 'description' => '', 'options' => digital_to_print_display_countries('form-country'), 'type' => 'text', 'required' => true],
					['label' => 'State', 'param' => 'form-state', 'description' => '', 'options' => digital_to_print_display_states('form-state'), 'type' => 'text', 'required' => true],
					['label' => 'Postal Code/Zip Code', 'param' => 'form-postal-code', 'placeholder' => '10025', 'description' => '', 'type' => 'text', 'required' => true]
				];

				if($formType == 'register') {
						$fields[] = ['label' => 'Username', 'param' => 'form-username-field', 'placeholder' => '', 'description' => 'Usernames must be between 6 and 18 characters in length, and can only contain letters and numbers. Usernames cannot begin with a number, and must be unique.', 'type' => 'text', 'required' => true];
						$fields[] = ['label' => 'Password', 'param' => 'form-password-field', 'placeholder' => '', 'description' => 'Please enter a password that is at least 8 characters long.', 'type' => 'password', 'required' => true];
						$fields[] = ['label' => 'Password Confirmation', 'param' => 'form-password-confirmation-field', 'placeholder' => '', 'description' => 'Please confirm your password that is at least 8 characters long.', 'type' => 'password', 'required' => true];
				}
				$html_content .= generateFormFields($fields, $formType);
			}

			return $html_content;
		}

		function generateFormFields($fields, $formType) {
			$formHtml = '<form method="post" id="rsvp_claim_form" action="">';

			foreach ($fields as $field) {
				$param = $field['param'];
				$placeholder = $field['placeholder'];
				$options = isset($field['options']) ? $field['options'] : null;
				$required = $field['required'] ? '<abbr class="required" title="required">*</abbr>' : '';
				$id = ($formType === 'default' && $param === 'form-print-id') ? 'tooltipInput' : $param;
				// $disabled = ($formType === 'register' && $param === 'form-print-id') ? 'disabled' : '';
				$tooltip = ($formType === 'default' && $param === 'form-print-id') ? '<div class="tooltip" id="tooltip" style="display: none;"><img src="https://hostedcontent.dragonforms.com/hosted/images/dragon/generic/257.gif" data-pin-description="Conversion" data-pin-title="Conversion"></div>' : '';
				$type = !empty($field['type']) ? $field['type'] : 'text';

				$formHtml .= '
					<p class="form-row address-field form-row-' . ($param === 'form-email' ? 'last' : 'first') . ' validate-required" id="' . str_replace("-", "_", str_replace("form-", "", $param)) . '_field" data-priority="10">
						<label for="' . $param . '">' . $field['label'] . ' &nbsp;' . $required . '</label>
						<span class="woocommerce-input-wrapper">
				';

				$formHtml .= '<input type="' . $type . '" class="input-text ' . ($options ? 'd-none' : '') . '" name="' . $param . '" id="' . $id . '" placeholder="' . $placeholder . '" value="' . (isset($_REQUEST[$param]) ? esc_attr($_REQUEST[$param]) : '') . '" data-placeholder="' . str_replace(' ', '_', strtolower($field['label'])) . '" ' . $disabled . '/>' . $tooltip;

				if($options) {
					$formHtml .= '<span data-id="' . $param . '">' . $options . '</span>';
				}		        

				$formHtml .= '
							<span class="field-description">' . $field['description'] . '</span>
						</span>
					</p>';
			}

			if ($formType === 'register') {
				$formHtml .= '
					<p>
						<input type="hidden" name="print_to_digital_account_registration" value="print_to_digital_account_registration">
						<input type="hidden" class="input-text" name="form-print-id" value="' . (isset($_POST['form-print-id']) ? esc_attr($_POST['form-print-id']) : (isset($_REQUEST['form-print-id']) ? esc_attr($_REQUEST['form-print-id']) : '')) . '" />
						<button type="submit" class="button" name="submit" id="submit" value="claim_account">Register</button>
					</p>';
			}

			if ($formType === 'default') {
					$formHtml .= '
					<p>
						<input type="hidden" name="print_to_digital" value="print_to_digital">
						<button type="submit" class="button" name="submit" id="submit" value="claim_account" style="margin-top: 5px;">Submit</button>
					</p>';
			}

			$formHtml .= '</form>';

			return $formHtml;
		}

		$html_content .= generateForm($output);

		return ($html_content);
	}

function digital_to_print_send_out_email($email, $template_name, $template_html, $omeda_track_id, $email_subject, $additional_merged_variables = array(), $test_email = true) {
	    global $c;

	    // Include OMEDA
	    echo INCLUDE_OMEDA();

	    $user_id = false;

	    if ($test_email) {
	        $user_id = get_current_user_id();
	    } else {
	        $user_id = get_user_by('email', $email);

	        if (!$user_id) {
	            $user_id = get_user_by('billing_email', $email);
	        }

	        if ($user_id) {
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

	    $first_name = get_user_meta($user_id, 'first_name', true) ?: get_user_meta($user_id, 'billing_first_name', true) ?: null;
	    $last_name = get_user_meta($user_id, 'last_name', true) ?: get_user_meta($user_id, 'billing_last_name', true) ?: null;
	    $makers_club_about = site_url('/makers-club/about');
	    $makers_club_collection = site_url('/makers-club/collection');
	    $makers_club_faq = site_url('/makers-club/faq');

	    $email_subject = stripslashes($email_subject);

	    $html_content = '';

	    $html_content .= empty($first_name) && empty($last_name) ? '<p>Dear Member,</p>' : '<p>Dear ' . $first_name . ',</p>';

	    $html_content .= <<<HTML

		<p>We're thrilled to have you as a part of our Artists Network community.</p>
		<p>Here's what you can look forward to as an Artists Network Member:</p>
        <ul>
            <li>Over 850 instructional workshops in a variety of mediums for all skill levels</li>
            <li>Access our searchable Artists Magazine archive, over a decade of inspiration from artists around the world</li>
            <li>Never miss an issue with an Artists Magazine print subscription delivered straight to you</li>
            <li>All-access pass to premium content</li>
			<li>A weekly premium newsletter to fuel your creativity and elevate your art practice</li>
        </ul>
        <p>Artists Network Members unlock more! Build your skills with access to practical, technique-driven content from top-notch working artists across a variety of media and become a part of a community of creatives just like you.</p>
		<ul>
			<li>Access videos on watercolor, acrylic, drawing, colored pencil, mixed media, oil, and pastel</li>
			<li>New art workshops are released on a regular basis</li>
			<li>Go full-screen in excellent quality</li>
			<li>Search a decade of how-to articles to strengthen your skills and explore a variety of media</li>
			<li>Expert advice and professional tips to level-up your career as an artist</li>
			<li>Stay up-to-date on the latest benefits and how to get the most out of your membership with a weekly newsletter</li>
			<li>Enjoy our editor's picks for top videos straight to your inbox with a weekly Member newsletter. Let us do the work for you!</li>
		</ul>
		<p>Helpful Links:</p>
		<ul>
			<li>Click <a href="https://peakmediaproperties.zendesk.com/hc/en-us/articles/360055350192-How-to-Navigate-the-Artists-Network-Membership" target="_blank">HERE</a> for instructions on how to navigate the Artists Network Membership. </li>
			<li>Having trouble logging in? Click <a href="https://www.artistsnetwork.com/my-account/lost-password/" target="_blank">HERE</a> to reset your password:</li>
			<li>For customer assistance contact us <a href="https://peakmediaproperties.zendesk.com/hc/en-us/requests/new" target="_blank">HERE</a>:</li>		
		</ul>
        <p>Warm regards,</p>
        <p>The Artists Network Team</p>
HTML;

	    $fields = array(
	        'TrackId' => $omeda_track_id,
	        'EmailAddress' => $email,
	        'FirstName' => $first_name,
	        'LastName' => $last_name,
	        'Subject' => $email_subject,
	        'HtmlContent' => $html_content,
	        'Preference' => 'HTML',
	        'MergeVariables' => ''
	    );

	    $omedaCall = omedaCurl("{$c('ON_DEMAND_ENDPOINT')}{$c('OMEDA_DIRECTORY')['send_email']}", json_encode($fields));

	    return $omedaCall;
}

	function digital_to_print_capture_claim_expiration($print_id) {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT expiration FROM `midtc_print_to_digital` WHERE `customer_id` = %d LIMIT 1", $print_id);

		$result = $wpdb->get_var($sql);

		if ($result) {
			return $result;
		} else {
			return false;
		}
	}

	function log_to_file_pd($message, $filename = 'custom-log.txt') {
		$log_dir = __DIR__; // Same directory as the current file
		$log_file = $log_dir . DIRECTORY_SEPARATOR . $filename;

		// Format the message with timestamp
		$formatted_message = "[" . date("Y-m-d H:i:s") . "] " . print_r($message, true) . PHP_EOL;

		// Append to log file
		file_put_contents($log_file, $formatted_message, FILE_APPEND);
	}

	function digital_to_print_assign_membership($membership_id, $user_id, $user_info) {
		[$email_address,$first_name, $last_name, $street_address_one, $street_address_two, $city, $country, $state, $postal_code] = $user_info;

		//$start_date = date('Y-m-d', strtotime('-1 day'));
		$start_date = date('Y-m-d');
		$new_expiry_timestamp = strtotime('+1 year', strtotime($start_date));
		$new_end_date = date('Y-m-d', $new_expiry_timestamp);

		// Get existing membership if it exists
		$existing_membership = wc_memberships_get_user_membership($user_id, $membership_id);

		if ($existing_membership && $existing_membership->is_active()) {
				// STEP 1: Get products tied to this membership plan
				$membership_plan = wc_memberships_get_membership_plan($membership_id);
				$granting_product_ids = $membership_plan ? $membership_plan->get_product_ids() : [];

				// STEP 2: Get all user subscriptions
				$subscriptions = wcs_get_users_subscriptions($user_id);

				foreach ($subscriptions as $subscription) {
					if ($subscription->has_status('active')) {
						foreach ($subscription->get_items() as $item) {
							$product_id = $item->get_product_id();
							if (in_array($product_id, $granting_product_ids)) {
								// Found valid subscription tied to this membership
								$next_payment = $subscription->get_time('next_payment');

								if ($next_payment) {
									$new_next_payment = date('Y-m-d H:i:s', strtotime('+1 year', $next_payment));
									$subscription->update_dates(['next_payment' => $new_next_payment]);
									$subscription->add_order_note(__('Next payment date extended by 1 year due to art competition win.', 'woocommerce'));
									return $existing_membership;
								}
							}
						}
					}
				}

				// Fallback: No tied subscription found — extend membership
				$current_end = $existing_membership->get_end_date('timestamp');
				if ($current_end && $current_end > time()) {
					$new_end_date = date('Y-m-d', strtotime('+1 year', $current_end));
				}

				$existing_membership->update_status('active');
				$existing_membership->set_end_date($new_end_date);
				$existing_membership->add_note(__('Membership extended due to art competition win.', 'woocommerce'));
				$membership = $existing_membership;
		} else {
				// Create new membership
				$membership = wc_memberships_create_user_membership([
					'plan_id'    => $membership_id,
					'user_id'    => $user_id,
					'status'     => 'active',
					'start_date' => $start_date,
					'end_date'   => $new_end_date,
				]);

				$get_user_membership_created = wc_memberships_get_user_membership($user_id, $membership_id);
				if ($get_user_membership_created) {
					$get_user_membership_created->update_status('active');
					$get_user_membership_created->set_start_date($start_date);
					$get_user_membership_created->set_end_date($new_end_date);
					$membership->add_note(
						sprintf(__( 'Membership access granted due to win art competition.', 'woocommerce' ))
					);
				}
		}

		if ($membership) {
			//send data to omeda
			$adhoc_behaviors = [
				[
					'BehaviorAttributeTypeId' => 16,
					'BehaviorAttributeValue' => 'membership',
				],
				[
					'BehaviorAttributeTypeId' => 16,
					'BehaviorAttributeValue' => 'featured products',
				],
				[
					'BehaviorAttributeTypeId' => 16,
					'BehaviorAttributeValue' => 'magazine subscription featured products',
				],
				[
					'BehaviorAttributeTypeId' => 16,
					'BehaviorAttributeValue' => 'video',
				],
				[
					'BehaviorAttributeTypeId' => 17,
					'BehaviorAttributeValue' => 'ANMEM',
				],
				[
					'BehaviorAttributeTypeId' => 18,
					'BehaviorAttributeValue' => 'attribute-value',
				],
				[
					'BehaviorAttributeTypeId' => 19,
					'BehaviorAttributeValue' => 'M21AAYRT',
				],
				[
					'BehaviorAttributeTypeId' => 20,
					'BehaviorAttributeValue' => '99.99',
				],
				[
					'BehaviorAttributeTypeId' => 37,
					'BehaviorAttributeValue' => '0',
				],
			];


			$payload = [
					"RunProcessor"=> 1,
					'ExternalCustomerId' => $user_id . substr(uniqid(), 0, 5),
					'ExternalCustomerIdNamespace' => 'WooCommOrd',//'WooCommOrd',
					'PromoCode' => 'M21AAYRT',
					'FirstName' => $first_name,
					'LastName' => $last_name,
					'Addresses' => [
						[
							'Company' => '',//$useShippingInfo('company')
							'Street' => $street_address_one,
							'ApartmentMailStop' => $street_address_two,
							'City' => $city,
							'Region' => $state,
							'PostalCode' => $postal_code,
							'CountryCode' => $country,
							'AddressProducts' => 93
						]
					],
					'Emails' => [
						[
							'EmailAddress' => $email_address
						]
					],
					'CustomerBehaviors' => [
						[
							'BehaviorId' => 16,
							'BehaviorDate' => date('Y-m-d H:i:s'),
							'BehaviorAttributes' => $adhoc_behaviors,
						]
					]
				];
			try {
				global $c;
				$filtered_payload = filterPayload($payload);
				$jsonPayload = json_encode($filtered_payload);
				$send_payload_to_omeda = omedaCurl("{$c('ENDPOINT')}{$c('OMEDA_DIRECTORY')['save_customer_and_order_paid']}", $jsonPayload);
				$send_decode_omeda_payload_status = $send_payload_to_omeda[1];
				//log_to_file_pd($send_payload_to_omeda);
				
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
				echo 'error with payload: ' . $e->getMessage();
			}	
		}
		return $membership;
	}

	function digital_to_print_generate_coupon_code($length = 6) {
		$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$coupon_code = '';

		for ($i = 0; $i < $length; $i++) {
			$random_char = $characters[rand(0, strlen($characters) - 1)];
			$coupon_code .= $random_char;
		}

		return $coupon_code;
	}

	function digital_to_print_create_100_percent_discount_coupon() {
		$coupon_amount = 100;
		$coupon_type = 'percent';

		$coupon = new WC_Coupon();
		$coupon->set_code('IMP2023' . digital_to_print_generate_coupon_code());
		$coupon->set_discount_type($coupon_type);
		$coupon->set_description("[AUTO] Produced for the transition from print to digital.");
		$coupon->set_amount($coupon_amount);
		$coupon->set_individual_use(false);
		$coupon->set_usage_limit(1);
		$coupon->set_usage_limit_per_user(1);
		$coupon->set_date_expires(date('d-m-Y', strtotime("+3 days")));

		$coupon->save();

		return $coupon;
	}

	function digital_to_print_set_cookie($name, $data, $expiration = 3600, $path = '/') {
		$jsonData = json_encode($data);

		setcookie($name, $jsonData, time() + $expiration, $path);
	}

	function digital_to_print_login_script_and_style() {
		if (strpos($_SERVER['REQUEST_URI'], '/my-account/') !== false) {
			wp_enqueue_script('digital-to-print-my-account-script', plugin_dir_url(__FILE__) . 'assets/js/my-account-script.js?' . time());
			wp_enqueue_style('digital-to-print-my-account-style', plugin_dir_url(__FILE__) . 'assets/css/my-account-style.css?' . time());
		}
	}
	add_action('init', 'digital_to_print_login_script_and_style');

	function digital_to_print_login_action($user_login, $user) {
		$redirect_url = get_user_meta($user->ID, 'midtc_rsvp_user_redirect', true);

		if (!empty($redirect_url)) {
			wp_redirect($redirect_url);
			exit;
		}
	}
	add_action('wp_login', 'digital_to_print_login_action', 10, 2);

	add_action('admin_menu', function () {
		add_menu_page(
			'Art Competition',
			'Art Competition',
			'manage_options',
			'art-competition',
			'render_art_competition_admin'
		);
	});

	function render_art_competition_admin() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'art_competition';
		$log_table  = $wpdb->prefix . 'art_competition_logs';
		$current_user_id = get_current_user_id();

		if (!empty($_FILES['csv_file']['tmp_name'])) {
			$inserted = 0;
			$row_num = 1;
			$handle = fopen($_FILES['csv_file']['tmp_name'], 'r');

			if ($handle !== false) {
				while (($row = fgetcsv($handle, 1000, ",")) !== false) {
					if ($row_num === 1) {
						$row_num++;
						continue;
					}

					if (count($row) < 3) {
						echo "<div class='notice notice-warning'><p>Row $row_num skipped: Missing columns.</p></div>";
						$row_num++;
						continue;
					}

					$email_raw      = $row[0];
					$user_id_raw    = $row[1];
					$claim_sale_raw = $row[2];

					$email      = sanitize_email($email_raw);
					$user_id    = intval($user_id_raw);
					$claim_sale = (int)$claim_sale_raw === 1 ? 1 : 0;

					// Allow null if user_id is not valid
					$user_id_db = $user_id > 0 ? $user_id : null;

					if (empty($email)) {
						echo "<div class='notice notice-warning'><p>Row $row_num skipped: Invalid email: $email_raw</p></div>";
					} else {
						$existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE email = %s", $email));

						if ($existing_id) {
							$result = $wpdb->update(
								$table_name,
								[
									'user_id'    => $user_id_db,
									'claim_sale' => $claim_sale,
									'updated_at' => current_time('mysql'),
								],
								['id' => $existing_id]
							);

							if ($result !== false) {
								$log_details = json_encode([
									'email'      => $email,
									'user_id'    => $user_id_db,
									'claim_sale' => $claim_sale,
									'action_by'  => $current_user_id,
								]);

								$wpdb->insert($log_table, [
									'user_id'   => $current_user_id,
									'record_id' => $existing_id,
									'action'    => 'update',
									'email'     => $email,
									'details'   => $log_details,
								]);

								echo "<div class='notice notice-info'><p>Row $row_num updated: $email</p></div>";
							} else {
								echo "<div class='notice notice-error'><p>Update failed at row $row_num: " . esc_html($wpdb->last_error) . "</p></div>";
							}
						} else {
							$result = $wpdb->insert($table_name, [
								'email'      => $email,
								'user_id'    => $user_id_db,
								'claim_sale' => $claim_sale,
								'created_at' => current_time('mysql'),
								'updated_at' => current_time('mysql'),
							]);

							if ($result !== false) {
								$new_id = $wpdb->insert_id;

								$log_details = json_encode([
									'email'      => $email,
									'user_id'    => $user_id_db,
									'claim_sale' => $claim_sale,
									'action_by'  => $current_user_id,
								]);

								$wpdb->insert($log_table, [
									'user_id'   => $current_user_id,
									'record_id' => $new_id,
									'action'    => 'insert',
									'email'     => $email,
									'details'   => $log_details,
								]);

								$inserted++;
							} else {
								echo "<div class='notice notice-error'><p>Insert failed at row $row_num: " . esc_html($wpdb->last_error) . "</p></div>";
							}
						}
					}

					$row_num++;
				}

				fclose($handle);
				echo '<div class="updated"><p>CSV import complete. ' . $inserted . ' rows inserted.</p></div>';
			} else {
				echo '<div class="error"><p>Failed to open uploaded file.</p></div>';
			}
		}
		if (isset($_GET['delete_entry'], $_GET['_wpnonce'])) {
			$delete_id = absint($_GET['delete_entry']);
			if (wp_verify_nonce($_GET['_wpnonce'], 'delete_entry_' . $delete_id)) {
				$deleted = $wpdb->delete($table_name, ['id' => $delete_id], ['%d']);
				if ($deleted !== false) {
					echo '<div class="notice notice-success"><p>Entry deleted successfully.</p></div>';
				} else {
					echo '<div class="notice notice-error"><p>Failed to delete the entry.</p></div>';
				}
			} else {
				echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
			}
		}
		// CSV Upload Form
		?>
		<div class="wrap">
			<h1>Art Competition - Import Entries</h1>
			<form method="post" enctype="multipart/form-data">
				<input type="file" name="csv_file" accept=".csv" required>
				<input type="submit" class="button button-primary" value="Import CSV">
			</form>
			<hr>
			<h2>Imported Entries</h2>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Email</th>
						<th>User ID</th>
						<th>Claim Sale</th>
						<th>Created At</th>
						<th>Updated At</th>
						<th>Action</th>

					</tr>
				</thead>
				<tbody>
					<?php
					$entries = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
					if ($entries) {
						foreach ($entries as $entry) {
							$delete_url = add_query_arg([
								'delete_entry' => $entry->id,
								'_wpnonce'     => wp_create_nonce('delete_entry_' . $entry->id),
							]);
							echo '<tr>';
							echo '<td>' . esc_html($entry->id) . '</td>';
							echo '<td>' . esc_html($entry->email) . '</td>';
							echo '<td>' . esc_html($entry->user_id) . '</td>';
							echo '<td>' . esc_html($entry->claim_sale) . '</td>';
							echo '<td>' . esc_html($entry->created_at) . '</td>';
							echo '<td>' . esc_html($entry->updated_at) . '</td>';
							echo '<td><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Are you sure you want to delete this entry?\');">Delete</a></td>';
							echo '</tr>';
						}
					} else {
						echo '<tr><td colspan="5">No entries found.</td></tr>';
					}
					?>
				</tbody>
			</table>
					
		<?php if (isset($_GET['showimportlog']) && $_GET['showimportlog'] === 'yes') {  ?>
		<hr>
		<h2>Import Logs Details</h2>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th>ID</th>
					<th>User ID</th>
					<th>Record ID</th>
					<th>Action</th>
					<th>Email</th>
					<th>Details</th>
					<th>Timestamp</th>
				</tr>
			</thead>
			<tbody>
				<?php
				global $wpdb;
				$log_table = $wpdb->prefix . 'art_competition_logs';
				$logs = $wpdb->get_results("SELECT * FROM $log_table ORDER BY id DESC LIMIT 100");

				if ($logs) {
					foreach ($logs as $log) {
						echo '<tr>';
						echo '<td>' . esc_html($log->id) . '</td>';
						echo '<td>' . esc_html($log->user_id) . '</td>';
						echo '<td>' . esc_html($log->record_id) . '</td>';
						echo '<td>' . esc_html($log->action) . '</td>';
						echo '<td>' . esc_html($log->email) . '</td>';

						$details = json_decode($log->details, true);
						echo '<td>';
						if (is_array($details)) {
							foreach ($details as $key => $val) {
								echo esc_html($key . ': ' . $val) . '<br>';
							}
						} else {
							echo esc_html($log->details);
						}
						echo '</td>';

						echo '<td>' . esc_html($log->timestamp) . '</td>';
						echo '</tr>';
					}
				} else {
					echo '<tr><td colspan="7">No logs found.</td></tr>';
				}
				?>
			</tbody>
		</table>
		<?php } ?>
		</div>
		<?php
	}

	function create_art_competition_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'art_competition';
		$log_table_name  = $wpdb->prefix . 'art_competition_logs';
		$charset_collate = $wpdb->get_charset_collate();

		// Main data table
		$sql1 = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(255) NOT NULL,
			user_id BIGINT(20) UNSIGNED NULL,
			claim_sale TINYINT(1) DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		// Logging table
		$sql2 = "CREATE TABLE IF NOT EXISTS $log_table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NULL,
			record_id BIGINT(20) UNSIGNED,
			action ENUM('insert', 'update') NOT NULL,
			email VARCHAR(255) NOT NULL,
			details LONGTEXT NULL,
			timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql1);
		dbDelta($sql2);
	}
	add_action('admin_init', 'create_art_competition_table');