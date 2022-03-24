define([
    "jquery",
    "Magento_Checkout/js/view/payment/default",
    "Magento_Checkout/js/model/payment/additional-validators",
    "Magento_Checkout/js/model/quote",
    "Magento_Checkout/js/model/resource-url-manager",
    "Magento_Checkout/js/model/error-processor",
    "mage/storage",
    "mage/url",
], function (e, a, t, o, n, c, i, r) {
    "use strict";
    return a.extend({
        redirectAfterPlaceOrder: !1,
        defaults: { template: "Pace_Pay/payment/form" },
        initialize: function () {
            this._super(), (this.defaultPlaceOrder = this.placeOrder);
        },
        initObservable: function () {
            return this._super(), this;
        },
        getCode: function () {
            return "pace_pay";
        },
        getData: function () {
            return { method: this.item.method, additional_data: {} };
        },
        loadPacePayJS: function () {
            var a = {};
            try {
                a = JSON.parse(window.checkoutConfig.payment.pace_pay.baseWidgetConfig);
            } catch (e) {
                a = {};
            }
            a && a.styles && a.styles;
            var t = "playground" === window.checkoutConfig.payment.pace_pay.apiEnvironment;
            require([t ? "pacePayPlayground" : "pacePay"], function (a) {
                function t(a) {
                    if ((e("#pace-pay-submit-button").attr("disabled", !1), a.totals().base_grand_total)) {
                        var t = a.totals().base_grand_total,
                            o = {};
                        try {
                            o = JSON.parse(window.checkoutConfig.payment.pace_pay.checkoutWidgetConfig);
                        } catch (e) {
                            o = {};
                        }
                        a = {};
                        o && (a = o.styles ? o.styles : {}),
                            e("#pace-pay-container").attr("data-price", t),
                            window.pacePayBaseWidgetConfig.baseActive && 1 == window.pacePayCheckoutWidgetConfig.isActive && window.pacePay.loadWidgets({ containerSelector: "#pace-pay-container", type: "checkout", styles: a });
                    }
                }
                o.paymentMethod.subscribe(function (e) {
                    "pace_pay" == e.method && t(o);
                }),
                    o.paymentMethod() && "pace_pay" == o.paymentMethod().method && t(o);
            });
        },
        afterPlaceOrder: function (a) {
            window.pacePay.showProgressModal(), e("#pace-pay-submit-button").attr("disabled", !0), this.isPlaceOrderActionAllowed(!0), a && a.preventDefault();
            var t = r.build("pace_pay/pace/createtransaction"),
                o = r.build("pace_pay/pace/verifytransaction"),
                n = r.build("/checkout/cart");
            function c() {
                window.location.replace(o);
            }
            e.post(t, "json")
                .done(function (e) {
                    void 0 === e.token && window.location.replace(n);
                    var a = window.checkoutConfig.payment.pace_pay.payWithPaceMode,
                        t = e.token;
                    "popup" === a
                        ? window.pacePay.popup({
                              txnToken: t,
                              onSuccess: function () {
                                  c();
                              },
                              onCancel: () => {
                                  c();
                              },
                              onLoad: () => {
                                  window.pacePay.hideProgressModal();
                              },
                          })
                        : window.pacePay.redirect(t);
                })
                .fail(function () {
                    window.location.replace(n);
                });
        },
    });
});
