# Defox SEO Suite Lite

[![License: GPLv3](https://img.shields.io/badge/License-GPLv3-yellow.svg)](https://opensource.org/licenses/MIT)
[![Magento 2.4.7](https://img.shields.io/badge/Magento-2.4.7-orange.svg)](https://devdocs.magento.com/)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)

A comprehensive SEO Lite solution for Magento 2.4.7 that provides advanced meta tag templates, canonical URLs, structured data, XML sitemaps, and SEO-friendly URLs with an intuitive admin interface.

## Table of Contents

- [Features](#features)
- [About This Edition](#about-this-edition)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Features Overview](#features-overview)
- [Usage](#usage)
- [Development](#development)
- [Contributing](#contributing)
- [Troubleshooting](#troubleshooting)
- [Upgrading to Full Version](#upgrading-to-full-version)
- [License](#license)
- [Support](#support)

## Features

### 🏷️ **Meta Tag Templates**
- Dynamic template system with variable support
- Templates for title, description, keywords, and robots meta tags
- Open Graph support (title, description, type, image)
- Entity-specific templates (products, categories, CMS pages)
- Template prioritization and conditional application
- Variable processor with extensive placeholder support

### 🔗 **Canonical URLs**
- Advanced canonical URL management
- Product canonical URLs with multiple strategies
- Category canonical URLs with pagination support
- Filter parameter handling
- Configurable exclusions for products and categories
- High-performance caching system

### 📊 **Structured Data (JSON-LD)**
- Schema.org compliant structured data generation
- Support for Organization, WebSite, WebPage schemas
- Product schema with pricing, reviews, and availability
- Category, Article, and Review schemas
- Product attribute mapping to schema properties
- Built-in schema validation
- Performance-optimized caching

### 🗺️ **XML Sitemap**
- Enhanced XML sitemap generation
- Support for products, categories, and CMS pages
- Image inclusion in sitemap entries
- Hreflang support for multilingual sites
- Automatic search engine notifications
- Analytics and statistics dashboard
- XML validation and error reporting
- Automated CRON scheduling

### 🌐 **Friendly URLs**
- SEO-friendly URLs for category filters
- Customizable URL patterns and separators
- Attribute name mapping to friendly terms
- Multi-value parameter support
- URL rewriting and routing

### ⚡ **Cache Management**
- Dedicated cache types for different features
- Configurable cache backends
- Automatic cache invalidation
- Performance optimization
- Lifetime management

### 🎛️ **Admin Interface**
- Comprehensive admin panel
- Meta tag template management
- Sitemap dashboard with analytics
- Sitemap generator and validator
- Structured data preview
- Detailed configuration options

## About This Edition

Defox SEO Suite Lite is a streamlined version of our comprehensive SEO Suite platform, specifically curated to provide essential SEO functionality while maintaining the same high-quality codebase. This edition shares its foundation with the full enterprise version, which means:

- **Future-Ready Architecture**: The module is built with extensibility in mind, allowing seamless upgrades to the full version
- **Enterprise-Grade Code Quality**: All components follow the same strict coding standards and design patterns used in our commercial offering
- **Modular Design**: Some classes and interfaces may appear unused in this Lite edition but serve as foundation components for advanced features in the full version
- **Professional Foundation**: This approach ensures consistency, maintainability, and provides a clear upgrade path for growing businesses

While this Lite edition focuses on core SEO functionality, the underlying architecture supports advanced enterprise features like AI-powered content optimization, advanced analytics, multi-site management, and custom integrations available in the full version.

## Requirements

- **Magento**: 2.4.7+
- **PHP**: 8.2+
- **Dependencies**:
  - Magento_Store
  - Magento_Catalog
  - Magento_Cms
  - Magento_UrlRewrite
  - Magento_Sitemap
  - Magento_LayeredNavigation
  - Magento_Swatches

## Installation

### Via Composer (Recommended)

```bash
composer require defox/module-seosuite
php bin/magento module:enable Defox_SEOSuite
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

### Manual Installation

1. Download the module files
2. Extract to `app/code/Defox/SEOSuite/`
3. Run the installation commands:

```bash
php bin/magento module:enable Defox_SEOSuite
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

## Configuration

Navigate to **Stores → Configuration → Defox → SEO Suite** in your Magento admin panel. Do not forget to create a folder for sitemap.xml on Your host.

### Basic Setup

1. **General Settings**: Enable the module and configure debug mode
2. **Meta Tags**: Enable template system and configure override behavior
3. **Canonical URLs**: Enable canonical URL generation for different entity types
4. **Structured Data**: Enable JSON-LD generation and configure schema types
5. **XML Sitemap**: Configure sitemap generation settings and schedules

### Advanced Configuration

- **Organization Information**: Configure business details for structured data
- **Product Attribute Mapping**: Map product attributes to schema.org properties
- **Cache Settings**: Optimize performance with cache configuration
- **URL Patterns**: Customize friendly URL generation

## Features Overview

### Meta Tag Templates

Create dynamic meta tag templates using variables:

```
Product Title: {{product.name}} - {{category.name}} | {{store.name}}
Meta Description: {{product.name}} - {{product.short_description}} Starting at {{product.price}}
```

**Available Variables:**
- **Products**: `{{product.name}}`, `{{product.sku}}`, `{{product.price}}`, `{{category.name}}`, etc.
- **Categories**: `{{category.name}}`, `{{category.description}}`, `{{parent_category.name}}`, etc.
- **CMS Pages**: `{{cms_page.title}}`, `{{cms_page.content}}`, `{{cms_page.identifier}}`, etc.
- **Store**: `{{store.name}}`, `{{store.url}}`, etc.

### Structured Data Examples

The module automatically generates structured data for:

**Product Schema:**
```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Product Name",
  "description": "Product Description",
  "brand": {
    "@type": "Brand",
    "name": "Brand Name"
  },
  "offers": {
    "@type": "Offer",
    "price": "99.99",
    "priceCurrency": "USD",
    "availability": "https://schema.org/InStock"
  }
}
```

**Organization Schema:**
```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Your Company",
  "url": "https://yoursite.com",
  "logo": "https://yoursite.com/logo.png",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "123 Main St",
    "addressLocality": "City",
    "addressRegion": "State",
    "postalCode": "12345"
  }
}
```

### XML Sitemap Features

- **Automatic Generation**: Scheduled via CRON
- **Multi-entity Support**: Products, categories, CMS pages
- **Image Sitemaps**: Include product images
- **Hreflang**: Multi-language support
- **Analytics**: Track sitemap performance
- **Validation**: Ensure XML compliance

## Usage

### Managing Meta Tag Templates

1. Navigate to **SEO Suite → Meta Tag Templates**
2. Create new templates for different entity types
3. Use variables to create dynamic content
4. Set priorities and conditions for template application

### Generating Sitemaps

1. Go to **SEO Suite Sitemap → Generation**
2. Configure sitemap settings
3. Generate manually or set up automatic generation
4. Monitor progress in the Analytics dashboard

### Previewing Structured Data

1. Visit **SEO Suite → Structured Data Preview**
2. Enter entity IDs to preview generated schema
3. Validate schema.org compliance
4. Test different configuration settings

### Console Commands

Generate sitemap via command line:
```bash
php bin/magento defox:sitemap:generate
```

## Development

### File Structure

```
Defox/SEOSuite/
├── Api/                    # API interfaces
├── Block/                  # Block classes
├── Console/                # Console commands
├── Controller/             # Controllers
├── Cron/                   # Cron jobs
├── Helper/                 # Helper classes
├── Model/                  # Models and business logic
├── Observer/               # Event observers
├── Plugin/                 # Plugins/interceptors
├── Setup/                  # Installation/upgrade scripts
├── Template/               # Template processors
├── Ui/                     # UI components
├── view/                   # Frontend/admin templates
├── etc/                    # Configuration files
└── i18n/                   # Translations
```

### Key Classes

- **`Model/MetaTag/Manager`**: Core meta tag template processing
- **`Model/StructuredData/AbstractGenerator`**: Base for structured data generators
- **`Model/Sitemap/Generator/XmlGenerator`**: XML sitemap generation
- **`Helper/StructuredDataManager`**: Structured data management
- **`Template/VariableProcessorFactory`**: Variable processing system

### Extending the Module

#### Adding Custom Variables

```php
<?php
namespace YourNamespace\YourModule\Template\Processor;

use Defox\SEOSuite\Template\AbstractVariableProcessor;

class CustomProcessor extends AbstractVariableProcessor
{
    public function getAvailableVariables(): array
    {
        return [
            'custom.variable' => 'Custom Variable Description'
        ];
    }
    
    protected function processVariable(string $variable, $entity): string
    {
        switch ($variable) {
            case 'custom.variable':
                return $this->getCustomValue($entity);
            default:
                return parent::processVariable($variable, $entity);
        }
    }
}
```

#### Adding Custom Structured Data Generators

```php
<?php
namespace YourNamespace\YourModule\Model\StructuredData\Generator;

use Defox\SEOSuite\Model\StructuredData\AbstractGenerator;

class CustomGenerator extends AbstractGenerator
{
    public function getSchemaType(): string
    {
        return 'CustomType';
    }
    
    public function canHandle($entity): bool
    {
        return $entity instanceof \Your\Custom\Entity;
    }
    
    protected function doGenerate($entity, array $context): array
    {
        return [
            '@type' => $this->getSchemaType(),
            'name' => $entity->getName(),
            // ... additional schema properties
        ];
    }
}
```

## Contributing

We welcome contributions! Please follow these guidelines:

### Development Workflow

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature-name`
3. Make your changes following our coding standards
4. Write tests for new functionality
5. Ensure all tests pass: `vendor/bin/phpunit`
6. Submit a pull request

### Coding Standards

- Follow Magento 2 coding standards
- Use PHP 8.2+ features and type declarations
- Write comprehensive PHPDoc blocks
- Follow SOLID principles
- Implement proper error handling

## Troubleshooting

### Common Issues

**Cache Issues:**
```bash
php bin/magento cache:flush
php bin/magento cache:clean
```

**Compilation Issues:**
```bash
php bin/magento setup:di:compile
```

**Permission Issues:**
```bash
chmod -R 755 var/ pub/ generated/
```

### Debug Mode

Enable debug mode in **Stores → Configuration → Defox → SEO Suite → General Settings** for detailed logging.

### Performance Optimization

- Enable all cache types in the module configuration
- Use Redis or Varnish for better caching performance
- Configure appropriate cache lifetimes
- Monitor cache hit rates in the admin dashboard

## Changelog

### Version 1.0.0
- Initial release
- Meta tag template system
- Canonical URL management
- Structured data generation
- XML sitemap enhancement
- Friendly URL system
- Comprehensive admin interface

## License

This module is licensed under the GNU General Public License v3.0.
See the LICENSE file for more information.

### Professional Services
For custom development, enterprise support, or consultation services, please contact the development team.

---

**Made with ❤️ by [Marcin Lisiecki](mailto:marcin@lisi.pl) - Defox SEO Suite Lite Edition**
