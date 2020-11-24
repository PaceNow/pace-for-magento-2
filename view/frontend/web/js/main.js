require([], function () {
  var isPlayground = window.pacePayEnvironment === "playground";
  var pacePayRequireKey = isPlayground ? "pacePayPlayground" : "pacePay";
  if (window.pacePaymentPlan && window.pacePaymentPlan.isCurrencySupported) {
    require([pacePayRequireKey], function (requiredPacePay) {
      requiredPacePay = requiredPacePay.init({
        fallbackWidget: window.pacePayBaseWidgetConfig.fallbackWidget,
        minAmount: parseFloat(window.pacePaymentPlan.minAmount),
        maxAmount: parseFloat(window.pacePaymentPlan.maxAmount),
        styles: window.pacePayBaseWidgetConfig.styles
          ? window.pacePayBaseWidgetConfig.styles
          : undefined,
      });

      window.pacePaySingleProductWidgetConfig.isActive &&
        requiredPacePay.loadWidgets({
          containerSelector: ".pace-pay_single-product-widget-container",
          type: "single-product",
          styles: window.pacePaySingleProductWidgetConfig.styles
            ? window.pacePaySingleProductWidgetConfig.styles
            : undefined,
        });

      window.pacePayMultiProductsWidgetConfig.isActive &&
        requiredPacePay.loadWidgets({
          containerSelector: ".pace-pay_multi-products-widget-container",
          type: "multi-products",
          styles: window.pacePayMultiProductsWidgetConfig.styles
            ? window.pacePayMultiProductsWidgetConfig.styles
            : undefined,
        });
    });
  }
});
