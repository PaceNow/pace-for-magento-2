<?php

namespace Pace\Pay\Controller\Pace;

/**
 * summary
 */
class Testing extends \Magento\Framework\App\Action\Action
{	
	public function __construct(\Magento\Framework\App\Action\Context $context)
    {
     	return parent::__construct($context);
    }
    
    /**
     * summary
     */
    public function execute()
    {
        return $this->_jsonResponse(array('message' => 'testing'));
    }
}