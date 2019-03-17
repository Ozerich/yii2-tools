<?php

namespace ozerich\tools\behaviors;

use yii\base\Behavior;
use yii\db\ActiveRecord;

/**
 * Class PriorityBehavior
 * @package app\components
 * @property ActiveRecord $owner
 * @property string $attribute
 */
class PriorityBehavior extends Behavior
{
    const UP = +1;
    const DOWN = -1;

    public $conditionAttribute = null;

    public $attribute = 'priority';

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
        ];
    }

    public function beforeValidate()
    {
        $conditionAttributes = $this->conditionAttribute ? (is_array($this->conditionAttribute) ? $this->conditionAttribute : [$this->conditionAttribute]) : null;

        if ($this->owner->isNewRecord) {

            if ($conditionAttributes) {
                $queries = [];
                foreach ($conditionAttributes as $attribute) {
                    $value = $this->owner->{$attribute};
                    if (!$value) {
                        $queries[] = '`' . $attribute . '` is null';
                    } else {
                        $queries[] = '`' . $attribute . '` = "' . $value . '"';
                    }
                }

                $condition = $this->conditionAttribute ? implode(' AND ', $queries) : 1;
            } else {
                $condition = 1;
            }

            $result = \Yii::$app->getDb()->createCommand("select max(" . $this->attribute . ") from " . $this->owner->tableName() .
                " WHERE " . $condition)->queryScalar();


            $this->owner->{$this->attribute} = $result ? $result + 1 : 1;
        } elseif ($this->conditionAttribute) {

            $changed = false;
            foreach ($conditionAttributes as $attribute) {
                $old_value = \Yii::$app->getDb()->createCommand("SELECT " . $attribute . " FROM " . $this->owner->tableName() . " WHERE `id` = " . $this->owner->id)->queryScalar();
                if ($old_value != $this->owner->{$attribute}) {
                    $changed = true;
                    break;
                }
            }
            if ($changed) {
                $queries = [];
                foreach ($conditionAttributes as $attribute) {
                    $value = $this->owner->{$attribute};
                    if (!$value) {
                        $queries[] = '`' . $attribute . '` is null';
                    } else {
                        $queries[] = '`' . $attribute . '` = "' . $value . '"';
                    }
                }

                $result = (int)\Yii::$app->getDb()->createCommand("SELECT max(" . $this->attribute . ") FROM " . $this->owner->tableName() . " WHERE " . implode(' AND ', $queries))->queryScalar();
                $this->owner->{$this->attribute} = $result ? $result + 1 : 1;
            }
        }
    }
}