export default () => {
    // Object which Handles "URI" encoding on string
    // https://attacomsian.com/blog/javascript-encode-url
    const uriOnString = {
        /**
         * Encode a complete URL.
         *
         * @param {String} URI String with unescaped non URI characters
         **/
        encode: (URI) => {
            return encodeURI(URI);
        },
        /**
         * Encode URI parameter (e.g. a query string).
         *
         * @param {String} queryParam String with unescaped non URI characters
         **/
        encodeParam: (queryParam) => {
            return encodeURIComponent(queryParam);
        },
        /**
         * Encode URI parameter with RFC 3986 standard (e.g. a query string).
         *
         * @param {String} queryParam String with unescaped non URI characters
         **/
        encodeParamWithRFC3986: (queryParam) => {
            return encodeURIComponent(queryParam).replace(/[!'()*]/g, (c) => {
                return '%' + c.charCodeAt(0).toString(16);
            });
        }
        // Object which Handles "URI" decoding on string
        // Decode methods here if needed!
        //https://attacomsian.com/blog/javascript-decode-url
    };
    return {
        uriOnString: uriOnString
    };
};
