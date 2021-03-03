define([
    'jquery'
], function ($) {
    'use strict';

    /**
     * Mixin to trigger an Ometria data layer push when configurable option is selected
     */
    return function (widget) {
        $.widget('mage.configurable', widget, {
            _configureElement: function (element) {
                this._super(element);

                if (this.simpleProduct) {
                    $(document).trigger('updateOmetriaProductVariant', [
                        this.simpleProduct
                    ]);
                }
            }
        });

        return $.mage.configurable;
    }
});
