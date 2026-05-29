<?php
namespace WebArchive;

return [
    'file_renderers' => [
        'factories' => [
            'web-archive' => Service\FileRenderer\WebArchiveRendererFactory::class,
        ],
        'aliases' => [
            'application/wacz' => 'web-archive',
            'application/warc' => 'web-archive',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
];
