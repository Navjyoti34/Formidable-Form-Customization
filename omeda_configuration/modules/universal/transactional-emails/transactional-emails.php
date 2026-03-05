<?php

/*
Plugin Name: Transactional E-mails
Plugin URI:
Description: Governs the ability to manage e-mail templates from admin.
Version: 1.0
Author:  James
Author URI: http://midtc.com/
License:
*/

// Exit if accessed directly.
if (! defined('ABSPATH')) exit;

include($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

add_action('woocommerce_cart_updated', 'track_abandoned_cart');
add_action('admin_init', 'transactional_emails_manager_file_upload');
add_action('admin_init', 'transactional_emails_manager_wp_enqueue', 0);
add_action('process_transactional_emails_abandoned_cart_hook', 'process_transactional_emails_abandoned_cart');
add_action('wp_loaded', 'setup_transactional_scheduling_event');
add_action('wp_loaded', 'transactional_emails_check_cart');
add_action('admin_menu', 'transactional_emails_add_menu_item');

function transactional_emails_recursive_unserialize($data) {
    while (is_serialized($data)) {
        $unserializedData = unserialize($data);
        if ($unserializedData !== false) {
            $data = $unserializedData;
        } else {
            break;
        }
    }
    return $data;
}


function transactional_emails_arrays_equal($array1, $array2) {
    if (count($array1) !== count($array2)) {
        return false;
    }

    foreach ($array1 as $key => $value) {
        if (!array_key_exists($key, $array2)) {
            return false;
        }

        if (is_array($value) && is_array($array2[$key])) {
            if (!transactional_emails_arrays_equal($value, $array2[$key])) {
                return false;
            }
        } elseif ($value !== $array2[$key]) {
            return false;
        }
    }

    return true;
}

function track_abandoned_cart() {
    global $woocommerce;

	$items = $woocommerce->cart->get_cart();

    if (!empty($items)) {
        global $wpdb;

        $cart_contents = array();

		foreach ($items as $item_key => $item) {
		    $product = $item['data'];
		    $product_id = $product->get_id();
		    $product_name = $product->get_name();
		    $product_link = $product->get_permalink();
		    $product_image = wp_get_attachment_image_url($product->get_image_id(), 'full');
		    $quantity = $item['quantity'];

		    $cart_contents[] = array(
		        'product_id' => $product_id,
		        'product_name' => $wpdb->esc_like($product_name),
		        'product_link' => $product_link,
		        'product_image' => $product_image,
		        'quantity' => $quantity,
		    );
		}

		foreach ($cart_contents as $item_key => $item) {
		    $product = wc_get_product($item['product_id']);

		    if ($product && $product->is_type('bundle')) {
		        $bundled_items = $product->get_bundled_items();

		        foreach ($bundled_items as $bundled_item) {
		            $bundled_product = $bundled_item->get_product();

		            if ($bundled_product) {
		                $bundled_product_id = $bundled_product->get_id();
		                $bundled_product_name = $wpdb->prepare('%s', $bundled_product->get_name());
		                $bundled_product_link = $bundled_product->get_permalink();

		                foreach ($cart_contents as $item_key_ => $item_) {
		                    if ($item_['product_id'] == $bundled_product_id) {
		                        unset($cart_contents[$item_key_]);
		                        break;
		                    }
		                }
		            }
		        }
		    }
		}

		return;

		$new_date = (new DateTime())->setTimezone(new DateTimeZone('America/New_York'))->modify('+' . transactional_emails_abandoned_time_to_add())->format('Y-m-d H:i:s');

        $abandoned_cart_data = array(
            'user_id' => get_current_user_id(),
            'cart_contents' => serialize($cart_contents),
            'cart_total' => WC()->cart->total,
            'abandoned_at' => date('Y-m-d H:i:s', time()),
            'abandoned_email_send' => $new_date
        );

        if($abandoned_cart_data['user_id'] == 0) {
        	return;
        }

        $table_name = 'midtc_abandoned_carts';
        $existing_user_id = $wpdb->get_row($wpdb->prepare("SELECT user_id, cart_contents FROM $table_name WHERE user_id = %d", $abandoned_cart_data['user_id']));

        if ($existing_user_id->user_id) {
        	$current_cart_contents = $existing_user_id->cart_contents;
        	$cart_items_serialized = serialize($abandoned_cart_data['cart_contents']);

        	$data_current_cart_contents = transactional_emails_recursive_unserialize($current_cart_contents);
        	$data_cart_items_serialized = transactional_emails_recursive_unserialize($cart_items_serialized);

			if (!(is_array($data_current_cart_contents) || is_array($data_cart_items_serialized))) {
				return;
			}

			if (transactional_emails_arrays_equal($data_current_cart_contents, $data_cart_items_serialized)) {
				return;
			}

			$query = "UPDATE `$table_name` SET cart_contents = '" . $cart_items_serialized . "', cart_total = '" . $abandoned_cart_data['cart_total'] . "', abandoned_at = '" . $abandoned_cart_data['abandoned_at'] . "', abandoned_email_send = '" . $abandoned_cart_data['abandoned_email_send'] . "', stage = 0 WHERE user_id = " . $abandoned_cart_data['user_id'] . ";";

			$update_query = $wpdb->prepare($query);

			$wpdb->query($update_query);

			delete_user_meta(get_current_user_id(), 'transactional_emails_abandoned_cart_status');
        } else {
            $wpdb->insert($table_name, $abandoned_cart_data);

            delete_user_meta(get_current_user_id(), 'transactional_emails_abandoned_cart_status');
        }
    } else {
    	transactional_emails_check_cart();
    }
}

function transactional_emails_add_menu_item() {
    add_submenu_page('woocommerce-marketing', 'Transactional Emails', 'Transactional Emails', 'manage_options', 'transactional_emails_manager', 'transactional_emails_manager', 99);
}


require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
require_once(ABSPATH . 'wp-admin/includes/template.php');
require_once(__DIR__ . '/reviews-ratings.php');

class Custom_Table extends WP_List_Table {
    function get_columns() {
        $columns = array(
            'template' => 'Template Name',
            'omeda' => 'Omeda Track Id',
            'file' => 'File',
            'download' => 'Download',
            'view' => 'View',
            'test' => 'Test Email',
            'date' => 'Date Uploaded',
        );
        return $columns;
    }

    function prepare_items() {
    	$data = $data_ = "";

        // delete_option('transactional_emails_manager_templates');
    	
    	if(get_option('transactional_emails_manager_templates')) {
    		$data = json_decode(get_option('transactional_emails_manager_templates'), true);
			$data_ = [];

    		foreach ($data as $key => $row) {
	        	$template_name = $key;
	        	$file_path = $data[$key]['file'];
	        	$file_details = get_file_details_by_path($file_path);

	        	$data_[] = array(
	        		'template' => str_replace("_", " ", ucwords($template_name, "_")),
	        		'omeda' => $data[$key]['track_id'],
	        		'file' => basename($file_path),
	        		'download' => '<a href="/wp-content/uploads' . $file_details['file'] . '" download><span class="dashicons dashicons-download"></span></a>',
	        		'view' => '<a href="/wp-content/uploads' . $file_details['file'] . '" target="_blank"><span class="dashicons dashicons-visibility"></span></a>',
	        		'test' => '<div id="transactional-emails-test-container"><input type="text" style="margin-top: 5px !important;height: 25px !important; min-height: 25px !important;" id="transactional-emails-test" data-id="' . $template_name . '" name="transactional-emails-test" placeholder="Enter your email"><button type="button" id="transactional-emails-test-button" style="cursor:pointer;margin:5px;">»</button></div>',
	        		'date' => $file_details['date']
	        	);
	        }
    	}

        $this->_column_headers = array($this->get_columns(), array(), array());
        $this->items = $data_;
    }

    function column_default($item, $column_name) {
        return $item[$column_name];
    }

    function display() {
        $this->prepare_items();
        parent::display();
    }
}

function get_file_details_by_path($file_path) {
	$wp_upload_dir = wp_upload_dir();
	$relative_path = str_replace($wp_upload_dir['basedir'], '', $file_path);
	$metadata = wp_get_attachment_metadata($relative_path);

	$upload_date = filemtime($file_path);
	$formatted_date = date('Y-m-d H:i:s', $upload_date);

	$file_details = array(
	    'file'      => $relative_path,
	    'date'		=> $formatted_date
	);

	return $file_details;
}

function remove_tr_from_table($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);

    $dom->loadHTML($html);

    $trElement = $dom->getElementsByTagName('tr')->item(0);

    if ($trElement) {
        $trElement->parentNode->removeChild($trElement);
    }

    $updatedHtml = $dom->saveHTML();

    return $updatedHtml;
}

function send_out_email($email, $template_name, $template_html, $omeda_track_id, $email_subject, $additionalmergedVariables = array(), $test_email = True) {
	global $c;

	echo INCLUDE_OMEDA();

	$user_id = False;

	$mergedVariables = array([
		'site_cart_link' => get_option('siteurl') . '/cart/?p=abandoned_cart'
	]);

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

	$first_name = get_user_meta($user_id, 'first_name', true) ?: get_user_meta($user_id, 'billing_first_name', true) ?: 'John';
	$last_name = get_user_meta($user_id, 'last_name', true) ?: get_user_meta($user_id, 'billing_last_name', true) ?: 'Smith';

	$html_content = file_get_contents($template_html);
	$email_subject = stripslashes($email_subject);

	if($test_email) {
		$template_name = str_replace("_", " ", ucwords($template_name, "_"));
		$current_site_url = get_site_url();
		$site_name = get_bloginfo('name');
		$date_sent = (new DateTime(null, new DateTimeZone('America/New_York')))->format('F j, Y @ g:i:s A');

		$default_message = '<p><span style="color: red; font-weight: bold;">Attention</span>: <strong>This is a test email that was sent out on ' . $date_sent . ' manually using the <a href="' . $current_site_url . '/wp-admin/admin.php?page=transactional_emails_manager" target="_blank">Transactional Emails Manager</a> page of <a href="' . $current_site_url . '" target="_blank">' . $site_name . '</a>. If you are experiencing issues with image display, please ensure that your email client is not blocking external images. If the issue persists, <i>double-check</i> that all images in the template are correctly linked. Thank you for your attention. Below you will find the contents of the "' . $template_name . '" template.</strong></p>';

		$email_subject = '[OMEDA] ' . $email_subject . ' [EMAIL TEST]';
		$html_content = $default_message . $html_content;
	}

	if(!empty($additionalmergedVariables)) {
		$mergedVariables = array(array_merge($additionalmergedVariables[0], $mergedVariables[0]));
	}

	if($template_name == 'Abandoned Cart') {
		$mergedVariables[0]['product_table'] = '
		<table style="padding-top:5px;">
		  <tbody>
		    <tr>
		      <td>
		        <img src="https://dev.artistsnetwork.com/wp-content/uploads/2023/03/SWA_20230501-scaled.jpg" alt="Product 1" width="320px">
		        <h2 style="padding-bottom:5px;text-align: center;">Product 1 Title</h2>
		      </td>
		    </tr>
		    <tr>
		      <td>
		        <img src="https://dev.artistsnetwork.com/wp-content/uploads/2023/04/TAM_20230601-scaled.jpg" alt="Product 2" width="320px">
		        <h2 style="padding-bottom:5px;text-align: center;">Product 2 Title</h2>
		      </td>
		    </tr>
		  </tbody>
		</table>';
	}

	$dom = new DOMDocument();

	$product_table = $mergedVariables[0]['product_table'];

	$product_table_remove_white_space = preg_replace('/\s+/', ' ', $product_table);

	$dom->loadHTML($product_table_remove_white_space, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

	$elements = $dom->getElementsByTagName('h2');

	foreach ($elements as $element) {
	    $innerText = $element->textContent;
	    $encodedText = htmlentities(htmlspecialchars($innerText, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	    $element->nodeValue = $encodedText;
	}

	$count = 0;

	while (strlen($product_table_remove_white_space) > 2001) {
		if($count >= 3) {
			break;
		}
		
	   	$product_table_remove_white_space = remove_tr_from_table($product_table_remove_white_space);

	    $count++;
	}

	$mergedVariables[0]['product_table'] = $product_table_remove_white_space;

	// $email = 'james@selfo.io';

	$fields = array(
		'TrackId' => $omeda_track_id,
		'EmailAddress' => $email,
		'FirstName' => $first_name,
		'LastName' => $last_name,
		'Subject' => $email_subject,
		'HtmlContent' => $html_content,
		'Preference' => 'HTML',
		'MergeVariables' => $mergedVariables
   	);

	$omedaCall = omedaCurl("{$c('ON_DEMAND_ENDPOINT')}{$c('OMEDA_DIRECTORY')['send_email']}", json_encode($fields));

	return $omedaCall;
}

function transactional_emails_manager() {
	$transactional_emails_manager_options = json_decode(get_option('transactional_emails_manager_templates'), true);

	if(isset($_GET['test_email']) && $_GET['test_email'] === 'true' && isset($_GET['email']) && isset($_GET['template_name'])) {

		$e_mail = $_GET['email'];
		$template_name = $_GET['template_name'];
		$avail_templates = json_decode(get_option('transactional_emails_manager_templates'), true);

		header('HTTP/1.1 200 OK');
		header('Content-Type: text/html');
		ob_clean();

		if($template_name == 'ratings_reviews') {
			$send_payload_to_omeda = send_out_review_email($e_mail, $template_name);
		} else {
			$send_payload_to_omeda = send_out_email($e_mail, $template_name, $avail_templates[$template_name]['file'], $avail_templates[$template_name]['track_id'], $avail_templates[$template_name]['email_subject']);
		}

		$send_decode_omeda_payload_status = $send_payload_to_omeda[1];

		if($send_decode_omeda_payload_status == 400) {
			$grab_omeda_errors = json_decode($send_payload_to_omeda[0], true);
			die(json_encode($grab_omeda_errors));
		}

		if($send_decode_omeda_payload_status == 404) {
			$grab_omeda_errors = json_decode($send_payload_to_omeda[0], true);
			die(json_encode($grab_omeda_errors));
		}

		if($send_decode_omeda_payload_status !== 200) {
			$grab_omeda_errors = json_decode($send_payload_to_omeda[0], true);
			die(json_encode($grab_omeda_errors));
		}

		$send_decode_omeda_payload = json_decode($send_payload_to_omeda[0], true);

		if(!isset($send_decode_omeda_payload)) {
			die('unknown error');
		}

		die(json_encode($send_decode_omeda_payload));
	}
	
    ?>
    <div class="wrap">
    <h1>Transactional Emails</h1>
    <?php add_transactional_general_notification(); ?>
    <h2 class="nav-tab-wrapper">
        <a href="#overview" class="nav-tab active">Overview</a>
        <a href="#abandoned" class="nav-tab">Abandoned Cart</a>
        <a href="#ratingnreview" class="nav-tab">Ratings and Reviews</a>
    </h2>
    
    <div class="tab-content">
        <div id="overview" class="tab-panel active">
             <div class="wrap">
        <p>This gives an overview of current files assisgned to their respected templates. New files can be uploaded to update the template.
        <br/><br/>
        <h3>Current Templates</h3>
    <?php
    	$table = new Custom_Table();
		$table->display();
	?>
		<br/><br/>
		<h3>Update Template</h3>
        <form method="post" enctype="multipart/form-data">
		    <?php wp_nonce_field('transactional_emails_manager_uploader', 'transactional_emails_manager_uploader_nonce'); ?>
		    <table class="form-table">
		        <tr>
		            <th scope="row"><label for="transactional_emails_manager_dropdown">Select a template:</label></th>
		            <td>
		                <select name="transactional_emails_manager_dropdown" id="transactional_emails_manager_dropdown" class="regular-text">
		                    <option value="abandoned_cart" data-omeda-track-id="<?php echo ($transactional_emails_manager_options['abandoned_cart']['track_id']) ?? ('GPM230320002'); ?>" data-email-subject="<?php echo stripslashes(($transactional_emails_manager_options['abandoned_cart']['email_subject']) ?? ("Aren't you forgetting something?")); ?>">Abandoned Cart</option>
		                    <option value="ratings_reviews" data-omeda-track-id="<?php echo ($transactional_emails_manager_options['ratings_reviews']['track_id']) ?? ('GPM240716020'); ?>" data-email-subject="<?php echo stripslashes(($transactional_emails_manager_options['ratings_reviews']['email_subject']) ?? ("Reminder: Please Rate and Review Your Recent Purchased Product")); ?>">Ratings & Reviews</option>
		                </select>
		            </td>
		        </tr>
		        <tr>
				    <th scope="row"><label for="omeda_track_id_label">Omeda Track Id:</label></th>
				    <td>
				    <input type="text" class="regular-text" id="omeda_track_id_input" name="omeda_track_id_input" value="" disabled><span id="omeda_track_id_input_lock" class="dashicons dashicons-lock"></span></td>
				</tr>
				<tr>
				    <th scope="row"><label for="omeda_track_id_label">Email Subject:</label></th>
				    <td>
				    <input type="text" class="regular-text" id="transactional_emails_manager_subject" name="transactional_emails_manager_subject"></td>
				</tr>
		        <tr>
		            <th scope="row"><label for="html_file">HTML File:</label></th>
		            <td><input type="file" name="html_file" accept=".html"></td>
		        </tr>
		    </table>
		    <p class="submit">
		    	<input type="hidden" name="<?php echo get_current_screen()->id; ?>">
		        <input type="submit" name="upload_html" class="button button-primary" value="Update">
		    </p>
		</form>
		
    </div>
        </div>
        <div id="abandoned" class="tab-panel">
            <h3>Abandoned Cart</h3>
            <p>Here are the current carts for the last 30 days that have items inside them.</p>
<?php

$tables = new RAF_List_Table();
$tables->prepare_items();
$tables->display();

?>
        </div>
        <div id="ratingnreview" class="tab-panel">
			<h3>Ratings and Reviews</h3>
			<?php product_review_reminder_emails_log_page();?>
		</div>
    </div>
    </div>
</div>


   
    <?php
}

class RAF_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular' => 'referral',
            'plural'   => 'referrals',
            'ajax'     => false
        ));
    }

    private function maybe_unserialize( $original ) {
    if ( is_serialized( $original ) )
        return @unserialize( $original );
    return $original;
	}

    public function prepare_items() {
        $columns = $this->get_columns();
        $data = $this->get_customers();

        $perPage = 10;
        $currentPage = $this->get_pagenum();
        $offset = ($currentPage - 1) * $perPage;

        $this->set_pagination_args(array(
            'total_items' => count($data),
            'per_page'    => $perPage
        ));

        $this->_column_headers = array($columns);
        $this->items = array_slice($data, $offset, $perPage);
    }

    public function get_columns() {
        return array(
            'user'   => __('User', 'gens-raf'),
            'contents'       => __('Cart Contents', 'gens-raf'),
            'total' => __('Cart Total', 'gens-raf'),
            'date'     => __('Date Abandoned', 'gens-raf'),
            'date_send'     => __('Next Stage', 'gens-raf'),
            'status' => __('Status', 'gens-raf'),
            'stage' => __('Stage', 'gens-raf'),
        );
    }

    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? $item[$column_name] : '';
    }


	public function get_customers($hook = false) {
	    global $wpdb;

	    $table_name = 'midtc_abandoned_carts';

	    $data = array();

	    $results = $wpdb->get_results("SELECT * FROM $table_name");

	    if (!function_exists('build_contents')) {
		    function build_contents($contents, $hook = false) {
		        $product_names = array_column($contents, 'product_name');
		        $product_links = array_column($contents, 'product_link');
		        $product_images = array_column($contents, 'product_image');
		        $quantities = array_column($contents, 'quantity');
		        $product_ids = array_column($contents, 'product_id');

		        $products = array();

		        for ($i = 0; $i < count($product_names); $i++) {
		            $products[] = array(
		                'product_name' => $product_names[$i],
		                'product_link' => $product_links[$i],
		                'product_image' => $product_images[$i],
		                'quantity' => $quantities[$i],
		                'product_id' => $product_ids[$i],
		            );
		        }

		        if($hook) {
		        	return $products;
		        }

		        $links = array();

		        $build = "<ul>";

				foreach ($products as $item) {
					$build .= '<li>' . $item['quantity'] . ' x <a href="' . $item['product_link'] . '" target="_blank">' . $item['product_name'] . '</a></li>';
				}

				$build .= "</ul>";

		        $products = $build;

		        return $products;
		    }
		}



	    foreach ($results as $result) {
	        $contents = maybe_unserialize(unserialize($result->cart_contents));
	        $user_id = $result->user_id;

	        // delete_user_meta($user_id, 'transactional_emails_abandoned_cart_status');

			$meta_key = 'transactional_emails_abandoned_cart_status';

			$meta_value = get_user_meta($user_id, $meta_key, true);

			if (empty($meta_value)) {
				global $wpdb;

				$time_to_add = (new DateTime())->setTimezone(new DateTimeZone('America/New_York'))->modify('+' . transactional_emails_abandoned_time_to_add())->getTimestamp();
				$date_sent = date('F j, Y @ g:i:s A', $time_to_add);
				$new_time = date('Y-m-d H:i:s', $time_to_add);
				
				$table_name = 'midtc_abandoned_carts';

				$update_query = $wpdb->prepare(
				    "UPDATE $table_name SET abandoned_email_send = %s WHERE user_id = %d",
				    $new_time,
				    $user_id
				);

				$wpdb->query($update_query);
				$meta_value = 'Waiting to send abandoned cart email on ' . $date_sent . '.';
				update_user_meta($user_id, $meta_key, $meta_value);
			}

			$abandoned_email_send = $result->abandoned_email_send;
			$abandoned_at = $result->abandoned_at;

			$abandoned_email_send = (new DateTime($abandoned_email_send, new DateTimeZone('America/New_York')))->format('F j, Y @ g:i:s A');

			$abandoned_at = date('F j, Y @ g:i:s A', strtotime($abandoned_at));

	        $data[] = array(
	            'user' => ((!$hook) ? '<a href="/wp-admin/user-edit.php?user_id=' . $user_id . '" target="_blank">' . get_the_author_meta('display_name', $user_id) . '</a>' : $user_id),
	            'contents' => build_contents($contents, $hook),
	            'total' => $result->cart_total,
	            'date' => $abandoned_at,
	            'date_send' => $abandoned_email_send,
	            'status' => $meta_value,
	            'stage' => ($result->stage + 1)
	        );
	    }

	    return $data;
	}


    public function display_tablenav($which) {
        if ($which === 'top') {
            $this->pagination('top');
        } elseif ($which === 'bottom') {
            $this->pagination('bottom');
        }
    }

    public function pagination($position) {
        if (empty($this->_pagination_args)) {
            return;
        }

        $total_items = $this->_pagination_args['total_items'];
        $per_page = $this->_pagination_args['per_page'];

        $total_pages = ceil($total_items / $per_page);

        if ($total_pages <= 1) {
            return;
        }

        $current_page = $this->get_pagenum();
        $current_page = max(1, min($current_page, $total_pages));

        $start_page = max(1, $current_page - 2);
        $end_page = min($current_page + 2, $total_pages);

        echo '<div class="tablenav-pages ' . esc_attr($position) . '">';
        echo '<span class="displaying-num">' . sprintf(_n('%s item', '%s items', $total_items, 'gens-raf'), number_format_i18n($total_items)) . '</span>';

        echo '<span class="pagination-links">';
        $this->pagination_links($total_pages, $current_page, $start_page, $end_page);
        echo '</span>';

        echo '</div>';
    }

    public function pagination_links($total_pages, $current_page, $start_page, $end_page) {
        $args = array(
            'base'      => add_query_arg('paged', '%#%' . '#abandoned'),
            'total'     => $total_pages,
            'current'   => $current_page,
            'show_all'  => false,
            'end_size'  => 1,
            'mid_size'  => 2,
            'prev_next' => true,
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
        );

        if ($total_pages > 1) {

            echo paginate_links($args);
        }
    }
}

function transactional_emails_check_cart() {
    global $wpdb;

    $user_id = get_current_user_id();

    if (function_exists('WC') && WC()->cart && WC()->cart->is_empty() && $user_id) {
        $table_name = 'midtc_abandoned_carts';

        $existing_user_id = $wpdb->get_var(
            $wpdb->prepare("SELECT user_id FROM $table_name WHERE user_id = %d", $user_id)
        );

        if ($existing_user_id) {
            $delete_query = $wpdb->prepare("DELETE FROM $table_name WHERE user_id = %d", $user_id);
            $wpdb->query($delete_query);
        }

		$meta_key = 'transactional_emails_abandoned_cart_status';

		delete_user_meta($user_id, $meta_key);
    }
}


function transactional_emails_manager_update_options($template, $html_file, $omeda_track_id, $email_subject = '', $push_file = false) {
	// delete_option('transactional_emails_manager_templates');
    $option_value = get_option('transactional_emails_manager_templates');

    if ($option_value) {
    	$option_value = json_decode($option_value, true);
    } else {
    	$option_value = array();
    }

    if(!$push_file) {
	    if ($option_value) {
	        if (file_exists($option_value[$template]['file'])) {
			    if (!unlink($option_value[$template]['file'])) {
			    	 $message = 'An error occured file removing the file <strong>' . basename($option_value[$template]['file']) . '</strong> for template <strong>' . str_replace("_", " ", ucwords($template, "_")) . '</strong>.';
					 $class = 'notice notice-error is-dismissible transactional-emails';
					 echo '<div class="' . $class . '"><p>' . $message . '</p></div>';
			    }
			}
	        $option_value[$template]['file'] = $html_file;
	    } else {
	        $option_value[$template]['file'] = $html_file;
	    }
	}

	if($omeda_track_id !== false) {
		$option_value[$template]['track_id'] = $omeda_track_id;
	}

	if(!empty($email_subject)) {
		$option_value[$template]['email_subject'] = $email_subject;
	}

    $option_value = json_encode($option_value);
    update_option('transactional_emails_manager_templates', $option_value);
}


function setup_transactional_scheduling_event() {
    if(!wp_next_scheduled('process_transactional_emails_abandoned_cart_hook')) {
    	wp_schedule_event(time(), 'hourly', 'process_transactional_emails_abandoned_cart_hook');
    }
}


// add_action('admin_init', 'process_transactional_emails_abandoned_cart'); // remove during prod

function transactional_emails_abandoned_time_to_add() {
	return '60 minutes';
	#return '1 minutes';
}

function transactional_emails_abandoned_time_to_add_discount() {
	return '2880 minutes';
}

function transactional_emails_abandoned_time_to_delete() {
	return '4320 minutes';
}

function process_transactional_emails_abandoned_cart() {
	// trigger to happen every hour
	// remember to set that after 7 days the reminder comes in
	global $wpdb;

	$tables = new RAF_List_Table();
	$abandoned_cart_data = $tables->get_customers(true);

	foreach ($abandoned_cart_data as $cart_data) {
		$user_id = $cart_data['user'];
		$user_data = get_userdata($user_id);
		$billing_email = get_user_meta($user_id, 'billing_email', true);

		$user_email = $user_data->user_email;

		if ($billing_email) {
			$user_email = $billing_email;
		}

		if(!$user_email) {
			continue;
		}

		$contents = $cart_data['contents'];
		$total = $cart_data['total'];
		$date = $cart_data['date'];
		$date_send = $cart_data['date_send'];
		$unix_date = strtotime($date);
		$unix_date_send = strtotime(str_replace('@', '', $date_send));

		$time_now = (new DateTime('now', new DateTimeZone('America/New_York')))->getTimestamp();

		#return;

		// -------------- Discount Template Injection / ------------- //

		$current_stage = $cart_data['stage'];

		if($current_stage == 3 && (($unix_date_send) < $time_now)) {
			$table_name = 'midtc_abandoned_carts';

			$delete_query = $wpdb->prepare(
			    "DELETE FROM $table_name WHERE user_id = %d",
			    $user_id
			);

			$wpdb->query($delete_query);

		}

		if($current_stage == 2 && (($unix_date_send) < $time_now)) {
			$additionalmergedVariables = array();

			$tableContent = '';

			$limitedContents = array_slice($contents, 0, 3);

			$tableContent = '<style>#s{padding-bottom:5px;text-align: center;}</style>';

			foreach ($limitedContents as $content) {
				$product_name = $content['product_name'];
				$product_link = $content['product_link'];
				$product_image = $content['product_image'];

				$tableContent .= '<tr><td><center><a href="' . $product_link . '" target="_blank"><img src="' . $product_image . '" alt="' . $product_name . '" width="320px"><h2 id="s">' . $product_name . '</h2></a></center></td></tr>';
			}

			$additionalmergedVariables[0]['product_table'] = '<table style="padding-top:5px;">
			  <tbody>
			    ' . $tableContent . '
			  </tbody>
			</table>';

			$site_discount_templates = [
			    'artistsnetwork.com' => "artists-network-abandoned-cart-email-2.html",
			    'interweave.com' => "interweave-abandoned-cart-email-2.html",
			    'quiltingdaily.com' => "quilting-abandoned-cart-email-2.html",
			    'sewdaily.com' => "sew-daily-abandoned-cart-email-2.html"
			];

			$site_titles = [
			    'artistsnetwork.com' => "Artists Network",
			    'interweave.com' => "Interweave",
			    'quiltingdaily.com' => "Quilting Daily",
			    'sewdaily.com' => "Sew Daily"
			];

			$grab_abandoned_cart_discount_template = (function($templates) {
			    $current_host = preg_replace('/^www\./', '', implode('.', array_slice(explode('.', $_SERVER['HTTP_HOST']), -2)));
			    if (isset($templates[$current_host]) && !empty($templates[$current_host])) {
			        return $templates[$current_host];
			    } else {
			        return false;
			    }
			})($site_discount_templates);

			$grab_site_title = (function($templates) {
			    $current_host = preg_replace('/^www\./', '', implode('.', array_slice(explode('.', $_SERVER['HTTP_HOST']), -2)));
			    if (isset($templates[$current_host]) && !empty($templates[$current_host])) {
			        return ' ' . $templates[$current_host] . ' ';
			    } else {
			        return '';
			    }
			})($site_titles);

			$template_name = 'abandoned_cart';
			$avail_templates = json_decode(get_option('transactional_emails_manager_templates'), true);

			$date_sent = (new DateTime('now', new DateTimeZone('America/New_York')))->format('F j, Y @ g:i:s A');
			$meta_value = 'Discount abandoned cart email sent on ' . $date_sent;

			$grab_abandoned_cart_discount_template = (plugin_dir_path(__FILE__) . 'templates/' . $grab_abandoned_cart_discount_template);

			$send_payload_to_omeda = send_out_email($user_email, $template_name, $grab_abandoned_cart_discount_template, $avail_templates[$template_name]['track_id'], 'Save 15% on your' . $grab_site_title . 'Order!', $additionalmergedVariables, False);

			$send_decode_omeda_payload_status = $send_payload_to_omeda[1];
			$send_decode_omeda_payload = json_decode($send_payload_to_omeda[0], true);

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

			if(!empty($send_decode_omeda_payload)) {
				global $wpdb;

				$new_date_stage = (new DateTime())->setTimezone(new DateTimeZone('America/New_York'))->modify('+' . transactional_emails_abandoned_time_to_delete() . ' seconds')->format('Y-m-d H:i:s');
				$meta_value .= ' (' . $send_decode_omeda_payload['SubmissionId'] . '). Removing entry on ' . date('F j, Y @ g:i:s A', strtotime('+' . transactional_emails_abandoned_time_to_delete())) . '.';

				$table_name = 'midtc_abandoned_carts';

				$query = "UPDATE `" . $table_name . "` SET `abandoned_email_send` = '" . $new_date_stage . "', `stage` = '" . $current_stage . "' WHERE `user_id` = " . $user_id . ";";

				$update_query = $wpdb->prepare($query);

				$wpdb->query($update_query);
			}

			$meta_key = 'transactional_emails_abandoned_cart_status';

			if (!empty($meta_value)) {
				update_user_meta($user_id, $meta_key, $meta_value);
			}
		}

		// ------------- / Discount Template Injection ------------- //


		if ($current_stage == 1 && (($unix_date_send) < $time_now)) {
			$additionalmergedVariables = array();

			$tableContent = '';

			$limitedContents = array_slice($contents, 0, 3);

			$tableContent = '<style>#s{padding-bottom:5px;text-align: center;}</style>';

			foreach ($limitedContents as $content) {
				$product_name = $content['product_name'];
				$product_link = $content['product_link'];
				$product_image = $content['product_image'];

				$tableContent .= '<tr><td><center><a href="' . $product_link . '" target="_blank"><img src="' . $product_image . '" alt="' . $product_name . '" width="320px"><h2 id="s">' . $product_name . '</h2></a></center></td></tr>';
			}

			$additionalmergedVariables[0]['product_table'] = '<table style="padding-top:5px;">
			  <tbody>
			    ' . $tableContent . '
			  </tbody>
			</table>';

			$template_name = 'abandoned_cart';
			$avail_templates = json_decode(get_option('transactional_emails_manager_templates'), true);

			$date_sent = (new DateTime(null, new DateTimeZone('America/New_York')))->format('F j, Y @ g:i:s A');
			$meta_value = 'Abandoned cart email sent on ' . $date_sent;

			$send_payload_to_omeda = send_out_email($user_email, $template_name, $avail_templates[$template_name]['file'], $avail_templates[$template_name]['track_id'], $avail_templates[$template_name]['email_subject'], $additionalmergedVariables, False);

			$send_decode_omeda_payload_status = $send_payload_to_omeda[1];
			$send_decode_omeda_payload = json_decode($send_payload_to_omeda[0], true);

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

			if(!empty($send_decode_omeda_payload)) {
				global $wpdb;

				$new_stage_date = (new DateTime())->setTimezone(new DateTimeZone('America/New_York'))->modify('+' . transactional_emails_abandoned_time_to_add_discount())->format('Y-m-d H:i:s');

				$meta_value .= ' (' . $send_decode_omeda_payload['SubmissionId'] . '). Sending a discount email on ' . date('F j, Y @ g:i:s A', strtotime('+' . transactional_emails_abandoned_time_to_add_discount())) . '.';

				$table_name = 'midtc_abandoned_carts';

				$query = "UPDATE `$table_name` SET `abandoned_email_send` = %s, `stage` = %s WHERE `user_id` = %d";

				$update_query = $wpdb->prepare($query, $new_date_stage, $current_stage, $user_id);

				$wpdb->query($update_query);
			}

			$meta_key = 'transactional_emails_abandoned_cart_status';

			if (!empty($meta_value)) {
				update_user_meta($user_id, $meta_key, $meta_value);
			}
		}

		//echo $user_email;
	}
}

function add_transactional_general_notification() {
	$current_screen = get_current_screen();

	if($current_screen && $current_screen->id === 'marketing_page_transactional_emails_manager') {
		$message = "Gift for peak check scheduled orders hook not setup as of right now.";
		if ( $timestamp = wp_next_scheduled( 'process_transactional_emails_abandoned_cart_hook' ) ) {
			$datetime_utc = new DateTime();
			$datetime_utc->setTimestamp( $timestamp );
			$datetime_utc->setTimezone( new DateTimeZone( 'UTC' ) );
			$datetime_est = clone $datetime_utc;
			$datetime_est->setTimezone( new DateTimeZone( 'America/New_York' ) );
			$date_string = $datetime_est->format( 'Y-m-d h:i:s A' );
			$message = 'The next scheduled check for abandoned cart is to run @ ' . $date_string . ' EST.';
		}
    	echo "<div class='notice notice-warning transactional-emails'><p>$message</p></div>";
    }
}

function transactional_emails_manager_file_upload() {
	if(!$_POST || !isset($_POST['marketing_page_transactional_emails_manager'])) {
		return;
	}

	$selected_option = isset($_POST['transactional_emails_manager_dropdown']) ? sanitize_text_field($_POST['transactional_emails_manager_dropdown']) : '';

	$omeda_track_id_input = isset($_POST['omeda_track_id_input']) ? sanitize_text_field($_POST['omeda_track_id_input']) : '';

	$transactional_emails_manager_subject = isset($_POST['transactional_emails_manager_subject']) ? sanitize_text_field($_POST['transactional_emails_manager_subject']) : '';

	if(empty($selected_option)) {
		$message = 'A template must be selected.';
		$class = 'notice notice-error is-dismissible transactional-emails';
		echo '<div class="' . $class . '"><p>' . $message . '</p></div>';
		return;
	}

	if(empty($transactional_emails_manager_subject)) {
		$message = 'A email subject must be entered.';
		$class = 'notice notice-error is-dismissible transactional-emails';
		echo '<div class="' . $class . '"><p>' . $message . '</p></div>';
		return;
	}

    if (isset($_POST['upload_html']) && !empty($_FILES['html_file']['name']) && isset($_FILES['html_file'])) {

        $file = $_FILES['html_file'];

        $upload_dir = wp_upload_dir();
        $file_name = sanitize_file_name($file['name']);
        $fileExtension = pathinfo($file_name, PATHINFO_EXTENSION);
        $randomFileName = uniqid() . '_' . time();
        $file_path = $upload_dir['path'] . '/' . $randomFileName . '.' . $fileExtension;
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $sanitized_omeda_track_id = !empty($_POST['omeda_track_id_input']) ? sanitize_text_field($_POST['omeda_track_id_input']) : false;
            $transactional_emails_manager_subject = !empty($_POST['transactional_emails_manager_subject']) ? sanitize_text_field($_POST['transactional_emails_manager_subject']) : false;

            transactional_emails_manager_update_options($selected_option, $file_path, $sanitized_omeda_track_id, $transactional_emails_manager_subject);

             $message = 'Template <strong>' . str_replace("_", " ", ucwords($selected_option, "_")) . '</strong> has been updated successfully.';
			 $class = 'notice notice-success is-dismissible transactional-emails';
			 echo '<div class="' . $class . '"><p>' . $message . '</p></div>';
			 return;
        } else {
           	 $message = 'There was an error in updating the <strong>' . str_replace("_", " ", ucwords($selected_option, "_")) . '</strong> template.';
			 $class = 'notice notice-error is-dismissible transactional-emails';
			 echo '<div class="' . $class . '"><p>' . $message . '</p></div>';
			 return;
        }
    } else if(isset($_POST['omeda_track_id_input'])) {
    	$sanitized_omeda_track_id = sanitize_text_field($_POST['omeda_track_id_input']);
    	transactional_emails_manager_update_options($selected_option, null, $sanitized_omeda_track_id, true);

    	$message = 'Template <strong>' . str_replace("_", " ", ucwords($selected_option, "_")) . '</strong> has been updated successfully.';
		$class = 'notice notice-success is-dismissible transactional-emails';
		echo '<div class="' . $class . '"><p>' . $message . '</p></div>';
		return;
    }

	if(empty($omeda_track_id_input)) {
		$message = 'Please choose either an <b>HTML file</b> or update the <b>Omeda track ID</b> to modify the template.';
		$class = 'notice notice-error is-dismissible transactional-emails';
		echo '<div class="' . $class . '"><p>' . $message . '</p></div>';
		return;
	}
}

function transactional_emails_manager_wp_enqueue() {
	if ( isset( $_GET['page'] ) && $_GET['page'] === 'transactional_emails_manager' ) {
		wp_enqueue_style('transactional-emails-admin-style', plugin_dir_url(__FILE__) . 'assets/css/transactional-emails-admin.css?' . time());
    	wp_enqueue_script('transactional-emails-admin-script', plugin_dir_url(__FILE__) . 'assets/js/transactional-emails-admin.js?' . time());
	}
}
