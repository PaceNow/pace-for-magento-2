<?php

namespace Pace\Pay\Helper;

use Magento\Store\Model\StoreManagerInterface;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Request\Http;

class AdminStoreResolver extends AbstractHelper
{
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
        Http $request,
        Context $context,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->storeManager = $storeManager;
    }

    /**
     * getBaseUrl...
     * 
     * @return string;
     */
    public function getBaseUrl($path = '')
    {
        $path = $path ?: \Magento\Framework\UrlInterface::URL_TYPE_WEB;
        
        return $this->storeManager->getStore()->getBaseUrl($path);
    }

    /**
     * resolveAdminStoreId...
     * 
     * @return int
     */
    public function resolveAdminStoreId()
    {
        $storeId = (int) $this->request->getParam('store', 0);

        return $storeId;
    }
}
