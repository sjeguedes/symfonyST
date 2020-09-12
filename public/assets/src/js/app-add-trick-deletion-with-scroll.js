// *************** Custom scripts ***************

// Custom deletion with smooth scroll scripts
import addTrickDeletionWithScroll from './trick/add-trick-deletion-with-scroll';

// Init necessary scripts when DOM is ready (avoid issue with unloaded script in browser)
const setUp = () => {
    // Page partial
    addTrickDeletionWithScroll();
};

// Add our event listeners
window.addEventListener('DOMContentLoaded', setUp, false);
window.addEventListener('unload', setUp, false);
window.addEventListener('popstate', setUp, false);
