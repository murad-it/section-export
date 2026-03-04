<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

use Bitrix\Main\Loader;
use Bitrix\Main\Web\HttpClient;

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('STOP_STATISTICS', true);

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
    die('Required modules not installed');
}

const IBLOCK_ID = 16;
const PACKAGE_SIZE = 2000;
const TARGET_URL = 'https://portal.loc/import.php';



/**
 * DTO раздела
 */
class SectionDto
{
    public string $xmlId;
    public ?string $parentXmlId = null;
    public string $name = '';
    public string $description = '';
    public string $code = '';

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



/**
 * DTO товара
 */
class ProductDto
{
    public string $xmlId;
    public string $sectionXmlId;
    public string $name = '';
    public string $previewText = '';
    public string $detailText = '';
    public float $price = 0.0;
    public string $currency = 'RUB';
    public ?string $previewPicture = null;

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



/**
 * Репозиторий каталога
 */
class CatalogExportRepository
{
    public function getSectionsTree(int $rootSectionId): array
    {
        $sections = [];
        $map = [];

        $root = CIBlockSection::GetByID($rootSectionId)->Fetch();

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

        while ($row = $rs->Fetch()) {

            $xmlId = $row['XML_ID'] ?: 'section_'.$row['ID'];

            $sections[$row['ID']] = [
                'xml_id' => $xmlId,
                'parent_id' => $row['IBLOCK_SECTION_ID'],
                'name' => $row['NAME'] ?? '',
                'description' => $row['DESCRIPTION'] ?? '',
                'code' => $row['CODE'] ?? ''
            ];

            $map[$row['ID']] = $xmlId;
        }

        foreach ($sections as $id => &$section) {

            $section['parent_xml_id'] = $section['parent_id']
                ? ($map[$section['parent_id']] ?? null)
                : null;

            unset($section['parent_id']);
        }

        return [
            'sections' => array_values($sections),
            'ids' => array_keys($sections)
        ];
    }

    public function getProducts(int $sectionId, int $limit, int $offset): array
    {
        $products = [];
        $res = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            [
                'IBLOCK_ID' => IBLOCK_ID,
                'IBLOCK_SECTION_ID' => $sectionId,
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
            dump($row);
            $price = CPrice::GetBasePrice($row['ID']);

            $dto = new ProductDto();

            $dto->xmlId = $row['XML_ID'] ?: 'product_'.$row['ID'];

            $dto->sectionXmlId = $row['IBLOCK_SECTION_ID']
                ? $this->getSectionXmlId((int)$row['IBLOCK_SECTION_ID'])
                : 'root';

            $dto->name = $row['NAME'] ?? '';
            $dto->previewText = $row['PREVIEW_TEXT'] ?? '';
            $dto->detailText = $row['DETAIL_TEXT'] ?? '';

            $dto->price = $price ? (float)$price['PRICE'] : 0.0;
            $dto->currency = $price['CURRENCY'] ?? 'RUB';

            if ($row['PREVIEW_PICTURE']) {
                $dto->previewPicture = CFile::GetPath($row['PREVIEW_PICTURE']);
            }

            $products[] = $dto->toArray();
        }

        return $products;
    }



    private function getSectionXmlId(int $sectionId): string
    {
        $section = CIBlockSection::GetByID($sectionId)->Fetch();

        return $section['XML_ID'] ?: 'section_'.$sectionId;
    }
}



/**
 * HTTP транспорт
 */
class PostTransport
{
    public function send(array $payload): array
    {
        $client = new HttpClient([
            'redirect' => true,
            'disableSslVerification' => true
        ]);

        $client->setHeader('Content-Type', 'application/json', true);

        $response = $client->post(
            TARGET_URL,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        if (!$response) {
            return [
                'status' => 'error',
                'message' => 'Empty response'
            ];
        }

        $decoded = json_decode($response, true);

        return $decoded ?: [
            'status' => 'error',
            'message' => 'Invalid JSON',
            'raw' => $response
        ];
    }
}



/**
 * Сервис экспорта
 */
class CatalogExportService
{
    private CatalogExportRepository $repository;
    private PostTransport $transport;

    public function __construct()
    {
        $this->repository = new CatalogExportRepository();
        $this->transport = new PostTransport();
    }

    public function exportSection(int $sectionId): void
    {
        $sectionsData = $this->repository->getSectionsTree($sectionId);

        $sections = $sectionsData['sections'];

        $offset = 0;

        do {

            $products = $this->repository->getProducts(
                $sectionId,
                PACKAGE_SIZE,
                $offset
            );

            if (!$products) {
                break;
            }


            $payload = [
                'sections' => $sections,
                'products' => $products
            ];

            $response = $this->transport->send($payload);
            dump($response);
            $offset += PACKAGE_SIZE;

        } while (true);
    }
}



/**
 * Контроллер
 */
class ExportController
{
    public function run(): void
    {
        $sectionId = (int)($_REQUEST['section_id'] ?? 0);

        if (!$sectionId) {
            die('section_id required');
        }

        $service = new CatalogExportService();
        $service->exportSection($sectionId);

        echo 'Export finished';
    }
}



(new ExportController())->run();