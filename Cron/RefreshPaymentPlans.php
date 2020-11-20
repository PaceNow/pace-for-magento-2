<?php


namespace Pace\Pay\Cron;

use Magento\Store\Api\StoreRepositoryInterface;
use Pace\Pay\Controller\Adminhtml\System\Config\RefreshPaymentPlans as ConfigRefreshPaymentPlans;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class RefreshPaymentPlans
{
    /**
     * @var StoreRepositoryInterface
     */
    protected $_storeRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ConfigRefreshPaymentPlans
     */
    protected $_configRefreshPaymentPlans;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    function __construct(
        StoreRepositoryInterface $storeRepository,
        StoreManagerInterface $storeManager,
        ConfigRefreshPaymentPlans $configRefreshPaymentPlans,
        LoggerInterface $logger
    )
    {
        $this->_storeRepository = $storeRepository;
        $this->_storeManager = $storeManager;
        $this->_configRefreshPaymentPlans = $configRefreshPaymentPlans;
        $this->_logger = $logger;
    }

    function execute()
    {
        $this->_logger->info('Pace cron refresh payment plan executing');
        $stores = $this->_storeRepository->getList();
        foreach ($stores as $store) {
            $this->_storeManager->setCurrentStore($store);
            try {
                $result = $this->_configRefreshPaymentPlans->refreshPlans();
                if ($result == ConfigRefreshPaymentPlans::REFRESH_SUCCESS) {
                    $this->_logger
                        ->info('Pace cron refresh payment success for storeId ' . $store->getId());
                } else {
                    $this->_logger
                        ->info('Pace cron refresh payment failure for storeId ' . $store->getId());
                }
            } catch (\Exception $exception) {
                $this->_logger->error('Pace cron refresh payment failed with exception - ' .
                    $exception);
            }
        }
        $this->_logger->info('Pace cron refresh payment plan execution complete');
    }
}
