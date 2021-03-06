<?php

namespace Pace\Pay\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Pace\Pay\Helper\ConfigData;
use Pace\Pay\Helper\AdminStoreResolver;

class PaymentPlan extends Field
{
    protected $_template = 'Pace_Pay::system/config/paymentplan.phtml';
    public function __construct(Context $context, array $data = [], ConfigData $configData, AdminStoreResolver $adminStoreResolver)
    {
        $this->_configData = $configData;
        $this->_adminStoreResolver = $adminStoreResolver;
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getAjaxUrl()
    {
        return $this->getUrl('pace_pay/system_config/refreshpaymentplans');
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')->setData(['id' => 'payment-plan_refresh-button', 'label' => __('Refresh'),]);
        return $button->toHtml();
    }

    public function getPaymentPlan()
    {
        $storeId = $this->_adminStoreResolver->resolveAdminStoreId();
        return $this->_configData->getPaymentPlan($storeId);
    }
}
