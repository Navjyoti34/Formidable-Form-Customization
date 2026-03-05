<?php

/*
Plugin Name: AN Sub Opt In
Plugin URI:
Description: On the membership details page, there is a checkbox this script manages.
Version: 1.0
Author:  James
Author URI: http://midtc.com/
License:
*/

// Exit if accessed directly.
if (! defined('ABSPATH')) exit;

include($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

$MIDTCSubscriptionOpt = new MIDTCSubscriptionOpt();

add_action('woocommerce_before_edit_address', array($MIDTCSubscriptionOpt, 'addCustomStylesToMembershipDetails'));

class MIDTCSubscriptionOpt {
    public function addCustomStylesToMembershipDetails() {
        if (is_wc_endpoint_url('members_area')) {
            wp_enqueue_style('subscription-opt-style', plugin_dir_url(__FILE__) . 'assets/css/subscription-opt.css?' . time());
            wp_enqueue_script('subscription-opt-script', plugin_dir_url(__FILE__) . 'assets/js/subscription-opt.js?' . time(), array('jquery'));
            wp_localize_script('subscription-opt-script', 'subOptScript', array(
                'nonce' => wp_create_nonce('opt-in-nonce')
            ));
        }
    }

    public function validateFields($inputFields) {
        $fields = array(
            'shipping_first_name' => 'First name',
            'shipping_last_name' => 'Last name',
            'shipping_address_1' => 'Street address',
            'shipping_country'   => 'Country',
            'shipping_city'      => 'City',
            'shipping_state'     => 'State',
            'shipping_postcode'  => 'Postcode',
        );

        $errors = false;
        $message = '';
        $addressInfo = array();

        foreach ($fields as $field => $label) {
            if (isset($inputFields[$field]) && !empty($inputFields[$field])) {
                $addressInfo[$field] = sanitize_text_field($inputFields[$field]);
            } else {
                $errors = true;
                $message = "<strong>$label</strong> is a required field.";
                break;
            }
        }

        return array('errors' => $errors, 'message' => $message, 'addressInfo' => $addressInfo);
    }
}


function membership_endpoint_content($url_id) {
    $MIDTCSubscriptionOpt = new MIDTCSubscriptionOpt();

    $user_id = get_current_user_id();
    $subscription_opted_in = get_user_meta($user_id, 'subscription_opted', true);

    if (isset($_POST['update_shipping_address'])) {
        if ($user_id) {
            $address_data = array(
                'first_name' => sanitize_text_field($_POST['shipping_first_name']),
                'last_name' => sanitize_text_field($_POST['shipping_last_name']),
                'address_1' => sanitize_text_field($_POST['shipping_address_1']),
                'address_2' => sanitize_text_field($_POST['shipping_address_2']),
                'city'      => sanitize_text_field($_POST['shipping_city']),
                'state'     => sanitize_text_field($_POST['shipping_state']),
                'postcode'  => sanitize_text_field($_POST['shipping_postcode']),
                'country'   => sanitize_text_field($_POST['shipping_country']),
            );

            $check_fields = $MIDTCSubscriptionOpt->validateFields($_POST);

            if(!empty($check_fields['errors'])) {
                wc_add_notice(__(($check_fields['message'])), 'error');
            } else {
                foreach ($address_data as $field => $value) {
                    update_user_meta($user_id, 'shipping_' . $field, $value);
                }

                wc_add_notice(__(('You have successfully been opted-in. Your item will be mailed according to the USPS guidelines.')), 'success');
                update_user_meta($user_id, 'subscription_opted', true);
            }
        } else {
            wc_add_notice(__(('User ID not found.')), 'error');
        }
        die(header("Refresh:0"));
    }

    if(isset($_GET['_wpnonce']) && !empty($_GET['_wpnonce']) && isset($_GET['opt_out']) && !empty($_GET['opt_out']) && $_GET['opt_out'] === 'true') {
        update_user_meta($user_id, 'subscription_opted', false);
        wc_add_notice(__(('You have successfully been opted-out. You will no longer be mailed an issue.')), 'success');

        $current_url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $parts = parse_url($current_url);

        if (isset($parts['query'])) {
            $new_parts = $parts;
            unset($new_parts['query']);

            $new_url = $new_parts['scheme'] . '://' . $new_parts['host'] . $new_parts['path'];

            die(header("Location: $new_url"));
        }
    }
?>
    <div id="magazine-opt-in-content">
        <input id="magazine-opt-in" type="checkbox" onclick="toggle();" <?php echo ($subscription_opted_in ? ' checked' : '' ); ?> />
        <input type="hidden" id="magazine-opt-in-hidden" value=<?php echo ($subscription_opted_in ? ' checked' : '' ); ?>>
        <label for="magazine-opt-in">Yes, send me my included subscription to Artists Magazine.</label>
    </div>
    <br/>
<?php
    $load_address = 'shipping';
    $address = WC()->countries->get_address_fields(get_user_meta($user_id, $load_address . '_country', true), $load_address . '_');
?>

    <form method="post" id="address_form" style="display:none;" class="woocommerce-shipping-address-form">
        <?php do_action("woocommerce_before_edit_address"); ?>

        <?php foreach ($address as $key => $field) : ?>
            <?php
            $value = get_user_meta($user_id, $key, true);
            $field['value'] = isset($value) && !empty($value) ? $value : $field['value'];
            woocommerce_form_field($key, $field, $value);
            ?>
        <?php endforeach; ?>

        <?php do_action("woocommerce_after_edit_address_form"); ?>

        <p class="form-row">
            <input type="submit" class="button" name="update_shipping_address" value="Opt In" />
        </p>
    </form>
<?php
}
?>