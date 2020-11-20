<?php

namespace Pace\Pay\Block;

use Magento\Framework\View\Element\Template;
use Pace\Pay\Helper\ConfigData;

class PaceJS extends Template
{
    public function __construct(
        Template\Context $context,
        ConfigData $config
    ) {
        parent::__construct($context);
        $this->config = $config;
    }

    public function getApiEnvironment()
    {
        return $this->config->getApiEnvironment();
    }

    public function getSingleProductWidgetConfig()
    {
        return $this->config->getSingleProductWidgetConfig();
    }

    public function getMultiProductsWidgetConfig()
    {
        return $this->config->getMultiProductsWidgetConfig();
    }

    public function getBaseWidgetConfig()
    {
        return $this->config->getBaseWidgetConfig();
    }

    public function getPaymentPlan()
    {
        return $this->config->getPaymentPlan();
    }
}
