<?php
namespace Mab\AdminLocale\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\Filesystem\DirectoryList as AppDirectoryList;

class RemoveFrenchAdmin extends Command
{
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @param DirectoryList $directoryList
     */
    public function __construct(DirectoryList $directoryList)
    {
        $this->directoryList = $directoryList;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('mab:admin:remove-french')
            ->setDescription('Remove French language files from admin areas');
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Removing French language files from admin areas...</info>');

        $removedCount = 0;

        // Remove French static files from admin
        $staticAdminPath = $this->directoryList->getPath(AppDirectoryList::STATIC_VIEW) . '/adminhtml';
        if (is_dir($staticAdminPath)) {
            $frenchDirs = glob($staticAdminPath . '/*/fr_FR');
            foreach ($frenchDirs as $dir) {
                if (is_dir($dir)) {
                    $this->removeDirectory($dir);
                    $removedCount++;
                    $output->writeln('<comment>Removed: ' . $dir . '</comment>');
                }
            }

            // Also check for backend French files
            $backendFrenchPath = $staticAdminPath . '/Magento/backend/fr_FR';
            if (is_dir($backendFrenchPath)) {
                $this->removeDirectory($backendFrenchPath);
                $removedCount++;
                $output->writeln('<comment>Removed: ' . $backendFrenchPath . '</comment>');
            }
        }

        // Remove French language files from vendor admin areas
        $vendorPaths = [
            $this->directoryList->getPath(AppDirectoryList::ROOT) . '/vendor/magento/*/view/adminhtml/i18n/fr_FR.csv',
            $this->directoryList->getPath(AppDirectoryList::ROOT) . '/vendor/*/view/adminhtml/i18n/fr_FR.csv'
        ];

        foreach ($vendorPaths as $pattern) {
            $files = glob($pattern);
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $removedCount++;
                    $output->writeln('<comment>Removed: ' . $file . '</comment>');
                }
            }
        }

        $output->writeln('<info>Completed! Removed ' . $removedCount . ' French language files/directories from admin areas.</info>');
        
        return 0;
    }

    /**
     * Remove directory recursively
     *
     * @param string $dir
     * @return bool
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
}
