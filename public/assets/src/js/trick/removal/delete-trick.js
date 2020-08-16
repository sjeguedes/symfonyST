import deleteEntity from '../../all/delete-entity';
export default (trickMediaRemovalLink, referenceElementToScroll = null, adjustYPosition = 0, successCallback = null, args = []) => {
    deleteEntity(trickMediaRemovalLink, 'trick', referenceElementToScroll, adjustYPosition, successCallback, args);
}
