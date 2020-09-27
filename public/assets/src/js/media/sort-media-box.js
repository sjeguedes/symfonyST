import Sortable from 'sortablejs';

export default () => {
    let collectionBoxRank;
    // ------------------------------------------------------------------------------------------------------------

    // Update show list rank and box label visual number for each box in collections
    const updateBoxCollectionRankAndLabel = (event, collectionBoxElement, boxElementIndex, boxElementLabelCssClass) => {
        // This condition based on event is not necessary but more explicit!
        if (event.item === collectionBoxElement) {
            event.item.querySelector('.st-show-list-rank').value = event.newIndex + 1;
            collectionBoxRank = event.newIndex + 1;
            // Update other boxes ranks
        } else {
            collectionBoxElement.querySelector('.st-show-list-rank').value = boxElementIndex + 1;
            collectionBoxRank = boxElementIndex + 1;
        }
        // Update only box number in label as regards box visual rank!
        let collectionBoxLabel = collectionBoxElement.querySelector(boxElementLabelCssClass);
        // Update box label text
        collectionBoxLabel.textContent = collectionBoxLabel.innerText.replace(new RegExp(/\d+$/, 'g'), collectionBoxRank.toString());
    };

    // ------------------------------------------------------------------------------------------------------------

    // Sort collections with this script and not UIkit which has issue with appearance when dragging.
    // https://github.com/SortableJS/Sortable

    // Enable images collection re-ordering
    let imagesCollectionSortableContainer = document.getElementById('st-images-collection-sortable-wrapper');
    let sortableImages = Sortable.create(imagesCollectionSortableContainer, {
        handle: '.uk-sortable-handle',
        store: null,
        direction: () => {
            return 'vertical';
        },
        // Element dragging ended
        onEnd: event => {
            // Update other boxes
            let imageToCropBoxElements = document.querySelectorAll('.st-image-to-crop');
            if (imageToCropBoxElements.length > 1) {
                imageToCropBoxElements.forEach((imageBox, index) => {
                    // Update current moved "image to crop" box hidden input value which stores corresponding rank.
                    updateBoxCollectionRankAndLabel(event, imageBox, index, '.st-image-to-crop-label');
                });
            }
        }
    });

    // ------------------------------------------------------------------------------------------------------------

    // Sort collections with this script and not UIkit which has issue with appearance when dragging.
    // https://github.com/SortableJS/Sortable

    // Enable videos collection re-ordering
    let videosCollectionSortableContainer = document.getElementById('st-videos-collection-sortable-wrapper');
    let sortableVideos = Sortable.create(videosCollectionSortableContainer, {
        handle: '.uk-sortable-handle',
        store: null,
        direction: () => {
            return 'vertical';
        },
        // Element dragging ended
        onEnd: event => {
            // Update other boxes
            let videoInfosBoxElements = document.querySelectorAll('.st-video-infos');
            if (videoInfosBoxElements.length > 1) {
                videoInfosBoxElements.forEach((videoBox, index) => {
                    // Update current moved "video infos" box hidden input value which stores corresponding rank.
                    updateBoxCollectionRankAndLabel(event, videoBox, index, '.st-video-infos-label');
                });
            }
        }
    });
}
