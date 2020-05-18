import Cropper from 'cropperjs/dist/cropper.min';
//import canvasToBlob from './polyfill-canvas-to-blob';
import htmlStringHelper from './encode-decode-string';
import URIHelper from './encode-decode-uri';
import UIkit from "../../../uikit/dist/js/uikit.min";
import smoothScroll from "./smooth-vertical-scroll";
import {removeAttr} from "../../../uikit/src/js/util";
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

    // ------------------------------------------------------------------------------------------------------------

    // Retrieve last selected file to upload
    let file = cropParams.fileInputElement.files[cropParams.fileInputElement.files.length - 1];
    // Set UIkit notification group
    let groupOption = cropParams.notificationGroup !== undefined ? cropParams.notificationGroup : null;

    // ------------------------------------------------------------------------------------------------------------

    // Create common notification
    const createNotification = (message, status = 'error', icon = 'warning', timeout = 5000) => {
        // Escape html message
        // String helper
        const htmlStringHandler = htmlStringHelper();
        message = htmlStringHandler.htmlSpecialCharsOnString.encode(message);
        message = htmlStringHandler.formatOnString.nl2br(message);
        // Cancel previous notification(s) by closing it(them)
        // Use of "closeAll()" method is a tip to avoid notification to be shown multiple times probably
        // due to loop when image crop boxes are used (e.g. trick creation or update).
        UIkit.notification.closeAll(groupOption);
        // Activate new notification
        UIkit.notification({
            message: `<div class="uk-text-center">
                     <span uk-icon='icon: ${icon}'></span>&nbsp;` + message + `</div>`,
            status: status,
            pos: 'top-center',
            group: groupOption,
            timeout: timeout
        });
    };

    // ------------------------------------------------------------------------------------------------------------

    // Check validity of file
    // Allow only image of these types provided by file input selection or change
    const isFileRefused = file => {
        // Update (reset) error state to use it in scripts if necessary
        cropParams.errors.unCropped = false;
        // WARNING: "file" variable is the last uploaded file but crop process must be adapted to be functional with multiple upload!
        if (false === file || false === /^image\/(pjpeg|jpeg|png|gif)$/gi.test(file.type)) {
            // Inform user with notification
            createNotification(cropParams.fileInputElement.getAttribute('data-error'));
            // Update error state to use it in scripts
            cropParams.errors.unCropped = true;
            // Stop crop action in this case
            return true;
        }
        return false;
    };
    // Prevent crop process by not showing modal and un-instantiating a cropper object
    if (isFileRefused(file)) {
        //TODO: delete this line below which causes an issue!
        //cropParams.fileInputElement.files.splice(cropParams.fileInputElement.files.length - 1, 1);
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
            createNotification(errorMessage);
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

        // Callback before modal is shown, to fix a container generated twice after multiple changes
        UIkit.util.on(modalElementID, 'beforeshow', () => {
            let container = cropParams.modalElement.querySelector('.cropper-container');
            if (container !== null) {
                container.parentElement.removeChild(container);
            }
        });

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
            cropParams.modalElement.currentFilename = uriStringHandler.uriOnString.encodeParamWithRFC3986(file.name);
            // Will check resize event
            let currentCropBoxData = {};
            let data = {};
            let lastValidPosition = {x: 0, y: 0};
            let state = {
                counter: 1,
                isAutoCrop: false,
                isCropped: false,
                isCropValid: true,
                isWindowResized: false,
                firstLoad: false
            };
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
                //autoCropArea: params.autoCropArea,
                initialAspectRatio: params.ratio,
                aspectRatio: params.ratio,
                imageSmoothingQuality: 'high',
                movable: params.movable,
                rotatable: params.rotatable,
                scalable: params.scalable,
                zoomable: params.zoomable,
                // CAUTION: avoid their use alone or with auto crop, to keep control on crop box position (or even size) on window resize!
                // Do not limit crop box minimum dimensions not to have issue and since it is made in "crop" event callback!
                /*minCropBoxWidth: 0,
                minCropBoxHeight: 0,*/
                ready: () => {
                    state.firstLoad = true;
                    // Define cropper for image element to use it easily in handlers functions
                    cropParams.modalElement.cropper = newCropper;
                    // Hide crop box when ready for first load to improve appearance effect due to repositioning
                    cropParams.modalElement.querySelector('.cropper-crop-box').classList.add('uk-hidden');
                    // Get crop box maximum size corresponding to defined ratio as default size
                    currentCropBoxData = renderDefaultCropBoxWithMinMaxsize(newCropper, currentCropBoxData, true);
                    // Set defined crop box with default values (call "crop" event)
                    newCropper.setCropBoxData(currentCropBoxData);
                    // This is used to avoid crop box data modification during "auto crop" event on first load
                    newCropper.element.addEventListener('crop', () => {
                        state.isAutoCrop = true;
                        if (state.firstLoad) {
                           renderDefaultCropBoxWithMinMaxsize(newCropper, currentCropBoxData, true);
                        }
                        clearTimeout(newCropper.element.autoCropFinished);
                        // End of "auto crop" event
                        newCropper.element.autoCropFinished = setTimeout(() => {
                            state.isAutoCrop = false;
                            // Initial crop box data setting above calls this twice!
                            if (state.firstLoad && state.counter === 2) {
                                renderDefaultCropBoxWithMinMaxsize(newCropper, currentCropBoxData, true);
                                // Show crop box for first load after repositioning
                                cropParams.modalElement.querySelector('.cropper-crop-box').classList.remove('uk-hidden');
                            }
                            // Do not use counter after 2 iterations
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
                    // Crop box is handled for the first time
                    if (state.firstLoad) {
                        state.firstLoad = false;
                    }
                },
                // resize end check: https://stackoverflow.com/questions/5489946/how-to-wait-for-the-end-of-resize-event-and-only-then-perform-an-action
                crop: event => {
                    // Avoid infinite loop with crop end callback
                    if (state.isCropped) {
                        state.isCropped = false;
                        return;
                    }
                    // Get last data object
                    data = newCropper.getData();
                    // Fix data if they are under minimum size to limit crop box
                    if (Math.ceil(event.detail.width) < params.minCropBoxWidth || Math.ceil(event.detail.height) < params.minCropBoxHeight) {
                        state.isCropValid = false;
                        // Improve position effect
                        data.x = event.detail.x + (lastValidPosition.x !== 0 ? (lastValidPosition.x - event.detail.x) : 0);
                        data.y = event.detail.y + (lastValidPosition.y !== 0 ? (lastValidPosition.y - event.detail.y) : 0);
                        // Set minimum dimensions
                        data.width = params.minCropBoxWidth;
                        data.height = params.minCropBoxHeight;
                        // Re-create min crop box size options with the right visual effect thanks to style
                        let container = cropParams.modalElement.querySelector('.st-cropper-container-content');
                        let left = Math.round(data.x * container.clientWidth / newCropper.element.width);
                        let top =  Math.round(data.y * container.clientHeight / newCropper.element.height);
                        let width = Math.round(data.width * container.clientWidth / newCropper.element.width);
                        let height = Math.round(data.height * container.clientHeight / newCropper.element.height);
                        // Get concerned elements
                        let cb = cropParams.modalElement.querySelector('.cropper-crop-box');
                        let cvb = cropParams.modalElement.querySelector('.cropper-view-box img');
                        cb.setAttribute(
                            'style',
                             `width: ${width}px; height: ${height}px;
                                   transform: translateX(${left}px) translateY(${top}px) !important;`
                        );
                        cvb.setAttribute(
                            'style',
                            `width: ${container.clientWidth}px; height: ${container.clientHeight}px;
                                   transform: translateX(-${left}px) translateY(-${top}px) !important;`
                        );
                    } else {
                        state.isCropValid = true;
                        lastValidPosition.x = event.detail.x;
                        lastValidPosition.y = event.detail.y;
                        data.x = lastValidPosition.x;
                        data.y = lastValidPosition.y;
                    }
                    // Call "cropend" callback manually when crop ended to limit crop box size visually
                    clearTimeout(newCropper.cropFinished);
                    newCropper.cropFinished = setTimeout(() => {
                        newCropper.options.cropend();
                    }, 250);
                },
                cropend: () => {
                    if (!state.isCropValid) {
                        // Inform user with notification (Minimum crop box size is reached!)
                        createNotification(cropParams.fileInputElement.getAttribute('data-error-5'), 'info', 'info', 2500);
                        state.isCropValid = true;
                    }
                    newCropper.setData(data);
                    state.isCropped = true;
                }
            });
        });

        // ------------------------------------------------------------------------------------------------------------

        // Callback when modal is hidden
        UIkit.util.on(modalElementID, 'hidden', () => {
            // Destruct Cropper instance when modal is definitively hidden to enable next update
            newCropper.destroy();
            // CAUTION - tricky tip: reset image preview object to reinitialize cropper correctly between two successive uploads
            cropParams.previewElement = new Image(); // cropParams.previewElement.src = ''; can be used instead!
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
        // Inform user with notification: image will not be validated on server side and constraints violations will be shown!
        createNotification(cropParams.fileInputElement.getAttribute('data-error-4'), 'info', 'info');
        // Reset crop data elements (will be possible constraints violations) to avoid exception on server side
        resetCropDataElements();
    };

    // ------------------------------------------------------------------------------------------------------------

    // Close button event listener handler
    const cropHandler = event => {
        let newCropBoxData,
            newCanvasData,
            Base64ImagePreviewDataURI;
        // IMPORTANT: This stores file.name which is not accessible in this event listener handler
        // https://stackoverflow.com/questions/256754/how-to-pass-arguments-to-addeventlistener-listener-function
        let newFilename = cropParams.modalElement.currentFilename;
        // Get crop box and canvas data
        newCropBoxData = cropParams.modalElement.cropper.getCropBoxData();
        newCanvasData = cropParams.modalElement.cropper.getCanvasData();
        // Should set crop box data first here
        cropParams.modalElement.cropper.setCropBoxData(newCropBoxData).setCanvasData(newCanvasData);
        // Get cropped data to feed hidden particular field to effectively crop image on server-side
        let cropData = cropParams.modalElement.cropper.getData();
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
        Base64ImagePreviewDataURI = cropParams.modalElement.cropper.getCroppedCanvas({width: params.previewWidth, height: params.previewHeight}).toDataURL(file.type);
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
    const renderDefaultCropBoxWithMinMaxsize = (newCropper, currentCropBoxData, maxSize = false) => {
        // Get real container and concerned elements
        let container = cropParams.modalElement.querySelector('.cropper-container');
        let cb = cropParams.modalElement.querySelector('.cropper-crop-box');
        let cvb = cropParams.modalElement.querySelector('.cropper-view-box img');
        // Retrieve maximum size by calculation
        let width = container.clientWidth;
        let realHeight = Math.round(newCropper.element.width * params.minCropBoxHeight / params.minCropBoxWidth);
        let height = Math.round(realHeight * container.clientHeight / newCropper.element.height);
        // Switch to these lines below to get crop box minimum size as default size (can also be used for options settings)
        if (!maxSize) {
            width = Math.round(params.minCropBoxWidth * container.clientWidth / uploadedImage.width);
            height = Math.round(params.minCropBoxHeight * container.clientHeight / uploadedImage.height);
        }
        // Prepare crop box data
        currentCropBoxData = {
            left: (container.clientWidth - width) / 2,
            top: (container.clientHeight - height) / 2,
            width: width,
            height: height
        };
        // Set defined crop box visually with default values (call "crop" event)
        cb.setAttribute(
            'style',
            `width: ${currentCropBoxData.width}px !important; height: ${currentCropBoxData.height}px !important;
                   transform: translateX(${currentCropBoxData.left}px) translateY(${currentCropBoxData.top}px) !important;`
        );
        cvb.setAttribute(
            'style',
            `width: ${container.clientWidth}px !important; height: ${container.clientHeight}px !important;
                   transform: translateX(-${currentCropBoxData.left}px) translateY(-${currentCropBoxData.top}px) !important;`
        );
        return currentCropBoxData;
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
