<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * Product attribute "Shipping Quote Required" (Yes/No).
 *
 * Uses only core source/backend models, so the attribute (and its values) stay harmless when the module is
 * disabled. addAttribute() updates an existing attribute with the same code, so stores that already have it keep
 * their values. Removed only by `bin/magento module:uninstall --remove-data SPS_Quotation`.
 */
class AddQuoteRequiredAttribute implements DataPatchInterface, PatchRevertableInterface
{
    public const CODE = 'sps_shipping_quote_required';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->addAttribute(Product::ENTITY, self::CODE, [
            'type' => 'int',
            'label' => 'Shipping Quote Required',
            'input' => 'boolean',
            'source' => Boolean::class,
            'global' => Attribute::SCOPE_GLOBAL,
            'default' => '0',
            'required' => false,
            'user_defined' => true,
            'visible_on_front' => false,
            'used_in_product_listing' => true,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'apply_to' => 'simple,configurable,bundle,grouped',
            'group' => 'General',
            'sort_order' => 75,
            'note' => 'Large or freight items. When the cart contains this product, only the shipping methods allowed in '
                . 'Delivery Methods > Shipping Quote Required are offered and the customer is asked to request a quote.',
        ]);
        return $this;
    }

    public function revert(): void
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->removeAttribute(Product::ENTITY, self::CODE);
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
