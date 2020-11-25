<?php

namespace Pace\Pay\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Request\Http;

class AdminStoreResolver extends AbstractHelper
{
    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Data constructor.
     * @param Context $context
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        Context $context,
        State $state,
        Http $request
    ) {
        parent::__construct($context);
        $this->state = $state;
        $this->_request = $request;
    }

    /**
     * @return int
     */
    public function resolveAdminStoreId()
    {
        $request = $this->_request;
        $storeId = (int) $request->getParam('store', 0);
        return $storeId;
    }
}
