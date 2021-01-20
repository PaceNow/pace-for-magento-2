<?php


namespace Pace\Pay\Plugins;

use Magento\Sales\Model\Order;
use Pace\Pay\Model\Ui\ConfigProvider;
use Psr\Log\LoggerInterface;

class OrderSenderPlugin
{
    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * OrderSenderPlugin constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    )
    {
        $this->_logger = $logger;
    }


    public function aroundSend(\Magento\Sales\Model\Order\Email\Sender\OrderSender $subject, callable $proceed, Order $order, $forceSyncMode = false)
    {
        $paymentMethod = $order->getPayment()->getMethod();

        if ($paymentMethod === ConfigProvider::CODE && $order->getStatus() ===
            Order::STATE_PENDING_PAYMENT) {
            return false;
        }

        return $proceed($order, $forceSyncMode);
    }
}
