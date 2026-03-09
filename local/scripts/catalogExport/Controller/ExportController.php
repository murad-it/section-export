<?php

declare(strict_types=1);

final class ExportController
{
    public function __construct(
        private CatalogExportService $service
    ) {}

    public function run(): void
    {
        $sectionId = (int)($_REQUEST['section_id'] ?? 0);

        if ($sectionId <= 0) {
            http_response_code(400);
            echo 'section_id required';
            return;
        }

        $this->service->exportSection($sectionId);

        echo 'Export finished';
    }
}