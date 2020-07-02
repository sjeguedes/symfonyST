/**
 *
 * Ajax request ES6 function with Promise.
 *
 * @param {object} configObject - The object to configure an AJAX request
 *
 * @returns {Promise}
 */
export default (configObject) => {
    // Promise basics: https://javascript.info/promise-basics
    // Abort multiple AJAX calls: https://stackoverflow.com/questions/40936321/aborting-multiple-xhr-requests
    // Add abort to AJAX promise: https://stackoverflow.com/questions/32497035/abort-ajax-request-in-a-promise
    // Promise based xhr: http://ccoenraets.github.io/es6-tutorial-data/promisify/
    //                    https://gomakethings.com/promise-based-xhr/
    // ES6 class sample: https://googlechrome.github.io/samples/classes-es6/
    //                   https://medium.com/@dabit3/getting-up-to-speed-with-es6-classes-2382ef3efb9c
    // ES6 Modules export - import: https://alligator.io/js/modules-es6/
    // Cancel promise: https://blog.bloomca.me/2017/12/04/how-to-cancel-your-promise.html
    // Extends javascript object and class: http://phrogz.net/js/classes/ExtendingJavaScriptObjectsAndClasses.html
    // Private properties: https://stackoverflow.com/questions/22156326/private-properties-in-javascript-es6-classes
    // Document class: https://jsdoc.app/howto-es2015-classes.html

    // ------------------------------------------------------------------------------------------------------------

    return new Promise((resolve, reject) => {
        requestWithXHR(configObject, resolve, reject);
    });
}

// ------------------------------------------------------------------------------------------------------------

/**
 * Class which contains a AJAX request Promise.
 */
export class AjaxPromiseLoader {

    /**
     * Create a AJAX promise loader instance.
     *
     * @param {object} configObject - The object to configure an AJAX request
     */
    constructor(configObject) {
        this.resolve = null;
        this.reject = null;
        this.cancelled = false;
        this.object = configObject;
    }

    /**
     * Cancel a promise instance.
     */
    cancelPromise() {
        this.cancelled = true;
        this.reject({ reason: 'cancelled' });
        this.abortXHR();
    }

    /**
     * Get AJAX Promise instance.
     *
     * @returns {Promise}
     */
    getPromise() {
        return new Promise((resolve, reject) => {
            this.resolve = resolve;
            this.reject = reject;
            requestWithXHR(this.object, resolve, reject);
        });
    }

    /**
     * Get xmlHttpRequest instance.
     *
     * @returns {*|XMLHttpRequest}
     */
    getXHR() {
        return this.object.getXHR();
    }

    /**
     * Abort AJAX request to be called outside function if necessary.
     */
    abortXHR() {
        try {
            let xhr = this.getXHR();
            if (xhr && xhr.readyState !== 4) {
                xhr.abort();
            }
        } catch (e) {
            console.error('Exception thrown: ', e);
        }
    }
}

// ------------------------------------------------------------------------------------------------------------

/**
 * Send an AJAX request and manage response.
 *
 * @param {object} configObject - The object to configure an AJAX request
 * @param {function} resolve
 * @param {function} reject
 */
const requestWithXHR = (configObject, resolve, reject) => {
    let xhr = new XMLHttpRequest();
    let obj = configObject;
    xhr.open(obj.method || "GET", obj.url, obj.async || false);
    obj.getXHR = () => {
        return xhr;
    };
    if (obj.overrideMimeType) {
        xhr.overrideMimeType(obj.overrideMimeType);
    }
    if (obj.responseType) {
        xhr.responseType = obj.responseType;
    }
    if (obj.withCredentials) {
        xhr.withCredentials = obj.withCredentials;
    }
    if (obj.headers) {
        Object.keys(obj.headers).forEach(key => {
            xhr.setRequestHeader(key, obj.headers[key]);
        });
    }
    // File upload context
    if (obj.onUploadContextFunction) {
        obj.onUploadContextFunction(xhr);
    }
    // Custom functions for loader:
    if (obj.onProgressFunction) {
        // Custom loader
        xhr.onprogress = event => {
            obj.onProgressFunction(xhr, event); // call .apply(args) with arguments
        };
    }
    if (obj.onLoadStartFunction) {
        xhr.onloadstart = event => {
            obj.onLoadStartFunction(xhr, event); // call .apply(args) with arguments
        };
    }
    if (obj.onLoadEndFunction) {
        xhr.onloadend = event => {
            obj.onLoadEndFunction(xhr, event); // call .apply(args) with arguments
        };
    }
    // Manage error
    xhr.onerror = () => {
        reject(xhr);
    };
    // Manage response
    xhr.onload = xhr.onreadystatechange = () => {
        if (xhr && xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(xhr.response);
            } else {
                reject(xhr);
            }
            xhr = null;
        }
    };
    // Send request
    xhr.send(obj.body !== undefined ? obj.body : null);
};
