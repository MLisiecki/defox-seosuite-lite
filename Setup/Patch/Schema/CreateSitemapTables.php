<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     GPLv3
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Setup\Patch\Schema;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Create sitemap related tables
 */
class CreateSitemapTables implements SchemaPatchInterface
{
    /**
     * @var SchemaSetupInterface
     */
    private SchemaSetupInterface $schemaSetup;

    /**
     * Constructor
     *
     * @param SchemaSetupInterface $schemaSetup
     */
    public function __construct(SchemaSetupInterface $schemaSetup)
    {
        $this->schemaSetup = $schemaSetup;
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $this->schemaSetup->startSetup();
        
        $this->createSitemapExclusionTable();
        $this->createSitemapAdditionalLinksTable();
        
        $this->schemaSetup->endSetup();
        
        return $this;
    }

    /**
     * Create sitemap exclusion table
     *
     * @return void
     * @throws \Zend_Db_Exception
     */
    private function createSitemapExclusionTable(): void
    {
        $connection = $this->schemaSetup->getConnection();
        $tableName = $this->schemaSetup->getTable('defox_seosuite_sitemap_exclusion');
        
        if ($connection->isTableExists($tableName)) {
            return;
        }
        
        $table = $connection->newTable($tableName)
            ->addColumn(
                'exclusion_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'nullable' => false,
                    'primary' => true,
                    'unsigned' => true
                ],
                'Exclusion ID'
            )
            ->addColumn(
                'entity_type',
                Table::TYPE_TEXT,
                50,
                ['nullable' => false],
                'Entity Type (product, category, cms_page)'
            )
            ->addColumn(
                'entity_id',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Entity ID'
            )
            ->addColumn(
                'store_id',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false, 'unsigned' => true, 'default' => '0'],
                'Store ID'
            )
            ->addColumn(
                'exclusion_type',
                Table::TYPE_TEXT,
                50,
                ['nullable' => false, 'default' => 'manual'],
                'Exclusion Type (manual, auto_outofstock, auto_disabled)'
            )
            ->addColumn(
                'reason',
                Table::TYPE_TEXT,
                255,
                ['nullable' => true],
                'Exclusion Reason'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Created At'
            )
            ->addIndex(
                $this->schemaSetup->getIdxName(
                    'defox_seosuite_sitemap_exclusion',
                    ['entity_type', 'entity_id', 'store_id'],
                    AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                ['entity_type', 'entity_id', 'store_id'],
                ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
            )
            ->addIndex(
                $this->schemaSetup->getIdxName(
                    'defox_seosuite_sitemap_exclusion',
                    ['entity_type'],
                    AdapterInterface::INDEX_TYPE_INDEX
                ),
                ['entity_type'],
                ['type' => AdapterInterface::INDEX_TYPE_INDEX]
            )
            ->addIndex(
                $this->schemaSetup->getIdxName(
                    'defox_seosuite_sitemap_exclusion',
                    ['store_id'],
                    AdapterInterface::INDEX_TYPE_INDEX
                ),
                ['store_id'],
                ['type' => AdapterInterface::INDEX_TYPE_INDEX]
            )
            ->addForeignKey(
                $this->schemaSetup->getFkName(
                    'defox_seosuite_sitemap_exclusion',
                    'store_id',
                    'store',
                    'store_id'
                ),
                'store_id',
                $this->schemaSetup->getTable('store'),
                'store_id',
                Table::ACTION_CASCADE
            )
            ->setComment('Defox SEO Suite - Sitemap Exclusions');
        
        $connection->createTable($table);
    }

    /**
     * Create sitemap additional links table
     *
     * @return void
     * @throws \Zend_Db_Exception
     */
    private function createSitemapAdditionalLinksTable(): void
    {
        $connection = $this->schemaSetup->getConnection();
        $tableName = $this->schemaSetup->getTable('defox_seosuite_sitemap_additional_links');
        
        if ($connection->isTableExists($tableName)) {
            return;
        }
        
        $table = $connection->newTable($tableName)
            ->addColumn(
                'link_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'nullable' => false,
                    'primary' => true,
                    'unsigned' => true
                ],
                'Link ID'
            )
            ->addColumn(
                'url',
                Table::TYPE_TEXT,
                1024,
                ['nullable' => false],
                'URL'
            )
            ->addColumn(
                'title',
                Table::TYPE_TEXT,
                255,
                ['nullable' => true],
                'Link Title'
            )
            ->addColumn(
                'store_id',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false, 'unsigned' => true, 'default' => '0'],
                'Store ID'
            )
            ->addColumn(
                'changefreq',
                Table::TYPE_TEXT,
                50,
                ['nullable' => false, 'default' => 'weekly'],
                'Change Frequency'
            )
            ->addColumn(
                'priority',
                Table::TYPE_DECIMAL,
                '2,1',
                ['nullable' => false, 'default' => '0.5'],
                'Priority'
            )
            ->addColumn(
                'group_code',
                Table::TYPE_TEXT,
                50,
                ['nullable' => true],
                'Group Code for HTML Sitemap'
            )
            ->addColumn(
                'is_active',
                Table::TYPE_SMALLINT,
                null,
                ['nullable' => false, 'default' => '1'],
                'Is Active'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Created At'
            )
            ->addColumn(
                'updated_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
                'Updated At'
            )
            ->addIndex(
                $this->schemaSetup->getIdxName(
                    'defox_seosuite_sitemap_additional_links',
                    ['store_id'],
                    AdapterInterface::INDEX_TYPE_INDEX
                ),
                ['store_id'],
                ['type' => AdapterInterface::INDEX_TYPE_INDEX]
            )
            ->addIndex(
                $this->schemaSetup->getIdxName(
                    'defox_seosuite_sitemap_additional_links',
                    ['is_active'],
                    AdapterInterface::INDEX_TYPE_INDEX
                ),
                ['is_active'],
                ['type' => AdapterInterface::INDEX_TYPE_INDEX]
            )
            ->addIndex(
                $this->schemaSetup->getIdxName(
                    'defox_seosuite_sitemap_additional_links',
                    ['group_code'],
                    AdapterInterface::INDEX_TYPE_INDEX
                ),
                ['group_code'],
                ['type' => AdapterInterface::INDEX_TYPE_INDEX]
            )
            ->addForeignKey(
                $this->schemaSetup->getFkName(
                    'defox_seosuite_sitemap_additional_links',
                    'store_id',
                    'store',
                    'store_id'
                ),
                'store_id',
                $this->schemaSetup->getTable('store'),
                'store_id',
                Table::ACTION_CASCADE
            )
            ->setComment('Defox SEO Suite - Sitemap Additional Links');
        
        $connection->createTable($table);
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [
            CreateSeoTables::class
        ];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
