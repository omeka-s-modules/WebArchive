<?php
namespace WebArchive;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Url;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;
use WebArchive\Form\ConfigForm;

class Module extends AbstractModule
{
    const MEDIA_TYPES = ['application/wacz', 'application/warc'];

    const EMBED_MODES = [
        'default' => 'Default', // @translate
        'full' => 'Full', // @translate
        'replayonly' => 'Replay only', // @translate
        'replay-with-info' => 'Replay with info', // @translate
    ];

    public function getConfig()
    {
        return include sprintf('%s/config/module.config.php', __DIR__);
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');

        $extensionWhitelist = $settings->get('extension_whitelist', []);
        // "gz" is needed to allow .warc.gz uploads (Omeka resolves the extension from the last dot only)
        foreach (['wacz', 'warc', 'gz'] as $ext) {
            if (!in_array($ext, $extensionWhitelist)) {
                $extensionWhitelist[] = $ext;
            }
        }
        $settings->set('extension_whitelist', $extensionWhitelist);

        $mediaTypeWhitelist = $settings->get('media_type_whitelist', []);
        // "application/gzip" is needed because WARC files may be gzip-compressed
        foreach (['application/wacz', 'application/warc', 'application/gzip'] as $type) {
            if (!in_array($type, $mediaTypeWhitelist)) {
                $mediaTypeWhitelist[] = $type;
            }
        }
        $settings->set('media_type_whitelist', $mediaTypeWhitelist);
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        $settings->delete('webarchive_embed_mode');
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $form->setData([
            'webarchive_embed_mode' => $settings->get('webarchive_embed_mode', 'default'),
        ]);
        return $renderer->formCollection($form, false);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $form->setData($controller->params()->fromPost());
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }
        $formData = $form->getData();
        $settings->set('webarchive_embed_mode', $formData['webarchive_embed_mode']);
        return true;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\MediaAdapter',
            'api.hydrate.post',
            [$this, 'handleWebArchiveHydration']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.edit.form.advanced',
            [$this, 'addMediaFields']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.show.after',
            [$this, 'showMediaFields']
        );
    }

    public function handleWebArchiveHydration(Event $event)
    {
        $entity = $event->getParam('entity');
        $request = $event->getParam('request');

        // Correct MIME types on CREATE: finfo misdetects these formats, and the file
        // validator runs before this event, so we accept broad types and correct here.
        if ($request->getOperation() === 'create') {
            // WACZ is ZIP-based; finfo may detect it as application/zip or other types. Correct by extension.
            if ($entity->getExtension() === 'wacz'
                && $entity->getMediaType() !== 'application/wacz'
            ) {
                $entity->setMediaType('application/wacz');
            }

            // Modern libmagic detects uncompressed WARCs as application/warc; gzip-compressed WARCs
            // as application/gzip. Older libmagic may return application/octet-stream. Correct by extension.
            if ($entity->getExtension() === 'warc'
                && $entity->getMediaType() !== 'application/warc'
            ) {
                $entity->setMediaType('application/warc');
            }

            // .warc.gz files have extension 'gz'; detect by source filename
            if ($entity->getMediaType() === 'application/gzip'
                && str_ends_with($entity->getSource(), '.warc.gz')
            ) {
                $entity->setMediaType('application/warc');
            }
        }

        // Persist per-media fields on UPDATE
        if ($request->getOperation() === 'update' && in_array($entity->getMediaType(), self::MEDIA_TYPES)) {
            $content = $request->getContent();
            $data = $entity->getData() ?? [];
            if (array_key_exists('webarchive_start_url', $content)) {
                $data['start_url'] = $content['webarchive_start_url'] ?: null;
            }
            if (array_key_exists('webarchive_embed_mode', $content)) {
                $data['embed_mode'] = $content['webarchive_embed_mode'] ?: null;
            }
            $entity->setData($data);
        }
    }

    public function addMediaFields(Event $event)
    {
        $view = $event->getTarget();
        $media = $view->resource;
        if (!$media || !in_array($media->mediaType(), self::MEDIA_TYPES)) {
            return;
        }
        $mediaData = $media->mediaData() ?? [];

        $startUrl = new Url('webarchive_start_url');
        $startUrl->setLabel('Starting URL') // @translate
            ->setOption('info', 'Enter the original URL of the page to open first. Leave blank to show the archive\'s pages list, where viewers can browse all captured pages. Required if embed mode is set to "Replay only".') // @translate
            ->setAttribute('id', 'web-archive-start-url')
            ->setValue($mediaData['start_url'] ?? '');

        $embedMode = new Select('webarchive_embed_mode');
        $embedMode->setLabel('Embed mode') // @translate
            ->setOption('info', 'Controls what the player shows around the archived content. "Default" shows the full player interface, with a browser-like address bar and the archive\'s pages list. "Full" adds the ReplayWeb.page navigation bar (with logo) above that interface. "Replay only" shows just the archived page with no controls, and requires a starting URL to be meaningful. "Replay with info" shows just the archived page plus a collapsible bar that reveals archive details, including a download link, the original URL, the date archived, and the file size. Leave blank to use the site default.') // @translate
            ->setAttribute('id', 'web-archive-embed-mode')
            ->setEmptyOption('[Site default]') // @translate
            ->setValueOptions(self::EMBED_MODES)
            ->setValue($mediaData['embed_mode'] ?? '');

        echo $view->partial('common/media-fields-edit', ['elements' => [$startUrl, $embedMode]]);
    }

    public function showMediaFields(Event $event)
    {
        $view = $event->getTarget();
        $media = $view->media;
        if (!$media || !in_array($media->mediaType(), self::MEDIA_TYPES)) {
            return;
        }
        $mediaData = $media->mediaData() ?? [];
        echo $view->partial('common/media-fields-show', [
            'startUrl' => $mediaData['start_url'] ?? null,
            'embedMode' => $mediaData['embed_mode'] ?? null,
            'embedModes' => self::EMBED_MODES,
        ]);
    }
}
