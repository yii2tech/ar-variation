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
 * VariationBehavior provides support for ActiveRecord variation via related models.
 *
 * ```php
 * class Item extends ActiveRecord
 * {
 *     public function behaviors()
 *     {
 *         return [
 *             'translationBehavior' => [
 *                 'class' => VariationBehavior::className(),
 *                 'variationsRelation' => 'translations',
 *                 'defaultVariationRelation' => 'defaultTranslation',
 *                 'variationOptionReferenceAttribute' => 'languageId',
 *                 'optionModelClass' => Language::className(),
 *                 'defaultVariationOptionReference' => 1,
 *                 'variationAttributeDefaultValueMap' => [
 *                     'title' => 'name'
 *                 ],
 *             ],
 *         ];
 *     }
 *
 *     public function getTranslations()
 *     {
 *         return $this->hasMany(ItemTranslation::className(), ['itemId' => 'id']);
 *     }
 *
 *     public function getDefaultTranslation()
 *     {
 *         return $this->hasDefaultVariationRelation();
 *     }
 * }
 * ```
 *
 * @property \yii\db\BaseActiveRecord $owner
 * @property \yii\db\BaseActiveRecord[] $variationModels list of all possible variation models.
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
     * @var \yii\db\ActiveQueryInterface[]|null list of all possible variation models.
     */
    private $_variationModels;


    /**
     * Declares has-one relation [[defaultVariationRelation]] from [[variationsRelation]] relation.
     * @return \yii\db\ActiveQueryInterface the relational query object.
     */
    public function hasDefaultVariationRelation()
    {
        $variationsRelation = $this->getVariationsRelation();
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
     * Returns the instance of the [[variationsRelation]] relation.
     * @return \yii\db\ActiveQueryInterface|\yii\db\ActiveRelationTrait variations relation.
     */
    private function getVariationsRelation()
    {
        $relationMethod = 'get' . $this->variationsRelation;
        return $this->owner->$relationMethod();
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

    /**
     * Sets models related to the main one as variations.
     * @param \yii\db\BaseActiveRecord[]|null $models variation models.
     * @return $this self reference.
     */
    public function setVariationModels($models)
    {
        $this->_variationModels = $models;
        return $this;
    }

    /**
     * Returns models related to the main one as variations.
     * This method adjusts set of related models creating missing variations.
     * @return \yii\db\BaseActiveRecord[] list of variation models.
     */
    public function getVariationModels()
    {
        if (is_array($this->_variationModels)) {
            return $this->_variationModels;
        }

        $variationModels = $this->owner->{$this->variationsRelation};

        $variationModels = $this->adjustVariationModels($variationModels);
        $this->_variationModels = $variationModels;
        return $variationModels;
    }

    /**
     * Adjusts given variation models to be adequate to the {@link variatorModelClassName} records.
     * @param \yii\db\BaseActiveRecord[] $initialVariationModels set of initial variation models, found by relation
     * @return \yii\db\BaseActiveRecord[] set of {@link CActiveRecord}
     */
    private function adjustVariationModels(array $initialVariationModels)
    {
        /* @var $optionModelClass \yii\db\BaseActiveRecord */
        /* @var $options \yii\db\BaseActiveRecord[] */
        $optionModelClass = $this->optionModelClass;
        $options = $optionModelClass::find()->all();

        $variationsRelation = $this->getVariationsRelation();

        $optionReferenceAttribute = $this->variationOptionReferenceAttribute;
        list($ownerReferenceAttribute) = array_keys($variationsRelation->link);

        /* @var $variationModels \yii\db\BaseActiveRecord[] */
        /* @var $confirmedInitialVariationModels \yii\db\BaseActiveRecord[] */
        $variationModels = [];
        $confirmedInitialVariationModels = [];
        foreach ($options as $option) {
            $matchFound = false;
            foreach ($initialVariationModels as $initialVariationModel) {
                if ($option->getPrimaryKey() == $initialVariationModel->$optionReferenceAttribute) {
                    $variationModels[] = $initialVariationModel;
                    $confirmedInitialVariationModels[] = $initialVariationModel;
                    $matchFound = true;
                    break;
                }
            }
            if (!$matchFound) {
                $variationClassName = $variationsRelation->modelClass;
                $variationModel = new $variationClassName();
                $variationModel->$optionReferenceAttribute = $option->getPrimaryKey();
                $variationModel->$ownerReferenceAttribute = $this->owner->getPrimaryKey();
                $variationModels[] = $variationModel;
            }
        }

        if (count($confirmedInitialVariationModels) < count($initialVariationModels)) {
            foreach ($initialVariationModels as $initialVariationModel) {
                $matchFound = false;
                foreach ($confirmedInitialVariationModels as $confirmedInitialVariationModel) {
                    if ($confirmedInitialVariationModel->getPrimaryKey() == $initialVariationModel->getPrimaryKey()) {
                        $matchFound = true;
                        break;
                    }
                }
                if (!$matchFound) {
                    $initialVariationModel->delete();
                }
            }
        }

        return $variationModels;
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