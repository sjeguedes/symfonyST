import createNotification from "../all/create-notification";
import request from '../all/ajax-request';
export default (videoBox) => {
    // Resources:
    // Manage video iframe with JS: http://jsfiddle.net/onigetoc/quBD4/
    // Manage video iframe on server side with PHP: https://artisansweb.net/get-thumbnail-youtube-vimeo-dailymotion-videos/

    // ------------------------------------------------------------------------------------------------------------

    // Get "div" id video index
    let videoBoxIndex = videoBox.querySelector('.st-video-url')
        .getAttribute('id')
        .match(/^st-video-url-(\d+)$/)[1];
    let videoURLTextAreaElement = videoBox.querySelector('.st-video-url .uk-textarea');
    // Get existing saved video URL
    let savedVideoURL = videoURLTextAreaElement.getAttribute('data-saved-url');
    // Get frame or image tag preview elements
    let iframeParentPreview = videoBox.querySelector(`#st-video-iframe-${videoBoxIndex}`);
    let iframePreview = iframeParentPreview.querySelector('iframe');
    let iframeReplacementPreview = videoBox.querySelector(`#st-video-iframe-replacement-${videoBoxIndex}`);
    // Get watch link
    let watchLink = videoBox.querySelector(`#st-video-watch-link-${videoBoxIndex}`);
    // Get saved video text info
    let textInfo = videoBox.querySelector(`#st-video-text-info-${videoBoxIndex}`);
    // Get loading spinner
    let loadingSpinner = videoBox.querySelector(`#st-video-spinner-${videoBoxIndex}`);
    // Set UIkit notification group
    let groupOption = 'defineVideoURL';
    // Check existing textarea element
    if (videoURLTextAreaElement.length !== 0) {
        // Get previous defined VIDEO URL
        videoBox.previousURL = videoURLTextAreaElement.value;
        // Video URL textarea "blur" event handler
        const videoURLTextAreaBlurHandler = () => {
            // Get new video URL value
            let videoURL = videoURLTextAreaElement.value;
            // Will store checked video URL
            let checkedVideoURL = null;
            // Will store a timeout
            let to = undefined;
            // Stop process immediately if defined URL is empty or if there is no change!
            if (videoURL === '' || videoBox.previousURL === videoURL) {
                // Keep and update existing validation icon
                let textAreaCheck = videoURLTextAreaElement.nextElementSibling;
                if (textAreaCheck !== null) {
                    // Insert new error icon
                    videoURLTextAreaElement.nextElementSibling.insertAdjacentHTML(
                        'afterend',
                        '<span class="uk-form-icon uk-form-icon-flip uk-form-danger st-textarea-icon" uk-icon="icon: warning"></span>'
                    );
                    // Remove previous success icon
                    videoURLTextAreaElement.nextElementSibling.remove();
                }
                return;
            }
            // End of "blur" event
            clearTimeout(videoURLTextAreaElement.blurFinished);
            videoURLTextAreaElement.blurFinished = setTimeout(() => {
                checkedVideoURL = checkUrl(videoURL.trim());
                // URL is one among those which are expected!
                if (checkedVideoURL !== null) {
                    if (to !== undefined) clearTimeout(to);
                    // URL was adjusted for iframe use, so update textarea!
                    if (videoURL !== checkedVideoURL) {
                        videoURLTextAreaElement.value = checkedVideoURL;
                    }
                    // Show loading spinner
                    loadingSpinner.classList.remove('uk-hidden');
                    // Disable field temporarily
                    videoURLTextAreaElement.classList.add('uk-disabled');
                    // Delay process
                    to = setTimeout(() => {
                        // Assign videoURL value to videoBox object
                        videoBox.url = checkedVideoURL;
                        // Return if proxy URL is not found depending on data attribute
                        let proxyURL = document.getElementById('st-videos-collection')
                            .getAttribute('data-video-proxy');
                        if (proxyURL === null) return;
                        // Check content availability and clear timeout
                        checkLoadingCORSRequest(
                            'GET',
                            proxyURL + '/' + checkedVideoURL,
                            whenVideoLoaded,
                            whenVideoError,
                            [videoBox],
                            to
                        );
                    }, 2000);
                } else {
                    videoURLTextAreaElement.value = videoURL.trim();
                    whenVideoError(videoBox);
                    clearTimeout(to);
                }
                // Remove event listener
                videoURLTextAreaElement.removeEventListener('blur', videoURLTextAreaBlurHandler);
            }, 0);
        };
        videoURLTextAreaElement.addEventListener('blur', videoURLTextAreaBlurHandler);
    }

    // ------------------------------------------------------------------------------------------------------------

    // Check video loading with C.O.R.S
    const checkLoadingCORSRequest = (method, proxyUrl, resolvedCallback, errorCallback, args, timeOut) => {
        // XMLHttpRequest object
        const obj = {
            method: method,
            url: proxyUrl,
            async: true,
            withCredentials: false,
            responseType: 'json'
        };
        // Use promise with callbacks
        request(obj).then(response => {
            // No need to parse with JSON.parse(response).status: response is already an object
            if (response.status === 1) {
                resolvedCallback.apply(null, args);
                // Dispatch checked video success event
                let customEvent = new Event('checkedVideoSuccess');
                args[0].dispatchEvent(customEvent);
            } else {
                errorCallback.apply(null, args);
            }
            // Cancel timeOut
            clearTimeout(timeOut);
        }).catch(xhr => {
            errorCallback.apply(null, args);
            // Cancel timeOut
            clearTimeout(timeOut);
        });
    };

    // ------------------------------------------------------------------------------------------------------------

    // Check if URL is valid and can be accepted (and possibly replaced when it is a browser URL)
    // Browser URL is also allowed to be more user friendly, so it will be checked and replaced if needed!
    const checkUrl = (videoURL) => {
        switch (true) {
            case videoURL.indexOf('youtube.com') !== -1:
                videoURL = getYoutubeId(videoURL);
                break;
            case videoURL.indexOf('vimeo.com') !== -1:
                videoURL = getVimeoId(videoURL);
                break;
            case videoURL.indexOf('dailymotion.com') !== -1:
                videoURL = getDailymotionId(videoURL);
                break;
            default:
                videoURL = null;
        }
        return videoURL
    };

    // ------------------------------------------------------------------------------------------------------------

    // Get Youtube video id and adapt video URL
    const getYoutubeId = (videoURL) => {
        let matches;
        let videoId;
        switch (true) {
            case /\/embed\/([a-zA-Z0-9_-]+)$/.test(videoURL):
                matches = videoURL.match(/\/embed\/([a-zA-Z0-9_-]+)$/);
                videoId = matches[1];
                break;
            case /\/watch\?v=([a-zA-Z0-9_-]+)$/.test(videoURL):
                matches = videoURL.match(/\/watch\?v=([a-zA-Z0-9_-]+)$/);
                videoId = matches[1];
                videoURL = `https://www.youtube.com/embed/${videoId}`;
                // Show error notification message
                let message = videoURLTextAreaElement.getAttribute('data-replaced-url-info');
                createNotification(message, null, true, 'info', 'info');
                break;
            default:
                videoURL = null;
        }
        // Assign video provider page link value to videoBox object
        if (videoURL !== null) videoBox.browserLink = `https://www.youtube.com/watch?v=${videoId}`;
        return videoURL;
    };

    // ------------------------------------------------------------------------------------------------------------

    // Get Vimeo video id and adapt video URL
    const getVimeoId = (videoURL) => {
        let matches;
        let videoId;
        switch (true) {
            case /\/video\/(\d+)$/.test(videoURL):
                matches = videoURL.match(/\/video\/(\d+)$/);
                videoId = matches[1];
                break;
            case /vimeo\.com\/(\d+)$/.test(videoURL):
                matches = videoURL.match(/vimeo.com\/(\d+)$/);
                videoId = matches[1];
                videoURL = `https://player.vimeo.com/video/${videoId}`;
                // Show error notification message
                let message = videoURLTextAreaElement.getAttribute('data-replaced-url-info');
                createNotification(message, null, true, 'info', 'info');
                break;
            default:
                videoURL = null;
        }
        // Assign video provider page link value to videoBox object
        if (videoURL !== null) videoBox.browserLink = `https://www.vimeo.com/${videoId}`;
        return videoURL;
    };

    // ------------------------------------------------------------------------------------------------------------

    // Get Dailymotion video id and adapt video URL
    const getDailymotionId = (videoURL) => {
        let matches;
        let videoId;
        switch (true) {
            case /\/embed\/video\/([a-zA-Z0-9]+)$/.test(videoURL):
                matches = videoURL.match(/\/video\/([a-zA-Z0-9]+)$/);
                videoId = matches[1];
                break;
            case /dailymotion\.com\/video\/([a-zA-Z0-9]+)$/.test(videoURL):
                matches = videoURL.match(/dailymotion.com\/video\/([a-zA-Z0-9]+)$/);
                videoId = matches[1];
                videoURL = `https://www.dailymotion.com/embed/video/${videoId}`;
                // Show replaced URL info notification message
                let message = videoURLTextAreaElement.getAttribute('data-replaced-url-info');
                createNotification(message, null, true, 'info', 'info');
                break;
            default:
                videoURL = null;
        }
        // Assign video provider page link value to videoBox object
        if (videoURL !== null) videoBox.browserLink = `https://www.dailymotion.com/video/${videoId}`;
        return videoURL;
    };

    // ------------------------------------------------------------------------------------------------------------

    // "errorCallback" error callback function
    const whenVideoError = () => {
        // Hide loading spinner
        loadingSpinner.classList.add('uk-hidden');
        // Re-enable video URL textarea
        videoURLTextAreaElement.classList.remove('uk-disabled');
        // Hide video iframe and success infos (watch link and text info)
        iframeParentPreview.classList.add('uk-hidden');
        // Show default image tag for video iframe replacement
        iframeReplacementPreview.classList.remove('uk-hidden');
        // Hide saved video text info
        if (savedVideoURL !== null && textInfo !== null) {
            textInfo.classList.add('uk-hidden');
        }
        // Show error notification message
        let message = videoURLTextAreaElement.getAttribute('data-url-error');
        if (message && message !== '') {
            createNotification(message, groupOption, true, 'error', 'warning');
        }
        // Keep and update existing validation icon
        let textAreaCheck = videoURLTextAreaElement.nextElementSibling;
        if (textAreaCheck !== null) {
            // Insert new error icon
            videoURLTextAreaElement.nextElementSibling.insertAdjacentHTML(
                'afterend',
                '<span class="uk-form-icon uk-form-icon-flip uk-form-danger st-textarea-icon" uk-icon="icon: warning"></span>'
            );
            // Remove previous success icon
            videoURLTextAreaElement.nextElementSibling.remove();
        }
    };

    // ------------------------------------------------------------------------------------------------------------

    // "resolvedCallback" success callback function
    const whenVideoLoaded = (videoBox) => {
        // Hide loading spinner
        loadingSpinner.classList.add('uk-hidden');
        // Re-enable video URL textarea
        videoURLTextAreaElement.classList.remove('uk-disabled');
        // Update mini-iframe preview
        iframePreview.setAttribute('src', videoBox.url);
        // Update watch link
        watchLink.setAttribute('href', videoBox.browserLink);
        // Show video iframe and success infos (watch link and text info)
        iframeParentPreview.classList.remove('uk-hidden');
        // Show saved video text info
        if (savedVideoURL !== null && textInfo !== null) {
            if (savedVideoURL === videoBox.url) {
                textInfo.classList.remove('uk-hidden');
            } else {
                textInfo.classList.add('uk-hidden');
            }
        }
        // Hide default image tag for video iframe replacement
        iframeReplacementPreview.classList.add('uk-hidden');
        // Show success notification message
        let message = videoURLTextAreaElement.getAttribute('data-url-success');
        createNotification(message, groupOption, true, 'info', 'info');
        // Keep and update existing validation icon
        let textAreaCheck = videoURLTextAreaElement.nextElementSibling;
        if (textAreaCheck !== null) {
            // Insert new success icon
            videoURLTextAreaElement.nextElementSibling.insertAdjacentHTML(
                'afterend',
                '<span class="uk-form-icon uk-form-icon-flip uk-form-success st-textarea-icon" uk-icon="icon: check"></span>'
            );
            // Remove previous error icon
            videoURLTextAreaElement.nextElementSibling.remove();
        }
    };
};

