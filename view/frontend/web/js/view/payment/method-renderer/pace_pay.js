/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
  "jquery",
  "Magento_Checkout/js/view/payment/default",
  "Magento_Checkout/js/model/payment/additional-validators",
  "Magento_Checkout/js/model/quote",
  "Magento_Checkout/js/model/resource-url-manager",
  "Magento_Checkout/js/model/error-processor",
  "mage/storage",
  "mage/url",
], function (
  $,
  Component,
  additionalValidators,
  quote,
  resourceUrlManager,
  errorProcessor,
  storage,
  url
) {
  "use strict";
  var pacePay;
  return Component.extend({
    redirectAfterPlaceOrder: false,

    defaults: {
      template: "Pace_Pay/payment/form",
    },

    initialize: function () {
      this._super();
      // self = this;
      this.defaultPlaceOrder = this.placeOrder;
    },

    initObservable: function () {
      this._super();
      return this;
    },

    getCode: function () {
      return "pace_pay";
    },

    getData: function () {
      return {
        method: this.item.method,
        additional_data: {},
      };
    },

    loadPacePayJS: function () {
      var baseWidgetConfig = {};
      try {
        baseWidgetConfig = JSON.parse(
          window.checkoutConfig.payment.pace_pay.baseWidgetConfig
        );
      } catch (error) {
        baseWidgetConfig = {};
      }

      var baseWidgetStyles = {};
      if (baseWidgetConfig) {
        baseWidgetStyles = baseWidgetConfig.styles
          ? baseWidgetConfig.styles
          : {};
      }

      var isPlayground =
        window.checkoutConfig.payment.pace_pay.apiEnvironment === "playground";
      var pacePayRequireKey = isPlayground ? "pacePayPlayground" : "pacePay";
      require([pacePayRequireKey], function (requiredPacePay) {
        pacePay = requiredPacePay.init({
          styles: baseWidgetStyles,
        });

        $("#pace-pay-submit-button").attr("disabled", false);

        storage
          .get(resourceUrlManager.getUrlForCartTotals(quote), false)
          .done(function (response) {
            var checkoutWidgetConfig = {};

            try {
              checkoutWidgetConfig = JSON.parse(
                window.checkoutConfig.payment.pace_pay.checkoutWidgetConfig
              );
            } catch (error) {
              checkoutWidgetConfig = {};
            }

            var checkoutWidgetStyles = {};
            if (checkoutWidgetConfig) {
              checkoutWidgetStyles = checkoutWidgetConfig.styles
                ? checkoutWidgetConfig.styles
                : {};
            }
            $("#pace-pay-container").attr(
              "data-price",
              response.base_grand_total
            );
            pacePay.loadWidgets({
              containerSelector: "#pace-pay-container",
              type: "checkout",
              styles: checkoutWidgetStyles,
            });
          })
          .fail(function (response) {
            return false;
          });
      });
    },

    placeOrder: function (data, event) {
      pacePay.showProgressModal();
      var self = this;

      if (event) {
        event.preventDefault();
      }

      if (
        this.validate() &&
        additionalValidators.validate() &&
        this.isPlaceOrderActionAllowed() === true
      ) {
        this.isPlaceOrderActionAllowed(false);

        this.getPlaceOrderDeferredObject()
          .done(function () {
            self.afterPlaceOrder();

            if (self.redirectAfterPlaceOrder) {
              redirectOnSuccessAction.execute();
            }
          })
          .fail(function () {
            pacePay.hideProgressModal();
            errorProcessor.process(
              "Sorry something went wrong.",
              this.messageContainer
            );
          })
          .always(function () {
            self.isPlaceOrderActionAllowed(true);
          });

        return true;
      }

      return false;
    },

    afterPlaceOrder: function (event) {
      $("#pace-pay-submit-button").attr("disabled", true);
      this.isPlaceOrderActionAllowed(true);

      if (event) {
        event.preventDefault();
      }

      var createTransactionUrl = url.build("pace_pay/pace/createtransaction");
      var verifyTransactionUrl = url.build("pace_pay/pace/verifytransaction");
      var cartUrl = url.build("/checkout/cart");

      function verifyTransaction() {
        window.location.replace(verifyTransactionUrl);
      }

      $.post(createTransactionUrl, "json")
        .done(function (response) {
          if (response.token === undefined) {
            window.location.replace(cartUrl);
          }

          var payWithPaceMode =
            window.checkoutConfig.payment.pace_pay.payWithPaceMode;

          var token = response.token;
          if (payWithPaceMode === "popup") {
            pacePay.popup({
              txnToken: token,
              onSuccess: function () {
                verifyTransaction();
              },
              onCancel: () => {
                verifyTransaction();
              },
              onLoad: () => {
                // Hide loader
                pacePay.hideProgressModal();
              },
            });
          } else {
            pacePay.redirect(token);
          }
        })
        .fail(function () {
          window.location.replace(cartUrl);
        });
    },
  });
});
