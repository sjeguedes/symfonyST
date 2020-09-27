// *************** Custom scripts ***************

// Custom scroll to media box with URL hash scripts
import scrollToMediaBox from './media/scroll-to-media-box-with-hash';

// Init necessary scripts when DOM is ready (avoid issue with unloaded script in browser)
const setUp = () => {
    // Page partial
    scrollToMediaBox();
};

// Add our event listeners
window.addEventListener('DOMContentLoaded', setUp, false);
window.addEventListener('unload', setUp, false);
window.addEventListener('popstate', setUp, false);
