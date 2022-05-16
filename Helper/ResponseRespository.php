<?php
namespace Pace\Pay\Helper;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\JsonFactory;

class ResponseRespository
{
	
	function __construct(JsonFactory $resultJsonFactory)
	{
		$this->resultJsonFactory = $resultJsonFactory;
	}

	/**
	 * jsonRespone...
	 * 
	 * @return json
	 */
	public function jsonResponse($data, $code = 200, $url = '')
	{
		$result = $this->resultJsonFactory->create($url);
        $result->setData($data);
        $result->setStatusHeader($code);
        $result->setHttpResponseCode($code);

        return $result;
	}

	public function redirectResponse($url)
	{
		return $this->resultJsonFactory
			->create(ResultFactory::TYPE_REDIRECT)
			->setUrl($url);
	}
}