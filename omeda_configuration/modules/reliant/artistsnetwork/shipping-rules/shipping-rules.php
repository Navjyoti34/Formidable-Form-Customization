<?php

    if (! defined('ABSPATH')) exit;

    class ShippingRules {
        private $shipping_table = array(
            "CA" => array(
                "PRT" => 2.00,
                "MO" => 2,
                "YR" => 12
            ),
            "INT" => array(
                "PRT" => 3.00,
                "MO" => 2,
                "YR" => 18
            )
        );

        public function calculateShippingCost($country, $contains_prt_anmem) {
            $rate_cost = 0;

            foreach ($contains_prt_anmem as $key => $value) {
                if (!$value) continue;

                $duration_to_prefix = preg_match('/\bmonth\b/i', $value) ? 'MO' : (preg_match('/\byear\b/i', $value) ? 'YR' : $key);

                if($duration_to_prefix == 'PRT') {
                    $rate_cost += ($this->shipping_table[$country][$duration_to_prefix] * $value);
                    continue;
                }

                $rate_cost += $this->shipping_table[$country][$duration_to_prefix];
            }

            return $rate_cost;
        }

        public function containsPrtAnmem($decoded_data) {
            $contains = array();
            $contains = array($contains['PRT'] => 0);
			
            if ($decoded_data === null) {
                return false;
            }

            if (isset($decoded_data['contents'])) {
                foreach ($decoded_data['contents'] as $content) {
                    $product_id = $content['product_id'];
                    $quantity = $content['quantity'];

                    if (isset($product_id)) {
                        if ($this->hasPrintMagazineCategory($product_id)) {
                            $contains['PRT'] += (1 * $quantity);
                        }

                        if (isset($content['variation']['attribute_subscription-term']) && $this->get_item_title_contains_artists_network($product_id)) {
                            $contains['ANMEM'] = strtolower($content['variation']['attribute_subscription-term']);
                        }
                    }
                }
            }

            return $contains;
        }

        private function hasPrintMagazineCategory($product_id) {
            if (!class_exists('WooCommerce')) {
                return false;
            }

            $product_categories = get_the_terms($product_id, 'product_cat');

            if (!$product_categories || is_wp_error($product_categories)) {
                return false;
            }

            foreach ($product_categories as $category) {
                $category_name = strtolower($category->name);
            
                if (strpos($category_name, "print magazine") !== false) {
                    return true;
                }
            }

            return false;
        }

        private function get_item_title_contains_artists_network($product_id) {
            if (!class_exists('WooCommerce')) {
                return false;
            }

            $product = wc_get_product($product_id);

            if (!$product) {
                return false;
            }

            $item_title = $product->get_name();

            $lowercase_title = strtolower($item_title);

            return strpos($lowercase_title, "artists network") !== false;
        }

        public function overrideShippingCostInCart($rates, $package) {
            $country = $package['destination']['country'];
            $is_int = ($country !== 'US');
            $contains_prt_anmem = $this->containsPrtAnmem($package);
        
            $final_rates = array();
            $free_shipping_id = null;
            $flat_rate_id = null;
            if ($country === 'US' || empty($contains_prt_anmem)) {
                return $rates;
            }
            $flat_rate_count = 0;
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = $cart_item['data'];
                $shipping_class = $product->get_shipping_class();
                if ($shipping_class === 'flat-rate') {
                    $flat_rate_count += $cart_item['quantity'];
                }
            }
        
            foreach ($rates as $rate_id => $rate) {
                if ($rate->label === 'Shipping - Canada' && $country === 'CA') {
                    $rate->cost = $this->calculateShippingCost('CA', $contains_prt_anmem);
                    $final_rates[$rate_id] = $rate;
                }
        
                if ($is_int) {
                    if ($rate->label === 'Free Shipping - Print Mag Sub') {
                        $free_shipping_id = $rate_id;
                    } elseif ($rate->label === 'Flat rate') {
                        $flat_rate_id = $rate_id;
                    } elseif ($rate->label === 'Shipping - International') {
                        $rate->cost = $this->calculateShippingCost('INT', $contains_prt_anmem);
                        $final_rates[$rate_id] = $rate;
                    }
                } else {
                    $final_rates[$rate_id] = $rate;
                }
            }
            if ($is_int) {
                if ($flat_rate_id !== null && $flat_rate_count > 0) {
                    $rate = $rates[$flat_rate_id];
                    $per_item_cost = ($country === 'CA') ? 2.00 : 3.00;
                    $rate->cost = $per_item_cost * $flat_rate_count;
        
                    $final_rates = array();
                    $final_rates[$flat_rate_id] = $rate;
        
                } elseif ($free_shipping_id !== null && $flat_rate_count === 0) {
                    $rate = $rates[$free_shipping_id];
                    $rate->cost = 0.00;
        
                    $final_rates = array();
                    $final_rates[$free_shipping_id] = $rate;
                }
            }
        
            return $final_rates;
        }
    }

    add_filter('woocommerce_package_rates', array(new ShippingRules(), 'overrideShippingCostInCart'), 10, 2);