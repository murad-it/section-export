<?php

declare(strict_types=1);

final class CatalogExportService
{
    public function __construct(
        private readonly CatalogRepository $repository,
        private readonly PayloadBuilder    $builder,
        private readonly PostTransport     $transport
    ) {}

    public function exportSection(int $sectionId): void
    {
        $sections = $this->repository->getSectionsTree($sectionId);

        if (empty($sections)) {
            return;
        }

        $offset = 0;

        while (true) {

            $products = $this->repository->getProducts(
                $sectionId,
                PACKAGE_SIZE,
                $offset
            );

            if (empty($products)) {
                break;
            }

            $payload = $this->builder->build($sections, $products);

            $this->transport->send($payload);

            $offset += PACKAGE_SIZE;
        }
    }
}