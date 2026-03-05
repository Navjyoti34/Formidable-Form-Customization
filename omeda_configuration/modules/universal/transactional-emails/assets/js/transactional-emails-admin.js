
function isValidEmail(email) {
    var mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
    return email.match(mailformat);
}

function createNotification(message, template) {
    var existingNotification = document.getElementById('notification');


    if (existingNotification) {
        existingNotification.remove();
    }

    var notification = document.createElement('div');
    notification.id = 'notification';
    notification.className = 'notice notice-' + template + ' is-dismissible transactional-emails';

    var messageElement = document.createElement('p');
    messageElement.innerHTML = message;

    notification.appendChild(messageElement);

    var dismissButton = document.createElement('button');
    dismissButton.className = 'notice-dismiss';
    dismissButton.setAttribute('type', 'button');
    dismissButton.innerHTML = '<span class="screen-reader-text">Dismiss this notification</span>';

    dismissButton.addEventListener('click', function () {
        notification.remove();
    });

    notification.appendChild(dismissButton);

    var container = document.querySelector('.wrap');
    var firstHeading = container.querySelector('h1');

    container.insertBefore(notification, firstHeading.nextElementSibling);
}

document.addEventListener('DOMContentLoaded', function () {
    var test_email_input_buttons = document.querySelectorAll('#transactional-emails-test-button');
    var test_email_inputs = document.querySelectorAll('#transactional-emails-test');

    test_email_inputs.forEach(function (input) {
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                var button = input.closest('div').querySelector('button');
                button.click();
            }
        });
    })

    test_email_input_buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            var input = button.closest('div').querySelector('input');
            var email = input.value;
            if (isValidEmail(email)) {
                var template_name = input.getAttribute('data-id');
                input.style.boxShadow = "none";

                const protocol = window.location.protocol;
                const domain = window.location.hostname;
                const currentDomain = protocol + '//' + domain;
                fetch(currentDomain + '/wp-admin/admin.php?page=transactional_emails_manager&test_email=true&email=' + email + '&template_name=' + template_name)
                    .then(response => {
                        if (response.ok) {
                            return response.text();
                        } else {
                            throw new Error('Error: ' + response.status);
                        }
                    })
                    .then(data => {
                        data = JSON.parse(data.toString().replace(/<!--(.*?)-->[\s\S]*?(\r\n|\n|$)/g, ''));
                        var omeda_errors = data['Errors'];
                        if (omeda_errors) {
                            for (const element of omeda_errors) {
                                createNotification('Omeda reported an error: ' + element['Error'], 'error');
                            }
                            console.log(data);
                        } else {
                            createNotification('A test email has been successfully sent to <b>' + email + '</b> with submission ID <b>' + data['SubmissionId'] + '</b>.', 'success');
                            console.log(data);
                        }
                    })
                    .catch(error => {
                        createNotification('An error occurred while attempting to send a test email. Please check the console for more details.', 'error');
                        console.log(error);
                    });

                createNotification('Please stand-by until email is sent.', 'success');
            } else {
                input.style.boxShadow = "0 0 10px rgb(249, 7, 7, 0.5)";
                createNotification('The email address provided is invalid. Please ensure that you have entered a valid email address.', 'error');
            }
        });
    });

    var dropdown = document.getElementById('transactional_emails_manager_dropdown');
    var omedaInput = document.getElementById('omeda_track_id_input');
    var subjectInput = document.getElementById('transactional_emails_manager_subject');

    omedaInput.value = dropdown.options[dropdown.selectedIndex].getAttribute('data-omeda-track-id');
    subjectInput.value = dropdown.options[dropdown.selectedIndex].getAttribute('data-email-subject');

    dropdown.addEventListener('change', function () {
        omedaInput.value = this.options[this.selectedIndex].getAttribute('data-omeda-track-id');
        subjectInput.value = this.options[this.selectedIndex].getAttribute('data-email-subject');
    });

    var omedaInputIcon = document.getElementById('omeda_track_id_input_lock');

    omedaInputIcon.addEventListener('click', function () {
        this.classList.toggle('dashicons-lock');
        this.classList.toggle('dashicons-unlock');
        omedaInput.disabled = !omedaInput.disabled;
    });

    const tabLinks = document.querySelectorAll('.nav-tab');
    const tabPanels = document.querySelectorAll('.tab-panel');

    // Hide all tab panels except the first one
    tabPanels.forEach(function (panel, index) {
        if (index !== 0) {
            panel.style.display = 'none';
        }
    });

    // Function to show a specific tab panel
    function showTabPanel(index) {
        // Remove the "active" class from all tab links and panels
        tabLinks.forEach(function (link) {
            link.classList.remove('active');
        });
        tabPanels.forEach(function (panel) {
            panel.classList.remove('active');
        });

        // Add the "active" class to the selected tab link and panel
        tabLinks[index].classList.add('active');
        tabPanels[index].classList.add('active');

        // Hide all tab panels except the selected one
        tabPanels.forEach(function (panel) {
            panel.style.display = 'none';
        });
        tabPanels[index].style.display = 'block';
    }

    // Add click event listeners to the tab links
    tabLinks.forEach(function (link, index) {
        link.addEventListener('click', function (e) {
            showTabPanel(index);
        });
    });

    // Check if a specific tab is specified in the URL hash
    if (window.location.hash) {
        const tabId = window.location.hash.substring(1);
        const tab = document.getElementById(tabId);
        if (tab) {
            const tabIndex = Array.from(tabLinks).findIndex(function (link) {
                return link.getAttribute('href') === '#' + tabId;
            });
            if (tabIndex !== -1) {
                showTabPanel(tabIndex);
            }
        }
    }
});