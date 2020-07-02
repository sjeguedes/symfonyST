import request from './all/ajax-request';
import UIkit from '../../uikit/dist/js/uikit.min';
import stringHelper from "./all/encode-decode-string";
export default () => {
    // Resources:
    // https://varvy.com/pagespeed/defer-videos.html
    // https://developer.mozilla.org/fr/docs/Web/JavaScript/Reference/Objets_globaux/Array/concat
    // https://davidwalsh.name/nodelist-array
    // https://siderite.blogspot.com/2013/04/detecting-if-url-can-be-loaded-in-iframe.html#at1101777673
    // https://webdesign.tutsplus.com/tutorials/how-to-lazy-load-embedded-youtube-videos--cms-26743
    // https://davidwalsh.name/css-animation-callback
    // http://miloq.blogspot.com/2011/05/preload-images-in-javascript.html
    // https://stackoverflow.com/questions/6941533/get-protocol-domain-and-port-from-url
    // http://javascript.info/cross-window-communication
    // https://ourcodeworld.com/articles/read/291/how-to-solve-the-client-side-access-control-allow-origin-request-error-with-your-own-symfony-3-api
    // https://codepen.io/netsi1964/post/avoiding-the-javascript-cross-domain-loading-error
    // https://stackoverflow.com/questions/3076414/ways-to-circumvent-the-same-origin-policy
    // https://stackoverflow.com/questions/256754/how-to-pass-arguments-to-addeventlistener-listener-function
    // JavaScript cross domain proxy: https://www.youtube.com/watch?v=o8puzjzpjqo
    // https://stackoverflow.com/questions/1973140/parsing-json-from-xmlhttprequest-responsejson
    // https://developer.mozilla.org/en-US/docs/Web/API/CustomEvent/CustomEvent
    // Script for APIs
    const singleSliderElement = document.getElementById('st-single-slider');
    if (singleSliderElement) {

        // ------------------------------------------------------------------------------------------------------------
        // ------------------------------------------------------------------------------------------------------------

        // String helper to decode string
        const stringHandler = stringHelper();
        const videoProxyPath = stringHandler.htmlAttributeOnString.unescape(singleSliderElement.getAttribute('data-video-proxy'));

        // CUSTOM FUNCTIONS TO IMPROVE SCRIPT \\

        // Detect transition end event for current browser
        // https://davidwalsh.name/css-animation-callback
        const whichTransitionEvent = () => {
            let transition = null;
            let element = document.createElement('fakeElement');
            let transitions = {
                'transition':'transitionend',
                'OTransition':'oTransitionEnd',
                'MozTransition':'transitionend',
                'WebkitTransition':'webkitTransitionEnd'
            };
            for (transition in transitions) {
                if ( element.style[transition] !== undefined ) {
                    return transitions[transition];
                }
            }
        };

        // ------------------------------------------------------------------------------------------------------------

        // Modal
        const mediaModal = media => {
            // Avoid issue when no modal element was generated due to media error!
            if (null === media.nextElementSibling) {
                return;
            }
            // Iframe behavior inside modal
            if (media.nextElementSibling.classList.contains('st-modal')) {
                let modalElementID = '#' + media.nextElementSibling.getAttribute('id');
                let mediaElement;
                let matches = modalElementID.match(/(image|video)/gi);
                if (matches !== null) {
                    mediaElement = document.querySelector(modalElementID + ` .st-modal-${matches[0] === 'image' ? 'image' : 'iframe'}`); // ".st-modal-image" or ".st-modal-iframe" (video)
                } else {
                    return;
                }
                // Load media before modal is shown
                UIkit.util.on(modalElementID, 'beforeshow', () => {
                    if (modalElementID.match(/video/gi) !== null) {
                        // Stop current media video which is playing in slider
                        stopAnyOtherVideos(currentPlayers);
                        let iframeSrc = stringHandler.htmlAttributeOnString.unescape(mediaElement.getAttribute('data-src'));
                        iframeSrc = iframeSrc.replace('autoplay=0', 'autoplay=1');
                        // Load iframe and autoplay modal iframe video
                        mediaElement.setAttribute('src', iframeSrc);
                        // Manage video loading with C.O.R.S ajax request
                        mediaElement.addEventListener("load", () => {
                            // This event do not guaranty iframe content is correctly loaded, so we use C.O.R.S proxy.
                            let proxyURL = videoProxyPath + '/' + iframeSrc;
                            checkLoadingCORSRequest('GET', proxyURL, afterMediaLoaded, whenMediaError, [mediaElement]);
                        });
                    } else {
                        // Load image (image loading was already checked before with thumbnail src which the same!)
                        mediaElement.setAttribute('src', mediaElement.getAttribute('data-src'));
                        // Call image loading rendering behavior:
                        mediaElement.addEventListener("load", () => {
                            // This event guaranties an image is correctly loaded contrary to iframe.
                            afterMediaLoaded(mediaElement);
                        });
                        // Call image loading error behavior:
                        mediaElement.addEventListener("error", () => {
                            whenMediaError(mediaElement);
                        });
                    }
                });
                // Reset video media before modal is hidden
                UIkit.util.on(modalElementID, 'beforehide', () => {
                    if (modalElementID.match(/video/gi) !== null) {
                        // Stop video removing src
                        mediaElement.removeAttribute('src');
                    }
                });
                // Reset image media when modal is hidden
                UIkit.util.on(modalElementID, 'hidden', () => {
                    if (modalElementID.match(/image/gi) !== null) {
                        // Reset image removing src
                        mediaElement.removeAttribute('src');
                    }
                });
            }
        };

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

        // Define image or iframe loading error behavior
        const whenMediaError = media => {
            // Replace loading spinner by caution content message
            media.previousElementSibling.classList.remove('uk-spinner');
            media.previousElementSibling.removeAttribute('uk-spinner');
            media.previousElementSibling.innerHTML = `<span uk-icon="warning"></span>
                                                  <span class="uk-text-small">&nbsp;` + singleSliderElement.getAttribute('data-media-error') + `</span>`;
            // Hide modal button loading spinner
            let mediaIndex = /(\d+)$/gi.exec(media.getAttribute('id'))[1],
                modalButton = document.querySelector('#st-modal-button-' + mediaIndex);
            if (modalButton.previousElementSibling.classList.contains('st-modal-button-spinner')) {
                modalButton.previousElementSibling.classList.add('uk-hidden');
            }
        };

        // ------------------------------------------------------------------------------------------------------------

        // Define image or iframe before loading behavior
        const beforeMediaLoaded = media => {
            media.style.opacity = '0';
            media.classList.remove('uk-invisible');
        };

        // ------------------------------------------------------------------------------------------------------------

        // Define image or iframe after loading behavior
        const afterMediaLoaded = media => {
            // Hide spinner and make image or iframe visible
            if (media.previousElementSibling.classList.contains('st-media-spinner')) {
                media.previousElementSibling.classList.add('uk-hidden');
                media.classList.remove('uk-invisible');
                // Show media with fade in effect
                media.style.transition = 'opacity 1s ease-in-out';
                media.style.opacity = '1';
            }
            // Show modal button
            let mediaIndex = /(\d+)$/gi.exec(media.getAttribute('id'))[1],
                modalButton = document.querySelector('#st-modal-button-' + mediaIndex);
            if (modalButton.previousElementSibling.classList.contains('st-modal-button-spinner')) {
                modalButton.previousElementSibling.classList.add('uk-hidden');
            }
            modalButton.classList.remove('uk-disabled');
            modalButton.classList.remove('uk-invisible');
            // Modal: call iframe to expand behavior
            mediaModal(media);
        };

        // ------------------------------------------------------------------------------------------------------------

        // Stop any current played video when a new video starts
        const stopAnyOtherVideos = (currentPlayers, newStartedPlayer = null) => {
            // Stop current video only without new activated player
            if (newStartedPlayer === null) {
                newStartedPlayer = {};
                newStartedPlayer.isPlaying = false;
                newStartedPlayer.videoID = '';
                newStartedPlayer.player = {};
            }
            // Stop video which is currently playing
            if (currentPlayers.youtube.isPlaying === true &&
                currentPlayers.youtube.videoID !== newStartedPlayer.videoID) {
                currentPlayers.youtube.player.stopVideo();
                currentPlayers.youtube.isPlaying = false;
            }
            if (currentPlayers.vimeo.isPlaying === true &&
                currentPlayers.vimeo.videoID !== newStartedPlayer.videoID) {
                currentPlayers.vimeo.player.unload();
                currentPlayers.vimeo.isPlaying = false;
            }
            if (currentPlayers.dailymotion.isPlaying === true &&
                currentPlayers.dailymotion.videoID !== newStartedPlayer.videoID) {
                currentPlayers.dailymotion.player.togglePlay();
                currentPlayers.dailymotion.player.seek(0);
                currentPlayers.dailymotion.isPlaying = false;
            }
        };

        // ------------------------------------------------------------------------------------------------------------

        // Load APIs asynchronously
        // https://stackoverflow.com/questions/7718935/load-scripts-asynchronously
        const loadAPI = (src, callback, args) => {
            // args must be a array containing a unique object!
            if (typeof args[0] !== 'object' || args.length > 1) {
                return;
            }
            const htmlCollection = document.getElementsByTagName('script');
            let isScript = false;
            for (let i = 0; i < htmlCollection.length; i ++) {
                if (htmlCollection[i].src === src) {
                    isScript = true;
                    break;
                }
            }
            if (isScript === false) {
                const script = document.createElement('script');
                let tag = null;
                script.type = 'text/javascript';
                script.async = true;
                script.src = src;
                tag = document.getElementsByTagName('script')[0];
                tag.parentNode.insertBefore(script, tag);
                script.onload = script.onreadystatechange = () => {
                    if (!script.readyState || script.readyState === 'complete') {
                        args[0].script = true;
                        callback.apply(null, args);
                    }
                };
                script.onerror = () => {
                    args[0].script = false;
                    callback.apply(null, args);
                };
            }
        };

        // ------------------------------------------------------------------------------------------------------------

        // Show/hide medias with toggle button only for mobile devices
        const mediasToggleButton = document.getElementById('st-single-toggle-media'),
            mediasToggleButtonLoader = mediasToggleButton.querySelector('.single-toggle-media-spinner'),
            mediasSliderContainer = document.getElementById('st-single-slider-container'),
            httpScheme = window.location.origin.split('/').slice(0, 3)[0];
        let transitionEvent = whichTransitionEvent();

        // ------------------------------------------------------------------------------------------------------------

        // Click event on medias toggle button (available on small screen only: max viewport width == 639px)
        mediasToggleButton.addEventListener("click", () => {
            // Already on inactive state.
            if (!mediasSliderContainer.classList.contains('st-active')) {
                mediasSliderContainer.classList.add('st-active');
                // Transition to visible state for new active state
                mediasSliderContainer.style.opacity = '1';
                // Use height to enable fade effect (display is not adapted to have time to see fade in transition)
                mediasSliderContainer.style.height = 'auto';
                // Update button text when slider is visible.
                mediasToggleButton.querySelector('.st-button-text').textContent = mediasToggleButton.getAttribute('data-closed-text');
                mediasToggleButton.querySelector('[uk-icon]').setAttribute('uk-icon', 'minus-circle');
            // Already on active state.
            } else {
                if (mediasSliderContainer.classList.contains('uk-visible@s')) {
                    mediasSliderContainer.classList.remove('uk-visible@s');
                }
                mediasSliderContainer.classList.remove('st-active');
                // Transition to hidden state for new inactive state
                mediasSliderContainer.style.opacity = '0';
                // Update text button when slider is hidden.
                mediasToggleButton.querySelector('.st-button-text').textContent = mediasToggleButton.getAttribute('data-opened-text');
                mediasToggleButton.querySelector('[uk-icon]').setAttribute('uk-icon', 'plus-circle');
             }
        });

        // ------------------------------------------------------------------------------------------------------------

        // Initialize elements
        // Make medias toggle button appear and enable it
        mediasToggleButtonLoader.classList.add('uk-hidden');
        mediasToggleButton.classList.remove('uk-disabled');
        // Initialize opacity and transition on slider
        mediasSliderContainer.style.opacity = '0';
        mediasSliderContainer.style.transition = 'opacity 0.5s ease-in-out';
        if (window.matchMedia('(min-width: 640px)').matches) { // tablet and desktop
            // Maintain visibility if not on small screen
            mediasSliderContainer.style.opacity = '1';
        } else {
            // Remove special class (hide slider on small screen and put on html tag by default)
            mediasSliderContainer.classList.remove('uk-visible@s');
            // Initialize height to enable visibility on opacity transition effect (don't use display which is not adapted.)
            mediasSliderContainer.style.height = '0';
        }

        // ------------------------------------------------------------------------------------------------------------

        // Transition end event to improve fade out effect and make slider disappear at the end
        transitionEvent && mediasSliderContainer.addEventListener("transitionend", () => {
            // Hide with fade out
            if (!mediasSliderContainer.classList.contains('st-active') && !mediasSliderContainer.classList.contains('uk-visible@s')) {
                // Use height to enable fade effect (display is not adapted to have time to see fade out transition)
                mediasSliderContainer.style.opacity = '0';
                mediasSliderContainer.style.height = '0';
            // Show with fade in
            } else {
                mediasSliderContainer.style.opacity = '1';
                mediasSliderContainer.style.height = 'auto';
            }
        });

        // ------------------------------------------------------------------------------------------------------------

        // Resize event: update special class to show or hide slider
        window.addEventListener('resize', () => {
            if (window.matchMedia('(min-width: 640px)').matches) { // tablet and desktop
                // Reactivate visibility on tablet and desktop if special class is removed, so enable visibility on these viewports.
                if (!mediasSliderContainer.classList.contains('uk-visible@s')) {
                    mediasSliderContainer.classList.add('uk-visible@s');
                }
                mediasSliderContainer.style.opacity = '1';
                mediasSliderContainer.style.height = 'auto';
            } else {
                // Active state is detected on small screen, so enable visibility by removing special class on this viewport.
                if (mediasSliderContainer.classList.contains('uk-visible@s')) {
                    mediasSliderContainer.classList.remove('uk-visible@s');
                }
                // Show slider
                if (mediasSliderContainer.classList.contains('st-active')) {
                    mediasSliderContainer.style.opacity = '1';
                    mediasSliderContainer.style.height = 'auto';
                // Hide slider
                } else {
                    mediasSliderContainer.style.opacity = '0';
                    mediasSliderContainer.style.height = '0';
                }
            }
        });

        // ------------------------------------------------------------------------------------------------------------
        // ------------------------------------------------------------------------------------------------------------

        // Call image loading rendering behavior (both thumbnail and modal image)
        let img = null;
        const images = singleSliderElement.querySelectorAll('.st-card-image');
        for (let i = 0; i < images.length; i ++) {
            // Prepare fade in effect for image
            beforeMediaLoaded(images[i]);
            img = new Image;
            // thumbnail is in background with data-src attribute (no img tag contrary to modal image)
            img.src = images[i].getAttribute('data-src');
            // Call image loading rendering behavior:
            img.addEventListener("load", () => {
                afterMediaLoaded(images[i]);
            });
            // Call image loading error behavior:
            img.addEventListener("error", () => {
                whenMediaError(images[i]);
            });
        }

        // ------------------------------------------------------------------------------------------------------------

        // Convert NodeLists to arrays
        const youtubeIframes = Array.from(singleSliderElement.querySelectorAll('.st-card-iframe.st-yt-iframe')),
            vimeoIframes = Array.from(singleSliderElement.querySelectorAll('.st-card-iframe.st-vm-iframe')),
            dailymotionIframes = Array.from(singleSliderElement.querySelectorAll('.st-card-iframe.st-dm-iframe'));
        // Arrays to store multiple players
        let youtubePlayers = [], vimeoPlayers = [], dailymotionPlayers = [];
        // Objects to store current players which are playing a video or not
        let youtubeCurrentPlayer = {isPlaying: false, videoID: null, player: null},
            vimeoCurrentPlayer = {isPlaying: false, videoID: null, player: null},
            dailymotionCurrentPlayer = {isPlaying: false, videoID: null, player: null},
            currentPlayers = {
                youtube: youtubeCurrentPlayer,
                vimeo: vimeoCurrentPlayer,
                dailymotion: dailymotionCurrentPlayer
            };

        // ------------------------------------------------------------------------------------------------------------

        // Youtube
        // iframe JavaScript API: https://developers.google.com/youtube/iframe_api_reference
        if (youtubeIframes.length > 0) {
            let ytTimeOut = [];
            loadAPI('https://www.youtube.com/iframe_api', (args) => {
                // Script loading error
                let isScriptLoaded = args.script; // 'args' argument is an object.
                for (let i = 0; i < youtubeIframes.length; i ++) {
                    // Unescape path value from HTML attribute
                    let iframeSrc = stringHandler.htmlAttributeOnString.unescape(youtubeIframes[i].getAttribute('src'));
                    youtubeIframes[i].setAttribute('src', iframeSrc);
                    // API error
                    if (isScriptLoaded === false) {
                        whenMediaError(youtubeIframes[i]);
                        if (i === youtubeIframes.length - 1) {
                            return;
                        }
                    // API script is loaded.
                    } else {
                        // Prepare fade in effect for iframe
                        beforeMediaLoaded(youtubeIframes[i]);
                        // Call iframe loading rendering behavior:
                        ytTimeOut[i] = setTimeout(() => {
                            let proxyURL = videoProxyPath + '/' + iframeSrc;
                            checkLoadingCORSRequest('GET', proxyURL, afterMediaLoaded, whenMediaError, [youtubeIframes[i]], ytTimeOut[i]);
                        }, 10);
                        // API ready
                        window.onYouTubeIframeAPIReady = () => {
                            // Video checked custom event success with proxy ajax request
                            youtubeIframes[i].addEventListener('checkedVideoSuccess', () => {
                                    let videoID = /embed\/(.+)\?/gi.exec(iframeSrc)[1];
                                    youtubePlayers.push({
                                        type: 'yt',
                                        videoID: videoID,
                                        player: new YT.Player(youtubeIframes[i].getAttribute('id'), {
                                            videoId: videoID
                                        })
                                    });
                                    // Stop any other videos when playing and update Youtube current player
                                    youtubePlayers[i].player.addEventListener('onStateChange', (event) => {
                                        if (event.data === YT.PlayerState.PLAYING) {
                                            stopAnyOtherVideos(currentPlayers, youtubePlayers[i]);
                                            youtubeCurrentPlayer.isPlaying = true;
                                            youtubeCurrentPlayer.videoID = youtubePlayers[i].videoID;
                                            youtubeCurrentPlayer.player = youtubePlayers[i].player;
                                        }
                                    });
                                    // Player error
                                    youtubePlayers[i].player.addEventListener('onError', () => {
                                        whenMediaError(youtubeIframes[i]);
                                    });
                            }, false);
                        };
                    }
                }
            }, [{/*add object properties if necessary*/}]); // args must be a array containing a unique object!
        }

        // ------------------------------------------------------------------------------------------------------------

        // Vimeo
        // iframe JavaScript API: https://developer.vimeo.com/player/sdk
        if (vimeoIframes.length > 0) {
            let vmTimeOut = [];
            loadAPI('https://player.vimeo.com/api/player.js', (args) => {
                // Script loading error
                let isScriptLoaded = args.script; // 'args' argument is an object.
                for (let i = 0; i < vimeoIframes.length; i ++) {
                    // Unescape path value from HTML attribute
                    let iframeSrc = stringHandler.htmlAttributeOnString.unescape(vimeoIframes[i].getAttribute('src'));
                    vimeoIframes[i].setAttribute('src', iframeSrc);
                    // API error
                    if (isScriptLoaded === false) {
                        whenMediaError(vimeoIframes[i]);
                        if (i === vimeoIframes.length - 1) {
                            return;
                        }
                    // API script is loaded.
                    } else {
                        // Prepare fade in effect for iframe
                        beforeMediaLoaded(vimeoIframes[i]);
                        // Call iframe loading rendering behavior:
                        vmTimeOut[i] = setTimeout(() => {
                            let proxyURL = videoProxyPath + '/' + iframeSrc;
                            checkLoadingCORSRequest('GET', proxyURL, afterMediaLoaded, whenMediaError, [vimeoIframes[i]], vmTimeOut[i]);
                        }, 10);
                        // API ready
                        window.dmAsyncInit = () => {
                            // Video checked custom event success with proxy ajax request
                            vimeoIframes[i].addEventListener('checkedVideoSuccess', () => {
                                let videoID = /video\/(.+)\?/gi.exec(iframeSrc)[1];
                                vimeoPlayers.push({
                                    type: 'vm',
                                    videoID: videoID,
                                    player: new Vimeo.Player(vimeoIframes[i], {
                                        id: videoID
                                    })
                                });
                                // Player with index "i" is ready.
                                vimeoPlayers[i].player.ready().then(() => {
                                    // Playing event: stop any other videos when playing and update Vimeo current player
                                    vimeoPlayers[i].player.on('play', () => {
                                        stopAnyOtherVideos(currentPlayers, vimeoPlayers[i]);
                                        vimeoCurrentPlayer.isPlaying = true;
                                        vimeoCurrentPlayer.videoID = vimeoPlayers[i].videoID;
                                        vimeoCurrentPlayer.player = vimeoPlayers[i].player;
                                    });
                                    // Player error
                                    vimeoPlayers[i].player.on('error', () => {
                                        whenMediaError(vimeoIframes[i]);
                                    });
                                });
                            });
                        };
                    }
                }
            }, [{/*add object properties if necessary*/}]); // args must be a array containing a unique object!
        }

        // ------------------------------------------------------------------------------------------------------------

        // Dailymotion
        // iframe JavaScript API: https://developer.dailymotion.com/player
        if (dailymotionIframes.length > 0) {
            let dmTimeOut = [];
            loadAPI('https://api.dmcdn.net/all.js', (args) => {
                // Script loading error
                let isScriptLoaded = args.script; // 'args' argument is an object.
                for (let i = 0; i < dailymotionIframes.length; i ++) {
                    // Unescape path value from HTML attribute
                    let iframeSrc = stringHandler.htmlAttributeOnString.unescape(dailymotionIframes[i].getAttribute('src'));
                    dailymotionIframes[i].setAttribute('src', iframeSrc);
                    // API script error.
                    if (isScriptLoaded === false) {
                        whenMediaError(dailymotionIframes[i]);
                        if (i === dailymotionIframes.length - 1) {
                            return;
                        }
                    // API script is loaded.
                    } else {
                        // Prepare fade in effect for iframe
                        beforeMediaLoaded(dailymotionIframes[i]);
                        // Call iframe loading rendering behavior:
                        dmTimeOut[i] = setTimeout(() => {
                            let proxyURL = videoProxyPath + '/' + iframeSrc;
                            checkLoadingCORSRequest('GET', proxyURL, afterMediaLoaded, whenMediaError, [dailymotionIframes[i]], dmTimeOut[i]);
                        }, 10);
                        // Video checked custom event success with proxy ajax request
                        dailymotionIframes[i].addEventListener('checkedVideoSuccess', () => {
                            let videoID = /video\/(.+)\?/gi.exec(iframeSrc)[1];
                            dailymotionPlayers.push({
                                type: 'dm',
                                videoID: videoID,
                                player: DM.player(dailymotionIframes[i], {
                                    video: videoID
                                })
                            });
                            // API must be ready to add other player control event listeners
                            dailymotionPlayers[i].player.addEventListener('apiready', () => {
                                // Playing event: stop any other videos when playing and update Dailymotion current player
                                dailymotionPlayers[i].player.addEventListener('playing', () => {
                                    stopAnyOtherVideos(currentPlayers, dailymotionPlayers[i]);
                                    dailymotionCurrentPlayer.isPlaying = true;
                                    dailymotionCurrentPlayer.videoID = dailymotionPlayers[i].videoID;
                                    dailymotionCurrentPlayer.player = dailymotionPlayers[i].player;
                                });
                                // Player error
                                dailymotionPlayers[i].player.addEventListener('error', () => {
                                    whenMediaError(dailymotionPlayers[i].player);
                                });
                            });
                        });
                    }
                }
            }, [{/*add object properties if necessary*/}]); // args must be a array containing a unique object!
        }
    }
}
