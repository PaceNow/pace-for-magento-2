<?php

namespace Pace\Pay\Observer;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\Event\ObserverInterface;
use Pace\Pay\Helper\ConfigData;
use Pace\Pay\Model\Ui\ConfigProvider;

class PaymentMethodAvailable implements ObserverInterface {
	public function __construct(
		Session $checkoutSession,
		ConfigData $configData,
		\Psr\Log\LoggerInterface $logger
	) {
		$this->configData = $configData;
		$this->logger = $logger;
	}
	/**
	 * payment_method_is_active event handler.
	 *
	 * @param \Magento\Framework\Event\Observer $observer
	 */
	public function execute(\Magento\Framework\Event\Observer $observer) {
		$method = $observer
			->getEvent()
			->getMethodInstance()
			->getCode();
		$checkResult = $observer->getEvent()->getResult();

		if (ConfigProvider::CODE == $method) {
			try {
				$paymentPlans = $this->configData->getPaymentPlan() ?? null;
				$payment = !empty($paymentPlans)
				? $paymentPlans['paymentPlans']
				: null;

				if (empty($payment)) {
					throw new Exception('Pace payment methods is invalid!');
				}

				$checkResult->setData('is_available', true);
			} catch (Exception $e) {
				$this->logger->info("PaymentMethodAvailable Exception: {$e->getMessage()}");
				$checkResult->setData('is_available', false);
			}
		}
	}
}
