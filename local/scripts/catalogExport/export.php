<?php

require __DIR__.'/bootstrap.php';
require __DIR__.'/config.php';

require __DIR__.'/DTO/SectionDto.php';
require __DIR__.'/DTO/ProductDto.php';

require __DIR__.'/Repository/CatalogRepository.php';

require __DIR__.'/Builder/PayloadBuilder.php';
require __DIR__.'/Transport/PostTransport.php';

require __DIR__.'/Service/CatalogExportService.php';

require __DIR__.'/Controller/ExportController.php';


$repository = new CatalogRepository();
$builder = new PayloadBuilder();
$transport = new PostTransport();

$service = new CatalogExportService(
    $repository,
    $builder,
    $transport
);

$controller = new ExportController($service);
$controller->run();