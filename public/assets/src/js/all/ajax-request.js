// Ajax request ES6 function
export default obj => {
    return new Promise((resolve, reject) => {
        let xhr = new XMLHttpRequest();
        xhr.open(obj.method || "GET", obj.url, obj.async || false );
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
        // Custom functions for loader
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
        xhr.onerror = () => reject(xhr);
        xhr.onload = xhr.onreadystatechange = () => {
            if (xhr.readyState === XMLHttpRequest.DONE ) {
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(xhr.response);
                } else {
                    reject(xhr);
                }
            }
        };
        // Send request
        xhr.send(obj.body !== undefined ? obj.body : null);
    });
};
