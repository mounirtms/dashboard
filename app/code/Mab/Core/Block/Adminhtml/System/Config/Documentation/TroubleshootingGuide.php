<?php
/**
 * MAB Core - Troubleshooting Guide Block
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
 * Class TroubleshootingGuide
 * 
 * Display troubleshooting guide in admin configuration
 */
class TroubleshootingGuide extends Field
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
        $troubleshootingContent = $this->getTroubleshootingContent();
        
        $html = '<div class="mab-troubleshooting-guide">';
        $html .= '<div class="mab-troubleshooting-header">';
        $html .= '<h3><i class="icon-wrench"></i> 🔧 MAB Extensions Troubleshooting Guide</h3>';
        $html .= '<p>Common issues and solutions for MAB Extensions:</p>';
        $html .= '</div>';
        
        $html .= '<div class="troubleshooting-content">';
        
        // Common Issues Section
        $html .= $this->renderTroubleshootingSection();
        
        if ($troubleshootingContent) {
            $html .= '<div class="detailed-guide">';
            $html .= '<h4>📚 Detailed Troubleshooting Guide</h4>';
            $html .= '<div class="guide-content">' . $this->formatContent($troubleshootingContent) . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= $this->getTroubleshootingStyles();
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render troubleshooting sections
     *
     * @return string
     */
    protected function renderTroubleshootingSection()
    {
        $html = '<div class="troubleshooting-sections">';
        
        // Module Issues
        $html .= '<div class="troubleshooting-section">';
        $html .= '<h4>🔍 Module Issues</h4>';
        $html .= '<div class="issue-item">';
        $html .= '<strong>Module not appearing in admin menu:</strong>';
        $html .= '<ul>';
        $html .= '<li>Clear cache: <code>php bin/magento cache:clean</code></li>';
        $html .= '<li>Check ACL permissions for your admin user</li>';
        $html .= '<li>Ensure module is enabled: <code>php bin/magento module:status</code></li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        $html .= '<div class="issue-item">';
        $html .= '<strong>Configuration pages showing errors:</strong>';
        $html .= '<ul>';
        $html .= '<li>Run setup upgrade: <code>php bin/magento setup:upgrade</code></li>';
        $html .= '<li>Recompile DI: <code>php bin/magento setup:di:compile</code></li>';
        $html .= '<li>Deploy static content: <code>php bin/magento setup:static-content:deploy</code></li>';
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Delivery Methods Issues
        $html .= '<div class="troubleshooting-section">';
        $html .= '<h4>🚚 Delivery Methods Issues</h4>';
        $html .= '<div class="issue-item">';
        $html .= '<strong>Sales → Delivery Methods page is empty:</strong>';
        $html .= '<ul>';
        $html .= '<li>Check if Yalidine carrier is active: <code>php bin/magento config:show carriers/yalidine/active</code></li>';
        $html .= '<li>Verify Mab_DeliveryOptions module is enabled</li>';
        $html .= '<li>Clear configuration cache: <code>php bin/magento cache:clean config</code></li>';
        $html .= '<li>Check system.log for carrier registration errors</li>';
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
        
        // License Issues
        $html .= '<div class="troubleshooting-section">';
        $html .= '<h4>🔐 License Issues</h4>';
        $html .= '<div class="issue-item">';
        $html .= '<strong>License validation failing:</strong>';
        $html .= '<ul>';
        $html .= '<li>Enable beta mode in Firebase configuration (all modules automatically licensed)</li>';
        $html .= '<li>Check Firebase configuration settings</li>';
        $html .= '<li>Verify internet connectivity for license validation</li>';
        $html .= '<li>Check var/log/mab_debug.log for license errors</li>';
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Performance Issues
        $html .= '<div class="troubleshooting-section">';
        $html .= '<h4>⚡ Performance Issues</h4>';
        $html .= '<div class="issue-item">';
        $html .= '<strong>Slow loading admin pages:</strong>';
        $html .= '<ul>';
        $html .= '<li>Disable debug mode in production</li>';
        $html .= '<li>Enable production mode: <code>php bin/magento deploy:mode:set production</code></li>';
        $html .= '<li>Configure Redis for cache backend</li>';
        $html .= '<li>Disable verbose logging when not needed</li>';
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get troubleshooting content from file
     *
     * @return string
     */
    protected function getTroubleshootingContent()
    {
        try {
            $troubleshootingFile = BP . '/app/code/Mab/TROUBLESHOOTING.md';
            if (file_exists($troubleshootingFile)) {
                return file_get_contents($troubleshootingFile);
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
     * Get troubleshooting styles
     *
     * @return string
     */
    protected function getTroubleshootingStyles()
    {
        return '
        <style type="text/css">
        .mab-troubleshooting-guide {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 20px 0;
        }
        
        .mab-troubleshooting-header h3 {
            color: #4361ee;
            font-size: 18px;
            margin-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        .troubleshooting-sections {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        
        .troubleshooting-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 16px;
        }
        
        .troubleshooting-section h4 {
            color: #4361ee;
            margin: 0 0 15px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .issue-item {
            margin-bottom: 15px;
        }
        
        .issue-item strong {
            color: #dc3545;
            display: block;
            margin-bottom: 8px;
        }
        
        .issue-item ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .issue-item ul li {
            margin-bottom: 5px;
            line-height: 1.5;
        }
        
        .troubleshooting-content code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 13px;
            color: #e83e8c;
        }
        
        .troubleshooting-content pre {
            background: #f1f3f4;
            padding: 12px;
            border-radius: 6px;
            overflow-x: auto;
            margin: 10px 0;
        }
        
        .troubleshooting-content pre code {
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
        </style>';
    }
}