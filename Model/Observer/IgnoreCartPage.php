<?php

namespace Pace\Pay\Model\Observer;

use Magento\Checkout\Helper\Cart;

use Magento\Framework\UrlInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Response\RedirectInterface;

class IgnoreCartPage implements ObserverInterface
{
	protected $_url;

	protected $_cart;

    protected $_objectManager;

    public function __construct(
    	Cart $cart,
    	UrlInterface $url
    )
    {
    	$this->_url = $url;
   		$this->_cart = $cart;
    }

    public function execute( Observer $observer )
    {
    	if ( $this->_cart->getItemsCount() > 0 && !$this->_cart->getQuote()->getHasError() ) {
    		$url = $this->_url->getUrl( 'checkout/' );
    	} else {
    		$url = $this->_url->getBaseUrl();
    	}

    	$observer->getControllerAction()
            ->getResponse()
            ->setRedirect( $url );
    }
}