<?php

namespace Pace\Pay\Block;

use Pace\Pay\Helper\ConfigData;
use Magento\Framework\View\Element\Template;

/**
 * Include Pace SDK
 */
class PaceSDK extends Template
{

    public function __construct(
        Template\Context $context,
        ConfigData $configData
    ) {
        parent::__construct($context);
        $this->_config = $configData;
    }

    /**
     * Check whether Pace payment plans are available
     *
     * @since 1.0.4
     * @return boolean 
     */
    public function isAvailable()
    {
        $paymentPlan = $this->_configData->getPaymentPlan();

        if ( isset( $paymentPlan ) ) {
            $plans = $paymentPlan['paymentPlans'];

            return $plans->isAvailable;
        }

        return false;
    }
}
