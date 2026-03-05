<?php

	/*
		only effects articles under metered under sdmem
		have sdmembership, but haven't signed up for digital content
	*/

	add_action('add_meta_boxes', 'general_ledger_field_meta_box');

	function general_ledger_field_meta_box() {
	    add_meta_box(
	        'general_ledger_field_meta_box',
	        'General Ledger Field',
	        'general_ledger_field_meta_box_callback',
	        'product',
	        'side',
	        'high'
	    );
	}

	function general_ledger_field_meta_box_callback($post) {
		if (!(get_post_type($post->ID) === 'product')) {
			return;
		}

		wp_enqueue_style('general-ledger-field-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css?' . time());
	    wp_enqueue_script('general-ledger-field-admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js?' . time());

		$product_id = $post->ID;
		$attribute_name = 'GL_Code';

		$product = wc_get_product($product_id);

		echo '<div class="tooltip-container">';

		if (!$product) {
			echo '<input type="text" id="general-ledger-field" style="width:100%;margin-top:10px;" name="general_ledger_field" value="" class="tooltip-input" placeholder="General ledger code" />';
			echo '<span class="tooltip-text">Be sure the general ledger code is formatted like such <i>19*5120*0001*10010*00000</i></span>';
			echo '<p style="margin-top: 5px;">Please enter a general ledger code.</p>';
			echo '</div>';
			return;
		}

		$productAttributes = get_post_meta($product_id, '_product_attributes', true);

		$attribute_value = isset($productAttributes[$attribute_name]['value']) ? $productAttributes[$attribute_name]['value'] : (isset($productAttributes[strtolower($attribute_name)]['value']) ? $productAttributes[strtolower($attribute_name)]['value'] : '');
	  
		echo '<input type="text" id="general-ledger-field" style="width:100%;margin-top:10px;" name="general_ledger_field" class="tooltip-input" value="' . $attribute_value . '" placeholder="General ledger code" />';
		echo '<span class="tooltip-text">Be sure the general ledger code is formatted like such <i>19*5120*0001*10010*00000</i></span>';
		echo '<p style="margin-top: 5px;">Please enter a general ledger code.</p>';
		echo '</div>';
	}

	add_action('transition_post_status', 'send_new_post', 10, 3);

	function send_new_post($new_status, $old_status, $post) {
		general_ledger_field_check($_POST, $post->ID);
	}

	add_action('wp_insert_post', 'run_after_post_updated', 10, 3);

	function run_after_post_updated($post_ID, $post = null, $update = true) {
	    if ($update && is_admin()) {
	    	general_ledger_field_check($_POST, $post_ID);
	    }
	}

	add_action('save_post', 'op_save_offers_meta', 10, 2);

	function op_save_offers_meta($post_id, $post) {
	    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	    
	    if (!current_user_can('edit_post', $post_id)) return;

	    if ($post->post_type === 'product') {
	    	general_ledger_field_check($_POST, $post_id);
	    }
	}

	function general_ledger_field_check($post_data, $post_id) {
		if(!$post_data || $post_data['action'] == 'woocommerce_do_ajax_product_import') {
			return;
		}

		$post_type = get_post_type($post_id);

		if ($post_type === 'product') {
			$general_ledger_field = $post_data['general_ledger_field'];

			if (isset($general_ledger_field)) {
			    $general_ledger_value = sanitize_text_field($general_ledger_field);

			    $pattern = '/^(\d{2}\*\d{4}\*\d{4}\*\d{5}\*\d{5})$/';

				if (!preg_match($pattern, $general_ledger_value)) {
				    wp_die('Please ensure that you enter the general ledger field in the correct format, like this: <i>19*5120*0001*10010*00000</i>', 'Error', array('response' => 500));
				}

				$attribute_name = 'GL_Code';

				$productAttributes = get_post_meta($post_id, '_product_attributes', true);

				if (empty($productAttributes) || !is_array($productAttributes)) {
				    $productAttributes = array();
				}

				if (isset($productAttributes[$attribute_name])) {
				    $productAttributes[$attribute_name]['value'] = $general_ledger_value;
				    $productAttributes[$attribute_name]['is_visible'] = 0;
				} else {
				    $productAttributes[$attribute_name] = array(
				        'name' => $attribute_name,
				        'value' => $general_ledger_value,
				        'position' => 0,
				        'is_visible' => 0,
				        'is_variation' => 0,
				        'is_taxonomy' => 0,
				    );
				}

				update_post_meta($post_id, '_product_attributes', $productAttributes);
			} else {
				wp_die('Please make sure to enter the general ledger field correctly.', 'Error', array('response' => 500));
			}
		}
	}

	add_filter( 'manage_edit-product_columns', 'general_ledger_field_product_column', 10);

	function general_ledger_field_product_column($columns) {
	    $new_columns = [];
	    foreach( $columns as $key => $column ){
	        $new_columns[$key] = $columns[$key];
	        if( $key == 'price' ) {
	             $new_columns['gl-code'] = __( 'GL Code','woocommerce');
	        }
	    }
	    return $new_columns;
	}

	add_action( 'manage_product_posts_custom_column', 'general_ledger_field_product_column_content', 10, 2 );

	function general_ledger_field_product_column_content( $column, $product_id ) {
	    global $post;

	    if( $column =='gl-code' ) {
			$attribute_name = 'GL_Code';
			$product = wc_get_product($product_id);
			$productAttributes = get_post_meta($product_id, '_product_attributes', true);
			$attribute_value = isset($productAttributes[$attribute_name]['value']) ? $productAttributes[$attribute_name]['value'] : (isset($productAttributes[strtolower($attribute_name)]['value']) ? $productAttributes[strtolower($attribute_name)]['value'] : false);
			$edit_url = get_edit_post_link($product_id);
			$display_value = $attribute_value ? esc_html($attribute_value) : 'Unspecified';

			printf('<a href="%s">%s</a>', esc_url($edit_url), $display_value);
	    }
	}

	function general_ledger_field_admin_product_styles() {
	    global $pagenow;

	    if ($pagenow == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'product') {
	        wp_enqueue_style('general-ledger-field-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css?' . time());
	    }
	}

	add_action('admin_enqueue_scripts', 'general_ledger_field_admin_product_styles');

