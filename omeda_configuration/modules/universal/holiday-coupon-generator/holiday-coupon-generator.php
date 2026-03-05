<?php
    /*
    Plugin Name: Holiday Coupon Generator
    Plugin URI: 
    Description: Generates coupons intended for the holiday.
    Version: 0.0.1
    Author: James
    Author URI: 
    License: 
    */

    if( !defined( 'ABSPATH' ) ){
        exit; // Exit if accessed directly
    }

    // Exit if the user is on the one page checkout landing page
    if( get_option( 'woocommerce_one_page_checkout_id' ) != false ){
        $checkout_id = get_option( 'woocommerce_one_page_checkout_id' );
        $checkout_slug = get_post_field( 'post_name', $checkout_id );
        $current_url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $current_url = explode('/', $current_url);
        $current_url = array_filter( $current_url );
        $find_url = array_search( $checkout_slug, $current_url );
        if( $find_url !== FALSE ){
            return;
        }
    }

    add_action( 'admin_menu', 'addCouponGen', 20 );
    add_action( 'admin_init', 'couponGenSettings' );

    if(isset($_GET["page"])) {
        if(is_admin() && $_GET["page"] == 'coupon_gen') {
            remove_all_actions( 'user_admin_notices' );
            remove_all_actions( 'admin_notices' );
            remove_all_actions('all_admin_notices');
            echo '<style>div.notice:not(.holiday-coupon-generator), #message:not(.holiday-coupon-generator), .angelleye-notice {
  display: none !important;
}div.updated.fade{ display: none; }</style>';
        }
    }



    function couponGenSettings() {
        register_setting( 'coupon-gen-plugin-settings', 'coupon_gen_enabled' );
        register_setting( 'coupon-gen-plugin-settings', 'coupon_gen_identifier' );
        register_setting( 'coupon-gen-plugin-settings', 'coupon_gen_expiration' );
        register_setting( 'coupon-gen-plugin-settings', 'coupon_gen_over_amount' );
        register_setting( 'coupon-gen-plugin-settings', 'coupon_gen_amount' );
    }

    function addCouponGen() {
        add_submenu_page( 'woocommerce-marketing', 'Coupon Generation', 'Coupon Generation', 'manage_options', 'coupon_gen', 'coupon_gen' );
    }

    function coupon_gen() {
?>
        <div class="wrap">
        <h1>Coupon Generation</h1>

        <form method="post" action="options.php">
            <?php settings_fields( 'coupon-gen-plugin-settings' ); ?>
            <?php do_settings_sections( 'coupon-gen-plugin-settings' ); ?>
            <table class="form-table">
                <tr valign="top">
                <p>This is the coupon generator that will create a coupon upon a successful order based upon the dollar amount. It'll even send e-mails to the user!</p>
                <tr valign="top">
                <th scope="row">Enable</th>
                <td><input type="checkbox"  name="coupon_gen_enabled" value="1" <?php checked(1, get_option('coupon_gen_enabled'), true); ?> /><p>Would you like to enable this feature?</p></td>
                </tr>
                <tr valign="top">
                <th scope="row">Coupon ID</th>
                <td>
                    <input type="text" style="width:30%;" name="coupon_gen_identifier" value="<?php echo esc_attr( get_option('coupon_gen_identifier') ); ?>" /><?php echo (str_replace(['0', 'F'], '', strtoupper(uniqid()))); ?>
                    <p style="inline-size: 30%;">Enter a coupon identifier that will easily identify the coupons generated, e.g. "HOL" for holidays or "THANKS" for Thanksgiving.</p>
                </td>
                </tr>
                <tr valign="top">
                <th scope="row">Expiration</th>
                <td>
                    <input type="text" style="width:30%;" name="coupon_gen_expiration" value="<?php echo esc_attr( get_option('coupon_gen_expiration') ); ?>" /> days
                    <p style="inline-size: 30%;">Pick a number of days from the time the order was placed to which the coupon will expire, e.g. 7 days.</p>
                </td>
                </tr>
                <tr valign="top">
                <th scope="row">Over Amount</th>
                <td><input type="text" style="width:30%;" name="coupon_gen_over_amount" value="<?php echo esc_attr( get_option('coupon_gen_over_amount') ); ?>" />
                    <p style="inline-size: 30%;">At what dollar amount will this coupon be generated?</p></td>
                </tr>
                <tr valign="top">
                <th scope="row">Coupon Amount</th>
                <td><input type="text" style="width:30%;" name="coupon_gen_amount" value="<?php echo esc_attr( get_option('coupon_gen_amount') ); ?>" />
                    <p style="inline-size: 30%;">How much will the coupon be for?</p></td>
                </tr>
            </table>
            
            <?php submit_button(); ?>

        </form>
        </div>
<?php
    }

    function send_email($to = "jamesh@midtc.com", $message = "Test", $subject = "Your Awesome Coupon", $headers = array(), $attachments = null) {
        if (empty($headers)) {
            $headers = array('Content-Type: text/html; charset=UTF-8');
        }

        $email_send = wp_mail($to, $subject, $message, $headers, $attachments);

        if (!$email_send) {
            return false;
        } else {
            return true;
        }
    }

    function generateCouponCode($order = 3900362, $experation = "7", $amount = 10) {
        global $woocommerce; // pull woocommerce

        $uniCode = esc_attr( get_option('coupon_gen_identifier')); // identifier
        $uniCode .= (str_replace(['0', 'F'], '', strtoupper(uniqid()))); // generate coupon

        $coupon = new WC_Coupon($uniCode); // pull generated code
        $status = json_decode($coupon, true)['status']; // pull status of code

        // if status is not empty (code exists), re-run function
        if(!empty($status)) {
            return generateCouponCode($order, $experation, $amount);
        }

        $overAmount = esc_attr(get_option('coupon_gen_over_amount'));
        $discountAmount = esc_attr(get_option('coupon_gen_amount'));

        $coupon = new WC_Coupon();

        $coupon->set_code($uniCode);

        $coupon->set_description("[AUTO] Order #{$order} amount - {$amount} >= {$overAmount}.");
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount($discountAmount);
        $coupon->set_date_expires(date('d-m-Y', strtotime("+{$experation} days")));
        $coupon->set_free_shipping(false);
        $coupon->set_individual_use(true);
        $coupon->set_exclude_sale_items(false);
        $coupon->set_usage_limit(1);

        $coupon->save();

        updateOrderMetaData($order, $uniCode);

        return $uniCode;
    }

    function generate_gift_code($order_number, $experation = "7", $amount = 10) {
        $order = wc_get_order($order_number);

        // deleteOrderMetaData($order_number);
        $orderAmount = round($order->get_total());
        $discountAmount = esc_attr(get_option('coupon_gen_amount'));
        if(!($orderAmount >= $amount)) {
            //log_to_console("Cannot generate coupon due to total order amount - \${$orderAmount} not being greater than or equal to \${$amount}.");
            return false;
        } else {
            //log_to_console("Making coupon due to order amount - \${$orderAmount} - being greater than or equal to \${$amount}.");
            ?>
                <script type="text/javascript">
                    window.onload = (event) => {
                        let div = document.createElement('div');
                        var htmlString = '<span class="coupon-code" style="display:block;padding:10px;width:100%;background-color:lightblue;border-radius:10px;">Congrats! You have earned $<?= $discountAmount ?> toward your next purchase!<br/>';
                        htmlString += '<span style="font-size: .75rem;">Your gift will be emailed to you!</span></span>';
                        div.innerHTML = htmlString;
                        let div1 = document.querySelector('.woocommerce-thankyou-order-received');
                        div1.parentElement.insertBefore(div.firstChild, div1.nextSibling);
                    };
                </script>
            <?php
        }

        if(!empty($order->get_meta('_coupon_code_generated'))) {
            //log_to_console('Already has coupon assigned to order.');
            return false;
        } else {
            //log_to_console('Order does not have coupon assisgned to it - making one.');
        }

        $site = array("website" => get_bloginfo('name'), "url" => get_permalink(wc_get_page_id('shop')));
        $customer = array("order" => $order_number, "email" => $order->get_billing_email(), "coupon" => generateCouponCode($order_number, $experation, $orderAmount));

        $message = <<<EX
Thank you for your recent purchase!<br/><br/>

You've earned \${$discountAmount} toward your next purchase at the {$site['website']} shop.<br/><br/>

It's so simple! Enter coupon code {$customer['coupon']} at checkout to redeem.<br/><br/>

Start Shopping & Saving Now <a href="{$site['url']}?apply_coupon={$customer['coupon']}&sc-page=shop" target="_blank">{$site['url']}</a></br><br/>

*One time use only. The total credit must be used in one transaction. Some exclusions may apply.
EX;

        $subject = "\${$discountAmount} toward your next purchase at {$site['website']}!";

        // $customer['email'] = 'james@midtc.com'; // customer email override

        // message, to, subject - all other settings in Easy WP SMTP (from, name, reply to)
        send_email($customer['email'], $message, $subject);

        return $customer['coupon'];

    }

    // //log_to_console(delete_gift_code('hol63986d94d'));

    function delete_gift_code($code) {
        $coupon_data = new WC_Coupon($code);
        $status = json_decode($coupon_data, true)['status']; 

        if(!empty($status)) {
            wp_delete_post($coupon_data->id);
        }
    }

    function updateOrderMetaData($orderNum, $code) {
        $order = wc_get_order( $orderNum);
        $order->update_meta_data( '_coupon_code_generated', $code);
        $order->save();
    }

    function deleteOrderMetaData($orderNum) {
        $order = wc_get_order( $orderNum);
        $order->delete_meta_data( '_coupon_code_generated');
        $order->save();
    }

    if(get_option('coupon_gen_enabled')) {
        add_action( 'template_redirect', 'checkCartContents');
        add_action( 'woocommerce_thankyou', 'callCouponCode', 10, 1 );
    }

    if (!function_exists('log_to_console')) {
        function log_to_console( $text ) {
           echo "<script>console.log('" . $text . "' );</script>";
        }
    }

    function checkCartContents() {
        if( is_cart() || is_checkout() ) {
            //log_to_console('In the cart - dumping code.');
            $overAmount = esc_attr(get_option('coupon_gen_over_amount'));
            $discountAmount = esc_attr(get_option('coupon_gen_amount'));
            
            $generator = array(
                'coupon_gen_over_amount' => $overAmount,
                'discount_amount'=> $discountAmount
            );

            wp_enqueue_script('coupon-generator', plugin_dir_url(__FILE__) . 'assets/js/reward-notification.js?cache=' . time());
            wp_add_inline_script('coupon-generator', 'var generator = ' . wp_json_encode( $generator ), 'before' );
        }
    }

    function callCouponCode( $order_id ) {
        try {
            // deleteOrderMetaData($order_id);

            $genCode = generate_gift_code($order_id, esc_attr(get_option('coupon_gen_expiration')), esc_attr(get_option('coupon_gen_over_amount')));

            if(!$genCode) {
                //log_to_console('Generation of coupon code exited.');
            }
        } catch (Exception $e) { }
    }

    function holiday_coupon_generator_apply_cart_coupon_in_url() {
        if ( ! function_exists( 'WC' ) || ! WC()->session ) {
            return;
        }

        $coupon_code = isset( $_GET['apply_coupon'] ) ? sanitize_text_field( wp_unslash( $_GET['apply_coupon'] ) ) : '';

        if ( empty( $coupon_code ) ) {
            return;
        }

        WC()->session->set_customer_session_cookie( true );

        if ( ! WC()->cart->has_discount( $coupon_code ) ) {
            WC()->cart->add_discount( $coupon_code );
        }
    }

    add_action( 'wp_loaded', 'holiday_coupon_generator_apply_cart_coupon_in_url', 30 );
    add_action( 'woocommerce_add_to_cart', 'holiday_coupon_generator_apply_cart_coupon_in_url' );
