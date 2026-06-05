<?php
namespace WebArchive\Service\FileRenderer;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use WebArchive\Media\FileRenderer\WebArchiveRenderer;

class WebArchiveRendererFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new WebArchiveRenderer(
            $services->get('Omeka\Settings'),
            $services->get('Omeka\HttpClient')
        );
    }
}
