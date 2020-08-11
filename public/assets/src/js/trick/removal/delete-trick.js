import deleteEntity from '../../all/delete-entity';
export default (referenceElementToScroll = null, adjustYPosition = 0) => {
    deleteEntity('trick', referenceElementToScroll, adjustYPosition);
}
