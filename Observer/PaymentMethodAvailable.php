<?php

namespace Pace\Pay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Pace\Pay\Helper\ConfigData;

class PaymentMethodAvailable implements ObserverInterface
{
    public function __construct(Session $checkoutSession, StoreManagerInterface $storeManager, ConfigData $configData)
    {
        $this->_checkoutSession = $checkoutSession;
        $this->_storeManager = $storeManager;
        $this->_configData = $configData;
    }
    /**
     * payment_method_is_active event handler.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $grandTotal = $this->_checkoutSession->getQuote()->getGrandTotal();
        $currency = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
        if ($observer->getEvent()->getMethodInstance()->getCode() == "pace_pay") {
            $checkResult = $observer->getEvent()->getResult();
            $paymentPlanMin = $this->_configData->getConfigValue(ConfigData::CONFIG_PAYMENT_PLAN_MIN);
            $paymentPlanMax = $this->_configData->getConfigValue(ConfigData::CONFIG_PAYMENT_PLAN_MAX);
            $paymentPlanCurrency =  $this->_configData->getConfigValue(ConfigData::CONFIG_PAYMENT_PLAN_CURRENCY);
            \Magento\Framework\App\ObjectManager::getInstance()->get('Psr\Log\LoggerInterface')->info($currency);
            $checkResult->setData('is_available', $grandTotal >= $paymentPlanMin && $grandTotal <= $paymentPlanMax && $currency == $paymentPlanCurrency);
        }
    }
}
