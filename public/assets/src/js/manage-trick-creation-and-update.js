import htmlStringHelper from './all/encode-decode-string';
import cropper from './all/cropper';
import deleteMedia from './medias/delete-media';
import removeImageBox from "./medias/remove-image-box";
import Sortable from 'sortablejs';
export default () => {
    // Resources:
    // substring(): https://developer.mozilla.org/fr/docs/Web/JavaScript/Reference/Objets_globaux/String/substring
    // substr():
    // Text encoding: https://itnext.io/introduction-to-character-text-encoding-in-web-4b315c4244f2
    // Decode/encode html entities: https://ourcodeworld.com/articles/read/188/encode-and-decode-html-entities-using-pure-javascript
    //                              https://stackoverflow.com/questions/1354064/how-to-convert-characters-to-html-entities-using-plain-javascript
    // Decode hexadecimal html entities: https://stackoverflow.com/questions/25607969/how-to-decode-hex-code-of-html-entities-to-text-in-javascript
    // Decode/encode html special chars: https://medium.com/@Charles_Stover/phps-htmlspecialchars-implemented-in-javascript-3da9ac36d481
    //                                   https://www.julesgaston.fr/encoder-decoder-entites-html-entities-javascript/
    // Utils for html entities: https://github.com/fb55/entities
    //                          https://github.com/mathiasbynens/he
    //                          https://snippets.webaware.com.au/snippets/safely-encode-dynamically-built-html-and-javascript/
    // DOM parser to convert string to HTML Node: https://davidwalsh.name/convert-html-stings-dom-nodes
    // DOM modification https://javascript.info/modifying-document
    // Convert html string into markup: https://gomakethings.com/converting-a-string-into-markup-with-vanilla-js/
    // Passing data from twig to JavaScript: https://cruftlesscraft.com/passing-data-from-twig-to-javascript
    // Encode safe html & Javascript: https://snippets.webaware.com.au/snippets/safely-encode-dynamically-built-html-and-javascript/
    // Replace all the occurrences in string: https://www.designcise.com/web/tutorial/how-to-replace-all-occurrences-of-a-word-in-a-javascript-string
    // RegExp if - then - else: https://stackoverflow.com/questions/32852857/javascript-conditional-regular-expression-if-then-else
    // Add event listener on multiple elements (which are the same or not): https://flaviocopes.com/how-to-add-event-listener-multiple-elements-javascript/
    //                                                                      https://www.kirupa.com/html5/handling_events_for_many_elements.htm
    // Event capturing - event bubbling: https://www.kirupa.com/html5/event_capturing_bubbling_javascript.htm
    //                                   https://medium.com/@vsvaibhav2016/event-bubbling-and-event-capturing-in-javascript-6ff38bec30e

    // ------------------------------------------------------------------------------------------------------------

    const formElement = document.getElementById('st-create-trick-form') || document.getElementById('st-update-trick-form');
    if (formElement) {
        const formElementName = formElement.getAttribute('name');
        // Add image box element action
        const addImageButton = document.getElementById('st-image-add-button');
        // Add image box element action
        const addVideoButton = document.getElementById('st-video-add-button');
        // Images Collection container
        const imagesCollectionContainer = document.getElementById('st-images-collection');
        // Videos Collection container
        const videosCollectionContainer = document.getElementById('st-videos-collection');
        // String helper
        const htmlStringHandler = htmlStringHelper();
        // Store a show list rank for collections
        let collectionBoxRank = null;
        // Get the data-prototype attribute which contains image box template
        let imagePrototype = imagesCollectionContainer.getAttribute('data-prototype');
        // Get the data-prototype-name attribute which contains dynamic image index name
        let imagePrototypeName = imagesCollectionContainer.getAttribute('data-prototype-name');
        // Initialize var to get all existing "image to crop" boxes elements later
        let imageToCropBoxElements = null;
        // Initialize var to get all existing "video infos" boxes elements later
        let videoInfosBoxElements = null;
        // Get the data-prototype attribute which contains video box template
        let videoPrototype = videosCollectionContainer.getAttribute('data-prototype');
        // Get the data-prototype-name attribute which contains dynamic video index name
        let videoPrototypeName = videosCollectionContainer.getAttribute('data-prototype-name');

        // ------------------------------------------------------------------------------------------------------------

        // Implement logic for images collection

        imagesCollectionContainer.addEventListener('click', event => {
            // Initialize event target element for bubbling up
            let targetElement = event.target;
            // Prepare empty object to store future crop JSON data
            let cropParams = {};
            let imageBox,
                fileInputElement,
                imageBoxLabel,
                imageBoxIndexName,
                previewElement,
                modalElement,
                hiddenInputForImagePreviewDataURI,
                croppedImagePreview,
                cropJSONDataInputElement,
                removeImageButtonElement,
                isMainCheckBoxElement;

            // https://stackoverflow.com/questions/34896106/attach-event-to-dynamic-elements-in-javascript
            // https://dev.to/akhil_001/adding-event-listeners-to-the-future-dom-elements-using-event-bubbling-3cp1
            // https://gomakethings.com/why-you-shouldnt-attach-event-listeners-in-a-for-loop-with-vanilla-javascript/
            while (targetElement !== null) {
                if (targetElement.matches('.st-image-to-crop')) {
                    imageBox = targetElement;
                    fileInputElement = imageBox.querySelector('.st-file-input');
                    // Get image box index name
                    imageBoxLabel = imageBox.querySelector('.st-image-to-crop-label');
                    imageBoxIndexName = imageBoxLabel.getAttribute('data-image-index-name');
                    // Preview and resize (crop) image actions element are made inside a modal box
                    previewElement = document.getElementById(`st-cropper-preview-${imageBoxIndexName}`);
                    // Modal where crop is being set
                    modalElement = document.getElementById(`st-cropper-modal-${imageBoxIndexName}`);
                    // Hidden input to store image thumb which corresponds to reduced cropped image preview data URI
                    hiddenInputForImagePreviewDataURI = imageBox.querySelector('.st-image-preview-data-uri');
                    // Form image preview with cropped result
                    croppedImagePreview = imageBox.querySelector('.st-image-preview');
                    // Crop data hidden input
                    cropJSONDataInputElement = imageBox.querySelector('.st-crop-data');
                    // Image box removal button
                    removeImageButtonElement = imageBox.querySelector('.st-image-remove-button');
                    // Main image checkbox input
                    isMainCheckBoxElement = imageBox.querySelector('.st-is-main-image');

                    // ------------------------------------------------------------------------------------------------------------

                    // Event "click" is triggered before "change".
                    const clickOnFileInputHandler = event => {
                        // Set value to null to enable change event for the same previous file selected
                        event.target.value = null;
                        fileInputElement.removeEventListener('click', clickOnFileInputHandler);
                    };
                    fileInputElement.addEventListener('click', clickOnFileInputHandler);

                    // ------------------------------------------------------------------------------------------------------------

                    // Manage crop process with corresponding file input
                    const changeOnFileInputHandler = () => {
                        // Create an object in cropParams var to crop each image and store JSON data in corresponding hidden input
                        cropParams = {
                            formElement: formElement,
                            fileInputElement: fileInputElement,
                            hiddenInputElement: cropJSONDataInputElement,
                            previewElement: previewElement,
                            modalElement: modalElement,
                            showResultElement: croppedImagePreview,
                            hiddenInputForImagePreviewDataURIElement: hiddenInputForImagePreviewDataURI,
                            errors: {unCropped: false},
                            notificationGroup: 'trick',
                            getParams: () => {
                                return {
                                    autoCropArea: 1,
                                    minCropBoxWidth: 1600,
                                    minCropBoxHeight: 900,
                                    maxFileSize: 2000000, // 2M
                                    previewWidth: 142,
                                    previewHeight: 80,
                                    ratio: 16 / 9,
                                    viewMode: 1,
                                    movable: true,
                                    rotatable: false,
                                    scalable: true,
                                    zoomable: false
                                };
                            },
                            getCropDataImagesArray: () => {
                                return cropParams.cropDataImagesArray !== undefined ? cropParams.cropDataImagesArray : null;
                            },
                            getCropJSONData: () => {
                                return cropParams.cropJSONData !== undefined ? cropParams.cropJSONData : null;
                            },
                            setCropDataImagesArray: (imageOriginalName, data) => {
                                // Variable as property name can also be used with ES6 dynamic property [imageOriginalName] notation!
                                data['imageName'] = imageOriginalName;
                                let objectToStringify = data;
                                cropParams.cropDataImagesArray !== undefined
                                    ? cropParams.cropDataImagesArray.concat(objectToStringify)
                                    : cropParams.cropDataImagesArray = [objectToStringify];
                            },
                            setCropJSONData: (cropDataImagesArray) => {
                                cropParams.cropJSONData = JSON.stringify(cropDataImagesArray);
                            }
                        };
                        cropper(cropParams);
                        fileInputElement.removeEventListener('change', changeOnFileInputHandler);
                    };
                    fileInputElement.addEventListener('change', changeOnFileInputHandler);

                    // ------------------------------------------------------------------------------------------------------------

                    // "Click" event on image box removal button (link)
                    // Activate event listener
                    const clickRemoveImageButtonHandler = event => {
                        // Prevent link anchor to scroll up the window
                        event.preventDefault();
                        // Get current image removal button which is clicked
                        let removeImageButtonElement = event.currentTarget; // item
                        // Delete existing image on server with AJAX if necessary
                        // If deletion failed, image to crop box will not be removed.
                        if (removeImageButtonElement.hasAttribute('data-uuid')) {
                            // Prepare element to which window will scroll after deletion
                            // Here it is the same principle as in form.js!
                            let referenceElementToScroll = document.getElementById('st-form');
                            referenceElementToScroll = referenceElementToScroll.parentElement.parentElement.parentElement;
                            // Image box element removal will be called internally if it is ok!
                            deleteMedia(
                                removeImageButtonElement,
                                referenceElementToScroll,
                                imageToCropBoxElements
                            );
                        // Remove image to crop box directly
                        } else {
                            // Remove corresponding "image to crop" box by using its wrapper which was created dynamically.
                            // Look at "addImageButton" click event listener!
                            removeImageBox(removeImageButtonElement, imageToCropBoxElements);
                        }
                        removeImageButtonElement.removeEventListener('click', clickRemoveImageButtonHandler);
                    };
                    removeImageButtonElement.addEventListener('click', clickRemoveImageButtonHandler);

                    // ------------------------------------------------------------------------------------------------------------

                    // "Click" event on image box "isMain" checkbox input
                    // Activate event listener
                    const clickMainCheckBoxHandler = () => {
                        // CSS id for clicked checkbox element
                        let clickedElementId = isMainCheckBoxElement.getAttribute('id');
                        if (isMainCheckBoxElement.checked === true) {
                            // Show "Main image" indicator label
                            document.getElementById(`st-main-image-indicator-${imageBoxIndexName}`).classList.remove('uk-hidden');
                            // Get all "isMain" checkboxes
                            let allIsMainCheckBoxElements = document.querySelectorAll('.st-is-main-image');
                            // Get all "isMain" indicators ("MAIN IMAGE" label)
                            let allIsMainIndicatorsElements = document.querySelectorAll('.st-main-image-indicator');
                            for (let i = 0; i < allIsMainCheckBoxElements.length; i++) {
                                // CSS id for traversed checkbox element in loop
                                let traversedElementId = allIsMainCheckBoxElements[i].getAttribute('id');
                                // Search for the last checked "isMain" checkbox in set to cancel it and not the one which was just checked!
                                if (allIsMainCheckBoxElements[i].checked === true && clickedElementId !== traversedElementId) {
                                    // Uncheck this checkbox
                                    allIsMainCheckBoxElements[i].checked = false;
                                    // Hide "MAIN IMAGE" indicator label associated to this checkbox
                                    allIsMainIndicatorsElements[i].classList.add('uk-hidden');
                                    break;
                                }
                            }
                        } else {
                            // Hide "MAIN IMAGE" indicator label associated to this checkbox element isMainCheckBoxElements[imageBoxIndexName]
                            let currentIndicatorElement = document.getElementById(`st-main-image-indicator-${imageBoxIndexName}`);
                            currentIndicatorElement.classList.add('uk-hidden');
                        }
                        isMainCheckBoxElement.removeEventListener('click', clickMainCheckBoxHandler);
                    };
                    isMainCheckBoxElement.addEventListener('click', clickMainCheckBoxHandler);

                    // ------------------------------------------------------------------------------------------------------------

                    // Add media box sortable button element action
                    let mediaSortableButton = imageBox.querySelector('.uk-sortable-handle');
                    // Prevent window to scroll to top when sortable handler link is clicked (no id is defined on anchor!).
                    mediaSortableButton.addEventListener('click', event => {
                        event.preventDefault();
                    });

                    // ------------------------------------------------------------------------------------------------------------

                    // Stop while
                    return;
                }
                // Continue bubbling up
                targetElement = targetElement.parentElement;
            }
            // "options" parameter is important to be in capturing and then bubbling phase for images collection images children
            // https://stackoverflow.com/questions/5657292/why-is-false-used-after-this-simple-addeventlistener-function
        }, true);

        // ------------------------------------------------------------------------------------------------------------

        // "Click" event on "add a new image" button to show a new image block (will be added to PHP Collection)
        addImageButton.addEventListener('click', () => {
            // Unescape special chars and html entities in prototype template string
            imagePrototype = htmlStringHandler.htmlAttributeOnString.unescape(imagePrototype);
            // Get last "image to crop" box index name
            let lastImageBoxFormIndexName = 0;
            let usedIndexNames = [];
            let imageBoxLabel = null;
            let mustSetMainImage = false;
            // Are there already existing image blocks to define last index
            imageToCropBoxElements = document.querySelectorAll('.st-image-to-crop');
            if (imageToCropBoxElements.length >= 1) {
                imageToCropBoxElements.forEach((imageBox, index) => {
                    // Get label element
                    imageBoxLabel = imageBox.querySelector('.st-image-to-crop-label');
                    // Get the index name stored in label data attribute
                    usedIndexNames.push(parseInt(imageBoxLabel.getAttribute('data-image-index-name'), 10));
                });
                // Get the last used highest index name like this to avoid issue with "image to crop" boxes sortable function
                lastImageBoxFormIndexName = Math.max(...usedIndexNames); // with spread operator
            } else {
                // Indicate to initialize main image option on unique first "image to crop" box which is created
                mustSetMainImage = true;
            }
            // Prepare index for new image box index to prepend by adding 1 to value
            let newImageBoxIndexName = lastImageBoxFormIndexName + 1;
            // Replace image index name pattern in unescaped prototype
            let newImageBoxFormTemplateString = imagePrototype.replace(new RegExp(imagePrototypeName, 'gm'), newImageBoxIndexName);
            // Show a new image box prototype, by using a wrapper not to have an issue with new element event registering
            // Maybe, things can be done in a better and easiest way!
            let newImageBox = document.createElement('div');
            newImageBox.setAttribute('id', `st-image-to-crop-wrapper-${newImageBoxIndexName}`);
            newImageBox.setAttribute('class', 'st-image-to-crop-wrapper');
            newImageBox.insertAdjacentHTML('afterbegin', newImageBoxFormTemplateString);
            // Get label element
            imageBoxLabel = newImageBox.querySelector('.st-image-to-crop-label');
            // Use a rank instead of "newImageBoxIndexName" in new image box label
            let rank = imageToCropBoxElements !== null ? imageToCropBoxElements.length + 1 : 1;
            imageBoxLabel.textContent = imageBoxLabel.innerText.replace(new RegExp(newImageBoxIndexName.toString(), 'g'), rank.toString());
            // Update show list rank to avoid constraint violation issue on add
            newImageBox.querySelector('.st-show-list-rank').value = rank;
            // Main image checkbox input is automatically checked for the first created "image to crop" box
            if (mustSetMainImage) {
                // Check option
                newImageBox.querySelector('.st-is-main-image').checked = true;
                // Show "MAIN IMAGE" label
                newImageBox.querySelector('.st-main-image-indicator').classList.remove('uk-hidden');
            }
            // Add new box to DOM
            //addImageButton.insertAdjacentElement('beforebegin', newImageBox);
            document.getElementById('st-images-collection-sortable-wrapper').insertAdjacentElement('beforeend', newImageBox);

            // ------------------------------------------------------------------------------------------------------------

            // Add media box sortable button element action
            let mediaSortableButton = newImageBox.querySelector('.uk-sortable-handle');
            // Prevent window to scroll to top when sortable handler link is clicked (no id is defined on anchor!).
            mediaSortableButton.addEventListener('click', event => {
                event.preventDefault();
            });
        });

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
                imageToCropBoxElements = document.querySelectorAll('.st-image-to-crop');
                if (imageToCropBoxElements.length > 1) {
                    imageToCropBoxElements.forEach((imageBox, index) => {
                        // Update current moved "image to crop" box hidden input value which stores corresponding rank.
                        updateBoxCollectionRankAndLabel(event, imageBox, index, '.st-image-to-crop-label');
                    });
                }
            }
        });

        // ------------------------------------------------------------------------------------------------------------

        // Implement logic for videos collection
        videosCollectionContainer.addEventListener('click', event => {
            // Initialize event target element for bubbling up
            let targetElement = event.target;
            let videoBox,
                videoBoxLabel,
                videoBoxIndexName,
                removeVideoButtonElement;

            // https://stackoverflow.com/questions/34896106/attach-event-to-dynamic-elements-in-javascript
            // https://dev.to/akhil_001/adding-event-listeners-to-the-future-dom-elements-using-event-bubbling-3cp1
            // https://gomakethings.com/why-you-shouldnt-attach-event-listeners-in-a-for-loop-with-vanilla-javascript/
            while (targetElement !== null) {
                if (targetElement.matches('.st-video-infos')) {
                    videoBox = targetElement;
                    // Get video box index name
                    videoBoxLabel = videoBox.querySelector('.st-video-infos-label');
                    videoBoxIndexName = videoBoxLabel.getAttribute('data-video-index-name');
                    // Video box removal button
                    removeVideoButtonElement = videoBox.querySelector('.st-video-remove-button');

                    // ------------------------------------------------------------------------------------------------------------

                    // "Click" event on video box removal button (link)
                    // Activate event listener
                    const clickRemoveVideoButtonHandler = event => {
                        // Prevent link anchor to scroll up the window
                        event.preventDefault();
                        let targetedElement = event.currentTarget; // item
                        // Remove corresponding "video infos" box by using its wrapper which was created dynamically.
                        // Look at "addVideoButton" click event listener!
                        if (targetedElement.parentElement.parentElement.classList.contains('st-video-infos-wrapper')) {
                            targetedElement.parentElement.parentElement.remove();
                            // Remove directly "video infos" box (.st-video-infos) if no wrapper exists.
                        } else {
                            targetedElement.parentElement.remove();
                        }
                        // Loop on existing "video infos" boxes to update video box index name
                        videoInfosBoxElements = document.querySelectorAll('.st-video-infos');
                        if (videoInfosBoxElements.length !== 0) {
                            videoInfosBoxElements.forEach((videoBox, index) => {
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
                        removeVideoButtonElement.removeEventListener('click', clickRemoveVideoButtonHandler);
                    };
                    removeVideoButtonElement.addEventListener('click', clickRemoveVideoButtonHandler);

                    // ------------------------------------------------------------------------------------------------------------

                    // Add media box sortable button element action
                    let mediaSortableButton = videoBox.querySelector('.uk-sortable-handle');
                    // Prevent window to scroll to top when sortable handler link is clicked (no id is defined on anchor!).
                    mediaSortableButton.addEventListener('click', event => {
                        event.preventDefault();
                    });

                    // ------------------------------------------------------------------------------------------------------------

                    // Stop while
                    return;
                }
                // Continue bubbling up
                targetElement = targetElement.parentElement;
            }

            // "options" parameter is important to be in capturing and then bubbling phase for videos collection videos children
            // https://stackoverflow.com/questions/5657292/why-is-false-used-after-this-simple-addeventlistener-function
        }, true);

        // ------------------------------------------------------------------------------------------------------------

        // "Click" event on "add a new video" button to show a new video block (will be added to PHP Collection)
        addVideoButton.addEventListener('click', () => {
            // Unescape special chars and html entities in prototype template string
            videoPrototype = htmlStringHandler.htmlAttributeOnString.unescape(videoPrototype);
            // Get last "video infos" box index name
            let lastVideoBoxFormIndexName = 0;
            let usedIndexNames = [];
            let videoBoxLabel = null;
            // Are there already existing video blocks to define last index
            videoInfosBoxElements = document.querySelectorAll('.st-video-infos');
            if (videoInfosBoxElements.length >= 1) {
                videoInfosBoxElements.forEach((videoBox, index) => {
                    // Get label element
                    videoBoxLabel = videoBox.querySelector('.st-video-infos-label');
                    // Get the index name stored in label data attribute
                    usedIndexNames.push(parseInt(videoBoxLabel.getAttribute('data-video-index-name'), 10));
                });
                // Get the last used highest index name like this to avoid issue with "video infos" boxes sortable function
                lastVideoBoxFormIndexName = Math.max(...usedIndexNames); // with spread operator
            }
            // Prepare index for new image box index to prepend by adding 1 to value
            let newVideoBoxIndexName = lastVideoBoxFormIndexName + 1;
            // Replace video index name pattern in unescaped prototype
            let newVideoBoxFormTemplateString = videoPrototype.replace(new RegExp(videoPrototypeName, 'gm'), newVideoBoxIndexName);
            // Show a new video box prototype, by using a wrapper not to have an issue with new element event registering
            // Maybe, things can be done in a better and easiest way!
            let newVideoBox = document.createElement('div');
            newVideoBox.setAttribute('id', `st-video-infos-wrapper-${newVideoBoxIndexName}`);
            newVideoBox.setAttribute('class', 'st-video-infos-wrapper');
            newVideoBox.insertAdjacentHTML('afterbegin', newVideoBoxFormTemplateString);
            // Get label element
            videoBoxLabel = newVideoBox.querySelector('.st-video-infos-label');
            // Use a rank instead of "newVideoBoxIndexName" in new video box label
            let rank = videoInfosBoxElements !== null ? videoInfosBoxElements.length + 1 : 1;
            videoBoxLabel.textContent = videoBoxLabel.innerText.replace(new RegExp(newVideoBoxIndexName.toString(), 'g'), rank.toString());
            // Update show list rank to avoid constraint violation issue on add
            newVideoBox.querySelector('.st-show-list-rank').value = rank;
            // Add new box to DOM
            document.getElementById('st-videos-collection-sortable-wrapper').insertAdjacentElement('beforeend', newVideoBox);
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
                videoInfosBoxElements = document.querySelectorAll('.st-video-infos');
                if (videoInfosBoxElements.length > 1) {
                    videoInfosBoxElements.forEach((videoBox, index) => {
                        // Update current moved "video infos" box hidden input value which stores corresponding rank.
                        updateBoxCollectionRankAndLabel(event, videoBox, index, '.st-video-infos-label');
                    });
                }
            }
        });

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
    }
};
