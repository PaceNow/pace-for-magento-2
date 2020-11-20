require([], function () {
  var isPlayground = window.pacePayEnvironment === "playground";
  var pacePayRequireKey = isPlayground ? "pacePayPlayground" : "pacePay";
  require([pacePayRequireKey], function (requiredPacePay) {
    if (window.pacePaymentPlan) {
      requiredPacePay = requiredPacePay.init({
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
    }
  });
});
