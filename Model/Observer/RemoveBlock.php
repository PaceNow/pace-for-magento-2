<?php

namespace Pace\Pay\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Pace\Pay\Helper\ConfigData;

class RemoveBlock implements ObserverInterface
{
    protected $_scopeConfig;

    public function __construct(
        ConfigData $configData
    ) {
        $this->_configData = $configData;
    }

    public function execute(Observer $observer)
    {
        /** @var \Magento\Framework\View\Layout $layout */
        $layout = $observer->getLayout();
        // $isPacePayActive = $this->_configData->getConfigValue(ConfigData::CONFIG_ACTIVE);
        // $isPacePayWidgetsActive = $this->_configData->getConfigValue(ConfigData::CONFIG_WIDGETS_ACTIVE);
        // $isPacePaySingleProductWidgetActive = $this->_configData->getConfigValue(ConfigData::CONFIG_SINGLE_PRODUCT_ACTIVE);

        // if ($layout->getBlock('pace.pacejs')) {
        //     if (!$isPacePayActive || !$isPacePayWidgetsActive) {
        //         $layout->unsetElement('pace.pacejs');
        //     }
        // }

        // if ($layout->getBlock('pace.singleproduct')) {
        //     if (!$isPacePayActive || !$isPacePayWidgetsActive || !$isPacePaySingleProductWidgetActive) {
        //         $layout->unsetElement('pace.singleproduct');
        //     }
        // }
    }
}
