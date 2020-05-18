import htmlStringHelper from './encode-decode-string';
export default () => {
    // Header with big images
    const headerElement = document.getElementById('header');
    if (headerElement) {
        // Load each big image which exists in header
     let image = null;
     const images = headerElement.querySelectorAll('.st-background-image-header');
        for (let i = 0; i < images.length; i ++) {
            // Prepare fade in effect for image
            images[i].style.opacity = '0';
            images[i].classList.remove('uk-invisible');
            // create image object based on background image
            image = new Image();
            image.src = images[i].getAttribute('data-src');
            // Call image loading rendering behavior:
            image.addEventListener("load", () => {
                // Hide the loading spinner which is corresponding to image
                if (images[i].previousElementSibling.classList.contains('st-header-spinner')) {
                    images[i].previousElementSibling.classList.add('uk-hidden');
                }
                // Show image with fade in effect
                images[i].style.transition = 'opacity 2s ease-in-out';
                images[i].style.opacity = '1';
            });
            // Image loading error
            image.addEventListener("error", () => {
                // Replace loading spinner by caution content message
                if (images[i].previousElementSibling.classList.contains('st-header-spinner')) {
                    images[i].previousElementSibling.classList.remove('uk-spinner');
                    images[i].previousElementSibling.removeAttribute('uk-spinner');
                    // Escape html message
                    // String helper
                    const htmlStringHandler = htmlStringHelper();
                    // "Media loading error" message with warning icon
                    let error = htmlStringHandler.htmlSpecialCharsOnString.encode(
                        document.getElementById('st-app').getAttribute('data-media-error')
                    );
                    images[i].previousElementSibling.innerHTML = `<span uk-icon="warning"></span>
                                                                  <span class="uk-text-small">&nbsp;`
                                                                    + error +
                                                                  `</span>`;
                }
            });
        }
    }
}
