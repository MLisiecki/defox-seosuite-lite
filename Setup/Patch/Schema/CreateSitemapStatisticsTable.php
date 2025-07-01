<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Create sitemap statistics table
 */
class CreateSitemapStatisticsTable implements SchemaPatchInterface
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
    public function apply(): void
    {
        $installer = $this->schemaSetup;
        $installer->startSetup();

        $table = $installer->getConnection()->newTable(
            $installer->getTable('defox_seosuite_sitemap_stats')
        )->addColumn(
            'stat_id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
            'Statistics ID'
        )->addColumn(
            'store_id',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => false, 'unsigned' => true, 'default' => 0],
            'Store ID'
        )->addColumn(
            'generation_time',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Generation Time'
        )->addColumn(
            'duration_seconds',
            Table::TYPE_DECIMAL,
            '10,3',
            ['nullable' => false, 'default' => 0],
            'Duration in Seconds'
        )->addColumn(
            'total_urls',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'unsigned' => true, 'default' => 0],
            'Total URLs Generated'
        )->addColumn(
            'files_generated',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => false, 'unsigned' => true, 'default' => 0],
            'Number of Files Generated'
        )->addColumn(
            'total_file_size',
            Table::TYPE_BIGINT,
            null,
            ['nullable' => false, 'unsigned' => true, 'default' => 0],
            'Total File Size in Bytes'
        )->addColumn(
            'errors_count',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => false, 'unsigned' => true, 'default' => 0],
            'Number of Errors'
        )->addColumn(
            'provider_stats',
            Table::TYPE_TEXT,
            '64k',
            ['nullable' => true],
            'Provider Statistics (JSON)'
        )->addColumn(
            'performance_metrics',
            Table::TYPE_TEXT,
            '64k',
            ['nullable' => true],
            'Performance Metrics (JSON)'
        )->addColumn(
            'success',
            Table::TYPE_BOOLEAN,
            null,
            ['nullable' => false, 'default' => true],
            'Generation Success'
        )->addIndex(
            $installer->getIdxName('defox_seosuite_sitemap_stats', ['store_id']),
            ['store_id']
        )->addIndex(
            $installer->getIdxName('defox_seosuite_sitemap_stats', ['generation_time']),
            ['generation_time']
        )->addIndex(
            $installer->getIdxName('defox_seosuite_sitemap_stats', ['success']),
            ['success']
        )->addForeignKey(
            $installer->getFkName('defox_seosuite_sitemap_stats', 'store_id', 'store', 'store_id'),
            'store_id',
            $installer->getTable('store'),
            'store_id',
            Table::ACTION_CASCADE
        )->setComment(
            'Defox SEO Suite Sitemap Generation Statistics'
        );

        $installer->getConnection()->createTable($table);
        $installer->endSetup();
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [CreateSitemapTables::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
