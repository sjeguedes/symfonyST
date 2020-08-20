import cropper from './all/cropper';
import UIkit from "../../uikit/dist/js/uikit.min";
import request from './all/ajax-request';
import URIHelper from './all/encode-decode-uri';
import createNotification from "./all/create-notification";
export default () => {
    // Resources:
    // RequestAnimationFrame: https://blog.teamtreehouse.com/efficient-animations-with-requestanimationframe
    // Appended element and css transitions issue: https://stackoverflow.com/questions/24148403/trigger-css-transition-on-appended-element
    // Understanding JSON: https://medium.com/@timothyrobards/understanding-json-in-javascript-5098876d0915
    // check empty object: https://coderwall.com/p/_g3x9q/how-to-check-if-javascript-object-is-empty
    // Get object properties names: https://developer.mozilla.org/fr/docs/Web/JavaScript/Reference/Objets_globaux/Object/getOwnPropertyNames
    // Upload files - ajax / php / js: https://www.taniarascia.com/how-to-upload-files-to-a-server-with-plain-javascript-and-php/
    // https://blog.eleven-labs.com/en/upload-file-ajax/
    // https://ourcodeworld.com/articles/read/53/how-to-upload-a-file-with-jquery-ajax-in-php-or-symfony
    // Set a variable as an object property name: https://stackoverflow.com/questions/2274242/how-to-use-a-variable-for-a-key-in-a-javascript-object-literal
    // xhr fundamentals: https://javascript.info/xmlhttprequest#the-basics
    // xhr header: https://stackoverflow.com/questions/4658789/symfony-request-isxmlhttprequest-issue
    // Upload / Download progress: https://zinoui.com/blog/ajax-request-progress-bar

    const formElement = document.getElementById('st-update-profile-form');
    if (formElement) {
        // Crop data hidden input
        const cropJSONDataInputElement = formElement.querySelector('.st-crop-data');
        // Avatar file input
        const fileInputElement = formElement.querySelector('.st-file-input');
        // Avatar preview
        const avatarImagePreview = document.getElementById('st-avatar-preview');
        // Avatar form element
        const avatarUpdateFormElement = document.getElementById('st-ajax-avatar-update-form');
        const avatarUploadAjaxMode = avatarUpdateFormElement.getAttribute('data-ajax-mode');
        let cropParams = {};

        // ------------------------------------------------------------------------------------------------------------

        // Update avatar action
        // Event "click" is triggered before "change".
        fileInputElement.addEventListener('click', (event) => {
            // Set value to null to enable change event for the same previous file selected
            event.target.value = null;
        });
        fileInputElement.addEventListener('change', () => {
            // Preview and resize (crop) avatar actions
            const previewElement = document.getElementById('st-cropper-preview'),
                  modalElement = document.getElementById('st-cropper-modal');
            cropParams = {
                hiddenInputElement: cropJSONDataInputElement,
                formElement: avatarUpdateFormElement,
                fileInputElement: fileInputElement,
                previewElement: previewElement,
                modalElement: modalElement,
                showResultElement: avatarImagePreview,
                errors: {unCropped: false},
                notificationGroup: 'profile',
                getParams: () => {
                    return {
                        autoCropArea: 1,
                        minCropBoxWidth: 80,
                        minCropBoxHeight: 80,
                        maxFileSize: 2000000, // 2M
                        previewWidth: 80,
                        previewHeight: 80,
                        ratio: 1,
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
                    // Variable as property name can also be used with
                    // ES6 dynamic property [imageOriginalName] notation!
                    data['imageName'] = imageOriginalName; // Add original image name to object
                    let objectToStringify = data;
                    // Avoid XSSI vulnerability with array of potential multiple results in object property
                    // https://cheatsheetseries.owasp.org/cheatsheets/AJAX_Security_Cheat_Sheet.html#always-return-json-with-an-object-on-the-outside
                    cropParams.cropDataImagesArray !== undefined
                        ? cropParams.cropDataImagesArray.results.concat(objectToStringify)
                        : cropParams.cropDataImagesArray = { results: [objectToStringify] };
                },
                setCropJSONData: (cropDataImagesArray) => {
                    cropParams.cropJSONData = JSON.stringify(cropDataImagesArray);
                }
            };
            cropper(cropParams);

            // ------------------------------------------------------------------------------------------------------------

            // Manage file change text info without crop error and re-activate remove button
            if (!cropParams.errors.unCropped) {
                let modalElementID = '#' + modalElement.getAttribute('id');
                // Callback when modal is hidden
                UIkit.util.on(modalElementID, 'hidden', () => {
                    // Avatar action text info
                    const avatarActionTextInfo = document.getElementById('st-avatar-text-info');
                    // Add change (update or select) text info
                    const changeContainerElement = document.getElementById('st-change-avatar-container');
                    avatarActionTextInfo.classList.add('uk-hidden');
                    avatarActionTextInfo.classList.remove('st-ati-fade-in');
                    avatarActionTextInfo.querySelector('.st-avatar-text-info-content')
                                        .innerHTML = changeContainerElement.getAttribute('data-change-text-info');
                    // Check if avatar text info is added to DOM
                    checkAvatarTextInfoAdded();
                    // Change button label to "Change" instead of "Select"
                    const changeButtonElement = document.getElementById('st-avatar-change-button-label');
                    changeButtonElement.innerText = changeButtonElement.getAttribute('data-change-label');
                });
            }

            // ------------------------------------------------------------------------------------------------------------
        });

        // Process avatar upload only with ajax mode activation
        if ('1' === avatarUploadAjaxMode) {
            // Cancel as much as possible all existing notifications to avoid multiple shows
            UIkit.notification.closeAll();
            // Event "submit" is used to perform ajax request in order to crop avatar image on server-side.
            avatarUpdateFormElement.addEventListener('submit', (event) => {
                if (cropParams !== undefined && isEmptyObject(cropParams)) {
                    return;
                }
                // Avoid normal submission
                event.preventDefault();
                // Submit button
                const submitButton = document.getElementById('st-avatar-submit-button');
                const buttonSpinner = document.querySelector('#st-avatar-submit-button .st-profile-spinner');
                // Get all form data including uploaded file, removal option and crop JSON data
                const formData = new FormData(avatarUpdateFormElement);
                // Upload progress
                const percentText = document.getElementById('st-avatar-upload-loaded-percent');
                const progressContainer = document.getElementById('st-avatar-upload-progress');
                const avatarActionTextInfo = document.getElementById('st-avatar-text-info');
                let ratio = 0;
                let technicalError = avatarUpdateFormElement.getAttribute('data-technical-error');

                // ------------------------------------------------------------------------------------------------------------

                // AJAX request
                let formAction = avatarUpdateFormElement.getAttribute('data-avatar-upload-path');
                const pathHandler = URIHelper();
                formAction = pathHandler.uriOnString.encode(formAction);
                const obj = {
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    url: formAction,
                    method: 'POST',
                    body: formData,
                    async: true,
                    withCredentials: false,
                    responseType: 'json',
                    onLoadStartFunction: () => {
                        // Disable button
                        submitButton.classList.add('uk-disabled');
                        // Show loading spinner
                        buttonSpinner.classList.remove('uk-hidden');
                    },
                    onLoadEndFunction: () => {
                        // Enable button
                        submitButton.classList.remove('uk-disabled');
                        // Hide spinner
                        buttonSpinner.classList.add('uk-hidden');
                    },
                    onUploadContextFunction: xhr => {
                        // Initialize upload tracking
                        xhr.upload.onloadstart = () => {
                            progressContainer.classList.remove('uk-hidden');
                            progressContainer.classList.add('st-aup-fade-in');
                            percentText.innerText = '0%';
                        };
                        // Show upload progress
                        xhr.upload.onprogress = event => {
                             if (event.lengthComputable) {
                                ratio = Math.floor((event.loaded / event.total) * 100);
                                percentText.innerText = ratio + '%';
                             }
                        };
                        // Manage upload end state
                        xhr.upload.onloadend = () => {
                            // Transition to opacity equals to 0
                            progressContainer.classList.remove('st-aup-fade-in');
                            // Hide update text info
                            avatarActionTextInfo.classList.add('uk-hidden');
                        }
                    }
                };
                // Use promise with callbacks
                request(obj).then((response) => {
                    // Manage response when upload progress tracking disappeared
                    progressContainer.addEventListener('transitionend', () => {
                        // Hide upload progress container
                        progressContainer.classList.add('uk-hidden');
                        // No need to parse with JSON.parse(response): response is already an object
                        for (let prop in response) {
                            if (Object.prototype.hasOwnProperty.call(response, prop)) {
                                switch (prop.toString()) {
                                    case 'formError':
                                        // Close all possible previous notifications
                                        UIkit.notification.closeAll();
                                        // Add info to check file type, size and dimensions
                                        let additionalMessage = fileInputElement.getAttribute('data-error-6');
                                        // Error notification
                                        createNotification(
                                            response.formError.notification + `\n` + additionalMessage,
                                            null,
                                            true,
                                            'error',
                                            'warning',
                                            5000
                                        );
                                        break;
                                    case 'redirectionURL':
                                        // Perform a redirection as expected
                                        window.location.replace(response.redirectionURL);
                                        break;
                                    default:
                                        window.location.href = formAction;
                                }
                            }
                        }
                    });
                })
                // It is important to chain to prevent ajax request from being called twice!
                .catch(xhr => {
                    // Hide spinner but do not re-enable button
                    buttonSpinner.classList.add('uk-hidden');
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

        // ------------------------------------------------------------------------------------------------------------

        // Manage removal text info and show default avatar
        const removeButtonElement = document.getElementById('st-file-remove-button');
        // Remove avatar action
        if (removeButtonElement) {
            // Click on remove button
            removeButtonElement.addEventListener('click', () => {
                // Switch visually to default avatar with fade out / fade in effect
                const hiddenInputType = formElement.querySelector('.st-remove-avatar');
                if (!avatarImagePreview.classList.contains('st-ap-fade-out')) {
                    avatarImagePreview.classList.add('st-ap-fade-out');
                }
                // Reset preview to default avatar
                const setAvatarToDefault = event => {
                    if (window.getComputedStyle(avatarImagePreview).getPropertyValue('opacity') === '0') {
                        avatarImagePreview.src = avatarImagePreview.getAttribute('data-default-image-path');
                        avatarImagePreview.setAttribute('alt', avatarImagePreview.getAttribute('data-default-desc'));
                    }
                    event.target.removeEventListener(event.type, setAvatarToDefault);
                };
                avatarImagePreview.addEventListener("transitionend", setAvatarToDefault);
                // Default image is loaded, so fade in it.
                const addAvatarPreviewFadeEffect = event => {
                    if (avatarImagePreview.classList.contains('st-ap-fade-out')) {
                        avatarImagePreview.classList.remove('st-ap-fade-out');
                    }
                    // Set avatar removal to true
                    hiddenInputType.setAttribute('value', '1');
                    // Hide remove button
                    removeButtonElement.parentElement.classList.add('uk-hidden');
                    // Avatar action text info
                    const avatarActionTextInfo = document.getElementById('st-avatar-text-info');
                    // Remove/re-add text info
                    const removeContainerElement = document.getElementById('st-remove-avatar-container');
                    avatarActionTextInfo.classList.add('uk-hidden');
                    avatarActionTextInfo.classList.remove('st-ati-fade-in');
                    avatarActionTextInfo.querySelector('.st-avatar-text-info-content')
                                        .innerHTML = removeContainerElement.getAttribute('data-remove-text-info');
                    // Check if avatar text info is added to DOM
                    checkAvatarTextInfoAdded();
                    // Remove listener to be executed once a time.
                    event.target.removeEventListener(event.type, addAvatarPreviewFadeEffect);
                };
                avatarImagePreview.addEventListener('load', addAvatarPreviewFadeEffect);
            });
        }
    }

    // ------------------------------------------------------------------------------------------------------------

    // Add fade in effect if avatar text info element is added to DOM
    const checkAvatarTextInfoAdded = () => {
        const callable = () => {
            // Avatar action text info
            const avatarActionTextInfo = document.getElementById('st-avatar-text-info');
            avatarActionTextInfo.classList.remove('uk-hidden');
            if (window.getComputedStyle(avatarActionTextInfo).getPropertyValue('opacity') === '0') {
                avatarActionTextInfo.classList.add('st-ati-fade-in');
                cancelAnimationFrame(fallback);
            } else {
                requestAnimationFrame(callable);
            }
        };
        const fallback = requestAnimationFrame(callable);
    };

    // Check empty object (with no property or empty properties
    const isEmptyObject = object => {
        if (0 === Object.getOwnPropertyNames(object).length) {
            return true;
        }
        for (let key in object) {
            if (object.hasOwnProperty(key)) {
                return false;
            }
        }
        return true;
    };
}
