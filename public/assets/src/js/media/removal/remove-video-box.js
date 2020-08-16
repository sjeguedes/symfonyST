export default (removeVideoButtonElement, videoInfosBoxElements) => {
    let targetedElement = removeVideoButtonElement;
    // Remove corresponding "video infos" box by using its wrapper which was created dynamically.
    // Look at "addVideoButton" click event listener!
    // CAUTION! DOM contains 1 more parent element compared to "image to crop" box due to video AJAX loading process
    if (targetedElement.parentElement.parentElement.parentElement.classList.contains('st-video-infos-wrapper')) {
        targetedElement.parentElement.parentElement.parentElement.remove();
        // Remove directly "video infos" box (.st-video-infos) if no wrapper exists.
    } else {
        targetedElement.parentElement.parentElement.remove();
    }
    // Are there existing video blocks?
    // Loop on existing "video infos" boxes to update video box index name
    videoInfosBoxElements = document.querySelectorAll('.st-video-infos');
    let videoElementsLength = videoInfosBoxElements.length;
    if (videoElementsLength >= 1) {
        videoInfosBoxElements.forEach((videoBox, index) => {
            // Activate or de-activate sortable handle action
            if (videoElementsLength > 1) {
                videoBox.querySelector('.uk-sortable-handle').classList.remove('uk-hidden');
            } else {
                videoBox.querySelector('.uk-sortable-handle').classList.add('uk-hidden');
            }
            // Prepare rank to show in video box label
            let rank = index + 1;
            // Update only video box number in label as regards video box visual rank!
            let videoBoxLabel = videoBox.querySelector('.st-video-infos-label');
            // Update video box label text
            videoBoxLabel.textContent = videoBoxLabel.innerText.replace(new RegExp(/\d+$/, 'g'), rank.toString());
            // Update show list rank to avoid constraint violation issue on remove
            videoBox.querySelector('.st-show-list-rank').value = rank;
        });
    }
};
