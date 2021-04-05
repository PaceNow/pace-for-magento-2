<?php

namespace Pace\Pay\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Pace\Pay\Controller\Pace\VerifyTransaction as PaceVerifyTransaction;
use Pace\Pay\Cron\RefreshPaymentPlans;
use Pace\Pay\Model\Ui\ConfigProvider;
use Psr\Log\LoggerInterface;
use \Magento\Store\Model\StoreManagerInterface;

class CancelOrderObserver implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var RefreshPaymentPlans
     */
    protected $_refreshPaymentPlans;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var PaceVerifyTransaction
     */
    protected $_paceVerifyTransaction;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        PaceVerifyTransaction $paceVerifyTransaction
    ) {
        $this->_logger = $logger;
        $this->_storeManager = $storeManager;
        $this->_paceVerifyTransaction = $paceVerifyTransaction;
    }

    public function execute(EventObserver $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        $transactionId = $order->getPayment()->getAdditionalData();

        if ($order->getPayment()->getMethod() != ConfigProvider::CODE) {
            return;
        }

        $this->_storeManager->setCurrentStore($order->getStoreId());
        $this->_paceVerifyTransaction->cancelTransaction($order);
        $this->_logger->info('Pace transaction ' . $transactionId . ' cancelled');
    }
}
