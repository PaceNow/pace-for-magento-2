<?php

namespace Pace\Pay\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

class EmptyClient implements ClientInterface
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger
    )
    {
        $this->logger = $logger;
    }

    public function placeRequest(TransferInterface $transferObject)
    {
        $this->logger->debug(['request' => $transferObject->getBody()]);
        return [];
    }
}
