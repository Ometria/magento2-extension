define([
    'jquery',
    'Magento_Customer/js/customer-data'
], function(
    $,
    customerData
){
    "use strict";

    $.widget('ometria.redirect', {
        options: {
            redirectTimeout: 1000,
            cookieLifeTime: 3600,
            redirectUrl: '/checkout/cart/'
        },

        /**
         * @private
         */
        _create: function () {
            setTimeout(function () {
                this._clearStorage();
                this._invalidateCacheTimeOut();

                if (this.options.redirectUrl) {
                    this._redirect(this.options.redirectUrl);
                }
            }.bind(this), this.options.redirectTimeout);
        },

        /**
         * @private
         */
        _clearStorage: function () {
            var storage = $.initNamespaceStorage('mage-cache-storage').localStorage;
            storage.removeAll();
        },

        /**
         * @private
         */
        _invalidateCacheTimeOut: function () {
            var date = new Date(Date.now() + parseInt(this.options.cookieLifeTime, 10) * 1000);
            $.localStorage.set('mage-cache-timeout', date);
        },

        /**
         * @private
         */
        _redirect: function (redirectUrl) {
            document.location = redirectUrl;
        }
    });

    return $.ometria.redirect;
});
