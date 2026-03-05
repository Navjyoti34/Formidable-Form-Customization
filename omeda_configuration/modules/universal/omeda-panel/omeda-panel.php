<?php

	if (! defined('ABSPATH')) exit;

	include($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');

	function ommeda_panel() {
		wp_enqueue_style('omeda-panel-admin', plugin_dir_url( __FILE__ ) . 'assets/css/omeda-panel-admin.css?' . time(), false );
		wp_enqueue_script('omeda-panel-admin', plugin_dir_url( __FILE__ ) . 'assets/js/omeda-panel-admin.js?' . time(), false );

		$wp_list_table_path = ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

		if (file_exists($wp_list_table_path)) {
			if (!class_exists('WP_List_Table')) {
	    		require_once $wp_list_table_path;
	    	}

	    	if (class_exists('WP_List_Table')) {
				class Custom_List_Table extends WP_List_Table {
				    function __construct() {
				        parent::__construct(array(
				            'singular' => 'custom_item',
				            'plural'   => 'custom_items',
				            'ajax'     => false,
				        ));
				    }

				    function column_default($item, $column_name) {
				        return $item[$column_name];
				    }

				    function get_columns() {
				        $columns = array(
				            'order_id' => 'Order ID',
							'date' => 'Order Date',
				            'category_name' => 'Category Name',
				            'category_id' => 'Category ID',
				            'category_match' => 'Category Match',
				            'sent_receive_message' => 'Omeda Message',
				            'force_process' => 'Force Process'
				        );

				        return $columns;
				    }

				    function prepare_items() {
					    $data = $this->get_lead_data();

					    // Sort the data by the 'date' column in descending order
					    usort($data, function($a, $b) {
					        return strtotime($b['date']) - strtotime($a['date']);
					    });

					    $columns = $this->get_columns();
					    $hidden = array();
					    $sortable = array(
					        'date' => array('date', false), // Make the 'date' column sortable
					    );

					    $this->_column_headers = array($columns, $hidden, $sortable);

					    $per_page = 100;
					    $current_page = $this->get_pagenum();
					    $total_items = count($data);

					    $this->set_pagination_args(array(
					        'total_items' => $total_items,
					        'per_page'    => $per_page,
					    ));

					    $data_slice = array_slice($data, (($current_page - 1) * $per_page), $per_page);

					    $this->items = $data_slice;
					}

					function get_lead_data() {
					    global $wpdb;

					    $exclude_skus = [1117827, 346269, 1135609];
					    $exclude_orders = [];

					    $sql = "
					        SELECT order_id, sent_receive_message, sku, date
					        FROM omeda_queue
					        WHERE order_id NOT IN (
					            SELECT DISTINCT order_id
					            FROM omeda_queue
					            WHERE sent_receive_message LIKE '%in a category that is not acceptable%'
					        )
					        AND order_id NOT IN (
					            SELECT DISTINCT order_id
					            FROM omeda_queue
					            WHERE processor_message LIKE '%successfully completed and processed%'
					        )
					        GROUP BY order_id
					        HAVING SUM(CASE WHEN processor_status = 'success' THEN 1 ELSE 0 END) = 0
					        ORDER BY sent_receive_message DESC
					    ";

					    $data = $wpdb->get_results($sql, ARRAY_A);

					    $complete_data = array();

					    foreach ($data as $item) {
					    	$order_id = $item['order_id'];

					    	$order = wc_get_order($order_id);

							if (!$order) {
								continue;
							}

					        if (in_array($item['sku'], $exclude_skus)) {
					            $exclude_orders[] = $order_id;
					            $exclude_orders = array_unique($exclude_orders);
					            continue;
					        }

					        if (in_array($order_id, $exclude_orders)) {
					            continue;
					        }

					        $product_categories = wp_get_post_terms($item['sku'], 'product_cat');

					        $item['category_id'] = $item['category_name'] = $item['category_match'] = '';

							if (!empty($product_categories)) {
								$last_category = end($product_categories);

							    foreach ($product_categories as $product_category) {
								    $category_name = $product_category->name;
								    $category_id = $product_category->term_id;

								    $item['category_name'] .= $category_name;
								    $item['category_id'] .= $category_id;

								    if ($product_category !== $last_category) {
								        $item['category_name'] .= ', ';
								        $item['category_id'] .= ', ';
								    }
								}

								$trigger_categories = array('28368','28378', '28344', '27352');

								$valuesToCheck = explode(', ', $item['category_id']);

								foreach ($valuesToCheck as $value) {
								    if (in_array($value, $trigger_categories)) {
								        $item['category_match'] = $value;
								        break;
								    }
								}
							} else {
								$item['category_match'] = 'N/A';
							}

							if(!$item['category_match']) {
								continue;
							}

					        $item['sent_receive_message'] = stripslashes($item['sent_receive_message']);
					        $item['force_process'] = '<span id="force-omeda-process" class="o-button o-button--cta" data-order="' . $order_id . '"><span>Force Process</span></span>';
					        $item['order_id'] = '<a href="/wp-admin/post.php?post=' . $order_id . '&action=edit">' . $order_id . '</a>';
					        
					        $complete_data[] = $item;
					    }

					    return $complete_data;
					}

				}
	    	}
		}

		if (class_exists('Custom_List_Table')) {
			$list_table = new Custom_List_Table();
		    $list_table->prepare_items();
		    ?>
		    <div class="wrap">
	            <h2>Omeda Panel</h2>
	            <?php
		    		$list_table->display();
		    	?>
		    </div>
		    <?php
		} else {
			wp_die('The WP_List_Table class could not be found.');
		}
	}

	function add_custom_list_table_to_woocommerce_menu() {
	    add_submenu_page(
	        'woocommerce',
	        'Omeda Panel',
	        'Omeda Panel',
	        'manage_options',
	        'omeda-panel',
	        'ommeda_panel'
	    );
	}

	// Use a high priority to ensure it appears at the end of the WooCommerce menu
	add_action('admin_menu', 'add_custom_list_table_to_woocommerce_menu', 999);