function appendNotification(type, content) {
	const alertUlElements = document.querySelectorAll('ul[role="alert"]');

	alertUlElements.forEach(function (element) {
		element.parentNode.removeChild(element);
	});

	const errorDiv = document.createElement('div');

	errorDiv.innerHTML = `
	    <ul class="woocommerce-` + type + `" role="alert">
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

function isValidURL(url) {
    var urlPattern = /^(https?:\/\/)?([a-z0-9-]+\.)+[a-z]{2,}(:\d{1,5})?(\/[^\s]*)?$/i;

    return urlPattern.test(url);
}

function errorReset() {
	const passwordButton = document.querySelector('button[name="password"]');
	const magicButton = document.querySelector('button[name="magic"]');
	const usernameField = document.querySelector('input[name="username"]');

	passwordButton.classList.remove('d-none');
	usernameField.removeAttribute('disabled');
	magicButton.innerHTML = 'Magic Link';
	magicButton.removeAttribute('disabled');
	magicButton.classList.remove('no-hover');
	magicButton.style.width = 'unset';
}

function deleteCookie(name) {
	document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
}

function getCookiesByPattern(pattern) {
	var cookies = document.cookie.split(';');
	var matchingCookies = {};

	for (var i = 0; i < cookies.length; i++) {
		var cookie = cookies[i].trim();
		if (cookie.indexOf(pattern) === 0) {
			var parts = cookie.split('=');
			var cookieName = parts[0];
			var cookieValue = parts[1];
			matchingCookies[cookieName] = cookieValue;
		}
	}

	return matchingCookies;
}

document.addEventListener('DOMContentLoaded', function () {
	var cookies = getCookiesByPattern('magic_error_');

	for (var cookieName in cookies) {
		try {
			var response = JSON.parse(decodeURIComponent(cookies[cookieName]));
		} catch (error) { var response = []; }

		if (response.length === 0 || response === null) {
			deleteCookie(cookieName);
			continue;
		}

		var response_error = response['error'];
		var response_msg = response['msg'];

		if (response_error == true) {
			appendNotification('error', response_msg);
		} else {
			appendNotification('success', response_msg);
		}

		deleteCookie(cookieName);
	}
});

document.addEventListener('DOMContentLoaded', function () {
	const passwordButton = document.querySelector('button[name="password"]');
	const magicButton = document.querySelector('button[name="magic"]');
	const usernameField = document.querySelector('input[name="username"]');

	passwordButton.addEventListener('click', function () {
		const woocommerceLogin = document.querySelector('.woocommerce');

		if (woocommerceLogin) {
			const elementsWithDNone = woocommerceLogin.querySelectorAll('.d-none');

			elementsWithDNone.forEach(element => {
				element.classList.remove('d-none');
			});
		}

		this.classList.add('d-none');

		magicButton.classList.add('d-none');
	});

	magicButton.addEventListener('click', function () {
		passwordButton.classList.add('d-none');
		usernameField.setAttribute('disabled', '');
		magicButton.innerHTML = '<span style="display: contents;float: left;">Sending magic link</span><span id="dot"><span></span></span>';
		magicButton.setAttribute('disabled', '');
		magicButton.classList.add('no-hover');
		magicButton.style.width = '100%';

		var parts = window.location.hash.split("#");
		var redirectOnSignin = '';

		if (parts.length > 1) {
		    var textAfterHash = parts[1];

		    if(isValidURL(textAfterHash)) {
		    	redirectOnSignin = `&redirect=${textAfterHash}`
		    }
		}

		fetch('?request=single_sign_on&email=' + usernameField.value + redirectOnSignin, {
			method: 'GET',
			headers: {
				'Content-Type': 'application/json',
			}
		})
			.then(response => response.json())
			.then(data => {
				const dataError = data['error'];
				const msg = data['msg'];

				if (dataError == true) {
					appendNotification('error', msg);
					errorReset();
				} else {
					appendNotification('success', msg);

					magicButton.innerHTML = '<span style="display: contents;float: left;">Resend in <span id="countdown">60</span> seconds</span>';

					async function countdownAndPerformAction() {
						const countdownElements = document.querySelectorAll('[id="countdown"]');

						countdownElements.forEach(async (countdownElement) => {
							let count = parseInt(countdownElement.innerText);
							let shouldBreak = false;

							while (count > 0 && !shouldBreak) {
								await new Promise(resolve => setTimeout(resolve, 1000));
								count--;
								countdownElement.innerText = count;

								if (count % 8 === 0) {
									try {
										const response = await fetch('/?request=single_sign_on_check', {
											method: 'GET',
											headers: {
												'Content-Type': 'application/json',
											},
										});

										if (response.ok) {
											const data = await response.json();
											const loggedIn = data.logged_in;

											if (!(loggedIn === false)) {
												appendNotification('success', 'You are currently logged in from another location. You will be redirected to your account shortly.');

												shouldBreak = true;
											}
										} else {
											console.error('Fetch error:', response.status);
										}
									} catch (error) {
										console.error('Error:', error);
									}
								}
							}

							errorReset();

							if (shouldBreak) {
								window.location.href = '/my-account';
							} else {
								appendNotification('error', 'Your magic link has expired. You have the option to try again or use your password for access.');
							}
						});
					}

					countdownAndPerformAction();
				}
			})
			.catch(error => {
				console.error('Error:', error);
				errorReset();
			});
	});
});