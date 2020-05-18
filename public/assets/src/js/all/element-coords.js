// Get real offset position for a specific element in document, according to its DOM level
export default element => { // HHTMLNodeElement
    let xPos = 0;
    let yPos = 0;
    // Loop in nodes and add parent positions if element is a child.
    while (element) {
        if ('BODY' === element.tagName) {
            // Deal with browser quirks with body/window/document and page scroll
            let xScroll = element.scrollLeft || document.documentElement.scrollLeft;
            let yScroll = element.scrollTop || document.documentElement.scrollTop;

            xPos += (element.offsetLeft - xScroll + element.clientLeft);
            yPos += (element.offsetTop - yScroll + element.clientTop);
        } else {
            // For all other non-BODY elements
            xPos += (element.offsetLeft - element.scrollLeft + element.clientLeft);
            yPos += (element.offsetTop - element.scrollTop + element.clientTop);
        }
        element = element.offsetParent;
    }
    return {x: xPos, y: yPos};
}


