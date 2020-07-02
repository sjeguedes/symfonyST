import createNotification from './create-notification';
import Cropper from 'cropperjs/dist/cropper.min';
//import canvasToBlob from './polyfill-canvas-to-blob';
import URIHelper from './encode-decode-uri';
import UIkit from "../../../uikit/dist/js/uikit.min";
import smoothScroll from "./smooth-vertical-scroll";
export default (cropParams) =>  {
    // Resources:
    // https://github.com/fengyuanchen/cropperjs/blob/master/README.md
    // https://fengyuanchen.github.io/cropperjs/examples/cropper-in-modal.html
    // https://github.com/fengyuanchen/cropperjs/issues/339
    // Canvas to blob: https://github.com/eligrey/canvas-toBlob.js
    // cropper - vue.js - lodash/debounce: https://lobotuerto.com/blog/cropping-images-with-vuejs-and-cropperjs/
    // IMPORTANT LINK: debounced / throttle principles: https://codeburst.io/throttling-and-debouncing-in-javascript-646d076d0a44
    // "change" event enabled with "click": https://stackoverflow.com/questions/4109276/how-to-detect-input-type-file-change-for-the-same-file
    // One time listener: https://medium.com/beginners-guide-to-mobile-web-development/one-off-event-listeners-in-javascript-92e19c4c0336
    // Loop in object: https://stackoverflow.com/questions/8312459/iterate-through-object-properties
    // https://dev.to/saigowthamr/how-to-loop-through-object-in-javascript-es6-3d26
    // Data URI to File object: https://stackoverflow.com/questions/4998908/convert-data-uri-to-file-then-append-to-formdata
    // Compress and resize image with javaScript: https://zocada.com/compress-resize-images-javascript-browser/
    // Box width - height calculation: https://www.javascripttutorial.net/javascript-dom/javascript-width-height/

    // ------------------------------------------------------------------------------------------------------------

    // Retrieve last selected file to upload
    let file = cropParams.fileInputElement.files[cropParams.fileInputElement.files.length - 1];
    // Set UIkit notification group
    let groupOption = cropParams.notificationGroup !== undefined ? cropParams.notificationGroup : null;

    // ------------------------------------------------------------------------------------------------------------

    // Check validity of file
    // Allow only image of these types provided by file input selection or change
    const isFileRefused = file => {
        // Update (reset) error state to use it in scripts if necessary
        cropParams.errors.unCropped = false;
        // WARNING: "file" variable is the last uploaded file but crop process must be adapted to be functional with multiple upload!
        if (false === file || false === /^image\/(pjpeg|jpeg|png|gif)$/gi.test(file.type)) {
            // Inform user with notification
            createNotification(cropParams.fileInputElement.getAttribute('data-error'), groupOption, true);
            // Update error state to use it in scripts
            cropParams.errors.unCropped = true;
            // Stop crop action in this case
            return true;
        }
        return false;
    };
    // Prevent crop process by not showing modal and un-instantiating a cropper object
    if (isFileRefused(file)) {
        return;
    }

    // ------------------------------------------------------------------------------------------------------------

    // Warn user by stopping crop process, chosen image will not be validated by PHP server response after submission
    const isInvalidImage = imageElement => {
        // Update (reset) error state to use it in scripts if necessary
        cropParams.errors.unCropped = false;
        let invalidDimensions = imageElement.width < params.minCropBoxWidth || imageElement.height < params.minCropBoxHeight;
        let invalidSize = file.size > params.maxFileSize;
        let errorMessage = '';
        // Min width or min height is not respected!
        if (invalidDimensions) {
            errorMessage = cropParams.fileInputElement.getAttribute('data-error-2');
            // Size is not respected!
        } else if (invalidSize) {
            errorMessage = cropParams.fileInputElement.getAttribute('data-error-3');
        }
        if (invalidDimensions || invalidSize) {
            // Inform user with notification
            createNotification(errorMessage, groupOption, true);
            // Update error state to use it in scripts
            cropParams.errors.unCropped = true;
            // Stop crop action in this case
            return true;
        }
        return false;
    };

    // ------------------------------------------------------------------------------------------------------------

    // Avoid server side exception due to incoherent crop data if selected file does not corresponds to these data!
    const resetCropDataElements = () => {
        // https://stackoverflow.com/questions/1703228/how-can-i-clear-an-html-file-input-with-javascript/16222877
        // https://stackoverflow.com/questions/9011644/how-to-reset-clear-file-input
        // Empty file input
        try {
            cropParams.fileInputElement.value = ''; // for IE11, latest Chrome/Firefox/Opera...
        } catch (error) {} // Do nothing!
        if (cropParams.fileInputElement.value) { // for IE5 ~ IE10
            let form = document.createElement('form'),
                parentNode = cropParams.fileInputElement.parentNode,
                ref = cropParams.fileInputElement.nextSibling;
            form.appendChild(cropParams.fileInputElement);
            form.reset();
            parentNode.insertBefore(cropParams.fileInputElement, ref);
        }
        // This concerns trick creation and update!
        if (cropParams.hiddenInputForImagePreviewDataURIElement !== undefined) {
            // Empty image preview data URI
            cropParams.hiddenInputForImagePreviewDataURIElement.value = '';
        }
        // This concerns trick creation and update and avatar update!
        // Go back to default image as crop result preview
        cropParams.showResultElement.src = cropParams.showResultElement.getAttribute('data-default-image-path');
        // Empty crop JSON data for all cases
        cropParams.hiddenInputElement.value = '';
    };

    // ------------------------------------------------------------------------------------------------------------

    // Read loaded image to show a preview with crop in modal
    const params = cropParams.getParams();
    const modalElementID =  '#' + cropParams.modalElement.getAttribute('id');
    let reader = new FileReader();
    let uploadedImage = new Image();

    // ------------------------------------------------------------------------------------------------------------

    // Preview file and call crop initialization cropper
    reader.addEventListener('load', event => {
        uploadedImage.src = event.target['result'].toString();
    });
    reader.readAsDataURL(file);

    // ------------------------------------------------------------------------------------------------------------

    // Check image dimensions outside the DOM to have real dimensions
    uploadedImage.addEventListener('load', event => {
        // Check if image does not respect dimensions ans size constraints in order to prevent crop process
        if (isInvalidImage(event.target)) {
            // Reset crop data elements (will be possible constraints violations) to avoid exception on server side
            resetCropDataElements();
            // CAUTION: avoid an issue with multiple successive uploads as it is after crop success
            cropParams.previewElement = new Image();
            return;
        }
        // Feed crop image preview with file reader result, if dimensions and size are ok!
        cropParams.previewElement.src = uploadedImage.src;
    });

    // ------------------------------------------------------------------------------------------------------------

    // Call crop action when crop image preview is loaded!
    cropParams.previewElement.addEventListener('load', event => {
        // Crop functionality inside modal only if the selected file has an expected image type.
        cropImage();
    });

    // ------------------------------------------------------------------------------------------------------------

    // Call crop process
    const cropImage = () => {
        let newCropper;
        const uriStringHandler = URIHelper();

        // ------------------------------------------------------------------------------------------------------------

        // TODO: maybe delete this!
        // Callback before modal is shown, to fix a container generated twice after multiple changes
        /*UIkit.util.on(modalElementID, 'beforeshow', () => {
            let container = cropParams.modalElement.querySelector('.cropper-container');
            if (container !== null) {
                container.parentElement.removeChild(container);
            }
        });*/

        // ------------------------------------------------------------------------------------------------------------

        // Callback when modal is shown
        UIkit.util.on(modalElementID, 'show', () => {
            // Store file.name which is not updated (due to asynchronous event) in cropHandler and encode it for url
            // Please note quotes can be escaped with JSON.stringify() method!
            // to be compared by decoding this string with php "urldecode()" in abstract upload form handler checkCropData() on server side
            // IMPORTANT! Filename is not sanitized since it will not be injected in HTML!
            // https://github.com/parshap/node-sanitize-filename/blob/master/index.js
            // https://stackoverflow.com/questions/8485027/javascript-url-safe-filename-safe-string
            // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/encodeURI
            // https://locutus.io/php/url/urlencode/
            cropParams.currentFilename = uriStringHandler.uriOnString.encodeParamWithRFC3986(file.name);
            // Will check resize event
            let currentCropBoxData = {};
            let data = {};
            let state = {
                counter: 1,
                isAutoCrop: false,
                isCropped: false,
                isCropHandled: false,
                isCropMove: false,
                isCropValid: true,
                firstLoad: false
            };
            // Get cropper container custom wrapper before "ready" state
            let container = cropParams.modalElement.querySelector('.st-cropper-container-content');
            // Initialize a cropper
            // Issue ans resources:
            // A way to reset cropper for several successive uploads:
            // https://github.com/fengyuanchen/cropper/issues/189
            // A way to restrict crop box to minimum size:
            // https://github.com/fengyuanchen/cropperjs/issues/254
            // https://github.com/fengyuanchen/cropper/issues/587
            // https://github.com/fengyuanchen/cropper/issues/1032
            // https://github.com/fengyuanchen/cropperjs/issues/254#issuecomment-373952972
            newCropper = new Cropper(cropParams.previewElement, {
                // Must be 1 to not define dimensions over natural values
                viewMode: params.viewMode,
                // CAUTION: avoid activation to keep control on crop box position (or even size) when window is resized or during "auto crop"!
                autoCropArea: params.autoCropArea,
                initialAspectRatio: params.ratio,
                aspectRatio: params.ratio,
                imageSmoothingQuality: 'high',
                movable: params.movable,
                rotatable: params.rotatable,
                scalable: params.scalable,
                zoomable: params.zoomable,
                // CAUTION: avoid their use alone or with auto crop, to keep control on crop box position (or even size) on window resize!
                // Do not limit crop box minimum dimensions not to have issue and since it is made in "crop" event callback!
                minCropBoxWidth: params.minCropBoxWidth * container.clientWidth / uploadedImage.width,
                minCropBoxHeight: params.minCropBoxHeight * container.clientHeight / uploadedImage.height,
                ready: () => {
                    state.firstLoad = true;
                    // Define cropper for image element to use it easily in handlers functions
                    cropParams.cropper = newCropper;
                    // This is used to avoid crop box data modification during "auto crop" event on first load
                    newCropper.element.addEventListener('crop', () => {
                        state.isAutoCrop = true;
                        clearTimeout(newCropper.element.autoCropFinished);
                        // End of "auto crop" event
                        newCropper.element.autoCropFinished = setTimeout(() => {
                            if (state.firstLoad && state.counter === 2) {
                                state.firstLoad = false;
                                // Get crop box maximum size corresponding to defined ratio as default size
                                // CAUTION: This must be synchronized with cropper "autoCropArea" option!
                                currentCropBoxData = renderDefaultCropBoxWithMinMaxsize(newCropper, currentCropBoxData, true);
                                // Set defined crop box with default values (call "crop" event)
                                newCropper.setCropBoxData(currentCropBoxData);
                                // These data will be set at the end of crop event!
                                data = {
                                    x: Math.round(currentCropBoxData.left * newCropper.element.width / container.clientWidth),
                                    y: Math.round(currentCropBoxData.top * newCropper.element.height / container.clientHeight),
                                    width: Math.round(currentCropBoxData.width *  newCropper.element.width / container.clientWidth),
                                    height: Math.round(currentCropBoxData.height * newCropper.element.height / container.clientHeight),
                                    rotate: 0,
                                    scale: 1
                                };
                            }
                            // Do not use counter after 2 iterations launched automatically at start
                            if (state.counter < 2) state.counter ++;
                        }, 0);
                    });
                    // Make cropper previewer responsive
                    cropParams.modalElement.querySelector('.cropper-container').classList.add('uk-responsive');
                    // Listen modal close button context
                    cropParams.modalElement.querySelector('.uk-modal-close-outside').addEventListener('click', abortCropHandler);
                    // Listen modal crop button context
                    cropParams.modalElement.querySelector('.st-crop-button').addEventListener('click', cropHandler);
                },
                cropmove: () => {
                    state.isCropMove = true;
                },
                cropstart: event => {
                    if ('all' !== event.detail.action) {
                        state.isCropHandled = true;
                    }
                    // Disable crop button
                    cropParams.modalElement.querySelector('.st-crop-button').classList.add('uk-disabled');
                },
                // resize end check: https://stackoverflow.com/questions/5489946/how-to-wait-for-the-end-of-resize-event-and-only-then-perform-an-action
                crop: event => {
                    newCropper.options.minCropBoxWidth = params.minCropBoxWidth * container.clientWidth / uploadedImage.width;
                    newCropper.options.minCropBoxHeight = params.minCropBoxHeight * container.clientHeight / uploadedImage.height;
                    // Avoid infinite loop with crop end callback
                    if (state.isCropped) {
                        state.isCropped = false;
                        return;
                    }

                    // Adjust wrong behavior with "auto-crop" which modifies crop box position or dimensions
                    if (state.isAutoCrop && !state.isCropMove) {
                        state.isAutoCrop = false;
                        let left = Math.round(data.x * container.clientWidth / newCropper.element.width);
                        let top =  Math.round(data.y * container.clientHeight / newCropper.element.height);
                        let width = Math.round(data.width * container.clientWidth / newCropper.element.width);
                        let height = Math.round(data.height * container.clientHeight / newCropper.element.height);
                        // Get concerned elements
                        currentCropBoxData = {
                            left: left,
                            top: top,
                            width: width,
                            height: height
                        };
                        // Set defined crop box visually
                        setVisualCropBox(container, currentCropBoxData);
                    } else {
                        // Crop box minimum dimensions are forced!
                        if (state.isCropHandled && (Math.ceil(event.detail.width) <= params.minCropBoxWidth || Math.ceil(event.detail.height) <= params.minCropBoxHeight)) {
                            state.isCropHandled = false;
                            state.isCropValid = false;
                            // Define clean data to avoid strange decimal values
                            data.width = params.minCropBoxWidth;
                            data.height = params.minCropBoxHeight;
                        }
                        data.x = event.detail.x;
                        data.y = event.detail.y;
                        // Redefine dimensions if there is no invalid dimensions when box is forced! (see above)
                        if (state.isCropValid) {
                            data.width = event.detail.width;
                            data.height = event.detail.height;
                        }
                    }
                },
                cropend: () => {
                    if (!state.isCropValid) {
                        // Inform user with notification (Minimum crop box size is reached!)
                        createNotification(cropParams.fileInputElement.getAttribute('data-error-5'), groupOption, true, 'info', 'info', 2500);
                        state.isCropValid = true;
                    }
                    newCropper.setData(data);
                    state.isCropped = true;
                    state.isCropMove = false;
                    // Re-enable crop button
                    cropParams.modalElement.querySelector('.st-crop-button').classList.remove('uk-disabled');
                }
            });
        });

        // ------------------------------------------------------------------------------------------------------------

        // Callback when modal close functionality is called
        UIkit.util.on(modalElementID, 'hide', () => {
            UIkit.notification.closeAll(groupOption);
        });

        // ------------------------------------------------------------------------------------------------------------

        // Callback when modal is hidden
        UIkit.util.on(modalElementID, 'hidden', () => {
            // Destruct Cropper instance when modal is definitively hidden to enable next update
            newCropper.destroy();
            // CAUTION - tricky tip: reset image preview object to reinitialize cropper correctly between two successive uploads
            cropParams.previewElement = new Image();  // cropParams.previewElement.src = ''; // can be used instead!
            // Re-position window scroll at form level
            smoothScroll(cropParams.formElement, -50);
        });

        // ------------------------------------------------------------------------------------------------------------

        // Show modal programmatically
        UIkit.modal(cropParams.modalElement).show();
    };

    // ------------------------------------------------------------------------------------------------------------

    // Abort crop action
    const abortCropHandler = event => {
        // Remove listener to be executed once a time.
        event.target.removeEventListener(event.type, abortCropHandler);
        // Close modal immediately
        hideModalHandler(null);
        // Reset crop data elements (will be possible constraints violations) to avoid exception on server side
        resetCropDataElements();
        // Delay notification to make a better visual effect
        let ti = setTimeout(() => {
            // Inform user with notification: image will not be validated on server side and constraints violations will be shown!
            createNotification(cropParams.fileInputElement.getAttribute('data-error-4'), groupOption, true, 'info', 'info');
            clearTimeout(ti);
        }, 1500);

    };

    // ------------------------------------------------------------------------------------------------------------

    // Close button event listener handler
    const cropHandler = event => {
        let newCropBoxData,
            newCanvasData,
            Base64ImagePreviewDataURI;
        // IMPORTANT: This stores file.name which is not accessible in this event listener handler
        // https://stackoverflow.com/questions/256754/how-to-pass-arguments-to-addeventlistener-listener-function
        let newFilename = cropParams.currentFilename;

        // Get crop box and canvas data
        newCropBoxData = cropParams.cropper.getCropBoxData();
        newCanvasData = cropParams.cropper.getCanvasData();
        // Should set crop box data first here
        cropParams.cropper.setCropBoxData(newCropBoxData);
        cropParams.cropper.setCanvasData(newCanvasData);

        // Get cropped data to feed hidden particular field to effectively crop image on server-side
        let cropData = cropParams.cropper.getData();
        let roundedData = {};
        for (let prop in cropData) {
            if (Object.prototype.hasOwnProperty.call(cropData, prop)) {
                let value = cropData[prop];
                if (typeof value === 'number') {
                    value = Math.round(value);
                }
                roundedData[prop] = value;
            }
        }
        // Save crop data on server-side to avoid tampered data
        cropParams.setCropDataImagesArray(newFilename, roundedData);
        cropParams.setCropJSONData(cropParams.getCropDataImagesArray());
        // Use hidden input to store crop data for constraints validation
        cropParams.hiddenInputElement.setAttribute('value', cropParams.getCropJSONData());
        // Create a base 64 encoded image URi, with crop area (reduced to preview width and height), thanks to default autoCrop option set to true
        Base64ImagePreviewDataURI = cropParams.cropper.getCroppedCanvas({width: params.previewWidth, height: params.previewHeight}).toDataURL(file.type);
        if (cropParams.hiddenInputForImagePreviewDataURIElement) {
            // Store reduced image preview data URI  in corresponding hidden input
            cropParams.hiddenInputForImagePreviewDataURIElement.setAttribute('value', Base64ImagePreviewDataURI);
        }
        // Reset cropped reduced preview to avoid an issue: "load" event is not triggered later if the same image is used twice!
        cropParams.showResultElement.src = '';
        // Show cropped reduced image in form preview
        cropParams.showResultElement.src = Base64ImagePreviewDataURI;
        // Remove listener to be executed once a time.
        event.target.removeEventListener(event.type, cropHandler);
        // Close modal when image preview data URI is loaded!
        cropParams.showResultElement.addEventListener('load', hideModalHandler);
    };

    // ------------------------------------------------------------------------------------------------------------

    // Hide modal programmatically for the two cases
    const hideModalHandler = event => {
        UIkit.modal(cropParams.modalElement).hide();
        // Remove listener to be executed once a time.
        if (event !== null) {
            event.target.removeEventListener(event.type, hideModalHandler);
        }
    };

    // ------------------------------------------------------------------------------------------------------------

    // Render default the crop box with the maximum possible size
    const renderDefaultCropBoxWithMinMaxsize = (newCropper, currentCropBoxData, maxSize) => {
        // Get real container and concerned elements
        let container = cropParams.modalElement.querySelector('.st-cropper-container-content');
        // Retrieve maximum size by calculation
        // Get max width which is container width.
        let width = container.clientWidth;
        // Calculate corresponding height
        let height = Math.ceil(width * params.minCropBoxHeight / params.minCropBoxWidth);
        // Adjust dimensions if needed to keep crop box size inside container size
        if (container.clientHeight <= height) {
            height = container.clientHeight;
            width = Math.ceil(height * params.minCropBoxWidth / params.minCropBoxHeight);
        }
        // Switch to these lines below to get crop box minimum size as default size (can also be used for options settings)
        if (!maxSize) {
            width = Math.ceil(params.minCropBoxWidth * container.clientWidth / uploadedImage.width);
            height = Math.ceil(params.minCropBoxHeight * container.clientHeight / uploadedImage.height);
        }
        // Prepare crop box data
        currentCropBoxData = {
            left: (container.clientWidth - width) / 2,
            top: (container.clientHeight - height) / 2,
            width: width,
            height: height
        };
        // Set defined crop box visually with default values instead of view box settings
        setVisualCropBox(container, currentCropBoxData);
        return currentCropBoxData;
    };

    // ------------------------------------------------------------------------------------------------------------

    // Set crop box HTML element to update it visually
    const setVisualCropBox = (cropContainer, cropBoxData) => {
        let cb = cropContainer.querySelector('.cropper-crop-box');
        let cvb = cropContainer.querySelector('.cropper-view-box');
        let cvbi = cropContainer.querySelector('.cropper-view-box img');
        let cc = cropContainer.querySelector('.cropper-canvas');
        let cci = cropContainer.querySelector('.cropper-canvas img');
        cb.setAttribute(
            'style',
            `width: ${cropBoxData.width}px !important; height: ${cropBoxData.height}px !important;
                   transform: translateX(${cropBoxData.left}px) translateY(${cropBoxData.top}px) !important;`
        );
        cvbi.setAttribute(
            'style',
            `width: ${cropContainer.clientWidth}px !important; height: ${cropContainer.clientHeight}px !important;
                   transform: translateX(-${cropBoxData.left}px) translateY(-${cropBoxData.top}px) !important;`
        );
        cc.setAttribute(
            'style',
            `width: ${cropContainer.clientWidth}px !important; height: ${cropContainer.clientHeight}px !important;
                   transform: none !important;`
        );
        cci.setAttribute(
            'style',
            `width: ${cropContainer.clientWidth}px !important; height: ${cropContainer.clientHeight}px !important;
                   transform: none !important;`
        );
    };

    // ------------------------------------------------------------------------------------------------------------

    // Get a File object with a dataURL (not used in project yet!)
    const dataURLtoFile = (dataUrl, filename) => {
        let arr = dataUrl.split(','),
            mime = arr[0].match(/:(.*?);/)[1],
            bstr = atob(arr[1]),
            n = bstr.length,
            u8arr = new Uint8Array(n);
        while (n--) {
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new File([u8arr], filename, {type:mime});
    };
}
