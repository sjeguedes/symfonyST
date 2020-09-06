import deleteEntity from '../../all/delete-entity';
export default (commentRemovalLink, referenceElementToScroll = null, adjustYPosition = 0, successCallback = null, args = []) => {
    deleteEntity(commentRemovalLink, 'comment', referenceElementToScroll, adjustYPosition, successCallback, args);
}
