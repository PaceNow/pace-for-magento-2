<?php

namespace Pace\Pay\Cron;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\Module\ResourceInterface;
use Pace\Pay\Helper\ConfigData;
use Pace\Pay\Model\Ui\ConfigProvider;
use Psr\Log\LoggerInterface;
use Zend_Http_Client;

/**
 * Class Sync Pace version
 */
class SyncPaceVersion
{
    /**
     * @var \Magento\Framework\Module\ResourceInterface
     */
    protected $_moduleResource;

    /**
     * @var ProductMetadataInterface
     */
    protected $_metaDataInterface;

    /**
     * @var Magento\Framework\HTTP\ZendClient
     */
    protected $_client;

    /**
     * @var Pace\Pay\Helper\ConfigData
     */
    protected $_configData;

    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $_logger;

    public function __construct(
        ZendClient $client,
        ConfigData $configData,
        LoggerInterface $logger,
        ResourceInterface $moduleResource,
        ProductMetadataInterface $productMetadata
    ) {
        $this->_client = $client;
        $this->_logger = $logger;
        $this->_configData = $configData;
        $this->_moduleResource = $moduleResource;
        $this->_metaDataInterface = $productMetadata;
    }

    /**
     * class instance when cron run
     *
     * @return void
     */
    public function execute()
    {
        // init http client
        $this->_client->resetParameters();
        try {
            $this->startCron();
            $endpoint = 'https://developers.pacenow.co/version.json';
            $pluginVersion = $this->_moduleResource->getDbVersion('Pace_Pay');
            $magentoVersion = $this->_metaDataInterface->getVersion();
            $headers = array(
                'X-Pace-PlatformVersion' => ConfigProvider::PLUGIN_NAME . ',' . $pluginVersion . ',' . $magentoVersion,
            );

            $this->_client->setUri($endpoint);
            $this->_client->setMethod(Zend_Http_Client::GET);
            $this->_client->setHeaders($headers);
            $response = $this->_client->request();

            if (empty($response)) {
                throw new \Exception("Not found Pace plugins version");
            }

            $response = json_encode(json_decode($response->getBody()));
            // save pace version to magento core config
            $this->_configData->writeToConfig(
                ConfigData::CONFIG_PACE_SYNC_VERSION,
                $response,
                $storeID = 0// save into default store id
            );

            $this->endCron();
        } catch (\Exception $e) {
            $this->_logger->debug($e->getMessage());
            $this->endCron();
        }
    }

    private function startCron()
    {
        $this->_logger->info('Pace cron sync version executing');
    }

    private function endCron()
    {
        $this->_logger->info('Pace cron sync version execution complete');
    }
}
