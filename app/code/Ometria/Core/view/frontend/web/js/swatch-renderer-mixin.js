define([
    'jquery'
], function ($) {
    'use strict';

    /**
     * Mixin to trigger an Ometria data layer push when swatches are selected
     */
    return function (widget) {
        $.widget('mage.SwatchRenderer', widget, {
            _OnClick: function ($this, $widget) {
                this._super($this, $widget);

                var simpleProduct = this.getProduct();

                if (simpleProduct) {
                    $(document).trigger('updateOmetriaProductVariant', [
                        simpleProduct
                    ]);
                }
            }
        });

        return $.mage.SwatchRenderer;
    }
});
