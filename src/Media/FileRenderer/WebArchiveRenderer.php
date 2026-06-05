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
        $mediaUrl = $media->originalUrl();
        if ($this->isUnplayable($mediaUrl)) {
            $message = $view->status()->isAdminRequest()
                ? 'This archive is not available for playback. A server configuration issue was detected that prevents the player from loading the file.' // @translate
                : 'This archive is not available for playback.'; // @translate
            return '<p>' . $view->escapeHtml($view->translate($message)) . '</p>';
        }
        $view->headScript()->appendFile($view->assetUrl('vendor/replaywebpage/ui.js', 'WebArchive'));
        $mediaData = $media->mediaData() ?? [];
        return $view->partial('omeka/media/renderer/web-archive', [
            'mediaUrl' => $mediaUrl,
            'replayBase' => $view->assetUrl('vendor/replaywebpage/', 'WebArchive', false, false),
            'startUrl' => $mediaData['start_url'] ?? null,
            'embedMode' => $mediaData['embed_mode'] ?? $this->settings->get('webarchive_embed_mode', 'default'),
        ]);
    }

    /**
     * Check if the archive file is unplayable due to server-applied Content-Encoding.
     *
     * If the server applies Content-Encoding (e.g. Apache mod_deflate compressing the
     * response), the ReplayWeb.page player fails to load the file. Chrome throws
     * TypeError: Failed to fetch; Firefox loads the player but shows no pages. The
     * presence of the header alone triggers the failure — the fix is a server-side
     * configuration change.
     */
    protected function isUnplayable(string $url): bool
    {
        try {
            $response = $this->httpClient->setUri($url)->setMethod('HEAD')->send();
            return (bool) $response->getHeaders()->get('Content-Encoding');
        } catch (\Exception $e) {
            return false;
        }
    }
}
