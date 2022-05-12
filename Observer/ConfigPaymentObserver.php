<?php

namespace Pace\Pay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;

use Pace\Pay\Controller\Adminhtml\System\Config\RefreshPaymentPlans;

class ConfigPaymentObserver implements ObserverInterface
{
    /**
     * @param RefreshPaymentPlans $refreshPaymentPlans
     */
    public function __construct(
        RefreshPaymentPlans $refreshPaymentPlans
    ) {
        $this->refreshPaymentPlans = $refreshPaymentPlans;
    }

    public function execute(EventObserver $observer)
    {
        @$this->refreshPaymentPlans->execute();
    }
}
