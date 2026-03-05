<?php
/**
 * ====================================================
 * Modified PDF Generation with Surcharge & Conditional Shipping
 * ====================================================
 */

    // Remove the original PDF generation function
    remove_action('init', 'nexolis_process_order_pdf_download');
    add_action('init', 'custom_nexolis_process_order_pdf_download');

    function custom_nexolis_process_order_pdf_download() {
        if ( ! isset($_GET['download_order_pdf'], $_GET['_wpnonce']) ) {
            return;
        }
        $order_id = absint( wp_unslash($_GET['download_order_pdf']) );
        $nonce    = sanitize_text_field( wp_unslash($_GET['_wpnonce']) );

        if ( ! $order_id ) {
            wp_die('Invalid Order ID.');
        }

        if ( ! wp_verify_nonce($nonce, 'download_order_pdf') ) {
            wp_die('Security check failed.');
        }

        $order = wc_get_order($order_id);
        if ( ! $order || $order->get_user_id() !== get_current_user_id() ) {
            wp_die('You are not allowed to download this order.');
        }
        if ( ! class_exists('FPDF') ) {
            $fpdf_path = WP_PLUGIN_DIR . '/pdf-invoice-generator/lib/fpdf/fpdf.php';
            if (file_exists($fpdf_path)) {
                require_once $fpdf_path;
            } else {
                wp_die('FPDF library not found. Please check the plugin installation.');
            }
        }

        $pdf_file = custom_nexolis_generate_order_pdf($order_id);

        if ( ! $pdf_file ) {
            wp_die('PDF generation failed.');
        }

        $upload_dir = wp_upload_dir();
        $base_dir   = realpath($upload_dir['basedir'] . '/invoices');
        $real_file  = realpath($pdf_file);

        if ( ! $real_file || strpos($real_file, $base_dir) !== 0 ) {
            wp_die('Invalid file access.');
        }

        if ( ! file_exists($real_file) ) {
            wp_die('PDF file not found.');
        }

        global $wp_filesystem;
        if ( empty($wp_filesystem) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $file_content = $wp_filesystem->get_contents($real_file);

        if ( $file_content === false ) {
            wp_die('PDF file could not be read.');
        }

        if ( ob_get_length() ) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($real_file) . '"');
        header('Content-Length: ' . strlen($file_content));

        echo $file_content;
        exit;
    }

    function custom_nexolis_generate_order_pdf($order_id) {

        $order = wc_get_order($order_id);
        if ( ! $order ) { return false; }

        /* =====================================================
        * Load FPDF
        * ===================================================== */
        if ( ! class_exists('FPDF') ) {
            $fpdf_path = WP_PLUGIN_DIR . '/pdf-invoice-generator/lib/fpdf/fpdf.php';
            if ( file_exists($fpdf_path) ) {
                require_once $fpdf_path;
            } else {
                return false;
            }
        }

        $pdf = new FPDF();
        $pdf->AddPage();

        $pdf_invoice = get_option('nexo_pdf_invoice_settings');

        /* =====================================================
        * Header / Logo
        * ===================================================== */

        $logo        = $pdf_invoice['header_logo'] ?? '';
        $header_text = $pdf_invoice['header_text'] ?? '';

        $pdf->SetXY(10, 10); 
        $logo_rendered = false;
        if ( ! empty($logo) ) {
            $upload = wp_upload_dir();

            // Convert URL → filesystem path if needed
            if ( strpos($logo, $upload['baseurl']) !== false ) {
                $logo_path = str_replace($upload['baseurl'], $upload['basedir'], $logo);
            } else {
                $logo_path = $logo;
            }

            $logo_path = urldecode($logo_path);

            if ( file_exists($logo_path) && is_readable($logo_path) ) {
                $pdf->Image($logo_path, 10, 10, 50);
                $logo_rendered = true;
            }
        }
        if ( ! $logo_rendered ) {
            $pdf->SetFont('Arial','B',28);
            $pdf->SetXY(10, 12);
            $pdf->Cell(0, 12, $header_text, 0, 1, 'L');
        }


        /* =====================================================
        * Store Info
        * ===================================================== */
        $pdf->SetXY(130,10);
        $pdf->SetFont('Arial','',10);
        $store_text  = "Store Address:\n";  // Header line
        $store_text .= get_option('woocommerce_store_address') . "\n";     // Address line 1
        $store_text .= get_option('woocommerce_store_address_2') . "\n";   
        $store_text .= "Postcode: " . get_option('woocommerce_store_postcode') . "\n";
        $store_text .= "City / Country: ". get_option('woocommerce_store_city') . ", " . get_option('woocommerce_default_country') . "\n";
        $phone = trim(get_option('woocommerce_store_phone'));
        if (!empty($phone)) {
            $store_text .= "Phone: " . $phone . "\n";
        }

        $email = wc_clean( get_option('woocommerce_custom_store_email') );

        if ( $email && is_email($email) ) {
            $store_text .= "Email: " . sanitize_email($email) . "\n";
        }

        // $website = site_url();
        // $website_wrapped = wordwrap($website, 90, "\n", true);
        // $store_text .= "Website: " . $website_wrapped;

        $pdf->MultiCell(180,8,$store_text);
        $pdf->Ln(5);

        /* =====================================================
        * Invoice Meta
        * ===================================================== */
        $pdf->SetFont('Arial','B',27);
        $pdf->Cell(0,10,'Receipt',0,1,'C');
        $pdf->Ln(5);

        $prefix = $pdf_invoice['invoice_prefix'] ?? 'INV-';
        $last   = (int) get_option('last_invoice_number',0);
        $last++;
        update_option('last_invoice_number',$last);
        update_post_meta($order_id,'_invoice_number',$prefix.$last);

        $date_format = $pdf_invoice['invoice_date_format'] ?? 'd/m/Y';
        $order_date  = $order->get_date_created()->date($date_format);

        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(90,8,"Receipt # {$prefix}{$last}",1);
        $pdf->Cell(90,8,"Order Date: {$order_date}",1,1);
        $pdf->Cell(90,8,"Payment Method: ".$order->get_payment_method_title(),1);
        $pdf->Cell(90,8,"",1,1);

        /* =====================================================
        * Billing / Shipping
        * ===================================================== */

        // Show shipping only if at least one shipping field exists AND it's different from billing.
        $ship_first = trim((string) $order->get_shipping_first_name());
        $ship_last  = trim((string) $order->get_shipping_last_name());
        $ship_addr1 = trim((string) $order->get_shipping_address_1());
        $ship_addr2 = trim((string) $order->get_shipping_address_2());
        $ship_city  = trim((string) $order->get_shipping_city());
        $ship_state = trim((string) $order->get_shipping_state());
        $ship_post  = trim((string) $order->get_shipping_postcode());
        $ship_code  = trim((string) $order->get_shipping_country());

        $bill_first = trim((string) $order->get_billing_first_name());
        $bill_last  = trim((string) $order->get_billing_last_name());
        $bill_addr1 = trim((string) $order->get_billing_address_1());
        $bill_addr2 = trim((string) $order->get_billing_address_2());
        $bill_city  = trim((string) $order->get_billing_city());
        $bill_state = trim((string) $order->get_billing_state());
        $bill_post  = trim((string) $order->get_billing_postcode());
        $bill_code  = trim((string) $order->get_billing_country());

        $shipping_filled = ($ship_first !== '' || $ship_last !== '' || $ship_addr1 !== '' || $ship_addr2 !== '' || $ship_city !== '' || $ship_state !== '' || $ship_post !== '' || $ship_code !== '');
        $shipping_diff   = (
            $ship_first !== $bill_first ||
            $ship_last  !== $bill_last  ||
            $ship_addr1 !== $bill_addr1 ||
            $ship_addr2 !== $bill_addr2 ||
            $ship_city  !== $bill_city  ||
            $ship_state !== $bill_state ||
            $ship_post  !== $bill_post  ||
            $ship_code  !== $bill_code
        );
        $has_shipping = $shipping_filled && $shipping_diff;

        $pdf->SetFont('Arial','B',12);
        if ($has_shipping) {
            $pdf->Cell(95, 8, __('Billing Details', 'pdf-invoice-generator'), 0, 0, 'C');
            $pdf->Cell(95, 8, __('Shipping Details', 'pdf-invoice-generator'), 0, 1, 'C');
        } else {
            $pdf->Cell(0, 8, __('Billing Details', 'pdf-invoice-generator'), 0, 1, 'C');
        }

        $pdf->SetFont('Arial','',10);
        $y = $pdf->GetY();

        $billing =
            "Customer: {$order->get_billing_first_name()} {$order->get_billing_last_name()}\n".
            "Address: {$order->get_billing_address_1()}\n".
            "City: {$order->get_billing_city()}\n".
            "State: {$order->get_billing_state()}\n".
            "Postcode: {$order->get_billing_postcode()}\n".
            "Country: {$order->get_billing_country()}\n".
            "Phone: {$order->get_billing_phone()}\n".
            "Email: {$order->get_billing_email()}";

        if ( $has_shipping ) {
            $pdf->MultiCell(95,5,$billing);
            $pdf->SetXY(105,$y);
            $pdf->MultiCell(95,5,
                "Customer: {$order->get_shipping_first_name()} {$order->get_shipping_last_name()}\n".
                "Address: {$order->get_shipping_address_1()}\n".
                "City: {$order->get_shipping_city()}\n".
                "State: {$order->get_shipping_state()}\n".
                "Postcode: {$order->get_shipping_postcode()}\n".
                "Country: {$order->get_shipping_country()}"
            );
        } else {
            $pdf->MultiCell(0,5,$billing);
        }

        /* =====================================================
        * Delivery Text (fallback supported)
        * ===================================================== */
        $days  = (int) ($pdf_invoice['delivery_days'] ?? 3);
        $ts    = strtotime("+{$days} days",$order->get_date_created()->getTimestamp());
        $dte   = date_i18n($date_format,$ts);
        $text  = $pdf_invoice['delivery_text'] ?? 'Your order will be delivered by {delivery_date}';

        $pdf->Ln(10);
        $pdf->SetFont('Arial','I',14);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 8, str_replace('{delivery_date}', $dte, $text), 0, 1, 'C');

        /* =====================================================
        * Items Table
        * ===================================================== */
        $currency = html_entity_decode(get_woocommerce_currency_symbol());
        $fm = function($n) use ($currency) { return $currency.' '.number_format((float)$n, wc_get_price_decimals(), '.', ','); };

        $pdf->Ln(5);
        // Ensure predictable colors for header row (avoid black fills)
        $pdf->SetFillColor(230, 240, 250);   // header fill
        $pdf->SetTextColor(0, 0, 0);         // text color
        $pdf->SetDrawColor(180, 180, 180);   // borders

        $pdf->SetFont('Arial','B',12);
        $col_widths = [
            'product'  => 120,
            'qty'      => 30,
            'subtotal' => 30,
        ];

        // Header: Product left, Subtotal right
        $pdf->Cell($col_widths['product'],  10, __('Product',  'pdf-invoice-generator'), 1, 0, 'L', true);
        // $pdf->Cell($col_widths['sku'],      10, __('SKU',      'pdf-invoice-generator'), 1, 0, 'C', true);
        $pdf->Cell($col_widths['qty'],      10, __('Qty',      'pdf-invoice-generator'), 1, 0, 'C', true);
        $pdf->Cell($col_widths['subtotal'], 10, __('Subtotal', 'pdf-invoice-generator'), 1, 1, 'R', true);

        // Data rows: white background
        $pdf->SetFillColor(255,255,255);
        $pdf->SetTextColor(0,0,0);
        $pdf->SetDrawColor(180,180,180);

        $pdf->SetFont('Arial','',12);
        // foreach ($order->get_items() as $item) {
        //     $p   = $item->get_product();
        //     // $sku = $p ? $p->get_sku() : '-';

        //     $name = $item->get_name();
        //     $name = str_replace(['<br>','<br/>','<br />'], ' ', $name);
        //     $name = iconv('UTF-8','ISO-8859-1//TRANSLIT',$name);

        //     $pdf->Cell($col_widths['product'],  10, $name,                   1, 0, 'L', false);
        //     // $pdf->Cell($col_widths['sku'],      10, $sku,                    1, 0, 'C', false);
        //     $pdf->Cell($col_widths['qty'],      10, $item->get_quantity(),   1, 0, 'C', false);
        //     $pdf->Cell($col_widths['subtotal'], 10, $fm($item->get_total()), 1, 1, 'R', false);
        // }
        foreach ($order->get_items() as $item) {
    $p   = $item->get_product();
    $sku = $p ? $p->get_sku() : '-';

    $name = $item->get_name();
    $name = str_replace(['<br>','<br/>','<br />'], ' ', $name);
    $name = iconv('UTF-8','ISO-8859-1//TRANSLIT',$name);

    // Get current position
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    
    // Draw border for product cell (height: 10 for 2 lines)
    $pdf->Cell($col_widths['product'], 10, '', 1, 0, 'L', false);
    
    // Write product name (first line)
    $pdf->SetXY($x + 1, $y + 1);
    $pdf->Cell($col_widths['product'] - 2, 4, $name, 0, 0, 'L', false);
    
    // Write SKU (second line with more space above)
    $pdf->SetXY($x + 1, $y + 6); // Changed from y + 5 to y + 6 for more space
    $pdf->Cell($col_widths['product'] - 2, 4, 'SKU: ' . $sku, 0, 0, 'L', false);
    
    // Move to position for next cells
    $pdf->SetXY($x + $col_widths['product'], $y);
    
    // Draw remaining cells
    $pdf->Cell($col_widths['qty'],      10, $item->get_quantity(),   1, 0, 'C', false);
    $pdf->Cell($col_widths['subtotal'], 10, $fm($item->get_total()), 1, 1, 'R', false);
}

        /* =====================================================
        * Totals
        * ===================================================== */
        // Reset colors for totals
        $pdf->SetFillColor(255,255,255);
        $pdf->SetTextColor(0,0,0);
        $pdf->SetDrawColor(0,0,0);

        $pdf->Ln(5);
        $label_w = 150;
        $value_w = 30;

        // Subtotal
        $pdf->Cell($label_w, 6, __('Subtotal', 'pdf-invoice-generator'), 0, 0, 'R');
        $pdf->Cell($value_w, 6, $fm($order->get_subtotal()), 1, 1, 'R');

        // Coupon (combined, if applied)
        if ( $order->get_discount_total() > 0 ) {
            $codes = $order->get_coupon_codes();
            $code  = $codes ? strtoupper($codes[0]) : '';
            $label = $code ? sprintf('%s (%s)', __('Coupon', 'pdf-invoice-generator'), $code) : __('Coupon', 'pdf-invoice-generator');

            $pdf->Cell($label_w, 6, $label, 0, 0, 'R');
            $pdf->Cell($value_w, 6, '-'.$fm($order->get_discount_total()), 1, 1, 'R');
        }

        // Shipping: only if applied (non-zero or has shipping items)
        if ( $order->get_shipping_total() > 0 || $order->get_items('shipping') ) {
            $pdf->Cell($label_w, 6, __('Shipping Total', 'pdf-invoice-generator'), 0, 0, 'R');
            $pdf->Cell($value_w, 6, $fm($order->get_shipping_total()), 1, 1, 'R');
        }

        // Fees (rename "surcharge")
        foreach ( $order->get_fees() as $fee ) {
            $name = $fee->get_name();
            if ( stripos($name, 'surcharge') !== false ) {
                $name = str_ireplace('surcharge', __('Payment Processing Fees', 'pdf-invoice-generator'), $name);
            }
            $pdf->Cell($label_w, 6, $name, 0, 0, 'R');
            $pdf->Cell($value_w, 6, $fm($fee->get_total()), 1, 1, 'R');
        }

        // Total
        $pdf->Cell($label_w, 6, __('Total', 'pdf-invoice-generator'), 0, 0, 'R');
        $pdf->Cell($value_w, 6, $fm($order->get_total()), 1, 1, 'R');

        /* =====================================================
        * Signature
        * ===================================================== */
        if ( ($pdf_invoice['enable_signature'] ?? '') === 'true' ) {
            $pdf->Ln(10);
            if ( ($pdf_invoice['signature_type'] ?? '') === 'nexo_sig_text' && ! empty($pdf_invoice['signature_text']) ) {
                $pdf->Cell(0,6,$pdf_invoice['signature_text'],0,1,'R');
            }
            $pdf->Line(140,$pdf->GetY(),190,$pdf->GetY());
        }

        /* =====================================================
        * Save
        * ===================================================== */
        $dir = wp_upload_dir()['basedir'].'/invoices';
        if ( ! file_exists($dir) ) { mkdir($dir,0755,true); }

        $file = "{$dir}/receipt_{$order_id}.pdf";
        $pdf->Output($file,'F');

        return $file;
    }
    /**
     * ====================================================
     * Thank You Page PDF Button - TOP POSITION WITH CUSTOM CLASS
     * ====================================================
     */

    // Remove the original button
    remove_action('woocommerce_thankyou', 'nexolis_add_pdf_button_on_thankyou', 25);
    // Add PDF button at the top of thank you page
    add_action('woocommerce_thankyou', 'custom_nexolis_add_pdf_button_on_thankyou_top', 1);

    function custom_nexolis_add_pdf_button_on_thankyou_top($order_id){    
        if( ! $order_id ) return;
        $pdf_invoice = get_option('nexo_pdf_invoice_settings');
        if ( isset($pdf_invoice['enable_pdf_invoice']) && $pdf_invoice['enable_pdf_invoice'] == 'true' ) {
            $download_url = wp_nonce_url(
                site_url('?download_order_pdf=' . $order_id),
                'download_order_pdf'
            );
            echo '<div class="custom-pdf-invoice-wrapper" style="margin-bottom:20px; text-align:center;">
                <a href="'.esc_url($download_url).'" class="button custom-pdf-invoice-btn nexolis_pdf_style wc-forward">
                    <span class="pdf-icon">📄</span> '. esc_attr($pdf_invoice['pdf_button_text']) .'
                </a>
            </div>';
        }
    }

    /**
     * ====================================================
     * Custom CSS for PDF Button
     * ====================================================
     */

    add_action('wp_footer', 'custom_nexolis_pdf_button_css');
    function custom_nexolis_pdf_button_css(){
        $pdf_invoice = get_option('nexo_pdf_invoice_settings');
        ?>
        <style>
            /* Original plugin styles */
            .nexolis_pdf_style, .download_pdf {
                color: <?php echo esc_attr($pdf_invoice['pdf_button_text_color']) ?> !important;
                background: <?php echo esc_attr($pdf_invoice['pdf_button_bg_color']) ?> !important;
            }

            /* Custom PDF Invoice Button Styles */
            .custom-pdf-invoice-wrapper {
                padding: 20px;
                background: #f7f7f7;
                border: 2px dashed #ddd;
                border-radius: 8px;
                margin-bottom: 25px !important;
            }

            .custom-pdf-invoice-btn {
                display: inline-block;
                padding: 12px 30px !important;
                font-size: 16px !important;
                font-weight: 600 !important;
                text-decoration: none !important;
                border-radius: 5px !important;
                transition: all 0.3s ease !important;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important;
            }

            .custom-pdf-invoice-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
                opacity: 0.9;
            }

            .custom-pdf-invoice-btn .pdf-icon {
                font-size: 18px;
                margin-right: 8px;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .custom-pdf-invoice-btn {
                    display: block;
                    width: 100%;
                    text-align: center;
                }
            }
        </style>
        <?php
    }
    add_action('init', function() {
        if ( function_exists('nexolis_add_pdf_button_on_thankyou') ) {
            remove_action('woocommerce_thankyou', 'nexolis_add_pdf_button_on_thankyou', 25);
            remove_action('woocommerce_order_details_after_order_table', 'nexolis_add_pdf_button_on_thankyou', 25);
            remove_action('woocommerce_view_order', 'nexolis_add_pdf_button_on_thankyou', 25);
        }
    }, 20);
    add_action( 'current_screen', function( $screen ) {
        /**
         * IMPORTANT CHANGE:
         * We now replace inside $translated (final output),
         * not by matching exact $text keys. This catches labels like:
         * "Enable PDF Invoice", "Invoice:", "Invoice Information", etc.
         */
        add_filter( 'gettext', function( $translated, $text, $domain ) {
            $search  = array(
                'Invoices',   
                'INVOICES',   
                'Invoice',    
                'INVOICE',    
            );
            $replace = array(
                'Receipts',
                'RECEIPTS',
                'Receipt',
                'RECEIPT',
            );
            $translated = str_replace( $search, $replace, $translated );
            if ( stripos( $translated, 'invoice' ) !== false ) {
                $translated = preg_replace( '/\binvoices\b/i', 'Receipts', $translated );
                $translated = preg_replace( '/\binvoice\b/i',  'Receipt',  $translated );
            }

            return $translated;
        }, 10, 3 );

        add_filter( 'ngettext', function( $translated, $single, $plural, $number, $domain ) {
            if ( stripos( $translated, 'invoice' ) !== false ) {
                $translated = preg_replace( '/\bInvoices\b/i', 'Receipts', $translated );
                $translated = preg_replace( '/\bInvoice\b/i',  'Receipt',  $translated );
            }
            return $translated;
        }, 10, 5 );

    }, 10 );

    add_action( 'load-woocommerce_page_wc-settings', function () {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
        if ( $tab !== 'nexo_pdf_invoice' ) {
            return;
        }
        ob_start( function( $html ) {
            $search  = array(
                'Invoice Information',
                'Invoice Prefix',
                'Invoice Number',
                'Invoice Date Format',
            );
            $replace = array(
                'Receipt Information',
                'Receipt Prefix',
                'Receipt Number',
                'Receipt Date Format',
            );
            $html = str_replace( $search, $replace, $html );
            $html = preg_replace(
                '~(Use\s*Shortcode\b.*?to\s+show\s+the\s+estimated\s+delivery\s+date\s+for\s+the\s+order\s+in\s+the\s+PDF\s+)(?:&nbsp;|&amp;nbsp;)?invoice(\s*\.)~is',
                '$1Receipt$2',
                $html
            );
            $html = preg_replace(
                '~(Use\s*Shortcode\b.*?in\s+the\s+PDF\s+)(?:&nbsp;|&amp;nbsp;)?invoice(?!\w)~is',
                '$1Receipt',
                $html
            );
            $html = str_ireplace(
                'Use Shortcode [delivery_date] to show the estimated delivery date for the order in the PDF invoice.',
                'Use Shortcode [delivery_date] to show the estimated delivery date for the order in the PDF Receipt.',
                $html
            );
            $html = preg_replace( '/\bInvoices\b/u', 'Receipts', $html );
            $html = preg_replace( '/\bInvoice\b/u',  'Receipt',  $html );

            return $html;
        } );
    } );