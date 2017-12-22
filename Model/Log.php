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
 * @subpackage  Model
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Model;

use Lengow\Connector\Model\ResourceModel\Log as ResourceLog;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;

class Log extends AbstractModel
{
    /**
     * @var array $_fieldList field list for the table lengow_log
     * required => Required fields when creating registration
     * update   => Fields allowed when updating registration
     */
    protected $_fieldList = [
        'message' => ['required' => true, 'updated' => false],
        'category' => ['required' => true, 'updated' => false],
    ];

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime Magento datetime instance
     */
    protected $_dateTime;

    /**
     * Constructor
     *
     * @param \Magento\Framework\Model\Context $context Magento context instance
     * @param \Magento\Framework\Registry $registry Magento registry instance
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime Magento datetime instance
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime
    ) {
        parent::__construct($context, $registry);
        $this->_dateTime = $dateTime;
    }

    /**
     * Initialize logs model
     **
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceLog::class);
    }

    /**
     * Create Lengow log
     *
     * @param array $params
     *
     * @return Log|boolean
     */
    public function createLog($params = [])
    {
        foreach ($this->_fieldList as $key => $value) {
            if (!array_key_exists($key, $params) && $value['required']) {
                return false;
            }
        }
        foreach ($params as $key => $value) {
            $this->setData($key, $value);
        }
        $this->setData('date', $this->_dateTime->gmtDate('Y-m-d H:i:s'));
        try {
            return $this->save();
        } catch (\Exception $e) {
            return false;
        }
    }
}
