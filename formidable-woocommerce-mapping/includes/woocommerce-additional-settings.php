<?php
/**
 * Add Store Phone Number Field to WooCommerce General Settings
 * Appears below the Store Address section
 */

// =============================
// 1. ADD PHONE FIELD TO GENERAL SETTINGS (BELOW ADDRESS)
// =============================
add_filter('woocommerce_general_settings', 'fwc_add_store_phone_to_general_settings');
function fwc_add_store_phone_to_general_settings($settings) {
    
    // Find the position after "Store Address" field
    $insert_position = 0;
    foreach ($settings as $key => $setting) {
        if (isset($setting['id']) && $setting['id'] === 'woocommerce_store_postcode') {
            $insert_position = $key + 1;
            break;
        }
    }
    
    // Define the phone number field
    $phone_field = array(
        array(
            'title'       => __('Store Phone', 'woocommerce'),
            'desc'        => __('The phone number customers can use to contact your store.', 'woocommerce'),
            'id'          => 'woocommerce_store_phone',
            'type'        => 'text',
            'css'         => 'min-width:300px;',
            'default'     => '',
            'autoload'    => false,
            'desc_tip'    => true,
            'placeholder' => __('+1 (555) 123-4567', 'woocommerce'),
        ),
    );
    
    // Insert the field after postcode
    array_splice($settings, $insert_position, 0, $phone_field);
    
    return $settings;
}

// =============================
// 2. HELPER FUNCTION TO GET PHONE NUMBER
// =============================
function fwc_get_store_phone() {
    $phone = trim(get_option('woocommerce_store_phone'));
    return apply_filters('fwc_store_phone', $phone);
}

// =============================
// 3. ADD PHONE TO EMAIL FOOTER
// =============================
add_action('woocommerce_email_footer_text', 'fwc_add_store_phone_to_email_footer');
function fwc_add_store_phone_to_email_footer($email) {
    $phone = trim(get_option('woocommerce_store_phone'));
    
    if (!empty($phone)) {
        echo '<p style="margin-top: 20px; text-align: center;">';
        echo '<strong>' . __('Store Phone:', 'woocommerce') . '</strong> ';
        echo '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '" style="color: #0066cc; text-decoration: none;">';
        echo esc_html($phone);
        echo '</a>';
        echo '</p>';
    }
}

// =============================
// 4. USAGE EXAMPLES
// =============================

// Example 1: Use in any template
function my_display_store_phone() {
    $phone = trim(get_option('woocommerce_store_phone'));
    if (!empty($phone)) {
        echo '<p>Call us: <a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a></p>';
    }
}

// Example 2: Add to header
add_action('wp_head', 'fwc_phone_in_header');
function fwc_phone_in_header() {
    $phone = trim(get_option('woocommerce_store_phone'));
    if (!empty($phone)) {
        ?>
        <style>
            .site-phone {
                position: absolute;
                top: 10px;
                right: 20px;
                font-weight: bold;
            }
        </style>
        <?php
    }
}

// Example 3: Add to footer
add_action('wp_footer', 'fwc_phone_in_footer');
function fwc_phone_in_footer() {
    $phone = trim(get_option('woocommerce_store_phone'));
    if (!empty($phone)) {
        echo '<div class="footer-phone" style="text-align: center; padding: 20px;">';
        echo '<strong>' . __('Call Us:', 'woocommerce') . '</strong> ';
        echo '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '">' . esc_html($phone) . '</a>';
        echo '</div>';
    }
}

// =============================
// 5. SHORTCODE
// =============================
add_shortcode('store_phone', 'fwc_store_phone_shortcode');
function fwc_store_phone_shortcode($atts) {
    $atts = shortcode_atts(array(
        'format' => 'link', // link, plain, button
        'label' => '',
    ), $atts);
    
    $phone = trim(get_option('woocommerce_store_phone'));
    
    if (empty($phone)) {
        return '';
    }
    
    // Plain text
    if ($atts['format'] === 'plain') {
        return esc_html($phone);
    }
    
    // Button format
    if ($atts['format'] === 'button') {
        $label = !empty($atts['label']) ? $atts['label'] : __('Call Now', 'woocommerce');
        return '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '" class="button store-phone-button">' . esc_html($label) . '</a>';
    }
    
    // Default link format
    $label = !empty($atts['label']) ? $atts['label'] . ' ' : '';
    return $label . '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '">' . esc_html($phone) . '</a>';
}

// Usage:
// [store_phone]
// [store_phone format="plain"]
// [store_phone format="button" label="Call Us Now"]
// [store_phone label="Phone:"]

// =============================
// 6. ADD TO CHECKOUT PAGE
// =============================
add_action('woocommerce_review_order_before_payment', 'fwc_phone_on_checkout');
function fwc_phone_on_checkout() {
    $phone = trim(get_option('woocommerce_store_phone'));
    
    if (!empty($phone)) {
        echo '<div class="checkout-phone-notice" style="background: #f0f8ff; padding: 15px; margin: 20px 0; border-left: 4px solid #0066cc;">';
        echo '<strong>' . __('Need Help?', 'woocommerce') . '</strong><br>';
        echo __('Call us at', 'woocommerce') . ' ';
        echo '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '" style="color: #0066cc; font-weight: bold;">';
        echo esc_html($phone);
        echo '</a>';
        echo '</div>';
    }
}

// =============================
// 7. ADD TO CART PAGE
// =============================
add_action('woocommerce_before_cart', 'fwc_phone_on_cart');
function fwc_phone_on_cart() {
    $phone = trim(get_option('woocommerce_store_phone'));
    
    if (!empty($phone)) {
        wc_print_notice(
            sprintf(
                __('Questions about your order? Call us at %s', 'woocommerce'),
                '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '">' . esc_html($phone) . '</a>'
            ),
            'notice'
        );
    }
}

// =============================
// 8. ADD SCHEMA.ORG MARKUP (SEO)
// =============================
add_action('wp_footer', 'fwc_add_phone_schema');
function fwc_add_phone_schema() {
    $phone = trim(get_option('woocommerce_store_phone'));
    
    if (!empty($phone)) {
        // Get store info
        $store_name = get_bloginfo('name');
        $store_address = get_option('woocommerce_store_address');
        $store_city = get_option('woocommerce_store_city');
        $store_postcode = get_option('woocommerce_store_postcode');
        
        ?>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Store",
            "name": "<?php echo esc_js($store_name); ?>",
            "telephone": "<?php echo esc_js($phone); ?>",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "<?php echo esc_js($store_address); ?>",
                "addressLocality": "<?php echo esc_js($store_city); ?>",
                "postalCode": "<?php echo esc_js($store_postcode); ?>"
            }
        }
        </script>
        <?php
    }
}

// =============================
// 9. WIDGET - DISPLAY PHONE IN SIDEBAR
// =============================
class FWC_Store_Phone_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'fwc_store_phone_widget',
            __('Store Phone Number', 'woocommerce'),
            array('description' => __('Display store phone number', 'woocommerce'))
        );
    }
    
    public function widget($args, $instance) {
        $phone = trim(get_option('woocommerce_store_phone'));
        
        if (empty($phone)) {
            return;
        }
        
        $title = !empty($instance['title']) ? $instance['title'] : __('Call Us', 'woocommerce');
        
        echo $args['before_widget'];
        
        if (!empty($title)) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        
        echo '<div class="store-phone-widget">';
        echo '<p><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '" style="font-size: 18px; font-weight: bold; color: #0066cc;">';
        echo esc_html($phone);
        echo '</a></p>';
        echo '</div>';
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Call Us', 'woocommerce');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php _e('Title:', 'woocommerce'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        return $instance;
    }
}

// Register widget
add_action('widgets_init', function() {
    register_widget('FWC_Store_Phone_Widget');
});




// =============================
// 1. ADD EMAIL FIELD TO GENERAL SETTINGS (BELOW POSTCODE)
// =============================
add_filter('woocommerce_general_settings', 'fwc_add_store_email_to_general_settings');
function fwc_add_store_email_to_general_settings($settings) {

    // Find the position after "Store Postcode"
    $insert_position = 0;
    foreach ($settings as $key => $setting) {
        if (isset($setting['id']) && $setting['id'] === 'woocommerce_store_postcode') {
            $insert_position = $key + 1;
            break;
        }
    }

    // Define the email field
    $email_field = array(
        array(
            'title'       => __('Store Email', 'woocommerce'),
            'desc'        => __('The email address customers can use to contact your store.', 'woocommerce'),
            'id'          => 'woocommerce_custom_store_email',
            'type'        => 'email',
            'css'         => 'min-width:300px;',
            'default'     => '',
            'autoload'    => false,
            'desc_tip'    => true,
            'placeholder' => __('store@example.com', 'woocommerce'),
        ),
    );

    // Insert the field
    array_splice($settings, $insert_position, 0, $email_field);

    return $settings;
}


// =============================
// 2. HELPER FUNCTION TO GET STORE EMAIL
// =============================
function fwc_get_store_email() {
    $email = trim(get_option('woocommerce_custom_store_email'));
    return apply_filters('fwc_store_email', $email);
}


// =============================
// 3. ADD EMAIL TO EMAIL FOOTER
// =============================
add_action('woocommerce_email_footer_text', 'fwc_add_store_email_footer');
function fwc_add_store_email_footer($email) {
    $store_email = trim(get_option('woocommerce_custom_store_email'));

    if (!empty($store_email)) {
        echo '<p style="margin-top: 10px; text-align: center;">';
        echo '<strong>' . __('Store Email:', 'woocommerce') . '</strong> ';
        echo '<a href="mailto:' . esc_attr($store_email) . '" style="color: #0066cc; text-decoration: none;">';
        echo esc_html($store_email);
        echo '</a></p>';
    }
}


// =============================
// 4. SHORTCODE: [store_email]
// =============================
add_shortcode('store_email', 'fwc_store_email_shortcode');
function fwc_store_email_shortcode($atts) {

    $atts = shortcode_atts(array(
        'format' => 'link', // link, plain
        'label'  => '',
    ), $atts);

    $email = trim(get_option('woocommerce_store_email'));

    if (empty($email)) {
        return '';
    }

    // Plain text
    if ($atts['format'] === 'plain') {
        return esc_html($email);
    }

    // Default clickable link
    $label = !empty($atts['label']) ? $atts['label'] . ' ' : '';

    return $label . '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
}


// =============================
// 5. SHOW EMAIL ON CHECKOUT PAGE
// =============================
add_action('woocommerce_review_order_before_payment', 'fwc_email_on_checkout');
function fwc_email_on_checkout() {
    $email = trim(get_option('woocommerce_store_email'));

    if (!empty($email)) {
        echo '<div class="checkout-email-notice" style="background: #f0f8ff; padding: 15px; margin: 20px 0; border-left: 4px solid #0066cc;">';
        echo '<strong>' . __('Need Help?', 'woocommerce') . '</strong><br>';
        echo __('Email us at', 'woocommerce') . ' ';
        echo '<a href="mailto:' . esc_attr($email) . '" style="color: #0066cc; font-weight: bold;">';
        echo esc_html($email);
        echo '</a></div>';
    }
}


// =============================
// 6. SHOW EMAIL ON CART PAGE
// =============================
add_action('woocommerce_before_cart', 'fwc_email_on_cart');
function fwc_email_on_cart() {
    $email = trim(get_option('woocommerce_store_email'));

    if (!empty($email)) {
        wc_print_notice(
            sprintf(
                __('Questions about your order? Email us at %s', 'woocommerce'),
                '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>'
            ),
            'notice'
        );
    }
}