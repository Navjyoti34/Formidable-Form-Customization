// jQuery run on when Woo cart update
jQuery('body').on('updated_checkout', function (event) {
    gpm_set_gift_js()
});

function isValidValue(value) {
  return value.trim() !== '';
}

const gpmgetCookie = (c_name) => {
    const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(`${c_name}=`));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : null;
};

const gpmsetCookie = (name, value, exdays) => {
    const d = new Date();
    d.setTime(d.getTime() + exdays * 24 * 60 * 60 * 1000);
    document.cookie = `${name}=${escape(value)}; expires=${d.toGMTString()}; path=/`;
};

const getCurrentContentLength = (content, max) => {
    const maxLength = max;
    return content.length <= maxLength;
};

const dateFormat = (e) => {
    const t = e.target || e.srcElement || e;
    t.setAttribute('data-date', moment(t.value, 'YYYY-MM-DD').format(t.getAttribute('data-date-format')));
};

const delcookie = (name) => {
    setcookie(name, '', -1);
};

const getKeyByValue = (object, value) => {
    return Object.keys(object).find((key) => object[key] === value);
};

const update_physical_totals = (front_gift_checkboxes) => {
    // Helper function to count occurrences of a value in an array
    const countOccurrences = (arr, val) => arr.reduce((a, v) => (v === val ? a + 1 : a), 0);

    const physicalProducts = document.querySelectorAll("[data-product-virtual=true]");
    let totalGiftCheckboxes = 0;
    let totalPhysicalProduct = 0;
    const pids = [];

    // Loop through each gift checkbox to update totals and collect product IDs
    for (const giftCheckbox of front_gift_checkboxes) {
        const cartKey = giftCheckbox.getAttribute('data-product-cart-key');
        const cookieData = JSON.parse(gpmgetCookie(`ggp-${cartKey}`) || '{}');

        // Update total gift checkboxes
        const giftTotal = parseInt(cookieData['physical-gift-total']);
        if (Number.isInteger(giftTotal)) {
            totalGiftCheckboxes += giftTotal;
        }

        // Add main product IDs for each "on" occurrence
        const onCount = countOccurrences(Object.values(cookieData), "on");
        for (let i = 0; i < onCount; i++) {
            pids.push(giftCheckbox.getAttribute('data-main-product-id'));
        }
    }

    // Update the hidden input field for gift purchase list
    document.querySelector("[name='gift_purchase_list']").value = pids.join(",");

    // Calculate total quantity of physical products
    physicalProducts.forEach(product => {
        totalPhysicalProduct += parseInt(product.getAttribute('data-product-qty')) || 0;
    });

    // Update shipping fields visibility based on totals
    const showShippingFields = totalPhysicalProduct > totalGiftCheckboxes;
    document.querySelector('.woocommerce-shipping-fields').style.display = showShippingFields ? "unset" : "none";
    document.querySelector("[name='physical_gift_purchase']").value = showShippingFields.toString();
};


const gpm_set_gift_js = () => {
    const frontGiftCheckboxes = document.querySelectorAll(".is-this-a-gift a");

    frontGiftCheckboxes.forEach((checkbox) => {
        try {
            const productCartKey = checkbox.getAttribute("data-product-cart-key");
            document.querySelector(`[data-product-cart-key="${productCartKey}"] [id*="is-that-a-gift-"]`).checked = getKeyByValue(
                JSON.parse(gpmgetCookie(`ggp-${productCartKey}`)), "on"
            );

            const parsedCookie = JSON.parse(gpmgetCookie(`ggp-${productCartKey}`));
            const gifteeEmails = [];
            const giftElement = document.querySelector(`[data-product-cart-key="${productCartKey}"]`);

            for (const key in parsedCookie) {
                if (key.includes("gsem-")) {
                    gifteeEmails.push(parsedCookie[key]);
                }
            }

            giftElement.setAttribute('data-giftee-emails', gifteeEmails.join(","));
        } catch (err) {}

        update_physical_totals(frontGiftCheckboxes);

        if (checkbox.getAttribute("listener")) return;
        
        checkbox.setAttribute("listener", "true");

        checkbox.addEventListener('click', function (event) {
            update_physical_totals(frontGiftCheckboxes);

            const firstName = (document.querySelector('input[name="billing_first_name"]').value || "First").replace(/[^a-zA-Z0-9 ]/g, "");
            const lastName = (document.querySelector('input[name="billing_last_name"]').value || "Last").replace(/[^a-zA-Z0-9 ]/g, "");
            const senderName = `${firstName} ${lastName}`;

            const productAttributes = {
                product_id: this.getAttribute("data-product-id"),
                product_cart_key: this.getAttribute("data-product-cart-key"),
                product_img: this.getAttribute("data-product-image"),
                product_title: this.getAttribute("data-product-title"),
                product_qty: this.getAttribute("data-product-qty"),
                product_physical: this.getAttribute("data-product-virtual") === "true",
                product_main_id: this.getAttribute("data-main-product-id"),
            };

            const giftAttributes = {
                gift_it: "checked",
                gift_message: "This Product is a gift?",
                gift_table: "",
            };

            for (let i = 0; i < productAttributes.product_qty; i++) {
                try {
                    const attemptCookie = JSON.parse(gpmgetCookie(`ggp-${productAttributes.product_cart_key}`));
                    giftAttributes.gift_options_show = "gift-options-form";

                    if (attemptCookie[`gft-${productAttributes.product_cart_key}-${i}`] !== "on") {
                        giftAttributes.gift_options_show += " d-none";
                        giftAttributes.gift_it = "";
                    }
                } catch (err) {}

                giftAttributes.gift_message = (productAttributes.product_qty == 1) ? giftAttributes.gift_message : "Is this one a gift?";
                
                const field = (() => {
                    const forms = ["physical_gift_form", "digital_gift_form"];
                    return document.getElementById(forms[productAttributes.product_physical ? 0 : 1]).innerHTML;
                })();

                giftAttributes.gift_table += `
                    <table id="item-${i}" data-id="${i}" data-product-id="${productAttributes.product_id}" data-cart-key="${productAttributes.product_cart_key}">
                        <tr>
                            <td style="width:155px">
                                <img alt="" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" decoding="async" height="335" loading="lazy" sizes="(max-width: 247px) 100vw, 247px" src="${productAttributes.product_img}"/>
                            <td>
                            <div id="product-title">${productAttributes.product_title}</div>
                            <input type="hidden" name="id-${productAttributes.product_cart_key}-${i}" value="${productAttributes.product_id}">
                            <input type="hidden" name="cart-key" value="${productAttributes.product_cart_key}">
                            <fieldset>
                                <label>
                                    <span><strong>Sender Name:</strong> <p id="sender-name">${senderName}</p></span>
                                </label>
                            </fieldset>
                            <fieldset style="width: fit-content; cursor: pointer;">
                                <label>
                                    <input type="checkbox" class="input-checkbox" style="cursor: pointer;" name="gft-${productAttributes.product_cart_key}-${i}" id="is-this-one-a-gift-${i}" ${giftAttributes.gift_it}> <span id="would-you-gift">${giftAttributes.gift_message}</span>
                                </label>
                            </fieldset>
                           
                        </tr>
                         <tr>
                        <td colspan="2">
                           <div id="gift-options" class="${giftAttributes.gift_options_show}" data-id="${productAttributes.product_cart_key}-${i}">
                                ${field}
                            </div>
                        </td>
                        </tr>
                    </table>`;
                
                if (i !== (productAttributes.product_qty - 1)) {
                    giftAttributes.gift_table += '<hr style="height: 1px; width: 97%; margin: auto; margin-top: 10px; margin-bottom: 10px;">';
                }
            }

            if (!check_billing_first_last()) return;

            bootbox.dialog({
                title: 'Your gift details',
                message: `<div class="bootbox-body">
                    <form id="contact" action="" method="post">
                        ${giftAttributes.gift_table}
                    </form>
                </div>`,
                buttons: {
                    
                    save: {
                        label: "Save",
                        callback: function (result) {
                            const inputs = document.querySelectorAll('input[name^="gsem-"]');
                            let currentUserEmail = document.querySelector('input[name="gift_current_user_email"]') || document.querySelector('input[name="billing_email"]');
                            let errors = false;
                            add_error_to_gift_modal();

                            const currentUserEmailValue = currentUserEmail.value;

                            inputs.forEach((input) => {
                                if (currentUserEmailValue === input.value) {
                                    add_error_to_gift_modal('Sorry, but you cannot send a gift to yourself.');
                                    errors = true;
                                }
                            });

                            const contactForm = document.getElementById('contact');
                            if (!contactForm.reportValidity()) return false;
                            
                            const formData = new FormData(contactForm);
                            const formObject = Object.fromEntries(formData.entries());
                            if (productAttributes.product_physical) {
                                formObject['physical-gift-total'] = document.querySelectorAll("input[id*='is-this-one-a-gift']:checked").length;
                            }

                            const cartKey = formObject['cart-key'];
                            const giftCheckbox = document.querySelector(`[data-product-cart-key="${productAttributes.product_cart_key}"] [id*="is-that-a-gift-"]`);
                            const giftElement = document.querySelector(`[data-product-cart-key="${productAttributes.product_cart_key}"]`);

                            let gifteeEmails = [];
                            document.querySelectorAll('table[id*="item-"]').forEach((row) => {
                                const emailInput = row.querySelector('input[name*="gsem-"]');
                                if (emailInput && row.querySelector('.gift-options-form').offsetParent) {
                                    gifteeEmails.push(emailInput.value);
                                }
                            });

                            const joinedEmails = gifteeEmails.join(',');
                            giftElement.setAttribute('data-giftee-emails', joinedEmails);

                            document.querySelectorAll(`a[data-product-cart-key][data-main-product-id="${productAttributes.product_main_id}"]:not([data-product-cart-key="${productAttributes.product_cart_key}"])`).forEach((link) => {
                                const allGifteeEmails = link.getAttribute('data-giftee-emails');
                                if (allGifteeEmails && allGifteeEmails.split(',').some(email => gifteeEmails.includes(email))) {
                                    add_error_to_gift_modal('Apologies, but this item cannot be gifted to the same recipient.');
                                    errors = true;
                                }
                            });

                            if (errors) return false;

                            giftCheckbox.checked = !!getKeyByValue(formObject, "on");
                            gpmsetCookie(`ggp-${cartKey}`, JSON.stringify(formObject));
                            update_physical_totals(frontGiftCheckboxes);
                        }
                    },
                    cancel: { label: "Cancel" }
                }
            }).on('shown.bs.modal', function () {
                document.querySelectorAll('[name*="gsfd"]').forEach((date) => {
                    const currentDate = moment().format(date.getAttribute('data-date-format'));
                    date.setAttribute('data-date', currentDate);
                    date.setAttribute('value', moment().format('yyyy-MM-DD'));
                });
                try {
                    document.querySelector('.modal-backdrop').addEventListener('click', () => bootbox.hideAll());
                } catch (err) {}

                const modalController = new GiftOptionsModalController();
                modalController.init();
            });
        });
    });
};


function add_error_to_gift_modal(errorMessageText = false) {
    const parentDiv = document.querySelector('.bootbox-body');
    const existingErrorMessages = parentDiv.querySelectorAll('.woocommerce-error-custom');

    if (!errorMessageText) {
        existingErrorMessages.forEach(error => error.classList.add('d-none'));
        return false;
    }

    let existingErrorMessage = Array.from(existingErrorMessages).find(
        error => error.textContent.trim() === errorMessageText
    );

    if (!existingErrorMessage) {
        const errorMessage = document.createElement('div');
        errorMessage.className = 'woocommerce-error-custom woocommerce-error';
        errorMessage.innerHTML = `<li>${errorMessageText}</li>`;
        
        parentDiv.insertBefore(errorMessage, parentDiv.firstChild);
    }
}



function check_billing_first_last() {
    const firstNameInput = document.querySelector('input[name="billing_first_name"]');
    const lastNameInput = document.querySelector('input[name="billing_last_name"]');
    const emailInput = document.querySelector('input[name="billing_email"]');
    
    const isValid = [firstNameInput, lastNameInput, emailInput].every(input => isValidValue(input.value));

    if (!isValid) {
        const errorMessage = 'Please be sure your billing first, last name, and email are filled in before gifting an item.';

        if (!isValidValue(firstNameInput.value)) {
            firstNameInput.classList.add('woocommerce-invalid', 'woocommerce-invalid-required-field');
        }
        if (!isValidValue(lastNameInput.value)) {
            lastNameInput.classList.add('woocommerce-invalid', 'woocommerce-invalid-required-field');
        }

        let errorElement = document.querySelector('form[name="checkout"] .woocommerce-error-custom');
        
        if (errorElement) {
            const hasErrorMessage = Array.from(errorElement.querySelectorAll('li'))
                .some(li => li.textContent.includes(errorMessage));
            
            if (!hasErrorMessage) {
                errorElement.insertAdjacentHTML('afterbegin', `<li>${errorMessage}</li>`);
            }
        } else {
            errorElement = document.createElement('div');
            errorElement.innerHTML = `<li>${errorMessage}</li>`;
            errorElement.classList.add('woocommerce-error-custom', 'woocommerce-error');
            document.querySelector('form[name="checkout"]').insertAdjacentElement('afterbegin', errorElement);
        }

        return false;
    } else {
        const errorElement = document.querySelector('form[name="checkout"] .woocommerce-error-custom');
        if (errorElement) errorElement.remove();
        return true;
    }
}

class GiftOptionsModalController {
    constructor() {
        this.giftCheckboxes = document.querySelectorAll("[id*='is-this-one-a-gift']");
        this.requiredInputs = document.querySelectorAll('#gift-options p.form-row');
    }

    init() {
        this.requiredInputs.forEach(input => this.setFieldAttributes(input));
        this.giftCheckboxes.forEach(checkbox => {
            this.toggleGiftForm(checkbox);
            checkbox.addEventListener('click', event => this.toggleGiftForm(event.target));
        });
    }

    toggleGiftForm(checkbox) {
        const tableElement = checkbox.closest('table');
        const parentTableId = tableElement.getAttribute("id");
        const parentCartId = tableElement.getAttribute("data-cart-key");
        const dataId = tableElement.getAttribute("data-id");

        const associatedFields = document.querySelectorAll(`#gift-options[data-id*='${parentCartId}-${dataId}'] input, 
            [data-id*='${parentCartId}-${dataId}'] select, [data-id*='${parentCartId}-${dataId}'] textarea`);

        const giftOptionsElement = document.querySelector(`#${parentTableId} #gift-options`);
        if (giftOptionsElement) {
            giftOptionsElement.style.cssText = checkbox.checked
                ? 'pointer-events: unset !important; opacity: 1 !important; display: block !important;'
                : 'display: none !important;';
        }

        associatedFields.forEach(field => field.disabled = !checkbox.checked);

        document.querySelectorAll('[name*="gsms-"]').forEach(input => {
            input.addEventListener("keyup", this.updateGiftMessageLength.bind(this));
            this.updateGiftMessageLength(input);
        });
    }

    updateGiftMessageLength(input) {
        const target = input.target || input.srcElement || input;
        const maxLength = target.getAttribute('maxlength');
        const remainingLength = maxLength - target.value.length;
        const textColor = remainingLength > 0 ? "#6D6E71" : "#eb0000";

        target.parentElement.parentNode.querySelector('label').innerHTML = `
            <div style="display: flex;">
                <div style="flex: 1;">Message for gift (optional)</div>
                <div style="display:contents; font-size:0.8rem; text-align:right; flex: 1; color:${textColor};">
                    ${remainingLength} characters remaining
                </div>
            </div>`;
    }

    setFieldAttributes(input) {
        const inputField = input.querySelector('select, input, textarea');
        if (!inputField) return;

        inputField.required = input.classList.contains('validate-required');

        const dataSuffix = `-${input.closest('div[data-id]').getAttribute('data-id')}`;
        if (!inputField.name.includes(dataSuffix)) {
            inputField.name += dataSuffix;
        }

        const parentTable = input.closest('table');
        const parentCartId = parentTable.getAttribute('data-cart-key');
        const cookieData = JSON.parse(decodeURIComponent(gpmgetCookie(`ggp-${parentCartId}`)) || '{}');

        if (cookieData && cookieData[inputField.name]) {
            const cookieValue = cookieData[inputField.name];
            inputField.value = cookieValue;
            const dateField = input.querySelector('[name*="gsfd"]');
            if (dateField) {
                dateField.setAttribute('data-date', moment(cookieValue, 'yyyy-MM-DD').format(dateField.getAttribute('data-date-format')));
                dateField.setAttribute("value", cookieValue);
            }
        }
    }
}


