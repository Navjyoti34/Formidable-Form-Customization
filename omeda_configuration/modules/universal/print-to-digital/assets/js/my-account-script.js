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
	var cookies = getCookiesByPattern('rsvp_claim_response_');

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