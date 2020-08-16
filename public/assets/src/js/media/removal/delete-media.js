import deleteEntity from '../../all/delete-entity';
export default (mediaRemovalLink, entityType, referenceElementToScroll = null, adjustYPosition = 0, successCallback = null, args = []) => {
    deleteEntity(mediaRemovalLink, entityType, referenceElementToScroll, adjustYPosition, successCallback, args);
}
