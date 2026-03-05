<?php
/**
 * Plugin Name: WooCommerce Custom Labels & Fees
 * Description: Add custom fee label, surcharge description, and payment method label via settings.
 * Version: 1.0.0
 * Author: Surendra Raghuwanshi
 * Text Domain: wc-custom-labels
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Custom_Labels {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        add_filter( 'woocommerce_cart_calculate_fees', [ $this, 'rename_fee_label' ], 20 );
        add_filter( 'woocommerce_gateway_title', [ $this, 'change_payment_method_label' ], 20, 2 );

    }

    /* =========================
     * Settings Page
     * ========================= */
    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            'Custom Labels',
            'Custom Labels',
            'manage_woocommerce',
            'wc-custom-labels',
            [ $this, 'settings_page_html' ]
        );
    }

    public function register_settings() {

        register_setting( 'wc_custom_labels', 'wc_fee_label' );
        register_setting( 'wc_custom_labels', 'wc_surcharge_desc' );
        register_setting( 'wc_custom_labels', 'wc_payment_method_label' );

        add_settings_section(
            'wc_custom_labels_section',
            'WooCommerce Custom Labels',
            null,
            'wc-custom-labels'
        );

        add_settings_field(
            'wc_fee_label',
            'Fee Label',
            [ $this, 'text_field' ],
            'wc-custom-labels',
            'wc_custom_labels_section',
            [ 'option' => 'wc_fee_label', 'placeholder' => 'Credit Card Charge' ]
        );

        add_settings_field(
            'wc_surcharge_desc',
            'Surcharge Description',
            [ $this, 'textarea_field' ],
            'wc-custom-labels',
            'wc_custom_labels_section',
            [ 'option' => 'wc_surcharge_desc' ]
        );

        add_settings_field(
            'wc_payment_method_label',
            'Payment Method Label (Stripe)',
            [ $this, 'text_field' ],
            'wc-custom-labels',
            'wc_custom_labels_section',
            [ 'option' => 'wc_payment_method_label', 'placeholder' => 'Credit Card' ]
        );
    }

    public function text_field( $args ) {
        $value = esc_attr( get_option( $args['option'], '' ) );
        echo '<input type="text" class="regular-text" name="' . esc_attr( $args['option'] ) . '" value="' . $value . '" placeholder="' . esc_attr( $args['placeholder'] ?? '' ) . '">';
    }

    public function textarea_field( $args ) {
        $value = esc_textarea( get_option( $args['option'], '' ) );
        echo '<textarea class="large-text" rows="4" name="' . esc_attr( $args['option'] ) . '">' . $value . '</textarea>';
    }

    public function settings_page_html() {
        ?>
        <div class="wrap">
            <h1>WooCommerce Custom Labels</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wc_custom_labels' );
                do_settings_sections( 'wc-custom-labels' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /* =========================
     * Frontend Logic
     * ========================= */

    // 1️⃣ Rename Extra Fee Label
    public function rename_fee_label( $cart ) {

        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $label = get_option( 'wc_fee_label' );
        if ( ! $label ) return;

        foreach ( $cart->get_fees() as $fee ) {
            $fee->name = $label;
        }
    }

    // 2️⃣ Payment Method Label (Stripe)
    public function change_payment_method_label( $title, $gateway_id ) {

        if ( $gateway_id === 'stripe' ) {
            $custom_label = get_option( 'wc_payment_method_label' );
            if ( $custom_label ) {
                $title = $custom_label;
            }
        }

        return $title;
    }

    
}

new WC_Custom_Labels();
