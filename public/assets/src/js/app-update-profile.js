// Import Cropper css styles for user avatar crop
import '../scss/cropper.scss';

// *************** Custom scripts ***************

// Header image loader scripts
import imageHeaderLoader from './all/image-header-loader';

// flash messages notification scripts
import flashMessage from './all/flash-message-notification';

// Forms utils scripts
import form from './all/form';

// User update profile page scripts
import updateProfile from './update-profile';

// Init necessary scripts when DOM is ready (avoid issue with unloaded script in browser)
const setUp = () => {
    // Common elements
    imageHeaderLoader();
    flashMessage();
    form();
    // Pages
    updateProfile();
};

// Add our event listeners
window.addEventListener('DOMContentLoaded', setUp, false);
window.addEventListener('unload', setUp, false);
window.addEventListener('popstate', setUp, false);
