import deleteUser from './removal/delete-user';

export default () => {
    // Manage user account deletion
    const userRemovalLink = document.getElementById('st-delete-user');
    if (userRemovalLink !==null) {
        // Prepare element to which window will scroll after deletion
        // Here it is the same principle as in form.js!
        const userUpdateFormElement = document.getElementById('st-update-profile');
        let referenceElementToScroll = userRemovalLink;
        if (userUpdateFormElement !== null) {
            referenceElementToScroll = document.getElementById('st-form');
        }
        referenceElementToScroll = referenceElementToScroll.parentElement.parentElement.parentElement;
        if (userRemovalLink) {
            deleteUser(userRemovalLink, referenceElementToScroll);
        }
    }
}
