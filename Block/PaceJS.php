<?php

namespace Pace\Pay\Block;

use Magento\Framework\View\Element\Template;
use Pace\Pay\Helper\ConfigData;

class PaceJS extends Template
{
    public function __construct(
        Template\Context $context,
        ConfigData $configData
    ) {
        parent::__construct($context);
        $this->_configData = $configData;
    }

    public function getApiEnvironment()
    {
        return $this->_configData->getApiEnvironment();
    }

    public function getSingleProductWidgetConfig()
    {
        return $this->_configData->getSingleProductWidgetConfig();
    }

    public function getMultiProductsWidgetConfig()
    {
        return $this->_configData->getMultiProductsWidgetConfig();
    }

    public function getBaseWidgetConfig()
    {
        return $this->_configData->getBaseWidgetConfig();
    }

    public function getPaymentPlan()
    {
        return $this->_configData->getPaymentPlan();
    }
}
