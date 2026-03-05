function ga_four_disbatch_snackbar_notification(message, type) {
    const snackbar = document.createElement('div');
    snackbar.className = `custom-snackbar ${type}`;
    snackbar.textContent = message;
    document.body.appendChild(snackbar);

    setTimeout(function () {
        snackbar.remove();
    }, 3000);
}

document.addEventListener("DOMContentLoaded", function () {
    const toggleCheckboxes = document.querySelectorAll('#toggleCheckbox');

    toggleCheckboxes.forEach(function (toggleCheckbox) {
        toggleCheckbox.addEventListener('click', function () {
            const state = this.checked

            const rowElement = this.closest('.row');
            const spanElement = rowElement.querySelector('span');

            const url = '/wp-admin/admin.php?page=ga_four_integration&ga4=true&integration=' + encodeURIComponent(spanElement.textContent) + '&enable=' + state;

            fetch(url, {
                method: 'GET',
            })
            .then(response => response.json())
            .then(data => {
                const hasDataLayerOn = this.classList.contains('data_layer_on');

                this.classList.toggle('data_layer_on', !hasDataLayerOn);
                this.classList.toggle('data_layer_off', hasDataLayerOn);
                
                ga_four_disbatch_snackbar_notification(data['msg'], 'success')
                return;
            })
            .catch(error => {
                ga_four_disbatch_snackbar_notification(data['msg'], 'error')
                return;
            });
        });
    });
});
