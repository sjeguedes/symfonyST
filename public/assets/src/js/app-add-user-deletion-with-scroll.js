// *************** Custom scripts ***************

// Custom user (account) deletion with smooth scroll scripts
import addUserDeletionWithScroll from './user/add-user-deletion-with-scroll';

// Init necessary scripts when DOM is ready (avoid issue with unloaded script in browser)
const setUp = () => {
    // Page partial
    addUserDeletionWithScroll();
};

// Add our event listeners
window.addEventListener('DOMContentLoaded', setUp, false);
window.addEventListener('unload', setUp, false);
window.addEventListener('popstate', setUp, false);
