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
                'info' => 'Controls what the player shows around the archived content. "Default" and "Full" both show the full interface with navigation; "Full" differs only in sizing behavior. "Replay only" shows just the archived page with no controls, and requires a starting URL to be meaningful. "Replay with info" shows the archived page alongside a metadata panel with title, date, and source URL.', // @translate
                'value_options' => Module::EMBED_MODES,
            ],
            'attributes' => [
                'id' => 'webarchive-embed',
            ],
        ]);
    }
}
