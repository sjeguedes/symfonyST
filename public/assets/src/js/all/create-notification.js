import htmlStringHelper from "./encode-decode-string";
import UIkit from "../../../uikit/dist/js/uikit.min";
export default (message, groupOption = null, mustFormat = false, status = 'error', icon = 'warning', timeout = 5000) => {
    // Create common notification
    // Escape html message
    // String helper
    if (mustFormat) {
        const htmlStringHandler = htmlStringHelper();
        message = htmlStringHandler.htmlSpecialCharsOnString.encode(message);
        message = htmlStringHandler.formatOnString.nl2br(message);
    }
    // Cancel previous notification(s) by closing it(them)
    // Use of "closeAll()" method is a tip to avoid notification to be shown multiple times probably
    // due to loop when image crop boxes are used (e.g. trick creation or update).
    UIkit.notification.closeAll(groupOption);
    // Activate new notification
    UIkit.notification({
        message: `<div class="uk-text-center">
                 <span uk-icon='icon: ${icon}'></span>&nbsp;` + message + `</div>`,
        status: status,
        pos: 'top-center',
        group: groupOption,
        timeout: timeout
    });
}
