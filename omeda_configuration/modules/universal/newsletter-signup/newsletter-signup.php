<?php

/*
Plugin Name: Newsletter Signup
Plugin URI:
Description: Allows to sign up for newsletter during checkout.
Version: 1.0
Author:  James
Author URI: http://midtc.com/
License:
*/

// Exit if accessed directly.
if (! defined('ABSPATH')) exit;

include($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');

$current_url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$current_url = explode('/', $current_url);
$current_url = array_filter($current_url);
$find_url = array_search('one-page-checkout', $current_url);

if($find_url) {
	return;
}

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

add_action('woocommerce_review_order_before_submit', 'add_newsletter_checkbox');
add_action('woocommerce_review_order_before_payment', 'inject_newsletter_assets', 20);
add_action('woocommerce_checkout_order_processed', 'check_newsletter_checkbox', 10, 2);
add_action('woocommerce_checkout_create_order', 'save_newsletter_checkbox_field_value', 10, 1 );
add_action('woocommerce_admin_order_data_after_billing_address', 'display_omeda_email_optins');
add_action('admin_enqueue_scripts', 'newsletter_admin_area_js');
add_action('woocommerce_checkout_before_customer_details', 'newsletter_checkout_js', 10, 2);

function newsletter_omeda_api_call($operation, $data) {
    global $c;

    static $omeda_included = false;
    if (!$omeda_included) {
        INCLUDE_OMEDA();
        $omeda_included = true;
    }

    if ($operation === 'create_customer') {
        $endpoint = "{$c('ENDPOINT')}{$c('OMEDA_DIRECTORY')['save_customer_and_order_paid']}";
        $fields = array(
            'Emails' => array([
                'EmailAddress' => $data['email']
            ])
        );
    } elseif ($operation === 'run_processor') {
        $endpoint = "{$c('ENDPOINT')}{$c('OMEDA_DIRECTORY')['run_processor']}";
        $fields = array('Process' => [array('TransactionId' => $data['transaction_id'])]);
    } elseif ($operation === 'submit_to_omeda') {
        $fields = array(
            'DeploymentTypeOptIn' => array([
                'EmailAddress' => $data['email'],
                'DeploymentTypeId' => $data['newsletter_deployments'],
                'Source' => 'checkout-process'
            ])
        );

        $send_newsletter_payload = omedaCurl("{$c('EMAIL_ENDPOINT')}{$c('OMEDA_DIRECTORY')['email_optin_queue']}", json_encode($fields));

        if ($send_newsletter_payload[1] == 200) {
            $recieved_payload = json_decode($send_newsletter_payload[0], true);

            if (isset($recieved_payload)) {
                $newslettter_deployments_string = implode(',', $data['newsletter_deployments']);
                $submissionInfo = [$recieved_payload['SubmissionId'], $newslettter_deployments_string];

                return $submissionInfo;
            }
        }
    } elseif ($operation === 'email_optin_optout_lookup') {
        $billing_email = $data['order']->get_billing_email();
        $url = str_replace('{email_address}', $billing_email, "{$c('ENDPOINT')}{$c('OMEDA_DIRECTORY')['email_optin_optout_lookup']}");
        $send_newsletter_payload = omedaCurl($url);
        $response_decode = json_decode($send_newsletter_payload[0], true);

        if (!(isset($response_decode) && !empty($response_decode))) {
            return;
        } else {
        	return $response_decode;
        }
    } else {
        return false;
    }

    $send_payload_to_omeda = omedaCurl($endpoint, json_encode($fields));

    $received_payload = $send_payload_to_omeda[0];

    return $received_payload;
}

function newsletter_checkout_js() {
	wp_enqueue_script('newsletter-checkout', plugin_dir_url(__FILE__) . 'assets/js/newsletter-checkout-area.js?cache=' . time());
}

function newsletter_admin_area_js() {
	$conditions = [
	    is_admin() && get_post_type() === 'shop_order',
	    isset($post) && $post->post_type === 'shop_order',
	    (get_current_screen()->post_type ?? '') === 'shop_order',
	];

	$on_woocommerce_order_page = in_array(true, $conditions);

	if (!$on_woocommerce_order_page) {
	    return;
	}
	
	wp_enqueue_script('newsletter-admin', plugin_dir_url(__FILE__) . 'assets/js/newsletter-admin-area.js?cache=' . time(), null, '1.0.0', true);
}

function array_keys_recursive($array) {
  $keys = array();

  foreach ($array as $key => $value) {
    $keys[] = $key;
    if (is_array($value)) {
      $keys = array_merge($keys, array_keys_recursive($value));
    }
  }

  return $keys;
}

function display_omeda_email_optins($order) {
	$data = array('order' => $order);
	$response_decode = newsletter_omeda_api_call('email_optin_optout_lookup', $data);

	if(!(isset($response_decode) && !empty($response_decode))) {
		return;
	}

	$order_id = get_the_ID();
	$grab_omeda_deployments = explode(',', get_post_meta($order_id, 'newsletter_deployment_id', true));

	$bold_deployments = array();
	$non_bold_deployments = array();
	foreach ($response_decode as $value) {
		if (is_array($value) || is_object($value)) {
		    foreach ($value as $values) {
		        if (isset($values['Status']) && $values['Status'] == 'IN') {
		            $deployement_id = $values['DeploymentTypeId'];
		            $name = $values['DeploymentTypeName'];
		            if (in_array($deployement_id, $grab_omeda_deployments)) {
		                $bold_deployments[] = "<b>$name ($deployement_id)</b>";
		            } else {
		                $non_bold_deployments[] = "$name ($deployement_id)";
		            }
		        }
		    }
		}
	}

	$in_deployments = array_merge($non_bold_deployments, $bold_deployments);

	$in_deployments_string = implode(', ', $in_deployments);

	echo '<strong>Omeda Opt-in Newsletter(s)</strong> <a href="#" id="toggle-newsletter-optins">[Show/Hide]</a>:<br/><div id="newsletter-section" style="display:none;">' . $in_deployments_string . '</div>';
}

function save_newsletter_checkbox_field_value($order) {
    $newsletter_checkbox_value = isset( $_POST['newsletter_checkbox'] ) ? sanitize_text_field( $_POST['newsletter_checkbox'] ) : '';
    if ( $newsletter_checkbox_value ) {
        $order->update_meta_data( 'newsletter_checkbox', $newsletter_checkbox_value );
    }
}

function add_newsletter_checkbox() {
    woocommerce_form_field( 'newsletter_checkbox', array(
        'type'          => 'checkbox',
        'class'         => array('form-row-wide newsletter-signup'),
        'label_class'   => 'newsletter-signup',
        'default'	    => 1,
        'label'         => __('I want to receive email communications from ' . get_bloginfo('name') . ', including educational resources, promotions, the latest content, partner news, and tips.'),
        'required'      => false,
    ), WC()->checkout->get_value( 'newsletter_checkbox' ));
}

function inject_newsletter_assets() {
	wp_enqueue_style('newsletter-style', plugin_dir_url(__FILE__) . 'assets/css/style.css?' . time());
}

function process_newsletter_signup($email, $order_id, $force_membership = false) {
	delete_option('newsletter_dictionary');

	$newsletter_dictionary_option = get_option('newsletter_dictionary');
	$newsletter_community_based_sites = get_option('newsletter_community_based_sites');
	$newsletter_membership_based_sites = get_option('newsletter_membership_based_sites');

	if (empty($newsletter_dictionary_option)) {
		$newsletter_dictionary_path = plugin_dir_path(__FILE__) . 'includes/json/newsletter-dict.json';
		$newsletter_dictionary_json = file_get_contents($newsletter_dictionary_path);
		$newsletter_dictionary_option = json_decode($newsletter_dictionary_json);

		add_option('newsletter_dictionary', $newsletter_dictionary_option);
	}

	$newsletter_dictionary_option = json_decode(json_encode($newsletter_dictionary_option), true);

	$newsletter_community_based_sites = [];
	$newsletter_membership_based_sites = [];

	foreach ($newsletter_dictionary_option as $key => $value) {
	    if (isset($value['community-based'])) $newsletter_community_based_sites[] = $key;
	    if (isset($value['membership-based'])) $newsletter_membership_based_sites[] = $key;
	}

	if (!empty($newsletter_community_based_sites)) update_option('newsletter_community_based_sites', $newsletter_community_based_sites);
	if (!empty($newsletter_membership_based_sites)) update_option('newsletter_membership_based_sites', $newsletter_membership_based_sites);

	$site_url = parse_url(get_site_url())['host'];

	$domain_parts = explode('.', $site_url);
	$domain_parts = array_slice($domain_parts, -2);
	$domain = implode('.', $domain_parts);

	$join_general = array();

	if(!$force_membership) {
		$general = $newsletter_dictionary_option[$domain]['general-checkout'];

		$join_general = array_values($general);
	}

	$complete_target_list = array();

	if (in_array($domain, $newsletter_community_based_sites) || in_array($domain, $newsletter_membership_based_sites)) {
	    $community_based_cats = $newsletter_dictionary_option[$domain]['community-based'] ?? [];
		$membership_based_cats = $newsletter_dictionary_option[$domain]['membership-based'] ?? [];
		$forced_membership_based_cats = $newsletter_dictionary_option[$domain]['forced-membership-based'] ?? [];

		if ($force_membership) {
		    $forced_membership_based_cats = $newsletter_dictionary_option[$domain]['forced-membership-based'] ?? [];
		}

	    $order = wc_get_order($order_id);

	    $complete_target_list = [];
	    $remove_items = [];

	    $items = $order->get_items();

	    foreach ($items as $item_id => $item) {
	        $product = $item->get_product();

	        if ($product && $product->is_type('bundle')) {
	            $bundle = new WC_Product_Bundle($product->get_id());
	            $bundled_items = $bundle->get_bundled_items();

	            foreach ($bundled_items as $bundled_item) {
	            	$bundled_product_id = $bundled_item->get_product_id();

	                $remove_items[] = $bundled_product_id;
	            }
	        }
	    }

	    foreach ($order->get_items() as $item) {
	        $product_id = $item->get_product_id();

	        if (in_array($product_id, $remove_items)) {
	        	continue;
	        }

	        $product_categories = get_the_terms($product_id, 'product_cat');

	        if ($product_categories && !is_wp_error($product_categories)) {
	            foreach ($product_categories as $category) {
	                $cat_id = $category->term_id;

	                if ($force_membership && isset($forced_membership_based_cats[0])) {
	                    $cat_id_build[] = $cat_id;
	                    $forced_membership_based_cats_array = array_keys($forced_membership_based_cats[0]);

	                    sort($forced_membership_based_cats_array);
	                    sort($cat_id_build);

	                    $intersection = array_intersect($cat_id_build, $forced_membership_based_cats_array);

	                    if (!empty($intersection) && count(array_intersect($cat_id_build, $forced_membership_based_cats_array)) == count($forced_membership_based_cats_array)) {
	                        $values = array_map(function ($build_id) use ($newsletter_dictionary_option, $domain) {
	                            return array_values($newsletter_dictionary_option[$domain]['forced-membership-based'][0][$build_id] ?? []);
	                        }, $intersection);

	                        $complete_target_list = array_merge(...$values);
	                    }

	                    continue;
	                }

	                if (isset($community_based_cats[$cat_id])) {
	                    $complete_target_list = array_merge($complete_target_list, $community_based_cats[$cat_id]);
	                    $complete_target_list = array_values($complete_target_list);
	                }

	                if (isset($membership_based_cats[0])) {
	                    $cat_id_build[] = $cat_id;
	                    $membership_based_cats_array = array_keys($membership_based_cats[0]);

	                    sort($membership_based_cats_array);
	                    sort($cat_id_build);

	                    $intersection = array_intersect($cat_id_build, $membership_based_cats_array);

	                    if (!empty($intersection) && count(array_intersect($cat_id_build, $membership_based_cats_array)) == count($membership_based_cats_array)) {
	                        $values = array_map(function ($build_id) use ($newsletter_dictionary_option, $domain) {
	                            return array_values($newsletter_dictionary_option[$domain]['membership-based'][0][$build_id] ?? []);
	                        }, $intersection);

	                        $complete_target_list = array_merge($complete_target_list, ...$values);
	                    }
	                } elseif (isset($membership_based_cats[$cat_id])) {
	                    $complete_target_list = array_merge($complete_target_list, $membership_based_cats[$cat_id]);
	                }

	                $complete_target_list = array_unique($complete_target_list);
	            }

	            $complete_target_list = array_unique($complete_target_list);
	        }
	    }
	}

	$complete_list = array_merge(array_unique($complete_target_list), $join_general);

	$newslettter_deployments = array_map('intval', array_values($complete_list));

	if (empty($newslettter_deployments)) {
	    return false;
	}

	$newsletter_omeda_create_customer = newsletter_omeda_api_call('create_customer', ['email' => $email]);

	if(!$newsletter_omeda_create_customer) {
		return false;
	}

	$newsletter_omeda_create_customer_decode = json_decode($newsletter_omeda_create_customer, true);

	$output_file = plugin_dir_path(__FILE__) . 'log.txt';

	if (isset($newsletter_omeda_create_customer_decode['ResponseInfo'])) {
		$newsletter_omeda_create_customer_data = $newsletter_omeda_create_customer_decode['ResponseInfo'][0]['TransactionId'];
	}

	if (isset($newsletter_omeda_create_customer_data) && $newsletter_omeda_create_customer_data !== null) {
		newsletter_omeda_api_call('run_processor', ['transaction_id' => $newsletter_omeda_create_customer_data]);
	}

	$call_submit_to_omeda = newsletter_omeda_api_call('submit_to_omeda', ['email' => $email, 'newsletter_deployments' => $newslettter_deployments]);

	if ($call_submit_to_omeda) {
		return $call_submit_to_omeda;
	}

	return false;
}

function check_newsletter_checkbox($order_id, $posted_data) {
    $newsletter_checkbox = get_post_meta($order_id, 'newsletter_checkbox', true);
    $order_instance = wc_get_order($order_id);

    if (!isset( $posted_data['billing_email'])) {
    	return;
    }

    $billing_email = sanitize_email($posted_data['billing_email']);

    if($newsletter_checkbox === '1') {
        $processed_newsletter_signup = process_newsletter_signup($billing_email, $order_id);
        
        if($processed_newsletter_signup) {
            update_post_meta($order_id, 'newsletter_submission_id', $processed_newsletter_signup[0]);
            update_post_meta($order_id, 'newsletter_deployment_id', $processed_newsletter_signup[1]);
            $order_instance->add_order_note(__("[NEWSLETTER] Signed up with submission {$processed_newsletter_signup[0]} - [{$processed_newsletter_signup[1]}]."));
        } else {
            $order_instance->add_order_note(__("[NEWSLETTER] Failed to sign up for newsletter." ));
        }
    } else {
    	$processed_newsletter_signup = process_newsletter_signup($billing_email, $order_id, true);

    	 if($processed_newsletter_signup) {
            update_post_meta($order_id, 'newsletter_submission_id', $processed_newsletter_signup[0]);
            update_post_meta($order_id, 'newsletter_deployment_id', $processed_newsletter_signup[1]);

            $order_instance->add_order_note(__("[NEWSLETTER] Optout, but has membership product - signed up with submission {$processed_newsletter_signup[0]} - [{$processed_newsletter_signup[1]}]."));
        } else {
            $order_instance->add_order_note(__("[NEWSLETTER] No membership product and optout." ));
        }
    }
}