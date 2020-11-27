<?php

namespace Pace\Pay\Block\Adminhtml\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Pace\Pay\Helper\ConfigData;

class Label extends \Magento\Config\Block\System\Config\Form\Field
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        ConfigData $configData
    ) {
        $this->_configData = $configData;
        parent::__construct($context);
    }


    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_configData->getModuleVersion();
    }
}
