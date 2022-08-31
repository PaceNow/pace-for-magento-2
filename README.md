# Pace Payment Gateway Module for Magento 2

###### Pace Enterprise Pte. Ltd.

Below are the instructions to install the module on Magento 2. Please ensure you have Pace
clientId and secret before you proceed.

# Requirements

## Supported Currencies

- Currently, Pace supports the following currencies:
  - SGD

Note that the Magento store's currency must be in the supported list for the Pace extension to
work. To set the base currency, navigate to `Stores > Configuration > Currency Setup`.

## Supported Magento Versions

Pace Magento extension is compatible with Magento version 2.30 or greater.

# Installation steps

## Composer

- Add Pace repository

```
composer config repositories.pacenow git https://github.com/PaceNow/pace-for-magento-2.git
```

- Require Pace_Pay module

```
composer require pace/module-pay:dev-master#v1.1.4
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

## Manual

If you do not choose to use composer, you may copy the files in this repository to
`<Root Magento Directory>/app/code/Pace/Pay`, and run `Enable Pace_Pay module` and `Magento setups and cache clean` steps in the composer section above.

# Requirements

- Store currency: SGD

# Configuration

From the Magento 2 Admin interface

- Navigate to `Stores > Configuration > Sales > Payment Methods`
- Under `OTHER PAYMENT METHODS > Pace Pay`
  - Ensure that Scope is `Default Config`
    - Set `Enabled` to `Yes`
    - Save Config
  - Switch Scope to Store specific scope
    - Select `Playground` or `Production` for the `Environment`
    - Set `Client ID` and `Client Secret` under `API config`
    - Save Config
- Set the other options as necessary
- Clear cache from `System > Cache Management > Flush Cache Storage`

# Updating

## Composer

First run

```
composer update pace/module-pay
```

followed by `Magento setups and cache clean` steps in the composer section above.

## Manual

Delete the folder `<Root Magento Directory>/app/code/Pace/Pay`, and then redo the `Manual`
installation section above with the latest files.

# Support

For additional support, contact <merchant-integration@pacenow.co>
