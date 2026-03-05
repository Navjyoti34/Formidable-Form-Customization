function check_cart_amount_for_reward() {
    var element = document.querySelector('.coupon-code');
    var items = document.querySelector('td[data-title="Total"], .order-total .woocommerce-Price-amount');

    if (typeof (element) != 'undefined' && element != null) {
        element.style.display = "none";
    }

    if (typeof (items) == 'undefined' || items == null) {
        return;
    }

    if (Math.round(parseFloat(items.textContent.replace(/[^0-9.-]+/g, ''))) >= generator.coupon_gen_over_amount) {
        //console.log(`Cart is over the dollar amount of $${generator.coupon_gen_over_amount} - displaying notification.`);

        if (typeof (element) != 'undefined' && element != null) {
            element.style.display = "block";
        } else {
            let div = document.createElement('div');
            var htmlString = `<span class="coupon-code" style="text-align:center;display:block;padding:10px;width:100%;background-color:lightblue;border-radius:10px;">Woohoo! This order will unlock a $${generator.discount_amount} reward.<br/>Complete your purchase and check your email for your exclusive code to use on your next visit.</span>`;
            div.innerHTML = htmlString;
            let div1 = document.querySelector('.entry-header');
            div1.parentElement.insertBefore(div.firstChild, div1.nextSibling);
        }
        return;
    }

    if (typeof (element) != 'undefined' && element != null) {
        element.style.display = "none";
    }

    //console.log(`Cart not over the amount of $${generator.coupon_gen_over_amount} - not displaying notification.`);
}

document.addEventListener("DOMContentLoaded", () => {
    jQuery(document.body).on("updated_cart_totals updated_wc_div wc_fragments_loaded", check_cart_amount_for_reward);

    jQuery('body').on('updated_checkout', () => {
        if (jQuery('body').is('.woocommerce-checkout')) {
            check_cart_amount_for_reward();
        }
    });

    var cartForm = document.querySelector('.woocommerce-cart-form');

    if (cartForm) {
        check_cart_amount_for_reward();
    }
});