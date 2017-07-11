! function ($, window, document, _undefined) {
    // disable in-page filter
    XenForo.FilterListItem.prototype.filter = function (filterRegex) {
        return 1;
    };
}(jQuery, this, document);
