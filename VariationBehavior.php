<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\ar\variation;

use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\base\UnknownPropertyException;

/**
 * VariationBehavior
 *
 * @property \yii\db\BaseActiveRecord $owner
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class VariationBehavior extends Behavior
{
    /**
     * @var string name of relation, which corresponds all variations.
     */
    public $variationsRelation = 'variations';
    /**
     * @var string name of relation, which corresponds default variation.
     */
    public $defaultVariationRelation = 'variation';
    /**
     * @var array map, which marks the attributes of main model, which should be a source for
     * the default value for the variation model attributes.
     * Format: variationModelAttributeName => mainModelAttributeName.
     * For example:
     *
     * ```php
     * [
     *     'title' => 'name',
     *     'content' => 'defaultContent',
     * ];
     * ```
     *
     * Default value map will be used if default variation model not exists, or
     * its requested attribute value is empty.
     */
    public $variationAttributeDefaultValueMap = [];
    /**
     * @var string name of attribute, which store option primary key reference.
     */
    public $variationOptionReferenceAttribute = 'optionId';
    /**
     * @var string name of ActiveRecord class, which determines possible variation options.
     */
    public $optionModelClass;
    /**
     * @var mixed|callable callback for the function, which should return default
     * variation option primary key id.
     */
    public $defaultVariationOptionReference;


    /**
     * Declares has-one relation [[defaultVariationRelation]] from [[variationsRelation]] relation.
     * @return \yii\db\ActiveQueryInterface the relational query object.
     */
    public function hasDefaultVariationRelation()
    {
        $relationMethod = 'get' . $this->variationsRelation;
        /* @var $variationsRelation \yii\db\ActiveQueryInterface */
        $variationsRelation = $this->owner->$relationMethod();
        $variationsRelation->multiple = false;
        $variationsRelation->andWhere([$this->variationOptionReferenceAttribute => $this->getDefaultVariationOptionReference()]);
        return $variationsRelation;
    }

    /**
     * @return mixed default variation option reference value.
     * @throws InvalidConfigException on empty [[defaultVariationOptionReference]]
     */
    public function getDefaultVariationOptionReference()
    {
        if ($this->defaultVariationOptionReference === null) {
            throw new InvalidConfigException('"' . get_class($this) . '::defaultVariationOptionReference" must be set.');
        }
        if (is_scalar($this->defaultVariationOptionReference)) {
            return $this->defaultVariationOptionReference;
        }
        return call_user_func($this->defaultVariationOptionReference, $this->owner);
    }

    /**
     * @return \yii\db\BaseActiveRecord|null
     */
    public function getDefaultVariationModel()
    {
        return $this->findDefaultVariationModel();
    }

    /**
     * @return \yii\db\BaseActiveRecord|null
     */
    private function findDefaultVariationModel()
    {
        if ($this->owner->isRelationPopulated($this->defaultVariationRelation) || !$this->owner->isRelationPopulated($this->variationsRelation)) {
            return $this->owner->{$this->defaultVariationRelation};
        } else {
            $defaultOptionReference = $this->getDefaultVariationOptionReference();
            foreach ($this->owner->{$this->variationsRelation} as $model) {
                if ($model->{$this->variationOptionReferenceAttribute} == $defaultOptionReference) {
                    $this->owner->populateRelation($this->defaultVariationRelation, $model);
                    return $model;
                }
            }
        }
        return null;
    }

    // Property Access Extension:

    /**
     * PHP getter magic method.
     * This method is overridden so that variation attributes can be accessed like properties.
     *
     * @param string $name property name
     * @throws UnknownPropertyException if the property is not defined
     * @return mixed property value
     */
    public function __get($name)
    {
        try {
            return parent::__get($name);
        } catch (UnknownPropertyException $exception) {
            $model = $this->getDefaultVariationModel();
            if (is_object($model) && $model->hasAttribute($name)) {
                $result = $model->$name;
                if (empty($result) && isset($this->variationAttributeDefaultValueMap[$name])) {
                    return $this->owner->{$this->variationAttributeDefaultValueMap[$name]};
                }
                return $result;
            } elseif (isset($this->variationAttributeDefaultValueMap[$name])) {
                return $this->owner->{$this->variationAttributeDefaultValueMap[$name]};
            }

            throw $exception;
        }
    }

    /**
     * PHP setter magic method.
     * This method is overridden so that variation attributes can be accessed like properties.
     * @param string $name property name
     * @param mixed $value property value
     * @throws UnknownPropertyException if the property is not defined
     */
    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (UnknownPropertyException $exception) {
            $model = $this->getDefaultVariationModel();
            if ($model->hasAttribute($name)) {
                $model->$name = $value;
            } else {
                throw $exception;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true, $checkBehaviors = true)
    {
        if (parent::canGetProperty($name, $checkVars, $checkBehaviors)) {
            return true;
        }
        if (isset($this->variationAttributeDefaultValueMap[$name])) {
            return true;
        }
        $model = $this->getDefaultVariationModel();
        return is_object($model) && $model->hasAttribute($name);
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true, $checkBehaviors = true)
    {
        if (parent::canSetProperty($name, $checkVars, $checkBehaviors)) {
            return true;
        }
        $model = $this->getDefaultVariationModel();
        return is_object($model) && $model->hasAttribute($name);
    }
}