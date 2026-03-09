<?php

declare(strict_types=1);

final class SectionDto
{
    public function __construct(
        public readonly string $xmlId,
        public readonly ?string $parentXmlId,
        public readonly string $name,
        public readonly string $description,
        public readonly string $code
    ) {}

    public function toArray(): array
    {
        return [
            'xml_id' => $this->xmlId,
            'parent_xml_id' => $this->parentXmlId,
            'name' => $this->name,
            'description' => $this->description,
            'code' => $this->code
        ];
    }
}