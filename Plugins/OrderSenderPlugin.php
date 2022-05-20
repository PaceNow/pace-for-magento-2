<?php

namespace Pace\Pay\Plugins;

use Magento\Sales\Model\Order;
use Pace\Pay\Model\Ui\ConfigProvider;

class OrderSenderPlugin {

	public function aroundSend(\Magento\Sales\Model\Order\Email\Sender\OrderSender $subject, callable $proceed, Order $order, $forceSyncMode = false) {
		$paymentMethod = $order->getPayment()->getMethod();

		if ($paymentMethod === ConfigProvider::CODE && $order->getStatus() === Order::STATE_PENDING_PAYMENT) {
			return false;
		}

		return $proceed($order, $forceSyncMode);
	}
}
