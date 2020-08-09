import htmlStringHelper from './encode-decode-string';
export default image => {
    // String helper
    const htmlStringHandler = htmlStringHelper();
    // Load each big image which exists in header
    // Prepare fade in effect for image
    image.style.opacity = '0';
    image.classList.remove('uk-invisible');
    // Create image object based on image tag or background image
    let img = new Image();
    let imageSource = image.getAttribute('src') || image.getAttribute('data-src');
    img.src = imageSource;
    // Store default image source
    let defaultSource = htmlStringHandler.htmlSpecialCharsOnString.encode(
        image.getAttribute('data-default-image')
    );
    image.src = defaultSource;
    // Call image loading rendering behavior:
    img.addEventListener("load", () => {
        image.src = imageSource;
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
