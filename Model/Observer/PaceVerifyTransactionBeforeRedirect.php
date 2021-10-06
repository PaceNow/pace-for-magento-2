<?php

namespace Pace\Pay\Model\Observer;

use Magento\Framework\View\Element\Message\InterpretationStrategyInterface;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;

use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;

use Magento\Framework\Serialize\Serializer\Json;

class PaceVerifyTransactionBeforeRedirect implements ObserverInterface
{	
	/**
     * Cookies name for messages
     */
    const MESSAGES_COOKIES_NAME = 'mage-messages';

    /**
     * @var Magento\Framework\Serialize\Serializer\Json
     */
    protected $_serializer;

    /**
	 * @var Magento\Framework\Stdlib\CookieManagerInterface
	 */
	protected $_cookieManager;

    /**
	 * @var Magento\Framework\Message\ManagerInterface
	 */
	protected $_messageManager;

	/**
	 * @var Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
	 */
	protected $_cookieMetadataFactory;

	/**
	 * @var Magento\Framework\View\Element\Message\InterpretationStrategyInterface
	 */
	protected $_interpretationStrategy;

    public function __construct(
    	Json $serializer = null,
    	CookieMetadataFactory $cookieMetadataFactory,
    	CookieManagerInterface $cookieManager,
    	MessageManagerInterface $messageManager,
    	InterpretationStrategyInterface $interpretationStrategy
    )
    {
    	$this->_serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
    	$this->_cookieManager = $cookieManager;
        $this->_messageManager = $messageManager;
        $this->_cookieMetadataFactory = $cookieMetadataFactory;
        $this->_interpretationStrategy = $interpretationStrategy;
    }

    protected function getMessages()
    {
    	$messages = $this->_cookieManager->getCookie( self::MESSAGES_COOKIES_NAME );

    	if ( empty( $messages ) ) {
    		return [];
    	}

    	$messages = $this->_serializer->unserialize( $messages );

        if ( !is_array( $messages ) ) {
            $messages = [];
        }

        return $messages;
    }

    /**
     * Add custom messages to cookies
     *
     * @since 1.0.5
     * @return @void
     */
    protected function addPaceMessagetoCookies()
    {
    	$notices = $this->_messageManager->getMessages( true, 'pace' );

    	if ( $notices ) {
    		$cookieMessages = $this->getMessages();
    		$lastAddedMessage = $notices->getLastAddedMessage();

    		if ( $lastAddedMessage ) {
    			$text = $this->_interpretationStrategy->interpret( $lastAddedMessage );

    			if ( empty( $text ) ) {
    				array_walk( $cookieMessages, function( $value, $key ) use ( &$cookieMessages, $lastAddedMessage ) {
    					if ( isset( $value['identifier'] ) && $value['identifier'] == $lastAddedMessage->getIdentifier() ) {
    						unset( $cookieMessages[$key] );
    					}
    				} );
    			} else {
					$cookieMessages[] = array(
    					'text' => $text,
		    			'type' => $lastAddedMessage->getType(),
		                'identifier' => $lastAddedMessage->getIdentifier(),
		    		);
    			}
    		}
    		
    		$publicCookieMetadata = $this->_cookieMetadataFactory->createPublicCookieMetadata();
            $publicCookieMetadata->setDurationOneYear();
            $publicCookieMetadata->setPath('/');
            $publicCookieMetadata->setHttpOnly(false);
            $publicCookieMetadata->setSameSite('Strict');

            $this->_cookieManager->setPublicCookie(
                self::MESSAGES_COOKIES_NAME,
                $this->_serializer->serialize( $cookieMessages ),
                $publicCookieMetadata
            );
    	}
    }

    public function execute( Observer $observer )
    {
    	$this->addPaceMessagetoCookies();
    }
}