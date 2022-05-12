<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Pace\Pay\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Pace\Pay\Helper\ConfigData;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'pace_pay';
    const MODULE_NAME = 'Pace_Pay';
    const PLUGIN_NAME = 'Pace For Magento 2';

    public function __construct(
        ConfigData $configData
    ) {
        $this->config = $configData;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    // 'enable' => $this->config->isEnable(),
                    // 'apiEnvironment' => $this->config->getApiEnvironment(),
                    // 'payWithPaceMode' => $this->config->getConfigValue(ConfigData::CONFIG_PAY_WITH_PACE_MODE),
                    // 'baseWidgetConfig' => $this->config->getBaseWidgetConfig(),
                    // 'checkoutWidgetConfig' => $this->config->getCheckoutWidgetConfig(),
                ],
            ],
        ];
    }
}
