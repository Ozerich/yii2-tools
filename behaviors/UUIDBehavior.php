<?php

namespace ozerich\tools\behaviors;

use yii\base\Behavior;
use yii\db\ActiveRecord;

class UUIDBehavior extends Behavior
{
    public $column = 'uuid';

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
        ];
    }

    /**
     * set beforeSave() -> UUID data
     */
    public function beforeSave()
    {
        $this->owner->{$this->column} = $this->owner->getDb()->createCommand("SELECT REPLACE(UUID(),'-','')")->queryScalar();
    }
}