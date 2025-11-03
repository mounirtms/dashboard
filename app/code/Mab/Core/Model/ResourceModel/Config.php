<?php
declare(strict_types=1);

namespace Mab\Core\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Config extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('mab_core_config', 'config_id');
    }
}
