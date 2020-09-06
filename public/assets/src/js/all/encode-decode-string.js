export default () => {
    // Resources:
    // Match multiple occurrences:
    // https://stackoverflow.com/questions/520611/how-can-i-match-multiple-occurrences-with-a-regex-in-javascript-similar-to-phps
    // Regexp and unicode issues:
    //https://stackoverflow.com/questions/280712/javascript-unicode-regexes
    // https://flaviocopes.com/javascript-unicode/
    // https://dmitripavlutin.com/what-every-javascript-developer-should-know-about-unicode/
    // List of unicode chars:
    // https://en.wikipedia.org/wiki/List_of_Unicode_characters

    // ------------------------------------------------------------------------------------------------------------

    // Object which Handles "|e('html_attr')" PHP Twig escaping on string
    // https://github.com/twigphp/Twig/blob/3.x/src/Extension/EscaperExtension.php
    const htmlAttributeOnString = {
        patternToExclude: /[&]/gm,
        /**
         * Escape a string like e('html_attr') escaping PHP Twig function.
         *
         * @param {String} string String with unescaped HTML characters
         **/
        escape: string => {
            // Encode string with html special chars entities but not ampersand (&)!
            string = htmlSpecialCharsOnString.encode(string, htmlAttributeOnString.patternToExclude);
            // This will Find hexadecimal/decimal/ entities in string.
            let regExp = /(&#?[0-9a-z]*;)/gi;
            let indexes = [];
            let isIndexToExclude = false;
            let buffer = [];
            // https://stackoverflow.com/questions/2295657/return-positions-of-a-regex-match-in-javascript
            // https://stackoverflow.com/questions/6323417/regex-to-extract-all-matches-from-string-using-regexp-exec
            const findAndKeepMatchesAsNeeded = (regex, str, matches = []) => {
                const res = regex.exec(str);
                res && matches.push(res) && indexes.push([res.index, res.index + res[0].length - 1]) && findAndKeepMatchesAsNeeded(regex, str, matches);
                return matches;
            };
            // Create an Array of indexes thanks to clever function!
            findAndKeepMatchesAsNeeded(regExp, string);
            let positionIntervalsToExclude = indexes;
            let characterIndexesToKeep = [];
            let intervalsArrayLength = positionIntervalsToExclude.length;
            // Loop on each string character
            for (let i = 0; i < string.length; i ++) {
                if (intervalsArrayLength > 0) {
                    for (let pi = 0; pi < intervalsArrayLength; pi ++) {
                        isIndexToExclude = i >= positionIntervalsToExclude[pi][0] && i <= positionIntervalsToExclude[pi][1];
                        if (false === isIndexToExclude) {
                            //characterIndexesToKeep.splice(ci, 1);
                            if (pi === intervalsArrayLength - 1) {
                                // Index must not be excluded!
                                characterIndexesToKeep.push(i);
                            }
                        } else {
                            // We are sure not to keep this index, so stop to loop in intervals to exclude for current "ci" index!
                            break;
                        }
                    }
                } else {
                    characterIndexesToKeep.push(i);
                }
            }
            // Loop on each string character a second time
            for (let i2 = string.length - 1; i2 >= 0; i2 --) {
                // Current character is already checked: do not check necessary exclusion conditions
                if (characterIndexesToKeep.indexOf(i2) !== -1) {
                    // Encode the character if it is included in allowed characters to encode
                    if (/(?=.*[^a-zA-Z0-9,.\-_])/.test(string[i2])) {
                        let hex = string[i2].charCodeAt(0).toString(16).toUpperCase();
                        // Add needed leading '0' for all character codes smaller than 16, or 10 in hex
                        // https://mathiasbynens.be/notes/javascript-escapes
                        if (1 === hex.length) {
                            hex = '0' + hex;
                        }
                        buffer.unshift(['&#x', hex, ';'].join(''));
                        // None of the conditions is true and character must not be encoded.
                    } else {
                        buffer.unshift(string[i2]);
                    }
                } else {
                    buffer.unshift(string[i2]);
                }
            }
            // Get reformed string
            let result = buffer.join('');
            // Find " (double quot) &quot; special char entity in text between html tags, and replace its ampersand (&) and (;) by its special chars entities (&amp; and hex value &#x3B;)
            result = result.replace(/(?:&gt;)((?!(&lt;)).)+(&quot;)((?!(&gt;)).)+(?=&lt;)/gm, (match, g1, g2, g3, offset, string) => {
                // positive and negative look behind are not supported until ES2018:
                // so positive look behind (?<=&gt;) at the beginning can be replaced by a non capturing group (?:&gt;) to delete this expression with two steps!
                match = match.replace(new RegExp('^(&gt;)', 'gm'), '');
                // RegExp object with "g" flag is mandatory to replace all the occurrences in string, and not only the first match.
                return match.replace(new RegExp('&quot;', 'gm'), '&amp;quot&#x3B;');
            });
            // Find " (opening and ending tag characters <>) &lt; and &gt; special char entity in text inside html tags attributes, and replace its ampersand (&) and (;) by its special chars entities (&amp; and hex value &#x3B;)
            result = result.replace(/(&#x3D;&quot;).*?(&quot;(&#x20)?)|(&quot;(&#x20)?&gt;)/gm, (match, g1, g2, g3, offset, string) => {
                // RegExp object with "g" flag is mandatory to replace all the occurrences in string, and not only the first match.
                return match.replace(new RegExp('&(lt|gt);', 'gm'), '&amp;$1&#x3B;');
            });
            // Get final result after multiple replaces
            return result;
        },
        /**
         * Unescape a string converted like e('html_attr')  PHP Twig function escaping.
         *
         * @param {String} string String with escaped HTML characters
         **/
        unescape: string => {
            // Decode html special chars entities
            string = htmlSpecialCharsOnString.decode(string);
            // and then decode hexadecimal html entities
            return htmlEntitiesOnString.decodeHex(string);
        },
    };
    // Object which Handles html special chars encode/decode on string
    //https://www.w3schools.com/html/html_entities.asp
    const htmlSpecialCharsOnString = {
        specialChars: [
            ['\'', '&apos;'], // single quotation mark (apostrophe)
            ['"', '&quot;'], // double quotation mark
            ['>', '&gt;'],
            ['<', '&lt;'],
            [ '&', '&amp;']
        ],
        /**
         * Converts a string to its special chars html characters completely.
         *
         * @param {String} string String with unescaped html special characters
         * @param {RegExp} excluded RegExp with characters to exclude
         **/
        encode: (string, excluded = null) => {
            // Our finalized string will start out as a copy of the initial string.
            let escapedString = string;
            // Apply replacement for each of the special characters
            let len = htmlSpecialCharsOnString.specialChars.length;
            for (let x = 0; x < len; x ++) {
                let subject = htmlSpecialCharsOnString.specialChars[x][0];
                let replacement = htmlSpecialCharsOnString.specialChars[x][1];
                // No change if subject matches characters exclusion pattern
                if (excluded !== null && excluded.test(subject)) {
                    replacement = subject;
                }
                // Replace all instances of the entity with the special character
                escapedString = escapedString.replace(new RegExp(subject, 'g'), replacement);
            }
            // Avoid issue with string escaped twice (e.g. when it was first escaped with PHP)
            escapedString = escapedString.replace(/&amp;/gi, '&');
            // Return the escaped string.
            return escapedString;
        },
        /**
         * Converts html special chars characters into original characters.
         *
         * @param {String} string html special chars entities
         **/
        decode: string => {
            // Our finalized string will start out as a copy of the initial string.
            let unescapedString = string;
            // Apply replacement for each of the special characters
            let len = htmlSpecialCharsOnString.specialChars.length;
            for (let x = 0; x < len; x ++) {
                // Replace all instances of the entity with the special character
                unescapedString = unescapedString.replace(
                    new RegExp(htmlSpecialCharsOnString.specialChars[x][1], 'g'),
                    htmlSpecialCharsOnString.specialChars[x][0]
                );
            }
            // Return the unescaped string.
            return unescapedString;
        }
    };
    // Object which Handles html entities encode/decode on string
    const htmlEntitiesOnString = {
        /**
         * Converts a string to its decimal html characters completely.
         *
         * @param {String} string String with unescaped html characters
         **/
        encodeDec: string => {
            let buffer = [];
            for (let i = string.length - 1; i >= 0; i --) {
                buffer.unshift(['&x', string[i].charCodeAt(0), ';'].join(''));
            }
            return buffer.join('');
        },
        /**
         * Converts an decimal html characterSet into its original character.
         *
         * @param {String} string htmlSet entities
         **/
        decodeDec: string => {
            return string.replace(/&#(\d+);/g, (match, dec) => {
                return String.fromCharCode(dec);
            });
        },
        /**
         * Converts a string to its decimal html characters completely.
         *
         * @param {String} string String with unescaped HTML characters
         **/
        encodeHex: (string) => {
            let buffer = [];
            for (let i = string.length - 1; i >= 0; i --) {
                // Encode characters if they are not part of html special chars entities
                if (/(?=.*[^a-zA-Z0-9,.\-_])(?!&(quot)|(gt)|(lt)|(amp);)/gm.test(string[i])) {
                    let hex = string[i].charCodeAt(0).toString(16).toUpperCase();
                    // Add needed leading '0' for all character codes smaller than 16, or 10 in hex
                    // https://mathiasbynens.be/notes/javascript-escapes
                    if (1 === hex.length) {
                        hex = '0' + hex;
                    }
                    buffer.unshift(['&#x', hex, ';'].join(''));
                } else {
                    buffer.unshift(string[i]);
                }
            }
            return buffer.join('');
        },
        /**
         * Converts an hexadecimal html characterSet into its original character.
         *
         * @param {String} string hexadecimal htmlSet entities
         **/
        decodeHex: string => {
            return string.replace(/&#x([a-fA-F0-9]+);/g, (match, hex) => {
                let num = parseInt(hex, 16);
                return String.fromCharCode(num);
            });
        }
    };
    // Object which Handles format on string
    const formatOnString = {
        /**
         * Converts a new line in text format into a <br> html tag
         * with unicode support.
         *
         * Please note this version also creates <br> after tag:
         * (string + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/gi, '$1' + breakTag + '$2');
         *
         * @param {String} string which contains new lines
         * @param {Boolean} isXhtml mode or html
         *
         * @see https://stackoverflow.com/questions/7467840/nl2br-equivalent-in-javascript
         **/
        nl2br: (string, isXhtml = false) => {
            if (typeof string === 'undefined' || string === null) {
                return '';
            }
            let breakTag = (isXhtml) ? '<br />' : '<br>';
            return  (string + '').replace(/([\r\n]?)/, '')
                                 .replace(/(\n)/gi, breakTag)
                                 .replace(/(\\n)/gi, breakTag);
        },
        /**
         * Converts a encapsulating common double quote pair in text format into a <strong> html tag
         * with unicode support by accepting Latin-1 Supplement (Unicode block).
         *
         * @param {String} string which contains quotes
         *
         * @see https://stackoverflow.com/questions/7467840/nl2br-equivalent-in-javascript
         **/
        quote2strong: (string) => {
            let p = /(?:"|(?:&quot;))(([&#;<\/>!?\s\u{00A0}-\u{017F}\w-]+)*)(?:"|(?:&quot;))/gu;
            string = string.replace(p, '<strong>' + '$1' + '</strong>');
            return string;
        },
        /**
         * Converts a encapsulating common double quote pair in text format into a <strong> html tag
         * with a primary color for the first matched pair and a secondary color for other pairs.
         *
         * @param {String} string which contains quotes
         *
         * @see https://stackoverflow.com/questions/7467840/nl2br-equivalent-in-javascript
         **/
        quote2strongWith2colors: (string) => {
            let pattern = /("|(?:&quot;))/;
            let matches = string.match(new RegExp(pattern, 'gi'));
            if (matches !== null) {
                let matchLength = matches.length;
                let colorClass;
                // Search for only one occurrence once a time to replace step by step
                let regex = new RegExp(pattern, 'i');
                // Match length is not even! So return string without format
                if (matches.length % 2 !== 0) {
                    return string;
                }
                // Adapt text color with a custom choice between two colors depending on even pairs!
                for (let i = 0; i < matchLength; i ++) {
                    if (i % 2 === 0) {
                        colorClass = matchLength === 2 ? 'st-color-yellow' : i === 0 ? 'st-color-white' : 'st-color-yellow';
                        string = string.replace(regex, '<strong><span class=\'' + colorClass + '\'>');
                    } else {
                        string = string.replace(regex, '</span></strong>');
                    }
                }
            }
            return string;
        }
    };
    return {
        htmlAttributeOnString: htmlAttributeOnString,
        htmlSpecialCharsOnString: htmlSpecialCharsOnString,
        htmlEntitiesOnString: htmlEntitiesOnString,
        formatOnString: formatOnString
    };
}
