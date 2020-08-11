export default (removeImageButtonElement, imageToCropBoxElements) => {
    let targetedElement = removeImageButtonElement;
    // Remove corresponding "image to crop" box by using its wrapper which was created dynamically.
    // Look at "addImageButton" click event listener!
    if (targetedElement.parentElement.parentElement.classList.contains('st-image-to-crop-wrapper')) {
        targetedElement.parentElement.parentElement.remove();
        // Remove directly "image to crop" box (.st-image-to-crop) if no wrapper exists.
    } else {
        targetedElement.parentElement.remove();
    }
    // Loop on existing "image to crop" boxes to update image box index name
    imageToCropBoxElements = document.querySelectorAll('.st-image-to-crop');
    if (imageToCropBoxElements.length !== 0) {
        imageToCropBoxElements.forEach((imageBox, index) => {
            // Prepare rank to show in image box label
            let rank = index + 1;
            // Update only image box number in label as regards image box visual rank!
            let imageBoxLabel = imageBox.querySelector('.st-image-to-crop-label');
            // Update image box label text
            imageBoxLabel.textContent = imageBoxLabel.innerText.replace(new RegExp(/\d+$/, 'g'), rank.toString());
            // Update show list rank to avoid constraint violation issue on remove
            imageBox.querySelector('.st-show-list-rank').value = rank;
        });
    }
};
