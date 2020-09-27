import URIHelper from '../all/encode-decode-uri';
import request from '../all/ajax-request';
import createNotification from '../all/create-notification';
import deleteComment from './removal/delete-comment';
import removeCommentBox from './removal/remove-comment-box';

export default () => {
    // Click event listener adder on comment view replies link
    const addEventListenerOnCommentViewRepliesLink = commentViewRepliesLink => {
        // Switch text and arrows on link: View <> Close n reply(ies)
        const clickViewCommentRepliesButtonHandler = () => {
            // Get necessary elements to switch texts
            let linkContent = commentViewRepliesLink.querySelector('.st-switch-replies-arrow');
            let linkContentText = commentViewRepliesLink.querySelector('.st-switch-replies-text');
            // get "View" and "Close" texts with "data-" attributes (translations ready)
            let viewText = linkContentText.getAttribute('data-view-text');
            let closeText = linkContentText.getAttribute('data-close-text');
            // Prepare dynamic patterns with regex objects to switch text
            let viewTextRegex = new RegExp(viewText + ' (\\d+)', 'i');
            let closeTextRegex = new RegExp(closeText + ' (\\d+)', 'i');
            // Get arrow icon UIkit parameters
            let arrowIconAttribute = linkContent.getAttribute('uk-icon');
            // Switch displayed link text between "opened" and "closed" state
            switch (true) {
                case viewTextRegex.test(linkContentText.textContent):
                    linkContent.setAttribute(
                        'uk-icon',
                        arrowIconAttribute.replace('arrow-down', 'arrow-up')
                    );
                    linkContentText.textContent = linkContentText.textContent.replace(
                        viewTextRegex,
                        closeText + ' $1'
                    );
                    break;
                case closeTextRegex.test(linkContentText.textContent):
                    linkContent.setAttribute(
                        'uk-icon',
                        arrowIconAttribute.replace('arrow-up', 'arrow-down')
                    );
                    linkContentText.textContent = linkContentText.textContent.replace(
                        closeTextRegex,
                        viewText + ' $1'
                    );
                    break;
            }
        };
        commentViewRepliesLink.addEventListener('click', clickViewCommentRepliesButtonHandler);
    };

    // ------------------------------------------------------------------------------------------------------------

    // Click event listener adder on comment replies link
    const addEventListenerOnCommentReplyButton = (parentCommentSelect, commentReplyLink) => {
        // Manage comment reply
        const clickReplyCommentButtonHandler = event => {
            event.preventDefault();
            let commentId = commentReplyLink.getAttribute('id');
            let matches = commentId.match(/^st-reply-comment-(\d+)$/i);
            let commentKey = matches[1];
            // CAUTION! Comments in select are listed in ascending order in select.
            // contrary to comment list to show.
            parentCommentSelect.options[commentKey].selected = true;
            // Position scroll on comment creation form with smooth effect
            const anchor = commentReplyLink.getAttribute('href');
            const offsetTop = document.querySelector(anchor).offsetTop;
            window.scroll({
                top: offsetTop,
                behavior: "smooth"
            });
        };
        commentReplyLink.addEventListener('click', clickReplyCommentButtonHandler);
    };

    // ------------------------------------------------------------------------------------------------------------

    // "Click" event on comment box removal button (link)
    const addEventListenerOnReplyCommentRemovalButton = commentDeletionLink => {
        // Prepare element to which window will scroll after deletion
        let referenceElementToScroll = trickCommentList;
        // Manage comments removal
        const clickRemoveCommentButtonHandler = event => {
            // Prevent link anchor to scroll up the window
            event.preventDefault();
            // Get current comment removal button which is clicked
            let removeCommentButtonElement = event.currentTarget; // item
            // Delete existing comment on server with AJAX if necessary
            // If deletion failed, comment box will not be removed.
            if (removeCommentButtonElement.hasAttribute('data-action')) {
                // Comment box element removal will be called internally if it is ok!
                deleteComment(
                    removeCommentButtonElement,
                    referenceElementToScroll, // reference element to target with scroll
                    0,
                    removeCommentBox,
                    [removeCommentButtonElement, null]
                );
            }
        };
        commentDeletionLink.addEventListener('click', clickRemoveCommentButtonHandler);
    };

    // ------------------------------------------------------------------------------------------------------------

    // Manage trick comment list
    const trickCommentList = document.getElementById('st-trick-comment-list');
    // Load more AJAX loading
    if (trickCommentList !== null) {
        // Manage comment click to view possible reply(ies) when page is loaded!
        let commentViewRepliesLoadedLinks = trickCommentList.querySelectorAll('.st-view-replies');
        if (commentViewRepliesLoadedLinks !== null) {
            commentViewRepliesLoadedLinks.forEach(commentViewRepliesLink => {
                // Call particular function
                addEventListenerOnCommentViewRepliesLink(
                    commentViewRepliesLink
                );
            });
        }

        // ------------------------------------------------------------------------------------------------------------

        // Manage comment click in list to create a smooth scroll to comment form when page is loaded
        // in order ot reply, by updating parentComment select drop down!
        const createCommentForm = document.getElementById('st-create-comment-form');
        let commentReplyLoadedLinks = trickCommentList.querySelectorAll('.st-reply-comment');
        if (createCommentForm != null && commentReplyLoadedLinks != null) {
            const parentCommentSelect = createCommentForm.querySelector('.uk-select');
            commentReplyLoadedLinks.forEach(commentReplyLink => {
                addEventListenerOnCommentReplyButton(parentCommentSelect, commentReplyLink);
            });
        }

        // ------------------------------------------------------------------------------------------------------------

        // Manage comment removal click to delete it!
        let commentDeletionLoadedLinks = trickCommentList.querySelectorAll('.st-delete-comment');
        if (commentDeletionLoadedLinks !== null) {
            commentDeletionLoadedLinks.forEach(commentDeletionLink => {
                addEventListenerOnReplyCommentRemovalButton(commentDeletionLink);
            });
        }

        // ------------------------------------------------------------------------------------------------------------

        // Get defined limit dynamically (comment number per loading)
        let limit = parseInt(trickCommentList.getAttribute('data-limit'), 10);
        // Get first level comments total count to simplify loading due to children comments
        //let firstLevelComments = trickCommentList.querySelectorAll('.uk-comment.st-first-level-comment');
        //if (limit < firstLevelComments.length) {
        const loadMoreButton = document.getElementById('st-single-load-more');
        // Ajax request
        if (loadMoreButton) {
            // Get all comment boxes as nodes
            let nodes = trickCommentList.querySelectorAll('.uk-comment.st-first-level-comment');
            // Show load more button which is hidden by default
            loadMoreButton.classList.remove('uk-hidden');
            loadMoreButton.addEventListener('click', () => {
                nodes = trickCommentList.querySelectorAll('.uk-comment.st-first-level-comment');
                let lastOffset = nodes.length - 1;
                let technicalError = trickCommentList.getAttribute('data-technical-error');
                let listEnded = trickCommentList.getAttribute('data-ended');
                // Prepare path to AJAX action
                const pathHandler = URIHelper();
                let requestPath = trickCommentList.getAttribute('data-path');
                requestPath = pathHandler.uriOnString.encode(requestPath);
                let startOffset = pathHandler.uriOnString.encodeParamWithRFC3986((lastOffset + 1).toString());
                limit = pathHandler.uriOnString.encodeParamWithRFC3986(limit.toString());
                const obj = {
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    url: requestPath + '/' + startOffset + '/' + limit,
                    async: true,
                    withCredentials: false,
                    responseType: 'text', // 'document' does not work in this case!
                    onLoadStartFunction: () => {
                        // Disable button
                        document.querySelector('#st-single-load-more').classList.add('uk-disabled');
                        // Show loading spinner
                        document.querySelector('#st-single-load-more .st-single-spinner').classList.remove('uk-hidden');
                    },
                };
                request(obj).then(response => {
                    let tmp = document.createElement("div");
                    tmp.innerHTML = response.toString();
                    let cards = tmp.querySelectorAll('.st-first-level-comment');
                    let listError = false;
                    let endReached = false;
                    cards.forEach((card, index) => {
                        // Manage error to display reinitialized default list.
                        if (index === 0 && card.getAttribute('data-error') !== null) {
                            trickCommentList.innerHTML = '';
                            listError = true;
                        }
                        // Add appearance effect on trick comment list container
                        trickCommentList.setAttribute(
                            'uk-scrollspy',
                            'target: > .uk-comment; cls: uk-animation-fade; delay: 1000'
                        );
                        // Hide card element before feeding and show
                        card.classList.add('uk-hidden');
                        // Show card
                        let to = setTimeout(() => {
                            card.classList.remove('uk-hidden');
                            trickCommentList.insertAdjacentHTML('beforeend', card.outerHTML);
                            // Last card
                            if (index === cards.length - 1) {
                                // Hide loading spinner
                                document.querySelector('#st-single-load-more .st-single-spinner').classList.add('uk-hidden');
                                // Check end of list
                                let countAll = parseInt(cards[0].getAttribute('data-count'), 10);
                                let trickComments = trickCommentList.querySelectorAll('.uk-comment');
                                if (trickComments.length === countAll) {
                                    endReached = true;
                                    // Hide Load more button
                                    document.querySelector('#st-single-load-more').classList.add('uk-hidden');
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
                                document.querySelector('#st-single-load-more').classList.remove('uk-disabled');

                                // ------------------------------------------------------------------------------------------------------------

                                // Add click event listeners on cards comment view replies link(s) due to dynamic loading
                                let commentViewRepliesLinks = trickCommentList.querySelectorAll('.st-view-replies');
                                if (commentViewRepliesLinks != null) {
                                    commentViewRepliesLinks.forEach(commentViewRepliesLink => {
                                        // Avoid issue with already attached events on page load
                                        let isNewId = true;
                                        if (commentViewRepliesLoadedLinks !== null) {
                                            let ajaxLoadedLinkId = commentViewRepliesLink.getAttribute('id');
                                            commentViewRepliesLoadedLinks.forEach(commentViewRepliesLoadedLink => {
                                                let loadedId = commentViewRepliesLoadedLink.getAttribute('id');
                                                if (loadedId === ajaxLoadedLinkId) {
                                                    isNewId = false;
                                                }
                                            });
                                        }
                                        if (isNewId) {
                                            addEventListenerOnCommentViewRepliesLink(commentViewRepliesLink);
                                        }
                                    });
                                }

                                // ------------------------------------------------------------------------------------------------------------

                                // Add click event listeners on cards comment replies link(s) due to dynamic loading
                                const createCommentForm = document.getElementById('st-create-comment-form');
                                let commentReplyLinks = trickCommentList.querySelectorAll('.st-reply-comment');
                                if (createCommentForm !== null && commentReplyLinks != null) {
                                    const parentCommentSelect = createCommentForm.querySelector('.uk-select');
                                    commentReplyLinks.forEach(commentReplyLink => {
                                        // Avoid issue with already attached events on page load
                                        let isNewId = true;
                                        if (commentReplyLoadedLinks !== null) {
                                            let ajaxLoadedLinkId = commentReplyLink.getAttribute('id');
                                            commentReplyLoadedLinks.forEach(commentReplyLoadedLink => {
                                                let loadedId = commentReplyLoadedLink.getAttribute('id');
                                                if (loadedId === ajaxLoadedLinkId) {
                                                    isNewId = false;
                                                }
                                            });
                                        }
                                        if (isNewId) {
                                            addEventListenerOnCommentReplyButton(parentCommentSelect, commentReplyLink);
                                        }
                                    });
                                }

                                // ------------------------------------------------------------------------------------------------------------

                                // Add click event listeners on cards comment removal link(s) due to dynamic loading
                                let commentDeletionLinks = trickCommentList.querySelectorAll('.st-delete-comment');
                                if (commentDeletionLinks !== null) {
                                    commentDeletionLinks.forEach(commentDeletionLink => {
                                        // Avoid issue with already attached events on page load
                                        let isNewId = true;
                                        if (commentDeletionLoadedLinks !== null) {
                                            let ajaxLoadedLinkId = commentDeletionLink.getAttribute('id');
                                            commentDeletionLoadedLinks.forEach(commentViewRepliesLoadedLink => {
                                                let loadedId = commentViewRepliesLoadedLink.getAttribute('id');
                                                if (loadedId === ajaxLoadedLinkId) {
                                                    isNewId = false;
                                                }
                                            });
                                        }
                                        if (isNewId) {
                                            addEventListenerOnReplyCommentRemovalButton(commentDeletionLink);
                                        }
                                    });
                                }
                            }
                            clearTimeout(to);
                        }, (index + 1) * 250);
                    });
                })
                // It is important to chain to prevent ajax request from being called twice!
                .catch(xhr => {
                    // Hide spinner
                    document.querySelector('#st-single-load-more').classList.add('uk-hidden');
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
            });
        }
    }
}



