<?php

declare(strict_types=1);

final class PayloadBuilder
{
    /**
     * @param SectionDto[] $sections
     * @param ProductDto[] $products
     */
    public function build(array $sections, array $products): array
    {
        return [
            'sections' => array_map(
                static fn(SectionDto $section) => $section->toArray(),
                $sections
            ),
            'products' => array_map(
                static fn(ProductDto $product) => $product->toArray(),
                $products
            )
        ];
    }
}