<?php


namespace Pace\Pay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface;
use Pace\Pay\Cron\RefreshPaymentPlans;

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
        RefreshPaymentPlans $refreshPaymentPlans
    )
    {
        $this->_logger = $logger;
        $this->_refreshPaymentPlans = $refreshPaymentPlans;
    }

    public function execute(EventObserver $observer)
    {
        $this->_logger->info('Pace config update');
        $this->_refreshPaymentPlans->execute();
    }
}
