<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Melc extends Entity
{
    protected $_accessible = [
        'id' => true,
        'description' => true,
        'teacher_id' => true,
        'subject_id' => true,
        'upload_date' => true,
    ];
}
