import deleteTrick from './trick/removal/delete-trick';
import imageListLoader from './all/image-list-loader';

export default () => {
    // ------------------- Paginated page (trick list) -------------------
    const element = document.getElementById('st-paginated-trick-list');
    if (element) {
        // No trick was found! So this will stop all the script.
        const nodes = element.querySelectorAll('.uk-card');
        if (0 === nodes.length) {
            return;
        }
        // Manage list actions
        nodes.forEach(card => {
            // Trick list image loader behavior when page is loaded.
            imageListLoader(document.getElementById('st-card-image-' + card.parentElement.getAttribute('data-offset')));

            // ------------------------------------------------------------------------------------------------------------

            // Manage trick deletion
            let trickRemovalLink = card.querySelector('.st-delete-trick');
            if (trickRemovalLink) {
                deleteTrick(card.querySelector('.st-delete-trick'), element);
            }
        });
    }
};
