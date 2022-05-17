<?php
namespace Pace\Pay\Helper;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\JsonFactory;

class ResponseRespository
{
	
	function __construct(
		JsonFactory $resultJsonFactory,
		ResultFactory $resultFactory
	)
	{
		$this->resultFactory = $resultFactory;
		$this->resultJsonFactory = $resultJsonFactory;
	}

	/**
	 * jsonRespone...
	 * 
	 * @return json
	 */
	public function jsonResponse($data, $code = 200)
	{
		$result = $this->resultJsonFactory->create();
        $result->setData($data);
        $result->setStatusHeader($code);
        $result->setHttpResponseCode($code);

        return $result;
	}

	/**
	 * redirectResponse...
	 * 
	 * @return redirect
	 */
	public function redirectResponse($url)
	{
		return $this->resultFactory
			->create(ResultFactory::TYPE_REDIRECT)
			->setUrl($url);
	}
}