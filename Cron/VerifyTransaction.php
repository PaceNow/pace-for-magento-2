<?php


namespace Pace\Pay\Cron;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use \Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Pace\Pay\Helper\ConfigData;
use Psr\Log\LoggerInterface;
use Pace\Pay\Controller\Pace\VerifyTransaction as PaceVerifyTransaction;
use Magento\Sales\Model\OrderRepository;
use Magento\Framework\Model\ResourceModel\Iterator;
use \Magento\Store\Model\StoreManagerInterface;
use Pace\Pay\Model\Ui\ConfigProvider;

class VerifyTransaction
{
    /**
     * @var Order
     */
    private $_order;

    /**
     * @var ConfigData
     */
    private $_configData;

    /**
     * @var LoggerInterface
     */
    private $_logger;

    /**
     * @var OrderCollectionFactory
     */
    private $_orderCollectionFactory;

    /**
     * @var PaceVerifyTransaction
     */
    private $_paceVerifyTransaction;

    /**
     * @var OrderRepository
     */
    private $_orderRepository;

    /**
     * @var Iterator
     */
    private $_iterator;

    /**
     * @var StoreManagerInterface
     */
    private $_storeManager;

    /**
     * VerifyTransaction constructor.
     * @param Order $order
     * @param ConfigData $configData
     * @param LoggerInterface $logger
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param PaceVerifyTransaction $paceVerifyTransaction
     * @param OrderRepository $orderRepository
     * @param Iterator $iterator
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Order $order,
        ConfigData $configData,
        LoggerInterface $logger,
        OrderCollectionFactory $orderCollectionFactory,
        PaceVerifyTransaction $paceVerifyTransaction,
        OrderRepository $orderRepository,
        Iterator $iterator,
        StoreManagerInterface $storeManager
    )
    {
        $this->_order = $order;
        $this->_configData = $configData;
        $this->_logger = $logger;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_paceVerifyTransaction = $paceVerifyTransaction;
        $this->_orderRepository = $orderRepository;
        $this->_iterator = $iterator;
        $this->_paceVerifyTransaction = $paceVerifyTransaction;
        $this->_storeManager = $storeManager;
    }

    public function execute()
    {
        $this->_logger->info('Pace cron verify transaction executing');
        $pendingPaymentOrdersCollection = $this->getPendingPaymentOrders();

        foreach ($pendingPaymentOrdersCollection as $key => $item) {
            try {
                $order = $this->_orderRepository->get($key);

                if ($order->getPayment()->getMethod() != ConfigProvider::CODE) {
                    continue;
                }

                $this->_storeManager->setCurrentStore($order->getStoreId());
                $result = $this->_paceVerifyTransaction->verifyAndInvoiceOrder($order);
                if ($result == PaceVerifyTransaction::VERIFY_FAILED) {
                    $order->setStatus(Order::STATE_CANCELED);
                    $order->addCommentToStatusHistory(
                        __('Order cancelled by Pace cron after failed verification')
                    );
                    $this->_orderRepository->save($order);
                }
            } catch (\Exception $exception) {
                $this->_logger->warning('Pace order not found');
            }
        }

        $this->_logger->info('Pace cron verify transaction execution complete');
    }

    /**
     * @return OrderCollection
     */
    private function getPendingPaymentOrders()
    {
        $bufferDate = new \DateTime("45 minutes ago");

        return $this->_orderCollectionFactory->create()
            ->addAttributeToSelect('entity_id')
            ->addFieldToFilter('status', ['eq' => Order::STATE_PENDING_PAYMENT])
            ->addFieldToFilter('created_at', ['lteq' => $bufferDate->format('Y-m-d H:i:s')]);
    }
}
