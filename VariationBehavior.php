<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\ar\variation;

use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\base\NotSupportedException;
use yii\base\UnknownPropertyException;
use yii\db\BaseActiveRecord;

/**
 * VariationBehavior provides support for ActiveRecord variation via related models.
 *
 * Configuration example:
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
 * @property BaseActiveRecord $owner
 * @property BaseActiveRecord[] $variationModels list of all possible variation models.
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
    public $defaultVariationRelation;
    /**
     * @var array map, which marks the  a source for the default value for the variation model attributes.
     * Format: variationModelAttributeName => valueSource.
     * Each map value can be:
     *  - null, - returns `null` as variation attribute value
     *  - string, - returns value of the specified attribute from parent model as variation attribute value
     *  - callable, - returns result of callback invocation as variation attribute value
     * For example:
     *
     * ```php
     * [
     *     'title' => 'name',
     *     'content' => 'defaultContent',
     *     'brief' => null,
     *     'summary' => function() {return Yii::t('app', 'Not available')},
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
     * @var mixed|callable additional filter to be applied to the DB query used to find [[optionModelClass]] instances.
     * This could be a callable with the signature `function (\yii\db\QueryInterface $query)`, or a direct filter condition
     * for the [[\yii\db\QueryInterface::where()]] method.
     */
    public $optionQueryFilter;
    /**
     * @var mixed|callable callback for the function, which should return default
     * variation option primary key id.
     */
    public $defaultVariationOptionReference;
    /**
     * @var array|callable|null list of the attributes, which should be applied for newly created variation model.
     * This could be a callable with the signature `function (\yii\db\BaseActiveRecord $model)` or array of attribute values.
     * If not set attributes will be automatically determined from the [[variationsRelation]] relation `where` condition.
     */
    public $variationModelDefaultAttributes;
    /**
     * @var callable|null PHP callback, which should determine whether particular variation model should be saved or not.
     * Callable should have a following signature: `bool function (\yii\db\BaseActiveRecord $model)`.
     * For example:
     *
     * ```php
     * function ($model) {
     *     return !empty($model->title) || !empty($model->description);
     * }
     * ```
     */
    public $variationSaveFilter;

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
        $variationsRelation = $this->owner->getRelation($this->variationsRelation);
        $variationsRelation->multiple = false;
        $condition = [$this->variationOptionReferenceAttribute => $this->getDefaultVariationOptionReference()];

        if (method_exists($variationsRelation, 'andOnCondition')) {
            try {
                $variationsRelation->andOnCondition($condition);
            } catch (NotSupportedException $exception) {
                // particular ActiveQuery may extend `yii\db\ActiveQuery` but do not support `on` conditions
                $variationsRelation->andWhere($condition);
            }
        } else {
            $variationsRelation->andWhere($condition);
        }

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
     * @return BaseActiveRecord|null
     */
    public function getDefaultVariationModel()
    {
        return $this->findDefaultVariationModel();
    }

    /**
     * @return BaseActiveRecord|null
     */
    private function findDefaultVariationModel()
    {
        if ($this->defaultVariationRelation !== null) {
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
        }
        return null;
    }

    /**
     * Sets models related to the main one as variations.
     * @param BaseActiveRecord[]|null $models variation models.
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
     * @return BaseActiveRecord[] list of variation models.
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
     * @return bool whether the variation models have been initialized or not.
     */
    public function getIsVariationModelsInitialized()
    {
        return !empty($this->_variationModels);
    }

    /**
     * Returns variation model, matching given option primary key.
     * Note: this method will load [[variationsRelation]] relation fully.
     * @param mixed $optionPk option entity primary key.
     * @return BaseActiveRecord|null variation model.
     */
    public function getVariationModel($optionPk)
    {
        foreach ($this->getVariationModels() as $model) {
            if ($model->{$this->variationOptionReferenceAttribute} == $optionPk) {
                return $model;
            }
        }
        return null;
    }

    /**
     * Adjusts given variation models to be adequate to the [[optionModelClass]] records.
     * @param BaseActiveRecord[] $initialVariationModels set of initial variation models, found by relation
     * @return BaseActiveRecord[] list of [[BaseActiveRecord]]
     */
    private function adjustVariationModels(array $initialVariationModels)
    {
        $options = $this->findOptionModels();

        $variationsRelation = $this->owner->getRelation($this->variationsRelation);

        $optionReferenceAttribute = $this->variationOptionReferenceAttribute;
        list($ownerReferenceAttribute) = array_keys($variationsRelation->link);

        /* @var $variationModels BaseActiveRecord[] */
        /* @var $confirmedInitialVariationModels BaseActiveRecord[] */
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
                $this->fillUpVariationModelDefaults($variationModel);
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

    /**
     * Fills up default attributes for the variation model.
     * @param BaseActiveRecord $variationModel model instance.
     * @throws InvalidConfigException on invalid configuration.
     */
    private function fillUpVariationModelDefaults($variationModel)
    {
        if ($this->variationModelDefaultAttributes === null) {
            $variationsRelation = $this->owner->getRelation($this->variationsRelation);
            if (isset($variationsRelation->where)) {
                foreach ((array)$variationsRelation->where as $attribute => $value) {
                    if ($variationModel->hasAttribute($attribute)) {
                        $variationModel->{$attribute} = $value;
                    }
                }
            }
            return;
        }

        if (is_callable($this->variationModelDefaultAttributes, true)) {
            call_user_func($this->variationModelDefaultAttributes, $variationModel);
            return;
        }
        if (!is_array($this->variationModelDefaultAttributes)) {
            throw new InvalidConfigException('"' . get_class($this) . '::variationModelDefaultAttributes" must be a valid callable or an array.');
        }
        foreach ($this->variationModelDefaultAttributes as $attribute => $value) {
            $variationModel->{$attribute} = $value;
        }
    }

    /**
     * Finds available variation option models.
     * @return BaseActiveRecord[] option models list.
     */
    private function findOptionModels()
    {
        /* @var $optionModelClass BaseActiveRecord */
        $optionModelClass = $this->optionModelClass;
        $query = $optionModelClass::find();
        if ($this->optionQueryFilter !== null) {
            if (is_callable($this->optionQueryFilter)) {
                call_user_func($this->optionQueryFilter, $query);
            } else {
                $query->andWhere($this->optionQueryFilter);
            }
        }
        return $query->all();
    }

    /**
     * @param string $name variation attribute name.
     * @return mixed|null attribute value.
     * @throws InvalidConfigException on invalid attribute map.
     */
    private function fetchVariationAttributeDefaultValue($name)
    {
        $default = $this->variationAttributeDefaultValueMap[$name];
        if ($default === null) {
            return null;
        }
        if (!is_scalar($default)) {
            if (!is_callable($default)) {
                throw new InvalidConfigException("Default value map for '{$name}' should be a scalar or valid callback.");
            }
            return call_user_func($default, $this->owner);
        }
        return $this->owner->{$default};
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
            if ($this->owner !== null) {
                $model = $this->getDefaultVariationModel();
                if (is_object($model) && $model->hasAttribute($name)) {
                    $result = $model->$name;
                    if (empty($result) && array_key_exists($name, $this->variationAttributeDefaultValueMap)) {
                        return $this->fetchVariationAttributeDefaultValue($name);
                    }
                    return $result;
                } elseif (array_key_exists($name, $this->variationAttributeDefaultValueMap)) {
                    return $this->fetchVariationAttributeDefaultValue($name);
                }
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
            if ($this->owner !== null) {
                $model = $this->getDefaultVariationModel();
                if ($model->hasAttribute($name)) {
                    $model->$name = $value;
                    return;
                }
            }
            throw $exception;
        }
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        if (parent::canGetProperty($name, $checkVars)) {
            return true;
        }
        if (array_key_exists($name, $this->variationAttributeDefaultValueMap)) {
            return true;
        }
        if ($this->owner == null) {
            return false;
        }
        $model = $this->getDefaultVariationModel();
        return is_object($model) && $model->hasAttribute($name);
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        if (parent::canSetProperty($name, $checkVars)) {
            return true;
        }
        if ($this->owner == null) {
            return false;
        }
        $model = $this->getDefaultVariationModel();
        return is_object($model) && $model->hasAttribute($name);
    }

    // Events :

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            Model::EVENT_AFTER_VALIDATE => 'afterValidate',
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
        ];
    }

    /**
     * Handles owner 'afterValidate' event, ensuring variation models are validated as well
     * in case they have been fetched.
     * @param \yii\base\Event $event event instance.
     */
    public function afterValidate($event)
    {
        if (!$this->getIsVariationModelsInitialized()) {
            return;
        }
        $variationModels = $this->getVariationModels();
        foreach ($variationModels as $variationModel) {
            if (!$variationModel->validate()) {
                $this->owner->addErrors($variationModel->getErrors());
            }
        }
    }

    /**
     * Handles owner 'afterInsert' and 'afterUpdate' events, ensuring variation models are saved
     * in case they have been fetched before.
     * @param \yii\base\Event $event event instance.
     */
    public function afterSave($event)
    {
        if (!$this->getIsVariationModelsInitialized()) {
            return;
        }

        $variationsRelation = $this->owner->getRelation($this->variationsRelation);
        list($ownerReferenceAttribute) = array_keys($variationsRelation->link);

        $variationModels = $this->getVariationModels();
        foreach ($variationModels as $variationModel) {
            $variationModel->{$ownerReferenceAttribute} = $this->owner->getPrimaryKey();
            if ($this->variationSaveFilter === null || call_user_func($this->variationSaveFilter, $variationModel)) {
                $variationModel->save(false);
            } else {
                if (!$variationModel->getIsNewRecord()) {
                    $variationModel->delete();
                }
            }
        }
    }
}