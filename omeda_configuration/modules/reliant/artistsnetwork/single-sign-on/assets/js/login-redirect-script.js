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

function isValidURL(url) {
    var urlPattern = /^(https?:\/\/)?([a-z0-9-]+\.)+[a-z]{2,}(:\d{1,5})?(\/[^\s]*)?$/i;

    return urlPattern.test(url);
}

function deleteCookie(name) {
	document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
}

document.addEventListener('DOMContentLoaded', function () {
	var cookies = {
	  ...getCookiesByPattern('magic_logged_in_')
	};

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

		if (response_error == false) {
			var parts = window.location.hash.split("#");

			if (parts.length > 1) {
			    var textAfterHash = parts[1];

			    if(isValidURL(textAfterHash)) {
			    	deleteCookie(cookieName);
			    	window.location.href = textAfterHash;
			    }
			}
		}

		deleteCookie(cookieName);
	}
});
