function setCookie(name, value, daysToExpire) {
    try {
        var cookie = name + '=' + encodeURIComponent(value);

        if (daysToExpire) {
            var expirationDate = new Date();
            expirationDate.setDate(expirationDate.getDate() + daysToExpire);
            cookie += '; expires=' + expirationDate.toUTCString();
        }

        document.cookie = cookie;
        return true;
    } catch (error) {
        return false;
    }
}

function insertMessage(message, className) {
    const element = document.createElement('div');
    element.className = className;
    element.setAttribute('role', 'alert');
    element.textContent = message;

    const entryContentElement = document.querySelector('.entry-content');

    const woocommerceDiv = entryContentElement.querySelector('.woocommerce');

    woocommerceDiv.insertBefore(element, woocommerceDiv.firstChild);
}

function getCookie(cookieName) {
    var name = cookieName + "=";
    var decodedCookie = decodeURIComponent(document.cookie);
    var cookieArray = decodedCookie.split(';');

    for (var i = 0; i < cookieArray.length; i++) {
        var cookie = cookieArray[i].trim();
        if (cookie.indexOf(name) === 0) {
            return cookie.substring(name.length, cookie.length);
        }
    }
    return null;
}

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('myModal');
    const modalOverlay = document.querySelector('.modal-overlay');
    const closeModalButton = document.getElementById('closeModal');
    const continueButton = document.getElementById('continueButton');
    const cancelButton = document.getElementById('cancelButton');
    const modalContent = document.querySelector('.modal-content');
    const loader = document.querySelector('.overlay-content');
    window.insideModal = false;

    function cancellation_action_close_modal() {
        modal.style.display = 'none';
    }

    function cancellation_action_check_cookie(cookieName) {
        const cookies = document.cookie.split(';');
        for (const cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === cookieName) {
                return true;
            }
        }
        return false;
    }

    function cancellation_action_delete_cookie(cookieName) {
        document.cookie = `${cookieName}=; expires=Thu, 01 Jan 1970 00:00:00 GMT;`;
    }

    function cancellation_action_open_modal(e) {
        e.preventDefault();

        var src = this.getAttribute('href');

        modal.style.display = 'block';

        continueButton.addEventListener('click', function () {
            var output = {
                error: true,
                msg: 'An unexpected issue occurred while processing your request. Please feel free to reach out to our dedicated support team for further assistance.',
                wp_nonce: cancellation_action.cancellation_action_wp_nonce
            };
            loader.style.display = "block";

            fetch(src)
                .then(function (response) {
                    if (response.ok) {
                        output.error = false;
                        output.msg = 'Cancellation successful! Your subscription has been canceled.';
                    }
                })
                .catch(function (error) { })
                .finally(function () {
                    setCookie('cancellationAction', JSON.stringify(output), 7);
                    loader.style.display = "none";
                    cancellation_action_close_modal();
                    window.location.href = window.location.href + '?#' + Math.floor(Math.random() * 10000);
                });
        });
    }

    modalContent.addEventListener('mouseenter', function () {
        window.insideModal = true;
    });

    modalContent.addEventListener('mouseleave', function () {
        window.insideModal = false;
    });

    closeModalButton.addEventListener('click', cancellation_action_close_modal);
    modalOverlay.addEventListener('click', function () {
        if (!window.insideModal) {
            cancellation_action_close_modal();
        }
    });
    cancelButton.addEventListener('click', cancellation_action_close_modal);

    const cancelLinks = document.querySelectorAll('td.subscription-actions a.cancel, table.shop_table.subscription_details a.cancel');

    cancelLinks.forEach(function (link) {
        link.addEventListener('click', cancellation_action_open_modal);
    });

    const isCancellationActionCookieExists = cancellation_action_check_cookie('cancellationAction');

    if (!isCancellationActionCookieExists) {
        document.querySelector('#cancellation-action-modal-title').innerHTML = 'Subscription Cancellation';
        document.querySelector('#cancellation-action-modal-body').innerHTML = '<p>Are you sure you want to cancel your subscription’s auto-renewals? Your subscription has lots to offer: </p><br/><p style="font-size: 13px !important;">(No refunds are offered on early cancellations. You will continue to have access to your membership benefits through the remainder of your paid term, as noted in your account)</p>';

        continueButton.textContent = 'Yes';
        cancelButton.textContent = 'No';
    }

    if (isCancellationActionCookieExists) {
        const cancellationActionCookie = getCookie('cancellationAction');
        const parsedcancellationAction = JSON.parse(cancellationActionCookie);

        cancellation_action_error = parsedcancellationAction.error;
        cancellation_action_msg = parsedcancellationAction.msg;
        cancellation_action_nonce = parsedcancellationAction.nonce;

        if (!cancellation_action_error) {
            insertMessage(cancellation_action_msg, 'woocommerce-message');
        } else {
            insertMessage(cancellation_action_msg, 'woocommerce-error');
        }

        document.querySelector('#cancellation-action-modal-title').innerHTML = 'Sorry to see you go.';
        document.querySelector('#cancellation-action-modal-body').innerHTML = '<p>You will continue to have access to your membership benefits through the remainder of your paid term, as noted in your account.</p>';

        continueButton.style.display = 'none';
        cancelButton.textContent = 'Okay';

        modal.style.display = 'block';

        cancellation_action_delete_cookie('cancellationAction');
    }
});
