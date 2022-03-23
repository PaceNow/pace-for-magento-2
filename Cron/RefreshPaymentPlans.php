<?php

namespace Pace\Pay\Cron;

use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

use Psr\Log\LoggerInterface;

use Pace\Pay\Controller\Adminhtml\System\Config\RefreshPaymentPlans as ConfigRefreshPaymentPlans;

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
    ) {
        $this->_storeRepository = $storeRepository;
        $this->_storeManager = $storeManager;
        $this->_configRefreshPaymentPlans = $configRefreshPaymentPlans;
        $this->_logger = $logger;
    }

    function execute()
    {
        $this->_logger->info('Pace cron refresh payment plan executing');
        $this->_configRefreshPaymentPlans->refreshPlans();
        $this->_logger->info('Pace cron refresh payment plan execution complete');
    }
}
