jQuery('body').on('updated_checkout', function (event) {
    const priceElement = document.querySelector('tr.cart-subtotal').querySelector('span.woocommerce-Price-amount.amount');
    const priceString = priceElement.innerText.replace(/[^\d.-]/g, '');
    const price = parseFloat(priceString);

    const checkbox = document.getElementsByName("newsletter_checkbox")[0];
    const newsletterSignup = document.querySelector('#newsletter_checkbox_field');

    function handleClick(event) {
        event.preventDefault();

        checkbox.setAttribute('checked', true);
        checkbox.setAttribute('value', 1);
        checkbox.checked = true;
    }

    const isActive = price <= 0.00;

    checkbox.readOnly = isActive;
    newsletterSignup.classList.toggle('active', isActive);
    newsletterSignup.style.cursor = isActive ? 'default' : 'pointer';

    if (isActive) {
        checkbox.addEventListener("click", handleClick);
    } else {
        checkbox.removeEventListener("click", handleClick);
        checkbox.addEventListener("click", toggle_newsletter_checkbox);
    }

});

function toggle_newsletter_checkbox() {
     const newsletterCheckbox = document.getElementById('newsletter_checkbox');

    if (!newsletterCheckbox.hasAttribute('readonly')) {
        const checked = newsletterCheckbox.getAttribute('checked');
        if(checked) {
            newsletterCheckbox.removeAttribute("checked");
            newsletterCheckbox.setAttribute('value', 0);
            newsletterCheckbox.checked = false;
        } else {
            newsletterCheckbox.setAttribute('checked', !checked);
            newsletterCheckbox.setAttribute('value', 1);
            newsletterCheckbox.checked = true;
        }
      }
}