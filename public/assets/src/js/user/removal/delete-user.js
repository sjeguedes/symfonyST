import deleteEntity from '../../all/delete-entity';
export default (userRemovalLink, referenceElementToScroll = null, adjustYPosition = 0, successCallback = null, args = []) => {
    deleteEntity(userRemovalLink, 'user', referenceElementToScroll, adjustYPosition, successCallback, args);
}
