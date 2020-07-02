import htmlStringHelper from './encode-decode-string';
export default image => {
    // Load each big image which exists in header
    // Prepare fade in effect for image
    image.style.opacity = '0';
    image.classList.remove('uk-invisible');
    // create image object based on image tag or background image
    let img = new Image();
    img.src = (image.getAttribute('src') || image.getAttribute('data-src'));
    // Call image loading rendering behavior:
    img.addEventListener("load", () => {
        // Hide the loading spinner which is corresponding to image
        if (image.previousElementSibling.classList.contains('st-media-spinner')) {
            image.previousElementSibling.classList.add('uk-hidden');
        }
        // Show image with fade in effect
        image.style.transition = 'opacity 1s ease-in-out';
        image.style.opacity = '1';
    });
    img.addEventListener("error", () => {
        // Replace loading spinner by caution content message
        if (image.previousElementSibling.classList.contains('st-media-spinner')) {
            image.previousElementSibling.classList.remove('uk-spinner');
            image.previousElementSibling.removeAttribute('uk-spinner');
            // Escape html message
            // String helper
            const htmlStringHandler = htmlStringHelper();
            // "Media loading error" message with warning icon
            let error = htmlStringHandler.htmlSpecialCharsOnString.encode(
                document.getElementById('st-app').getAttribute('data-media-error')
            );
            image.previousElementSibling.innerHTML = `<span uk-icon="warning"></span>
                                                      <span class="uk-text-small">&nbsp;`
                                                        + error +
                                                      `</span>`;
        }
    });
}
