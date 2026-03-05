<?php
/**
 * GA4 / GTM dataLayer
 * - Formidable Forms (submit + error)
 * - WooCommerce Checkout (begin + error)
 * - WooCommerce Purchase
 */

add_action( 'wp_footer', function () { ?>
    
    <script>
    (function ($) {

        /* =========================
         * FORMIDABLE FORMS
         * ========================= */

    $(document).on('frmFormErrors', function (event, form, errors) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'form_error',
            form_id: form.id,
            error_count: Object.keys(errors).length,
            status: 'error'
        });
        
        if (typeof gtag !== 'undefined') {
            gtag('event', 'form_error', {
                form_id: form.id,
                error_count: Object.keys(errors).length,
                status: 'error'
            });
        }
    });
    })(jQuery);
    </script>

<?php
}, 100 );
