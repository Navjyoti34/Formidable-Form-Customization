document.addEventListener('DOMContentLoaded', function() {
    var tableWithLabel = document.querySelector('table.form-table label[for="ywaf_enable_plugin"]');

    if (tableWithLabel) {
        var h2Element = document.createElement('h2');
        h2Element.textContent = 'Whitelist Settings';
        
        var newTable = document.createElement('table');
        newTable.className = 'form-table';
        
        var newRow = document.createElement('tr');
        var newHeaderCell = document.createElement('th');
        newHeaderCell.innerHTML = '<th scope="row" class="titledesc"><label for="ywaf_medium_risk_threshold">Emails</label></th>';
        newRow.appendChild(newHeaderCell);
        
        var newContentCell = document.createElement('td');
        newContentCell.innerHTML = '<input type="button" class="button button-primary" value="Update" data-select-id="ywaf_white_list_emails">';
        newRow.appendChild(newContentCell);
        
        newTable.appendChild(newRow);
        
        var parentElement = tableWithLabel.closest('table.form-table');
        if (parentElement) {
            parentElement.parentNode.insertBefore(newTable, parentElement.nextSibling);
            parentElement.parentNode.insertBefore(h2Element, newTable);
        }
    }

    function validateEmails(content) {
        const lines = content.split('\n');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if(content.trim() === "") {
            return true;
        }
        
        for (let email of lines) {
            if (email.trim() === "") return false;
            if (!emailRegex.test(email.trim())) {
                return false;
            }
        }
        
        return true;
    }

    const ywaf_white_list_emails = document.querySelector('input[type="button"][data-select-id="ywaf_white_list_emails"]');
    
    window.ywaf_white_list_emails_current = `${YITHWhiteList.emails}`;
    
    ywaf_white_list_emails.addEventListener('click', function() {
        Swal.fire({
            title: 'Enter an email',
            input: 'textarea',
            icon: 'info',
            inputValue: window.ywaf_white_list_emails_current,
            html: '<div style="margin-top: 8px;">Please input each email on a separate line.</div>',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Update',
            inputValidator: (value) => {
                const isValid = validateEmails(value);

                if (!isValid) {
                    return 'Check to be sure emails are correct!';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                let emails = "";
                if (result.value) {
                    emails = result.value.split('\n').map(email => email.trim()).filter(email => email.length > 0).join(',');
                }

                fetch('/wp-json/yith/v1/bypass/', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ emails: emails })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.ywaf_white_list_emails_current = result.value;

                        Swal.fire(
                            'Submitted!',
                            'Great news! The whitelist has been successfully updated!',
                            'success'
                        );
                    } else {
                        Swal.fire({
                            title: "Error!",
                            text: "Oops, there was an issue while trying to update the whitelist with the emails.",
                            icon: "error"
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: "Error!",
                        text: "Oops, it seems there was a network error or the server couldn't be reached.",
                        icon: "error"
                    });
                });
            }
        });
    });
});