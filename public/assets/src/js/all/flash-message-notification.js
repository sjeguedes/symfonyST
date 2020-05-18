import UIkit from '../../../uikit/dist/js/uikit.min';
export default () => {
    // ------------------- Flash message -------------------
    let element = document.getElementById('st-flash-message-content');
    if (element) {
        let flashMessage = element.querySelector('.st-flash-message');
        let timeout = setTimeout( () => {
            switch (flashMessage.getAttribute('data-flash-type')) {
                case 'success':
                    UIkit.notification({
                        message: `<div class="uk-text-center">
                                    <span uk-icon='icon: bell'></span>&nbsp;` + flashMessage.innerText +
                            `</div>`,
                        status: 'success',
                        pos: 'top-center',
                        timeout: 10000
                    });
                    break;
                case 'warning':
                case 'danger':
                    UIkit.notification({
                        message: `<div class="uk-text-center">
                                    <span uk-icon='icon: warning'></span>&nbsp;` + flashMessage.innerText +
                            `</div>`,
                        status: 'error',
                        pos: 'top-center',
                        timeout: 10000
                    });
                    break;
                // Symfony "info" or "custom" type
                default:
                    UIkit.notification({
                        message: `<div class="uk-text-center">
                                    <span uk-icon='icon: bell'></span>&nbsp;` + flashMessage.innerText +
                            `</div>`,
                        status: 'info',
                        pos: 'top-center',
                        timeout: 10000
                    });
            }
            clearTimeout(timeout);
        }, 1000);
    }
}
