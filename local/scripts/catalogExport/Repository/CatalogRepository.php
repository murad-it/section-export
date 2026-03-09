<?php

declare(strict_types=1);

class CatalogRepository
{
    public function getSectionsTree(int $rootSectionId): array
    {
        $sections = [];
        $map = [];

        $root = CIBlockSection::GetByID($rootSectionId)->Fetch();

        if (!$root) {
            return [];
        }

        $rs = CIBlockSection::GetList(
            ['LEFT_MARGIN' => 'ASC'],
            [
                'IBLOCK_ID' => IBLOCK_ID,
                '>=LEFT_MARGIN' => $root['LEFT_MARGIN'],
                '<=RIGHT_MARGIN' => $root['RIGHT_MARGIN']
            ],
            false,
            [
                'ID',
                'IBLOCK_SECTION_ID',
                'XML_ID',
                'NAME',
                'DESCRIPTION',
                'CODE'
            ]
        );

        $rows = [];

        while ($row = $rs->Fetch()) {

            $xml = $row['XML_ID'] ?: 'section_' . $row['ID'];

            $map[$row['ID']] = $xml;
            $rows[$row['ID']] = $row;
        }

        foreach ($rows as $id => $row) {

            $parentXml = null;

            if (!empty($row['IBLOCK_SECTION_ID'])) {
                $parentXml = $map[$row['IBLOCK_SECTION_ID']] ?? null;
            }

            $sections[] = new SectionDto(
                $map[$id],
                $parentXml,
                $row['NAME'] ?? '',
                $row['DESCRIPTION'] ?? '',
                $row['CODE'] ?? ''
            );
        }

        return $sections;
    }



    public function getProducts(int $sectionId, int $limit, int $offset): array
    {
        $products = [];
        $res = CIBlockElement::GetList(
            ['ID' => 'ASC'],
            [
                'IBLOCK_ID' => IBLOCK_ID,
                //'IBLOCK_SECTION_ID' => $sectionId,
                'INCLUDE_SUBSECTIONS' => 'Y',
                'ACTIVE' => 'Y'
            ],
            false,
            [
                'nTopCount' => $limit,
                'nOffset' => $offset
            ],
            [
                'ID',
                'XML_ID',
                'NAME',
                'PREVIEW_TEXT',
                'DETAIL_TEXT',
                'IBLOCK_SECTION_ID',
                'PREVIEW_PICTURE'
            ]
        );

        while ($row = $res->GetNext()) {
            $price = CPrice::GetBasePrice($row['ID']);

            $sectionXmlId = 'root';

            if (!empty($row['IBLOCK_SECTION_ID'])) {

                $section = CIBlockSection::GetByID($row['IBLOCK_SECTION_ID'])->Fetch();

                if ($section && !empty($section['XML_ID'])) {
                    $sectionXmlId = $section['XML_ID'];
                } else {
                    $sectionXmlId = 'section_' . $row['IBLOCK_SECTION_ID'];
                }
            }

            $products[] = new ProductDto(
                $row['XML_ID'] ?: 'product_' . $row['ID'],
                $sectionXmlId,
                $row['NAME'] ?? '',
                $row['PREVIEW_TEXT'] ?? '',
                $row['DETAIL_TEXT'] ?? '',
                $price ? (float)$price['PRICE'] : 0.0,
                $price['CURRENCY'] ?? 'RUB',
                $row['PREVIEW_PICTURE']
                    ? CFile::GetPath($row['PREVIEW_PICTURE'])
                    : null
            );
        }
        return $products;
    }
}