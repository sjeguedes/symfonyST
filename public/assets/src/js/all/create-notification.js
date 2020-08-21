import htmlStringHelper from './encode-decode-string';
import UIkit from "../../../uikit/dist/js/uikit.min";
export default (message, groupOption = null, mustFormat = false, status = 'error', icon = 'warning', timeout = 5000) => {
    // Create common notification
    // Escape html message
    // String helper
    if (mustFormat) {
        const htmlStringHandler = htmlStringHelper();
        // Escape sensible characters
        message = htmlStringHandler.htmlSpecialCharsOnString.encode(message);
        // Create custom transformation from \n to <br>
        message = htmlStringHandler.formatOnString.nl2br(message);
        // Create custom transformation from "text" to <strong>text</strong>
        // Simple "quote2strong()" method can be used here without colors!
        message = htmlStringHandler.formatOnString.quote2strongWith2colors(message);
    }
    // Cancel previous notification(s) by closing it(them)
    // Use of "closeAll()" method is a tip to avoid notification to be shown multiple times probably
    // due to loop when image crop boxes are used (e.g. trick creation or update).
    UIkit.notification.closeAll(groupOption);
    // Activate new notification
    UIkit.notification({
        message: `<div class="uk-text-center" style="font-size: 95%">
                 <span uk-icon="icon: ${icon}"></span>&nbsp;` + message + `</div>`,
        status: status,
        pos: 'top-center',
        group: groupOption,
        timeout: timeout
    });
}
