function toggle() {
    let address_form = document.getElementById("address_form");
    let checkbox = document.getElementById("magazine-opt-in");
    let checkbox_content = document.getElementById("magazine-opt-in-content");

    if (checkbox.checked) {
        address_form.style.display = "block";
        checkbox_content.classList.remove('disable-div');
    } else {
        address_form.style.display = "none";
        if(document.getElementById("magazine-opt-in-hidden").value !== '') {
            checkbox_content.classList.add('disable-div');
            var formElements = checkbox_content.querySelectorAll('input, button, label');
            formElements.forEach(function(element) {
              element.disabled = true;
            });
            window.location.search = '?opt_out=true&_wpnonce=' + subOptScript.nonce;
        }
    }
}