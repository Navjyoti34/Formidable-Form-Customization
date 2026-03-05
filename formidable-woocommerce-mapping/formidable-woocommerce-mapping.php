<?php
    /**
     * Plugin Name: Formidable WooCommerce Mapping
     * Description: Adds a custom WooCommerce Product field supporting Simple and Variable products
     * Version: 2.1
     * Author: Navjyoti
     * Installation:
     * 1. Save this file as 'formidable-woocommerce-product-field.php'
     * 2. Upload to wp-content/plugins/formidable-woocommerce-product-field/ folder
     * 3. Activate the plugin
     * 4. In Formidable Forms, add a new field and select "WooCommerce Product"
     * 5. Configure the field in the field settings
     * 6. Select Product either variable or single
     */
    if (!defined('ABSPATH')) exit;
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-fwc-formidable-mapper.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/wc-credit-card-surcharge-survey.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/wc-custom-labels.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-fwc-formidable-simple-product.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/pdf-invoice-generator-customizations.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/formidable-gtm-datalayer.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/woocommerce-additional-settings.php';
        class Formidable_WooCommerce_Product_Field {    
            public function __construct() {
                add_action('frm_after_create_entry', array($this, 'link_entry_to_woo_data'), 10, 2);
                add_filter('frm_available_fields', array($this, 'register_field_type'));
                add_action('frm_field_options_form', array($this, 'field_options'), 10, 3);
                add_filter('frm_setup_new_fields_vars', array($this, 'setup_field'), 9999, 2);
                add_filter('frm_field_value_saved', array($this, 'save_field_value'), 10, 3);
                add_filter('frm_update_field_options', function($options, $field) {
                    if ($field->type === 'woocommerce_product') {
                        $field_id = $field->id;
                        // Save selected product
                        if (isset($_POST['field_options']['selected_products_' . $field_id])) {
                            $selected = sanitize_text_field($_POST['field_options']['selected_products_' . $field_id]);
                            $options['selected_products_' . $field_id] = array((int) $selected);
                        }
                        // Save custom classes
                        if (isset($_POST['field_options']['classes'])) {
                            $options['classes'] = sanitize_text_field($_POST['field_options']['classes']);
                        }
                        // SAVE show_price checkbox
                        $price_key = 'show_price_' . $field_id;
                        $options[$price_key] = isset($_POST['field_options'][$price_key]) ? '1' : '0';
                    }
                    return $options;

                }, 20, 2);

                add_filter('frm_setup_new_fields_vars', function($values, $field) {
                    if ($field->type === 'woocommerce_product') {
                        $custom_classes = trim($field->field_options['classes'] ?? '');
                        if ($custom_classes !== '') {
                            $existing = explode(' ', $values['classes']);
                            $new = explode(' ', $custom_classes);
                            $combined = array_unique(array_merge($existing, $new));
                            $values['classes'] = implode(' ', $combined);
                        }
                    }
                    return $values;
                }, 20, 2);        
                add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
                add_action('frm_display_after_field', array($this, 'add_field_html'), 10, 2);
                add_action('wp_ajax_get_woo_product_price', array($this, 'ajax_get_product_price'));
                add_action('wp_ajax_nopriv_get_woo_product_price', array($this, 'ajax_get_product_price'));
                add_action('wp_ajax_get_product_variations', array($this, 'ajax_get_variations'));
                add_action('wp_ajax_nopriv_get_product_variations', array($this, 'ajax_get_variations'));
                add_action('wp_ajax_get_variation_price', array($this, 'ajax_get_variation_price'));
                add_action('wp_ajax_nopriv_get_variation_price', array($this, 'ajax_get_variation_price'));
            }

            // Register the custom field type
            public function register_field_type($fields) {
                $fields['woocommerce_product'] = array(
                    'name' => 'WooCommerce Product',
                    'icon' => 'frm_icon_font frm-icon-shopping-cart'
                );
                return $fields;
            }
            
            // Add field options in admin
            public function field_options($field, $display, $values) {
                $field_type = $this->get_field_prop($field, 'type', '');
                if ($field_type != 'woocommerce_product') {
                    return;
                }
                $field_id = $this->get_field_prop($field, 'id', '');
                $field_options = $this->get_field_prop($field, 'field_options', []);
                if (!is_array($field_options)) {
                    $field_options = [];
                }
                $field_options['show_price_' . $field_id] =
                isset($_POST['field_options']) && isset($_POST['field_options']['show_price_' . $field_id])
                ? '1'
                : '0';

                $display_type = isset($field_options['display_type_' . $field_id]) ? $field_options['display_type_' . $field_id] : 'select';
                $all_products = get_posts([
                    'post_type'      => 'product',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'tax_query'      => [
                        [
                            'taxonomy' => 'product_type',
                            'field'    => 'slug',
                            'terms'    => ['variable'],
                        ],
                    ],
                ]);

                $selected_products = isset($field_options['selected_products_' . $field_id]) ? (array) $field_options['selected_products_' . $field_id] : [];
            
            ?>
            <tr>
                <td><label><strong>Select a Product</strong></label></td>
                <td>
                    <div style="max-height:300px;overflow-y:auto;border:1px solid #ddd;padding:10px;background:#fff;">
                    <?php 
                
                    if (!empty($all_products) && is_array($all_products)) :
                        foreach ( (array) $all_products as $product_post):
                            $product = wc_get_product($product_post->ID); 
                            if (!$product) continue;
                            $product_type = $product->get_type();
                            $checked = in_array($product->get_id(), $selected_products) ? 'checked="checked"' : '';
                            ?>
                            <label style="display:block;margin-bottom:5px;">
                                <input type="radio"
                                    name="field_options[selected_products_<?php echo esc_attr($field_id); ?>]"
                                    value="<?php echo esc_attr($product->get_id()); ?>"
                                    <?php echo $checked; ?> >
                                <?php echo esc_html($product->get_name()); ?>
                                <span style="color:#777;">(<?php echo ucfirst(esc_html($product_type)); ?>)</span>
                            </label>
                        <?php endforeach;
                    endif;
                    ?>
                    </div>

                    <!-- Checkbox MUST be outside the loop -->
                    <label style="display:block;margin:10px 0;">
                        <input type="checkbox"
                            name="field_options[show_price_<?php echo esc_attr($field_id); ?>]"
                            value="1"
                            <?php checked( $field_options['show_price_' . $field_id] ?? '', '1' ); ?> >
                            Show price on frontend?
                    </label>
                    <p>Select a product to display on frontend.</p>
                </td>
            </tr>

            <?php
            }
            private function get_field_prop($field, $key, $default = null) {
                if (is_array($field)) {
                    return isset($field[$key]) ? $field[$key] : $default;
                } elseif (is_object($field)) {
                    return isset($field->$key) ? $field->$key : $default;
                }
                return $default;
            }        
            public function update_field_options($options, $field) {
                $field_type = $this->get_field_prop($field, 'type', '');
                if ($field_type != 'woocommerce_product') {
                    return $options;
                }

                $field_id = $this->get_field_prop($field, 'id', '');
                if (isset($_POST['field_options']['display_type_' . $field_id])) {
                    $options['display_type_' . $field_id] = sanitize_text_field($_POST['field_options']['display_type_' . $field_id]);
                }
                if (isset($_POST['field_options']) && isset($_POST['field_options']['selected_products_' . $field_id])) {
                    $posted = $_POST['field_options']['selected_products_' . $field_id];
                    if (is_array($posted)) {
                        $options['selected_products_' . $field_id] = array_map('absint', $posted);
                    } else {
                        $options['selected_products_' . $field_id] = array(absint($posted));
                    }
                } else {
                    $options['selected_products_' . $field_id] = array();
                }
                $options['show_price_' . $field_id] = isset($_POST['field_options']['show_price_' . $field_id]) ? 1 : 0;

                return $options;
            }
            public function setup_field($values, $field) {

                $field_type = $this->get_field_prop($field, 'type', '');
                if ($field_type !== 'woocommerce_product') {
                    return $values;
                }

                $field_id      = $this->get_field_prop($field, 'id', '');
                $field_options = $this->get_field_prop($field, 'field_options', []);

                if (!is_array($field_options)) {
                    $field_options = [];
                }

                $key = 'selected_products_' . $field_id;
                $selected_products = isset($field_options[$key])
                    ? array_filter(array_map('absint', (array) $field_options[$key]))
                    : [];
                    
                if (empty($selected_products)) {
                    $values['custom_html'] = '';
                    $values['default_value'] = '';
                    return $values;
                }
                
                $args = [
                    'post_type'      => 'product',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'tax_query'      => [
                        [
                            'taxonomy' => 'product_type',
                            'field'    => 'slug',
                            'terms'    => ['variable'],
                        ],
                    ],
                ];

                if (!empty($selected_products)) {
                    $args['post__in'] = $selected_products;
                }
                
                $products      = get_posts($args);
                $options_html  = "";
                $has_variable  = false;
                $show_price    = !empty($field_options['show_price_' . $field_id]);
                
                foreach ((array) $products as $product) {
                    $product_obj = wc_get_product($product->ID);
                    if (!$product_obj) {
                        continue;
                    }

                    if ($product_obj->get_type() === 'variable') {
                        $has_variable = true;
                        break;
                    }
                }

                /* -------------------------------------------------------
                    VARIABLE PRODUCTS (select dropdown)
                ------------------------------------------------------- */
                if ($has_variable) {
                    $options_html .= '<select class="frm-woocommerce-product-select" '
                        . 'data-frmfield="' . esc_attr($field_id) . '" '
                        . 'name="item_meta[' . esc_attr($field_id) . ']">';
                    
                    // ✅ ADD BLANK DEFAULT OPTION
                    $options_html .= '<option value="">-- Select a Product --</option>';
                    
                    $first_product_id = null;
                    
                    foreach ((array) $products as $product) {
                        $product_obj = wc_get_product($product->ID);
                        if (!$product_obj || $product_obj->get_type() !== 'variable') continue;
                        
                        // Track first product for JS fallback
                        if ($first_product_id === null) {
                            $first_product_id = $product->ID;
                        }
                        
                        $label = esc_html($product_obj->get_name());
                        if ($show_price) {
                            $price_html = strip_tags($product_obj->get_price_html());
                            if ($price_html) {
                                $label .= ' - ' . $price_html;
                            }
                        }
                        
                        // ✅ REMOVED AUTO-SELECT - No product pre-selected
                        $options_html .= '<option value="' . esc_attr($product->ID) . '" '
                            . 'data-product_type="variable">'
                            . $label
                            . '</option>';
                    }
                    $options_html .= '</select>';
                    
                    // ✅ Add hidden input with first product ID for JS
                    if ($first_product_id) {
                        $options_html .= '<input type="hidden" class="frm-woo-first-product" value="' . esc_attr($first_product_id) . '">';
                    }
                }
                
                // Final assignments
                $values['custom_html']                 = $options_html;
                $values['html_attrs']['data-frmfield'] = $field_id;
                $values['classes']                     = trim(($values['classes'] ?? '') . ' frm_woocommerce_product');
                
                // ✅ SET DEFAULT VALUE TO BLANK
                $values['default_value'] = '';
                
                return $values;
            }  
            public function enqueue_scripts() {
                if ( ! function_exists( 'WC' ) ) {
                    return;
                }
                // Enqueue CSS
                wp_enqueue_style(
                    'formidable-woo-product',
                    plugin_dir_url( __FILE__ ) . 'css/formidable-woo-product.css',
                    array(),
                    filemtime( plugin_dir_path( __FILE__ ) . 'css/formidable-woo-product.css' )
                );
                if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
                    return;
                }
                global $post;
                global $wpdb;
                if ( ! $post instanceof WP_Post ) {
                    return;
                }
                $form_ids = array_map( 'intval', $this->get_all_target_form_ids() );
                if ( empty( $form_ids ) ) {
                    return;
                }
                $has_target_form = false;
                foreach ( $form_ids as $form_id ) {
                    if (
                        has_shortcode( $post->post_content, 'formidable' ) &&
                        preg_match( '/id=["\']?' . preg_quote( $form_id, '/' ) . '["\']?/', $post->post_content )
                    ) {
                        $has_target_form = true;
                        break;
                    }
                    if ( fwc_form_has_woocommerce_product_field( $form_id ) ) {
                        $has_target_form = true;
                        break;
                    }
                }
                if ( ! $has_target_form ) {
                    return;
                }
                $blog_id = get_current_blog_id();
                $table   = $wpdb->base_prefix . $blog_id . '_fwc_post_order_mapping';
                $form_category_map = [];

                foreach ( $form_ids as $fid ) {
                    $panel = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT category FROM {$table} WHERE form_id = %d LIMIT 1",
                            $fid
                        ),
                        ARRAY_A
                    );

                    if ( ! empty( $panel['category'] ) ) {
                        $form_category_map[ $fid ] = (int) FrmField::get_id_by_key( $panel['category'] );
                    }
                }

                wp_localize_script(
                    'formidable-woo-product',
                    'FWC_FORM',
                    array(
                        'form_category_map' => $form_category_map,
                    )
                );
                wp_enqueue_script(
                    'formidable-woo-product',
                    plugin_dir_url( __FILE__ ) . 'js/formidable-woo-product.js',
                    array( 'jquery', 'wc-validation-labels' ),
                    filemtime( plugin_dir_path( __FILE__ ) . 'js/formidable-woo-product.js' ),
                    true
                );

                wp_localize_script(
                    'formidable-woo-product',
                    'frmWoo',
                    array(
                        'ajax_url'        => admin_url( 'admin-ajax.php' ),
                        'entry_id'        => (int) get_option( 'frm_latest_entry_id' ),
                        'checkout_url'    => wc_get_checkout_url(),
                        'target_form_ids' => $form_ids,
                        'nonce'           => wp_create_nonce( 'frm_woo_nonce' ),
                    )
                );
        
            }    
            protected function get_all_target_form_ids() {
                global $wpdb;

                $blog_id = get_current_blog_id();
                $table   = $wpdb->base_prefix . $blog_id . '_fwc_post_order_mapping';

                // Safety: ensure table exists
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SHOW TABLES LIKE %s",
                        $table
                    )
                );

                if ( $exists !== $table ) {
                    return [];
                }

                $form_ids = $wpdb->get_col(
                    "SELECT DISTINCT form_id
                    FROM {$table}
                    WHERE form_id IS NOT NULL
                    AND form_id > 0"
                );

                return array_map( 'intval', $form_ids );
            }
            // Add custom HTML for field display
            public function add_field_html($field, $form) {
                if ($field->type != 'woocommerce_product') {
                    return;
                }
                $field_options = $field->field_options ?? [];
                $key = 'selected_products_' . $field->id;

                if (empty($field_options[$key])) {
                    return;
                }
                echo '<div class="frm-woo-product-wrapper" 
                    id="frm-woo-wrapper-'.$field_id.'" 
                    data-field-id="'.$field_id.'">
                    <input type="hidden" 
                        name="item_meta_variation_' . $field_id . '" 
                        id="item_meta_variation_' . $field_id . '" 
                        class="frm-woo-selected-variation">

                    <div class="frm-woo-variations" 
                        id="frm-woo-variations-'.$field_id.'" 
                        style="display:none;"></div>

                    <div class="frm-woo-product-price" 
                        id="frm-woo-price-'.$field_id.'">
                        <span class="price-label">Price: </span>
                        <span class="price-value">Select a product</span>
                    </div>

                </div>';
            }    
            // AJAX handler to get product price and check if variable
            public function ajax_get_product_price() {
                check_ajax_referer('frm_woo_nonce', 'nonce');        
                $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;        
                if (!$product_id) {
                    wp_send_json_error(array('message' => 'Invalid product ID'));
                }        
                $product = wc_get_product($product_id);        
                if (!$product) {
                    wp_send_json_error(array('message' => 'Product not found'));
                }        
                $product_type = $product->get_type();        
                if ($product_type == 'variable') {
                    wp_send_json_success(array(
                        'product_type' => 'variable',
                        'product_name' => $product->get_name(),
                        'price_html' => 'Select options'
                    ));
                } else {
                    $price = $product->get_price();
                    $price_html = $price ? wc_price($price) : 'N/A';
                    
                    wp_send_json_success(array(
                        'product_type' => 'simple',
                        'price' => $price,
                        'price_html' => $price_html,
                        'product_name' => $product->get_name()
                    ));
                }
            }    
            // AJAX handler to get product variations
            public function ajax_get_variations() {
                check_ajax_referer('frm_woo_nonce', 'nonce');        
                $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;        
                if (!$product_id) {
                    wp_send_json_error(array('message' => 'Invalid product ID'));
                }        
                $product = wc_get_product($product_id);        
                if (!$product || $product->get_type() != 'variable') {
                    wp_send_json_error(array('message' => 'Not a variable product'));
                }        
                $variations = $product->get_available_variations();
                $attributes = $product->get_variation_attributes();
                
                $formatted_variations = array();
                if (!is_array($variations)) {
                    $variations = [];
                }
                foreach ($variations as $variation) {
                    $variation_obj = wc_get_product($variation['variation_id']);
                    $formatted_variations[] = array(
                        'variation_id' => $variation['variation_id'],
                        'attributes' => $variation['attributes'],
                        'price' => $variation_obj->get_price(),
                        'price_html' => $variation_obj->get_price_html(),
                        'is_in_stock' => $variation['is_in_stock']
                    );
                }
                
                wp_send_json_success(array(
                    'variations' => $formatted_variations,
                    'attributes' => $attributes
                ));
            }
            public function ajax_get_variation_price() {
                check_ajax_referer('frm_woo_nonce', 'nonce');        
                $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;        
                if (!$variation_id) {
                    wp_send_json_error(array('message' => 'Invalid variation ID'));
                }        
                $variation = wc_get_product($variation_id);        
                if (!$variation) {
                    wp_send_json_error(array('message' => 'Variation not found'));
                }        
                wp_send_json_success(array(
                    'price' => $variation->get_price(),
                    'price_html' => wc_price($variation->get_price())
                ));
            }    
            // Save field value
            public function save_field_value($value, $field, $entry_id) {
                if (is_object($field) && isset($field->type) && $field->type === 'woocommerce_product') {
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    }
                    update_post_meta($entry_id, 'frm_woo_product_id', $value);
                    $variation_field_key = 'item_meta_variation_' . $field->id;
                    if (!empty($_POST[$variation_field_key])) {
                        update_post_meta($entry_id, 'frm_woo_variation_id', absint($_POST[$variation_field_key]));
                    }
                }
                return $value;
            }
            public function link_entry_to_woo_data( $entry_id, $form_id ) {
            if ( isset( $_POST['selected_variation_id'] ) ) {
                FrmEntryMeta::add_entry_meta(
                    $entry_id,
                    'frm_woo_variation_id',
                    null,
                    absint( $_POST['selected_variation_id'] )
                );
            }
            if ( isset( $_POST['selected_variation_parent_id'] ) ) {
                FrmEntryMeta::add_entry_meta(
                    $entry_id,
                    'frm_woo_product_id',
                    null,
                    absint( $_POST['selected_variation_parent_id'] )
                );
            }
        }

    }
    new Formidable_WooCommerce_Product_Field();

    add_action('admin_init', 'fwc_check_dependencies');
    function fwc_check_dependencies() {
        if (!class_exists('FrmForm')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>Formidable WooCommerce Product Field requires Formidable Forms to be installed and activated.</p></div>';
            });
            deactivate_plugins(plugin_basename(__FILE__));
        }
        
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>Formidable WooCommerce Product Field requires WooCommerce to be installed and activated.</p></div>';
            });
            deactivate_plugins(plugin_basename(__FILE__));
        }
    }

    // 2. Add version number for updates
    define('FWC_VERSION', '2.1.1');
    add_action( 'wp_initialize_site', 'fwc_on_new_blog_created' );

    register_activation_hook(__FILE__, 'fwc_plugin_activated');

    function fwc_plugin_activated() {
        if ( ! is_multisite() ) {
            fwc_create_order_mapping_table();
            return;
        }

        $sites = get_sites( [ 'fields' => 'ids' ] );

        foreach ( $sites as $site_id ) {
            switch_to_blog( $site_id );
            fwc_create_order_mapping_table();
            restore_current_blog();
        }
    }

    function fwc_on_new_blog_created( $site ) {
        switch_to_blog( $site->blog_id );
        fwc_create_order_mapping_table();
        restore_current_blog();
    }

    function fwc_create_order_mapping_table() {
        global $wpdb;

        $blog_id = get_current_blog_id();
        $table   = $wpdb->base_prefix . $blog_id . '_fwc_order_mapping';
        $charset = $wpdb->get_charset_collate();

        // Check if table already exists
        if ( $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s", $table
        ) ) === $table ) {
            return; // Table already exists, no need to create
        }

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id BIGINT UNSIGNED NOT NULL,
            form_id BIGINT UNSIGNED NOT NULL,
            order_id_key VARCHAR(50) NOT NULL,
            payment_type_key VARCHAR(100),
            payment_status_key VARCHAR(100),
            cart_status_key VARCHAR(100),
            surcharge_key DECIMAL(10,2),
            order_total_key DECIMAL(10,2),
            PRIMARY KEY (id),
            UNIQUE KEY entry_form (entry_id, form_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    function fwc_fix_missing_payment_status_column() {
        global $wpdb;
        $blog_id = get_current_blog_id();
        $table   = $wpdb->base_prefix . $blog_id . '_fwc_order_mapping';
        $exists = $wpdb->get_var(
            "SHOW COLUMNS FROM {$table} LIKE %s 'payment_status_key'"
        );
        if ( ! $exists ) {
            $wpdb->query(
                "ALTER TABLE {$table}
                ADD COLUMN payment_status_key VARCHAR(100) NULL AFTER payment_type_key"
            );
        }
        $exists_cart = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$table} LIKE %s",
                'cart_status_key'
            )
        );
        if ( ! $exists_cart ) {
            $wpdb->query(
                "ALTER TABLE `{$table}` 
                ADD COLUMN `cart_status_key` VARCHAR(100) NULL AFTER `payment_status_key`"
            );
        }
    }
    add_action('admin_init', 'fwc_fix_missing_payment_status_column');
    //Create a function to handle cases where the form includes a WooCommerce Product field.
    
    function fwc_form_has_woocommerce_product_field( $form_id ) {
        if ( ! class_exists( 'FrmField' ) || ! $form_id ) {
            return false;
        }

        $fields = FrmField::get_all_for_form( $form_id );

        if ( empty( $fields ) || ! is_array( $fields ) ) {
            return false;
        }

        foreach ( $fields as $field ) {
            if ( isset( $field->type ) && $field->type === 'woocommerce_product' || isset( $field->type ) && $field->type === 'woocommerce_simple_product' ) {
                return true;
            }
        }

        return false;
    }
    add_action('wp_ajax_get_variations_by_product', 'get_variations_by_product');
    add_action('wp_ajax_nopriv_get_variations_by_product', 'get_variations_by_product');
    function get_variations_by_product() {
        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Invalid product']);
        }
        if ($product->is_type('simple')) {
            wp_send_json_success([
                'type'     => 'simple',
                'name'  => $product->get_name(),
                'product_id'  => $product->get_id(),
                'price' => html_entity_decode( wp_strip_all_tags( $product->get_price_html() ) ),
            ]);
        }
        if ($product->is_type('variable')) {
            $attributes = $product->get_variation_attributes();
            $available_variations = [];

            $min_price = $product->get_variation_price( 'min', true ); 
            $min_price_html = wc_price( $min_price ); 
            foreach ((array) $product->get_available_variations() as $variation) {
                $available_variations[] = [
                    'variation_parent_id' => $product->get_id(),
                    'variation_id'  => $variation['variation_id'],
                    'price_html'    => html_entity_decode( wp_strip_all_tags( $variation['price_html'] ) ),
                    'display_price' => $variation['display_price'],
                    'attributes'    => $variation['attributes']
                ];
            }
            wp_send_json_success([
                'type'       => 'variable',
                'price'      => $min_price_html,
                'attrs'      => $attributes,
                'variations' => $available_variations
            ]);
        }
        wp_send_json_error(['message' => 'Unknown product type']);
    }
    add_action( 'woocommerce_product_after_variable_attributes', 'variation_settings_fields', 10, 3 );
    function variation_settings_fields( $loop, $variation_data, $variation ) {
        woocommerce_wp_text_input([
            'id'          => '_discount_text[' . $variation->ID . ']',
            'label'       => 'Discount Text',
            'placeholder' => '',
            'value'       => get_post_meta($variation->ID, '_discount_text', true)
        ]);
        woocommerce_wp_text_input([
            'id'          => '_participation[' . $variation->ID . ']',
            'label'       => 'Participation',
            'placeholder' => '',
            'value'       => get_post_meta($variation->ID, '_participation', true)
        ]);
        woocommerce_wp_text_input([
            'id'          => '_survey_results[' . $variation->ID . ']',
            'label'       => 'Survey Results',
            'placeholder' => '',
            'value'       => get_post_meta($variation->ID, '_survey_results', true)
        ]);
        woocommerce_wp_text_input([
            'id'          => '_focus_area[' . $variation->ID . ']',
            'label'       => 'Focus Area',
            'placeholder' => '',
            'value'       => get_post_meta($variation->ID, '_focus_area', true)
        ]);
        woocommerce_wp_text_input([
            'id'          => '_statement_avg[' . $variation->ID . ']',
            'label'       => 'Statement Average',
            'placeholder' => '',
            'value'       => get_post_meta($variation->ID, '_statement_avg', true)
        ]);
        woocommerce_wp_text_input([
            'id'          => '_reporting[' . $variation->ID . ']',
            'label'       => 'Result & Reporting',
            'placeholder' => '',
            'value'       => get_post_meta($variation->ID, '_reporting', true)
        ]);
        woocommerce_wp_text_input([
            'id'          => '_employee_comments[' . $variation->ID . ']',
            'label'       => 'Employee Comments',
            'placeholder' => '',
            'value'       => get_post_meta($variation->ID, '_employee_comments', true)
        ]);
        woocommerce_wp_text_input([
            'id'          => '_unlimited_data[' . $variation->ID . ']',
            'label'       => 'Unlimited Data',
            'placeholder' => '',
            'value'       => get_post_meta($variation->ID, '_unlimited_data', true)
        ]);
    }
    function save_variation_settings_fields( $post_id ) {
        $fields = [
            '_discount_text',
            '_participation',
            '_survey_results',
            '_focus_area',
            '_statement_avg',
            '_reporting',
            '_employee_comments',
            '_unlimited_data',
        ];

        foreach ( (array) $fields as $field ) {
            if ( isset($_POST[$field][$post_id]) ) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field][$post_id]));
            }
        }
    }
    add_action( 'woocommerce_save_product_variation', 'save_variation_settings_fields', 10, 1 );
    add_action('wp_ajax_get_all_variations_emp', 'get_all_variations_emp_fn');
    add_action('wp_ajax_nopriv_get_all_variations_emp', 'get_all_variations_emp_fn');

    function get_all_variations_emp_fn() {
        check_ajax_referer('frm_woo_nonce', 'nonce');

        $product_id = intval($_POST['product_id'] ?? 0);

        if (!$product_id) {
            wp_send_json_error("Missing product_id.");
        }

        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error("Invalid product.");
        }

        // FIXED: Use the correct attribute keys from your system
        $us_key   = 'attribute_us-employees';
        $co_key   = 'attribute_colorado-employees';  // NOTE: double 'ss' - this is how it's stored
        $type_key = 'attribute_type';

        $variations_data = [];
        foreach ($product->get_available_variations() as $var) {
            $vid = $var['variation_id'] ?? 0;
            $atts = $var['attributes'] ?? [];
            
            $us_raw   = isset($atts[$us_key]) ? $atts[$us_key] : '';
            $co_raw   = isset($atts[$co_key]) ? $atts[$co_key] : '';
            $type_raw = isset($atts[$type_key]) ? $atts[$type_key] : '';
            
            if (is_array($us_raw))   $us_raw   = reset($us_raw);
            if (is_array($co_raw))   $co_raw   = reset($co_raw);
            if (is_array($type_raw)) $type_raw = reset($type_raw);
            
            $us_norm   = normalize_attr_val($us_raw);
            $co_norm   = normalize_attr_val($co_raw);
            $type_norm = normalize_attr_val($type_raw);

            list($us_min, $us_max) = parse_range_emp($us_norm);
            list($co_min, $co_max) = parse_range_emp($co_norm);

            $wc_var = wc_get_product($vid);
            
            $variations_data[] = [
                'id'   => $vid,
                'type' => $type_norm,
                'us_min' => $us_min,
                'us_max' => $us_max,
                'co_min' => $co_min,
                'co_max' => $co_max,
                'price' => $wc_var ? $wc_var->get_price() : '',
                'price_html' => $wc_var ? $wc_var->get_price_html() : '',
                'description'       => $wc_var ? $wc_var->get_description() : '',
                'discount_text'     => get_post_meta($vid, '_discount_text', true),
                'participation'     => get_post_meta($vid, '_participation', true),
                'survey_results'    => get_post_meta($vid, '_survey_results', true),
                'focus_area'        => get_post_meta($vid, '_focus_area', true),
                'statement_avg'     => get_post_meta($vid, '_statement_avg', true),
                'reporting'         => get_post_meta($vid, '_reporting', true),
                'employee_comments' => get_post_meta($vid, '_employee_comments', true),
                'unlimited_data'    => get_post_meta($vid, '_unlimited_data', true),
            ];
        }
        
        wp_send_json_success($variations_data);
    }
    /**
     * Normalize attribute value (robust):
     * - convert en-dash/em-dash/non-breaking dashes to normal hyphen
     * - strip spaces, underscores, "to"
     * - lowercase
     */
    function normalize_attr_val($value) {
        $val = trim((string)$value);
        $val = preg_replace('/[\x{2012}\x{2013}\x{2014}\x{2015}\x{2212}]/u', '-', $val);
        $val = str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\xAF"], '', $val);
        $val = preg_replace('/\s+/', '', $val);
        $val = str_replace('_', '-', $val);
        $val = str_ireplace(['to', '–', '—'], '-', $val);
        $val = preg_replace('/-+/', '-', $val);
        $val = trim($val, "- ");
        return strtolower($val);
    }

    /**
     * Parse a normalized range string into [min,max]
     */
    function parse_range_emp($range_str) {
        $r = trim((string)$range_str);

        if ($r === '') {
            return [0, PHP_INT_MAX];
        }
        // Handle "2500+"
        if (preg_match('/^(\d+)\+$/', $r, $m)) {
            return [(int)$m[1], PHP_INT_MAX];
        }
        // Handle "200-499"
        if (preg_match('/^(\d+)-(\d+)$/', $r, $m)) {
            $min = (int)$m[1];
            $max = (int)$m[2];
            return ($min <= $max) ? [$min, $max] : [$max, $min];
        }
        // If single number
        if (ctype_digit($r)) {
            $v = (int)$r;
            return [$v, $v];
        }
        // Fallback
        return [0, PHP_INT_MAX];
    }
    /**
     * Add a checkbox to the product’s General tab to disable access to the single product page.
    */
    add_action('woocommerce_product_options_general_product_data', 'add_disable_single_page_checkbox');
    function add_disable_single_page_checkbox() {
        echo '<div class="options_group show_if_simple show_if_variable">';
        woocommerce_wp_checkbox([
            'id'            => '_disable_single_product_page',
            'label'         => __('Disable Single Product Page?', 'textdomain'),
            'description'   => __('If checked, customers cannot open the product detail page.', 'textdomain'),
        ]);
        echo '</div>';
    }
    /**
     * Save checkbox value
     */
    add_action('woocommerce_admin_process_product_object', function ($product) {
        $value = isset($_POST['_disable_single_product_page']) ? 'yes' : 'no';
        $product->update_meta_data('_disable_single_product_page', $value);
    });
    /**
     * Disable single product page if meta checkbox is enabled from shop, category and product page
     */
    add_action('template_redirect', function () {
        if (!is_product()) {
            return;
        }
        global $post;
        $disabled = get_post_meta($post->ID, '_disable_single_product_page', true);
        if ($disabled === 'yes') {
            wp_safe_redirect(wc_get_page_permalink('home'));
            exit;
        }
    });
    //Hide checked product from shop page and search result
    add_action('woocommerce_product_query', function ($q) {        
        if (
            !is_shop() &&
            !is_product_category() &&
            !is_product_tag() &&
            !is_search()
        ) {
            return;
        }
        $meta_query = $q->get('meta_query') ?: [];
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => '_disable_single_product_page',
                'value'   => 'yes',
                'compare' => '!='
            ],
            [
                'key'     => '_disable_single_product_page',
                'compare' => 'NOT EXISTS'
            ]
        ];

        $q->set('meta_query', $meta_query);
    });
    remove_action('frm_after_create_entry', 'store_formidable_entry_id', 30, 2);
    remove_filter( 'frm_continue_to_create', 'fwc_prevent_entry_creation_on_submit', 10, 2 );

    function fwc_prevent_entry_creation_on_submit( $continue, $form_id ) {
        if ( fwc_form_has_order_mapping( $form_id ) ) {
            return false; 
        }

        return $continue;
    }

    function fwc_form_has_order_mapping( $form_id ) {
        global $wpdb;
        if ( ! $form_id ) {
            return false;
        }
        $blog_id = get_current_blog_id();
        $table   = $wpdb->base_prefix . $blog_id . '_fwc_post_order_mapping';
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM {$table} WHERE form_id = %d LIMIT 1",
                $form_id
            )
        );
        return ! empty( $exists );
    }
    add_action( 'wp_ajax_add_variation_to_cart_without_entry', 'ajax_add_variation_to_cart_no_entry' );
    add_action( 'wp_ajax_nopriv_add_variation_to_cart_without_entry', 'ajax_add_variation_to_cart_no_entry' );

    function ajax_add_variation_to_cart_no_entry() {
        global $wpdb;
        try {
            /* — Security — */
            if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'frm_woo_nonce' ) ) {
                wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
            }
            if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
                wp_send_json_error( [ 'message' => 'WooCommerce cart not available' ] );
            }

            /* — Session Init for guests — */
            if ( ! is_user_logged_in() && ! WC()->session->has_session() ) {
                WC()->session->set_customer_session_cookie( true );
            }

            /* — Form ID & Mapping — */
            $form_id = absint( $_POST['form_id'] ?? 0 );
            if ( ! $form_id ) wp_send_json_error( [ 'message' => 'Missing form ID' ] );

            $table = $wpdb->base_prefix . get_current_blog_id() . '_fwc_post_order_mapping';
            $panel = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE form_id = %d LIMIT 1", $form_id ), ARRAY_A );

            /* — Parse Form Data — */
            $form_data = [];
            if ( ! empty( $_POST['form_data'] ) ) {
                $raw       = $_POST['form_data'];
                $form_data = is_string( $raw ) ? json_decode( stripslashes( $raw ), true ) : $raw;
            }

            /* — Build item_meta — */
            $item_meta = [];
            foreach ( $form_data as $key => $value ) {
                if ( preg_match( '/item_meta\[(\d+)\]/', $key, $m ) ) {
                    $item_meta[ absint( $m[1] ) ] = is_array( $value ) ? maybe_serialize( $value ) : sanitize_text_field( $value );
                } elseif ( preg_match( '/^field_(\d+)$/', $key, $m ) ) {
                    $item_meta[ absint( $m[1] ) ] = is_array( $value ) ? maybe_serialize( $value ) : sanitize_text_field( $value );
                }
            }

            /* — Extract Billing from Form — */
            $form_billing = [];
            $billing_map  = [
                'first_name' => $panel['first_name'] ?? '',
                'last_name'  => $panel['last_name']  ?? '',
                'email'      => $panel['email']       ?? '',
                'phone'      => $panel['phone']       ?? '',
            ];

            foreach ( $billing_map as $billing_key => $frm_field_key ) {
                if ( empty( $frm_field_key ) ) continue;

                $field_id = FrmField::get_id_by_key( $frm_field_key );
                if ( ! $field_id ) continue;

                $value = null;
                foreach ( [ "item_meta[{$field_id}]", "item_meta_{$field_id}", "field_{$field_id}", (string) $field_id ] as $key ) {
                    if ( isset( $form_data[ $key ] ) ) {
                        $value = $form_data[ $key ];
                        break;
                    }
                }

                if ( $value !== null && $value !== '' ) {
                    $form_billing[ $billing_key ] = sanitize_text_field( $value );
                }
            }



            /* — Save Billing to WC Customer Session — */
            if ( function_exists( 'WC' ) && WC()->customer ) {
                if ( isset( $form_billing['first_name'] ) ) WC()->customer->set_billing_first_name( $form_billing['first_name'] );
                if ( isset( $form_billing['last_name'] ) )  WC()->customer->set_billing_last_name( $form_billing['last_name'] );
                if ( isset( $form_billing['email'] ) )      WC()->customer->set_billing_email( $form_billing['email'] );
                if ( isset( $form_billing['phone'] ) )      WC()->customer->set_billing_phone( $form_billing['phone'] );
                WC()->customer->save();
            }

            /* — Collect Products — */
            $products_to_add = [];

            if ( ! empty( $_POST['product_id'] ) && ! empty( $_POST['variation_id'] ) ) {
                $products_to_add[] = [ 'product_id' => absint( $_POST['product_id'] ), 'variation_id' => absint( $_POST['variation_id'] ), 'price' => floatval( $_POST['custom_price'] ?? 0 ) ];
            }
            if ( ! empty( $_POST['simple_product_id'] ) ) {
                $products_to_add[] = [ 'product_id' => absint( $_POST['simple_product_id'] ), 'variation_id' => 0, 'price' => floatval( $_POST['simple_product_price'] ?? 0 ) ];
            }

            $ids_raw    = $_POST['simple_product_list_ids'] ?? '';
            $prices_raw = $_POST['simple_product_list_prices'] ?? '';
            $ids        = is_array( $ids_raw ) ? $ids_raw : explode( ',', $ids_raw );
            $prices     = is_array( $prices_raw ) ? $prices_raw : explode( ',', $prices_raw );
            foreach ( $ids as $i => $pid ) {
                $pid = absint( $pid );
                if ( ! $pid ) continue;
                $products_to_add[] = [ 'product_id' => $pid, 'variation_id' => 0, 'price' => floatval( $prices[ $i ] ?? 0 ) ];
            }

            $bundle_ids_raw    = $_POST['language_bundle_ids'] ?? '';
            $bundle_prices_raw = $_POST['language_bundle_prices'] ?? '';
            $bundle_ids        = is_array( $bundle_ids_raw ) ? $bundle_ids_raw : explode( ',', $bundle_ids_raw );
            $bundle_prices     = is_array( $bundle_prices_raw ) ? $bundle_prices_raw : explode( ',', $bundle_prices_raw );
            foreach ( $bundle_ids as $i => $pid ) {
                $pid = absint( $pid );
                if ( ! $pid ) continue;
                $products_to_add[] = [ 'product_id' => $pid, 'variation_id' => 0, 'price' => floatval( $bundle_prices[ $i ] ?? 0 ) ];
            }

            $has_products = ! empty( $products_to_add );

            /* — Create Formidable Entry — */
            $entry_id = 0;
            $blog_id_t = get_current_blog_id();
            $table_t   = $wpdb->base_prefix . $blog_id_t . '_fwc_post_order_mapping';
            $all_fields_t = FrmField::get_all_for_form( $form_id );
            $currency_symbol_t = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
            $product_price_map = [];
            foreach ( $products_to_add as $p ) {
                if ( $p['variation_id'] ) {
                    $product_price_map[ $p['variation_id'] ] = $p['price'];
                    $product_price_map[ $p['product_id'] ]   = $p['price'];
                } else {
                    $product_price_map[ $p['product_id'] ] = $p['price'];
                }
            }

            foreach ( $all_fields_t as $field ) {
                if ( ! in_array( $field->type, [ 'woocommerce_product', 'woocommerce_simple_product', 'product', 'data' ] ) ) {
                    continue;
                }
                if ( ! isset( $item_meta[ $field->id ] ) ) continue;

                $stored_val = $item_meta[ $field->id ];
                if ( strpos( (string) $stored_val, ' - ' ) !== false ) continue;

                $raw_product_id = absint( $stored_val );
                if ( ! $raw_product_id ) continue;
                $matched_product   = null;
                $matched_price     = 0;
                $matched_variation = null;

                foreach ( $products_to_add as $p ) {
                    if ( $p['product_id'] == $raw_product_id || $p['variation_id'] == $raw_product_id ) {
                        $matched_product   = wc_get_product( $p['product_id'] );
                        $matched_price     = $p['price'];
                        $matched_variation = $p['variation_id'] ? wc_get_product( $p['variation_id'] ) : null;
                        break;
                    }
                }

                if ( ! $matched_product ) continue;
                $sku = '';
                if ( $matched_variation ) {
                    $sku = $matched_variation->get_sku();
                }
                if ( empty( $sku ) ) {
                    $sku = $matched_product->get_sku();
                }

                if ( empty( $sku ) || ! $matched_price ) continue;

                $formatted = sprintf(
                    '%s - %s%s',
                    $sku,
                    $currency_symbol_t,
                    number_format( (float) $matched_price, 0 )
                );

                $item_meta[ $field->id ] = $formatted;
                error_log( "FWC: Pre-formatted product field #{$field->id} = {$formatted} at entry creation" );
            }
            if ( class_exists( 'FrmEntry' ) && ! empty( $item_meta ) ) {
                if ( ! empty( $panel['cart_status'] ) ) {
                    $cart_status_field_id = FrmField::get_id_by_key( $panel['cart_status'] );
                    if ( $cart_status_field_id ) {
                        $item_meta[ $cart_status_field_id ] = $has_products ? 'Cart' : '';
                    }
                }

                $entry_id = FrmEntry::create( [
                    'form_id'   => $form_id,
                    'item_meta' => $item_meta,
                    'is_draft'  => 0,
                ] );

                if ( $entry_id ) {
                    error_log( 'FWC: Entry #' . $entry_id . ' created on form submit with cart_status = ' . ( $has_products ? 'Cart' : 'Normal Entry' ) );
                }
            }

            /* — Early Return if No Products — */
            if ( ! $has_products ) {
                wp_send_json_success( [
                    'entry_id'    => $entry_id,
                    'cart_count'  => 0,
                    'total'       => 0,
                    'message'     => 'Entry created successfully',
                    'no_products' => true,
                ] );
            }

            /* — Store in WC Session — */
            WC()->session->set( 'fwc_pending_form_data', [
                'form_id'   => $form_id,
                'entry_id'  => $entry_id,
                'item_meta' => $item_meta,
                'form_data' => $form_data,
                'billing'   => $form_billing,
            ] );

            /* — Clear Cart if Requested — */
            if ( ! empty( $_POST['clear_cart'] ) ) WC()->cart->empty_cart();

            /* — Add to Cart — */
            foreach ( $products_to_add as $item ) {
                WC()->cart->add_to_cart( $item['product_id'], 1, $item['variation_id'], [], [
                    'fwc_form_id'  => $form_id,
                    'fwc_entry_id' => $entry_id,
                    'custom_price' => $item['price'],
                    'fwc_unique'   => uniqid( 'fwc_', true ),
                ] );
            }

            WC()->cart->calculate_totals();
            wp_send_json_success( [
                'entry_id'   => $entry_id,
                'cart_count' => WC()->cart->get_cart_contents_count(),
                'total'      => WC()->cart->get_cart_total(),
                'message'    => 'Entry created with cart status',
            ] );

        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    add_filter( 'woocommerce_add_cart_item_data', 'fwc_attach_entry_to_cart', 10, 3 );
    function fwc_attach_entry_to_cart( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $_POST['fwc_entry_id'] ) ) {
            $cart_item_data['fwc_entry_id'] = absint($_POST['fwc_entry_id']);
        }
        if ( isset( $_POST['form_id'] ) ) {
            $cart_item_data['fwc_form_id'] = absint($_POST['form_id']);
        }
        return $cart_item_data;
    }

    // =============================
    // 4. CREATE ENTRY FUNCTION
    // =============================
    function fwc_create_formidable_entry( $form_id, $payload, $order_id ) {
        if ( ! class_exists( 'FrmEntry' ) || empty( $payload ) ) {
            return;
        }
        $entry_id = FrmEntry::create( [
            'form_id'  => absint( $form_id ),
            'item_meta'=> $payload,
        ] );

        if ( $entry_id ) {
            error_log(
            'FWC: Formidable entry CREATED. Entry ID ' . $entry_id . ' for order ' . $order_id
            );
        }
    }

    add_filter( 'woocommerce_add_cart_item_data', 'fwc_attach_payload_to_cart', 10, 3 );
    function fwc_attach_payload_to_cart( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $_POST['form_id'] ) ) {
            $cart_item_data['fwc_payload'] = json_encode( $_POST );
            $cart_item_data['fwc_form_id'] = absint( $_POST['form_id'] );
        }
        return $cart_item_data;
    }
    /**
     * 2. Transfer payload from Cart Item to Order Item Meta
    */
    add_action('woocommerce_checkout_create_order_line_item', 'fwc_transfer_entry_id_to_order_item', 10, 4);
        function fwc_transfer_entry_id_to_order_item($item, $cart_item_key, $values, $order) {
            if (!empty($values['fwc_entry_id'])) {
                $item->add_meta_data('_fwc_entry_id', absint($values['fwc_entry_id']), true);
            }
            if (!empty($values['fwc_form_id'])) {
                $item->add_meta_data('_fwc_form_id', absint($values['fwc_form_id']), true);
            }
    }
    add_action(
        'woocommerce_checkout_create_order_line_item',
        function ( $item, $cart_item_key, $values ) {

            if ( isset( $values['fwc_payload'] ) ) {
                $item->add_meta_data( '_fwc_form_payload', $values['fwc_payload'] );
            }

            if ( isset( $values['fwc_form_id'] ) ) {
                $item->add_meta_data( '_fwc_form_id', $values['fwc_form_id'] );
            }
        },
        20,
        3
    );

    function fwc_add_entry_to_order_items( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['fwc_entry_id'] ) ) {
            $item->add_meta_data( '_fwc_entry_id', $values['fwc_entry_id'] );
        }
        if ( isset( $values['fwc_form_id'] ) ) {
            $item->add_meta_data( '_fwc_form_id', $values['fwc_form_id'] );
        }
    }
    add_action('woocommerce_before_calculate_totals', 'fwc_apply_custom_prices', 10, 1);
    function fwc_apply_custom_prices($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['frm_custom_price']) && $cart_item['frm_custom_price'] > 0) {
                $cart_item['data']->set_price(floatval($cart_item['frm_custom_price']));
            }
        }
    }
    function fwc_sync_order_with_entries( WC_Order $order ) {
            $entry_id = $order->get_meta( 'fwc_entry_id' );
            if ( ! $entry_id ) {
                return;
            }
            update_post_meta( $entry_id, 'fwc_order_id', $order->get_id() );
            $order->add_order_note( "Linked to Formidable Entry: #{$entry_id}" );
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'frm_items',
                [ 'is_draft' => 0 ],
                [ 'id' => $entry_id ],
                [ '%d' ],
                [ '%d' ]
            );
    }

    function fwc_create_formidable_entry_after_order( $form_id, array $payload, $order_id ) {
        global $wpdb;
        if ( ! class_exists('FrmEntry') || ! class_exists('FrmEntryMeta') ) {
            return false;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }

        // Decode form data
        $form_data = $payload['form_data'] ?? [];
        if ( is_string( $form_data ) ) {
            $form_data = json_decode( stripslashes( $form_data ), true );
        }
        if ( ! is_array( $form_data ) || empty( $form_data ) ) {
            return false;
        }

        // Prepare item_meta from form data
        $item_meta = [];
        foreach ( $form_data as $key => $value ) {
            if ( preg_match( '/item_meta\[(\d+)\]/', $key, $m ) ) {
                $item_meta[ absint( $m[1] ) ] = is_array( $value ) ? maybe_serialize( $value ) : $value;
            } elseif ( preg_match( '/^field_(\d+)$/', $key, $m ) ) {
                $item_meta[ absint( $m[1] ) ] = $value;
            }
        }
        if ( empty( $item_meta ) ) {
            return false;
        }
        $fields = FrmField::get_all_for_form($form_id);
        $currency_symbol = html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8');
        
        // Get all order items
        $order_items = $order->get_items();
        
        if (!empty($order_items)) {
            $updated_fields = [];
            
            foreach ($order_items as $item_id => $order_item) {
                $product = $order_item->get_product();
                
                if (!$product) {
                    continue;
                }
                
                $product_id = $product->get_id();
                $product_type = $product->get_type();
                foreach ($fields as $field) {
                    if (!in_array($field->type, ['woocommerce_product', 'woocommerce_simple_product', 'product', 'data'])) {
                        continue;
                    }
                    if (in_array($field->id, $updated_fields)) {
                        continue;
                    }
                    $field_product_id = isset($item_meta[$field->id]) ? $item_meta[$field->id] : null;
                    $parent_id = 0;
                    if ($product_type === 'variation') {
                        $parent_id = $product->get_parent_id();
                    }
                    $is_match = false;
                    if ($field_product_id == $product_id) {
                        $is_match = true;
                    } elseif ($parent_id > 0 && $field_product_id == $parent_id) {
                        $is_match = true;
                    }
                    
                    if (!$is_match) {
                        continue;
                    }
                    
                    $sku = '';
                    $price = '';
                    
                    // Handle VARIATION products
                    if ($product_type === 'variation') {                       
                        $sku = $product->get_sku();
                        if (empty($sku) && $parent_id > 0) {
                            $parent_product = wc_get_product($parent_id);
                            if ($parent_product) {
                                $sku = $parent_product->get_sku();
                            }
                        }
                        
                        $price = $order_item->get_total();
                    }
                    // Handle SIMPLE products
                    elseif ($product_type === 'simple') {                        
                        $sku = $product->get_sku();                        
                        $price = $order_item->get_total();
                    }
                    // Handle other product types
                    else {                        
                        $sku = $product->get_sku();
                        $price = $order_item->get_total();
                    }
                    
                    // Format the value with correct SKU (e.g., "SKU: CA15 - $15" or "SKU: BA15 - $15")
                    $formatted_value = sprintf(
                        '%s - %s%s',
                        !empty($sku) ? $sku : 'N/A',
                        $currency_symbol,
                        number_format((float)$price, 0)
                    );
                    
                    // Update the item_meta with correct SKU
                    $item_meta[$field->id] = $formatted_value;
                    $updated_fields[] = $field->id;
                    break;
                }
            }
            
            if (!empty($updated_fields)) {
                error_log('FWC: Successfully updated ' . count($updated_fields) . ' product fields with SKU data');
            } else {
                error_log('FWC: WARNING - No product fields were updated');
            }
        }
        // ========================================
        // END OF SKU UPDATE
        // ========================================

        // Create Formidable entry with corrected data
        $entry_id = FrmEntry::create([
            'form_id'   => absint( $form_id ),
            'item_meta' => $item_meta,
            'is_draft'  => 0,
        ]);
        if ( ! $entry_id ) {
            return false;
        }
        $blog_id = get_current_blog_id();
        $table   = $wpdb->base_prefix . $blog_id . '_fwc_post_order_mapping';
        $panel   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE form_id = %d LIMIT 1", $form_id),
            ARRAY_A
        );

        if ( ! $panel ) {
            return $entry_id; // No mapping panel, still return entry
        }

        // Prepare dynamic field mapping
        $mapped_fields = [
            'order_id'       => $panel['order_id'] ?? '',
            'order_total'    => $panel['order_total'] ?? '',
            'payment_method' => $panel['payment_method'] ?? '',
            'payment_status' => $panel['payment_status'] ?? '',
            'category'       => $panel['category'] ?? '',
            'surcharge'      => $panel['surcharge'] ?? '',
            'first_name'     => $panel['first_name'] ?? '',
            'last_name'      => $panel['last_name'] ?? '',
            'email'          => $panel['email'] ?? '',
            'phone'          => $panel['phone'] ?? '',
        ];

        $fields_to_update = [];
        foreach ($mapped_fields as $key => $field_key) {
            $field_id = FrmField::get_id_by_key($field_key);
            if (!$field_id) continue;

            switch ($key) {
                case 'order_id':
                    $fields_to_update[$field_id] = $order_id;
                    break;
                case 'order_total':
                    $fields_to_update[$field_id] = $order->get_total();
                    break;
                case 'payment_method':
                    $fields_to_update[$field_id] = $order->get_payment_method_title();
                    break;
                case 'payment_status':
                    $fields_to_update[$field_id] = wc_get_order_status_name( $order->get_status() );
                    break;
                case 'category':
                    $category_value = '';

                    foreach ( $order->get_items() as $item ) {
                        $product = $item->get_product();
                        if ( ! $product || ! $product->is_type('variation') ) {
                            continue;
                        }
                        $type_attr_value = $product->get_attribute('Type'); 
                        if ( $type_attr_value ) {
                            $variation_price = $product->get_price();
                            $currency_symbol_cat = html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8');
                            $formatted_price = $currency_symbol_cat . number_format((float)$variation_price, 0);                            
                            $category_value = "Select {$type_attr_value}: {$formatted_price}";
                            break; 
                        }
                    }

                    $fields_to_update[$field_id] = sanitize_text_field( $category_value );
                    break;

                case 'surcharge':
                    $surcharge = 0.0;
                    foreach ($order->get_fees() as $fee) {
                        $surcharge += (float) $fee->get_total();
                    }
                    $fields_to_update[$field_id] = $surcharge;
                    break;
                case 'first_name':
                    $fields_to_update[$field_id] = $order->get_billing_first_name();
                    break;
                case 'last_name':
                    $fields_to_update[$field_id] = $order->get_billing_last_name();
                    break;
                case 'email':
                    $fields_to_update[$field_id] = $order->get_billing_email();
                    break;
                case 'phone':
                    $fields_to_update[$field_id] = $order->get_billing_phone();
                    break;
            }
        }

        // Insert/update Formidable item metas
        $frm_table = $wpdb->prefix . 'frm_item_metas';
        foreach ($fields_to_update as $field_id => $value) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $frm_table WHERE item_id = %d AND field_id = %d",
                $entry_id,
                $field_id
            ));

            if ($existing) {
                $wpdb->update(
                    $frm_table,
                    ['meta_value' => $value],
                    ['id' => $existing],
                    ['%s'],
                    ['%d']
                );
            } else {
                $wpdb->insert(
                    $frm_table,
                    [
                        'item_id'    => $entry_id,
                        'field_id'   => $field_id,
                        'meta_value' => $value,
                        'created_at' => current_time('mysql'),
                    ],
                    ['%d','%d','%s','%s']
                );
            }
        }

        // Update custom mapping table
        $custom_table = $wpdb->base_prefix . $blog_id . '_fwc_order_mapping';
        $surcharge = 0.0;
        foreach ($order->get_fees() as $fee) {
            $surcharge += (float) $fee->get_total();
        }
        
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$custom_table}
                    (entry_id, form_id, order_id_key, payment_type_key, payment_status_key, surcharge_key, order_total_key)
                VALUES (%d, %d, %s, %s, %s, %f, %f)
                ON DUPLICATE KEY UPDATE
                    order_id_key       = VALUES(order_id_key),
                    payment_type_key   = VALUES(payment_type_key),
                    payment_status_key = VALUES(payment_status_key),
                    surcharge_key      = VALUES(surcharge_key),
                    order_total_key    = VALUES(order_total_key)",
                $entry_id,
                $form_id,
                $order_id,                                
                $order->get_payment_method_title(),       
                wc_get_order_status_name($order->get_status()), 
                floatval($surcharge),                     
                floatval($order->get_total())             
            )
        );

        // Link entry to order
        update_post_meta($order_id, '_formidable_entry_id', $entry_id);
        update_post_meta($entry_id, 'fwc_order_id', $order_id);

        return $entry_id;
    }

    add_action('woocommerce_order_status_processing', 'fwc_create_entry_after_order', 10, 1);
    add_action('woocommerce_order_status_completed', 'fwc_create_entry_after_order', 10, 1);
    function fwc_create_entry_after_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if we already created an entry for this order
        if ($order->get_meta('_fwc_entry_created')) {
            error_log("FWC: Entry already created for order #{$order_id}");
            return;
        }

        // Get the pending form data from session or order meta
        $pending_data = $order->get_meta('_fwc_pending_form_data');
        
        if (!$pending_data) {
            error_log("FWC: No pending form data found for order #{$order_id}");
            return;
        }

        $form_id = $pending_data['form_id'] ?? 0;
        $item_meta = $pending_data['item_meta'] ?? [];
        
        if (!$form_id || empty($item_meta)) {
            error_log("FWC: Invalid form data for order #{$order_id}");
            return;
        }

        // Create the Formidable entry now
        if (class_exists('FrmEntry')) {
            $entry_id = FrmEntry::create([
                'form_id'   => $form_id,
                'item_meta' => $item_meta,
                'is_draft'  => 0,
            ]);

            if ($entry_id) {
                error_log("FWC: Created entry #{$entry_id} for order #{$order_id}");
                
                // Update order meta to link entry
                $order->update_meta_data('_fwc_entry_id', $entry_id);
                $order->update_meta_data('_fwc_entry_created', true);
                $order->save();
                
                // Add order note
                $order->add_order_note(sprintf('Formidable entry #%d created after order completion.', $entry_id));
            } else {
                error_log("FWC ERROR: Failed to create entry for order #{$order_id}");
            }
        }
    }

    function fwc_get_abandoned_cart_threshold() {
        $ac_settings = get_option( 'woocommerce_ac_settings' );
        if ( ! empty( $ac_settings['cart_abandoned_cut_off_time'] ) ) {
            return absint( $ac_settings['cart_abandoned_cut_off_time'] ) * 60;
        }
        $cart_abandoned_time = get_option( 'ac_lite_cart_abandoned_time' );
        if ( $cart_abandoned_time && is_numeric( $cart_abandoned_time ) ) {
            return absint( $cart_abandoned_time ) * 60;
        }
        return 600; 
    }

    add_filter('cron_schedules', 'fwc_add_custom_schedule');
    function fwc_add_custom_schedule($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display'  => __('Every Minute')
        );
        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display'  => __('Every 5 Minutes')
        );
        return $schedules;
    }
    // Schedule to run every minute
    if (!wp_next_scheduled('fwc_check_abandoned_carts')) {
        wp_schedule_event(time(), 'every_minute', 'fwc_check_abandoned_carts');
    }
    add_action( 'fwc_check_abandoned_carts', 'fwc_mark_abandoned_carts' );
    function fwc_mark_abandoned_carts() {
        global $wpdb;

        $abandoned_threshold = fwc_get_abandoned_cart_threshold();
        $current_time        = current_time( 'timestamp' );
        $cutoff_time = time() - $abandoned_threshold;
        $blog_id             = get_current_blog_id();

        error_log( "FWC Cron: Checking for abandoned carts older than " . date( 'Y-m-d H:i:s', $cutoff_time ) . " (threshold: {$abandoned_threshold}s)" );

        $frm_items_table = $wpdb->prefix . 'frm_items';
        $frm_meta_table  = $wpdb->prefix . 'frm_item_metas';
        $map_table       = $wpdb->base_prefix . $blog_id . '_fwc_post_order_mapping';

        // Get all form IDs that have a cart_status field mapped
        $mapped_forms = $wpdb->get_results(
            "SELECT form_id, cart_status FROM {$map_table} WHERE cart_status != '' AND cart_status IS NOT NULL",
            ARRAY_A
        );

        if ( empty( $mapped_forms ) ) {
            error_log( "FWC Cron: No mapped forms found" );
            return;
        }

        foreach ( $mapped_forms as $mapping ) {
            $form_id   = absint( $mapping['form_id'] );
            $field_key = $mapping['cart_status'];

            $cart_status_field_id = FrmField::get_id_by_key( $field_key );
            if ( ! $cart_status_field_id ) {
                error_log( "FWC Cron: No field found for cart_status key: {$field_key}" );
                continue;
            }
            $cutoff_datetime = gmdate( 'Y-m-d H:i:s', time() - $abandoned_threshold );
            // Find entries for this form where cart_status = 'Cart' and older than threshold
            $abandoned_entries = $wpdb->get_results( $wpdb->prepare(
                "SELECT fi.id as entry_id
                FROM {$frm_items_table} fi
                INNER JOIN {$frm_meta_table} fim 
                    ON fim.item_id = fi.id 
                    AND fim.field_id = %d
                    AND LOWER(fim.meta_value) = 'cart'
                WHERE fi.form_id = %d
                AND fi.created_at < %s",  // ← direct datetime string comparison
                $cart_status_field_id,
                $form_id,
                $cutoff_datetime
            ) );

            if ( empty( $abandoned_entries ) ) {
                error_log( "FWC Cron: No abandoned carts for form #{$form_id}" );
                continue;
            }

            error_log( "FWC Cron: Found " . count( $abandoned_entries ) . " abandoned entries for form #{$form_id}" );

            foreach ( $abandoned_entries as $row ) {
                $entry_id = absint( $row->entry_id );

                // Skip if this entry is already linked to a completed order
                $linked_order_id = get_post_meta( $entry_id, '_fwc_order_id', true );
                if ( $linked_order_id ) {
                    $linked_order = wc_get_order( $linked_order_id );
                    if ( $linked_order && in_array( $linked_order->get_status(), [ 'processing', 'completed' ], true ) ) {
                        error_log( "FWC Cron: Entry #{$entry_id} skipped - linked to completed order #{$linked_order_id}" );
                        continue;
                    }
                }

                $existing = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$frm_meta_table} WHERE item_id = %d AND field_id = %d",
                    $entry_id,
                    $cart_status_field_id
                ) );

                if ( $existing ) {
                    $wpdb->update(
                        $frm_meta_table,
                        [ 'meta_value' => 'Abandoned' ],
                        [ 'id'         => $existing ],
                        [ '%s' ],
                        [ '%d' ]
                    );
                } else {
                    $wpdb->insert(
                        $frm_meta_table,
                        [
                            'item_id'    => $entry_id,
                            'field_id'   => $cart_status_field_id,
                            'meta_value' => 'Abandoned',
                            'created_at' => current_time( 'mysql' ),
                        ],
                        [ '%d', '%d', '%s', '%s' ]
                    );
                }

                error_log( "FWC Cron: Entry #{$entry_id} marked as Abandoned" );
            }
        }
    }
    add_action( 'wp', 'fwc_check_current_session_abandoned_cart' );
    function fwc_check_current_session_abandoned_cart() {
        if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) return;

        global $wpdb;

        $pending_data = WC()->session->get( 'fwc_pending_form_data' );
        if ( empty( $pending_data ) || empty( $pending_data['entry_id'] ) ) return;

        $entry_id = absint( $pending_data['entry_id'] );
        $form_id  = absint( $pending_data['form_id'] );
        if ( ! $entry_id || ! $form_id ) return;

        $abandoned_threshold = fwc_get_abandoned_cart_threshold();
        $frm_items_table     = $wpdb->prefix . 'frm_items';

        $entry = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, created_at FROM {$frm_items_table} WHERE id = %d",
            $entry_id
        ) );

        if ( ! $entry ) return;

        // FIX: Use gmdate/time() for UTC on both sides
        $created_utc  = strtotime( $entry->created_at . ' UTC' ); // force interpret as UTC
        $current_utc  = time();                                    // always UTC
        $time_elapsed = $current_utc - $created_utc;

        error_log( "FWC Real-time: Entry #{$entry_id} created_at={$entry->created_at}, elapsed={$time_elapsed}s, threshold={$abandoned_threshold}s" );

        if ( $time_elapsed < $abandoned_threshold ) {
            error_log( "FWC Real-time: Not abandoned yet, " . ($abandoned_threshold - $time_elapsed) . "s remaining" );
            return;
        }

        if ( $time_elapsed < $abandoned_threshold ) return;

        $blog_id   = get_current_blog_id();
        $map_table = $wpdb->base_prefix . $blog_id . '_fwc_post_order_mapping';
        $panel     = $wpdb->get_row( $wpdb->prepare(
            "SELECT cart_status FROM {$map_table} WHERE form_id = %d LIMIT 1",
            $form_id
        ), ARRAY_A );

        if ( ! $panel || empty( $panel['cart_status'] ) ) return;

        $cart_status_field_id = FrmField::get_id_by_key( $panel['cart_status'] );
        if ( ! $cart_status_field_id ) return;

        $frm_meta_table = $wpdb->prefix . 'frm_item_metas';

        $current_status = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$frm_meta_table} WHERE item_id = %d AND field_id = %d",
            $entry_id,
            $cart_status_field_id
        ) );

        // Only mark abandoned if still in Cart status
        if ( strtolower( $current_status ) !== 'cart' ) return;

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$frm_meta_table} WHERE item_id = %d AND field_id = %d",
            $entry_id,
            $cart_status_field_id
        ) );

        if ( $existing ) {
            $wpdb->update(
                $frm_meta_table,
                [ 'meta_value' => 'Abandoned' ],
                [ 'id'         => $existing ],
                [ '%s' ],
                [ '%d' ]
            );
            error_log( "FWC Real-time: Entry #{$entry_id} marked Abandoned after {$time_elapsed}s on checkout" );
        }
    }
    // Clean up scheduled event on plugin deactivation
    register_deactivation_hook(__FILE__, 'fwc_deactivate_cron');
    function fwc_deactivate_cron() {
        wp_clear_scheduled_hook('fwc_check_abandoned_carts');
    }
    /**
     * Save Formidable data to the Order Meta when the order is created
     */
    add_action('woocommerce_checkout_create_order', 'fwc_save_payload_to_order_meta', 10, 2);
    function fwc_save_payload_to_order_meta($order, $data) {
        // Use WooCommerce session instead of $_SESSION
        $session_data = WC()->session->get('fwc_form_data', []);

        if (!empty($session_data)) {
            // Save full form payload
            $order->update_meta_data('_fwc_form_payload', $session_data);
            // Save form ID if it exists
            if (isset($session_data['form_id'])) {
                $order->update_meta_data('_fwc_form_id', absint($session_data['form_id']));
            }

            // Update billing fields
            $order->set_billing_first_name($session_data['first_name'] ?? $order->get_billing_first_name());
            $order->set_billing_last_name($session_data['last_name'] ?? $order->get_billing_last_name());
            $order->set_billing_email($session_data['email'] ?? $order->get_billing_email());
            $order->set_billing_phone($session_data['phone'] ?? $order->get_billing_phone());
        }
    }

    add_filter('woocommerce_checkout_get_value', 'fwc_prefill_checkout_fields', 10, 2);

    function fwc_prefill_checkout_fields($value, $input) {
        // Use WooCommerce session instead of $_SESSION
        $session_data = WC()->session->get('fwc_form_data', []);

        if (!empty($session_data)) {
            switch ($input) {
                case 'billing_first_name':
                    return $session_data['first_name'] ?? $value;
                case 'billing_last_name':
                    return $session_data['last_name'] ?? $value;
                case 'billing_email':
                    return $session_data['email'] ?? $value;
                case 'billing_phone':
                    return $session_data['phone'] ?? $value;
            }
        }
        return $value;
    }

    /**
     * Add settings tab section
    */
    add_filter( 'woocommerce_get_sections_advanced', function ( $sections ) {
        $sections['wc_validation_labels'] = __( 'Validation Labels', 'wc-validation-labels' );
        return $sections;
    });

    /**
     * Add settings fields
    */
    add_filter( 'woocommerce_get_settings_advanced', function ( $settings, $current_section ) {

        if ( $current_section !== 'wc_validation_labels' ) {
            return $settings;
        }

        return array(
            array(
                'title' => __( 'Validation Messages', 'wc-validation-labels' ),
                'type'  => 'title',
                'id'    => 'wc_validation_labels_title',
            ),

            array(
                'title'   => __( 'No Matching Variations Message', 'wc-validation-labels' ),
                'desc'    => __( 'Displayed when no variations match user input.', 'wc-validation-labels' ),
                'id'      => 'wc_no_matching_variations_message',
                'type'    => 'text',
                'default' => __( '', 'wc-validation-labels' ),
                'css'     => 'min-width:400px;',
            ),
            array(
                'title'   => __( 'Loading Content Message', 'wc-loading-labels' ),
                'desc'    => __( 'Displayed while content is loading.', 'wc-loading-labels' ),
                'id'      => 'wc_loading_content_message',
                'type'    => 'text',
                'default' => __( '', 'wc-loading-labels' ),
                'css'     => 'min-width:400px;',
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wc_validation_labels_title',
            ),
        );

    }, 10, 2 );

    /**
     * Expose setting to JavaScript
     */
    add_action( 'wp_enqueue_scripts', function () {

        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        wp_register_script(
            'wc-validation-labels',
            '',
            array( 'jquery' ),
            '1.0',
            true
        );

        wp_enqueue_script( 'wc-validation-labels' );

        wp_localize_script(
            'wc-validation-labels',
            'wcValidationLabels',
            array(
                'no_matching_variations' => get_option(
                    'wc_no_matching_variations_message',
                    'Currently no options matching to your inputs.'
                ),
                'loading_content' => get_option(
                'wc_loading_content_message',
                'Loading, please wait...'
                ),
            )
        );
    });

    add_action( 'woocommerce_thankyou', 'fwc_update_entry_with_order_data', 10, 1 );
    function fwc_update_entry_with_order_data( $order_id ) {
        global $wpdb;

        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Prevent duplicate processing
        if ( $order->get_meta( '_fwc_entry_updated' ) ) {
            return;
        }

        $entry_id = null;
        $form_id  = null;

        // Get entry_id from order items
        foreach ( $order->get_items() as $item ) {
            $entry_id = $item->get_meta( '_fwc_entry_id' );
            $form_id  = $item->get_meta( '_fwc_form_id' );

            if ( $entry_id && $form_id ) {
                break;
            }
        }

        if ( ! $entry_id || ! $form_id ) {
            return;
        }

        // Get mapping panel
        $table = $wpdb->base_prefix . get_current_blog_id() . '_fwc_post_order_mapping';
        $panel = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE form_id = %d LIMIT 1", $form_id),
            ARRAY_A
        );

        if ( ! $panel ) {
            error_log("FWC: No mapping panel found for form {$form_id}");
            return;
        }

        // Calculate surcharge
        $surcharge = 0.0;
        foreach ( $order->get_fees() as $fee ) {
            $surcharge += (float) $fee->get_total();
        }

        // Map order data to field IDs
        $mapped_fields = [
            'order_id'       => $panel['order_id'] ?? '',
            'order_total'    => $panel['order_total'] ?? '',
            'payment_method' => $panel['payment_method'] ?? '',
            'payment_status' => $panel['payment_status'] ?? '',
            'cart_status'   => $panel['cart_status'] ?? '',
            'surcharge'      => $panel['surcharge'] ?? '',
        ];

        $fields_to_update = [];
        foreach ($mapped_fields as $key => $field_key) {
            if (empty($field_key)) continue;

            $field_id = FrmField::get_id_by_key($field_key);
            if (!$field_id) continue;

            switch ($key) {
                case 'order_id':
                    $fields_to_update[$field_id] = $order_id;
                    break;
                case 'order_total':
                    $fields_to_update[$field_id] = $order->get_total();
                    break;
                case 'payment_method':
                    $fields_to_update[$field_id] = $order->get_payment_method_title();
                    break;
                case 'payment_status':
                    $fields_to_update[$field_id] = wc_get_order_status_name( $order->get_status() );                    
                    break;
                case 'cart_status':
                    $fields_to_update[$field_id] = "Completed";
                    break;
                case 'surcharge':
                    $fields_to_update[$field_id] = $surcharge;
                    break;
            }
        }

        // Update Formidable entry meta
        $frm_table = $wpdb->prefix . 'frm_item_metas';
        foreach ($fields_to_update as $field_id => $value) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $frm_table WHERE item_id = %d AND field_id = %d",
                $entry_id,
                $field_id
            ));

            if ($existing) {
                $wpdb->update(
                    $frm_table,
                    ['meta_value' => $value],
                    ['id' => $existing],
                    ['%s'],
                    ['%d']
                );
            } else {
                $wpdb->insert(
                    $frm_table,
                    [
                        'item_id'    => $entry_id,
                        'field_id'   => $field_id,
                        'meta_value' => $value,
                        'created_at' => current_time('mysql'),
                    ],
                    ['%d','%d','%s','%s']
                );
            }
        }
        
        // CLEAN ORDER ITEM METAS ONCE ENTRY IS UPDATED
        // if ( $entry_id ) {
        //     cleanup_orderitem_data( $order );
        // }

        // Update custom mapping table
        $custom_table = $wpdb->prefix . 'fwc_order_mapping';
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$custom_table}
                    (entry_id, form_id, order_id_key, payment_type_key, surcharge_key, order_total_key)
                    VALUES (%d, %d, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    order_id_key       = VALUES(order_id_key),
                    payment_type_key   = VALUES(payment_type_key),
                    payment_status_key  = VALUES(payment_status_key),
                    surcharge_key      = VALUES(surcharge_key),
                    order_total_key    = VALUES(order_total_key)",
                $entry_id,
                $form_id,
                $order_id,
                $order->get_payment_method_title(),
                wc_get_order_status_name( $order->get_status() ),
                $surcharge,
                $order->get_total()
            )
        );

        // Link entry to order
        update_post_meta($entry_id, 'fwc_order_id', $order_id);
        $order->update_meta_data('fwc_entry_id', $entry_id);
        $order->update_meta_data('_fwc_entry_updated', 1);
        $order->save();
    }

    function cleanup_orderitem_data( $order ){
        foreach ( $order->get_items() as $item ) {

            // Remove ALL variations of the meta keys
            $item->delete_meta_data('_fwc_form_payload');
            $item->delete_meta_data('fwc_form_payload');
            $item->delete_meta_data('_fwc_form_id');
            $item->delete_meta_data('fwc_form_id');

            $item->save();
        }

        // Also remove from order-level meta
        delete_post_meta($order->get_id(), '_fwc_form_payload');
        delete_post_meta($order->get_id(), 'fwc_form_payload');
        delete_post_meta($order->get_id(), '_fwc_form_id');
        delete_post_meta($order->get_id(), 'fwc_form_id');
    }
    /**
     * Make product name non-clickable on Thank You page
     */
    add_filter( 'woocommerce_order_item_permalink', '__return_false' );
    add_action('wp_footer', 'custom_checkout_loader_script');
    function custom_checkout_loader_script() {
        if (is_checkout()) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('form.checkout').on('submit', function(e) {
                    $('body').addClass('processing');
                });
                $('form.checkout').on('checkout_place_order', function() {
                    $('body').addClass('processing');
                });
                $(document.body).on('checkout_error', function() {
                    $('body').removeClass('processing');
                });
            });
            </script>
            <?php
        }
    }
    add_action( 'wp_footer', 'fwc_abandoned_cart_heartbeat_script' );
    function fwc_abandoned_cart_heartbeat_script() {
        if ( ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) return;

        $pending_data = WC()->session->get( 'fwc_pending_form_data' );
        if ( empty( $pending_data['entry_id'] ) ) return;

        $threshold_ms = fwc_get_abandoned_cart_threshold() * 1000; // convert to ms
        ?>
        <script type="text/javascript">
        (function() {
            var entryId      = <?php echo absint( $pending_data['entry_id'] ); ?>;
            var formId       = <?php echo absint( $pending_data['form_id'] ); ?>;
            var thresholdMs  = <?php echo $threshold_ms; ?>;
            var ajaxUrl      = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
            var nonce        = '<?php echo wp_create_nonce( 'fwc_abandoned_nonce' ); ?>';
            var startTime    = Date.now();
            var timerHandle  = null;
            var alreadyFired = false;

            console.log('FWC Heartbeat: Entry #' + entryId + ', threshold ' + (thresholdMs/1000) + 's');

            function markAbandoned() {
                if (alreadyFired) return;
                alreadyFired = true;

                var formData = new FormData();
                formData.append('action', 'fwc_mark_entry_abandoned');
                formData.append('entry_id', entryId);
                formData.append('form_id', formId);
                formData.append('nonce', nonce);

                fetch(ajaxUrl, { method: 'POST', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        console.log('FWC Heartbeat: Mark abandoned result:', res);
                    })
                    .catch(function(err) {
                        console.error('FWC Heartbeat: Error:', err);
                    });
            }

            // Schedule the abandoned mark after threshold
            timerHandle = setTimeout(function() {
                console.log('FWC Heartbeat: Threshold reached, marking abandoned...');
                markAbandoned();
            }, thresholdMs);

            // If user places order or leaves via order-received, cancel the timer
            window.addEventListener('beforeunload', function() {
                // Check if we're going to order-received page
                // We can't know for sure, so we let the server decide
                // The server will skip if status is already Completed
            });

            // Cancel if order is placed successfully (WC triggers this event)
            if (typeof jQuery !== 'undefined') {
                jQuery(document.body).on('checkout_place_order', function() {
                    console.log('FWC Heartbeat: Order being placed, cancelling abandoned timer');
                    clearTimeout(timerHandle);
                    alreadyFired = true; // prevent firing
                });
            }
        })();
        </script>
        <?php
    }
    // AJAX handler for the heartbeat
    add_action( 'wp_ajax_fwc_mark_entry_abandoned',        'fwc_ajax_mark_entry_abandoned' );
    add_action( 'wp_ajax_nopriv_fwc_mark_entry_abandoned', 'fwc_ajax_mark_entry_abandoned' );

    function fwc_ajax_mark_entry_abandoned() {
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'fwc_abandoned_nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
        }

        $entry_id = absint( $_POST['entry_id'] ?? 0 );
        $form_id  = absint( $_POST['form_id'] ?? 0 );

        if ( ! $entry_id || ! $form_id ) {
            wp_send_json_error( [ 'message' => 'Missing entry_id or form_id' ] );
        }

        global $wpdb;
        $blog_id        = get_current_blog_id();
        $map_table      = $wpdb->base_prefix . $blog_id . '_fwc_post_order_mapping';
        $frm_meta_table = $wpdb->prefix . 'frm_item_metas';

        // Get cart_status field key
        $panel = $wpdb->get_row( $wpdb->prepare(
            "SELECT cart_status FROM {$map_table} WHERE form_id = %d LIMIT 1",
            $form_id
        ), ARRAY_A );

        if ( ! $panel || empty( $panel['cart_status'] ) ) {
            wp_send_json_error( [ 'message' => 'No mapping found' ] );
        }

        $cart_status_field_id = FrmField::get_id_by_key( $panel['cart_status'] );
        if ( ! $cart_status_field_id ) {
            wp_send_json_error( [ 'message' => 'No field found' ] );
        }

        // Only update if current status is still 'Cart' — never overwrite Completed
        $current_status = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$frm_meta_table} WHERE item_id = %d AND field_id = %d",
            $entry_id,
            $cart_status_field_id
        ) );

        if ( strtolower( $current_status ) !== 'cart' ) {
            wp_send_json_success( [ 'message' => 'Status is already: ' . $current_status . ', skipping' ] );
        }

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$frm_meta_table} WHERE item_id = %d AND field_id = %d",
            $entry_id,
            $cart_status_field_id
        ) );

        if ( $existing ) {
            $wpdb->update(
                $frm_meta_table,
                [ 'meta_value' => 'Abandoned' ],
                [ 'id'         => $existing ],
                [ '%s' ],
                [ '%d' ]
            );
        } else {
            $wpdb->insert(
                $frm_meta_table,
                [
                    'item_id'    => $entry_id,
                    'field_id'   => $cart_status_field_id,
                    'meta_value' => 'Abandoned',
                    'created_at' => current_time( 'mysql' ),
                ],
                [ '%d', '%d', '%s', '%s' ]
            );
        }

        error_log( "FWC Ajax: Entry #{$entry_id} marked as Abandoned via JS heartbeat" );
        wp_send_json_success( [ 'message' => 'Entry marked as Abandoned', 'entry_id' => $entry_id ] );
    }
        function fwc_update_entry_cart_status( $entry_id, $order_id, $status_value ) {
        global $wpdb;
        $blog_id   = get_current_blog_id();
        $map_table = $wpdb->base_prefix . $blog_id . '_fwc_post_order_mapping';

        $frm_items = $wpdb->prefix . 'frm_items';
        $form_id   = $wpdb->get_var( $wpdb->prepare(
            "SELECT form_id FROM {$frm_items} WHERE id = %d",
            $entry_id
        ) );
        if ( ! $form_id ) return;

        $panel = $wpdb->get_row( $wpdb->prepare(
            "SELECT cart_status, order_id, order_total, surcharge FROM {$map_table} WHERE form_id = %d LIMIT 1",
            $form_id
        ), ARRAY_A );
        if ( ! $panel ) return;

        $frm_meta = $wpdb->prefix . 'frm_item_metas';
        $order    = wc_get_order( $order_id );
        $fields_to_update = [];

        // Cart status → always "Completed"
        if ( ! empty( $panel['cart_status'] ) ) {
            $fid = FrmField::get_id_by_key( $panel['cart_status'] );
            if ( $fid ) $fields_to_update[ $fid ] = 'Completed';
        }
        // Order ID
        if ( ! empty( $panel['order_id'] ) ) {
            $fid = FrmField::get_id_by_key( $panel['order_id'] );
            if ( $fid ) $fields_to_update[ $fid ] = $order_id;
        }
        // Order total
        if ( ! empty( $panel['order_total'] ) && $order ) {
            $fid = FrmField::get_id_by_key( $panel['order_total'] );
            if ( $fid ) $fields_to_update[ $fid ] = $order->get_total();
        }
        // Surcharge
        if ( ! empty( $panel['surcharge'] ) && $order ) {
            $surcharge = 0.0;
            foreach ( $order->get_fees() as $fee ) $surcharge += (float) $fee->get_total();
            $fid = FrmField::get_id_by_key( $panel['surcharge'] );
            if ( $fid ) $fields_to_update[ $fid ] = $surcharge;
        }

        // ✅ FIX: Update product fields with formatted SKU - $price value
        if ( $order ) {
            $currency_symbol = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
            $all_fields      = FrmField::get_all_for_form( $form_id );
            $updated_product_fields = [];

            foreach ( $order->get_items() as $order_item ) {
                $product = $order_item->get_product();
                if ( ! $product ) continue;

                $product_id   = $product->get_id();
                $product_type = $product->get_type();
                $parent_id    = ( $product_type === 'variation' ) ? $product->get_parent_id() : 0;

                foreach ( $all_fields as $field ) {
                    if ( ! in_array( $field->type, [ 'woocommerce_product', 'woocommerce_simple_product', 'product', 'data' ] ) ) {
                        continue;
                    }
                    if ( in_array( $field->id, $updated_product_fields ) ) continue;

                    // Get current stored value for this field on this entry
                    $stored_value = $wpdb->get_var( $wpdb->prepare(
                        "SELECT meta_value FROM {$frm_meta} WHERE item_id = %d AND field_id = %d",
                        $entry_id, $field->id
                    ) );

                    // Match: stored value is the product ID or parent ID (raw, not yet formatted)
                    $is_match = false;
                    if ( (string) $stored_value === (string) $product_id ) {
                        $is_match = true;
                    } elseif ( $parent_id > 0 && (string) $stored_value === (string) $parent_id ) {
                        $is_match = true;
                    }
                    // Also skip if already formatted (contains ' - $')
                    if ( strpos( (string) $stored_value, ' - ' ) !== false ) {
                        $updated_product_fields[] = $field->id;
                        continue;
                    }

                    if ( ! $is_match ) continue;

                    // Get SKU
                    $sku = $product->get_sku();
                    if ( empty( $sku ) && $parent_id > 0 ) {
                        $parent_product = wc_get_product( $parent_id );
                        if ( $parent_product ) $sku = $parent_product->get_sku();
                    }

                    $price = $order_item->get_total();
                    $formatted_value = sprintf(
                        '%s - %s%s',
                        ! empty( $sku ) ? $sku : 'N/A',
                        $currency_symbol,
                        number_format( (float) $price, 0 )
                    );

                    $fields_to_update[ $field->id ] = $formatted_value;
                    $updated_product_fields[] = $field->id;

                    error_log( "FWC: Formatted product field #{$field->id} = {$formatted_value} for entry #{$entry_id}" );
                    break;
                }
            }
        }

        // Write all updates
        foreach ( $fields_to_update as $field_id => $value ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$frm_meta} WHERE item_id = %d AND field_id = %d",
                $entry_id, $field_id
            ) );
            if ( $existing ) {
                $wpdb->update( $frm_meta, [ 'meta_value' => $value ], [ 'id' => $existing ], [ '%s' ], [ '%d' ] );
            } else {
                $wpdb->insert( $frm_meta, [
                    'item_id'    => $entry_id,
                    'field_id'   => $field_id,
                    'meta_value' => $value,
                    'created_at' => current_time( 'mysql' ),
                ], [ '%d', '%d', '%s', '%s' ] );
            }
        }
        error_log( "FWC: Entry #{$entry_id} updated — cart_status=Completed, product SKUs formatted" );
    }
    // function fwc_update_payment_status_field( $order_id, $entry_id, $payment_status_value ) {
    //     global $wpdb;
    //     $blog_id = get_current_blog_id();
    //     $order_table = $wpdb->base_prefix . $blog_id . '_fwc_order_mapping';
    //     $map_table   = $wpdb->base_prefix . $blog_id . '_fwc_post_order_mapping';
    //     $form_id = $wpdb->get_var(
    //         $wpdb->prepare(
    //             "SELECT form_id FROM {$order_table} WHERE order_id_key = %s LIMIT 1",
    //             $order_id
    //         )
    //     );

    //     if ( ! $form_id ) {
    //         error_log("FWC: No form_id found in order table for order {$order_id}");
    //         return false;
    //     }
    //     $field_key = $wpdb->get_var(
    //         $wpdb->prepare(
    //             "SELECT payment_status FROM {$map_table} WHERE form_id = %d LIMIT 1",
    //             $form_id
    //         )
    //     );

    //     if ( ! $field_key ) {
    //         error_log("FWC: No payment_status field key found for form_id {$form_id}");
    //         return false;
    //     }
    //     $field_id = FrmField::get_id_by_key( $field_key );
    //     if ( ! $field_id ) {
    //         error_log("FWC: Invalid field key {$field_key} cannot get field ID");
    //         return false;
    //     }
    //     FrmEntryMeta::update_entry_meta(
    //         $entry_id,
    //         $field_id,
    //         null,
    //         $payment_status_value
    //     );
    //     $wpdb->update(
    //         $order_table,
    //         [ 'payment_status_key' => $payment_status_value ],
    //         [ 'order_id_key' => $order_id ],
    //         [ '%s' ],
    //         [ '%s' ]
    //     );
    // }
    add_action(
        'woocommerce_order_status_changed',
        'fwc_create_entry_after_status_change',
        20,
        4
    );
    function fwc_create_entry_after_status_change( $order_id, $old_status, $new_status, $order ) {
        $status_map = [
            'processing' => 'Processing', 'completed'  => 'Completed',
            'on-hold'    => 'On Hold',    'pending'    => 'Pending',
            'failed'     => 'Failed',     'refunded'   => 'Refunded',
            'cancelled'  => 'Cancelled',
        ];
        $payment_status_value = $status_map[ $new_status ] ?? ucfirst( $new_status );
        $entry_id = $order->get_meta( 'fwc_entry_id' );

        if ( ! $entry_id ) {
            $pending_data = $order->get_meta( '_fwc_pending_form_data' );
            if ( ! empty( $pending_data['entry_id'] ) ) {
                $entry_id = absint( $pending_data['entry_id'] );
                $order->update_meta_data( 'fwc_entry_id', $entry_id );
                $order->save();
                error_log( "FWC: Found entry #{$entry_id} in _fwc_pending_form_data for order #{$order_id}" );
            }
        }
         if ( ! $entry_id ) {
            $entry_id = absint( get_post_meta( $order_id, '_formidable_entry_id', true ) );
            if ( $entry_id ) {
                $order->update_meta_data( 'fwc_entry_id', $entry_id );
                $order->save();
                error_log( "FWC: Found entry #{$entry_id} in _formidable_entry_id for order #{$order_id}" );
            }
        }
        if ( ! $entry_id ) {
            foreach ( $order->get_items() as $item ) {
                $item_entry_id = absint( $item->get_meta( '_fwc_entry_id', true ) );
                if ( $item_entry_id ) {
                    $entry_id = $item_entry_id;
                    $order->update_meta_data( 'fwc_entry_id', $entry_id );
                    $order->save();
                    error_log( "FWC: Found entry #{$entry_id} in order item meta for order #{$order_id}" );
                    break;
                }
            }
        }
        if ( $entry_id ) {
            fwc_update_payment_status_field( $order_id, $entry_id, $payment_status_value );
            if ( in_array( $new_status, [ 'processing', 'completed' ], true ) ) {
                fwc_update_entry_cart_status( $entry_id, $order_id, $payment_status_value );
            }
            return;
        }

        // Only create for actionable statuses
        if ( ! in_array( $new_status, [ 'processing', 'completed', 'on-hold' ], true ) ) return;

        // ATOMIC LOCK: Use wp_cache_add which is atomic - only one process wins
        $lock_key = 'fwc_entry_lock_' . $order_id;
        if ( ! wp_cache_add( $lock_key, 1, 'fwc_locks', 30 ) ) {
            error_log( "FWC: Lock exists for order #{$order_id}, skipping duplicate entry creation" );
            return;
        }

        // Double-check after acquiring lock
        $order = wc_get_order( $order_id ); // Fresh load
        $entry_id = $order->get_meta( 'fwc_entry_id' );
        if ( ! $entry_id ) {
            $pending_data = $order->get_meta( '_fwc_pending_form_data' );
            if ( ! empty( $pending_data['entry_id'] ) ) {
                $entry_id = absint( $pending_data['entry_id'] );
            }
        }
        if ( ! $entry_id ) {
            $entry_id = absint( get_post_meta( $order_id, '_formidable_entry_id', true ) );
        }
        if ( ! $entry_id ) {
            foreach ( $order->get_items() as $item ) {
                $eid = absint( $item->get_meta( '_fwc_entry_id', true ) );
                if ( $eid ) { $entry_id = $eid; break; }
            }
        }
        if ( $entry_id ) {
            $order->update_meta_data( 'fwc_entry_id', $entry_id );
            $order->save();
            fwc_update_payment_status_field( $order_id, $entry_id, $payment_status_value );
            if ( in_array( $new_status, [ 'processing', 'completed' ], true ) ) {
                fwc_update_entry_cart_status( $entry_id, $order_id, $payment_status_value );
            }
            wp_cache_delete( $lock_key, 'fwc_locks' );
            return;
        }

        if ( $order->get_meta( '_fwc_entry_created' ) ) {
            error_log( "FWC: Entry already created for order #{$order_id} (post-lock check)" );
            wp_cache_delete( $lock_key, 'fwc_locks' );
            return;
        }
        $order->update_meta_data( '_fwc_entry_created', 1 );
        $order->save();

        $entry_id = null;
        $pending_data = $order->get_meta( '_fwc_pending_form_data' );
        if ( ! empty( $pending_data['form_id'] ) ) {
            $entry_id = fwc_create_formidable_entry_after_order(
                absint( $pending_data['form_id'] ),
                $pending_data,
                $order_id
            );
            error_log( "FWC: Entry #{$entry_id} created from pending_form_data for order #{$order_id}" );
        }

        // Fallback: try from order item payload
        if ( ! $entry_id ) {
            foreach ( $order->get_items() as $item ) {
                $payload = $item->get_meta( '_fwc_form_payload', true );
                $form_id = $item->get_meta( '_fwc_form_id', true );
                if ( empty( $payload ) || empty( $form_id ) ) continue;

                $decoded = is_string( $payload ) ? json_decode( $payload, true ) : $payload;
                if ( ! is_array( $decoded ) ) continue;

                $entry_id = fwc_create_formidable_entry_after_order( absint( $form_id ), $decoded, $order_id );
                if ( $entry_id ) {
                    error_log( "FWC: Entry #{$entry_id} created from item payload for order #{$order_id}" );
                    break;
                }
            }
        }

        if ( $entry_id ) {
            $order->update_meta_data( 'fwc_entry_id', $entry_id );
            $order->save();
            fwc_update_payment_status_field( $order_id, $entry_id, $payment_status_value );
            if ( in_array( $new_status, [ 'processing', 'completed' ], true ) ) {
                fwc_update_entry_cart_status( $entry_id, $order_id, $payment_status_value );
            }
        } else {
            $order->delete_meta_data( '_fwc_entry_created' );
            $order->save();
            error_log( "FWC ERROR: Entry creation failed for order #{$order_id}" );
        }
        wp_cache_delete( $lock_key, 'fwc_locks' );
    }
    function fwc_update_payment_status_field( $order_id, $entry_id, $payment_status_value ) {
        global $wpdb;
        $blog_id = get_current_blog_id();
        $order_table = $wpdb->base_prefix . $blog_id . '_fwc_order_mapping';
        $map_table   = $wpdb->base_prefix . $blog_id . '_fwc_post_order_mapping';
        $form_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT form_id FROM {$order_table} WHERE order_id_key = %s LIMIT 1",
                $order_id
            )
        );

        if ( ! $form_id ) {
            error_log("FWC: No form_id found in order table for order {$order_id}");
            return false;
        }
        $field_key = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT payment_status FROM {$map_table} WHERE form_id = %d LIMIT 1",
                $form_id
            )
        );

        if ( ! $field_key ) {
            error_log("FWC: No payment_status field key found for form_id {$form_id}");
            return false;
        }
        $field_id = FrmField::get_id_by_key( $field_key );
        if ( ! $field_id ) {
            error_log("FWC: Invalid field key {$field_key} cannot get field ID");
            return false;
        }
        FrmEntryMeta::update_entry_meta(
            $entry_id,
            $field_id,
            null,
            $payment_status_value
        );
        $wpdb->update(
            $order_table,
            [ 'payment_status_key' => $payment_status_value ],
            [ 'order_id_key' => $order_id ],
            [ '%s' ],
            [ '%s' ]
        );
    }

    add_filter('woocommerce_hidden_order_itemmeta', 'fwc_hide_form_payload_meta');
    function fwc_hide_form_payload_meta( $hidden_meta_keys ) {
        $hidden_meta_keys[] = '_fwc_form_payload';
        $hidden_meta_keys[] = 'fwc_form_payload';
        $hidden_meta_keys[] = '_fwc_form_id';
        $hidden_meta_keys[] = 'fwc_form_id';
        return $hidden_meta_keys;
    }
