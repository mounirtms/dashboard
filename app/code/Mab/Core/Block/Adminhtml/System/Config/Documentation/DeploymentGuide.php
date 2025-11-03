<?php
/**
 * MAB Core - Deployment Guide Block
 * 
 * @category    Mab
 * @package     Mab_Core
 * @author      Mounir Abderrahmani <mounir.webdev@gmail.com>
 * @copyright   Copyright (c) 2025 MAB Extensions
 * @license     https://opensource.org/licenses/MIT MIT License
 */

namespace Mab\Core\Block\Adminhtml\System\Config\Documentation;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Module\Dir\Reader;

/**
 * Class DeploymentGuide
 * 
 * Display deployment guide in admin configuration
 */
class DeploymentGuide extends Field
{
    /**
     * @var Reader
     */
    protected $moduleReader;

    /**
     * @param Context $context
     * @param Reader $moduleReader
     * @param array $data
     */
    public function __construct(
        Context $context,
        Reader $moduleReader,
        array $data = []
    ) {
        $this->moduleReader = $moduleReader;
        parent::__construct($context, $data);
    }

    /**
     * Remove scope label
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element HTML
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $deploymentContent = $this->getDeploymentContent();
        
        $html = '<div class="mab-deployment-guide">';
        $html .= '<div class="mab-deployment-header">';
        $html .= '<h3><i class="icon-rocket"></i> 🚀 MAB Extensions Deployment Guide</h3>';
        $html .= '<p>Professional deployment instructions for MAB Extensions:</p>';
        $html .= '</div>';
        
        $html .= '<div class="deployment-content">';
        
        // Quick Deployment Section
        $html .= $this->renderQuickDeploymentSection();
        
        if ($deploymentContent) {
            $html .= '<div class="detailed-guide">';
            $html .= '<h4>📚 Detailed Deployment Guide</h4>';
            $html .= '<div class="guide-content">' . $this->formatContent($deploymentContent) . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= $this->getDeploymentStyles();
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render quick deployment sections
     *
     * @return string
     */
    protected function renderQuickDeploymentSection()
    {
        $html = '<div class="deployment-sections">';
        
        // Prerequisites
        $html .= '<div class="deployment-section">';
        $html .= '<h4>📋 Prerequisites</h4>';
        $html .= '<div class="deployment-item">';
        $html .= '<ul>';
        $html .= '<li><strong>Magento:</strong> 2.4.6+ Community/Commerce Edition</li>';
        $html .= '<li><strong>PHP:</strong> 8.1+ with required extensions</li>';
        $html .= '<li><strong>Database:</strong> MySQL 8.0+ or MariaDB 10.4+</li>';
        $html .= '<li><strong>Web Server:</strong> Apache 2.4+ or Nginx 1.18+</li>';
        $html .= '<li><strong>Composer:</strong> 2.0+ for dependency management</li>';
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Installation Steps
        $html .= '<div class="deployment-section">';
        $html .= '<h4>🛠️ Installation Steps</h4>';
        $html .= '<div class="deployment-item">';
        $html .= '<ol>';
        $html .= '<li><strong>Download & Extract:</strong><br>';
        $html .= '<code>cd app/code && unzip mab-extensions.zip</code></li>';
        
        $html .= '<li><strong>Enable Modules:</strong><br>';
        $html .= '<code>php bin/magento module:enable Mab_Core Mab_DeliveryOptions Mab_CheckoutCustomization</code></li>';
        
        $html .= '<li><strong>Run Setup:</strong><br>';
        $html .= '<code>php bin/magento setup:upgrade</code></li>';
        
        $html .= '<li><strong>Compile Dependencies:</strong><br>';
        $html .= '<code>php bin/magento setup:di:compile</code></li>';
        
        $html .= '<li><strong>Deploy Static Content:</strong><br>';
        $html .= '<code>php bin/magento setup:static-content:deploy</code></li>';
        
        $html .= '<li><strong>Clear Cache:</strong><br>';
        $html .= '<code>php bin/magento cache:flush</code></li>';
        $html .= '</ol>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Configuration
        $html .= '<div class="deployment-section">';
        $html .= '<h4>⚙️ Post-Installation Configuration</h4>';
        $html .= '<div class="deployment-item">';
        $html .= '<ol>';
        $html .= '<li><strong>Core Settings:</strong><br>';
        $html .= 'Navigate to <em>Stores → Configuration → MAB Extensions → Core Settings</em></li>';
        
        $html .= '<li><strong>License Configuration:</strong><br>';
        $html .= 'Enter your license key or enable beta mode for testing</li>';
        
        $html .= '<li><strong>Firebase Setup (Optional):</strong><br>';
        $html .= 'Configure Firebase integration for license validation</li>';
        
        $html .= '<li><strong>Module Activation:</strong><br>';
        $html .= 'Enable required modules through Module Management section</li>';
        
        $html .= '<li><strong>Yalidine Configuration:</strong><br>';
        $html .= 'Configure Yalidine carrier in <em>Stores → Configuration → Sales → Shipping Methods</em></li>';
        $html .= '</ol>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Production Deployment
        $html .= '<div class="deployment-section">';
        $html .= '<h4>🚀 Production Deployment</h4>';
        $html .= '<div class="deployment-item">';
        $html .= '<ol>';
        $html .= '<li><strong>Set Production Mode:</strong><br>';
        $html .= '<code>php bin/magento deploy:mode:set production</code></li>';
        
        $html .= '<li><strong>Configure Caching:</strong><br>';
        $html .= 'Set up Redis for cache and sessions<br>';
        $html .= '<code>php bin/magento cache:enable</code></li>';
        
        $html .= '<li><strong>Set File Permissions:</strong><br>';
        $html .= '<code>find . -type f -exec chmod 644 {} \;</code><br>';
        $html .= '<code>find . -type d -exec chmod 755 {} \;</code></li>';
        
        $html .= '<li><strong>Enable Full Page Cache:</strong><br>';
        $html .= 'Configure Varnish or built-in FPC</li>';
        
        $html .= '<li><strong>Disable Debug Modes:</strong><br>';
        $html .= 'Turn off debug mode in all MAB Extensions</li>';
        $html .= '</ol>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Verification
        $html .= '<div class="deployment-section">';
        $html .= '<h4>✅ Deployment Verification</h4>';
        $html .= '<div class="deployment-item">';
        $html .= '<ul>';
        $html .= '<li><strong>Check Module Status:</strong><br>';
        $html .= '<code>php bin/magento module:status | grep Mab_</code></li>';
        
        $html .= '<li><strong>Verify Admin Menu:</strong><br>';
        $html .= 'Check for "MAB Extensions" menu in admin panel</li>';
        
        $html .= '<li><strong>Test Configuration:</strong><br>';
        $html .= 'Access MAB Core Settings and verify license status</li>';
        
        $html .= '<li><strong>Frontend Testing:</strong><br>';
        $html .= 'Test checkout process and delivery options</li>';
        
        $html .= '<li><strong>Error Log Check:</strong><br>';
        $html .= 'Review <code>var/log/system.log</code> for any errors</li>';
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Maintenance
        $html .= '<div class="deployment-section">';
        $html .= '<h4>🔧 Ongoing Maintenance</h4>';
        $html .= '<div class="deployment-item">';
        $html .= '<ul>';
        $html .= '<li><strong>Regular Updates:</strong> Check for MAB Extensions updates</li>';
        $html .= '<li><strong>License Renewal:</strong> Monitor license expiration dates</li>';
        $html .= '<li><strong>Performance Monitoring:</strong> Use MAB Core performance tools</li>';
        $html .= '<li><strong>Backup Strategy:</strong> Regular database and code backups</li>';
        $html .= '<li><strong>Security Updates:</strong> Keep Magento and extensions updated</li>';
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get deployment content from file
     *
     * @return string
     */
    protected function getDeploymentContent()
    {
        try {
            $deploymentFile = BP . '/app/code/Mab/DEPLOYMENT_GUIDE.md';
            if (file_exists($deploymentFile)) {
                return file_get_contents($deploymentFile);
            }
        } catch (\Exception $e) {
            // Silently fail if file not found
        }
        
        return '';
    }

    /**
     * Format content for display
     *
     * @param string $content
     * @return string
     */
    protected function formatContent($content)
    {
        // Basic markdown to HTML conversion
        $content = htmlspecialchars($content);
        
        // Headers
        $content = preg_replace('/^# (.*$)/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^## (.*$)/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^### (.*$)/m', '<h4>$1</h4>', $content);
        
        // Bold and italic
        $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
        
        // Code blocks
        $content = preg_replace('/```(.*?)```/s', '<pre><code>$1</code></pre>', $content);
        $content = preg_replace('/`(.*?)`/', '<code>$1</code>', $content);
        
        // Links
        $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $content);
        
        // Line breaks
        $content = nl2br($content);
        
        return $content;
    }

    /**
     * Get deployment styles
     *
     * @return string
     */
    protected function getDeploymentStyles()
    {
        return '
        <style type="text/css">
        .mab-deployment-guide {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 20px 0;
        }
        
        .mab-deployment-header h3 {
            color: #4361ee;
            font-size: 18px;
            margin-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        .deployment-sections {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        
        .deployment-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 16px;
        }
        
        .deployment-section h4 {
            color: #4361ee;
            margin: 0 0 15px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .deployment-item ul,
        .deployment-item ol {
            margin: 0;
            padding-left: 20px;
        }
        
        .deployment-item ul li,
        .deployment-item ol li {
            margin-bottom: 8px;
            line-height: 1.6;
        }
        
        .deployment-item strong {
            color: #333;
            font-weight: 600;
        }
        
        .deployment-content code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 13px;
            color: #e83e8c;
            display: inline-block;
            margin: 2px 0;
        }
        
        .deployment-content pre {
            background: #f1f3f4;
            padding: 12px;
            border-radius: 6px;
            overflow-x: auto;
            margin: 10px 0;
        }
        
        .deployment-content pre code {
            background: none;
            padding: 0;
            color: #333;
        }
        
        .detailed-guide {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .detailed-guide h4 {
            color: #4361ee;
            margin-bottom: 15px;
        }
        
        .guide-content {
            max-height: 400px;
            overflow-y: auto;
            padding: 16px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
        
        .deployment-item em {
            font-style: italic;
            color: #666;
        }
        </style>';
    }
}