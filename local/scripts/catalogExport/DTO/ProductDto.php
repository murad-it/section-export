<?php

declare(strict_types=1);

final class ProductDto
{
    public function __construct(
        public readonly string $xmlId,
        public readonly string $sectionXmlId,
        public readonly string $name,
        public readonly string $previewText,
        public readonly string $detailText,
        public readonly float $price,
        public readonly string $currency,
        public readonly ?string $previewPicture
    ) {}

    public function toArray(): array
    {
        return [
            'xml_id' => $this->xmlId,
            'section_xml_id' => $this->sectionXmlId,
            'name' => $this->name,
            'preview_text' => $this->previewText,
            'detail_text' => $this->detailText,
            'price' => $this->price,
            'currency' => $this->currency,
            'preview_picture' => $this->previewPicture
        ];
    }
}