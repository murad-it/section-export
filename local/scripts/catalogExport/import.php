<?php

define('BX_SECURITY_SHOW_MESSAGE', true);
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('STOP_STATISTICS', true);
define('PUBLIC_AJAX_MODE', true);

use Bitrix\Main\Loader;

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed'], JSON_THROW_ON_ERROR);
    die();
}

if (!Loader::includeModule('iblock')) {
    echo json_encode(['status' => 'error', 'message' => 'iblock module not installed'], JSON_THROW_ON_ERROR);
    die();
}

if (!Loader::includeModule('catalog')) {
    echo json_encode(['status' => 'error', 'message' => 'catalog module not installed'], JSON_THROW_ON_ERROR);
    die();
}

const IBLOCK_ID = 16;



$raw = file_get_contents('php://input');

if (!$raw) {
    echo json_encode(['status' => 'error', 'message' => 'Empty payload'], JSON_THROW_ON_ERROR);
    die();
}

$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON'], JSON_THROW_ON_ERROR);
    die();
}



$sections = $data['sections'] ?? [];
$products = $data['products'] ?? [];

$result = [
    'sections_created' => 0,
    'sections_updated' => 0,
    'products_created' => 0,
    'products_updated' => 0,
];



$sectionMap = importSections($sections, $result);
importProducts($products, $sectionMap, $result);



while (ob_get_level()) {
    ob_end_clean();
}

echo json_encode([
    'status' => 'success',
    'result' => $result
], JSON_UNESCAPED_UNICODE);

exit;



function importSections(array $sections, array &$result): array
{
    $map = [];

    foreach ($sections as $section) {

        $xmlId = $section['xml_id'] ?? null;

        if (!$xmlId) {
            continue;
        }

        $existing = CIBlockSection::GetList(
            [],
            [
                'IBLOCK_ID' => IBLOCK_ID,
                '=XML_ID' => $xmlId
            ],
            false,
            ['ID']
        )->Fetch();

        if ($existing) {

            $map[$xmlId] = (int)$existing['ID'];
            $result['sections_updated']++;

            continue;
        }

        $parentId = null;

        if (!empty($section['parent_xml_id'])) {
            $parentId = getSectionIdByXmlId($section['parent_xml_id']);
        }

        $fields = [
            'IBLOCK_ID' => IBLOCK_ID,
            'XML_ID' => $xmlId,
            'IBLOCK_SECTION_ID' => $parentId,
            'NAME' => $section['name'] ?? '',
            'DESCRIPTION' => $section['description'] ?? '',
            'CODE' => $section['code'] ?? '',
            'ACTIVE' => 'Y'
        ];

        $bs = new CIBlockSection();

        $id = $bs->Add($fields);

        if ($id) {

            $map[$xmlId] = (int)$id;
            $result['sections_created']++;
        }
    }

    return $map;
}



function getSectionIdByXmlId(string $xmlId): ?int
{
    $section = CIBlockSection::GetList(
        [],
        [
            'IBLOCK_ID' => IBLOCK_ID,
            '=XML_ID' => $xmlId
        ],
        false,
        ['ID']
    )->Fetch();

    return $section ? (int)$section['ID'] : null;
}



function importProducts(array $products, array $sectionMap, array &$result): void
{
    foreach ($products as $product) {

        $xmlId = $product['xml_id'] ?? null;

        if (!$xmlId) {
            continue;
        }

        $existing = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => IBLOCK_ID,
                '=XML_ID' => $xmlId
            ],
            false,
            false,
            ['ID']
        )->Fetch();

        $fields = [
            'IBLOCK_ID' => IBLOCK_ID,
            'XML_ID' => $xmlId,
            'NAME' => $product['name'] ?? '',
            'PREVIEW_TEXT' => $product['preview_text'] ?? '',
            'DETAIL_TEXT' => $product['detail_text'] ?? '',
            'IBLOCK_SECTION_ID' => $sectionMap[$product['section_xml_id']] ?? null,
            'ACTIVE' => 'Y'
        ];

        if (!empty($product['preview_picture'])) {

            $filePath = $_SERVER['DOCUMENT_ROOT'] . $product['preview_picture'];

            if (file_exists($filePath)) {
                $fields['PREVIEW_PICTURE'] = CFile::MakeFileArray($filePath);
            }
        }

        $el = new CIBlockElement();

        if ($existing) {

            $elementId = (int)$existing['ID'];
            $el->Update($elementId, $fields);

            $result['products_updated']++;

        } else {

            $elementId = $el->Add($fields);

            if ($elementId) {

                CCatalogProduct::Add([
                    'ID' => $elementId
                ]);

                $result['products_created']++;
            }
        }

        if ($elementId) {

            updatePrice(
                $elementId,
                (float)($product['price'] ?? 0),
                $product['currency'] ?? 'RUB'
            );
        }
    }
}



function updatePrice(int $productId, float $price, string $currency): void
{
    $existing = CPrice::GetList(
        [],
        [
            'PRODUCT_ID' => $productId
        ]
    )->Fetch();

    $fields = [
        'PRODUCT_ID' => $productId,
        'PRICE' => $price,
        'CURRENCY' => $currency,
        'CATALOG_GROUP_ID' => 1
    ];

    if ($existing) {

        CPrice::Update($existing['ID'], $fields);

    } else {

        CPrice::Add($fields);
    }
}