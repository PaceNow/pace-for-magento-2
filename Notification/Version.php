<?php

namespace Pace\Pay\Notification;

use Pace\Pay\Helper\ConfigData;
use Psr\Log\LoggerInterface;

/**
 * Pace show notify UPDATE VERSION message box
 */
class Version implements \Magento\Framework\Notification\MessageInterface
{

    const MESSAGE_IDENTITY = 'pace_version_notification';

    /**
     * @var \Magento\Framework\Module\ResourceInterface
     */
    protected $_moduleResource;

    /**
     * @var Pace\Pay\Helper\ConfigData
     */
    protected $_configData;

    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $_logger;

    public function __construct(
        ConfigData $configData,
        LoggerInterface $logger,
        \Magento\Framework\Module\ResourceInterface $moduleResource
    ) {
        $this->_logger = $logger;
        $this->_configData = $configData;
        $this->_moduleResource = $moduleResource;
    }

    public function getIdentity()
    {
        // Retrieve unique message identity
        return self::MESSAGE_IDENTITY;
    }

    public function isDisplayed()
    {
        // Return true to show your message, false to hide it
        return $this->checkPaceVersion();
    }

    public function getText()
    {
        // message text
        $html = sprintf("There is a new version of <strong>Pace For Magento 2</strong> available. Click %s to view.", '<a href="https://developers.pacenow.co/#plugins-magento2" target="_blank">here</a>');
        return $html;
    }

    /**
     * Retrieve system message severity
     * Possible default system message types:
     * - MessageInterface::SEVERITY_CRITICAL
     * - MessageInterface::SEVERITY_MAJOR
     * - MessageInterface::SEVERITY_MINOR
     * - MessageInterface::SEVERITY_NOTICE
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;
    }

    private function checkPaceVersion()
    {
        try {
            // get Pace version on default store
            $paceVersion = $this->_configData->getConfigValue(ConfigData::CONFIG_PACE_SYNC_VERSION, $storeID = 0);
            if (empty($paceVersion)) {
                throw new \Exception("Pace version not found");
            }

            $paceVersion = json_decode($paceVersion);
            $paceVersion = $paceVersion->plugins->magento2;
            $moduleVersion = $this->_moduleResource->getDbVersion('Pace_Pay');
            return version_compare($paceVersion, $moduleVersion) == 1;
        } catch (\Exception $e) {
            $this->_logger->debug($e->getMessage());
            return;
        }
    }
}
