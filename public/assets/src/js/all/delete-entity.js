import {AjaxPromiseLoader} from './ajax-request';
import createNotification from './create-notification';
import smoothScroll from './smooth-vertical-scroll';
import UIkit from '../../../uikit/dist/js/uikit.min';
import URIHelper from './encode-decode-uri';
export default (
    mediaRemovalLink,
    entityType,
    referenceElementToScroll = null,
    adjustYPosition = 0,
    successCallback = null,
    args = []
) => {
    // Resources:
    // Capitalize a string: https://flaviocopes.com/how-to-uppercase-first-letter-javascript/

    // ------------------------------------------------------------------------------------------------------------

    // Get corresponding entity deletion modal
    const entityRemovalModalElement = document.getElementById(`st-modal-delete-${entityType}`);
    // Removal button
    const deletionButton = document.getElementById(`st-confirm-delete-${entityType}-button`);
    const buttonSpinner = deletionButton.querySelector(`.st-delete-${entityType}-spinner`);
    deletionButton.setAttribute('data-action', mediaRemovalLink.getAttribute('data-action'));
    deletionButton.removalLink = mediaRemovalLink;

    // ------------------------------------------------------------------------------------------------------------

    // Check if entity removal modal is present on page.
    if (entityRemovalModalElement) {
        // Set UIkit notification group
        const capitalizedEntityType = entityType.charAt(0).toUpperCase() + entityType.slice(1);
        let groupOption = `delete${capitalizedEntityType}`;
        let deletionAction = null;

        // ------------------------------------------------------------------------------------------------------------

        // "click" event handler to perform an AJAX request when modal deletion button is clicked
        const clickEntityRemovalButtonHandler = () => {
            // Disable button
            deletionButton.classList.add('uk-disabled');
            // Show spinner but do not re-enable button
            buttonSpinner.classList.remove('uk-hidden');
            // Get action
            const pathHandler = URIHelper();
            deletionAction = deletionButton.removalLink.getAttribute('data-action');
            deletionAction = pathHandler.uriOnString.encode(deletionAction);
            const obj = {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                url: deletionAction,
                method: 'DELETE',
                body: '', // An empty body is set to show this is not used!
                async: true,
                withCredentials: false,
                responseType: 'json'
            };

            // ------------------------------------------------------------------------------------------------------------

            // Avoid multiple calls to ajax promise with a kind of "time out debounce"
            clearTimeout(deletionButton.clickActionFinished);
            // End of "deletion click" event
            deletionButton.clickActionFinished = setTimeout(() => {
                // Use promise (with loader instance) with callbacks:
                // "request(obj)" function can be used instead, to get promise: hre a class is called to learn about class handling
                new AjaxPromiseLoader(obj).getPromise().then((response) => {
                    // Re-enable button
                    deletionButton.classList.remove('uk-disabled');
                    // Hide spinner
                    buttonSpinner.classList.add('uk-hidden');
                    // No need to parse with JSON.parse(response): response is already an object
                    let ti = setTimeout(() => {
                        UIkit.notification.closeAll();
                        switch (response.status) {
                            case 0:
                                if (response.redirection !== undefined) {
                                    // Delay redirection
                                    let ti = setTimeout(() => {
                                        window.location = response.redirection;
                                        // Cancel timeout
                                        clearTimeout(ti);
                                    }, 1500);
                                } else {
                                    // Error notification
                                    let errorMessage = response.notification !== undefined
                                        ? response.notification
                                        : entityRemovalModalElement.getAttribute('data-error');
                                    let mustFormat = response.notification === undefined;
                                    createNotification(errorMessage, groupOption, mustFormat, 'error', 'warning', 5000);
                                }
                                break;
                            case 1:
                                if (response.redirection !== undefined) {
                                    // Delay redirection
                                    let ti = setTimeout(() => {
                                        window.location = response.redirection;
                                        // Cancel timeout
                                        clearTimeout(ti);
                                    }, 1500);
                                } else {
                                    // Use success callback if it is defined!
                                    if (typeof successCallback === 'function') {
                                        successCallback.apply(null, args);
                                    }
                                    // Success notification
                                    let successMessage = response.notification !== undefined
                                        ? response.notification
                                        : entityRemovalModalElement.getAttribute('data-success');
                                    let mustFormat = response.notification === undefined;
                                    createNotification(successMessage, groupOption, mustFormat, 'success', 'bell', 5000);
                                }

                                break;
                            default:
                                // Error notification
                                let technicalErrorMessage = entityRemovalModalElement.getAttribute('data-technical-error');
                                createNotification(technicalErrorMessage, groupOption, true, 'error', 'warning', 0);
                        }
                        // Cancel timeout
                        clearTimeout(ti);
                    }, response.redirection !== undefined ? 0 : 1500);
                    // Hide modal programmatically
                    UIkit.modal(entityRemovalModalElement).hide();
                })
                // It is important to chain to prevent ajax request from being called twice!
                    .catch(xhr => {
                        // Re-enable button
                        deletionButton.classList.remove('uk-disabled');
                        // Hide spinner
                        buttonSpinner.classList.add('uk-hidden');
                        // Technical error: show notification
                        let error;
                        error = (xhr.status !== undefined && xhr.statusText !== undefined) ? `error: ${xhr.status} - ${xhr.statusText}` : '';
                        // Aborted request
                        error = 0 !== xhr.status ? error : '';
                        // Delay response notification due to modal close side effect!
                        let ti = setTimeout(() => {
                            UIkit.notification.closeAll(groupOption);
                            // Error notification
                            let technicalErrorMessage = entityRemovalModalElement.getAttribute('data-technical-error');
                            createNotification(technicalErrorMessage + '\n' + error, groupOption, true, 'error', 'warning', 0);
                            // Cancel timeout
                            clearTimeout(ti);
                        }, 1500);
                        // Hide modal programmatically
                        UIkit.modal(entityRemovalModalElement).hide();
                    });
            }, 0);

            // ------------------------------------------------------------------------------------------------------------

            // Remove event listener
            //deletionButton.removeEventListener('click', clickEntityRemovalButtonHandler);
        };
        deletionButton.addEventListener('click', clickEntityRemovalButtonHandler);

        // ------------------------------------------------------------------------------------------------------------

        if (referenceElementToScroll !== null) {
            const modalElementID = `#st-modal-delete-${entityType}`;
            // Callback when modal is hidden
            UIkit.util.on(modalElementID, 'hidden', () => {
                // Re-position window scroll at a desired level
                smoothScroll(referenceElementToScroll, adjustYPosition);
            });
        }
    }
}
