define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'mage/url',
], function(
    $,
    Default,
    Quote,
    Url
) {
    'use strict';
    return Default.extend({
        redirectAfterPlaceOrder: !1,
        defaults: { template: 'Pace_Pay/payment/form' },
        initialize: function() {
            this._super(), (this.defaultPlaceOrder = this.placeOrder);
        },
        initObservable: function() {
            return this._super(), this;
        },
        getCode: function() {
            return this.item.method;
        },
        getData: function() {
            return { method: this.item.method, additional_data: {} };
        },
        loadPacePayJS: function() {
            var mode = /playground/.test(window.pace.mode) ?
                'pacePayPlayground' :
                'pacePay';

            require([mode], m => {
                const t = a => {
                    var price = a.totals().base_grand_total;

                    if (price && parseFloat(price) > 0 && (window.activeCheckout || (window.activeCheckout = window.pace.checkoutSetting.isActive))) {
                        var c = document.getElementById('pace-pay-container');
                        c && c.setAttribute('data-price', price), window.pacePay.loadWidgets({ containerSelector: "#" + c.id, styles: window.pace.checkoutSetting.styles, type: 'checkout' });
                    }

                    document.getElementById('pace-pay-submit-button').removeAttribute('disabled');
                };
                Quote.paymentMethod.subscribe(e => {
                        this.getCode() == e.method && t(Quote);
                    }),
                    Quote.paymentMethod() && this.getCode() == Quote.paymentMethod().method && t(Quote);
            });
        },
        afterPlaceOrder: function(a) {
            window.pacePay.showProgressModal(),
                document.getElementById('pace-pay-submit-button').setAttribute('disabled', !0),
                this.isPlaceOrderActionAllowed(!0),
                a && a.preventDefault();

            var o = Url.build('pace_pay/pace/verifytransaction'),
                n = Url.build('checkout/cart');

            const c = e => {
                window.location.replace(e);
            }

            $.post(Url.build('pace_pay/pace/createtransaction'), 'json')
                .done(function(e) {
                    var t;
                    void 0 === (t || (t = e.token)) && c(n);
                    /popup/.test(window.pace.paymentMode) ?
                        window.pacePay.popup({
                            txnToken: t,
                            onSuccess: () => {
                                c(o);
                            },
                            onCancel: () => {
                                c(o);
                            },
                            onLoad: () => {
                                window.pacePay.hideProgressModal();
                            },
                        }) :
                        window.pacePay.redirect(t);
                })
                .fail(function() {
                    c(n);
                });
        },
    });
});
