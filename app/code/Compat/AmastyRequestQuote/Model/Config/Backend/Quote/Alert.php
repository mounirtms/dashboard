<?php
namespace Compat\AmastyRequestQuote\Model\Config\Backend\Quote;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\ResourceModel\AbstractResource;

class Alert extends \Magento\Framework\App\Config\Value
{
    public const CRON_STRING_PATH = 'crontab/default/jobs/amasty_quote_notify_admin/schedule/cron_expr';
    public const CRON_MODEL_PATH = 'crontab/default/jobs/amasty_quote_notify_admin/run/model';

    private $configValueFactory;
    private $runModelPath = '';
    private $logger;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Config\ValueFactory $configValueFactory,
        \Psr\Log\LoggerInterface $logger,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        $runModelPath = '',
        array $data = []
    ) {
        $this->runModelPath = $runModelPath;
        $this->configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->logger = $logger;
    }

    public function afterSave()
    {
        $time = $this->getData('groups/admin_notifications/fields/time/value');
        $frequency = $this->getData('groups/admin_notifications/fields/frequency/value');

        // Normalize time value for safety
        $minute = 0;
        $hour = 0;
        if (is_array($time)) {
            $minute = isset($time[1]) ? (int)$time[1] : 0;
            $hour = isset($time[0]) ? (int)$time[0] : 0;
        } elseif (is_string($time) && strpos($time, ',') !== false) {
            $parts = explode(',', $time);
            $hour = isset($parts[0]) ? (int)$parts[0] : 0;
            $minute = isset($parts[1]) ? (int)$parts[1] : 0;
        }

        $cronExprArray = [
            $minute,
            $hour,
            $frequency == \Magento\Cron\Model\Config\Source\Frequency::CRON_MONTHLY ? '1' : '*',
            '*',
            $frequency == \Magento\Cron\Model\Config\Source\Frequency::CRON_WEEKLY ? '1' : '*',
        ];

        $cronExprString = join(' ', $cronExprArray);

        try {
            $this->configValueFactory->create()->load(self::CRON_STRING_PATH, 'path')
                ->setValue($cronExprString)->setPath(self::CRON_STRING_PATH)->save();
            $this->configValueFactory->create()->load(self::CRON_MODEL_PATH, 'path')
                ->setValue($this->runModelPath)->setPath(self::CRON_MODEL_PATH)->save();
        } catch (\Exception $e) {
            $this->logger->error(__('We can\'t save the cron expression.'));
        }

        return parent::afterSave();
    }
}
