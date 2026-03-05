function disbatch_snackbar_notification(message) {
    wp.data.dispatch("core/notices").createNotice(
        "error",
        message,
        {
            type: "snackbar",
            isDismissible: true,
        }
    );
}

function check_session_notifications() {
    const apiUrl = '/wp-admin/?q=notifications&post=' + uploadNotificationScript.postID;

    fetchJsonFromUrl(apiUrl).then(data => {
        const message = data['message'];
        disbatch_snackbar_notification(message);
    }).catch(error => {
        console.error('Error:', error);
    });
}

async function fetchJsonFromUrl(url) {
    try {
        const response = await fetch(url);

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
    }
}

function handleSnackbarDetection(mutationsList, observer) {
    const processedSnackbarElements = new Set();

    mutationsList.forEach(function (mutation) {
        if (mutation.addedNodes.length > 0) {
            const snackbarElements = document.querySelectorAll('.components-snackbar__content');

            snackbarElements.forEach(function (snackbarElement) {
                if (!processedSnackbarElements.has(snackbarElement)) {
                    processedSnackbarElements.add(snackbarElement);
                    if(snackbarElement.textContent.toLowerCase().includes('error while uploading')) {
                        check_session_notifications();
                    }
                }
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const targetElement = document.getElementById('editor');
    const observer = new MutationObserver(handleSnackbarDetection);
    const observerConfig = { childList: true, subtree: true };

    observer.observe(targetElement, observerConfig);
});