<?php
namespace WebArchive;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;
use WebArchive\Form\ConfigForm;

class Module extends AbstractModule
{
    const MEDIA_TYPES = ['application/wacz', 'application/warc'];

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
        $settings->delete('webarchive_embed');
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $form->setData([
            'webarchive_embed' => $settings->get('webarchive_embed', 'default'),
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
        $settings->set('webarchive_embed', $formData['webarchive_embed']);
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
            [$this, 'addStartUrlField']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.show.after',
            [$this, 'showStartUrl']
        );
    }

    public function handleWebArchiveHydration(Event $event)
    {
        $entity = $event->getParam('entity');
        $request = $event->getParam('request');

        // Correct MIME types on CREATE: finfo misdetects these formats, and the file
        // validator runs before this event, so we accept broad types and correct here.
        if ($request->getOperation() === 'create') {
            // finfo detects WACZ as application/zip (WACZ is ZIP-based)
            if ($entity->getExtension() === 'wacz'
                && $entity->getMediaType() === 'application/zip'
            ) {
                $entity->setMediaType('application/wacz');
            }

            // finfo detects gzip-compressed WARCs as application/gzip; correct by extension.
            // Also a safety net for older libmagic that doesn't recognise application/warc.
            if ($entity->getExtension() === 'warc'
                && $entity->getMediaType() !== 'application/warc'
            ) {
                $entity->setMediaType('application/warc');
            }

            // .warc.gz files have extension 'gz'; detect by source filename
            if ($entity->getMediaType() === 'application/gzip'
                && str_ends_with(parse_url($entity->getSource(), PHP_URL_PATH) ?? $entity->getSource(), '.warc.gz')
            ) {
                $entity->setMediaType('application/warc');
            }
        }

        // Persist starting URL on UPDATE
        if ($request->getOperation() === 'update' && in_array($entity->getMediaType(), self::MEDIA_TYPES)) {
            $content = $request->getContent();
            if (array_key_exists('web_archive_start_url', $content)) {
                $data = $entity->getData() ?? [];
                $data['start_url'] = $content['web_archive_start_url'] ?: null;
                $entity->setData($data);
            }
        }
    }

    public function addStartUrlField(Event $event)
    {
        $view = $event->getTarget();
        $media = $view->resource;
        if (!$media || !in_array($media->mediaType(), self::MEDIA_TYPES)) {
            return;
        }
        $startUrl = ($media->mediaData() ?? [])['start_url'] ?? '';
        echo $view->partial('common/media-fields', ['startUrl' => $startUrl]);
    }

    public function showStartUrl(Event $event)
    {
        $view = $event->getTarget();
        $media = $view->media;
        if (!$media || !in_array($media->mediaType(), self::MEDIA_TYPES)) {
            return;
        }
        $startUrl = ($media->mediaData() ?? [])['start_url'] ?? null;
        echo $view->partial('common/media-fields', ['startUrl' => $startUrl, 'readOnly' => true]);
    }
}
