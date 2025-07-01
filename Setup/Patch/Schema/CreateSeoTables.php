<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Setup\Patch\Schema;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Patch for creating SEO tables
 */
class CreateSeoTables implements SchemaPatchInterface
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

        $this->createTemplateTable();

        $this->createCrosslinkTable();
        $this->createImageLogTable();
        $this->createCacheTable();

        $this->schemaSetup->endSetup();
        
        return $this;
    }

    /**
     * Create template table
     *
     * @return void
     */
    private function createTemplateTable(): void
    {
        $table = $this->schemaSetup->getConnection()->newTable(
            $this->schemaSetup->getTable('defox_seosuite_template')
        )->addColumn(
            'template_id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
            'Template ID'
        )->addColumn(
            'name',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Name'
        )->addColumn(
            'type',
            Table::TYPE_TEXT,
            64,
            ['nullable' => false],
            'Type'
        )->addColumn(
            'entity_type',
            Table::TYPE_TEXT,
            64,
            ['nullable' => false],
            'Entity Type'
        )->addColumn(
            'store_id',
            Table::TYPE_SMALLINT,
            null,
            ['unsigned' => true, 'nullable' => false, 'default' => '0'],
            'Store ID'
        )->addColumn(
            'template_text',
            Table::TYPE_TEXT,
            '64k',
            ['nullable' => false],
            'Template Text'
        )->addColumn(
            'is_active',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => false, 'default' => '1'],
            'Is Active'
        )->addColumn(
            'priority',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '0'],
            'Priority'
        )->addColumn(
            'conditions_serialized',
            Table::TYPE_TEXT,
            '2M',
            [],
            'Conditions Serialized'
        )->addColumn(
            'created_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Created At'
        )->addColumn(
            'updated_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
            'Updated At'
        )->addIndex(
            $this->schemaSetup->getIdxName(
                'defox_seosuite_template',
                ['type', 'entity_type', 'store_id', 'is_active'],
                AdapterInterface::INDEX_TYPE_INDEX
            ),
            ['type', 'entity_type', 'store_id', 'is_active'],
            ['type' => AdapterInterface::INDEX_TYPE_INDEX]
        )->addIndex(
            $this->schemaSetup->getIdxName('defox_seosuite_template', ['store_id']),
            ['store_id'],
            ['type' => AdapterInterface::INDEX_TYPE_INDEX]
        )->addForeignKey(
            $this->schemaSetup->getFkName('defox_seosuite_template', 'store_id', 'store', 'store_id'),
            'store_id',
            $this->schemaSetup->getTable('store'),
            'store_id',
            Table::ACTION_CASCADE
        )->setComment('Defox SEO Suite Template Table');

        $this->schemaSetup->getConnection()->createTable($table);
    }

    /**
     * Create crosslink table
     *
     * @return void
     */
    private function createCrosslinkTable(): void
    {
        $table = $this->schemaSetup->getConnection()->newTable(
            $this->schemaSetup->getTable('defox_seosuite_crosslink')
        )->addColumn(
            'crosslink_id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
            'Crosslink ID'
        )->addColumn(
            'keyword',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Keyword'
        )->addColumn(
            'target_url',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Target URL'
        )->addColumn(
            'store_id',
            Table::TYPE_SMALLINT,
            null,
            ['unsigned' => true, 'nullable' => false, 'default' => '0'],
            'Store ID'
        )->addColumn(
            'target_blank',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => false, 'default' => '0'],
            'Target Blank'
        )->addColumn(
            'title',
            Table::TYPE_TEXT,
            255,
            [],
            'Title'
        )->addColumn(
            'priority',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '0'],
            'Priority'
        )->addColumn(
            'max_replacements',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '1'],
            'Max Replacements'
        )->addColumn(
            'is_active',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => false, 'default' => '1'],
            'Is Active'
        )->addColumn(
            'created_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Created At'
        )->addColumn(
            'updated_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
            'Updated At'
        )->addIndex(
            $this->schemaSetup->getIdxName(
                'defox_seosuite_crosslink',
                ['keyword', 'store_id'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['keyword', 'store_id'],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        )->addIndex(
            $this->schemaSetup->getIdxName('defox_seosuite_crosslink', ['store_id']),
            ['store_id'],
            ['type' => AdapterInterface::INDEX_TYPE_INDEX]
        )->addIndex(
            $this->schemaSetup->getIdxName('defox_seosuite_crosslink', ['is_active']),
            ['is_active'],
            ['type' => AdapterInterface::INDEX_TYPE_INDEX]
        )->addIndex(
            $this->schemaSetup->getIdxName('defox_seosuite_crosslink', ['priority']),
            ['priority'],
            ['type' => AdapterInterface::INDEX_TYPE_INDEX]
        )->addForeignKey(
            $this->schemaSetup->getFkName('defox_seosuite_crosslink', 'store_id', 'store', 'store_id'),
            'store_id',
            $this->schemaSetup->getTable('store'),
            'store_id',
            Table::ACTION_CASCADE
        )->setComment('Defox SEO Suite Crosslink Table');

        $this->schemaSetup->getConnection()->createTable($table);
    }

    /**
     * Create image log table
     *
     * @return void
     */
    private function createImageLogTable(): void
    {
        $table = $this->schemaSetup->getConnection()->newTable(
            $this->schemaSetup->getTable('defox_seosuite_image_log')
        )->addColumn(
            'log_id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
            'Log ID'
        )->addColumn(
            'image_path',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Image Path'
        )->addColumn(
            'original_size',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '0', 'unsigned' => true],
            'Original Size'
        )->addColumn(
            'optimized_size',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '0', 'unsigned' => true],
            'Optimized Size'
        )->addColumn(
            'saved_bytes',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '0', 'unsigned' => true],
            'Saved Bytes'
        )->addColumn(
            'saved_percentage',
            Table::TYPE_DECIMAL,
            '5,2',
            ['nullable' => false, 'default' => '0.00'],
            'Saved Percentage'
        )->addColumn(
            'status',
            Table::TYPE_TEXT,
            32,
            ['nullable' => false, 'default' => 'success'],
            'Status'
        )->addColumn(
            'message',
            Table::TYPE_TEXT,
            '64k',
            [],
            'Message'
        )->addColumn(
            'created_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Created At'
        )->addColumn(
            'updated_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
            'Updated At'
        )->addIndex(
            $this->schemaSetup->getIdxName('defox_seosuite_image_log', ['image_path']),
            ['image_path'],
            ['type' => AdapterInterface::INDEX_TYPE_INDEX]
        )->addIndex(
            $this->schemaSetup->getIdxName('defox_seosuite_image_log', ['status']),
            ['status'],
            ['type' => AdapterInterface::INDEX_TYPE_INDEX]
        )->setComment('Defox SEO Suite Image Optimization Log Table');

        $this->schemaSetup->getConnection()->createTable($table);
    }

    /**
     * Create cache table
     *
     * @return void
     */
    private function createCacheTable(): void
    {
        $table = $this->schemaSetup->getConnection()->newTable(
            $this->schemaSetup->getTable('defox_seosuite_cache')
        )->addColumn(
            'cache_id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
            'Cache ID'
        )->addColumn(
            'cache_key',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Cache Key'
        )->addColumn(
            'cache_data',
            Table::TYPE_TEXT,
            '16M',
            ['nullable' => false],
            'Cache Data'
        )->addColumn(
            'cache_tags',
            Table::TYPE_TEXT,
            '64k',
            [],
            'Cache Tags'
        )->addColumn(
            'lifetime',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '0', 'unsigned' => true],
            'Lifetime'
        )->addColumn(
            'creation_time',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '0', 'unsigned' => true],
            'Creation Time'
        )->addColumn(
            'expiration_time',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '0', 'unsigned' => true],
            'Expiration Time'
        )->addIndex(
            $this->schemaSetup->getIdxName(
                'defox_seosuite_cache',
                ['cache_key'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['cache_key'],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        )->addIndex(
            $this->schemaSetup->getIdxName('defox_seosuite_cache', ['expiration_time']),
            ['expiration_time'],
            ['type' => AdapterInterface::INDEX_TYPE_INDEX]
        )->setComment('Defox SEO Suite Cache Table');

        $this->schemaSetup->getConnection()->createTable($table);
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
