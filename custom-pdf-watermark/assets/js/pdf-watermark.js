document.addEventListener('DOMContentLoaded', async () => {
    const fileUrl = decodeURIComponent(getCookie("downloadPdfFilePath"));
    const watermarkText = decodeURIComponent(getCookie("pdfWatermark"));
    const pdfFileName = decodeURIComponent(getCookie("pdfFileName"));
    const permissionId = decodeURIComponent(getCookie("order_permission"));

    if (window.location.pathname.includes('/my-account/downloads/') || window.location.pathname.includes('/download-complete/')) {
        if (fileUrl) {
            console.log(fileUrl);
            // Create and append the loader HTML
            const loaderHTML = `
                <div id="loader" style="display: flex;">
                    <div class="spinner"></div>
                    <p>Processing your PDF, please wait...</p>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', loaderHTML);

            const loader = document.getElementById('loader');

            try {
                // Fetch the PDF file
                const response = await fetch(fileUrl);
                const pdfBytes = await response.arrayBuffer();

                // Attempt to load the PDF with PDF-lib
                try {
                    const pdfDoc = await PDFLib.PDFDocument.load(pdfBytes);

                    // Add watermark to all pages
                    const pages = pdfDoc.getPages();
                    pages.forEach((page) => {
                        const { width, height } = page.getSize();

                        const xPercent = 0.1; // 10% from the left
                        const yPercent = 0.7; // 20% from the top

                        const xPosition = width * xPercent;
                        const yPosition = height * yPercent;

                        page.drawText(watermarkText, {
                            x: xPosition,
                            y: height - yPosition,
                            size: 12,
                            color: PDFLib.rgb(0.75, 0.75, 0.75),
                            rotate: PDFLib.degrees(90),
                            opacity: 0.8,
                        });
                    });

                    // Save and download the modified PDF
                    const modifiedPdfBytes = await pdfDoc.save();
                    const blob = new Blob([modifiedPdfBytes], { type: 'application/pdf' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = pdfFileName;
                    document.body.appendChild(a);
                    a.click();
                    URL.revokeObjectURL(url);
                } catch (innerError) {
                    console.warn('PDF processing skipped due to encryption or error:', innerError);
                    // Fallback - Download the PDF directly without watermark
                    fetch(fileUrl)
                        .then(response => response.blob())
                        .then(blob => {
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = pdfFileName;
                            document.body.appendChild(a);
                            a.click();
                            URL.revokeObjectURL(url);
                        })
                        .catch(fetchError => {
                            console.error('Error during fallback download:', fetchError);
                        });

                    // Log download attempt
                    await fetch(PDFWatermarkData.ajax_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'log_download',
                            permission_id: permissionId,
                            security: PDFWatermarkData.nonce,
                        }),
                    });

                    // Delete the file
                    await fetch(PDFWatermarkData.ajax_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'delete_pdf_file',
                            fileUrl: fileUrl,
                        }),
                    });
                }

                // Log download after success
                await fetch(PDFWatermarkData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'log_download',
                        permission_id: permissionId,
                        security: PDFWatermarkData.nonce,
                    }),
                });

                // AJAX call to delete file
                await fetch(PDFWatermarkData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'delete_pdf_file',
                        fileUrl: fileUrl,
                    }),
                });
            } catch (error) {
                console.error('Error processing the PDF:', error);
                const pdfLocation = getCookie('pdf_location');
                console.log('pdfLocation:', pdfLocation);

                // Log download attempt
                await fetch(PDFWatermarkData.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'log_download',
                        permission_id: permissionId,
                        security: PDFWatermarkData.nonce,
                    }),
                });

                // Retry fallback download or redirect
                if (pdfLocation) {
                    const decodedUrl = decodeURIComponent(pdfLocation);
                    console.log('Decoded URL:', decodedUrl);
                    window.location.assign(decodedUrl);
                } else {
                    const a = document.createElement('a');
                    a.href = fileUrl;
                    a.download = pdfFileName;
                    document.body.appendChild(a);
                    a.click();
                    URL.revokeObjectURL(fileUrl);
                }
            } finally {
                // Remove the loader after processing
                loader.remove();
            }
        }
    }
    delete_cookie("downloadPdfFilePath");
    delete_cookie("pdfWatermark");
    delete_cookie("pdfFileName");
    delete_cookie("permissionId");
});

function getCookie(cname) {
    let name = cname + "=";
    let ca = document.cookie.split(';');
    for(let i = 0; i < ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) == ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) == 0) {
        return c.substring(name.length, c.length);
      }
    }
    return "";
}

function delete_cookie(name) {
    document.cookie = name +'=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
}
