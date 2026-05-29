<?php
namespace WebArchive\Form;

use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'type' => 'select',
            'name' => 'webarchive_embed',
            'options' => [
                'label' => 'Default embed mode', // @translate
                'info' => '<ul><li><strong>Default</strong>: full player interface with navigation.</li><li><strong>Full</strong>: same as Default, differs only in sizing behavior.</li><li><strong>Replay only</strong>: archived page with no controls — only useful when a starting URL is also set on the media item.</li><li><strong>Replay with info</strong>: archived page alongside a metadata panel with title, date, and source URL.</li></ul>', // @translate
                'escape_info' => false,
                'value_options' => [
                    'default'          => 'Default', // @translate
                    'full'             => 'Full', // @translate
                    'replayonly'       => 'Replay only', // @translate
                    'replay-with-info' => 'Replay with info', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'webarchive-embed',
            ],
        ]);
    }
}
