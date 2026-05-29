<?php
namespace WebArchive\Media\FileRenderer;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\AbstractRenderer;
use Omeka\Media\FileRenderer\RendererInterface;
use Omeka\Settings\Settings;

class WebArchiveRenderer extends AbstractRenderer implements RendererInterface
{
    protected $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $view->headScript()->appendFile($view->assetUrl('js/ui.js', 'WebArchive'));
        $mediaData = $media->mediaData() ?? [];
        return $view->partial('omeka/media/renderer/web-archive', [
            'mediaUrl' => $media->originalUrl(),
            'replayBase' => $view->assetUrl('replay/', 'WebArchive', false, false),
            'startUrl' => $mediaData['start_url'] ?? null,
            'embedMode' => $this->settings->get('webarchive_embed', 'default'),
        ]);
    }
}
