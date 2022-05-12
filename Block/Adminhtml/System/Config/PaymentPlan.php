<?php

namespace Pace\Pay\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Pace\Pay\Helper\AdminStoreResolver;
use Pace\Pay\Helper\ConfigData;

class PaymentPlan extends Field
{
    protected $_template = 'Pace_Pay::system/config/paymentplan.phtml';

    public function __construct(
        Context $context, 
        ConfigData $configData, 
        AdminStoreResolver $adminStoreResolver, 
        array $data = []
    )
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

    /**
     * getPaymentPlan...
     * Get & display payment plans in admin dashboard
     * 
     * @return mixed
     */
    public function getPaymentPlan()
    {
        try {
            $storeId = $this->_adminStoreResolver->resolveAdminStoreId();
            $paymentPlans = $this->_configData->getPaymentPlan($storeId, true);

            return $paymentPlans;
        } catch (\Exception $e) {
            return;
        }
    }
}
