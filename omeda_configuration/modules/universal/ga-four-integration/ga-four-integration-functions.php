<?php

if (! defined('ABSPATH')) exit;

// Callback function for REST API request
function ga_four_api_request_callback(WP_REST_Request $request) {
    $layer_request = sanitize_text_field($request->get_param('layer_request'));
    $output = [
        'error' => true,
        'msg' => 'Parameter issue.',
    ];

    if (isset($layer_request) && !empty($layer_request)) {
        if (in_array($layer_request, ga_four_avail_data_layers('confirmed_avail_data_layers'))) {
            if ($layer_request === 'add_to_cart') {
                $quantity = sanitize_text_field($request->get_param('product_quantity'));
                $product_id = sanitize_text_field($request->get_param('product'));
                $ga4_grab_json_payload = ga4_track_add_to_cart($product_id, $quantity, true);
                if ($ga4_grab_json_payload) {
                    return $ga4_grab_json_payload;
                }
            }

            if ($layer_request === 'remove_from_cart') {
                $quantity = sanitize_text_field($request->get_param('product_quantity'));
                $product_id = sanitize_text_field($request->get_param('product'));
                $ga4_grab_json_payload = ga4_track_remove_from_cart($product_id, $quantity, true);
                if ($ga4_grab_json_payload) {
                    return $ga4_grab_json_payload;
                }
            }

            if ($layer_request === 'add_payment_info') {
                $method = sanitize_text_field($request->get_param('payment_method'));
                $ga4_grab_json_payload = ga_four_add_payment_information($method, true);
                if ($ga4_grab_json_payload) {
                    return $ga4_grab_json_payload;
                }
            }

            if ($layer_request === 'add_shipping_info') {
                $ga4_grab_json_payload = ga_four_add_shipping_information(true);
                if ($ga4_grab_json_payload) {
                    return $ga4_grab_json_payload;
                }
            }

            $output = ['msg' => 'Request not processed.'];
        } else {
            $output['msg'] = 'Data layer not active.';
        }
    }

    return $output;
}


function ga_four_integration_tag($comment, $script) {
    echo "<!-- GA4 $comment -->";
    echo "\r\n";
    echo "\r\n";
    echo "<script>";
    echo $script;
    echo "</script>";
    echo "\r\n";
    echo "\r\n";
    echo "<!-- GA4 $comment \ -->";
}

function ga_four_view_item_list() {
    if (!(is_product_category())) {
        return;
    }

    if (!(in_array('view_item_list', ga_four_avail_data_layers('confirmed_avail_data_layers')))) {
        add_action('wp_footer', function() {
            ga_four_integration_tag('Integration Console Log', "console.log('[GA4] Data layer not active.')");
        });
        return;
    }

    $term = get_queried_object();
    $term_id = $term->term_id;
    $term_name = $term->name;
    $term_slug = $term->slug;

    $current_page = max(1, get_query_var('paged'));

    $total_pages = $GLOBALS['wp_query']->max_num_pages;

    $products_per_page = wc_get_loop_prop('per_page');

    $offset = ($current_page - 1) * $products_per_page;

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => $products_per_page,
        'offset' => $offset,
        'tax_query' => array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term_id,
                'operator' => 'IN',
            ),
        ),
    );

    $products = new WP_Query($args);
    $position = 1;

    $items = [];

    if ($products->have_posts()) {
        while ($products->have_posts()) {
            $products->the_post();
            global $product;

            $item = [
                'item_id' => $product->get_id(),
                'item_name' => $product->get_name(),
                'price' => $product->get_price(),
                'index' => $position,
                'quantity' => 1
            ];

            $terms_string = implode(', ', wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']));

            $terms_array = array_unique(explode(', ', $terms_string));

            foreach ($terms_array as $key => $value) {
                $key += 1;
                if ($key === 1) {
                    $key = '';
                }
                $item_category_key = 'item_category' . $key;
                $item[$item_category_key] = $value;
            }

            $items[] = $item;

            $position++;
        }
    }

    $page_items = [
        "item_list_id" => $term_id,
        "item_list_name" => $term_name,
        "item_list_slug" => $term_slug,
        "page_location" => get_permalink(),
        "items" => $items
    ];

    return ga4_send_event('view_item_list', $page_items);    
}

function ga_four_is_wc_order_received_page() {
    return is_wc_endpoint_url('order-received');
}

function ga_four_get_wc_order_id_from_received_page() {
    if (ga_four_is_wc_order_received_page()) {
        $order_id = isset($_GET['key']) ? ($_GET['key']) : 0;
        return $order_id;
    }

    return 0;
}

function ga_four_purchase() {
    if (!(ga_four_is_wc_order_received_page())) {
        return;
    }

    if (!(in_array('purchase', ga_four_avail_data_layers('confirmed_avail_data_layers')))) {
        add_action('wp_head', function() {
            ga_four_integration_tag('Integration Console Log', "console.log('[GA4] Data layer not active.')");
        });
        return;
    }

    
    $order_id = wc_get_order_id_by_order_key(ga_four_get_wc_order_id_from_received_page());
    $order = wc_get_order($order_id);
    $order_total = $order->get_total();
    $currency = $order->get_currency();
    $tax_total = $order->get_total_tax();
    $applied_coupons = $order->get_coupon_codes();
    $coupon_used = !empty($applied_coupons);

    $items = [];

    $bundled_products = array();

    foreach ($order->get_items() as $item_id => $order_item) {
        $product = $order_item->get_product();
        $position = array_search($item_id, array_keys($order->get_items()));
        $cart_item = $order_item->get_data();
        $_product = apply_filters('woocommerce_cart_item_product', $order_item->get_product(), $cart_item, $order_item->get_id());
        $product_id = $product->get_id();

        if($_product->is_type('bundle')) {
            foreach ($_product->get_bundled_items()  as $bundled_item_id => $bundled_item ) {
                array_push($bundled_products, $bundled_item->get_product_id());
            }
        }

        if (in_array($product_id, $bundled_products)) {
            continue;
        }

        $formatted_item = [
            'item_id' => $product_id,
            'item_name' => $product->get_name(),
            'price' => $product->get_price(),
            'index' => $position,
            'quantity' => $order_item->get_quantity()
        ];

        $terms_string = implode(', ', wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']));

        $terms_array = array_unique(explode(', ', $terms_string));

        foreach ($terms_array as $key => $value) {
            $key += 1;
            if ($key === 1) {
                $key = '';
            }
            $item_category_key = 'item_category' . $key;
            $formatted_item[$item_category_key] = $value;
        }

        $items[] = $formatted_item;
    }

    $order_data = [
        "transaction_id" => $order_id,
        "value" => $order_total,
        "tax" => $tax_total,
        "currency" => $currency, 
        "items" => $items
    ];

    if($coupon_used) {
        $coupons_string = implode(', ', $applied_coupons);
        $coupons_array = array_unique(explode(', ', $coupons_string));

        foreach ($coupons_array as $key => $value) {
            $key += 1;
            if ($key === 1) {
                $key = '';
            }
            $coupon_category_key = 'coupon' . $key;
            $order_data[$coupon_category_key] = $value;
        }
    }

    return ga4_send_event('purchase', $order_data);
}

function ga_four_remove_from_cart($cart_item_key, $cart) {
    $removed_item = $cart->removed_cart_contents[$cart_item_key];

    if (isset($removed_item['product_id'])) {
        $product_id = $removed_item['product_id'];
        $quantity_removed = $removed_item['quantity'];

        ga4_track_remove_from_cart($product_id, $quantity_removed);
    }
}

function ga4_track_remove_from_cart($cart_item_key, $quantity, $request = False) {
    $product = wc_get_product($cart_item_key);
    
    if (!($product && is_a($product, 'WC_Product'))) {
        return False;
    }

    $items = [];

    if ($product) {
        $item = [
            'item_id' => $product->get_id(),
            'item_name' => $product->get_name(),
            'price' => $product->get_price()
        ];

        $terms_string = implode(', ', wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']));

        $terms_array = array_unique(explode(', ', $terms_string));

        foreach ($terms_array as $key => $value) {
            $key += 1;
            if ($key === 1) {
                $key = '';
            }
            $item_category_key = 'item_category' . $key;
            $item[$item_category_key] = $value;
        }

        $items[] = $item;

        $product_data = [
           "currency" => get_woocommerce_currency(), 
           "page_location" => wp_get_referer(),
           "items" => $items
        ];

        return ga4_send_event('remove_from_cart', $product_data, $request);
    }
}

function ga_four_viewing_item() {
    if(!is_product()) {
        return;
    }

    global $post;

    $product = wc_get_product($post->ID);

    if (!($product && is_a($product, 'WC_Product'))) {
        return;
    }

    if (!(in_array('view_item', ga_four_avail_data_layers('confirmed_avail_data_layers')))) {
        add_action('wp_head', function() {
            ga_four_integration_tag('Integration Console Log', "console.log('[GA4] Data layer not active.')");
        });
        return;
    }

    $items = [];

    $item = [
        'item_id' => $product->get_id(),
        'item_name' => $product->get_name(),
        'price' => $product->get_price(),
        'index' => 1,
        'quantity' => 1
    ];

    $terms_string = implode(', ', wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']));

    $terms_array = array_unique(explode(', ', $terms_string));

    foreach ($terms_array as $key => $value) {
        $key += 1;
        if ($key === 1) {
            $key = '';
        }
        $item_category_key = 'item_category' . $key;
        $item[$item_category_key] = $value;
    }

    $items[] = $item;

    $item_data = [
       "currency" => get_woocommerce_currency(), 
       "page_location" => get_permalink(),
       "items" => $items
    ];

    return ga4_send_event('view_item', $item_data);
}

function ga_four_viewing_cart() {
    if (!is_cart()) {
        return;
    }

    if (!(in_array('view_cart', ga_four_avail_data_layers('confirmed_avail_data_layers')))) {
        add_action('wp_head', function() {
            ga_four_integration_tag('Integration Console Log', "console.log('[GA4] Data layer not active.')");
        });
        return;
    }

    $cart_contents = WC()->cart->get_cart();

    if (!empty($cart_contents)) {
        $items = [];

        foreach ($cart_contents as $cart_item_key => $cart_item) {
            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

            $item = [
                'item_id' => $cart_item['product_id'],
                'item_name' => $_product->get_name(),
                'price' => $_product->get_price(),
                'quantity' => $cart_item['quantity']
            ];

            $terms_string = implode(', ', wp_get_post_terms($cart_item['product_id'], 'product_cat', ['fields' => 'names']));

            $terms_array = array_unique(explode(', ', $terms_string));

            foreach ($terms_array as $key => $value) {
                $key += 1;
                if ($key === 1) {
                    $key = '';
                }
                $item_category_key = 'item_category' . $key;
                $item[$item_category_key] = $value;
            }

            $items[] = $item;
        }

        $cart_data = [
           "currency" => get_woocommerce_currency(), 
           "value" => WC()->cart->cart_contents_total,
           "items" => $items
        ];

        return ga4_send_event('view_cart', $cart_data);
    }
}

function ga_four_add_shipping_information($request) {
    $session_id = null;
    $values = null;

    foreach ($_COOKIE as $key => $value) {
        if (stripos($key, 'wp_woocommerce_session_') !== false) {
            $values = explode('||', $value);
        }
    }

    $cart_data = [];

    if ($values !== null) {
        $session_id = $values[0];
        $session = new WC_Session_Handler();
        $session_data = $session->get_session($session_id);

        if (isset($session_data['cart'])) {
            $cart_items = unserialize($session_data['cart']);
            $cart_total = [];

            foreach ($cart_items as $cart_item) {
                $product_id = $cart_item['product_id'];
                $quantity = $cart_item['quantity'];
                $line_total = $cart_item['line_total'];

                $product = wc_get_product($product_id);

                if (!($product && is_a($product, 'WC_Product'))) {
                    continue;
                }

                $cart_total[] = $line_total;

                $item = [
                    'item_id' => $product->get_id(),
                    'item_name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'quantity' => $quantity
                ];

                $terms_string = implode(', ', wp_get_post_terms($cart_item['product_id'], 'product_cat', ['fields' => 'names']));

                $terms_array = array_unique(explode(', ', $terms_string));

                foreach ($terms_array as $key => $value) {
                    $key += 1;
                    if ($key === 1) {
                        $key = '';
                    }
                    $item_category_key = 'item_category' . $key;
                    $item[$item_category_key] = $value;
                }

                $cart_data['items'][] = $item;
            }

            $cart_data['currency'] = get_woocommerce_currency();
            $cart_data['value'] = array_sum($cart_total);
            $cart_data['shipping_tier'] = 'Ground';
        }
    }

    return ga4_send_event('add_shipping_info', $cart_data, $request);
}

function ga_four_add_payment_information($method, $request) {
    $session_id = null;
    $values = null;

    foreach ($_COOKIE as $key => $value) {
        if (stripos($key, 'wp_woocommerce_session_') !== false) {
            $values = explode('||', $value);
        }
    }

    $cart_data = [];

    if ($values !== null) {
        $session_id = $values[0];
        $session = new WC_Session_Handler();
        $session_data = $session->get_session($session_id);

        if (isset($session_data['cart'])) {
            $cart_items = unserialize($session_data['cart']);
            $cart_total = [];

            foreach ($cart_items as $cart_item) {
                $product_id = $cart_item['product_id'];
                $quantity = $cart_item['quantity'];
                $line_total = $cart_item['line_total'];

                $product = wc_get_product($product_id);

                if (!($product && is_a($product, 'WC_Product'))) {
                    continue;
                }

                $cart_total[] = $line_total;

                $item = [
                    'item_id' => $product->get_id(),
                    'item_name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'quantity' => $quantity
                ];

                $terms_string = implode(', ', wp_get_post_terms($cart_item['product_id'], 'product_cat', ['fields' => 'names']));

                $terms_array = array_unique(explode(', ', $terms_string));

                foreach ($terms_array as $key => $value) {
                    $key += 1;
                    if ($key === 1) {
                        $key = '';
                    }
                    $item_category_key = 'item_category' . $key;
                    $item[$item_category_key] = $value;
                }

                $cart_data['items'][] = $item;
            }

            $cart_data['currency'] = get_woocommerce_currency();
            $cart_data['value'] = array_sum($cart_total);
            $cart_data['payment_type'] = $method;
        }
    }

    return ga4_send_event('add_payment_info', $cart_data, $request);
}

function ga_four_begin_checkout() {
    if (!is_checkout()) {
        return;
    }

    if (!(in_array('begin_checkout', ga_four_avail_data_layers('confirmed_avail_data_layers')))) {
        add_action('wp_head', function() {
            ga_four_integration_tag('Integration Console Log', "console.log('[GA4] Data layer not active.')");
        });
        return;
    }

    $cart_contents = WC()->cart->get_cart();

    if (!empty($cart_contents)) {
        $items = [];

        foreach ($cart_contents as $cart_item_key => $cart_item) {
            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

            $item = [
                'item_id' => $cart_item['product_id'],
                'item_name' => $_product->get_name(),
                'price' => $_product->get_price(),
                'quantity' => $cart_item['quantity']
            ];

            $terms_string = implode(', ', wp_get_post_terms($cart_item['product_id'], 'product_cat', ['fields' => 'names']));

            $terms_array = array_unique(explode(', ', $terms_string));

            foreach ($terms_array as $key => $value) {
                $key += 1;
                if ($key === 1) {
                    $key = '';
                }
                $item_category_key = 'item_category' . $key;
                $item[$item_category_key] = $value;
            }

            $items[] = $item;
        }

        $cart_data = [
           "currency" => get_woocommerce_currency(), 
           "value" => WC()->cart->cart_contents_total,
           "items" => $items
        ];

        return ga4_send_event('begin_checkout', $cart_data);
    }
}

function ga4_send_event($event_name, $event_params = [], $request = False) {
    $event_data = [
        'event' => $event_name,
        'ecommerce' => $event_params,
    ];

    if($request) {
        return json_encode($event_data);
    }

    if (!(isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wc-ajax') !== false && strpos($_SERVER['REQUEST_URI'], $event_name) !== false)) {
        add_action('wp_footer', function() use ($event_data) {
            ga_four_integration_tag('Integration Data Layer Tag', "dataLayer.push(" . json_encode($event_data) . ");");
        });
    }
}

function ga4_track_add_to_cart($cart_item_key, $quantity, $request = False) {
    $product = wc_get_product($cart_item_key);
    if (!($product && is_a($product, 'WC_Product'))) {
        return False;
    }

    $items = [];

    if ($product) {
        $item = [
            'item_id' => $product->get_id(),
            'item_name' => $product->get_name(),
            'price' => $product->get_price(),
            'quantity' => $quantity
        ];

        $terms_string = implode(', ', wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']));

        $terms_array = array_unique(explode(', ', $terms_string));

        foreach ($terms_array as $key => $value) {
            $key += 1;
            if ($key === 1) {
                $key = '';
            }
            $item_category_key = 'item_category' . $key;
            $item[$item_category_key] = $value;
        }

        $items[] = $item;

        $product_data = [
           "currency" => get_woocommerce_currency(), 
           "page_location" => wp_get_referer(),
           "items" => $items    
        ];

        return ga4_send_event('add_to_cart', $product_data, $request);
    }
}

function enqueue_admin_scripts() {
    wp_enqueue_script('wp-notices');
}

function ga4_add_to_cart_action($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    $product_id = WC()->cart->get_cart()[$cart_item_key]['product_id'];

    ga4_track_add_to_cart($product_id, $quantity);
}

add_action('woocommerce_add_to_cart', 'ga4_add_to_cart_action', 10, 6);

function custom_script_editor_menu() {
    add_menu_page(
        'GA4 Integration',
        'GA4 Integration',
        'manage_options',
        'ga_four_integration',
        'ga_four_integration_page'
    );
}

function reverseSanitization($encodedScript) {
    $decodedScript = str_replace('&#039;', "'", html_entity_decode($encodedScript, ENT_QUOTES, 'UTF-8'));
    return $decodedScript;
}

function inject_ga_four_script_into_header() {
    $encodedScript = get_option('ga_integration_script');

    echo "\r\n";

    if ($encodedScript) {
        $reversedScript = reverseSanitization(stripslashes(base64_decode($encodedScript)));
        if ($reversedScript) {
            echo '<!-- GA4 Integration Tag -->';
            echo "\r\n";
            echo "\r\n";
            echo $reversedScript;
            echo "\r\n";
            echo "\r\n";
            echo '<!-- GA4 Integration Tag / -->';
            echo "\r\n";
        } else {
            echo '<!-- Error: Unable to reverse sanitization or decode script -->';
        }
    } else {
        echo '<!-- Error: GA integration script option not found or empty -->';
    }
    echo "\r\n";
}

function sanitize_custom_script($input) {
    return wp_kses($input, array('script' => array()));
}

function formatAndColorizeJson($json) {
    $formattedJson = json_encode(json_decode($json, true), JSON_PRETTY_PRINT);
    return '<pre><code class="json">' . htmlspecialchars($formattedJson) . '</code></pre>';
}

function ga_four_integration_api($data_layer = null) {
    if($data_layer) {
        $option_name = 'midtc_ga4_integration_' . $data_layer;
        $option_value = get_option($option_name);
        if ($option_value !== false) {
            return get_option($option_name);
        } else {
            return 'off';
        }
    }

    if (isset($_GET['ga4'], $_GET['integration'], $_GET['enable'])) {
        header('Content-Type: application/json');
        $output = array('error' => false);

        $ga4 = filter_var($_GET['ga4'], FILTER_VALIDATE_BOOLEAN);
        $integration = sanitize_key($_GET['integration']);
        $enable = filter_var($_GET['enable'], FILTER_VALIDATE_BOOLEAN);

        if (!$ga4 || !in_array($enable, [true, false], true)) {
            $output['error'] = true;
            $output['msg'] = 'Invalid parameters';
            echo json_encode($output);
            exit;
        }

        if(!(in_array($integration, ga_four_avail_data_layers('all_avail_layers')))) {
            $output['error'] = true;
            $output['msg'] = 'Not an available data layer.';
            echo json_encode($output);
            exit;
        }

        $enable = $enable ? 'on' : 'off';

        $option_name = 'midtc_ga4_integration_' . $integration;

        if (update_option($option_name, $enable) !== false) {
            $option_value = get_option($option_name);

            if ($option_value !== false) {
                $output['msg'] = "Data layer $integration turned $enable successfully.";
                $output['data_layer'] = $integration;
                $output['value'] = $option_value;
            } else {
                $output['error'] = true;
                $output['msg'] = 'Error getting data layer setting.';
            }
        } else {
            if(get_option($option_name) == $enable) {
                $output['msg'] = "Data layer $integration turned $enable successfully.";
                $output['data_layer'] = $integration;
                $output['value'] = get_option($option_name);
                echo json_encode($output);
                exit;
            }

            $output['error'] = true;
            $output['msg'] = 'Error updating data layer setting.';
        }

        echo json_encode($output);
        exit;
    }

    return;
}

function ga_four_avail_data_layers($return_type) {
    $avail_layers = array('add_to_cart','add_payment_info','view_item_list','begin_checkout','purchase','remove_from_cart','view_cart','view_item','add_shipping_info');
    
    if($return_type == 'all_avail_layers') {
        return $avail_layers;
    }
        
    $confirmed_avail_layers = [];

    foreach ($avail_layers as $data_layer) {
        $option_name = 'midtc_ga4_integration_' . $data_layer;
        $option_value = get_option($option_name);

        if ($option_value !== false) {
            if($option_value == 'on') {
                $confirmed_avail_layers[] = $data_layer;
            }
        }
    }

    return $confirmed_avail_layers;
}

function ga_four_integration_page($return_avail_layers = false) {
    $event_data = array(
        'add_to_cart' => array(
            "currency" => "USD",
            "page_location" => "https://domain.com/product-category/digital-magazines/",
            "items" => array(
                array(
                    "item_id" => "SKU_12345",
                    "item_name" => "Stan and Friends Tee",
                    "item_category" => "Apparel",
                    "item_category2" => "Adult",
                    "item_category3" => "Shirts",
                    "item_category4" => "Crew",
                    "item_category5" => "Short sleeve",
                    "price" => 9.99,
                    "quantity" => 1
                )
            )
        ),
        'add_payment_info' => array(
            "currency" => "USD",
            "value" => 7.77,
            "payment_type" => "Credit Card",
            "items" => array(
                array(
                    "item_id" => "SKU_12345",
                    "item_name" => "Stan and Friends Tee",
                    "item_category" => "Apparel",
                    "item_category2" => "Adult",
                    "item_category3" => "Shirts",
                    "item_category4" => "Crew",
                    "item_category5" => "Short sleeve",
                    "price" => 9.99,
                    "quantity" => 1
                )
            )
        ),
        'view_item_list' => array(
            "item_list_id" => "related_products",
            "item_list_name" => "Related products",
            "page_location" => "https://domain.com/product-category/digital-magazines/",
            "items" => array(
                array(
                    "item_id" => "SKU_12345",
                    "item_name" => "Stan and Friends Tee",
                    "item_category" => "Apparel",
                    "item_category2" => "Adult",
                    "item_category3" => "Shirts",
                    "item_category4" => "Crew",
                    "item_category5" => "Short sleeve",
                    "price" => 9.99,
                    "quantity" => 1
                )
            )
        ),
        'begin_checkout' => array(
            "currency" => "USD",
            "value" => 7.77,
            "items" => array(
                array(
                    "item_id" => "SKU_12345",
                    "item_name" => "Stan and Friends Tee",
                    "item_category" => "Apparel",
                    "item_category2" => "Adult",
                    "item_category3" => "Shirts",
                    "item_category4" => "Crew",
                    "item_category5" => "Short sleeve",
                    "price" => 9.99,
                    "quantity" => 1
                )
            )
        ),
        'purchase' => array(
            "transaction_id" => "T_12345",
            "value" => 25.42,
            "tax" => 4.90,
            "currency" => "USD",
            "coupon" => "SUMMER_SALE",
            "items" => array(
                array(
                    "item_id" => "SKU_12345",
                    "item_name" => "Stan and Friends Tee",
                    "index" => 0,
                    "item_category" => "Apparel",
                    "item_category2" => "Adult",
                    "item_category3" => "Shirts",
                    "item_category4" => "Crew",
                    "item_category5" => "Short sleeve",
                    "price" => 9.99,
                    "quantity" => 1
                ),
                array(
                    "item_id" => "SKU_12346",
                    "item_name" => "Grey Womens Tee",
                    "index" => 1,
                    "item_category" => "Apparel",
                    "item_category2" => "Adult",
                    "item_category3" => "Shirts",
                    "item_category4" => "Crew",
                    "item_category5" => "Short sleeve",
                    "price" => 20.99,
                    "quantity" => 1
                )
            )
        ),
        'remove_from_cart' => array(
            "currency" => "USD",
            "value" => 7.77,
            "items" => array(
                array(
                    "item_id" => "SKU_12346",
                    "item_name" => "Grey Womens Tee",
                    "index" => 1,
                    "item_category" => "Apparel",
                    "item_category2" => "Adult",
                    "item_category3" => "Shirts",
                    "item_category4" => "Crew",
                    "item_category5" => "Short sleeve",
                    "price" => 20.99,
                    "quantity" => 1
                )
            )
        ),
        'view_cart' => array(
            "currency" => "USD",
            "value" => 7.77,
            "items" => array(
                array(
                    "item_id" => "SKU_12346",
                    "item_name" => "Grey Womens Tee",
                    "index" => 1,
                    "item_category" => "Apparel",
                    "item_category2" => "Adult",
                    "item_category3" => "Shirts",
                    "item_category4" => "Crew",
                    "item_category5" => "Short sleeve",
                    "price" => 20.99,
                    "quantity" => 1
                )
            )
        ),
        'view_item' => array(
            "currency" => "USD",
            "value" => 7.77,
            "page_location" => "https://domain.com/product-category/digital-magazines/",
            "items" => array(
                array(
                    "item_id" => "SKU_12346",
                    "item_name" => "Grey Womens Tee",
                    "index" => 1,
                    "item_category" => "Apparel",
                    "item_category2" => "Adult",
                    "item_category3" => "Shirts",
                    "item_category4" => "Crew",
                    "item_category5" => "Short sleeve",
                    "price" => 20.99,
                    "quantity" => 1
                )
            )
        ),
        'add_shipping_info' => array(
            'currency' => 'USD',
            'value' => 7.77,
            'coupon' => 'SUMMER_FUN',
            'shipping_tier' => 'Ground',
            'items' => array(
                array(
                    'item_id' => 'SKU_12345',
                    'item_name' => 'Stan and Friends Tee',
                    'index' => 0,
                    'item_category' => 'Apparel',
                    'item_category2' => 'Adult',
                    'item_category3' => 'Shirts',
                    'item_category4' => 'Crew',
                    'item_category5' => 'Short sleeve',
                    'price' => 9.99,
                    'quantity' => 1
                )
            )
        )
    );

    ?>
    <div class="wrap">
        <h1>Google Analytics 4 Integration</h1>
        <fieldset class="custom-fieldset my-5">
            <legend>GA4 Script Tag</legend>
            <form method="post" action="">
                <textarea id="ga_integration_script" name="ga_integration_script" rows="10"><?php echo stripslashes(base64_decode(html_entity_decode(get_option('ga_integration_script')))); ?></textarea>
                <button type="submit" name="update_ga_integration_script" class="btn btn-primary">Save</button>
            </form>
        </fieldset>
        <fieldset class="custom-fieldset my-5">
            <legend>Available dataLayers</legend>

    <?php
        $chunk_size = 3;
        $total_events = count($event_data);

        for ($i = 0; $i < $total_events; $i += $chunk_size) {
            $chunk = array_slice($event_data, $i, $chunk_size);
            
            ?>
            <div class="container mt-5">
                <div class="row p-5">
            <?php
                foreach ($chunk as $event_name => $event_details) {
                    ?>
                    <fieldset class="inner-fieldset">
                        <div class="row">
                            <div class="col-md-6">
                                <span style="font-size:18px;"><?php echo $event_name; ?></span>
                            </div>
                            <div class="col-md-6">
                                <label class="toggle-label">
                                    <input type="checkbox" id="toggleCheckbox" 
                                        <?php echo (function() use ($event_name) {
                                            $display = 'data_layer_' . ga_four_integration_api($event_name);
                                            return 'class="' . $display . '" ' . ($display == 'data_layer_on' ? 'checked=checked' : '');
                                        })(); ?>>
                                </label>
                            </div>
                        </div>
                        <?php echo formatAndColorizeJson(json_encode($event_details)); ?>
                    </fieldset>
                    <?php
                }
            ?>
                </div>
            </div>
            <?php
        }
    ?>
        </fieldset>
    </div>
    <?php
}

function ga_four_integration_save() {
    if (isset($_POST['update_ga_integration_script'])) {
        $ga_integration_script_tag = ($_POST['ga_integration_script']);
        $has_opening_script = strpos($ga_integration_script_tag, '<script>') !== false;
        $has_closing_script = strpos($ga_integration_script_tag, '</script>') !== false;

        if ($has_opening_script && $has_closing_script) {
            update_option('ga_integration_script', base64_encode(htmlspecialchars((sanitize_custom_script($ga_integration_script_tag)), ENT_QUOTES, 'UTF-8')));
        } else {
            $message = "The entered script tag appears to be invalid. Please ensure that the tag is correctly provided.";
            $class = 'notice notice-error is-dismissible ga-four-integration';
            echo '<div class="' . $class . '"><p>' . $message . '</p></div>';
        }
    }
}

function ga_four_integration_admin_wp_enqueue() {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'ga_four_integration' ) {
        wp_enqueue_style('ga-four-integration-admin-style', plugin_dir_url(__FILE__) . 'assets/css/ga-four-integration-admin.css?' . time());
        wp_enqueue_script('ga-four-integration-admin-script', plugin_dir_url(__FILE__) . 'assets/js/ga-four-integration-admin.js?' . time());
    }
}

function ga_four_integration_wp_enqueue() {
    wp_enqueue_script('ga-four-integration-script', plugin_dir_url(__FILE__) . 'assets/js/ga-four-integration.js?' . time());
}