
<?php
/**
 * ABSOLUTE BLOCK: Prevent user meta updates during checkout
 * This MUST be at the top of the file, before any other code
 */

add_action('init', 'fwc_store_original_user_data', 1);
function fwc_store_original_user_data() {
    if (!is_user_logged_in()) {
        return;
    }
    if (WC()->session && !WC()->session->get('fwc_original_billing_stored')) {
        $user_id = get_current_user_id();
        
        $original_data = array(
            'billing_first_name' => get_user_meta($user_id, 'billing_first_name', true),
            'billing_last_name' => get_user_meta($user_id, 'billing_last_name', true),
            'billing_company' => get_user_meta($user_id, 'billing_company', true),
            'billing_address_1' => get_user_meta($user_id, 'billing_address_1', true),
            'billing_address_2' => get_user_meta($user_id, 'billing_address_2', true),
            'billing_city' => get_user_meta($user_id, 'billing_city', true),
            'billing_state' => get_user_meta($user_id, 'billing_state', true),
            'billing_postcode' => get_user_meta($user_id, 'billing_postcode', true),
            'billing_country' => get_user_meta($user_id, 'billing_country', true),
            'billing_email' => get_user_meta($user_id, 'billing_email', true),
            'billing_phone' => get_user_meta($user_id, 'billing_phone', true),
            'shipping_first_name' => get_user_meta($user_id, 'shipping_first_name', true),
            'shipping_last_name' => get_user_meta($user_id, 'shipping_last_name', true),
            'shipping_company' => get_user_meta($user_id, 'shipping_company', true),
            'shipping_address_1' => get_user_meta($user_id, 'shipping_address_1', true),
            'shipping_address_2' => get_user_meta($user_id, 'shipping_address_2', true),
            'shipping_city' => get_user_meta($user_id, 'shipping_city', true),
            'shipping_state' => get_user_meta($user_id, 'shipping_state', true),
            'shipping_postcode' => get_user_meta($user_id, 'shipping_postcode', true),
            'shipping_country' => get_user_meta($user_id, 'shipping_country', true),
        );
        
        WC()->session->set('fwc_original_billing', $original_data);
        WC()->session->set('fwc_original_billing_stored', true);
    }
}
add_action('woocommerce_checkout_update_customer', '__return_false', 1, 2);
add_filter('woocommerce_checkout_update_customer_data', '__return_empty_array', 999);


add_filter('update_user_metadata', 'fwc_block_user_meta_updates', 999, 5);
function fwc_block_user_meta_updates($check, $object_id, $meta_key, $meta_value, $prev_value) {

    if (!is_user_logged_in() || get_current_user_id() != $object_id) {
        return $check;
    }

    $protected_keys = array(
        'billing_first_name', 'billing_last_name', 'billing_company',
        'billing_address_1', 'billing_address_2', 'billing_city',
        'billing_state', 'billing_postcode', 'billing_country',
        'billing_email', 'billing_phone',
        'shipping_first_name', 'shipping_last_name', 'shipping_company',
        'shipping_address_1', 'shipping_address_2', 'shipping_city',
        'shipping_state', 'shipping_postcode', 'shipping_country',
    );
    if (in_array($meta_key, $protected_keys)) {
        if (is_checkout() || is_cart() || wp_doing_ajax() || 
            (isset($_POST['action']) && $_POST['action'] === 'woocommerce_checkout') ||
            did_action('woocommerce_checkout_process') > 0) {
            return true;
        }
    }
    
    return $check;
}

// Restore original data after order is placed
add_action('woocommerce_checkout_order_processed', 'fwc_restore_original_billing', 9999, 1);
function fwc_restore_original_billing($order_id) {
    if (!is_user_logged_in() || !WC()->session) {
        return;
    }    
    $user_id = get_current_user_id();
    $original_data = WC()->session->get('fwc_original_billing');
    
    if (!empty($original_data) && is_array($original_data)) {
        foreach ($original_data as $meta_key => $meta_value) {
            global $wpdb;
            $wpdb->update(
                $wpdb->usermeta,
                array('meta_value' => $meta_value),
                array(
                    'user_id' => $user_id,
                    'meta_key' => $meta_key
                ),
                array('%s'),
                array('%d', '%s')
            );
        }
        WC()->session->set('fwc_original_billing', null);
        WC()->session->set('fwc_original_billing_stored', false);
    }
}

// Also restore on thank you page as a backup
add_action('woocommerce_thankyou', 'fwc_restore_on_thankyou', 9999);
function fwc_restore_on_thankyou($order_id) {
    fwc_restore_original_billing($order_id);
}

add_action('woocommerce_checkout_init', 'fwc_disable_customer_persistence');
function fwc_disable_customer_persistence($checkout) {
    remove_action('woocommerce_checkout_update_customer', array($checkout, 'update_customer'), 10);
}

add_action('woocommerce_checkout_order_processed', 'fwc_debug_user_meta', 10000);
function fwc_debug_user_meta($order_id) {
    if (!is_user_logged_in()) return;
    
    $user_id = get_current_user_id();
    $billing_first = get_user_meta($user_id, 'billing_first_name', true);
    $billing_last = get_user_meta($user_id, 'billing_last_name', true);
    
    error_log('FWC DEBUG: After order processed - User billing: ' . $billing_first . ' ' . $billing_last);
}