<?php
/**
 * Copyright © SPS. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0), see LICENSE.txt.
 */
declare(strict_types=1);

namespace SPS\Quotation\Model;

use Magento\Catalog\Api\Data\ProductCustomOptionInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;

/**
 * Product custom options on quotation items: validation against the product's option definitions, labels for
 * display (admin, e-mails) and definitions for the admin editor.
 *
 * Stored format (= buy request fragment): ['options' => [option_id => value_id | value_id[] | text]].
 * Supported option types: drop-down, radio, checkbox, multiple select, text field, text area.
 */
class ItemOptions
{
    private const SELECT_TYPES = ['drop_down', 'radio', 'checkbox', 'multiple'];
    private const TEXT_TYPES = ['field', 'area'];

    public function __construct(private readonly ProductRepositoryInterface $productRepository)
    {
    }

    /**
     * Option definitions for the admin editor.
     *
     * @return array<int, array{id: int, title: string, type: string, required: bool, values: array<int, array{id: int, title: string}>}>
     */
    public function getDefinitions(Product $product): array
    {
        $definitions = [];
        foreach ($product->getOptions() ?: [] as $option) {
            $type = (string) $option->getType();
            if (!in_array($type, array_merge(self::SELECT_TYPES, self::TEXT_TYPES), true)) {
                continue;
            }
            $values = [];
            foreach ($option->getValues() ?: [] as $value) {
                $values[] = ['id' => (int) $value->getOptionTypeId(), 'title' => (string) $value->getTitle()];
            }
            $definitions[] = [
                'id' => (int) $option->getOptionId(),
                'title' => (string) $option->getTitle(),
                'type' => $type,
                'required' => (bool) $option->getIsRequire(),
                'values' => $values,
            ];
        }
        return $definitions;
    }

    /**
     * Keep only valid options of this product; checks required options when $requireAll is true.
     *
     * @param array<int|string, mixed> $input option_id => value
     * @return array<string, mixed> buy request fragment (empty when no options)
     * @throws LocalizedException
     */
    public function normalize(Product $product, array $input, bool $requireAll): array
    {
        $clean = [];
        foreach ($product->getOptions() ?: [] as $option) {
            $id = (int) $option->getOptionId();
            $type = (string) $option->getType();
            $raw = $input[$id] ?? ($input[(string) $id] ?? null);
            if (in_array($type, self::SELECT_TYPES, true)) {
                $allowed = [];
                foreach ($option->getValues() ?: [] as $value) {
                    $allowed[] = (int) $value->getOptionTypeId();
                }
                $chosen = array_values(array_intersect(array_map('intval', (array) $raw), $allowed));
                if ($chosen) {
                    $clean[$id] = in_array($type, ['checkbox', 'multiple'], true) ? $chosen : $chosen[0];
                }
            } elseif (in_array($type, self::TEXT_TYPES, true)) {
                $text = trim(strip_tags((string) (is_scalar($raw) ? $raw : '')));
                if ($text !== '') {
                    $clean[$id] = mb_substr($text, 0, 1000);
                }
            }
            if ($requireAll && $option->getIsRequire() && !isset($clean[$id])) {
                throw new LocalizedException(
                    __('Please choose "%1" for %2.', $option->getTitle(), $product->getName())
                );
            }
        }
        return $clean ? ['options' => $clean] : [];
    }

    /**
     * Human-readable options of a stored item.
     *
     * @param array<string, mixed> $buyRequestOptions
     * @return array<int, array{label: string, value: string}>
     */
    public function getLabels(?int $productId, array $buyRequestOptions, ?int $storeId = null): array
    {
        $selected = $buyRequestOptions['options'] ?? [];
        if (!$productId || !$selected) {
            return [];
        }
        try {
            /** @var Product $product */
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (\Throwable) {
            return [];
        }
        $labels = [];
        foreach ($product->getOptions() ?: [] as $option) {
            $id = (int) $option->getOptionId();
            if (!isset($selected[$id])) {
                continue;
            }
            if (in_array((string) $option->getType(), self::SELECT_TYPES, true)) {
                $titles = [];
                foreach ($option->getValues() ?: [] as $value) {
                    if (in_array((int) $value->getOptionTypeId(), array_map('intval', (array) $selected[$id]), true)) {
                        $titles[] = (string) $value->getTitle();
                    }
                }
                $text = implode(', ', $titles);
            } else {
                $text = (string) $selected[$id];
            }
            if ($text !== '') {
                $labels[] = ['label' => (string) $option->getTitle(), 'value' => $text];
            }
        }
        return $labels;
    }
}
