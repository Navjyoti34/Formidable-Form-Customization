<?php
/**
 * Plugin Name: Woo Credit Card Surcharge & Survey
 * Description: Dynamic credit card surcharge text + optional survey form based on product setting with admin settings.
 * Version: 1.1.0
 * Author: Asen dev
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------
 * ADD SETTINGS TAB (WooCommerce → Settings)
 * --------------------------------------------------------- */
add_filter( 'woocommerce_settings_tabs_array', function ( $tabs ) {
    $tabs['wc_survey'] = 'Survey & Surcharge';
    return $tabs;
}, 50 );

add_action( 'woocommerce_settings_tabs_wc_survey', function () {
    woocommerce_admin_fields( wc_survey_get_settings() );
});

add_action( 'woocommerce_update_options_wc_survey', function () {
    woocommerce_update_options( wc_survey_get_settings() );
});

/* ---------------------------------------------------------
 * SETTINGS FIELDS
 * --------------------------------------------------------- */
function wc_survey_get_settings() {

    return [

        [
            'title' => 'Survey Settings',
            'type'  => 'title',
            'id'    => 'wc_survey_section',
        ],

        [
            'title'   => 'Enable Survey',
            'id'      => 'wc_survey_enabled',
            'type'    => 'checkbox',
            'default' => 'yes',
            'desc'    => 'Enable survey on Thank You page',
        ],

        [
            'title'   => 'Formidable Form ID',
            'id'      => 'wc_survey_form_id',
            'type'    => 'number',
            'default' => '10',
        ],

        [
            'title'   => 'Order ID Field Name',
            'id'      => 'wc_survey_field_order',
            'type'    => 'text',
            'default' => 'order_id',
        ],

        [
            'title'   => 'First Name Field',
            'id'      => 'wc_survey_field_first',
            'type'    => 'text',
            'default' => 'first_name',
        ],

        [
            'title'   => 'Last Name Field',
            'id'      => 'wc_survey_field_last',
            'type'    => 'text',
            'default' => 'last_name',
        ],

        [
            'title'   => 'Email Field',
            'id'      => 'wc_survey_field_email',
            'type'    => 'text',
            'default' => 'email',
        ],

        [
            'type' => 'sectionend',
            'id'   => 'wc_survey_section',
        ],

        [
            'title' => 'Credit Card & Surcharge',
            'type'  => 'title',
            'id'    => 'wc_surcharge_section',
        ],

        [
            'title'   => 'Credit Card Label',
            'id'      => 'wc_credit_card_label',
            'type'    => 'text',
            'default' => 'Credit Card',
        ],

        [
            'title'   => 'Surcharge Legal Text',
            'id'      => 'wc_surcharge_text',
            'type'    => 'textarea',
            'css'     => 'min-height:80px;',
            'default' => 'A payment processing fee (up to {percent}%) may apply with credit card payments, where permitted by law.',
        ],

        [
            'type' => 'sectionend',
            'id'   => 'wc_surcharge_section',
        ],
    ];
}

/* ---------------------------------------------------------
 * PRODUCT META BOX – ENABLE SURVEY
 * --------------------------------------------------------- */
add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'wc_enable_survey',
        'Enable Survey Form',
        'wc_enable_survey_metabox',
        'product',
        'side'
    );
});

function wc_enable_survey_metabox( $post ) {
    $value = get_post_meta( $post->ID, '_enable_survey', true );
    ?>
    <select name="wc_enable_survey" style="width:100%">
        <option value="no" <?php selected( $value, 'no' ); ?>>No</option>
        <option value="yes" <?php selected( $value, 'yes' ); ?>>Yes</option>
    </select>
    <?php
}

add_action( 'save_post_product', function ( $post_id ) {
    if ( isset( $_POST['wc_enable_survey'] ) ) {
        update_post_meta(
            $post_id,
            '_enable_survey',
            sanitize_text_field( $_POST['wc_enable_survey'] )
        );
    }
});

/* ---------------------------------------------------------
 * SHOW SURVEY ON THANK YOU PAGE
 * --------------------------------------------------------- */
add_action( 'woocommerce_thankyou', function ( $order_id ) {

    if ( get_option( 'wc_survey_enabled' ) !== 'yes' ) return;
    if ( ! $order_id ) return;

    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    $show = false;

    foreach ( $order->get_items() as $item ) {
        if ( get_post_meta( $item->get_product_id(), '_enable_survey', true ) === 'yes' ) {
            $show = true;
            break;
        }
    }

    if ( ! $show ) return;

    $form_id = absint( get_option( 'wc_survey_form_id', 10 ) );

    echo '<div class="wc-survey-wrap" style="margin-top:30px">';
    echo '<h3>Please Share Your Feedback</h3>';
    echo do_shortcode( '[formidable id="' . $form_id . '"]' );
    echo '</div>';

}, 5 );

/* ---------------------------------------------------------
 * PASS ORDER DATA TO FORM (FORMIDABLE SAFE VERSION)
 * --------------------------------------------------------- */
add_action( 'wp_footer', function () {

    if ( ! is_wc_endpoint_url( 'order-received' ) ) return;

    $order_id = absint( get_query_var( 'order-received' ) );
    $order    = wc_get_order( $order_id );
    if ( ! $order ) return;

    ?>
    <script>
    (function () {

        const values = {
            order: "<?php echo esc_js( $order_id ); ?>",
            first: "<?php echo esc_js( $order->get_billing_first_name() ); ?>",
            last:  "<?php echo esc_js( $order->get_billing_last_name() ); ?>",
            email: "<?php echo esc_js( $order->get_billing_email() ); ?>"
        };

        const fields = {
            order: "<?php echo esc_js( get_option('wc_survey_field_order') ); ?>",
            first: "<?php echo esc_js( get_option('wc_survey_field_first') ); ?>",
            last:  "<?php echo esc_js( get_option('wc_survey_field_last') ); ?>",
            email: "<?php echo esc_js( get_option('wc_survey_field_email') ); ?>"
        };

        let attempts = 0;
        const maxAttempts = 20;

        const interval = setInterval(function () {

            attempts++;

            Object.keys(fields).forEach(function (key) {

                const selector = '[name="item_meta[' + fields[key] + ']"]';
                const input = document.querySelector(selector);

                if (input && !input.value) {
                    input.value = values[key];
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            // Stop after success or timeout
            if (
                document.querySelector('[name="item_meta[' + fields.order + ']"]') ||
                attempts >= maxAttempts
            ) {
                clearInterval(interval);
            }

        }, 500);

    })();
    </script>
    <?php
});


/* ---------------------------------------------------------
 * SURCHARGE PERCENTAGE CALCULATION
 * --------------------------------------------------------- */
function wc_get_surcharge_percentage() {

    if ( ! WC()->cart ) return false;

    $subtotal = WC()->cart->get_subtotal();
    if ( $subtotal <= 0 ) return false;

    $percentages = [];

    foreach ( WC()->cart->get_fees() as $fee ) {
        if ( $fee->total > 0 ) {
            $percentages[] = ( $fee->total / $subtotal ) * 100;
        }
    }

    return empty( $percentages ) ? false : max( $percentages );
}

/* ---------------------------------------------------------
 * SHOW SURCHARGE LEGAL TEXT
 * --------------------------------------------------------- */
add_action( 'woocommerce_review_order_before_submit', function () {

    $percent = wc_get_surcharge_percentage();
    if ( ! $percent ) return;

    $text = get_option( 'wc_surcharge_text' );
    $text = str_replace( '{percent}', number_format( $percent, 1 ), $text );

    echo '<div class="checkout-surcharge-legal-text" style="margin-bottom:15px;"><em>' .
         esc_html( $text ) .
         '</em></div>';
});

/* ---------------------------------------------------------
 * RENAME FEES TO CREDIT CARD CHARGE
 * --------------------------------------------------------- */
add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {
    foreach ( $cart->get_fees() as $fee ) {
        $fee->name = 'Credit Card Charge';
    }
});

/* ---------------------------------------------------------
 * RENAME STRIPE TO CREDIT CARD (DYNAMIC)
 * --------------------------------------------------------- */
add_filter( 'woocommerce_gateway_title', function ( $title, $gateway_id ) {
    if ( $gateway_id === 'stripe' ) {
        return get_option( 'wc_credit_card_label', 'Credit Card' );
    }
    return $title;
}, 10, 2 );

add_filter( 'woocommerce_order_get_payment_method_title', function ( $title, $order ) {
    if ( $order && $order->get_payment_method() === 'stripe' ) {
        return get_option( 'wc_credit_card_label', 'Credit Card' );
    }
    return $title;
}, 10, 2 );
