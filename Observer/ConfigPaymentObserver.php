<?php


namespace Pace\Pay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface;
use Pace\Pay\Cron\RefreshPaymentPlans;
use Pace\Pay\Helper\Cache;

class ConfigPaymentObserver implements ObserverInterface
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
     * @param LoggerInterface $logger
     * @param RefreshPaymentPlans $refreshPaymentPlans
     */
    public function __construct(
        LoggerInterface $logger,
        RefreshPaymentPlans $refreshPaymentPlans,
        Cache $cacheHelper
    ) {
        $this->_logger = $logger;
        $this->_refreshPaymentPlans = $refreshPaymentPlans;
        $this->_cacheHelper = $cacheHelper;
    }

    public function execute(EventObserver $observer)
    {
        $this->_logger->info('Pace config update');
        $this->_refreshPaymentPlans->execute();
        $this->_cacheHelper->flushCache();
    }
}
