<?php
/**
 * Copyright © MAB, Inc. All rights reserved.
 */
namespace Mab\Core\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Module\Status as ModuleStatus;
use Magento\Framework\App\State as AppState;

/**
 * Console command to disable resource-intensive modules
 */
class DisableModulesCommand extends Command
{
    const MODULES_OPTION = 'modules';
    const RECOMMENDED_OPTION = 'recommended';
    
    /**
     * @var ModuleManager
     */
    private $moduleManager;
    
    /**
     * @var ModuleStatus
     */
    private $moduleStatus;
    
    /**
     * @var AppState
     */
    private $appState;
    
    /**
     * List of modules that are known to be resource-intensive
     *
     * @var array
     */
    private $resourceIntensiveModules = [
        'Magento_Elasticsearch',
        'Magento_Elasticsearch7',
        'Magento_OpenSearch',
        'Amasty_Shopby',
        'Amasty_Storelocator',
        'Magefan_Blog',
        'Sm_MegaMenu',
        'Magento_LayeredNavigation',
        'Magento_Review',
        'Magento_Reward',
        'Magento_TargetRule',
        'Magento_AdvancedSearch',
        'Magento_CatalogSearch',
        'Magento_Swatches',
        'Magento_Reports',
        'Magento_Analytics',
        'Magento_SendFriend',
        'Magento_MultipleWishlist',
        'Magento_Wishlist',
        'Magento_GiftMessage',
        'Magento_GiftRegistry',
        'Magento_GiftCard',
        'Magento_GiftWrapping'
    ];
    
    /**
     * @param ModuleManager $moduleManager
     * @param ModuleStatus $moduleStatus
     * @param AppState $appState
     */
    public function __construct(
        ModuleManager $moduleManager,
        ModuleStatus $moduleStatus,
        AppState $appState
    ) {
        $this->moduleManager = $moduleManager;
        $this->moduleStatus = $moduleStatus;
        $this->appState = $appState;
        parent::__construct();
    }
    
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('mab:modules:disable-heavy');
        $this->setDescription('Disable resource-intensive modules to reduce CPU and RAM usage');
        $this->addOption(
            self::MODULES_OPTION,
            'm',
            InputOption::VALUE_REQUIRED,
            'Comma-separated list of modules to disable'
        );
        $this->addOption(
            self::RECOMMENDED_OPTION,
            'r',
            InputOption::VALUE_NONE,
            'Disable recommended resource-intensive modules'
        );
        
        parent::configure();
    }
    
    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->appState->setAreaCode('adminhtml');
        
        $output->writeln('<info>Disabling resource-intensive modules to reduce CPU and RAM usage...</info>');
        
        $modulesToDisable = [];
        
        // Handle specific modules option
        $modulesOption = $input->getOption(self::MODULES_OPTION);
        if ($modulesOption) {
            $modulesToDisable = explode(',', $modulesOption);
            $modulesToDisable = array_map('trim', $modulesToDisable);
        }
        
        // Handle recommended modules option
        $recommendedOption = $input->getOption(self::RECOMMENDED_OPTION);
        if ($recommendedOption) {
            $modulesToDisable = array_merge($modulesToDisable, $this->resourceIntensiveModules);
        }
        
        if (empty($modulesToDisable)) {
            $output->writeln('<comment>No modules specified for disabling.</comment>');
            $output->writeln('<info>Use --recommended (-r) to disable recommended modules or --modules (-m) to specify modules.</info>');
            return Command::SUCCESS;
        }
        
        // Filter to only include enabled modules
        $enabledModules = array_filter($modulesToDisable, [$this->moduleManager, 'isEnabled']);
        
        if (empty($enabledModules)) {
            $output->writeln('<comment>None of the specified modules are currently enabled.</comment>');
            return Command::SUCCESS;
        }
        
        try {
            // Disable the modules
            $this->moduleStatus->setIsEnabled(false, $enabledModules);
            
            $output->writeln('<info>Successfully disabled the following modules:</info>');
            foreach ($enabledModules as $module) {
                $output->writeln("  - {$module}");
            }
            
            $output->writeln('');
            $output->writeln('<comment>Remember to run the following commands to apply changes:</comment>');
            $output->writeln('  php bin/magento setup:upgrade');
            $output->writeln('  php bin/magento setup:di:compile');
            $output->writeln('  php bin/magento cache:clean');
            
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to disable modules: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}