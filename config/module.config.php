<?php
namespace WebArchive;

use WebArchive\Service\FileRenderer\WebArchiveRendererFactory;

return [
    'file_renderers' => [
        'factories' => [
            'web-archive' => WebArchiveRendererFactory::class,
        ],
        'aliases' => [
            'application/wacz' => 'web-archive',
            'application/warc' => 'web-archive',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            sprintf('%s/../view', __DIR__),
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => sprintf('%s/../language', __DIR__),
                'pattern' => '%s.mo',
            ],
        ],
    ],
];
