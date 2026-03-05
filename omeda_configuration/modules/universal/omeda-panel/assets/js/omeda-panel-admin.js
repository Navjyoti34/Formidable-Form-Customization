async function force_process(dataOrder, that) {
    const force_omeda_process_button = that;

    force_omeda_process_button.setAttribute('processing', '');
    force_omeda_process_button.classList.add("disabled")
    force_omeda_process_button.textContent = 'Sending to Omeda...'

    const force_data_send_omeda_order = await fetch(window.location + '&omeda_force_data_send=' + dataOrder, {
        method: 'GET'
    });

    const force_data_send_omeda_order_text = await force_data_send_omeda_order.text();

    force_omeda_process_button.textContent = 'Checking Omeda response...'

    force_omeda_process_button.textContent = 'Processing data sent to Omeda...'

    const force_process_omeda_order = await fetch(window.location + '&omeda_force_process=' + dataOrder, {
        method: 'GET'
    });

    const force_process_omeda_order_text = await force_process_omeda_order.text();

    force_omeda_process_button.textContent = 'Completed! Check order notes...'

    force_omeda_process_button.textContent = 'Done'

    force_omeda_process_button.removeAttribute('processing');
    window.processing_omeda_request = false;
}

document.addEventListener('DOMContentLoaded', function (event) {
    window.processing_omeda_request = false;

    var buttons = document.querySelectorAll('#force-omeda-process');

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            buttons.forEach(function (button) {
                if (button.hasAttribute('processing')) {
                    window.processing_omeda_request = true;
                }
            });


            if (window.processing_omeda_request) {
                alert('Please wait while a force process completes.');
                return;
            } else {
                var dataOrder = this.getAttribute('data-order');

                force_process(dataOrder, this);
            }
        }, false);
    });
});
