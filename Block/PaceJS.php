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
        $this->_config = $configData;
    }

    public function getApiEnvironment()
    {
        return $this->_config->getApiEnvironment();
    }

    public function getSingleProductWidgetConfig()
    {
        return $this->_config->getSingleProductWidgetConfig();
    }

    public function getMultiProductsWidgetConfig()
    {
        return $this->_config->getMultiProductsWidgetConfig();
    }

    public function getBaseWidgetConfig()
    {
        return $this->_config->getBaseWidgetConfig();
    }

    public function getPaymentPlan()
    {
        return $this->_config->getPaymentPlan();
    }
}
