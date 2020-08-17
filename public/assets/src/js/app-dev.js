// Manage polyfills for ES6
import "babel-polyfill";
// Styles from SASS including UIkit customization
import styles from '../scss/app-dev.scss';

// Or import from custom relative directory (
//import css from '../../uikit/dist/css/uikit.css';

// *************** Custom scripts ***************

// Header image loader scripts
import imageHeaderLoader from './all/image-header-loader';

// flash messages notification scripts
import flashMessage from './all/flash-message-notification';

// Forms utils scripts
import form from './all/form';

// Home page scripts
import home from './home';

// Paginated list page scripts
import paginatedList from './paginated-list';

// Single trick page scripts
import single from './single';

// User update profile page scripts
import updateProfile from './update-profile';

// Trick creation and update page scripts
import createOrUpdateTrick from './create-or-update-trick';

// Init necessary scripts when DOM is ready (avoid issue with unloaded script in browser)
const setUp = () => {
    // Common elements
    imageHeaderLoader();
    flashMessage();
    form();
    // Pages
    home();
    paginatedList();
    single();
    updateProfile();
    createOrUpdateTrick();
};
// Add our event listeners
window.addEventListener('DOMContentLoaded', setUp, false);
window.addEventListener('unload', setUp, false);
window.addEventListener('popstate', setUp, false);

