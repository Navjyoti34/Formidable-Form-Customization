document.addEventListener('DOMContentLoaded', function () {
	const input = document.getElementById('tooltipInput');

	if (!input) {
		return;
	}

	const tooltip = document.getElementById('tooltip');
	const submitButton = document.getElementById('first_name_field');

	input.addEventListener('mouseenter', () => {
		submitButton.style.marginTop = '100px';
		tooltip.style.display = 'block';
	});


	tooltip.addEventListener('mouseenter', () => {
		submitButton.style.marginTop = '100px';
		tooltip.style.display = 'block';
	});

	tooltip.addEventListener('mouseleave', () => {
		if (window.print_id) { return; }
		if (!isHover(input) && !isHover(tooltip)) {
			submitButton.style.marginTop = '5px';
			tooltip.style.display = 'none';
		}
	});

	window.print_id = false;

	input.addEventListener('focus', () => {
		submitButton.style.marginTop = '100px';
		tooltip.style.display = 'block';
		window.print_id = true;
	});

	input.addEventListener('blur', () => {
		submitButton.style.marginTop = '5px';
		tooltip.style.display = 'none';
		window.print_id = false;
	});

	input.addEventListener('mouseleave', () => {
		if (window.print_id) { return; }
		submitButton.style.marginTop = '5px';
		tooltip.style.display = 'none';
	});

	function isHover(element) {
		const { left, top, right, bottom } = element.getBoundingClientRect();
		const { clientX, clientY } = event;
		return clientX >= left && clientX <= right && clientY >= top && clientY <= bottom;
	}
});

document.addEventListener("DOMContentLoaded", function () {
    const submitButton = document.querySelector('button[name="submit"]');

    submitButton.addEventListener('click', function(event) {
        event.preventDefault();

        submitButton.disabled = true;

        const form = submitButton.closest('form');

        if (form) {
            submitForm(form);
        }
    });

	function submitForm(form) {
	    const submitFormFunction = Object.getPrototypeOf(form).submit;
	    submitFormFunction.call(form);
	}

	var claimButton = document.querySelector('button[name="claim"]');

	/*claimButton.addEventListener('click', function() {
		window.location.href = '/makers-club/collection';
	});*/

	function toggleStateField() {
		var countryField = document.getElementById('countrySelect');

		if (!countryField) {
			return;
		}

		var selectedCountry = countryField.value;
		var stateField = document.getElementById('state_field');
		const stateInputField = document.querySelector('#form-state');
		const stateSelectField = document.querySelector('[data-id="form-state"]');

		if (selectedCountry !== 'US') {
			stateField.style.display = 'none';
			stateInputField.setAttribute('value', '');
			stateSelectField.setAttribute('value', '');
		} else {
			stateField.style.display = 'block';
		}
	}

	toggleStateField();

	var countrySelect = document.getElementById('countrySelect');

	if (!countrySelect) {
		return;
	}

	countrySelect.addEventListener('change', function () {
		toggleStateField();
	});
});
