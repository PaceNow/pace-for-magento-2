## Structure

```
.
├── Block
│   ├── Adminhtml
│   │   └── System
│   │       └── Config
│   │           ├── Label.php - to supply version of the module for display in Pace's system configuration.
│   │           └── PaymentPlan.php - to provide view/adminhtml/templates/system/config/paymentplan.phtml with data.
│   ├── Info.php
│   ├── PaceJS.php - to provide view/frontend/templates/pacejs.phtml with data.
│   └── SingleProductWidget.php - to provide view/frontend/templates/singleproductwidget.phtml with data.
├── Controller
│   ├── Adminhtml
│   │   └── System
│   │       └── Config
│   │           └── RefreshPaymentPlans.php - for refreshing of the payment plan based on Pace's current system config.
│   └── Pace
│       ├── CreateTransaction.php
│       ├── Transaction.php
│       └── VerifyTransaction.php
├── Cron
│   ├── RefreshPaymentPlans.php
│   └── VerifyTransaction.php
├── Gateway
│   ├── Http
│   │   ├── Client
│   │   │   ├── ClientMock.php
│   │   │   └── EmptyClient.php
│   │   ├── EmptyTransferFactory.php
│   │   ├── PayJsonConverter.php
│   │   └── TransferFactory.php
│   ├── Request
│   │   ├── AuthorizationRequest.php
│   │   ├── CaptureRequest.php
│   │   ├── InitRequest.php
│   │   ├── MockDataRequest.php
│   │   └── VoidRequest.php
│   ├── Response
│   │   ├── FraudHandler.php
│   │   └── TxnIdHandler.php
│   └── Validator
│       └── ResponseCodeValidator.php
├── Helper
│   ├── AdminStoreResolver.php
│   └── ConfigData.php - Source of truth for Pace's system config values.
├── Model
│   ├── Adminhtml
│   │   └── Source
│   │       ├── Environment.php
│   │       ├── PayWithPaceMode.php
│   │       ├── PaymentAction.php
│   │       └── WidgetLogoTheme.php
│   ├── Observer
│   │   └── RemoveBlock.php - based on Pace's system configurations, we unset the corresponding inactive Blocks here.
│   └── Ui
│       └── ConfigProvider.php - to provide the checkout configurations for use in view/frontend/web/js/view/payment/method-renderer/pace_pay.js
├── Observer
│   ├── CancelOrderObserver.php
│   ├── ConfigPaymentObserver.php
│   ├── DataAssignObserver.php
│   └── PaymentMethodAvailable.php
├── etc
│   ├── adminhtml
│   │   ├── di.xml
│   │   ├── routes.xml
│   │   └── system.xml - define Pace's system configuration
│   ├── config.xml
│   ├── cron_groups.xml
│   ├── crontab.xml
│   ├── csp_whitelist.xml - whitelist any content security policies here.
│   ├── di.xml
│   ├── events.xml
│   ├── frontend
│   │   ├── di.xml - checkout config provider is injected here (Model/Ui/ConfigProvider.php).
│   │   └── routes.xml
│   └── module.xml
├── i18n
│   └── en_US.csv
├── internal
│   ├── README.md.template
│   ├── composer.json.template
│   ├── generateComposer.sh
│   └── generateReadme.sh
├── registration.php
└── view
    ├── adminhtml
    │   ├── layout
    │   │   └── adminhtml_system_config_edit.xml - used to include the payment plan styles.css.
    │   ├── templates
    │   │   └── system
    │   │       └── config
    │   │           └── paymentplan.phtml - template of the payment plan component in the admin config page.
    │   └── web
    │       └── styles.css - styles for payment plan component in admin config page.
    └── frontend
        ├── layout
        │   ├── catalog_product_view.xml - to declare where to place the single product widget on the product page.
        │   ├── checkout_index_index.xml - include Pace's payment method renderer.
        │   └── default.xml - to make templates/pacejs.phtml available to all pages.
        ├── requirejs-config.js - the url for playground and production pacepay.js are hardcoded here.
        ├── templates
        │   ├── pacejs.phtml - to make configs available globally on frontend pages, depends on Block/PaceJS.php.
        │   └── singleproductwidget.phtml - html placeholder for single product widget.
        └── web
            ├── js
            │   ├── main.js - this js will be run on every page (except the checkout page). initialization of pacepay.js and logic to toggle widgets on and off is done here.
            │   └── view
            │       └── payment - the js files needed to render payment method in checkout page. (works together with view/frontend/web/template/payment/form.html)
            │           ├── method-renderer
            │           │   └── pace_pay.js
            │           └── pace_pay.js
            └── template
                └── payment
                    └── form.html - html template of Pace's payment method on the checkout page.
```
