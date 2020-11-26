<?php

namespace Pace\Pay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session;
use Pace\Pay\Helper\ConfigData;

class PaymentMethodAvailable implements ObserverInterface
{
    public function __construct(Session $checkoutSession, ConfigData $configData)
    {
        $this->_checkoutSession = $checkoutSession;
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
        if ($observer->getEvent()->getMethodInstance()->getCode() == "pace_pay") {
            $paymentPlan = $this->_configData->getPaymentPlan();

            if (isset($paymentPlan)) {
                $checkResult = $observer->getEvent()->getResult();
                $paymentPlanMin = $paymentPlan['minAmount'];
                $paymentPlanMax = $paymentPlan['maxAmount'];
                $isCurrencySupported =  $this->_configData->getIsCurrencySupported();
                $checkResult->setData('is_available', $grandTotal >= $paymentPlanMin && $grandTotal <= $paymentPlanMax && $isCurrencySupported);
            }
        }
    }
}
