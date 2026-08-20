<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;
use Cake\View\View;

/**
 * Link helper
 */
class LinkHelper extends Helper
{
    /**
     * Default configuration.
     *
     * @var array<string, mixed>
     */
    protected $_defaultConfig = [];

    public $helpers = ['Html', 'Encrypt'];

    public function student($studentEntity, $blank = true)
    {
        $options = [
            'escape' => false,
            'class' => 'text-decoration-none'
        ];

        if ($blank)
        {
            $options['target'] = '_blank';
        }

        return $this->Html->link($studentEntity->name . ' <i class="mdi mdi-open-in-new"></i>', ['controller' => 'Dashboard', 'action' => 'student', 'prefix' => 'Teacher', $this->Encrypt->hex($studentEntity->id)], $options);
    }
}
