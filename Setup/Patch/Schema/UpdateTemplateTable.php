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
 * Patch for updating template table with meta tag fields
 * This patch adds specific fields for meta tags to the template table
 */
class UpdateTemplateTable implements SchemaPatchInterface
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

        $this->updateTemplateTable();

        $this->schemaSetup->endSetup();
        
        return $this;
    }

    /**
     * Update template table with specific meta tag fields
     *
     * @return void
     */
    private function updateTemplateTable(): void
    {
        $connection = $this->schemaSetup->getConnection();
        $tableName = $this->schemaSetup->getTable('defox_seosuite_template');

        // Drop template_text column which is too generic
        $connection->dropColumn($tableName, 'template_text');

        // Add specific meta tag columns
        $connection->addColumn(
            $tableName,
            'meta_title_template',
            [
                'type' => Table::TYPE_TEXT,
                'length' => '64k',
                'nullable' => true,
                'comment' => 'Meta Title Template'
            ]
        );

        $connection->addColumn(
            $tableName,
            'meta_description_template',
            [
                'type' => Table::TYPE_TEXT,
                'length' => '64k',
                'nullable' => true,
                'comment' => 'Meta Description Template'
            ]
        );

        $connection->addColumn(
            $tableName,
            'meta_keywords_template',
            [
                'type' => Table::TYPE_TEXT,
                'length' => '64k',
                'nullable' => true,
                'comment' => 'Meta Keywords Template'
            ]
        );

        $connection->addColumn(
            $tableName,
            'meta_robots_template',
            [
                'type' => Table::TYPE_TEXT,
                'length' => 255,
                'nullable' => true,
                'comment' => 'Meta Robots Template'
            ]
        );

        // Add Open Graph columns
        $connection->addColumn(
            $tableName,
            'og_title_template',
            [
                'type' => Table::TYPE_TEXT,
                'length' => '64k',
                'nullable' => true,
                'comment' => 'Open Graph Title Template'
            ]
        );

        $connection->addColumn(
            $tableName,
            'og_description_template',
            [
                'type' => Table::TYPE_TEXT,
                'length' => '64k',
                'nullable' => true,
                'comment' => 'Open Graph Description Template'
            ]
        );

        $connection->addColumn(
            $tableName,
            'og_type_template',
            [
                'type' => Table::TYPE_TEXT,
                'length' => 255,
                'nullable' => true,
                'comment' => 'Open Graph Type Template'
            ]
        );

        $connection->addColumn(
            $tableName,
            'og_image_template',
            [
                'type' => Table::TYPE_TEXT,
                'length' => '64k',
                'nullable' => true,
                'comment' => 'Open Graph Image Template'
            ]
        );
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
