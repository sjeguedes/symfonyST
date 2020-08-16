import UIkit from '../../../../uikit/dist/js/uikit.min';
export default (mediaRemovalLink, parentElement, containerElement, mediaType) => {
    // Activate not recommended actions information in media deletion modal

    // ------------------------------------------------------------------------------------------------------------

    // Check if no more "main image", or no more (image or video) media information must be shown in modal
    let mediaTypeElements = containerElement.querySelectorAll(`.st-delete-${mediaType}`);
    let mediaTypeElementsLength = mediaTypeElements.length;
    // Get removal modal
    let removalModalElement = document.getElementById(`st-modal-delete-${mediaType}`);
    // Warn about "no more image or video" on deletion in modal
    if (mediaTypeElementsLength === 1) {
        [
            '#st-no-more-info',
            '#st-media-removal-no-more-label-info',
            '#st-media-removal-last-media-info'
        ].forEach(id => {
            removalModalElement.querySelector(id)
                .classList.remove('uk-hidden');
        });
    }
    // Warn about "main image" deletion in modal
    let mainImageLabelElement = parentElement.querySelector('.st-main-image-indicator');
    let isMainImageLabelHidden = mainImageLabelElement === null ? true : mainImageLabelElement.classList.contains('uk-hidden');
    if ('image' === mediaType && mainImageLabelElement !== null && !isMainImageLabelHidden) {
        [
            '#st-no-more-info',
            '#st-media-removal-no-more-label-info',
            '#st-media-main-image-info'
        ].forEach((id) => {
            removalModalElement.querySelector(id)
                .classList.remove('uk-hidden');
        });
    }
    // Callback when modal is hidden to hide all infos message for next openings
    let modalRemovalElementID = `#st-modal-delete-${mediaType}`;
    UIkit.util.on(modalRemovalElementID, 'hidden', () => {
        // Re-init information visibility state
        let idReferences = [
            '#st-no-more-info',
            '#st-media-removal-no-more-label-info',
            '#st-media-removal-last-media-info',
            '#st-media-main-image-info'
        ];
        // avoid issue for video modal removal case
        if (!/image/.test(modalRemovalElementID)) idReferences.splice(3, 1);
        idReferences.forEach((id) => {
            removalModalElement.querySelector(id)
                .classList.add('uk-hidden');
        });
    });
};
