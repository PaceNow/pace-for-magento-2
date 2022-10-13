<?php

namespace Pace\Pay\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;
use Pace\Pay\Controller\Adminhtml\System\Config\RefreshPaymentPlans;

class ConfigPaymentObserver implements ObserverInterface {
	/**
	 * @param RefreshPaymentPlans $refreshPaymentPlans
	 */
	public function __construct(
		RefreshPaymentPlans $refreshPaymentPlans
	) {
		$this->refreshPaymentPlans = $refreshPaymentPlans;
	}

	public function execute(EventObserver $observer) {
		$scopeData = $this->getScopeData($observer);
		if ($scopeData['scope'] == ScopeInterface::SCOPE_STORES) {
			@$this->refreshPaymentPlans->execute();
		}
	}

    protected function getScopeData($observer)
    {
        $scopeData = [];

        $scopeData['scope']    = 'default';
        $scopeData['scope_id'] = null;

        $website = $observer->getWebsite();
        $store   = $observer->getStore();

        if ($website) {
             $scopeData['scope']    = ScopeInterface::SCOPE_WEBSITES;
             $scopeData['scope_id'] = $website;
        }

        if ($store) {
             $scopeData['scope']    = ScopeInterface::SCOPE_STORES;
             $scopeData['scope_id'] = $store;
        }

        return $scopeData;
    }
}
