<?php
namespace Pace\Pay\Plugins;

use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Pace\Pay\Helper\ConfigData;
use Pace\Pay\Model\Ui\ConfigProvider;

class OrderSavePlugin {
	/**
	 * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
	 */
	protected $orderSender;

	/**
	 * @var \Magento\Checkout\Model\Session $checkoutSession
	 */
	protected $checkoutSession;

	/**
	 * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
	 * @param \Magento\Checkout\Model\Session $checkoutSession
	 * @codeCoverageIgnore
	 */
	public function __construct(
		OrderSender $orderSender,
		ConfigData $configData,
		Session $checkoutSession
	) {
		$this->orderSender = $orderSender;
		$this->configData = $configData;
		$this->checkoutSession = $checkoutSession;
	}

	/**
	 * @param \Magento\Sales\Api\OrderRepositoryInterface $subject
	 * @param \Magento\Sales\Api\Data\OrderInterface $result
	 * @return mixed
	 * @throws \Exception
	 */
	public function afterSave(\Magento\Sales\Api\OrderRepositoryInterface $subject, $result) {
		$paymentMethod = $result->getPayment()->getMethod();
		$state = $this->configData->getConfigValue('pace_approved', $result->getStoreId()) ?: Order::STATE_PROCESSING;

		$ableToSend = $result->getState() === $state;

		if (ConfigProvider::CODE == $paymentMethod && $ableToSend && !$result->getEmailSent()) {
			$this->checkoutSession->setForceOrderMailSentOnSuccess(true);
			$this->orderSender->send($result, true);
		}

		return $result;
	}
}
