/**
 * Formidable WooCommerce Product Field - Frontend JavaScript
 * Complete Fixed Version - No Duplicate Submissions
 */

jQuery(document).ready(function ($) {
    window.frmWooValidationPassed = false;
    
    // =============================
    // Helper: Create hidden field
    // =============================
    function createHiddenField(fieldName) {
        if (typeof frmWoo === 'undefined' || !Array.isArray(frmWoo.target_form_ids)) {
            return;
        }
        const tryCreateForForm = (formId) => {
            const formSelector = '#frm_form_' + formId + '_container form';
            const form = document.querySelector(formSelector);
            if (!form) {
                setTimeout(() => tryCreateForForm(formId), 100);
                return;
            }
            if (!form.querySelector(`[name="${fieldName}"]`)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = fieldName;
                form.appendChild(input);
            }
        };

        frmWoo.target_form_ids.forEach(formId => {
            tryCreateForForm(formId);
        });
    }

    // Create all hidden fields on page load
    if (typeof frmWoo !== 'undefined' && Array.isArray(frmWoo.target_form_ids)){
        const hiddenFields = [
            'selected_variation_parent_id',
            'selected_variation_id',
            'selected_variation_price',
            'selected_simple_checkbox_ids',
            'selected_simple_checkbox_prices',
        ];
        hiddenFields.forEach(fieldName => {
            createHiddenField(fieldName);
        });
    }

    // =============================
    // Display error in Formidable's error container
    // =============================
    function displayFormError($form, message) {
        if (!message) {
            $form.find('.frm_woo_custom_error, .fwc-error-message').remove();
            $form.data('fwc-processing', false);
            return;
        }
        const errorHtml = `<div class="frm_error_style frm_woo_custom_error fwc-error-message" style="background-color: #f8d7da; color: #721c24; padding: 12px; margin-bottom: 15px; border: 1px solid #f5c6cb; border-radius: 4px; font-weight: bold;">
            <div class="frm_error">${message}</div>
        </div>`;
        
        const $formMessages = $form.find('.frm_form_fields');
        if ($formMessages.length) {
            $formMessages.prepend(errorHtml);
        } else {
            $form.prepend(errorHtml);
        }
        
        $('html, body').animate({
            scrollTop: $form.offset().top - 100
        }, 500);
        
        $form.data('fwc-processing', false);
    }

    // =============================
    // Validate Formidable required fields
    // =============================
    $(document).on('input change', 'form.frm-show-form input, form.frm-show-form select, form.frm-show-form textarea', function() {
        const $field = $(this);
        const $container = $field.closest('.frm_form_field');
        
        $field.removeClass('frm_invalid');
        $container.find('.frm_error').remove();
        
        if ($field.val() && $field.val().toString().trim() !== '') {
            $field.attr('aria-invalid', 'false');
        }
    });
    
    function validateFormidableRequiredFields($form) {
        let isValid = true;
        let firstErrorField = null;
        let processedFields = [];
        
        // If employee feedback is checked, skip validation for product fields
        if ($(".employee_feedback input[type='checkbox']").is(":checked")) {
            $form.find(
                '.matched-results :input,' +
                '.frm-woo-simple-product-wrapper :input'
            ).each(function () {
                $(this).removeAttr('aria-required')
                    .removeAttr('data-reqmsg')
                    .removeClass('frm_invalid');
            });
        }

        $form.find('.frm_error').remove();
        $form.find('.frm_invalid').removeClass('frm_invalid');
        
        $form.find('[aria-required="true"], [data-reqmsg]').each(function () {
            const $field = $(this);

            if ($field.is(':hidden') || !$field.is(':visible')) {
                return;
            }
            
            const tagName = $field.prop('tagName').toLowerCase();
            if (!['input', 'select', 'textarea'].includes(tagName)) {
                return;
            }
            
            const fieldType = $field.attr('type');
            const fieldName = $field.attr('name');
            
            if (fieldType === 'checkbox' || fieldType === 'radio') {
                if (processedFields.includes(fieldName)) {
                    return;
                }
                processedFields.push(fieldName);
                
                const escapedFieldName = fieldName.replace(/\[/g, '\\[').replace(/\]/g, '\\]');
                const $checkedOptions = $form.find(`input[name="${escapedFieldName}"]:checked`);
                const isChecked = $checkedOptions.length > 0;
                
                if (!isChecked) {
                    isValid = false;
                    showFieldError($field, $field.data('reqmsg') || 'This field is required.');
                    
                    if (!firstErrorField) {
                        firstErrorField = $field;
                    }
                }
            } else {
                const value = ($field.val() || '').toString().trim();
                
                if (!value) {
                    isValid = false;
                    showFieldError($field, $field.data('reqmsg') || 'This field is required.');
                    
                    if (!firstErrorField) {
                        firstErrorField = $field;
                    }
                }
            }
        });

        if (firstErrorField) {
            $('html, body').animate(
                { scrollTop: firstErrorField.offset().top - 100 },
                300
            );
            firstErrorField.focus();
        }
        
        return isValid;
    }

    function showFieldError($field, message) {
        if (!message) {
            message = 'This field is required.';
        }

        const $container = $field.closest('.frm_form_field');
        
        $field.addClass('frm_invalid');
        $field.attr('aria-invalid', 'true');

        if ($container.find('.frm_error').length === 0) {
            $('<div class="frm_error"></div>')
                .text(message)
                .appendTo($container);
        }
    }

    // =============================
    // Handle simple product wrapper changes
    // =============================
    $(document).on(
        'change',
        '.frm-woo-simple-product-wrapper input[type="radio"],' +
        '.frm-woo-simple-product-wrapper input[type="checkbox"],' +
        '.frm-woo-simple-product-wrapper select',
        function () {
            const $input   = $(this);
            const $form    = $input.closest('form');
            const $wrapper = $input.closest('.frm-woo-simple-product-wrapper');

            if (!$form.length || !$wrapper.length) return;

            const fieldId = $wrapper.data('field-id');
            if (!fieldId) return;

            let ids    = [];
            let prices = [];

            if ($input.is('select')) {
                const val = $input.val();
                if (val) {
                    ids.push(val);
                    prices.push(
                        parseFloat(
                            $input.find('option:selected').data('price') || 0
                        )
                    );
                }
            }
            else if ($input.is(':radio')) {
                const $checked = $wrapper.find(
                    `input[name="item_meta[${fieldId}]"]:checked`
                );

                if ($checked.length) {
                    ids.push($checked.val());
                    prices.push(parseFloat($checked.data('price') || 0));
                }
            }
            else if ($input.is(':checkbox')) {
                $wrapper
                    .find(`input[name="item_meta[${fieldId}][]"]:checked`)
                    .each(function () {
                        ids.push(this.value);
                        prices.push(parseFloat($(this).data('price') || 0));
                    });
            }

            $wrapper.data('selectedIds', ids);
            $wrapper.data('selectedPrices', prices);

            // ===== COLLECT PRODUCTS FROM UNIQUE WRAPPERS ONLY =====
            let allIds = [];
            let allPrices = [];
            const processedFieldIds = new Set(); // Track which field IDs we've processed
            
            // Check if employee feedback is checked
            const isEmployeeFeedback = $('.employee_feedback input[type="checkbox"]').is(':checked');
            $form.find('.frm-woo-simple-product-wrapper').each(function () {
                const $wrap = $(this);
                const wrapFieldId = $wrap.data('field-id');                
                // Skip if employee feedback is checked AND this wrapper is inside matched-results
                if (isEmployeeFeedback && $wrap.closest('.matched-results').length) {
                    return;
                }
                if (processedFieldIds.has(wrapFieldId)) {
                    return;
                }
                
                // Mark this field ID as processed
                processedFieldIds.add(wrapFieldId);

                const fIds    = $wrap.data('selectedIds') || [];
                const fPrices = $wrap.data('selectedPrices') || [];
                allIds.push(...fIds);
                allPrices.push(...fPrices);
            });
            if (!$form.find('[name="selected_simple_product_ids"]').length) {
                $form.append('<input type="hidden" name="selected_simple_product_ids">');
            }
            if (!$form.find('[name="selected_simple_product_prices"]').length) {
                $form.append('<input type="hidden" name="selected_simple_product_prices">');
            }

            $form.find('[name="selected_simple_product_ids"]').val(allIds.join(','));
            $form.find('[name="selected_simple_product_prices"]').val(allPrices.join(','));
            if (typeof updateFinalTotal === 'function') {
                updateFinalTotal($form);
            }
        }
    );

    // =============================
    // FORM SUBMISSION HANDLER - SINGLE VERSION
    // =============================
    window.frmWooCustomProcessing = false;
    window.frmWooBypassNormalSubmit = false;

    // Intercept Formidable's events to prevent double submission
    $(document).on('frmBeforeFormRedirect', function(event, form, response) {        
        if (window.frmWooBypassNormalSubmit) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            return false;
        }
    });
    $(document).on('frmFormComplete', function(event, form, response) {        
        if (window.frmWooBypassNormalSubmit) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            return false;
        }
    });
    // Prevent any AJAX form submissions when bypassing
    $(document).on('frmBeforeSend', function(event, form) {        
        if (window.frmWooBypassNormalSubmit) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            return false;
        }
    });

    // Initialize handler with delay
    // Replace your form handler initialization with this improved version:
    setTimeout(function() {        
        if (!window.frmWoo || !Array.isArray(frmWoo.target_form_ids)) {
            return;
        }        
        frmWoo.target_form_ids.forEach(function(formId) {
            const $targetForm = $('form.frm-show-form').filter(function() {
                return parseInt($(this).find('input[name="form_id"]').val(), 10) === formId;
            });
            
            if (!$targetForm.length) {
                return;
            }
            
            // Store that we've initialized this form
            if ($targetForm.data('fwc-handler-initialized')) {
                return;
            }
            $targetForm.data('fwc-handler-initialized', true);
            
            // Remove ALL existing submit event handlers
            $targetForm.off('submit');
            
            // Add a unique processing flag per form
            const processingKey = 'fwc-processing-' + formId;
            
            // Bind handler at capture phase (highest priority)
    // In your initialization, update the submit handler:
    $targetForm[0].addEventListener('submit', function(e) {        
        const $form = $(this);
        
        // Check if we should allow native Formidable submission
        if ($form.data('fwc-allow-native')) {
            $form.data('fwc-allow-native', false);
            // Don't prevent default - let Formidable handle it
            return;
        }
        
        // ALWAYS prevent default for our custom handling
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();        
        // Check if already processing
        if ($targetForm.data(processingKey)) {
            return false;
        }
        
        // Check global flag as backup
        if (window.frmWooCustomProcessing) {
            return false;
        }
        
        // Set both flags
        $targetForm.data(processingKey, true);
        window.frmWooCustomProcessing = true;        
        const form_id = parseInt($form.find('input[name="form_id"]').val(), 10);        
        const isEmployeeFeedback = $('.employee_feedback input[type="checkbox"]:checked').length > 0;
        const productData = {
            product_id: $form.find('input[name="selected_variation_parent_id"]').val(),
            variation_id: $form.find('input[name="selected_variation_id"]').val(),
            custom_price: $form.find('input[name="selected_variation_price"]').val(),
            simple_product_ids: $form.find('input[name="selected_simple_product_ids"]').val(),
            simple_product_prices: $form.find('input[name="selected_simple_product_prices"]').val()
        };
        const hasSimpleProductIds = (            
            (productData.simple_product_ids && productData.simple_product_ids.trim().length > 0) ||
            ($form.find('.frm-woo-simple-product-wrapper input[type="checkbox"]:checked').length > 0)
        );
        
        const hasVariationProduct = !!(productData.product_id && productData.variation_id);
        const hasAnyProduct = hasVariationProduct || hasSimpleProductIds;
        let shouldRedirectToCheckout = false;
        let shouldSubmitNormally = false;

        // DECISION LOGIC
        if (isEmployeeFeedback) {            
            if (hasAnyProduct) {
                shouldRedirectToCheckout = true;
                shouldSubmitNormally = false;
            } else {
                shouldSubmitNormally = true;
                shouldRedirectToCheckout = false;
            }
        } else {            
            if (hasAnyProduct) {
                shouldRedirectToCheckout = true;
                shouldSubmitNormally = false;
            } else {
                shouldSubmitNormally = true;
                shouldRedirectToCheckout = false;
            }
        }
        // Helper function to reset flags
        function resetFlags() {
            $targetForm.data(processingKey, false);
            window.frmWooCustomProcessing = false;
            window.frmWooBypassNormalSubmit = false;
        }

        // EXECUTE DECISION
        if (shouldRedirectToCheckout && !shouldSubmitNormally) {
            window.frmWooBypassNormalSubmit = true;
            executeCheckoutRedirect($form, form_id, productData, resetFlags);
            return false;
        } 
        
        if (shouldSubmitNormally && !shouldRedirectToCheckout) {
            window.frmWooBypassNormalSubmit = false;
            executeNormalSubmission($form, form_id, resetFlags);
            return false;
        }
        resetFlags();
        return false;
        
    }, true);
        });
    }, 500);

    // NORMAL FORM SUBMISSION FUNCTION
    function executeNormalSubmission($form, form_id, resetFlags) {        
        $form.find('.frm_woo_custom_error, .fwc-error-message').remove();
        
        if (typeof validateFormidableRequiredFields === 'function' && !validateFormidableRequiredFields($form)) {
            if (typeof asen_form_failure === 'function') {
                asen_form_failure(form_id);
            }
            if (resetFlags) resetFlags();
            return;
        }        
        // Reset bypass flag
        window.frmWooBypassNormalSubmit = false;
        $form.data('fwc-allow-native', true);
        
        // Trigger native form submission
        // This will be caught by our handler which will allow it through
        $form.trigger('submit');
        
        // Note: resetFlags will happen after page reload
    }
    // Separate AJAX submission function
    function executeAjaxSubmission($form, form_id, resetFlags) {
        const formData = new FormData($form[0]);
        
        // Ensure required Formidable fields are present
        if (!formData.has('action')) {
            formData.append('action', 'frm_entries_create');
        }
        if (!formData.has('form_id')) {
            formData.append('form_id', form_id);
        }
        
        $.ajax({
            url: $form.attr('action') || window.location.href,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {                
                // Handle Formidable's response
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        // Response might be HTML, handle success message display
                        if (response.indexOf('frm_message') > -1) {
                            $form.closest('.frm_forms').html(response);
                        }
                    }
                }
                
                // Trigger Formidable's after submit handlers
                if (typeof frmFrontForm !== 'undefined' && frmFrontForm.afterFormSubmitted) {
                    frmFrontForm.afterFormSubmitted($form, response);
                }
                
                // Check for redirect in response
                if (response && response.redirect) {
                    window.location.href = response.redirect;
                } else if (response && response.content) {
                    // Display success message
                    $form.closest('.frm_forms').html(response.content);
                }
                
                if (resetFlags) resetFlags();
            },
            error: function(xhr, status, error) {
                console.error('Form submission error:', error);
                
                if (typeof displayFormError === 'function') {
                    displayFormError($form, 'Form submission failed. Please try again.');
                } else {
                    alert('Form submission failed. Please try again.');
                }
                
                if (resetFlags) resetFlags();
            }
        });
    }
    // CHECKOUT REDIRECT FUNCTION (NO FORM ENTRY)
    function executeCheckoutRedirect($form, form_id, productData, resetFlags) {
        if ($form.data('fwc-processing')) {
            if (resetFlags) resetFlags();
            return;
        }

        $form.find('.frm_woo_custom_error, .fwc-error-message').remove();
        
        if (typeof validateFormidableRequiredFields === 'function' && !validateFormidableRequiredFields($form)) {
            if (typeof asen_form_failure === 'function') {
                asen_form_failure(form_id);
            }
            if (resetFlags) resetFlags();
            return;
        }

            $form.data('fwc-processing', true);

            const $submitBtn = $form.find('input[type="submit"], button[type="submit"]');            
            let originalBtnValue = null;
            let originalBtnHtml = null;
            
            if ($submitBtn.is('button')) {
                originalBtnHtml = $submitBtn.html();
                $submitBtn.prop('disabled', true).html('Processing...');
            } else {
                originalBtnValue = $submitBtn.val();
                $submitBtn.prop('disabled', true).val('Processing...');
            }

            const formData = {};
            $form.serializeArray().forEach(item => {
                formData[item.name] = item.value;
            });            
            $.ajax({
                url: frmWoo.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'add_variation_to_cart_without_entry', // CHANGED ACTION NAME
                    nonce: frmWoo.nonce,
                    form_id: form_id,
                    clear_cart: true,

                    product_id: productData.product_id,
                    variation_id: productData.variation_id || 0,
                    custom_price: productData.custom_price || 0,
                    simple_product_list_ids: productData.simple_product_ids || '',
                    simple_product_list_prices: productData.simple_product_prices || '',
                    form_data: JSON.stringify(formData)
                },
                success: function (res) {
                    if (res.success && frmWoo.checkout_url) {
                        if ($submitBtn.is('button')) {
                            $submitBtn.html('Redirecting...');
                        } else {
                            $submitBtn.val('Redirecting...');
                        }
                        window.location.href = frmWoo.checkout_url;
                        return;
                    }
                    if (typeof displayFormError === 'function') {
                        displayFormError($form, res.data?.message || 'Could not add product to cart.');
                    }

                    if ($submitBtn.is('button')) {
                        $submitBtn.prop('disabled', false).html(originalBtnHtml);
                    } else {
                        $submitBtn.prop('disabled', false).val(originalBtnValue);
                    }
                    $form.data('fwc-processing', false);
                    window.frmWooCustomProcessing = false;
                    window.frmWooBypassNormalSubmit = false;
                },
                error: function (xhr, status, error) {
                    console.error('Cart AJAX error:', {xhr, status, error});
                    
                    if (typeof displayFormError === 'function') {
                        displayFormError($form, 'An error occurred. Please try again.');
                    }
                    
                    if ($submitBtn.is('button')) {
                        $submitBtn.prop('disabled', false).html(originalBtnHtml);
                    } else {
                        $submitBtn.prop('disabled', false).val(originalBtnValue);
                    }
                    $form.data('fwc-processing', false);
                    window.frmWooCustomProcessing = false;
                    window.frmWooBypassNormalSubmit = false;
                }
            });
    }

    // =============================
    // CHECKOUT PAGE AUTO-FILL
    // =============================
    if ($('body').hasClass('woocommerce-checkout')) {
        const billingDataJson = sessionStorage.getItem('fwc_billing_data');
        
        if (billingDataJson) {
            try {
                const billingData = JSON.parse(billingDataJson);
                
                function populateBlocksCheckout() {
                    if (typeof wp === 'undefined' || !wp.data) {
                        return false;
                    }

                    try {
                        const customerStore = wp.data.select('wc/store/customer');
                        
                        if (!customerStore) {
                            return false;
                        }

                        const store = wp.data.dispatch('wc/store/customer');                        
                        if (!store || !store.setBillingAddress) {
                            return false;
                        }

                        store.setBillingAddress({
                            first_name: billingData.first_name || '',
                            last_name: billingData.last_name || '',
                            email: billingData.email || '',
                            phone: billingData.phone || ''
                        });                        
                        
                        if (store.setEmail && billingData.email) {
                            store.setEmail(billingData.email);
                        }
                        
                        sessionStorage.removeItem('fwc_billing_data');
                        return true;
                        
                    } catch (error) {
                        return false;
                    }
                }

                function populateClassicCheckout() {
                    let populated = false;
                    if (billingData.email) {
                        const $email = $('#email, input[name="email"], input[type="email"]');
                        if ($email.length) {
                            $email.val(billingData.email).trigger('change').trigger('input');
                            populated = true;
                        }
                    }

                    if (billingData.first_name) {
                        $('#billing_first_name').val(billingData.first_name).trigger('change').trigger('input');
                        populated = true;
                    }
                    
                    if (billingData.last_name) {
                        $('#billing_last_name').val(billingData.last_name).trigger('change').trigger('input');
                        populated = true;
                    }
                    
                    if (billingData.phone) {
                        $('#billing_phone').val(billingData.phone).trigger('change').trigger('input');
                        populated = true;
                    }

                    if (populated) {
                        sessionStorage.removeItem('fwc_billing_data');
                    }

                    return populated;
                }

                function waitForBlocks() {                    
                    return new Promise((resolve) => {
                        let checkCount = 0;
                        const maxChecks = 50;
                        
                        const checkInterval = setInterval(() => {
                            checkCount++;
                            
                            if (typeof wp !== 'undefined' && 
                                wp.data && 
                                wp.data.select('wc/store/customer')) {
                                clearInterval(checkInterval);
                                resolve(true);
                            } else if (checkCount >= maxChecks) {
                                clearInterval(checkInterval);
                                resolve(false);
                            }
                        }, 100);
                    });
                }

                async function autoFillCheckout() {                    
                    if ($('#billing_first_name').length > 0) {
                        if (populateClassicCheckout()) {
                            return;
                        }
                    }

                    const blocksReady = await waitForBlocks();
                    
                    if (blocksReady && populateBlocksCheckout()) {
                        return;
                    }

                    setTimeout(() => {
                        if (populateClassicCheckout()) {
                        } else {
                            sessionStorage.removeItem('fwc_billing_data');
                        }
                    }, 500);
                }
                autoFillCheckout();
                $(document.body).on('updated_checkout checkout_updated', function() {
                    const dataStillExists = sessionStorage.getItem('fwc_billing_data');
                    if (dataStillExists) {
                        setTimeout(autoFillCheckout, 100);
                    }
                });

            } catch (error) {
                sessionStorage.removeItem('fwc_billing_data');
            }
        }
    }

    // =============================
    // Handle variation selection
    // =============================
    $(document).on('click', '.select-variation-btn', function(e) {
        e.preventDefault();
        
        const $form = $(this).closest('form');
        if ($form.length) {
            $form.find('.frm_woo_custom_error, .fwc-error-message').fadeOut(300, function() {
                $(this).remove();
            });
        }
        
        const btn = this;
        const variationPrice = parseFloat(btn.dataset.price || "0");
        const variationId = btn.dataset.variationId || "";
        const parentProductId = btn.dataset.variationParentId || "";
        $(".frm-woocommerce-product-select").val(parentProductId);
        $(btn).closest('.matched-results').find('.select-variation-btn.active').removeClass('active');
        $(btn).addClass('active');

        $form.find('[name="selected_variation_id"]').val(variationId);
        $form.find('[name="selected_variation_parent_id"]').val(parentProductId);
        $form.find('[name="selected_variation_price"]').val(variationPrice);
        
        updateFinalTotal($form);
    });
    // =============================
    // Handle simple product checkboxes
    // =============================
    $(document).on('change', '.woo-simple-checkbox', function(e) {
        const $checkbox = $(this);
        const $form = $checkbox.closest('form');
        
        let ids = [];
        let prices = [];

        $form.find('.woo-simple-checkbox:checked').each(function () {
            ids.push(this.value);
            prices.push($(this).data('price'));
        });

        $form.find('[name="selected_simple_checkbox_ids"]').val(ids.join(','));
        $form.find('[name="selected_simple_checkbox_prices"]').val(prices.join(','));

        updateFinalTotal($form);
    });

    // =============================
    // Handle Language Bundle and similar checkboxes
    // =============================
    $(document).on('change', '.frm-woo-simple-product-wrapper input[type="checkbox"]', function() {
        const $form = $(this).closest('form');
        updateFinalTotal($form);
    });
    // =============================
    // Handle Language Bundle and similar checkboxes
    // =============================
// =============================
// Handle Language Bundle and similar checkboxes
// =============================
(function () {
    let debounceTimer = null;
    let lastUs = null;
    let lastCo = null;
    let variationsCache = null;
    let currentProductId = null;
    const DEBOUNCE_MS = 500;
    
    function isEmployeeFeedbackChecked() {
        return $(".employee_feedback input[type='checkbox']").is(":checked");
    }
    window.isEmployeeFeedbackChecked = isEmployeeFeedbackChecked;

    function applyVisibilityRules() {
        const isChecked = isEmployeeFeedbackChecked();
        const $form = $('.frm-show-form');
        const { us, co } = getValues();

        // If both US and CO are 0, keep .hidden_category_table visible and ignore checkbox
        if (us === 0 && co === 0) {
            $(".hidden_category_table").show();
            $(".matched-results").hide();
            return;
        }

        if (isChecked) {
            // Hide matched results, show hidden category
            $(".matched-results").hide();
            $(".hidden_category_table").show();
            
            // Remove 'active' class from variation buttons
            $(".select-variation-btn").removeClass("active selected");

            // Clear ONLY variation-related fields
            $form.find('[name="selected_variation_price"]').val('');
            $form.find('[name="selected_variation_id"]').val('');
            $form.find('[name="selected_variation_parent_id"]').val('');

            // Clear matched results UI elements (don't trigger change to avoid loops)
            const $matched = $(".matched-results");
            $matched.find('input[type="radio"]:checked').prop('checked', false);
            $matched.find('input[type="checkbox"]:checked').prop('checked', false);
            $matched.find('select').prop('selectedIndex', 0);
            
            // Recalculate total (will include Language Bundle but not variation)
            updateFinalTotal($form);
            
            // Scroll to hidden category table
            const $target = $(".hidden_category_table");
            if ($target.length) {
                $('html, body').animate({
                    scrollTop: $target.offset().top
                }, 600);
            }
        } else {
            // Show matched results, hide hidden category
            $(".hidden_category_table").hide();
            $(".matched-results").show();
            
            // Recalculate total
            updateFinalTotal($form);
        }
    }

    // Listen to employee feedback checkbox changes
    $(document).on("change.employeeFeedback", ".employee_feedback input[type='checkbox']", function () {
        applyVisibilityRules();
    });

    // Get employee values
    function getValues() {
        const us = parseInt($(".field-us-employees input").val(), 10) || 0;
        const co = parseInt($(".field-co-employees input").val(), 10) || 0;
        return { us, co };
    }

    // Fetch all variations from server
    function fetchAllVariations(product_id) {
        return new Promise((resolve, reject) => {
            if (currentProductId === product_id && variationsCache) {
                resolve(variationsCache);
                return;
            }

            const formData = new FormData();
            formData.append("action", "get_all_variations_emp");
            formData.append("product_id", product_id);
            formData.append("nonce", frmWoo.nonce);

            fetch(frmWoo.ajax_url, { 
                method: "POST", 
                body: formData 
            })
            .then(r => r.json())
            .then(res => {
                if (!res || !res.success || !res.data) {
                    reject(new Error("Failed to fetch variations"));
                    return;
                }
                variationsCache = res.data;
                currentProductId = product_id;
                resolve(variationsCache);
            })
            .catch(err => reject(err));
        });
    }

    // Match variations based on US and CO employee counts
    function matchVariations(us, co, allVariations) {
        if (!us || !co || !allVariations) {
            return [];
        }
        const matched = [];
        const seenIds = new Set();
        
        for (const variation of allVariations) {
            if (seenIds.has(variation.id)) {
                continue;
            }

            const us_matches = (us >= variation.us_min && us <= variation.us_max);
            const co_matches = (co >= variation.co_min && co <= variation.co_max);
            
            if (us_matches && co_matches) {
                matched.push(variation);
                seenIds.add(variation.id);
            }
        }
        return matched;
    }

    // Render variation results as table
    function renderResults(items, product_id) {
        $(".matched-results").empty();

        if (!items || items.length === 0) {
            if (typeof wcValidationLabels !== 'undefined') {
                $(".matched-results").html(
                    "<p class='variable_div_error'>" + wcValidationLabels.no_matching_variations + "</p>"
                );
            }
            return;
        }
        
        const getTypeLabel = v => (v.type && v.type.trim()) ? v.type : '';

        const properties = Object.keys(items[0]).filter(k =>
            !['type', 'id', 'price', 'price_html', 'description', 'us_min', 'us_max', 'co_min', 'co_max'].includes(k)
        );

        const headerRow = `<tr>${items.map(v => `<th><strong>${v.type || 'N/A'}</strong></th>`).join('')}</tr>`;
        
        const firstRow = `
        <tr>
            ${items.map(v => `
                <td style="text-align:center; vertical-align:top;">
                    <div class="variation-description">
                        ${v.description || ''}
                    </div>
                    ${v.price_html ? `<div class="variation-price">${v.price_html}</div>` : ''}
                    <button
                        class="select-variation-btn"
                        data-price="${v.price || ''}"
                        data-variation-id="${v.id}"
                        data-variation-parent-id="${product_id}">
                        ${getTypeLabel(v) ? `Select ${getTypeLabel(v)}` : 'Select'}
                    </button>
                </td>
            `).join('')}
        </tr>`;

        const bodyRows = properties.map(prop => `
        <tr>
            ${items.map(v => `<td>${v[prop] || ''}</td>`).join('')}
        </tr>
        `).join('');

        $(".matched-results").html(`
        <table border="1" cellpadding="6" cellspacing="0">
            <tbody>
                ${headerRow}
                ${firstRow}
                ${bodyRows}
            </tbody>
        </table>
        `);
    }

    // Run matching logic
// Run matching logic
function runMatchingNow(us, co) {
    if (!isEmployeeFeedbackChecked()) {
        $('.hidden_category_table').hide();
        $(".matched-results").show();
    }
    
    if (!us || !co) {
        if (typeof wcValidationLabels !== 'undefined') {
            $(".matched-results").html(
                "<p class='variable_div_error'>" + wcValidationLabels.no_matching_variations + "</p>"
            );
        } else {
            $(".matched-results").empty();
        }
        return;
    }

    let product_id = $(".frm-woocommerce-product-select").val();
    
    // ✅ CHANGED: Don't update the dropdown - just use first product for lookup
    if (!product_id || product_id === '') {
        const firstProductId = $('.frm-woo-first-product').val();
        if (firstProductId) {
            product_id = firstProductId; // Use it for fetching variations
            // DON'T do: $(".frm-woocommerce-product-select").val(firstProductId);
        }
    }
    
    if (!product_id) {
        $(".matched-results").html("<p class='variable_div_error'>No product available</p>");
        return;
    }
    
    $(".matched-results").html(
        "<p class='variable_loading_msg'>" + wcValidationLabels.loading_content + "</p>"
    );

    fetchAllVariations(product_id)
        .then(allVariations => {
            const matched = matchVariations(us, co, allVariations);
            renderResults(matched, product_id);
        })
        .catch(err => {
            if (typeof wcValidationLabels !== 'undefined') {
                $(".matched-results").html(
                    "<p class='variable_div_error'>" + wcValidationLabels.no_matching_variations + "</p>"
                );
            } else {
                $(".matched-results").html(
                    "<p class='variable_div_error'>Error loading variations. Please try again.</p>"
                );
            }
        });
}

    // Clear selected variation
    function clearSelectedVariation() {
        const $form = $('.frm-show-form');        
        // Remove active class from all variation buttons
        $(".select-variation-btn").removeClass("active selected");
        
        // Clear variation-related hidden fields
        $form.find('[name="selected_variation_price"]').val('');
        $form.find('[name="selected_variation_id"]').val('');
        $form.find('[name="selected_variation_parent_id"]').val('');
        
        // Update the total to reflect removal of variation price
        updateFinalTotal($form);
    }

    // Schedule matching with debounce
    function scheduleRunMatching() {
        const { us, co } = getValues();
        
        // Check if values actually changed
        const valuesChanged = (us !== lastUs || co !== lastCo);
        
        if (!valuesChanged) {
            return;
        }
        
        // Clear selected variation when US or CO values change
        if (lastUs !== null || lastCo !== null) {
            clearSelectedVariation();
        }
        
        lastUs = us; 
        lastCo = co;
        
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => runMatchingNow(us, co), DEBOUNCE_MS);
    }

    // Product select change handler
    $(document).on("change", ".frm-woocommerce-product-select", function() {
        variationsCache = null;
        currentProductId = null;
        lastUs = null;
        lastCo = null;
        $(".matched-results").empty();
        clearSelectedVariation();
    });

    // Listen to input changes
    $(document).off("input.match change.match", ".field-us-employees input, .field-co-employees input");
    $(document).on("input.match change.match", ".field-us-employees input, .field-co-employees input", scheduleRunMatching);

    // Initialize on page load
    $(document).ready(function() {
        // Show .hidden_category_table on page load
        $(".hidden_category_table").show();
        
        applyVisibilityRules();
        const { us, co } = getValues();
        if (us && co) {
            lastUs = us;
            lastCo = co;
            runMatchingNow(us, co);
        }
    });
})()

    function recalcBaseTotal($form) {
        let total = 0;
        
        // Check if employee feedback is enabled
        const efOn = $(".employee_feedback input[type='checkbox']").is(":checked");
        
        // Only add variation price if employee feedback is NOT checked
        if (!efOn) {
            total += parseFloat($form.find('[name="selected_variation_price"]').val() || 0);
        }
        
        // Add checkbox products from hidden fields (set by the handler at top of file)
        const checkboxPrices = $form.find('[name="selected_simple_checkbox_prices"]').val() || '';
        if (checkboxPrices) {
            checkboxPrices.split(',').forEach(p => {
                const price = parseFloat(p || 0);
                if (price > 0) total += price;
            });
        }
        
        // Add simple products from hidden fields (set by the handler at top of file)
        const simplePrices = $form.find('[name="selected_simple_product_prices"]').val() || '';
        if (simplePrices) {
            simplePrices.split(',').forEach(p => {
                const price = parseFloat(p || 0);
                if (price > 0) total += price;
            });
        }
        
        // Add WooCommerce products from hidden fields
        const wcPrices = $form.find('[name="selected_wc_product_prices"]').val() || '';
        if (wcPrices) {
            wcPrices.split(',').forEach(p => {
                const price = parseFloat(p || 0);
                if (price > 0) total += price;
            });
        }

        
        return total;
    }

    function updateFinalTotal($form) {
        if (!$form || !$form.length) {
            $form = $('.frm-show-form');
        }
        
        const total = recalcBaseTotal($form);
        
        // Update displayed total
        $form.find('.frm_form_field p.frm_total_formatted')
            .first()
            .text('$' + total.toFixed(2));
        
        // Update hidden total field
        const $totalInput = $form.find('#field_sqxty');
        if ($totalInput.length) {
            $totalInput.val(total.toFixed(2));
            
            // Remove error state
            $totalInput.attr('aria-invalid', 'false');
            $totalInput.siblings('.frm_error').remove();
        }
    }

    // Make functions globally available
    window.recalcBaseTotal = recalcBaseTotal;
    window.updateFinalTotal = updateFinalTotal;

    // =============================
    // Restore selections on frmAfterUpdateForm / frmPageChanged
    // =============================
    $(document).on('frmAfterUpdateForm frmPageChanged', function(event, form){
        if(!form) return;

        const $form = $(form);
        window.frmWooValidationPassed = false;

        ['selected_variation_parent_id','selected_variation_id','selected_variation_price','selected_simple_checkbox_ids','selected_simple_checkbox_prices'].forEach(createHiddenField);

        const savedVariationId = $form.find('[name="selected_variation_id"]').val();
        if(savedVariationId){ 
            $form.find(`.select-variation-btn[data-variation-id="${savedVariationId}"]`).addClass('active'); 
        }

        const savedSimpleIds = $form.find('[name="selected_simple_checkbox_ids"]').val();
        if(savedSimpleIds){ 
            savedSimpleIds.split(',').forEach(id => { 
                $form.find(`.woo-simple-checkbox[value="${id}"]`).prop('checked', true); 
            }); 
        }

        updateFinalTotal($form);
    });
        document.querySelectorAll('.woocommerce_variation_custom_div, .frm-woocommerce-product-select').forEach(el => { el.style.display = 'none'; });
        document.querySelectorAll('.hidden_category_table').forEach(el => { el.setAttribute('disabled','disabled'); el.replaceWith(el.cloneNode(true)); });
    });
    jQuery(document).ready(function($) {
        // Trigger calculation for any pre-selected values
        $('.frm-woo-simple-product-wrapper radio:checked, select[data-original-type="woocommerce_simple_product"]').trigger('change');
    });

    /**
     * ======================================
     * GA4 – Centralized Form Tracking Helpers
     * ======================================
     */

    function asen_form_failure(formId, errorCount = 1) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'form_error',
            form_id: formId,
            error_count: errorCount,
            status: 'error'
        });
        
        if (typeof gtag !== 'undefined') {
            gtag('event', 'form_error', {
                form_id: formId,              
                error_count: errorCount,      
                status: 'error'
            });
        }
    }

