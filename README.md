# Pace Payment Gateway Module for Magento 2
###### Pace Enterprise Pte. Ltd.

Below are the instructions to install the module on Magento 2. Please ensure you have Pace
  clientId and secret before you proceed.
  
# Installation steps
## Composer
- Add Pace repository
 ```
  composer config repositories.pacenow git https://github.com/PaceNow/pace-for-magento-2.git
 ```

- Require Pace_Pay module
```
 composer require pace/module-pay:dev-master#0.0.5
```

- Enable Pace_Pay module
```
 ./bin/magento module:enable Pace_Pay
```

- Magento setups and cache clean
```
 ./bin/magento setup:upgrade
```
```
 ./bin/magento setup:di:compile
```
```
 ./bin/magento setup:static-content:deploy 
```
```
 ./bin/magento cache:clean
```

## Configure
From the Magento 2 Admin interface
- navigate to `Stores > Configuration > Sales > Payment Methods`
- under `OTHER PAYMENT METHODS > Pace Pay`
    - Set `Enabled` to `Yes`
    - Select `Playground` or `Production` for the `Environment`
    - Set `Client ID` and `Client Secret` under `API config`
- set the other options as necessary

# Support
For additional support, contact <merchant-integration@pacenow.co>
