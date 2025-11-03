<?php
/**
 * MAB Core - README Viewer Block
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
use Magento\Framework\Module\Manager;

/**
 * Class ReadmeViewer
 * 
 * Display module README files in admin configuration
 */
class ReadmeViewer extends Field
{
    /**
     * @var Reader
     */
    protected $moduleReader;

    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * @var array
     */
    protected $mabModules = [
        'Mab_Core' => 'Core Module',
        'Mab_DeliveryOptions' => 'Delivery Options',
        'Mab_CheckoutCustomization' => 'Checkout Customization',
        'Mab_AdminLocale' => 'Admin Locale Control',
        'Mab_SourceSelector' => 'Source Selector',
        'Mab_SocialLogin' => 'Social Login',
        'Mab_VisualEffects' => 'Visual Effects',
        'Mab_License' => 'License Management',
        'Mab_GuestCheckout' => 'Guest Checkout',
        'Mab_Theme' => 'Theme Settings'
    ];

    /**
     * @param Context $context
     * @param Reader $moduleReader
     * @param Manager $moduleManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        Reader $moduleReader,
        Manager $moduleManager,
        array $data = []
    ) {
        $this->moduleReader = $moduleReader;
        $this->moduleManager = $moduleManager;
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
        $html = '<div class="mab-readme-viewer">';
        $html .= '<div class="mab-readme-header">';
        $html .= '<h3><i class="icon-book"></i> MAB Extensions Documentation</h3>';
        $html .= '<p>Click on any module below to view its comprehensive documentation:</p>';
        $html .= '</div>';

        $html .= '<div class="mab-readme-modules">';
        
        foreach ($this->mabModules as $moduleName => $moduleTitle) {
            if ($this->moduleManager->isEnabled($moduleName)) {
                $readmeContent = $this->getModuleReadme($moduleName);
                $status = $readmeContent ? 'available' : 'missing';
                
                $html .= '<div class="mab-module-doc" data-module="' . $moduleName . '">';
                $html .= '<div class="module-header">';
                $html .= '<span class="module-icon">📚</span>';
                $html .= '<strong>' . $moduleTitle . '</strong>';
                $html .= '<span class="status-badge status-' . $status . '">' . ucfirst($status) . '</span>';
                $html .= '<button type="button" class="btn-toggle" onclick="toggleModuleDoc(\'' . $moduleName . '\')">View</button>';
                $html .= '</div>';
                
                if ($readmeContent) {
                    $html .= '<div class="module-content" id="content-' . $moduleName . '" style="display:none;">';
                    $html .= '<div class="readme-content">' . $this->formatReadmeContent($readmeContent) . '</div>';
                    $html .= '</div>';
                } else {
                    $html .= '<div class="module-content no-readme" id="content-' . $moduleName . '" style="display:none;">';
                    $html .= '<p><em>README file not found for this module.</em></p>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
        }

        $html .= '</div>';
        $html .= $this->getReadmeViewerScript();
        $html .= $this->getReadmeViewerStyles();
        $html .= '</div>';

        return $html;
    }

    /**
     * Get module README content
     *
     * @param string $moduleName
     * @return string|false
     */
    protected function getModuleReadme($moduleName)
    {
        try {
            $moduleDir = $this->moduleReader->getModuleDir('', $moduleName);
            $readmePath = $moduleDir . '/README.md';
            
            if (file_exists($readmePath)) {
                return file_get_contents($readmePath);
            }
        } catch (\Exception $e) {
            // Module directory not found or README not accessible
        }
        
        return false;
    }

    /**
     * Format README content for HTML display
     *
     * @param string $content
     * @return string
     */
    protected function formatReadmeContent($content)
    {
        // Basic markdown to HTML conversion
        $content = htmlspecialchars($content);
        
        // Headers
        $content = preg_replace('/^### (.*$)/m', '<h4>$1</h4>', $content);
        $content = preg_replace('/^## (.*$)/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^# (.*$)/m', '<h2>$1</h2>', $content);
        
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
     * Get JavaScript for README viewer
     *
     * @return string
     */
    protected function getReadmeViewerScript()
    {
        return '
        <script type="text/javascript">
        function toggleModuleDoc(moduleName) {
            var content = document.getElementById("content-" + moduleName);
            var button = event.target;
            
            if (content.style.display === "none") {
                content.style.display = "block";
                button.textContent = "Hide";
                button.classList.add("active");
            } else {
                content.style.display = "none";
                button.textContent = "View";
                button.classList.remove("active");
            }
        }
        </script>';
    }

    /**
     * Get CSS styles for README viewer
     *
     * @return string
     */
    protected function getReadmeViewerStyles()
    {
        return '
        <style type="text/css">
        .mab-readme-viewer {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 10px 0;
        }
        
        .mab-readme-header h3 {
            color: #4361ee;
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        
        .mab-readme-header p {
            color: #6c757d;
            margin: 0 0 20px 0;
        }
        
        .mab-module-doc {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        
        .module-header {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            border-radius: 6px 6px 0 0;
        }
        
        .module-icon {
            font-size: 16px;
        }
        
        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-missing {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-toggle {
            margin-left: auto;
            padding: 4px 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-toggle:hover {
            background: #0056b3;
        }
        
        .btn-toggle.active {
            background: #28a745;
        }
        
        .module-content {
            padding: 16px;
            border-top: 1px solid #dee2e6;
        }
        
        .readme-content {
            max-height: 400px;
            overflow-y: auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .readme-content h2, .readme-content h3, .readme-content h4 {
            color: #4361ee;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        
        .readme-content code {
            background: #f8f9fa;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: "Monaco", "Consolas", monospace;
        }
        
        .readme-content pre {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            overflow-x: auto;
            border: 1px solid #e9ecef;
        }
        
        .no-readme {
            color: #6c757d;
            font-style: italic;
        }
        </style>';
    }
}