import createNotification from './all/create-notification';
import deleteTrick from './trick/removal/delete-trick';
import imageListLoader from './all/image-list-loader';
import request from './all/ajax-request';
import URIHelper from './all/encode-decode-uri';

export default () => {
    // ------------------- Home page -------------------
    let element = document.getElementById('st-trick-list');
    if (element) { // element !== undefined && element !== null
        // No trick was found! So this will stop all the script.
        if (0 === element.querySelectorAll('.uk-card').length) {
            return;
        }
        // Get defined parameters from server side
        let loadingMode = element.querySelector('.uk-grid').getAttribute('data-loading-mode'),
            definedLimit = parseInt(element.querySelector('.uk-grid').getAttribute('data-limit'), 10),
            nodes = element.querySelectorAll('.uk-card'),
            countAll = parseInt(element.querySelector('.uk-grid').getAttribute('data-count'), 10),
            last = nodes[nodes.length - 1],
            lastOffset = parseInt(last.parentElement.getAttribute('data-offset'), 10),
            limit = parseInt(element.querySelector('.uk-grid').getAttribute('data-limit'), 10),
            requestPath = element.querySelector('.uk-grid').getAttribute('data-path'),
            technicalError = element.querySelector('.uk-grid').getAttribute('data-technical-error'),
            listEnded = element.querySelector('.uk-grid').getAttribute('data-ended'),
            ajaxPrevented = false;
        const loadMoreButton = document.getElementById('st-home-load-more');
        // Trick list image loader behavior when page is loaded.
        nodes.forEach(card => {
            imageListLoader(document.getElementById('st-card-image-' + card.parentElement.getAttribute('data-offset')));

            // ------------------------------------------------------------------------------------------------------------

            // Manage trick deletion
            let trickRemovalLink = card.querySelector('.st-delete-trick');
            if (trickRemovalLink) {
                deleteTrick(card.querySelector('.st-delete-trick'));
            }
        });
        // Prevent AJAX request from being called if all cards are already loaded.
        if ((loadingMode === 'DESC' && lastOffset === 0) ||
            (loadingMode === 'ASC' && lastOffset === countAll - 1)) {
            ajaxPrevented = true;
        } else {
            // Show load more button which is hidden by default
            loadMoreButton.classList.remove('uk-hidden');
        }
        // Trick list anchor "Let's go!" mouse behaviors for slideshow:
        const toHomeListButton = document.getElementById('st-home-list-anchor');
        toHomeListButton.addEventListener('mouseover', () => {
            if (toHomeListButton) {
                // Add class which hides slideshow nav
                document.querySelector('.uk-slideshow .uk-slidenav-previous').classList.add('uk-hidden');
                document.querySelector('.uk-slideshow .uk-slidenav-next').classList.add('uk-hidden');
            }
        });
        toHomeListButton.addEventListener('mouseout', () => {
            if (toHomeListButton) {
                // Remove class which hides slideshow nav
                document.querySelector('.uk-slideshow .uk-slidenav-previous').classList.remove('uk-hidden');
                document.querySelector('.uk-slideshow .uk-slidenav-next').classList.remove('uk-hidden');
            }
        });
        // To top: show button if at least "limit + 1" tricks are shown.
        const toTopButton = document.getElementById('st-home-top');
        const toTop = () => {
            nodes = element.querySelectorAll('.uk-card');
            if (toTopButton) {
                if (nodes.length <= 10) {
                    // Hide
                    toTopButton.classList.add('uk-hidden');
                    // Remove scrollspy behavior
                    toTopButton.removeAttribute('uk-scrollspy');
                } else {
                    // Show
                    toTopButton.classList.remove('uk-hidden');
                    // Add scrollspy behavior
                    toTopButton.setAttribute('uk-scrollspy', 'cls:uk-animation-slide-right-medium; delay: 500; repeat: true');
                }
            }
        };
        toTop();
        // Load more
        if (loadMoreButton) {
            loadMoreButton.addEventListener('click', () => {
                nodes = element.querySelectorAll('.uk-card');
                last = nodes[nodes.length - 1];
                // Get offset from last card shown
                let endOffset = parseInt(last.parentElement.getAttribute('data-offset'), 10);
                let minOffset = 0;
                let maxOffset = countAll - 1;
                // check loading mode to define startOffset
                let startOffset = loadingMode === 'DESC' ? endOffset - limit : endOffset + 1;
                // Check a valid startOffset for both modes
                let validOffset = (startOffset >= -limit) && (startOffset < maxOffset + limit + 1);
                // Check a valid limit for both modes
                let validLimit = (limit >= 1);  // && (limit < maxOffset + 1); will restrict limit max value with possible issue
                // Check correctly defined order
                let validOrder = (loadingMode === 'DESC') || (loadingMode === 'ASC');
                // Error: check if startOffset or limit values are wrong: so reset list to default parameters for descending order
                // Descending order is also initial state if loading mode is wrong.
                if (!validOrder || (loadingMode === 'DESC' && (!validOffset || !validLimit))) {
                    startOffset = maxOffset + 1 - limit;
                // Error: check if offset or limit values are wrong: so reset list to default parameters for ascending order
                } else if (loadingMode === 'ASC' && (!validOffset || !validLimit)) {
                    startOffset = 0;
                // Particular case: check if calculated startOffset is not under minOffset: lowest startOffset must be 0 for descending order
                } else if (loadingMode === 'DESC' && startOffset < minOffset) {
                    startOffset = minOffset;
                    // A modulo can be used instead of enOffset, but for client side, the new limit value is already available with it:
                    // limit = (0 == countAll % limit) ? 1 : countAll % limit;
                    limit = endOffset;
                // Particular case: check if calculated startOffset + limit is not over maxOffset for ascending order
                } else if (loadingMode === 'ASC' && startOffset + limit > maxOffset) {
                    limit = maxOffset + 1 - startOffset;
                }

                // ------------------------------------------------------------------------------------------------------------

                // AJAX request
                if (!ajaxPrevented) {
                    // Prepare path to AJAX action
                    const pathHandler = URIHelper();
                    requestPath = pathHandler.uriOnString.encode(requestPath);
                    startOffset = pathHandler.uriOnString.encodeParamWithRFC3986(startOffset.toString());
                    limit = pathHandler.uriOnString.encodeParamWithRFC3986(limit.toString());
                    const obj = {
                        headers: {'X-Requested-With': 'XMLHttpRequest'},
                        url: limit !== definedLimit ? (requestPath + '/' + startOffset + '/' + limit) : (requestPath + '/' + startOffset),
                        async: true,
                        withCredentials: false,
                        responseType: 'text', // 'document' does not work in this case!
                        onLoadStartFunction: () => {
                            // Disable button
                            document.querySelector('#st-home-load-more').classList.add('uk-disabled');
                            // Show loading spinner
                            document.querySelector('#st-home-load-more .st-home-spinner').classList.remove('uk-hidden');
                        },
                    };
                    request(obj).then(response => {
                        let tmp = document.createElement("div");
                        tmp.innerHTML = response.toString();
                        let cards = tmp.querySelectorAll('.st-card-container');
                        let listError = false;
                        let endReached = false;
                        cards.forEach((card, index) => {
                            // Manage error to display reinitialized default list.
                            if (index === 0 && card.getAttribute('data-error') !== null) {
                                document.querySelector('#st-trick-list .uk-grid').innerHTML = '';
                                listError = true;
                            }
                            // Add appearance effect on trick list container
                            element.setAttribute(
                                'uk-scrollspy',
                                'target: > .uk-card; cls: uk-animation-fade; delay: 1000'
                            );
                            // Hide card element before feeding and show
                            card.classList.add('uk-hidden');
                            // Show card
                            let to = setTimeout(() => {
                                card.classList.remove('uk-hidden');
                                document.querySelector('#st-trick-list .uk-grid').insertAdjacentHTML('beforeend', card.outerHTML);
                                // Trick list image loader behavior after insertion
                                imageListLoader(document.getElementById('st-card-image-' + card.getAttribute('data-offset')));
                                // Last card
                                if (index === cards.length - 1) {
                                    // Hide loading spinner
                                    document.querySelector('#st-home-load-more .st-home-spinner').classList.add('uk-hidden');
                                    // Check end of list
                                    if (loadingMode === 'DESC' && parseInt(card.getAttribute('data-offset')) === 0) {
                                        // Hide Load more button
                                        document.querySelector('#st-home-load-more').classList.add('uk-hidden');
                                        endReached = true;
                                    } else if (loadingMode === 'ASC' && parseInt(card.getAttribute('data-offset')) === maxOffset) {
                                        // Hide Load more button
                                        document.querySelector('#st-home-load-more').classList.add('uk-hidden');
                                        endReached = true;
                                    }
                                    // Max offset is reached: show notification
                                    if (!listError && endReached) {
                                         createNotification(
                                            listEnded,
                                            null,
                                            true,
                                            'info',
                                            'info',
                                            5000
                                         );
                                    }
                                    // List error is triggered: show notification
                                    if (listError) {
                                        createNotification(
                                            cards[0].getAttribute('data-error'),
                                            null,
                                            true,
                                            'error',
                                            'warning',
                                            5000
                                        );
                                    }
                                    // Enable button
                                    document.querySelector('#st-home-load-more').classList.remove('uk-disabled');
                                    toTop();

                                    // ------------------------------------------------------------------------------------------------------------

                                    // Manage trick deletion also here to update removal links list after AJAX request success
                                    let trickDeletionLink = card.querySelector('.st-delete-trick');
                                    if (trickDeletionLink !== null) {
                                        deleteTrick(trickDeletionLink);
                                    }
                                }
                                clearTimeout(to);
                            }, (index + 1) * 250);
                        });
                    })
                    // It is important to chain to prevent ajax request from being called twice!
                    .catch(xhr => {
                        // Hide spinner
                        document.querySelector('#st-home-load-more').classList.add('uk-hidden');
                        // Technical error: show notification
                        let error;
                        error = (xhr.status !== undefined && xhr.statusText !== undefined) ? `error: ${xhr.status} - ${xhr.statusText}` : '';
                        // Aborted request
                        error = 0 !== xhr.status ? error : '';
                        createNotification(
                            technicalError + error,
                            null,
                            true,
                            'error',
                            'warning',
                            0
                        );
                    });
                }
            });
        }
    }
};
