function general_ledger_field_toggle(toggle) {
	const publishButton = document.getElementById('publish');
	const generalLedgerFieldMetaBox = document.getElementById('general_ledger_field_meta_box');

	if(toggle) {
		publishButton.disabled = false;
		generalLedgerFieldMetaBox.style.boxShadow = '0px 0px 15px 0px rgb(61 181 0 / 50%)';
	} else {
	    publishButton.disabled = true;
	    generalLedgerFieldMetaBox.style.boxShadow = '0px 0px 15px 0px rgb(255 98 141 / 50%)';
	}
}

if (typeof window.conditions === 'undefined') {
    window.conditions = {};
}

window.conditions['met'] = false;

document.addEventListener('DOMContentLoaded', function() {
	const inputField = document.getElementById('general-ledger-field');

	check_general_ledger_field(inputField);

	inputField.addEventListener('input', function() {
		check_general_ledger_field(inputField);
	});

    const publishButton = document.getElementById('publish');

    if (publishButton) {
    	general_ledger_field_toggle(window.conditions['met']);

        publishButton.addEventListener('click', function(event) {
    		//event.preventDefault();
    		console.log('Woop')
            //alert('Custom action before publishing');
        });
    }


});

function check_general_ledger_field(inputField) {
	if (inputField.value.trim() !== '') {
        const inputValue = inputField.value;
        
        const pattern = /^(\d{2}\*\d{4}\*\d{4}\*\d{5}\*\d{5})$/;

	    if (pattern.test(inputValue)) {
        	window.conditions['met'] = true;
        	general_ledger_field_toggle(window.conditions['met']);
        } else {
        	window.conditions['met'] = false;
    		general_ledger_field_toggle(window.conditions['met']);
        }
    } else {
    	window.conditions['met'] = false;
    	general_ledger_field_toggle(window.conditions['met']);
    }
}