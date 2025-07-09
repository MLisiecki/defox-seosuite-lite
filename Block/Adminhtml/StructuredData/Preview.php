<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Block\Adminhtml\StructuredData;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Structured data preview block
 * 
 * Provides UI for previewing and testing structured data.
 */
class Preview extends Template
{
    /**
     * Constructor
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }
    
    /**
     * Get AJAX URL for preview
     *
     * @return string
     */
    public function getPreviewUrl(): string
    {
        return $this->getUrl('defox_seosuite/structuredData/preview');
    }
    
    /**
     * Get Google Rich Results Test URL
     *
     * @return string
     */
    public function getGoogleTestUrl(): string
    {
        return 'https://search.google.com/test/rich-results';
    }
    
    /**
     * Get Schema.org Validator URL
     *
     * @return string
     */
    public function getSchemaValidatorUrl(): string
    {
        return 'https://validator.schema.org/';
    }
}
