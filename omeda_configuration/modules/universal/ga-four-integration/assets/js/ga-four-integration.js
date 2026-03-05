document.addEventListener("DOMContentLoaded", function () {
    jQuery(document).ready(function ($) {
        function checkBillingFields() {
            var allFieldsFilled = true;

            var billingFieldsDiv = document.querySelector('.woocommerce-billing-fields');
            if (billingFieldsDiv) {
                var requiredFields = billingFieldsDiv.querySelectorAll('.validate-required input, .validate-required select, .validate-required textarea');
                for (var i = 0; i < requiredFields.length; i++) {
                    if (requiredFields[i].value === '') {
                        allFieldsFilled = false;
                        break;
                    }
                }
            }

            if (allFieldsFilled) {
                 const params = {
                    layer_request: 'add_shipping_info'
                };

                sendGA4Request(params)
                    .then(responseData => {
                        if (responseData.error) {
                            console.log('[GA4]', responseData.msg);
                            return;
                        }
                        addGAScriptToHead(responseData);
                    });
            }
        }

        var billingFieldsDiv = document.querySelector('.woocommerce-billing-fields');
        if (billingFieldsDiv) {
            var requiredFields = billingFieldsDiv.querySelectorAll('.validate-required input, .validate-required select, .validate-required textarea');
            for (var i = 0; i < requiredFields.length; i++) {
                requiredFields[i].addEventListener('input', checkBillingFields);
            }
        }

        $(document.body).on('updated_checkout', checkBillingFields);

        function sendGA4Request(params) {
            const queryString = Object.keys(params)
                .map(key => key + '=' + encodeURIComponent(params[key]))
                .join('&');

            const endpointURL = '/wp-json/ga/v1/request?' + queryString;

            return fetch(endpointURL)
                .then(response => {
                    if (response.ok) {
                        return response.json();
                    } else {
                        throw new Error(`Error: ${response.status} ${response.statusText}`);
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                });
        }

        function addGAScriptToHead(responseData) {
            var firstComment = document.createComment(' GA4 Integration Data Layer Tag ');
            var script = document.createElement('script');
            script.text = 'dataLayer.push(' + responseData + ');';
            document.head.appendChild(firstComment);
            document.head.appendChild(script);
            document.head.appendChild(document.createComment(' GA4 Integration Data Layer Tag \\ '));
        }

        $(document.body).on('change', 'input[name="payment_method"]', function() {
            const params = {
                payment_method: $(this).val(),
                layer_request: 'add_payment_info'
            };

            sendGA4Request(params)
                .then(responseData => {
                    if (responseData.error) {
                        console.log('[GA4]', responseData.msg);
                        return;
                    }
                    addGAScriptToHead(responseData);
                });
        });

        $(document).on('added_to_cart', function (event, fragments, cart_hash, $button) {
            var productID = $button.data('product_id');
            var productQuantity = $button.data('quantity');

            const params = {
                product: productID,
                product_quantity: productQuantity,
                layer_request: 'add_to_cart'
            };

            sendGA4Request(params)
                .then(responseData => {
                    if (responseData.error) {
                        console.log('[GA4]', responseData.msg);
                        return;
                    }
                    addGAScriptToHead(responseData);
                });
        });

        $('.product-remove').on('click', '.remove', function() {
            handleCartRemoval($(this).data('product_id'));
        });

        $(document.body).on('removed_from_cart updated_cart_totals', function(event, fragments, cart_hash, $button) {
            handleCartRemoval($button.data('product_id'));
        });

        function handleCartRemoval(productID) {
            const params = {
                product: productID,
                layer_request: 'remove_from_cart'
            };

            sendGA4Request(params)
                .then(responseData => {
                    if (responseData.error) {
                        console.log('[GA4]', responseData.msg);
                        return;
                    }

                    addGAScriptToHead(responseData);
                });
        }
    });
});