import {AjaxPromiseLoader} from '../../all/ajax-request';
import createNotification from "../../all/create-notification";
import removeImageBox from './remove-image-box';
import smoothScroll from '../../all/smooth-vertical-scroll';
import stringHelper from '../../all/encode-decode-string';
import UIkit from '../../../../uikit/dist/js/uikit.min';
export default (removeMediaButtonElement, referenceElementToScroll, mediaBoxElements) => {
    // Ajax with xhr: https://dev.to/nikola/making-ajax-calls-in-pure-javascript-the-old-way-ed5
    // Handling multiple ajax calls: https://medium.com/@alperen.talaslioglu/handling-multiple-ajax-calls-for-same-service-646a4c7e5fe7
    // xhr ready state: https://developer.mozilla.org/fr/docs/Web/API/XMLHttpRequest/readyState
    // Event listeners: https://medium.com/beginners-guide-to-mobile-web-development/one-off-event-listeners-in-javascript-92e19c4c0336
    // Event propagation: https://javascript.info/bubbling-and-capturing
    // Event: https://www.quirksmode.org/js/events_order.html

    // ------------------------------------------------------------------------------------------------------------

    // Get media types
    let allowedMediaTypes = ['image', 'video'];
    let linkToModalElement = removeMediaButtonElement;
    let linkID = linkToModalElement.getAttribute('id');
    let matches = linkID.match(/st-([a-z]+)-remove-button-\d+/i);
    if (matches === null || !allowedMediaTypes.includes(matches[1])) {
        return;
    }
    let mediaType = matches[1];
    // Get corresponding deletion form
    const formElement = document.getElementById(`st-delete-${mediaType}-form`);
    if (formElement) {
        // String helper to decode string
        const htmlStringHandler = stringHelper();
        // Form request URI
        const formAction = formElement.getAttribute('action');
        // Submit button
        const submitButton = document.getElementById(`st-confirm-delete-${mediaType}-button`);
        const buttonSpinner = submitButton.querySelector('.st-delete-spinner');
        const modalElement = document.getElementById(`st-modal-delete-${mediaType}`);
        const modalElementID =  '#' + modalElement.getAttribute('id');
        // Set UIkit notification group
        let groupOption = 'deleteImage';
        // Get media data to remove it with SQL query
        let mediaUuid = linkToModalElement.getAttribute('data-uuid');
        let mediaName = linkToModalElement.getAttribute('data-name');
        let mediaOwnerType = linkToModalElement.getAttribute('data-type');
        // Get default technical error
        let technicalError = formElement.getAttribute('data-error');
        technicalError = htmlStringHandler.htmlSpecialCharsOnString.encode(technicalError);
        technicalError = htmlStringHandler.formatOnString.nl2br(technicalError);
        // Feed form fields values with data attributes
        formElement.querySelector(`.st-delete-${mediaType}-uuid`).setAttribute('value', mediaUuid);
        formElement.querySelector(`.st-delete-${mediaType}-name`).setAttribute('value', mediaName);
        formElement.querySelector(`.st-delete-${mediaType}-type`).setAttribute('value', mediaOwnerType);
        // Perform AJAX request on form submit
        const submitRemoveMediaFormHandler = event => {
            // Prevent form from being submitted normally!
            event.preventDefault();
            // Get all form data
            const formData = new FormData(formElement);
            // Disable button
            submitButton.classList.add('uk-disabled');
            // Show spinner but do not re-enable button
            buttonSpinner.classList.remove('uk-hidden');
            const obj = {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                url: formAction,
                method: 'POST',
                body: formData,
                async: true,
                withCredentials: false,
                responseType: 'json'
            };

            // ------------------------------------------------------------------------------------------------------------

            // Avoid multiple calls to ajax promise with a kind of "time out debounce"
            clearTimeout(formElement.submitFinished);
            // End of "form submit" event
            formElement.submitFinished = setTimeout(() => {
                // Use promise (with loader instance) with callbacks:
                // "request(obj)" function can be used instead, to get promise: hre a class is called to learn about class handling
                new AjaxPromiseLoader(obj).getPromise().then((response) => {
                    // Re-enable button
                    submitButton.classList.remove('uk-disabled');
                    // Hide spinner
                    buttonSpinner.classList.add('uk-hidden');
                    // No need to parse with JSON.parse(response): response is already an object
                    for (let prop in response) {
                        if (Object.prototype.hasOwnProperty.call(response, prop)) {
                            // Delay response notification and box removal
                            let ti = setTimeout(() => {
                                UIkit.notification.closeAll(groupOption);
                                switch (prop.toString()) {
                                    case 'formError':
                                        // Error notification
                                        createNotification(response.formError.notification, groupOption, false, 'error', 'warning', 5000);
                                        break;
                                    case 'formSuccess':
                                        // Success notification
                                        createNotification(response.formSuccess.notification, groupOption, false, 'success', 'bell', 5000);
                                        // Remove media box element and make necessary changes
                                        switch (matches[1]) {
                                            // Remove image to crop box element
                                            case 'image':
                                                removeImageBox(linkToModalElement, mediaBoxElements);
                                                break;
                                            // Remove video infos box element
                                            case 'video':
                                                // TODO: complete code for videos!
                                                //removeVideoBox(linkToModalElement, videoInfosBoxElements);
                                                break;
                                        }
                                        break;
                                    default:
                                        // Error notification
                                        createNotification(technicalError, groupOption, false, 'error', 'warning', 0);
                                }
                                // Cancel timeout
                                clearTimeout(ti);
                            }, 1500);
                        }
                    }
                    // Hide modal programmatically
                    UIkit.modal(modalElement).hide();
                })
                // It is important to chain to prevent ajax request from being called twice!
                .catch(xhr => {
                    // Re-enable button
                    submitButton.classList.remove('uk-disabled');
                    // Hide spinner
                    buttonSpinner.classList.add('uk-hidden');
                    // Technical error: show notification
                    let error;
                    error = (xhr.status !== undefined && xhr.statusText !== undefined) ? `error: ${xhr.status} - ${xhr.statusText}` : '';
                    // Aborted request
                    error = 0 !== xhr.status ? error : '';
                    // Delay response notification and box removal
                    let ti2 = setTimeout(() => {
                        UIkit.notification.closeAll(groupOption);
                        // Error notification
                        createNotification(technicalError + `<br>` + error, groupOption, false, 'error', 'warning', 0);
                        // Cancel timeout
                        clearTimeout(ti2);
                    }, 1500);
                    // Hide modal programmatically
                    UIkit.modal(modalElement).hide();
                });
            }, 0);

            // ------------------------------------------------------------------------------------------------------------

            // Remove event listener
            formElement.removeEventListener('submit', submitRemoveMediaFormHandler);
        };
        formElement.addEventListener('submit', submitRemoveMediaFormHandler);

        // ------------------------------------------------------------------------------------------------------------

        // Callback when modal is hidden
        UIkit.util.on(modalElementID, 'hidden', () => {
            // Re-position window scroll at form level
            smoothScroll(referenceElementToScroll, 0);
        });
    }
}
