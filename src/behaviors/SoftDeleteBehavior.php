<?php

namespace ozerich\tools\behaviors;

use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\base\ModelEvent;
use yii\db\BaseActiveRecord;

class SoftDeleteBehavior extends Behavior
{
    const EVENT_BEFORE_SOFT_DELETE = 'beforeSoftDelete';

    const EVENT_AFTER_SOFT_DELETE = 'afterSoftDelete';

    const EVENT_BEFORE_RESTORE = 'beforeRestore';

    const EVENT_AFTER_RESTORE = 'afterRestore';

    public $softDeleteAttributeValues = [
        'deleted' => true
    ];

    public $restoreAttributeValues;

    public $invokeDeleteEvents = true;

    public $allowDeleteCallback;

    public $deleteFallbackException = 'yii\db\IntegrityException';

    private $_replaceRegularDelete = true;

    public function getReplaceRegularDelete()
    {
        return $this->_replaceRegularDelete;
    }

    public function setReplaceRegularDelete($replaceRegularDelete)
    {
        $this->_replaceRegularDelete = $replaceRegularDelete;
        if (is_object($this->owner)) {
            $owner = $this->owner;
            $this->detach();
            $this->attach($owner);
        }
    }

    public function softDelete()
    {
        if ($this->isDeleteAllowed()) {
            return $this->owner->delete();
        }
        if ($this->invokeDeleteEvents && !$this->owner->beforeDelete()) {
            return false;
        }
        $result = $this->softDeleteInternal();
        if ($this->invokeDeleteEvents) {
            $this->owner->afterDelete();
        }
        return $result;
    }

    protected function softDeleteInternal()
    {
        $result = false;
        if ($this->beforeSoftDelete()) {
            $attributes = $this->owner->getDirtyAttributes();
            foreach ($this->softDeleteAttributeValues as $attribute => $value) {
                if (!is_scalar($value) && is_callable($value)) {
                    $value = call_user_func($value, $this->owner);
                }
                $attributes[$attribute] = $value;
            }
            $result = $this->owner->updateAttributes($attributes);
            $this->afterSoftDelete();
        }
        return $result;
    }

    public function beforeSoftDelete()
    {
        if (method_exists($this->owner, 'beforeSoftDelete')) {
            if (!$this->owner->beforeSoftDelete()) {
                return false;
            }
        }
        $event = new ModelEvent();
        $this->owner->trigger(self::EVENT_BEFORE_SOFT_DELETE, $event);
        return $event->isValid;
    }

    public function afterSoftDelete()
    {
        if (method_exists($this->owner, 'afterSoftDelete')) {
            $this->owner->afterSoftDelete();
        }
        $this->owner->trigger(self::EVENT_AFTER_SOFT_DELETE);
    }

    protected function isDeleteAllowed()
    {
        if ($this->allowDeleteCallback === null) {
            return false;
        }
        return call_user_func($this->allowDeleteCallback, $this->owner);
    }

    public function restore()
    {
        $result = false;
        if ($this->beforeRestore()) {
            $result = $this->restoreInternal();
            $this->afterRestore();
        }
        return $result;
    }

    protected function restoreInternal()
    {
        $restoreAttributeValues = $this->restoreAttributeValues;
        if ($restoreAttributeValues === null) {
            foreach ($this->softDeleteAttributeValues as $name => $value) {
                if (is_bool($value) || $value === 1 || $value === 0) {
                    $restoreValue = !$value;
                } elseif (is_int($value)) {
                    if ($value === 1) {
                        $restoreValue = 0;
                    } elseif ($value === 0) {
                        $restoreValue = 1;
                    } else {
                        $restoreValue = $value + 1;
                    }
                } else {
                    throw new InvalidConfigException('Unable to automatically determine restore attribute values, "' . get_class($this) . '::$restoreAttributeValues" should be explicitly set.');
                }
                $restoreAttributeValues[$name] = $restoreValue;
            }
        }
        $attributes = $this->owner->getDirtyAttributes();
        foreach ($restoreAttributeValues as $attribute => $value) {
            if (!is_scalar($value) && is_callable($value)) {
                $value = call_user_func($value, $this->owner);
            }
            $attributes[$attribute] = $value;
        }
        return $this->owner->updateAttributes($attributes);
    }

    public function beforeRestore()
    {
        if (method_exists($this->owner, 'beforeRestore')) {
            if (!$this->owner->beforeRestore()) {
                return false;
            }
        }
        $event = new ModelEvent();
        $this->owner->trigger(self::EVENT_BEFORE_RESTORE, $event);
        return $event->isValid;
    }

    public function afterRestore()
    {
        if (method_exists($this->owner, 'afterRestore')) {
            $this->owner->afterRestore();
        }
        $this->owner->trigger(self::EVENT_AFTER_RESTORE);
    }

    public function safeDelete()
    {
        try {
            $transaction = $this->beginTransaction();
            $result = $this->owner->delete();
            if (isset($transaction)) {
                $transaction->commit();
            }
        } catch (\Exception $exception) {
            if (isset($transaction)) {
                $transaction->rollback();
            }
            $fallbackExceptionClass = $this->deleteFallbackException;
            if ($exception instanceof $fallbackExceptionClass) {
                $result = $this->softDeleteInternal();
            } else {
                throw $exception;
            }
        }
        return $result;
    }

    private function beginTransaction()
    {
        $db = $this->owner->getDb();
        if ($db->hasMethod('beginTransaction')) {
            return $db->beginTransaction();
        }
        return null;
    }

    public function events()
    {
        if ($this->getReplaceRegularDelete()) {
            return [
                BaseActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            ];
        }
        return [];
    }

    public function beforeDelete($event)
    {
        if (!$this->isDeleteAllowed()) {
            $this->softDeleteInternal();
            $event->isValid = false;
        }
    }
}