document.addEventListener("DOMContentLoaded", function () {
    const currentPageURL = window.location.href;

    // Define a function to determine the request type based on the URL
    function getRequestType(url) {
        if (url.includes('/checkout/')) {
            return 'general_permissions_update';
        } else if (url.includes('/my-account/')) {
            return 'current_user_permissions_update';
        }
        return null; // Return null if no match
    }

    const requestType = getRequestType(currentPageURL);

    // If there is a valid request type, proceed with sending the request
    if (requestType) {
        const params = { request: requestType };
        sendGA4Request(params).then(responseData => {
            // Handle the response if needed
        });
    }

    // Function to send the GA4 request
    function sendGA4Request(params) {
        const queryString = new URLSearchParams(params).toString();
        const endpointURL = `/wp-json/gfp/v1/request?${queryString}`;

        return fetch(endpointURL)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Error: ${response.status} ${response.statusText}`);
                }
                return response.json();
            })
            .catch(error => {
                console.error('Fetch Error:', error);
            });
    }
});
