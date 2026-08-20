<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;
use Cake\View\View;

/**
 * Field helper
 */
class FieldHelper extends Helper
{
    /**
     * Default configuration.
     *
     * @var array<string, mixed>
     */
    protected $_defaultConfig = [];

    public function errors($entity, $fields = [], $class = 'is-invalid')
    {
        $fieldErrors = [];

        if (!$fields)
        {
            $fields = array_keys($entity->getAccessible());
        }

        foreach ($fields as $field)
        {
            $fieldErrors[$field]['class'] = '';
            $fieldErrors[$field]['message'] = '';

            if ($entity->getError($field))
            {
                $fieldErrors[$field]['class'] = $class;

                foreach ($entity->getError($field) as $errorMessage)
                {
                    $fieldErrors[$field]['message'] = $errorMessage;
                }
            }
        }

        return $fieldErrors;
    }
}
