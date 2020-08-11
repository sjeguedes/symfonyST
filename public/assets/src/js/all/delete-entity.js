import {AjaxPromiseLoader} from './ajax-request';
import createNotification from './create-notification';
import smoothScroll from './smooth-vertical-scroll';
import UIkit from '../../../uikit/dist/js/uikit.min';
import URIHelper from './encode-decode-uri';
export default (entityType, referenceElementToScroll = null, adjustYPosition = 0) => {
    // Resources:
    // Capitalize a string: https://flaviocopes.com/how-to-uppercase-first-letter-javascript/

    // ------------------------------------------------------------------------------------------------------------

    // Get corresponding entity deletion modal
    const entityRemovalModalElement = document.getElementById(`st-modal-delete-${entityType}`);
    // Removal button
    const deletionButton = document.getElementById(`st-confirm-delete-${entityType}-button`);
    // Get entity deletion links
    let entityRemovalLinkElements = null;

    // ------------------------------------------------------------------------------------------------------------

    // "click" entity removal link event handler
    const setEntityRemovalLinksListener = () => {
        // Get entity deletion links
        entityRemovalLinkElements = document.querySelectorAll(`.st-delete-${entityType}`);
        // Loop on removal links
        entityRemovalLinkElements.forEach((removalLink) => {
            // Update deletion button action (requested URI)
            const clickEntityRemovalLinkHandler = () => {
                //setEntityRemovalLinksListener();
                // Add "data-action" attribute on button
                deletionButton.setAttribute('data-action', removalLink.getAttribute('data-action'));

                // ------------------------------------------------------------------------------------------------------------

                // Remove event listener
                removalLink.removeEventListener('click', clickEntityRemovalLinkHandler);
            };
            removalLink.addEventListener('click', clickEntityRemovalLinkHandler);
        });
        return entityRemovalLinkElements;
    };

    // ------------------------------------------------------------------------------------------------------------
    // Get links only if modal element exists!
    if (entityRemovalModalElement && deletionButton) entityRemovalLinkElements = setEntityRemovalLinksListener();
    // Check if at least one entity removal link, and entity removal modal are present on page.
    if (entityRemovalLinkElements && entityRemovalLinkElements.length >= 1 && entityRemovalModalElement) {
        // Removal button
        const deletionButton = document.getElementById(`st-confirm-delete-${entityType}-button`);
        const buttonSpinner = deletionButton.querySelector(`.st-delete-${entityType}-spinner`);
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
            deletionAction = deletionButton.getAttribute('data-action');
            deletionAction = pathHandler.uriOnString.encode(deletionAction);
            const obj = {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                url: deletionAction,
                method: 'DELETE',
                body: '',
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
                                    window.location = response.redirection;
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
                                    window.location = response.redirection;
                                } else {
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
            deletionButton.removeEventListener('click', clickEntityRemovalButtonHandler);
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
