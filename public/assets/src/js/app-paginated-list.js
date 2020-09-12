// *************** Custom scripts ***************

// Header image loader scripts
import imageHeaderLoader from './all/image-header-loader';

// flash messages notification scripts
import flashMessage from './all/flash-message-notification';

// Paginated list page scripts
import paginatedList from './paginated-list';

// Init necessary scripts when DOM is ready (avoid issue with unloaded script in browser)
const setUp = () => {
    // Common elements
    imageHeaderLoader();
    flashMessage();
    // Pages
    paginatedList();
};

// Add our event listeners
window.addEventListener('DOMContentLoaded', setUp, false);
window.addEventListener('unload', setUp, false);
window.addEventListener('popstate', setUp, false);
