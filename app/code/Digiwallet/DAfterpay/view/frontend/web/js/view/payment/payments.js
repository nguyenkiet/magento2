/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'dafterpay',
                component: 'Digiwallet_DAfterpay/js/view/payment/method-renderer/method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);