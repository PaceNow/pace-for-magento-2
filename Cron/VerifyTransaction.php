<?php

namespace Pace\Pay\Cron;

use Magento\Framework\Model\ResourceModel\Iterator;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Pace\Pay\Controller\Pace\VerifyTransaction as PaceVerifyTransaction;
use Pace\Pay\Helper\ConfigData;
use Psr\Log\LoggerInterface;
use \Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use \Magento\Store\Model\StoreManagerInterface;

class VerifyTransaction
{
    /**
     * @var OrderInterfaceFactory
     */
    private $_order;

    /**
     * @var Magento\Sales\Model\Order\Config instance
     */
    private $_orderConfig;

    /**
     * @var Magento\Sales\Model\Order\OrderStateResolverInterface instance
     */
    private $_orderStateResolver;

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
     * @param OrderInterfaceFactory $order
     * @param ConfigData $configData
     * @param LoggerInterface $logger
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param PaceVerifyTransaction $paceVerifyTransaction
     * @param OrderRepository $orderRepository
     * @param Iterator $iterator
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        OrderInterfaceFactory $order,
        ConfigData $configData,
        LoggerInterface $logger,
        OrderCollectionFactory $orderCollectionFactory,
        PaceVerifyTransaction $paceVerifyTransaction,
        OrderRepository $orderRepository,
        Iterator $iterator,
        StoreManagerInterface $storeManager,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Sales\Model\Order\OrderStateResolverInterface $orderStateResolver
    ) {
        $this->_order = $order;
        $this->_orderConfig = $orderConfig;
        $this->_orderStateResolver = $orderStateResolver;
        $this->_configData = $configData;
        $this->_logger = $logger;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_paceVerifyTransaction = $paceVerifyTransaction;
        $this->_orderRepository = $orderRepository;
        $this->_iterator = $iterator;
        $this->_paceVerifyTransaction = $paceVerifyTransaction;
        $this->_storeManager = $storeManager;
    }

    /**
     * Set order state
     * @param  Magento\Sales\Model\Order &$order
     * @param  String $state
     */
    protected function updateOrderState(&$order, $state)
    {
        $order->setState(
            $this->_orderStateResolver->getStateForOrder($order, [$state])
        );
        $order->setStatus($this->_orderConfig->getStateDefaultStatus($order->getState()));
    }

    public function execute()
    {
        $this->_logger->info('Pace cron verify transaction executing');
        $pace_order = $this->_paceVerifyTransaction->getAllOrder();

        foreach ($pace_order as $key => $value) {
            // $this->_logger->info('Pace cron verify transaction execution complete');
            try {
                $order = $this->_order->create()->loadByIncrementId($value['referenceID']);

                if ($order) {
                    $payment_method = $order->getPayment() != null ? $order->getPayment()->getMethod() : "";
                    if ($payment_method == "pace_pay") {
                        // $this->_logger->info($order->getId());
                        if ($this->_paceVerifyTransaction->checkOrderManuallyUpdate($order, $value)) {
                            switch ($value['status']) {
                                case "pending_confirmation":
                                    if ($order->getState() != Order::STATE_PENDING_PAYMENT) {
                                        $this->updateOrderState($order, Order::STATE_PENDING_PAYMENT);
                                    }
                                    break;
                                case "cancelled":
                                case "expired":
                                    if ($order->getState() != Order::STATE_CANCELED) {
                                        $order->cancel();
                                    }
                                    break;
                                case "approved":
                                    if ($order->getState() != Order::STATE_PROCESSING) {
                                        $this->updateOrderState($order, Order::STATE_PROCESSING);
                                    }
                                    break;
                            }
                            $order->save();
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
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
