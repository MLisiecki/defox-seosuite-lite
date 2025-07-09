<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Patch to add canonical disabled columns
 */
class AddCanonicalDisabledColumns implements SchemaPatchInterface
{
    /**
     * @var SchemaSetupInterface
     */
    private SchemaSetupInterface $schemaSetup;

    /**
     * @param SchemaSetupInterface $schemaSetup
     */
    public function __construct(
        SchemaSetupInterface $schemaSetup
    ) {
        $this->schemaSetup = $schemaSetup;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->schemaSetup->startSetup();

        $this->addColumnToProductTable();
        $this->addColumnToCategoryTable();

        $this->schemaSetup->endSetup();
        
        return $this;
    }

    /**
     * Add column to product table
     *
     * @return void
     */
    private function addColumnToProductTable(): void
    {
        $connection = $this->schemaSetup->getConnection();
        $tableName = $this->schemaSetup->getTable('catalog_product_entity');
        
        // Check if column already exists
        if (!$connection->tableColumnExists($tableName, 'seo_canonical_disabled')) {
            $connection->addColumn(
                $tableName,
                'seo_canonical_disabled',
                [
                    'type' => Table::TYPE_SMALLINT,
                    'nullable' => false,
                    'default' => '0',
                    'comment' => 'SEO Canonical Disabled'
                ]
            );
        }
    }

    /**
     * Add column to category table
     *
     * @return void
     */
    private function addColumnToCategoryTable(): void
    {
        $connection = $this->schemaSetup->getConnection();
        $tableName = $this->schemaSetup->getTable('catalog_category_entity');
        
        // Check if column already exists
        if (!$connection->tableColumnExists($tableName, 'seo_canonical_disabled')) {
            $connection->addColumn(
                $tableName,
                'seo_canonical_disabled',
                [
                    'type' => Table::TYPE_SMALLINT,
                    'nullable' => false,
                    'default' => '0',
                    'comment' => 'SEO Canonical Disabled'
                ]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [
            CreateSeoTables::class
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
