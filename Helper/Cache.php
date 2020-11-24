<?php

namespace Pace\Pay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Cache extends AbstractHelper
{
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool
    ) {
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheFrontendPool = $cacheFrontendPool;
    }

    public function flushCache()
    {
        $invalidcache = $this->_cacheTypeList->getInvalidated();
        foreach ($invalidcache as $key => $value) {
            $this->_cacheTypeList->cleanType($key);
        }
    }
}
