<?php

namespace Pace\Pay\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\DB\Ddl\Table;


class UpgradeSchema implements  UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        
        $setup->startSetup();
        if(version_compare($context->getVersion(),'0.0.1') > 0 )
        {
        //if version of your project is lower than 0.0.2 then implement this
            $isTableExist = $setup->tableExists('track_order_status');
            if(!$isTableExist)
            {
                $table =    $setup->getConnection()
            ->newTable($setup->getTable('track_order_status'))
            ->addColumn(
                'entity_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Entity Id'
            )
            ->addColumn(
                'order_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false],
                'order ID'
            )
            ->addColumn(
                'prev_status',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Previous Status'
            )
            ->addColumn(
                'current_status',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Current Status'
            )
            ->setComment("Track Order Status table");
                $setup->getConnection()->createTable($table);
                $setup->endSetup();
            }
        }
    }
}