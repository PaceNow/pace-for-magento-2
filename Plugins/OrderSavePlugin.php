<?php


namespace Pace\Pay\Plugins;

use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class OrderSavePlugin
{
    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var \Magento\Checkout\Model\Session $checkoutSession
     */
    protected $checkoutSession;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param LoggerInterface $logger
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Checkout\Model\Session $checkoutSession,
        LoggerInterface $logger
    )
    {
        $this->orderSender = $orderSender;
        $this->checkoutSession = $checkoutSession;
        $this->_logger = $logger;
    }

    /**
     * @param \Magento\Sales\Api\OrderRepositoryInterface $subject
     * @param \Magento\Sales\Api\Data\OrderInterface $result
     * @return mixed
     * @throws \Exception
     */
    public function afterSave(
        \Magento\Sales\Api\OrderRepositoryInterface $subject,
        $result
    )
    {
        if ($result->getStatus() == Order::STATE_PROCESSING && !$result->getEmailSent()) {
            $this->checkoutSession->setForceOrderMailSentOnSuccess(true);
            $this->orderSender->send($result, true);
        }
        return $result;
    }
}
