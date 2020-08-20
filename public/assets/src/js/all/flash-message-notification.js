import createNotification from './create-notification';
export default () => {
    // ------------------- Flash message -------------------
    let element = document.getElementById('st-flash-message-content');
    if (element) {
        let flashMessage = element.querySelector('.st-flash-message');
        let timeout = setTimeout( () => {
            switch (flashMessage.getAttribute('data-flash-type')) {
                case 'success':
                    createNotification(
                        flashMessage.innerText,
                        null,
                        true,
                        'success',
                        'bell',
                        10000
                    );
                    break;
                case 'warning':
                case 'danger':
                    createNotification(
                        flashMessage.innerText,
                        null,
                        true,
                        'error',
                        'warning',
                        10000
                    );
                    break;
                // Symfony "info" or "custom" type
                default:
                    createNotification(
                        flashMessage.innerText,
                        null,
                        true,
                        'info',
                        'info',
                        10000
                    );
            }
            clearTimeout(timeout);
        }, 1000);
    }
}
