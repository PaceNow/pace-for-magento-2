<?php


namespace Pace\Pay\Observer;

use Magento\Sales\Model\Order;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Pace\Pay\Model\Ui\ConfigProvider;
use Psr\Log\LoggerInterface;
use Pace\Pay\Cron\RefreshPaymentPlans;
use \Magento\Store\Model\StoreManagerInterface;
use Pace\Pay\Controller\Pace\VerifyTransaction as PaceVerifyTransaction;

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
    )
    {
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
