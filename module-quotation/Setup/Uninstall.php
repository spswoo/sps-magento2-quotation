<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

/**
 * `bin/magento module:uninstall --remove-data SPS_Quotation`: removes the module's tables, settings and
 * customised copies of its e-mail templates. (The product attribute is removed by the attribute patch's revert.)
 * Orders and invoices placed from quotations are not touched.
 */
class Uninstall implements UninstallInterface
{
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        $setup->startSetup();
        $connection = $setup->getConnection();
        foreach (['sps_quotation_item', 'sps_quotation'] as $table) {
            $name = $setup->getTable($table);
            if ($connection->isTableExists($name)) {
                $connection->dropTable($name);
            }
        }
        $where = implode(' OR ', array_map(
            static fn(string $prefix): string => $connection->quoteInto('path LIKE ?', $prefix . '%'),
            ['sps_quotation/', 'carriers/spsquote/', 'carriers/spsquotation/']
        ));
        $connection->delete($setup->getTable('core_config_data'), $where);
        $connection->delete($setup->getTable('email_template'), ['orig_template_code LIKE ?' => 'sps_quotation_%']);
        $setup->endSetup();
    }
}
