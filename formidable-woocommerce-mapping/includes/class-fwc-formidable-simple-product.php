<?php
class Formidable_WooCommerce_Simple_Field {
    
    public function __construct() {
        add_filter('frm_available_fields', array($this, 'register_field_type'));
        add_action('frm_field_options_form', array($this, 'field_options'), 10, 3);
        add_filter('frm_setup_new_fields_vars', array($this, 'setup_field'), 20, 2);
        add_filter('frm_field_value_saved', array($this, 'save_field_value'), 10, 3);
        add_filter('frm_update_field_options', array($this, 'update_field_options'), 20, 2);
        add_action('frm_form_fields', array($this, 'add_field_html'), 10, 2);
        add_action('wp_ajax_get_woo_product_price', array($this, 'ajax_get_product_price'));
        add_action('wp_ajax_nopriv_get_woo_product_price', array($this, 'ajax_get_product_price'));  
        add_filter('frm_validate_field_entry', array($this, 'validate_field'), 10, 3);
        add_action('frm_entry_form', array($this, 'add_validation_script'), 100, 1);
    }
    
    public function register_field_type($fields) {
        $fields['woocommerce_simple_product'] = array(
            'name' => 'WooCommerce Simple Product',
            'icon' => 'frm_icon_font frm-icon-shopping-cart'
        );
        return $fields;
    }
    
    public function field_options($field, $display, $values) {
        if ($field['type'] != 'woocommerce_simple_product') {
            return;
        }
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => array( 'simple' ),
                ),
            ),
        );

        $all_products = get_posts($args);
        
        $field_id = $field['id'];
        $field_options = isset($field['field_options']) ? $field['field_options'] : array();
        
        $selected_products = isset($field_options['selected_products_' . $field_id]) 
            ? $field_options['selected_products_' . $field_id] 
            : array();
        if (!is_array($selected_products)) {
            $selected_products = array();
        }
        
        $display_type = isset($field_options['display_type_' . $field_id]) 
            ? $field_options['display_type_' . $field_id] 
            : 'select';
            
        $show_price = isset($field_options['show_price_' . $field_id]) 
            ? $field_options['show_price_' . $field_id] 
            : 0;
        
        $product_attributes = isset($field_options['product_attributes_' . $field_id]) 
            ? $field_options['product_attributes_' . $field_id] 
            : array();
        ?>
        <tr>
            <td>
                <label><strong>Display Type</strong></label>
            </td>
            <td>
                <select name="field_options[display_type_<?php echo esc_attr($field_id); ?>]" 
                        id="display_type_<?php echo esc_attr($field_id); ?>">
                    <option value="select" <?php selected($display_type, 'select'); ?>>Dropdown</option>
                    <option value="radio" <?php selected($display_type, 'radio'); ?>>Radio Buttons</option>
                    <option value="checkbox" <?php selected($display_type, 'checkbox'); ?>>Checkboxes</option>
                </select>
                <p class="howto">Choose how products will be displayed on the frontend</p>
            </td>
        </tr>
        
        <tr>
            <td>
                <label><strong>Select & Order Products</strong></label>
            </td>
            <td>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                    <?php if (!empty($all_products) && is_array($all_products)) : ?>
                    <?php foreach ($all_products as $product) : 
                        $product_obj = wc_get_product($product->ID);
                        $product_type = $product_obj->get_type();
                        $is_selected = in_array($product->ID, $selected_products);
                        $current_order = $is_selected ? array_search($product->ID, $selected_products) + 1 : '';
                        ?>
                        <div style="display: flex; align-items: center;padding: 0px 5px 5px 5px; background: #f9f9f9;">
                            <label style="flex: 1; margin: 0;">
                                <input type="checkbox" 
                                       name="field_options[selected_products_<?php echo esc_attr($field_id); ?>][]" 
                                       value="<?php echo esc_attr($product->ID); ?>"
                                       class="product_checkbox_<?php echo esc_attr($field_id); ?>"
                                       data-product-id="<?php echo esc_attr($product->ID); ?>"
                                       <?php checked($is_selected); ?> />
                                <?php echo esc_html($product->post_title); ?>
                                <span style="color: #666; font-size: 0.9em;">
                                    (<?php echo esc_html(ucfirst($product_type)); ?>)
                                </span>
                            </label>
                            <input type="number" 
                                   name="field_options[product_order_<?php echo esc_attr($field_id); ?>][<?php echo esc_attr($product->ID); ?>]"
                                   value="<?php echo esc_attr($current_order); ?>"
                                   min="1"
                                   step="1"
                                   placeholder="Sequence"
                                   style="width: 60px; margin-left: 10px;"
                                   class="product_order_<?php echo esc_attr($field_id); ?>"
                                   data-product-id="<?php echo esc_attr($product->ID); ?>" />
                        </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <p class="howto">Check products to include and set their display order (1, 2, 3, etc.)</p>
            </td>
        </tr>
        
        <tr>
            <td>
                <label><strong>Product Options</strong></label>
            </td>
            <td>
                <label style="display: block;">
                    <input type="checkbox" 
                           name="field_options[show_price_<?php echo esc_attr($field_id); ?>]" 
                           value="1" <?php checked($show_price, 1); ?> />
                    Show price with product name
                </label>
            </td>
        </tr>
        <?php
    }    
    
    public function update_field_options($options, $field) {
        if ($field->type != 'woocommerce_simple_product') {
            return $options;
        }
        
        $field_id = $field->id;
        
        if (isset($_POST['field_options']['display_type_' . $field_id])) {
            $options['display_type_' . $field_id] = sanitize_text_field($_POST['field_options']['display_type_' . $field_id]);
        }
        
        if (isset($_POST['field_options']['selected_products_' . $field_id])) {
            $selected_products = array_map('absint', $_POST['field_options']['selected_products_' . $field_id]);
            $product_orders = isset($_POST['field_options']['product_order_' . $field_id]) 
                ? $_POST['field_options']['product_order_' . $field_id] 
                : array();
            
            $ordered_products = array();
            foreach ($selected_products as $product_id) {
                $order = isset($product_orders[$product_id]) ? absint($product_orders[$product_id]) : 999;
                $ordered_products[$product_id] = $order;
            }
            
            asort($ordered_products);
            $options['selected_products_' . $field_id] = array_keys($ordered_products);
        } else {
            $options['selected_products_' . $field_id] = array();
        }
        
        if (isset($_POST['field_options']['product_attributes_' . $field_id])) {
            $product_attributes = array();
            foreach ($_POST['field_options']['product_attributes_' . $field_id] as $product_id => $attributes) {
                $product_attributes[absint($product_id)] = array_map('sanitize_text_field', $attributes);
            }
            $options['product_attributes_' . $field_id] = $product_attributes;
        } else {
            $options['product_attributes_' . $field_id] = array();
        }
        
        $options['show_price_' . $field_id] = isset($_POST['field_options']['show_price_' . $field_id]) ? 1 : 0;
       
        return $options;
    }
    

    public function setup_field($values, $field) {
        if ($field->type !== 'woocommerce_simple_product') {
            return $values;
        }

        $field_id      = (int) $field->id;
        $field_options = (array) ($field->field_options ?? array());

        $selected_products = !empty($field_options['selected_products_' . $field_id])
            ? array_map('absint', (array) $field_options['selected_products_' . $field_id])
            : array();

        if (empty($selected_products)) {
            $values['custom_html'] = '<em>No product selected.</em>';
            return $values;
        }

        $display_type = isset($field_options['display_type_' . $field_id]) ? $field_options['display_type_' . $field_id] : 'select';
        $show_price   = !empty($field_options['show_price_' . $field_id]);
        $is_required  = !empty($field->required) && $field->required == '1';

        // Field name and messages
        $field_name = !empty($field->name) ? $field->name : 'This field';
        $req_msg = $field_name . ' cannot be blank.';
        $inv_msg = $field_name . ' is invalid';
        $required_mark = $is_required ? ' <span class="frm_required">*</span>' : '';
        $field_description = '';

        if (!empty($field->description)) {
            $allowed_html = array(
                'a' => array(
                    'href'   => array(),
                    'title'  => array(),
                    'target' => array(),
                    'rel'    => array(),
                ),
            );

            $field_description = trim(
                wp_kses(
                    do_shortcode($field->description),
                    $allowed_html
                )
            );
        }
        // Get products in the exact order specified by admin
        $products = array();
        foreach ($selected_products as $product_id) {
            $product = get_post($product_id);
            if ($product && $product->post_status === 'publish' && $product->post_type === 'product') {
                $products[] = $product;
            }
        }

        // Wrapper
        $html  = '<div class="frm_form_field frm-woo-simple-product-wrapper form-field"';
        $html .= ' id="frm_field_' . esc_attr($field_id) . '_container"';
        $html .= ' data-field-id="' . esc_attr($field_id) . '"';
        $html .= ' data-required="' . ($is_required ? '1' : '0') . '"';
        $html .= ' data-display-type="' . esc_attr($display_type) . '">';

        /* ===== Labels / Legends ===== */
        if ($display_type === 'select') {
            // Label for select
            $html .= '<label for="field_' . esc_attr($field_id) . '" class="frm_primary_label">';
            $html .= esc_html($field_name) . $required_mark . '</label>';
        } else {
            // Fieldset + legend for radio/checkbox
            $role = ($display_type === 'checkbox') ? 'group' : 'radiogroup';
            $html .= '<fieldset class="frm_opt_container" role="' . esc_attr($role) . '" aria-required="' . ($is_required ? 'true' : 'false') . '">';
            $html .= '<legend class="frm_primary_label">' . esc_html($field_name) . $required_mark . '</legend>';
        }

        /* ===== RADIO / CHECKBOX ===== */
        if (in_array($display_type, array('radio', 'checkbox'), true)) {
            $input_name = ($display_type === 'checkbox')
                ? 'item_meta[' . $field_id . '][]'
                : 'item_meta[' . $field_id . ']';

            foreach ($products as $product) {
                $product_obj = wc_get_product($product->ID);
                if (!$product_obj || $product_obj->get_type() !== 'simple') {
                    continue;
                }

                $price = (float) $product_obj->get_price();
                $label = esc_html($product_obj->get_name());

                if ($show_price && $price !== '') {
                    $currency_code = get_woocommerce_currency();
                    $display_price = strip_tags( wc_price( $price, array( 'decimals' => 0, 'currency' => $currency_code ) ) );
                    $label .= ' - ' . $display_price . ' ' . $currency_code;
                }


                $html .= '<div class="frm_' . esc_attr($display_type) . '">';
                $html .= '<label>';
                $html .= sprintf(
                    '<input type="%s" name="%s" value="%d" data-product-id="%d" data-price="%s" data-field-id="%d" data-reqmsg="%s" data-invmsg="%s" class="frm_woo_product_field"> ',
                    esc_attr($display_type),
                    esc_attr($input_name),
                    esc_attr($product->ID),
                    esc_attr($product->ID),
                    esc_attr($price),
                    esc_attr($field_id),
                    esc_attr($req_msg),
                    esc_attr($inv_msg)
                );
                $html .= $label;
                $html .= '</label>';
                $html .= '</div>';
            }
            if ($field_description) {
                $html .= '<div class="frm_description">' . $field_description . '</div>';
            }
            $html .= '</fieldset>';
        }

        /* ===== SELECT (Dropdown) ===== */
        if ($display_type === 'select') {
            $html .= '<select name="item_meta[' . esc_attr($field_id) . ']"';
            $html .= ' id="field_' . esc_attr($field_id) . '"';
            $html .= ' class="frm_woo_simple_select frm_woo_product_field"';
            $html .= ' data-field-id="' . esc_attr($field_id) . '"';
            $html .= ' data-reqmsg="' . esc_attr($req_msg) . '"';
            $html .= ' data-invmsg="' . esc_attr($inv_msg) . '"';
            $html .= ' aria-required="' . ($is_required ? 'true' : 'false') . '"';
            if ($is_required) {
                $html .= ' required="required"';
            }
            $html .= '>';

            $html .= '<option value="">-- Select a Product --</option>';

            foreach ($products as $product) {
                $product_obj = wc_get_product($product->ID);
                if (!$product_obj || $product_obj->get_type() !== 'simple') {
                    continue;
                }

                $price = (float) $product_obj->get_price();
                $label = esc_html($product_obj->get_name());

            if ($show_price && $price !== '') {
                $currency_code = get_woocommerce_currency(); 
                $display_price = strip_tags( wc_price( $price, array( 'decimals' => 0, 'currency' => $currency_code ) ) );
                $label .= ' - ' . $display_price . ' ' . $currency_code;
            }

                $html .= sprintf(
                    '<option value="%d" data-product-id="%d" data-price="%s">%s</option>',
                    esc_attr($product->ID),
                    esc_attr($product->ID),
                    esc_attr($price),
                    $label
                );
            }
            $html .= '</select>';
             if ($field_description) {
                $html .= '<div class="frm_description">' . $field_description . '</div>';
            }
        }
        // Close wrapper
        $html .= '</div>';

        $values['custom_html'] = $html;
        return $values;
    }
    
    public function validate_field($errors, $field, $value) {
        if ($field->type !== 'woocommerce_simple_product') {
            return $errors;
        }
        
        // Check if field is required
        if (empty($field->required) || $field->required != 1) {
            return $errors;
        }
        
        $field_id = $field->id;
        $field_options = isset($field->field_options) ? $field->field_options : array();
        $display_type = isset($field_options['display_type_' . $field_id]) 
            ? $field_options['display_type_' . $field_id] 
            : 'select';
        
        // Determine if value is empty based on display type
        $is_empty = true;
        
        if ($display_type === 'checkbox') {
            // For checkbox - value should be an array with at least one checked item
            if (is_array($value)) {
                $filtered = array_filter($value, function($v) {
                    return !empty($v) && trim($v) !== '' && $v !== '0';
                });
                $is_empty = empty($filtered);
            } else {
                // If not array, check if single value exists
                $is_empty = (empty($value) || trim($value) === '' || $value === '0');
            }
        } elseif ($display_type === 'radio') {
            // For radio - value should be a single non-empty value
            $is_empty = (empty($value) || trim($value) === '' || $value === '0');
        } elseif ($display_type === 'select') {
            // For dropdown - value should be selected (not empty string)
            $is_empty = (empty($value) || trim($value) === '' || $value === '0');
        }
        
        // Add error if field is empty
        if ($is_empty) {
            $errors['field' . $field->id] = 'This field cannot be blank.';
        }
        
        return $errors;
    }

    public function add_field_html($field, $form) {
        if (is_numeric($field)) {
            $field = FrmField::getOne($field);
        } elseif (is_array($field)) {
            $field = (object) $field;
        }

        if (!is_object($field) || empty($field->type)) {
            return;
        }

        if ($field->type !== 'woocommerce_simple_product') {
            return;
        }

        $field_id = (int) $field->id;
        $is_required = !empty($field->required) && $field->required == '1';
        ?>
        <script>
        jQuery(document).ready(function($) {
            var fieldId = <?php echo $field_id; ?>;
            var isRequired = <?php echo $is_required ? 'true' : 'false'; ?>;
            console.log(isRequired);
            // Force required attributes after Formidable loads
            setTimeout(function() {
                if (isRequired) {
                    $('.frm_woo_product_field[data-field-id="' + fieldId + '"]').attr('aria-required', 'true');
                    if ($('.frm_woo_product_field[data-field-id="' + fieldId + '"]').is('select')) {
                        $('.frm_woo_product_field[data-field-id="' + fieldId + '"]').attr('required', 'required');
                    }
                }
            }, 100);
            var hiddenHtml = `
                <input type="hidden" name="selected_simple_product_ids" value="">
                <input type="hidden" name="selected_simple_product_prices" value="">
            `;

            if ($('[name="selected_simple_product_ids"]').length === 0) {
                $('form.frm-show-form').append(hiddenHtml);
            }

            function updateSimpleProductFields() {
                let ids = [];
                let prices = [];

                // Handle RADIO buttons
                $('input[type="radio"][name="item_meta[' + fieldId + ']"]:checked').each(function () {
                    ids.push(this.value);
                    prices.push($(this).data('price') || 0);
                });

                // Handle CHECKBOXES
                $('input[type="checkbox"][name="item_meta[' + fieldId + '][]"]:checked').each(function () {
                    ids.push(this.value);
                    prices.push($(this).data('price') || 0);
                });

                // Handle SELECT dropdown
                var selectVal = $('select[name="item_meta[' + fieldId + ']"]').val();
                if (selectVal) {
                    ids.push(selectVal);
                    var selectPrice = $('select[name="item_meta[' + fieldId + ']"] option:selected').data('price');
                    prices.push(selectPrice || 0);
                }

                $('[name="selected_simple_product_ids"]').val(ids.join(','));
                $('[name="selected_simple_product_prices"]').val(prices.join(','));
            }

            $(document).on('change', '.frm_woo_product_field[data-field-id="' + fieldId + '"]', updateSimpleProductFields);
        });
        </script>
        <?php
    }
    
    public function add_validation_script($form) {
        // Get all woocommerce simple product fields in this form
        $fields = FrmField::get_all_for_form($form->id);
        $woo_fields = array();
        
        foreach ($fields as $field) {
            if ($field->type === 'woocommerce_simple_product' && !empty($field->required) && $field->required == '1') {
                $field_options = maybe_unserialize($field->field_options);
                $display_type = isset($field_options['display_type_' . $field->id]) 
                    ? $field_options['display_type_' . $field->id] 
                    : 'select';
                
                $woo_fields[] = array(
                    'id' => $field->id,
                    'display_type' => $display_type,
                    'name' => $field->name
                );
            }
        }
        
        if (empty($woo_fields)) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var wooFields = <?php echo json_encode($woo_fields); ?>;
            $(document).on('frmBeforeFormRedirect', function(event, form, response) {
                var hasError = false;                
                $.each(wooFields, function(index, fieldInfo) {
                    var fieldId = fieldInfo.id;
                    var displayType = fieldInfo.display_type;
                    var fieldName = fieldInfo.name;
                    var hasValue = false;                    
                    if (displayType === 'checkbox') {
                        hasValue = $('input[type="checkbox"][name="item_meta[' + fieldId + '][]"]:checked').length > 0;
                    } else if (displayType === 'radio') {
                        hasValue = $('input[type="radio"][name="item_meta[' + fieldId + ']"]:checked').length > 0;
                    } else if (displayType === 'select') {
                        var val = $('select[name="item_meta[' + fieldId + ']"]').val();
                        hasValue = (val && val !== '' && val !== '0');
                    }                    
                    if (!hasValue) {
                        hasError = true;
                        var container = $('#frm_field_' + fieldId + '_container');
                        container.addClass('frm_blank_field');
                        container.find('.frm_error').remove();
                        var errorHtml = '<div class="frm_error">' + fieldName + ' cannot be blank.</div>';
                        container.prepend(errorHtml);
                        if (index === 0) {
                            $('html, body').animate({
                                scrollTop: container.offset().top - 100
                            }, 500);
                        }
                    }
                });
                
                if (hasError) {
                    return false;
                }
            });
            
            // Clear errors on change
            $.each(wooFields, function(index, fieldInfo) {
                var fieldId = fieldInfo.id;
                
                $(document).on('change', '[data-field-id="' + fieldId + '"] input, [data-field-id="' + fieldId + '"] select', function() {
                    var container = $('#frm_field_' + fieldId + '_container');
                    container.removeClass('frm_blank_field');
                    container.find('.frm_error').remove();
                });
            });
        });
        </script>
        <?php
    }
    
    public function ajax_get_product_price() {
        check_ajax_referer('frm_woo_product_nonce', 'nonce');        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;        
        if (!$product_id) {
            wp_send_json_error(array('message' => 'Invalid product ID'));
        }        
        $product = wc_get_product($product_id);        
        if (!$product) {
            wp_send_json_error(array('message' => 'Product not found'));
        }        
        $price = $product->get_price();
        $price_html = $price ? wc_price($price) : 'N/A';        
        wp_send_json_success(array(
            'product_type' => 'simple',
            'price' => $price,
            'price_html' => $price_html,
            'product_name' => $product->get_name()
        ));
    }

    public function save_field_value($value, $field, $entry_id) {
        if (is_object($field) && isset($field->type) && $field->type == 'woocommerce_simple_product') {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            update_post_meta($entry_id, 'frm_woo_product_id', $value);
        }
        return $value;
    }
}
new Formidable_WooCommerce_Simple_Field();