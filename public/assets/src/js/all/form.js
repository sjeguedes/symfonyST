import smoothScroll from './smooth-vertical-scroll';
export default () => {
    // Scroll to form automatically when a form is not valid after submission.
    const formElement = document.getElementById('st-form');
    if (formElement) {
        const isError = formElement.classList.contains('st-form-error');
        if (isError) {
            smoothScroll(formElement.parentElement.parentElement.parentElement, 0); // #st-update-profile-form, #st-login-form ...
        }
    }
}
