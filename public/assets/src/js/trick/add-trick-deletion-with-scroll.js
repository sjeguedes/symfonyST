import deleteTrick from './removal/delete-trick';

export default () => {
    // Manage trick deletion
    const trickRemovalLink = document.getElementById('st-delete-trick');
    if (trickRemovalLink !==null) {
        // Prepare element to which window will scroll after deletion
        // Here it is the same principle as in form.js!
        const trickUpdateFormElement = document.getElementById('st-update-trick-form');
        let referenceElementToScroll = trickRemovalLink;
        if (trickUpdateFormElement !== null) {
            referenceElementToScroll = document.getElementById('st-form');
        }
        referenceElementToScroll = referenceElementToScroll.parentElement.parentElement.parentElement;
        if (trickRemovalLink) {
            deleteTrick(trickRemovalLink, referenceElementToScroll);
        }
    }
}
