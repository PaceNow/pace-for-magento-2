<?php

namespace Pace\Pay\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;

use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;

class PaceSalesOrderPlaceBefore implements ObserverInterface
{
	/**
	 * @var Magento\Framework\Event\ManagerInterface
	 */
	protected $_eventManager;

	/**
	 * @var Magento\Framework\Message\ManagerInterface
	 */
	protected $_messageManager;

    public function __construct(
    	EventManager $eventManager,
    	MessageManagerInterface $messageManager
    )
    {
    	$this->_eventManager = $eventManager;
        $this->_messageManager = $messageManager;
    }

    public function clearNotices()
    {
    	// Magento\Framework\Message\ManagerInterface
        $message = $this->_messageManager->createMessage( 'notice', 'pace-notice' )->setText( '' );
        $this->_messageManager->addMessage( $message, 'pace' );

        $this->_eventManager->dispatch( 'pace_pay_verifytransaction_before_redirect' );
    }

    /**
     * Pace modified before place order
     *
     * @since 1.0.0
     * @param  Observer $observer 
     * @return @void
     */
    public function execute( Observer $observer )
    {
    	$this->clearNotices();
    }
}