<?php

/*
Plugin Name: Omeda Staging
Plugin URI:
Description: Mirroring Darwin to process through Omeda.
Version: 1.0
Author:  James
Author URI: http://midtc.com/
License:

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

Notes:
 - single sale goes under digital sales
 - if it's digital - mark with D
 - 16,17,18 - get clarity - item sku, cat, ect

*/

if ( ! defined( 'ABSPATH' ) ) exit;

include($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');

// add_action( 'woocommerce_thankyou', 'omeda_processor_', 10, 1 );

add_action('add_meta_boxes', 'omeda_product_promo_code_meta_box');

function omeda_product_promo_code_meta_box() {
    add_meta_box(
        'omeda_product_promo_code_meta_box',
        'Product Promo Code',
        'omeda_product_promo_code_meta_box_callback',
        'product',
        'side',
        'high'
    );
}
function get_all_iso_country_codes() {
    return array(
        'AF' => 'AFG', 'AL' => 'ALB', 'DZ' => 'DZA', 'AS' => 'ASM', 'AD' => 'AND',
        'AO' => 'AGO', 'AI' => 'AIA', 'AQ' => 'ATA', 'AG' => 'ATG', 'AR' => 'ARG',
        'AM' => 'ARM', 'AW' => 'ABW', 'AU' => 'AUS', 'AT' => 'AUT', 'AZ' => 'AZE',
        'BS' => 'BHS', 'BH' => 'BHR', 'BD' => 'BGD', 'BB' => 'BRB', 'BY' => 'BLR',
        'BE' => 'BEL', 'BZ' => 'BLZ', 'BJ' => 'BEN', 'BM' => 'BMU', 'BT' => 'BTN',
        'BO' => 'BOL', 'BA' => 'BIH', 'BW' => 'BWA', 'BV' => 'BVT', 'BR' => 'BRA',
        'IO' => 'IOT', 'BN' => 'BRN', 'BG' => 'BGR', 'BF' => 'BFA', 'BI' => 'BDI',
        'KH' => 'KHM', 'CM' => 'CMR', 'CA' => 'CAN', 'CV' => 'CPV', 'KY' => 'CYM',
        'CF' => 'CAF', 'TD' => 'TCD', 'CL' => 'CHL', 'CN' => 'CHN', 'CX' => 'CXR',
        'CC' => 'CCK', 'CO' => 'COL', 'KM' => 'COM', 'CG' => 'COG', 'CD' => 'COD',
        'CK' => 'COK', 'CR' => 'CRI', 'HR' => 'HRV', 'CU' => 'CUB', 'CY' => 'CYP',
        'CZ' => 'CZE', 'DK' => 'DNK', 'DJ' => 'DJI', 'DM' => 'DMA', 'DO' => 'DOM',
        'EC' => 'ECU', 'EG' => 'EGY', 'SV' => 'SLV', 'GQ' => 'GNQ', 'ER' => 'ERI',
        'EE' => 'EST', 'ET' => 'ETH', 'FK' => 'FLK', 'FO' => 'FRO', 'FJ' => 'FJI',
        'FI' => 'FIN', 'FR' => 'FRA', 'GF' => 'GUF', 'PF' => 'PYF', 'TF' => 'ATF',
        'GA' => 'GAB', 'GM' => 'GMB', 'GE' => 'GEO', 'DE' => 'DEU', 'GH' => 'GHA',
        'GI' => 'GIB', 'GR' => 'GRC', 'GL' => 'GRL', 'GD' => 'GRD', 'GP' => 'GLP',
        'GU' => 'GUM', 'GT' => 'GTM', 'GN' => 'GIN', 'GW' => 'GNB', 'GY' => 'GUY',
        'HT' => 'HTI', 'HM' => 'HMD', 'VA' => 'VAT', 'HN' => 'HND', 'HK' => 'HKG',
        'HU' => 'HUN', 'IS' => 'ISL', 'IN' => 'IND', 'ID' => 'IDN', 'IR' => 'IRN',
        'IQ' => 'IRQ', 'IE' => 'IRL', 'IL' => 'ISR', 'IT' => 'ITA', 'JM' => 'JAM',
        'JP' => 'JPN', 'JO' => 'JOR', 'KZ' => 'KAZ', 'KE' => 'KEN', 'KI' => 'KIR',
        'KP' => 'PRK', 'KR' => 'KOR', 'KW' => 'KWT', 'KG' => 'KGZ', 'LA' => 'LAO',
        'LV' => 'LVA', 'LB' => 'LBN', 'LS' => 'LSO', 'LR' => 'LBR', 'LY' => 'LBY',
        'LI' => 'LIE', 'LT' => 'LTU', 'LU' => 'LUX', 'MO' => 'MAC', 'MK' => 'MKD',
        'MG' => 'MDG', 'MW' => 'MWI', 'MY' => 'MYS', 'MV' => 'MDV', 'ML' => 'MLI',
        'MT' => 'MLT', 'MH' => 'MHL', 'MQ' => 'MTQ', 'MR' => 'MRT', 'MU' => 'MUS',
        'YT' => 'MYT', 'MX' => 'MEX', 'FM' => 'FSM', 'MD' => 'MDA', 'MC' => 'MCO',
        'MN' => 'MNG', 'ME' => 'MNE', 'MS' => 'MSR', 'MA' => 'MAR', 'MZ' => 'MOZ',
        'MM' => 'MMR', 'NA' => 'NAM', 'NR' => 'NRU', 'NP' => 'NPL', 'NL' => 'NLD',
        'NC' => 'NCL', 'NZ' => 'NZL', 'NI' => 'NIC', 'NE' => 'NER', 'NG' => 'NGA',
        'NU' => 'NIU', 'NF' => 'NFK', 'MP' => 'MNP', 'NO' => 'NOR', 'OM' => 'OMN',
        'PK' => 'PAK', 'PW' => 'PLW', 'PS' => 'PSE', 'PA' => 'PAN', 'PG' => 'PNG',
        'PY' => 'PRY', 'PE' => 'PER', 'PH' => 'PHL', 'PN' => 'PCN', 'PL' => 'POL',
        'PT' => 'PRT', 'PR' => 'PRI', 'QA' => 'QAT', 'RE' => 'REU', 'RO' => 'ROU',
        'RU' => 'RUS', 'RW' => 'RWA', 'BL' => 'BLM', 'SH' => 'SHN', 'KN' => 'KNA',
        'LC' => 'LCA', 'MF' => 'MAF', 'PM' => 'SPM', 'VC' => 'VCT', 'WS' => 'WSM',
        'SM' => 'SMR', 'ST' => 'STP', 'SA' => 'SAU', 'SN' => 'SEN', 'RS' => 'SRB',
        'SC' => 'SYC', 'SL' => 'SLE', 'SG' => 'SGP', 'SX' => 'SXM', 'SK' => 'SVK',
        'SI' => 'SVN', 'SB' => 'SLB', 'SO' => 'SOM', 'ZA' => 'ZAF', 'GS' => 'SGS',
        'SS' => 'SSD', 'ES' => 'ESP', 'LK' => 'LKA', 'SD' => 'SDN', 'SR' => 'SUR',
        'SJ' => 'SJM', 'SE' => 'SWE', 'CH' => 'CHE', 'SY' => 'SYR', 'TW' => 'TWN',
        'TJ' => 'TJK', 'TZ' => 'TZA', 'TH' => 'THA', 'TL' => 'TLS', 'TG' => 'TGO',
        'TK' => 'TKL', 'TO' => 'TON', 'TT' => 'TTO', 'TN' => 'TUN', 'TR' => 'TUR',
        'TM' => 'TKM', 'TC' => 'TCA', 'TV' => 'TUV', 'UG' => 'UGA', 'UA' => 'UKR',
        'AE' => 'ARE', 'GB' => 'GBR', 'US' => 'USA', 'UM' => 'UMI', 'UY' => 'URY',
        'UZ' => 'UZB', 'VU' => 'VUT', 'VE' => 'VEN', 'VN' => 'VNM', 'VG' => 'VGB',
        'VI' => 'VIR', 'WF' => 'WLF', 'EH' => 'ESH', 'YE' => 'YEM', 'ZM' => 'ZMB',
        'ZW' => 'ZWE'
    );
}
function omeda_product_promo_code_meta_box_callback($post) {
    global $wpdb;

	$product_id = $post->ID;

    $darwin_marketing_code = false;

    $darwin_marketing_code = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT pm.meta_value FROM wp_postmeta AS pm WHERE pm.post_id = %d AND pm.meta_key = %s',
            $product_id = $post->ID,
            '_darwin_marketing_code'
        )
    );

    if(!$darwin_marketing_code) {
    	$darwin_marketing_code = '<span style="color: red;font-weight: 500;">There is no product promo code assigned, and this will result in a failure when trying to send an order containing this product to Omeda.</span>';
    } else {
    	$darwin_marketing_code = '<span style="color: green;font-weight: 500;">The product promo code </span><span style="font-weight: 700;">' . $darwin_marketing_code . '</span><span style="color: green;font-weight: 500;"> is assigned to this product, which will ensure successful order transmission to Omeda for any order containing this product.</span>';
    }

	echo '<span id="omeda-product-promo-code">' . $darwin_marketing_code . '</span>';
}

add_action('woocommerce_order_status_changed', 'initiate_omeda_processor', 99, 4);

function filterPayload($array) {
   foreach ($array as $key => &$value) {
      if (empty($value) && $value !== "0") {
         unset($array[$key]);
      } else {
         if (is_array($value)) {
            $value = filterPayload($value);
            if (empty($value) && $value !== "0") unset($array[$key]);
         }
      }
   }

   return $array;
}

add_action('wp_loaded', 'setup_scheduling_event');

function setup_scheduling_event() {
    if(!wp_next_scheduled('process_omeda_orders_hook')) {
    	wp_schedule_event(time(), 'hourly', 'process_omeda_orders_hook');
    }
}

add_action('process_omeda_orders_hook', 'process_omeda_orders');

add_action('add_meta_boxes', 'omeda_meta_box');

function omeda_meta_box() {
    add_meta_box('omeda_order_meta_box', 'Omeda', 'omeda_order_meta_box', 'shop_order', 'side', 'core');
}

add_action('admin_init', 'omeda_force_process', 0);
add_action('admin_init', 'omeda_force_process_', 0);

function omeda_force_process_() {
	if(isset($_GET['omeda_process_old_orders'])) {
		global $wpdb;

		$query = "
		        SELECT p.*
FROM wp_posts p
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = 'omeda_process_old_orders'
WHERE p.post_type = 'shop_order'
  AND p.post_status = 'wc-completed'
  AND p.post_date >= '2023-04-01'
  AND p.post_date <= CURDATE()
  AND pm.meta_id IS NULL
ORDER BY RAND()
		";

		$results = $wpdb->get_results($query);

		if (!empty($results)) {
		    foreach ($results as $result) {
		        $order_id = $result->ID;

		        $meta_key = 'omeda_process_old_orders';
		        $meta_value = get_post_meta($order_id, $meta_key, true);

					if ($meta_value) {
						continue;
					}

					$meta_value = true;

		        	omeda_processor($order_id, 'on-hold', 'completed', false);

		        	update_post_meta($order_id, 'omeda_process_old_orders', $meta_value);

		        echo ($order_id);
		    }

		    die();
		} else {
		    echo 'No orders found';
		}

		//
	}
}
add_action( 'admin_notices', 'add_general_notification' );

function add_general_notification() {
    if ( isset( $_GET['post_type'] ) && $_GET['post_type'] == 'shop_order' ) {
    	$message = "Omeda processing hook not setup as of right now.";
		if ( $timestamp = wp_next_scheduled( 'process_omeda_orders_hook' ) ) {
			$datetime_utc = new DateTime();
			$datetime_utc->setTimestamp( $timestamp );
			$datetime_utc->setTimezone( new DateTimeZone( 'UTC' ) );
			$datetime_est = clone $datetime_utc;
			$datetime_est->setTimezone( new DateTimeZone( 'America/New_York' ) );
			$date_string = $datetime_est->format( 'Y-m-d h:i:s A' );
			$message = 'The Omeda processing hook is scheduled to run @ ' . $date_string . ' EST.';
		}
    	echo "<div class='notice notice-warning'><p>$message</p></div>";
    }
}

function omeda_enqueue() {
	$conditions = [
	    is_admin() && get_post_type() === 'shop_order',
	    isset($post) && $post->post_type === 'shop_order',
	    (get_current_screen()->post_type ?? '') === 'shop_order',
	];

	$on_woocommerce_order_page = in_array(true, $conditions);

	if (!$on_woocommerce_order_page) {
	    return;
	}
	
	wp_enqueue_script('force-process-js', plugin_dir_url( __FILE__ ) . 'assets/js/process.js?' . time(), false );
	wp_enqueue_style('force-process-css', plugin_dir_url( __FILE__ ) . 'assets/css/process.css?' . time(), false );
}

add_action('admin_enqueue_scripts', 'omeda_enqueue');


function omeda_force_process() {
	if(isset($_GET['omeda_force_data_send'])) {
		$order = $_GET['omeda_force_data_send'];

		if(isset($_GET['notes'])) {
			echo(json_encode(wc_get_order_notes([
				'order_id' => $order,
				'type' => 'internal',
			])));

			die();
		}
			
		omeda_processor($order, 'on-hold', 'completed', false);

		die('complete');
	}

	if(isset($_GET['omeda_force_process'])) {

		process_omeda_orders();
        
      die('complete');
	}
}

function omeda_order_meta_box() {
    global $post, $wpdb; // OPTIONALLY USE TO ACCESS ORDER POST

	echo '<span id="force-omeda-process" class="o-button o-button--cta"><span>Force Process</span></span></span>';
}

function run_report($content, $gift_order = false) {
	global $wpdb;

	$content = shortcode_atts(
		array(
			'sent_receive_submission_id' => '',
			'sku' => '',
			'order_id' => '',
			'sent_receive_message' => '',
			'user_id' => '',
			'transaction_id' => '',
			'sent_payload' => '',
			'response_payload' => '',
			'sent_receive_status'	=> ''
		), 
		$content
	);

	$order = wc_get_order($content['order_id']);

	$content['sent_receive_status'] = 'SUCCESS';
	$sent_receive_message = 'omeda data sent, waiting to process transaction';

	if(isset($content['sent_receive_message']['error']['omeda'])) {
		$content['sent_receive_status'] = 'ERROR';
	}

	if(isset($content['sent_receive_message']['error']['payload'])) {
		$content['sent_receive_status'] = 'ERROR';
	}

	if(isset($content['sent_receive_message']['error']['stop'])) {
		$content['sent_receive_status'] = 'ERROR';
	}

	$response_payload = trim(addslashes(json_encode($content['response_payload'])));
	$sent_payload = trim(addslashes(json_encode($content['sent_payload'])));

	if(isset($content['sent_receive_message']['error']['omeda']) || isset($content['sent_receive_message']['error']['payload']) || isset($content['sent_receive_message']['error']['stop'])) {
		$sent_receive_message = trim( addslashes( json_encode($content['sent_receive_message']['error'])));
	}

	if($gift_order) {
		$gift_order = ' [GIFT] ';
	} else {
		$gift_order = ' ';
	}

	$env = (defined('STAGING') && STAGING === false) ? '[PROD] ' : '[DEV] ';

	$order->add_order_note(__($env . "OMEDA" . $gift_order . "→ " . $sent_receive_message . ", " . $response_payload));

	$sql = $wpdb->prepare("INSERT INTO `omeda_queue` (`sent_receive_submission_id`, `sku`, `order_id`, `sent_receive_message`, `user_id`, `transaction_id`, `sent_payload`, `response_payload`, `sent_receive_status`) values ('{$content['sent_receive_submission_id']}', '{$content['sku']}', {$content['order_id']}, '{$sent_receive_message}', {$content['user_id']}, '{$content['transaction_id']}', '{$sent_payload}', '{$response_payload}', '{$content['sent_receive_status']}')");

	$wpdb->query($sql);
}
 

function process_omeda_orders() {
	global $c ,$directory, $wpdb;

	echo INCLUDE_OMEDA();

	$omeda_to_process = $wpdb->get_results("SELECT order_id, transaction_id FROM omeda_queue WHERE (processor_status = '' OR processor_status IS NULL) AND sent_receive_status = 'SUCCESS'");

	foreach($omeda_to_process as $transaction_id) {
		$order_id = $transaction_id->order_id;

		$order = wc_get_order($order_id);

		if (!$order) {
			continue;
		}

		try {
			$log_to_database_build = array();

			$log_to_database_build = shortcode_atts(
				array(
					'processor_status' => '',
					'processor_message' => '',
					'processor_submission_id' => '',
					'processor_payload' => ''
				), 
				$log_to_database_build
			);

			$log_to_database_build['processor_status'] = 'SUCCESS';
			$log_to_database_build['processor_message'] = 'omeda successfully completed and processed this order';

			$omeda_transaction_id = $transaction_id->transaction_id;

			$fields = array('Process' => [array('TransactionId' => $omeda_transaction_id)]);

			$send_payload_to_omeda = omedaCurl("{$c('ENDPOINT')}{$c('OMEDA_DIRECTORY')['run_processor']}", json_encode($fields));

			$send_decode_omeda_payload_status = $send_payload_to_omeda[1];

			$processor_payload = $send_payload_to_omeda[0];

			$log_to_database_build['processor_payload'] = trim( addslashes( json_encode($fields)));

			$processor_payload = json_decode($processor_payload, true);

			$log_to_database_build['processor_submission_id'] = end($processor_payload);

			$log_to_database_build['processor_payload'] = trim(addslashes(json_encode($processor_payload)));

			if($send_decode_omeda_payload_status !== 200) {
				throw new Exception("error at omeda");
			}

			if(!isset($processor_payload)) {
				throw new Exception("payload error");
			}

			if(isset($processor_payload['Errors'])) {
				$omeda_processor_errors = $processor_payload['Errors'];

				if(str_contains($omeda_processor_errors[0]['Error'], 'has already been processed')) {
					throw new Exception("already processed");
				}

				throw new Exception("processor error");
			}

			$omeda_processor_success = $processor_payload['BatchStatus'][0]['Success'] === 'true'? true: false;

			if(!$omeda_processor_success) {
				throw new Exception("omeda was unable to process order");
			}

		} catch (Exception $e) {
			$log_to_database_build['processor_status'] = 'ERROR';
			if (isset($log_to_database_build['processor_message']['error']['stop']) && !empty($log_to_database_build['processor_message']['error']['stop'])) {
				$log_to_database_build['processor_message']['error']['stop'] = 'hard stop due to: ' . $e->getMessage();
			}
		}

		if(isset($log_to_database_build['processor_message'])) {
			$log_to_database_build['processor_message'] = trim(addslashes(json_encode($log_to_database_build['processor_message'])));
		}

		$env = defined('STAGING') && STAGING === false ? '[PROD] ' : '[DEV] ';

		$order_note = sprintf('%sOMEDA → %s, %s', $env, $log_to_database_build['processor_message'], $log_to_database_build['processor_payload']);

		$order->add_order_note(__($order_note));

		$sql = $wpdb->prepare("UPDATE `omeda_queue` SET `processor_status` = '{$log_to_database_build['processor_status']}', `processor_message` = '{$log_to_database_build['processor_message']}', `processor_submission_id` = '{$log_to_database_build['processor_submission_id']}', `processor_payload` = '{$log_to_database_build['processor_payload']}' WHERE `transaction_id` = {$omeda_transaction_id}");

		$wpdb->query($sql);
	}
}

// Warning: Array to string conversion in /dom40473/wp-content/plugins/omeda-staging/omeda.php on line 148

function initiate_omeda_processor($order_id, $old_status, $new_status, $order) {
	@omeda_processor($order_id, $old_status, $new_status, $order);
}

// add_action('admin_init', 'omeda_processor_');

function omeda_processor_() {
	// omeda_processor(1305909, "drt", "completed", false);
}

function gpm_get_omeda_product_type($omeda_product_id) {
	// Ensure we have a valid product ID to check
    if (empty($omeda_product_id)) {
        return null;
    }

	$omeda_data = get_option('gpm_omeda_products_data');
	$omeda_products = $omeda_data['product_array'] ?? [];
	if(!empty($omeda_products)){
		foreach ($omeda_products as $omeda_product) {
		if (isset($omeda_product['ProductId']) && $omeda_product['ProductId'] == $omeda_product_id) {
				return $omeda_product['ProductType'] ?? null;
			}
		}
	}
	return null; // Not found
}

function omeda_processor($order_id, $old_status, $new_status, $order) {
	global $c ,$directory, $wpdb;

	if(($old_status == $new_status) || ($new_status != 'completed')) {
		return;
	}
	echo INCLUDE_OMEDA();

	$order = wc_get_order($order_id);

	// $order->update_status( 'on-hold' );
	

	$customer_id = $order->get_customer_id();
	
	$trigger_categories = array('28368','28378', '28344', '27352');

	$filler_products = array();

	$sites_omeda_id = [
	    'artistsnetwork.com' => [172, 16],
	    'interweave.com' => [174, 18],
	    'quiltingdaily.com' => [173, 17],
	    'sewdaily.com' => [175, 19],
		'goldenpeakmedia.com' => [0, 0]
	];

	$filler_products_id = (function($properties) {
	    $current_host = preg_replace('/^www\./', '', implode('.', array_slice(explode('.', $_SERVER['HTTP_HOST']), -2)));
	    if (isset($properties[$current_host]) && !empty($properties[$current_host])) {
	        return $properties[$current_host];
	    } else {
	        return false;
	    }
	})($sites_omeda_id);

	$products_list = $productsBuild = $products_to_gift = array();

	$log_to_database_build = array();

	$log_to_database_build['order_id'] = $order_id;
	$log_to_database_build['user_id'] = $customer_id;

	try {
		foreach($order->get_items() as $line_item_id => $line_item) {
			$variation_id = null;

			if (isset($line_item['variation_id'])) {
			    $variation_id = $line_item['variation_id'];
			}

			if($variation_id !== null) {
				$product_id = $variation_id;
			}

			$product_categories = array();
			$product_slug = array();
			$product_cat_names = array();

			$darwin_marketing_code = $wpdb->get_var($wpdb->prepare('select pm.meta_value from wp_postmeta as pm WHERE pm.post_id = %d AND pm.meta_key = "_darwin_marketing_code"', array($product_id)));
	
			if (!$darwin_marketing_code && $darwin_marketing_code != null) {
				throw new Exception("no darwin marketing code");
			}

			$product_id = $line_item->get_product_id();
			if($variation_id !== null) {
				$om_product = wc_get_product($variation_id);
			}else{
				$om_product = wc_get_product($product_id);
			}

			try {
	    		$grabProductCat = (get_the_terms($product_id, 'product_cat'));

	    		foreach($grabProductCat as $cat) {
	    			$cat_term_id = $cat->term_id;
	    			$cat_slug = $cat->slug;
	    			$product_categories[] = $cat_term_id;
	    			$product_slug[$cat_term_id] = $cat_slug;
	    			$product_cat_names[] = str_replace('-', ' ', $cat_slug);
	    			$itemSKU = $line_item->get_product()->get_sku();
	    			$item_price = $line_item->get_product()->get_price();
	    		}

	    		if (empty((array_intersect($product_categories, $trigger_categories)))) {
	    			throw new Exception("product category not accepted");
	    		}

	    		$build_products['PersonalIdentifier'] = $product_slug[array_shift(array_intersect($product_categories, $trigger_categories))];
			} catch (Exception $e) {
				$log_to_database_build['sent_receive_message']['error']['product']['category'][$product_id] = ["in a category that is not acceptable", $e->getMessage()];
	    		continue;
			}	

			$item_cat_list = $build_products = array();
			$omeda_product_id = $subscription_term = null;

			$item_subscription = false;

			try {
				$item_subscription = WC_Subscriptions_Product::is_subscription($product_id);
			} catch (Exception $e) {
				$log_to_database_build['sent_receive_message']['error']['product']['subsciption'][$product_id] = ["unable to identify as subsciption", $e->getMessage()];
	    		continue;
			}

			$itemTitle = $line_item->get_product()->get_title();
			$itemSKU = $line_item->get_product()->get_sku();

			try {
				$omeda_product_id = end(get_post_meta($product_id, '_product_attributes'))['omedaproductid']['value'];

				if(!isset($omeda_product_id)) {
					throw new Exception("omedaproductid not set");
				}
			} catch (Exception $e) {
				$log_to_database_build['sent_receive_message']['error']['product']['attribute'][$product_id] = ["unable to get omedaproductid attribute", $e->getMessage()];
	    		continue;
			}

			if($item_subscription) {
				try {
					$subscription_opt = end(get_post_meta($product_id, '_product_attributes'))['subscriptionopt']['value'];

					if(isset($subscription_opt)) {
						$subscription_opted_in = get_user_meta($customer_id, 'subscription_opted', true);

						if($subscription_opted_in !== 'true') {
							throw new Exception("user is not opted in receiving mags with subscription");
						}
					}
				} catch (Exception $e) {
					$log_to_database_build['sent_receive_message']['error']['product']['attribute'][$product_id] = ["subscriptionopt attribute error", $e->getMessage()];
		    		continue;
				}
			}

			$dates = array('month' => 1, 'year' => 6);

			$variation_id = '';

			if($item_subscription) {
				try {
					$variation_id = ($line_item['variation_id']);

					$period = WC_Subscriptions_Product::get_period($variation_id);

					$omeda_term = end(get_post_meta($product_id, '_product_attributes'))['omedaterm']['value'];

					if(!isset($omeda_term)) {
						throw new Exception("omedaterm not set"); 
					}

					$build_products['Term'] = ($omeda_term);

					if (str_contains($omeda_term, '|')) { 
						$term_split = explode(' | ', $omeda_term);

						if (count($term_split) !== 2) {
							throw new Exception("omedaterm is invalid - check product settings");
						}

						$dates = array('month' => $term_split[0], 'year' => $term_split[1]);

						$build_products['Term'] = ($dates[$period]);
					}

					
				} catch (Exception $e) {
					$log_to_database_build['sent_receive_message']['error']['product']['attribute'][$product_id] = ["unable to get omedaterm attribute", $e->getMessage()];
			    		continue;
				}
			} else {
				$build_products['Sku'] = $itemSKU;
				$build_products['Term'] = $dates['month'];
			}

			$products_list[] = $omeda_product_id;

			$get_order_item_meta = function ($order_item_id) use ($wpdb) {
			    try {
			        $results = $wpdb->get_results($wpdb->prepare(
			            "SELECT meta_key, meta_value 
			            FROM {$wpdb->prefix}woocommerce_order_itemmeta 
			            WHERE order_item_id = %d 
			            AND meta_key IN ('_line_subtotal', '_line_subtotal_tax', '_line_total', '_line_tax')",
			            $order_item_id
			        ), ARRAY_A);

			        if ($results) {
			            return array_column($results, 'meta_value', 'meta_key');
			        } else {
			            return null;
			        }
			    } catch (Exception $e) {
			        return null;
			    }
			};

			$grab_order_item_meta = $get_order_item_meta($line_item_id);

			if(is_null($grab_order_item_meta)) {
				$log_to_database_build['sent_receive_message']['error']['line_item_id'][$line_item_id]['empty'] = ["unable to continue as could not pull item metadata"];
				throw new Exception("encountered an error while retrieving item metadata");
			}

			$subtotal_for_item = $grab_order_item_meta["_line_subtotal"];
			$subtotal_tax_for_item = $grab_order_item_meta["_line_subtotal_tax"];

			$subtotal_for_item = $grab_order_item_meta["_line_subtotal"];
			$subtotal_tax_for_item = $grab_order_item_meta["_line_subtotal_tax"];
			$amount_paid = round($grab_order_item_meta["_line_total"], 2);
			$amount_tax_paid = round($grab_order_item_meta["_line_tax"], 2);
			$amount_paid_with_tax = round(($amount_tax_paid + $amount_paid), 2);
			$itemQty = $line_item['qty'];

			// check if product is a gift

			$product_is_gift_query = "
			    SELECT * FROM {$wpdb->prefix}postmeta
			    WHERE post_id = {$order_id}
			    AND meta_key LIKE '_gft_p\_%'
			    AND (meta_value = {$product_id}";

			if (!empty($variation_id) && $variation_id !== null) {
			    $product_is_gift_query .= " OR meta_value = {$variation_id}";
			}

			$product_is_gift_query .= ")";

			$product_is_gift_query_results = $wpdb->get_row($product_is_gift_query);

			foreach($product_cat_names as $cat_name) {
				$behaviors[] = array(
					'BehaviorAttributeTypeId' => 16,
					'BehaviorAttributeValue' => $cat_name
				);
			}

			$behaviors[] = array(
				'BehaviorAttributeTypeId' => 17,
				'BehaviorAttributeValue' => $itemSKU
			);

			$behaviors[] = array(
				'BehaviorAttributeTypeId' => 18,
				'BehaviorAttributeValue' => 'attribute-value'
			);

			if(!empty($darwin_marketing_code)) {
				$behaviors[] = array(
					'BehaviorAttributeTypeId' => 19,
					'BehaviorAttributeValue' => $darwin_marketing_code
				);
			}

			$behaviors[] = array(
				'BehaviorAttributeTypeId' => 20,
				'BehaviorAttributeValue' => strval((isset($subtotal_for_item) && !empty($subtotal_for_item) ? $subtotal_for_item : 0))
			);

			$behaviors[] = array(
				'BehaviorAttributeTypeId' => 37,
				'BehaviorAttributeValue' => strval((isset($amount_tax_paid) && !empty($amount_tax_paid) ? $amount_tax_paid : 0))
			);

			$RequestedVersiontype = ($om_product && $om_product->is_virtual())?'D':'P';
			$requested_version = get_field('requested_version_enabledisable', $product_id);
			if ($requested_version && in_array('enable', $requested_version)) 
			{
				$subscription_type = '';

				if ( $om_product && is_object($om_product) ) {
					$attributes = $om_product->get_attributes();

					if ( isset($attributes['subscription-type']) ) {
						$attr = $attributes['subscription-type'];
						$subscription_type = is_object($attr) ? implode(', ', $attr->get_options()) : $attr;
					}
				}

				if ($subscription_type =='Print + Digital') 
				{
				    $RequestedVersiontype='B';
				} elseif ($subscription_type =='Digital') {
					$RequestedVersiontype='D';	
				} else {
					$RequestedVersiontype='P';
				}	

			}
			
			$products = array(
				'Amount' => number_format((isset($amount_paid) && !empty($amount_paid) ? $amount_paid : 0), 2),
				'AmountPaid' => number_format((isset($amount_paid_with_tax) && !empty($amount_paid_with_tax) ? $amount_paid_with_tax : 0), 2),
				'SalesTax' => number_format((isset($amount_tax_paid) && !empty($amount_tax_paid) ? $amount_tax_paid : 0), 2),
				'OmedaProductId' => $omeda_product_id,
				'RequestedVersion' => $RequestedVersiontype,
				'Quantity' => $itemQty,
				'adhoc_sku' => $itemSKU,
				'adhoc_product_id' => $product_id,
				'adhoc_variation_id' => $variation_id,
				'adhoc_behaviors' => $behaviors
			);

			$behaviors = array();

			$gift_products = $products = array_merge($products, $build_products);

			// if it is a gift it will be sent to gift for peak for processing
			if (isset($product_is_gift_query_results)) {
				$post_id = $product_is_gift_query_results->post_id;
				$meta_key = $product_is_gift_query_results->meta_key;
				$meta_value = $product_is_gift_query_results->meta_value;
				$item_price = wc_get_product($product_id)->get_price();

				$gift_uniq_id = end(explode("p_", $meta_key));

				//update_post_meta($post_id, '_gft_ot_' . $gift_uniq_id, false);

				$gift_shipping_details = get_post_meta($post_id, '_gft_sd_' . $gift_uniq_id, true);
				$gift_shipping_details_md5 = md5($gift_shipping_details);

				$products_to_gift[$gift_shipping_details_md5][$gift_uniq_id]['products'] = $gift_products;
				$products_to_gift[$gift_shipping_details_md5][$gift_uniq_id]['shipping'] = json_decode($gift_shipping_details, true);

				update_post_meta($post_id, '_gft_ot_' . $gift_uniq_id, true);

				if($products['Quantity'] >= 1) {
					array_push($productsBuild, $products);
				}
			} else {
				// if it is not it will continue normal processing
				array_push($productsBuild, $products);
			}
		}

		if(isset($log_to_database_build['sent_receive_message']['error']['product']['category']) || isset($log_to_database_build['sent_receive_message']['error']['product']['subsciption']) || isset($log_to_database_build['sent_receive_message']['error']['product']['attribute'])) {
			$parent_array = $log_to_database_build['sent_receive_message']['error']['product'];

			foreach($parent_array as $key => $value) {
				$product_id = key($parent_array[$key]);

				foreach($parent_array[$key] as $keyo => $valueo) {
					$sent_receive_message = trim(addslashes(json_encode($parent_array[key($parent_array)][$keyo])));

					$log_to_database_build['sent_receive_status'] = 'ERROR';

					$sql = $wpdb->prepare("INSERT INTO `omeda_queue` (`sku`, `order_id`, `sent_receive_message`, `user_id`, `sent_receive_status`) values ({$keyo}, {$log_to_database_build['order_id']}, '{$sent_receive_message}', {$log_to_database_build['user_id']}, '{$log_to_database_build['sent_receive_status']}')");

					$wpdb->query($sql);

					$order->add_order_note(__("OMEDA → " . $sent_receive_message));
				}

				unset($log_to_database_build['sent_receive_message']['error']['product'][$key]);
			}
		}

		if(!$productsBuild && !$products_to_gift) {
			$log_to_database_build['sent_receive_message']['error']['product'][$product_id]['empty'] = ["unable to continue as empty build"];
			throw new Exception("empty build");
		}

		if($productsBuild) {
			foreach(array_chunk($productsBuild, 1) as $product) {
				try {
					$marketing_country = '';

					$adhoc_sku = $product[0]['adhoc_sku'];
					$adhoc_product_id = $product[0]['adhoc_product_id'];
					$adhoc_variation_id = $product[0]['adhoc_variation_id'];
					$adhoc_behaviors = $product[0]['adhoc_behaviors'];

					unset($product[0]['adhoc_sku']);
					unset($product[0]['adhoc_product_id']);
					unset($product[0]['adhoc_variation_id']);
					unset($product[0]['adhoc_behaviors']);
					
					//unset sku for omeda product type 1 as this is not require
 					$product_type = gpm_get_omeda_product_type($omeda_product_id);
					if (!empty($product_type) && $product_type == 1) {
						unset($product[0]['Sku']);
					}
					
					if (strtolower($order->get_shipping_country()) == 'us') {
						$marketing_country = '_US';
					} else if (strtolower($order->get_shipping_country()) == 'ca') {
						$marketing_country = '_CA';
					} else {
						$marketing_country = '_INT';
					}

					if(!empty($adhoc_variation_id)) {
						$adhoc_product_id = $adhoc_variation_id;
					}

					$darwin_marketing_code = $wpdb->get_var($wpdb->prepare('select pm.meta_value from wp_postmeta as pm WHERE pm.post_id = %d AND pm.meta_key = "_darwin_marketing_code"', array($adhoc_product_id)));
	
					if (!$darwin_marketing_code && $darwin_marketing_code != null) {
						throw new Exception("no darwin marketing code");
					}

					$useShippingInfo = function($type) use ($order) {
						$shippingGetter = "get_shipping_$type";
						$billingGetter = "get_billing_$type";
						$result = $order->$shippingGetter() ?: $order->$billingGetter();
    
					    if ($type === 'country') {
					        $countryCodes = get_all_iso_country_codes();
					        return isset($countryCodes[$result]) ? $countryCodes[$result] : $result;
					    }
					    
					    return $result;
					};

					$payload = [
						"RunProcessor"=> 1,
						'ExternalCustomerId' => $order_id . substr(uniqid(), 0, 5),
						'ExternalCustomerIdNamespace' => 'WooCommOrd',
						'PromoCode' => $darwin_marketing_code,
						'FirstName' => $useShippingInfo('first_name'),
						'LastName' => $useShippingInfo('last_name'),
						'Addresses' => [
							[
								'Company' => $useShippingInfo('company'),
								'Street' => $useShippingInfo('address_1'),
								'ApartmentMailStop' => $useShippingInfo('address_2'),
								'City' => $useShippingInfo('city'),
								'Region' => $useShippingInfo('state'),
								'PostalCode' => $useShippingInfo('postcode'),
								'CountryCode' => $useShippingInfo('country'),
								'AddressProducts' => join(",", $products_list)
							]
						],
						'BillingInformation' => [
							'CreditCardNumber' => '4111111111111111',
							'ExpirationDate' => '0226',
							'CardSecurityCode' => '111',
							'NameOnCard' => "{$useShippingInfo('first_name')} {$useShippingInfo('last_name')}",
							'DoCharge' => 'False',
							'BillingCompany' => $useShippingInfo('company'),
							'BillingStreet' => $useShippingInfo('address_1'),
							'BillingApartmentMailStop' => $useShippingInfo('address_2'),
							'BillingCity' => $useShippingInfo('city'),
							'BillingRegion' => $useShippingInfo('state'),
							'BillingPostalCode' => $useShippingInfo('postcode'),
							'BillingCountryCode' => $useShippingInfo('country'),
							'Comment1' => $customer_id,
							'Comment2' => $order->get_payment_method_title(),
							'DepositDate' => date('Y-m-d ', strtotime('-1 days')),
							'AuthCode' => $order->get_transaction_id() ?: bin2hex(random_bytes(10))
						],
						'Emails' => [
							[
								'EmailAddress' => $order->get_billing_email()
							]
						],
						'Phones' => [
							[
								'Number' => $order->get_billing_phone()
							]
						],
						'Products' => $product,
						'CustomerBehaviors' => [
							[
								'BehaviorId' => $filler_products_id[1],
								'BehaviorDate' => date('Y-m-d H:i:s'),
								'BehaviorAttributes' => $adhoc_behaviors
							]
						]
					];

					if(strpos($adhoc_sku, 'MEM') !== false) {
						unset($payload['Products']);
						unset($payload['BillingInformation']);
					}

					$amount = $payload['Products'][0]['Amount'];
					$amountPaid = $payload['Products'][0]['AmountPaid'];

					if (empty($amount) || $amount === '0.00' || empty($amountPaid) || $amountPaid === '0.00' || $amountPaid === 0) {
					    unset($payload['BillingInformation'], $payload['Phones']);
					}
					
					if(strpos($adhoc_sku, 'MEM') !== false) {
						unset($payload['Products']);
						unset($payload['BillingInformation']);
					}

					if($filler_products_id[1] == 0) {
						unset($payload['CustomerBehaviors']);
					}
				} catch (exception $e) { 
					$log_to_database_build['sent_receive_message']['error']['payload'] = 'unable to build payload to send to omeda:' . $e->getMessage();
					throw new Exception("hard stop error");
				}

				if(!empty($filler_products)) {
					array_push($payload['Products'], ...$filler_products);
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

					if($products_to_gift) {
							$omeda_customer_id = $omeda_response_data['CustomerId'];
							wp_gpm_custom_logger_omeda($omeda_customer_id);
							process_gift_through_omeda($products_to_gift, $order, $order_id, $customer_id, $products_list, $filler_products_id,$omeda_customer_id);
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

				run_report($log_to_database_build);
			}
		}
	} catch (Exception $e) { $log_to_database_build['sent_receive_message']['error']['stop'] = 'hard stop due to: ' . $e->getMessage(); run_report($log_to_database_build); }
}


add_action('woocommerce_variation_options_pricing', 'gpm_add_darwin_field_to_variations', 10, 3);
function gpm_add_darwin_field_to_variations($loop, $variation_data, $variation) {
    $promo_code = get_post_meta($variation->ID, '_darwin_marketing_code', true);
    ?>
    <div class="options_group">
        <?php
        woocommerce_wp_text_input([
            'id' => 'darwin_marketing_code_' . $loop,
            'name' => 'darwin_marketing_code[' . $variation->ID . ']',
            'value' => esc_attr($promo_code),
            'label' => __('Promo Code', 'woocommerce'),
            'description' => __('Enter promo code for this variation.', 'woocommerce'),
            'desc_tip' => true,
            'type' => 'text',
        ]);
        ?>
    </div>
    <?php
}


add_action('woocommerce_save_product_variation', 'gpm_save_darwin_field_variations', 10, 2);
function gpm_save_darwin_field_variations($variation_id, $i) {
    if (isset($_POST['darwin_marketing_code'][$variation_id])) {
        update_post_meta($variation_id, '_darwin_marketing_code', sanitize_text_field($_POST['darwin_marketing_code'][$variation_id]));
    }
}
