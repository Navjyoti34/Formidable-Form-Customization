function appendVerificationNotification(type, content) {
    const alertUlElements = document.querySelectorAll('ul[role="alert"]');

    alertUlElements.forEach(function (element) {
        element.parentNode.removeChild(element);
    });

    const errorDiv = document.createElement('div');

    errorDiv.innerHTML = `
	    <ul class="woocommerce-` + type + `-verify" role="alert">
	        <li>
	            <strong>` + capitalizeFirstLetter(type) + `:</strong> ` + content + `
	        </li>
	    </ul>
	`;

    const woocommerceElement = document.querySelector('.woocommerce');

    if (woocommerceElement) {
        woocommerceElement.insertBefore(errorDiv, woocommerceElement.firstChild);
    }
}

function capitalizeFirstLetter(word) {
    return word.charAt(0).toUpperCase() + word.slice(1);
}

appendVerificationNotification(emailVerification.type, emailVerification.msg);

function resendVerification() {
    fetch('/my-account/?verification=true&request=resend')
    .then(response => {
        if (!response.ok || response.redirected) {
            console.log('Primary URL failed or redirected. Trying fallback URL.');
            return fetch('/my-account/edit-account/?verification=true&request=resend'); 
        }
        return response; 
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json(); 
    })
        .then(data => {
            if (data.error == true) {
                throw { message: data.msg };
            }

            Swal.fire({
                icon: 'success',
                title: 'Resend Verification',
                html: data.msg,
            });

        })
        .catch(error => {
            var swalWarningModal = Swal.fire({
                icon: 'warning',
                title: 'Resend Verification',
                html: error.message,
            });

            var secondsElement = document.querySelector('span[data-id=time-left]');
            var seconds = parseInt(secondsElement.textContent);

            var timer = setInterval(function () {
                seconds--;

                if (seconds <= 0) {
                    clearInterval(timer);
                    secondsElement.innerHTML = '0 seconds';
                    setTimeout(function () {
                        swalWarningModal.close();
                        Swal.fire({
                            icon: 'success',
                            title: 'Resend Verification',
                            html: 'You can now request another email confirmation request!',
                        });
                    }, 150);
                } else {
                    secondsElement.innerHTML = seconds + ' seconds';
                }
            }, 1000);
        });
}

document.querySelector("a[data-id=resend-verification]").addEventListener('click', resendVerification);