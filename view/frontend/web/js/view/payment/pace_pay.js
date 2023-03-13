/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
  'uiComponent',
  'Magento_Checkout/js/model/payment/renderer-list',
], function (Component, rendererList) {
  'use strict';
  !(function (e) {
    if (void 0 != e.isEnable && !e.blacklisted) {
      e.isEnable.isAvailable &&
        rendererList.push({
          type: 'pace_pay',
          component: 'Pace_Pay/js/view/payment/method-renderer/pace_pay',
        });
    }
  })(window.pace);
  /** Add view logic here if needed */
  return Component.extend({});
});
