import smoothScroll from '../all/smooth-vertical-scroll';

export default () => {
    // Manage image or video box URI anchor smooth scroll from trick single page
    if (window.location.hash) {
        let hashFromURI = window.location.hash;
        let matches = hashFromURI.match(/^#(image|video)-(\w+)$/i);
        if (matches !== null) {
            let elementId = `st-${matches[1]}-box-${matches[2]}`;
            let referenceElementToScroll = document.getElementById(elementId);
            // "load" event handler to improve reload smooth scroll unnecessary process
            const scrollToMediaBox = () => {
                smoothScroll(referenceElementToScroll, 0);
            };
            scrollToMediaBox();
        }
    }
}
