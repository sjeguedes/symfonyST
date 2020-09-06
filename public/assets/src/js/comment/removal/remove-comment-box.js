export default (removeCommentButtonElement) => {
    let targetedElementId = removeCommentButtonElement.getAttribute('id');
    let matches = targetedElementId.match(/^st-delete-comment-(\d+)$/i);
    let commentKey = matches[1];
    let commentBoxElement = document.getElementById(`st-comment-${commentKey}`);
    // Remove corresponding comment box.
    // Look at "removeCommentButtonElement" click event listener in single.js!
    if (commentBoxElement !== null) {
        commentBoxElement.remove();
    }
};
