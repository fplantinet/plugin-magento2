<?php
/**
 * Copyright 2017 Lengow SAS
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category    Lengow
 * @package     Lengow_Connector
 * @subpackage  Controller
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Controller\Cron;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Lengow\Connector\Helper\Config as ConfigHelper;
use Lengow\Connector\Helper\Security as SecurityHelper;
use Lengow\Connector\Helper\Sync as SyncHelper;
use Lengow\Connector\Model\Import\Action as LengowAction;
use Lengow\Connector\Model\Import as LengowImport;

/**
 * CronController
 */
class Index extends Action
{
    /**
     * @var JsonHelper Magento json helper instance
     */
    protected $_jsonHelper;

    /**
     * @var ConfigHelper Lengow config helper instance
     */
    protected $_configHelper;

    /**
     * @var SecurityHelper Lengow security helper instance
     */
    protected $_securityHelper;

    /**
     * @var SyncHelper Lengow sync helper instance
     */
    protected $_syncHelper;

    /**
     * @var LengowAction Lengow action instance
     */
    protected $_action;

    /**
     * @var LengowImport Lengow import instance
     */
    protected $_import;

    /**
     * Constructor
     *
     * @param Context $context Magento action context instance
     * @param JsonHelper $jsonHelper Magento json helper instance
     * @param SecurityHelper $securityHelper Lengow security helper instance
     * @param ConfigHelper $configHelper Lengow config helper instance
     * @param SyncHelper $syncHelper Lengow sync helper instance
     * @param LengowImport $import Lengow import instance
     * @param LengowAction $action Lengow action instance
     */
    public function __construct(
        Context $context,
        JsonHelper $jsonHelper,
        SecurityHelper $securityHelper,
        ConfigHelper $configHelper,
        SyncHelper $syncHelper,
        LengowImport $import,
        LengowAction $action
    ) {
        $this->_jsonHelper = $jsonHelper;
        $this->_securityHelper = $securityHelper;
        $this->_configHelper = $configHelper;
        $this->_syncHelper = $syncHelper;
        $this->_import = $import;
        $this->_action = $action;
        parent::__construct($context);
    }

    /**
     * Cron Process (Import orders, check actions and send stats)
     */
    public function execute()
    {
        /**
         * List params
         * string  sync                Number of products exported
         * integer days                Import period
         * integer limit               Number of orders to import
         * integer store_id            Store id to import
         * string  marketplace_sku     Lengow marketplace order id to import
         * string  marketplace_name    Lengow marketplace name to import
         * string  created_from        import of orders since
         * string  created_to          import of orders until
         * integer delivery_address_id Lengow delivery address id to import
         * boolean debug_mode          Activate debug mode
         * boolean log_output          See logs (1) or not (0)
         * boolean get_sync            See synchronisation parameters in json format (1) or not (0)
         */
        $token = $this->getRequest()->getParam('token');
        if ($this->_securityHelper->checkWebserviceAccess($token)) {
            // get all store data for synchronisation with Lengow
            if ($this->getRequest()->getParam('get_sync') == 1) {
                $storeData = $this->_syncHelper->getSyncData();
                $this->getResponse()->setBody($this->_jsonHelper->jsonEncode($storeData));
            } else {
                $force = $this->getRequest()->getParam('force') !== null
                    ? (bool)$this->getRequest()->getParam('force')
                    : false;
                $logOutput = $this->getRequest()->getParam('log_output') !== null
                    ? (bool)$this->getRequest()->getParam('log_output')
                    : false;
                // get sync action if exists
                $sync = $this->getRequest()->getParam('sync');
                // sync catalogs id between Lengow and Magento
                if (!$sync || $sync === SyncHelper::SYNC_CATALOG) {
                    $this->_syncHelper->syncCatalog($force, $logOutput);
                }
                // sync orders between Lengow and Magento
                if ($sync === null || $sync === SyncHelper::SYNC_ORDER) {
                    // array of params for import order
                    $params = [
                        'type' => LengowImport::TYPE_CRON,
                        'log_output' => $logOutput,
                    ];
                    // check if the GET parameters are available
                    if ($this->getRequest()->getParam('debug_mode') !== null) {
                        $params['debug_mode'] = (bool)$this->getRequest()->getParam('debug_mode');
                    }
                    if ($this->getRequest()->getParam('days') !== null) {
                        $params['days'] = (int)$this->getRequest()->getParam('days');
                    }
                    if ($this->getRequest()->getParam('created_from') !== null) {
                        $params['created_from'] = (string)$this->getRequest()->getParam('created_from');
                    }
                    if ($this->getRequest()->getParam('created_to') !== null) {
                        $params['created_to'] = (string)$this->getRequest()->getParam('created_to');
                    }
                    if ($this->getRequest()->getParam('limit') !== null) {
                        $params['limit'] = (int)$this->getRequest()->getParam('limit');
                    }
                    if ($this->getRequest()->getParam('marketplace_sku') !== null) {
                        $params['marketplace_sku'] = (string)$this->getRequest()->getParam('marketplace_sku');
                    }
                    if ($this->getRequest()->getParam('marketplace_name') !== null) {
                        $params['marketplace_name'] = (string)$this->getRequest()->getParam('marketplace_name');
                    }
                    if ($this->getRequest()->getParam('delivery_address_id') !== null) {
                        $params['delivery_address_id'] = (int)$this->getRequest()->getParam('delivery_address_id');
                    }
                    if ($this->getRequest()->getParam('store_id') !== null) {
                        $params['store_id'] = (int)$this->getRequest()->getParam('store_id');
                    }
                    // synchronise orders
                    $this->_import->init($params);
                    $this->_import->exec();
                }
                // sync action between Lengow and Magento
                if ($sync === null || $sync === SyncHelper::SYNC_ACTION) {
                    $this->_action->checkFinishAction($logOutput);
                    $this->_action->checkOldAction($logOutput);
                    $this->_action->checkActionNotSent($logOutput);
                }
                // sync options between Lengow and Magento
                if ($sync === null || $sync === SyncHelper::SYNC_CMS_OPTION) {
                    $this->_syncHelper->setCmsOption($force, $logOutput);
                }
                // sync marketplaces between Lengow and Magento
                if ($sync === SyncHelper::SYNC_MARKETPLACE) {
                    $this->_syncHelper->getMarketplaces($force, $logOutput);
                }
                // sync status account between Lengow and Magento
                if ($sync === SyncHelper::SYNC_STATUS_ACCOUNT) {
                    $this->_syncHelper->getStatusAccount($force, $logOutput);
                }
                // sync plugin data between Lengow and Magento
                if ($sync === SyncHelper::SYNC_PLUGIN_DATA) {
                    $this->_syncHelper->getPluginData($force, $logOutput);
                }
                // sync option is not valid
                if ($sync && !$this->_syncHelper->isSyncAction($sync)) {
                    $errorMessage = __('Action: %1 is not a valid action', [$sync]);
                    $this->getResponse()->setStatusHeader(400, '1.1', 'Bad Request');
                    $this->getResponse()->setBody($errorMessage->__toString());
                }
            }
        } else {
            if ((bool)$this->_configHelper->get('ip_enable')) {
                $errorMessage = __('unauthorised IP: %1', [$this->_securityHelper->getRemoteIp()]);
            } else {
                $errorMessage = strlen($token) > 0
                    ? __('unauthorised access for this token: %1', [$token])
                    : __('unauthorised access: token parameter is empty');
            }
            $this->getResponse()->setStatusHeader(403, '1.1', 'Forbidden');
            $this->getResponse()->setBody($errorMessage->__toString());
        }
    }
}
