<?php


namespace Pace\Pay\Observer;

use Magento\Sales\Model\Order;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Pace\Pay\Model\Ui\ConfigProvider;
use Psr\Log\LoggerInterface;
use Pace\Pay\Cron\RefreshPaymentPlans;
use \Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ObjectManager;
use Pace\Pay\Controller\Pace\VerifyTransaction as PaceVerifyTransaction;

class TrackingOrderStatus implements ObserverInterface
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
        $order = $observer->getOrder();
        if (isset($order->getOrigData()['status']) && $order->getStatus() !=  $order->getOrigData()['status']) {
            $objectmanager = ObjectManager::getInstance();
            $trackingStatus = $objectmanager->create('Pace\Pay\Model\TrackingStatus');
            $trackingStatus->setOrderId($order->getIncrementId());
            $trackingStatus->setPrevStatus($order->getOrigData()['status']);
            $trackingStatus->setCurrentStatus($order->getStatus());
            $trackingStatus->save();
        }
    }
}
