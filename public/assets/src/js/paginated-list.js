import deleteTrick from './trick/removal/delete-trick';
import imageListLoader from './all/image-list-loader';
export default () => {
    // ------------------- Paginated page (trick list) -------------------
    const element = document.getElementById('st-paginated-trick-list');
    if (element) {
        // No trick was found! So this will stop all the script.
        if (0 === element.querySelectorAll('.uk-card').length) {
            return;
        }
        const nodes = element.querySelectorAll('.uk-card');
        // Trick list image loader behavior when page is loaded.
        nodes.forEach(card => {
            imageListLoader(document.getElementById('st-card-image-' + card.parentElement.getAttribute('data-offset')));
        });

        // ------------------------------------------------------------------------------------------------------------

        // Manage trick deletion
        deleteTrick();
    }
};
