<?php
namespace WebArchive\Media\FileRenderer;

use Laminas\Http\Client;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\AbstractRenderer;
use Omeka\Media\FileRenderer\RendererInterface;
use Omeka\Settings\Settings;

class WebArchiveRenderer extends AbstractRenderer implements RendererInterface
{
    protected Settings $settings;
    protected Client $httpClient;

    public function __construct(Settings $settings, Client $httpClient)
    {
        $this->settings = $settings;
        $this->httpClient = $httpClient;
    }

    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        try {
            $this->assertPlayable($media);
        } catch (\RuntimeException $e) {
            $message = $view->translate('This archive is not available for playback.');
            if ($view->status()->isAdminRequest()) {
                $message .= ' ' . $view->translate($e->getMessage());
            }
            return '<p>' . $view->escapeHtml($message) . '</p>';
        }
        $view->headScript()->appendFile($view->assetUrl('vendor/replaywebpage/ui.js', 'WebArchive'));
        $mediaData = $media->mediaData() ?? [];
        return $view->partial('omeka/media/renderer/web-archive', [
            'mediaUrl' => $media->originalUrl(),
            'replayBase' => $view->assetUrl('vendor/replaywebpage/', 'WebArchive', false, false),
            'startUrl' => $mediaData['start_url'] ?? null,
            'embedMode' => $mediaData['embed_mode'] ?? $this->settings->get('webarchive_embed_mode', 'default'),
        ]);
    }

    /**
     * Perform checks to determine if an archive file is known to be unplayable.
     *
     * Throws a RuntimeException with an admin-facing reason if a check fails.
     */
    protected function assertPlayable(MediaRepresentation $media): void
    {
        // If the server applies Content-Encoding (e.g. Apache mod_deflate compressing
        // the response), the ReplayWeb.page player fails to load the file. Chrome throws
        // TypeError: Failed to fetch; Firefox loads the player but shows no pages. The
        // presence of the header alone triggers the failure.
        try {
            $response = $this->httpClient->setUri($media->originalUrl())->setMethod('HEAD')->send();
        } catch (\Exception $e) {
            return;
        }
        if ($response->getHeaders()->get('Content-Encoding')) {
            throw new \RuntimeException('The server is applying Content-Encoding to the file, which prevents the player from loading it.'); // @translate
        }
    }
}
