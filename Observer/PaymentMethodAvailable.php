<?php

namespace Pace\Pay\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\Event\ObserverInterface;
use Pace\Pay\Helper\ConfigData;

class PaymentMethodAvailable implements ObserverInterface
{
    public function __construct(
        Session $checkoutSession,
        ConfigData $configData,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->_configData = $configData;
        $this->_logger = $logger;
    }
    /**
     * payment_method_is_active event handler.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // $grandTotal = $this->_checkoutSession->getQuote()->getGrandTotal();
        if ($observer->getEvent()->getMethodInstance()->getCode() == "pace_pay") {
            $checkResult = $observer->getEvent()->getResult();
            try {
                $paymentPlan = $this->_configData->getPaymentPlan();
                if (!isset($paymentPlan['paymentPlans'])) {
                    throw new \Exception("Pace payment plans not found");
                }

                $paymentPlan = $paymentPlan['paymentPlans'];
                if (!$paymentPlan->isAvailable) {
                    throw new \Exception("Pace payment methods is invalid");
                }

                $checkResult->setData('is_available', true);
            } catch (\Exception $e) {
                $this->_logger->info($e->getMessage());
                $checkResult->setData('is_available', false);
            }
        }
    }
}
