const url_params = new URLSearchParams(window.location.search);
const order_param = url_params.get('post')

async function refresh_order_notes() {
    const grab_order_notes = await fetch(window.location + '&omeda_force_data_send=' + order_param + '&notes=true', {
        method: 'GET'
    });

    const grab_order_notes_response = await grab_order_notes.json();

    var current_notes = document.querySelectorAll('[id="woocommerce-order-notes"] .inside .order_notes li')

    if(Object.keys(current_notes).length < Object.keys(grab_order_notes_response).length) {
        var a = [];
        var b = [];
        var p = {};

        current_notes.forEach((element) => {
           a.push(parseInt(element.getAttribute('rel')))
        });

        grab_order_notes_response.map(function(person) {
            b.push(person['id']);
            p[`${person['id']}`] = person;
        });

        var order_notes = document.querySelector(".order_notes");

        (b.filter(x => !a.includes(x))).forEach((u) => {
            var note = p[u];

            var d = note['date_created']['date'].split(/[- :]/)
            var month_day_year = new Date(Date.UTC(d[0], (d[1]-1), d[2])).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'Europe/London' });

            var date = `${month_day_year} at ${parseInt(d[3])}:${d[4]} ${(parseInt(d[3]) >= 12 ? 'pm' : 'am')}`;

            var note_html = `<li rel="${note['id']}" class="note system-note">
                <div class="note_content">
                    <p>${note['content']}</p>
                </div>
                <p class="meta">
                    <abbr class="exact-date">
                       ${date}                 </abbr>   <a href="#" class="delete_note" role="button">Delete note</a>
                </p>
            </li>`;

            order_notes.innerHTML = note_html + order_notes.innerHTML
        })
    }
}

async function force_process() {
    const force_omeda_process_button = document.querySelector('#force-omeda-process');

    force_omeda_process_button.classList.add("disabled")
    force_omeda_process_button.textContent = 'Sending to Omeda...'

    const force_data_send_omeda_order = await fetch(window.location + '&omeda_force_data_send=' + order_param, {
        method: 'GET'
    });

    const force_data_send_omeda_order_text = await force_data_send_omeda_order.text();

    force_omeda_process_button.textContent = 'Checking Omeda response...'

    refresh_order_notes();

    force_omeda_process_button.textContent = 'Processing data sent to Omeda...'

    const force_process_omeda_order = await fetch(window.location + '&omeda_force_process=' + order_param, {
        method: 'GET'
    });

    const force_process_omeda_order_text = await force_process_omeda_order.text();

    refresh_order_notes();

    force_omeda_process_button.textContent = 'Completed! Check order notes...'

    force_omeda_process_button.classList.remove("disabled")
    force_omeda_process_button.classList.add("complete")

    await new Promise(r => setTimeout(r, 3500));

    force_omeda_process_button.classList.remove("complete")
    force_omeda_process_button.textContent = 'Force Process...'

}

document.addEventListener('DOMContentLoaded', function(event) {
    document.querySelector('#force-omeda-process').addEventListener('click', function() { force_process() }, false);
});