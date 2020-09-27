// *************** Custom scripts ***************

// Custom sortable media box scripts
import sortMediaBox from './media/sort-media-box';

// Init necessary scripts when DOM is ready (avoid issue with unloaded script in browser)
const setUp = () => {
    // Page partial
    sortMediaBox();
};

// Add our event listeners
window.addEventListener('DOMContentLoaded', setUp, false);
window.addEventListener('unload', setUp, false);
window.addEventListener('popstate', setUp, false);
