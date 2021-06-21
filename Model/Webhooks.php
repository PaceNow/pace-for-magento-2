<?php 

namespace Pace\Pay\Model;

use Magento\Framework\Webapi\Rest\Request;

use Psr\Log\LoggerInterface;

use Pace\Pay\Controller\Pace\Transaction;
use Pace\Pay\Api\WebhookManagementInterface;

/**
 * Class process Pace webhooks callback
 */
class Webhooks extends Transaction implements WebhookManagementInterface
{	
	/**
     * @var LoggerInterface
     */
    protected $_logger;

	/**
     * @var \Magento\Framework\Webapi\Rest\Request
     */
    protected $_request;

	/**
	 * @var Pace\Pay\Controller\Pace\Transaction
	 */
	protected $Transaction;

	public function __construct(Request $request, LoggerInterface $logger)
	{
		$this->_logger = $logger;
		$this->_request = $request;
	}

	public function execute()
	{
		return 2;
	}

	public function _handle()
	{	
		$params = $this->_request->getBodyParams();
		$this->_logger->info(json_encode( $params ));
    	try {
    		if (!isset($params['status'])) {
    			throw new \Exception('Unknow Pace webhooks response status');
    		}

    		if ('success' !== $params['status']) {
    			throw new \Exception('Unsuccessfully handle webhooks callback');
    		}

    		$order = $this->orderRepository->get($params['referenceID']);

    		if (!$order) {
    			throw new \Exception('Unknow orders');
    		}

    		switch ($params['event']) {
    			case 'approved':
    				$this->_handleApprove($order);
    				break;
    			case 'cancelled':
    				
    				break;
    			case 'expired':
    				
    				break;
    			default:
    				// code...
    				break;
    		}
    	} catch (\Exception $e) {
    		$this->_logger->info($e->getMessage());
    	}
	}
}