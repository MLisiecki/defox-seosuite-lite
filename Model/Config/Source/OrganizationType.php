<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     GNU General Public License v3.0
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Organization type source model
 * 
 * Provides organization types for structured data configuration.
 */
class OrganizationType implements OptionSourceInterface
{
    /**
     * Get options array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'Organization', 'label' => __('Organization (General)')],
            ['value' => 'Corporation', 'label' => __('Corporation')],
            ['value' => 'LocalBusiness', 'label' => __('Local Business')],
            ['value' => 'Store', 'label' => __('Store')],
            ['value' => 'OnlineStore', 'label' => __('Online Store')],
            ['value' => 'Restaurant', 'label' => __('Restaurant')],
            ['value' => 'Hotel', 'label' => __('Hotel')],
            ['value' => 'ProfessionalService', 'label' => __('Professional Service')],
            ['value' => 'MedicalBusiness', 'label' => __('Medical Business')],
            ['value' => 'AutomotiveBusiness', 'label' => __('Automotive Business')],
            ['value' => 'FinancialService', 'label' => __('Financial Service')],
            ['value' => 'FoodEstablishment', 'label' => __('Food Establishment')],
            ['value' => 'SportsActivityLocation', 'label' => __('Sports Activity Location')],
            ['value' => 'EducationalOrganization', 'label' => __('Educational Organization')],
            ['value' => 'GovernmentOrganization', 'label' => __('Government Organization')],
            ['value' => 'NGO', 'label' => __('NGO (Non-Governmental Organization)')],
        ];
    }
}
