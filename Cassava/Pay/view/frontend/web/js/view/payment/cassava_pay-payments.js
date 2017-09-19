/**
 * Copyright © 2013-2017 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
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
                type: 'cassava_pay',
                component: 'Cassava_Pay/js/view/payment/method-renderer/cassava_pay-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);