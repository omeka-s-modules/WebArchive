<?php
namespace WebArchive\Form;

use Laminas\Form\Form;
use WebArchive\Module;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'type' => 'select',
            'name' => 'webarchive_embed_mode',
            'options' => [
                'label' => 'Default embed mode', // @translate
                'info' => 'Controls what the player shows around the archived content. "Default" shows the full player interface, with a browser-like address bar and the archive\'s pages list. "Full" adds the ReplayWeb.page navigation bar (with logo) above that interface. "Replay only" shows just the archived page with no controls, and requires a starting URL to be meaningful. "Replay with info" shows just the archived page plus a collapsible bar that reveals archive details, including a download link, the original URL, the date archived, and the file size.', // @translate
                'value_options' => Module::EMBED_MODES,
            ],
            'attributes' => [
                'id' => 'webarchive-embed',
            ],
        ]);
    }
}
