<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Pace\Pay\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Pace\Pay\Gateway\Http\Client\ClientMock;
use Pace\Pay\Helper\ConfigData;

use function Safe\json_encode;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'pace_pay';
    const MODULE_NAME = 'Pace_Pay';
    const PLUGIN_NAME = 'PaceForMagento2';

    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        ConfigData $configData
    ) {
        $this->method = $paymentHelper->getMethodInstance(self::CODE);
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
                    'apiEnvironment' => $this->config->getApiEnvironment(),
                    'payWithPaceMode' => $this->config->getConfigValue(ConfigData::CONFIG_PAY_WITH_PACE_MODE),
                    'baseWidgetConfig' => json_encode($this->config->getBaseWidgetConfig()),
                    'checkoutWidgetConfig' => json_encode($this->config->getCheckoutWidgetConfig())
                ]
            ]
        ];
    }
}
