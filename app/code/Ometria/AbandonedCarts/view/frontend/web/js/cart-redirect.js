define([
    "jquery",
], function(jQuery){
    "use strict";
    return function(data, domElement){
        setTimeout(function(){        
            var $ = jQuery;
            var storage = $.initNamespaceStorage('mage-cache-storage').localStorage;        
            storage.removeAll();
            var date = new Date(Date.now() + 24 * 1000);
            $.localStorage.set('mage-cache-timeout', date);        
            document.location = data.url;
        }, 1000);
    };
});
