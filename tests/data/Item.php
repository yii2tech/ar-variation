<?php

namespace yii2tech\tests\unit\ar\variation\data;

use yii\db\ActiveRecord;
use yii2tech\ar\variation\VariationBehavior;

/**
 * @property int $id
 * @property string $name
 * @property int $categoryId
 *
 * @property Category|null $category
 * @property ItemTranslation[]|array $translations
 * @property ItemTranslation|null $defaultTranslation
 */
class Item extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'translations' => [
                'class' => VariationBehavior::className(),
                'variationsRelation' => 'translations',
                'defaultVariationRelation' => 'defaultTranslation',
                'variationOptionReferenceAttribute' => 'languageId',
                'optionModelClass' => Language::className(),
                'defaultVariationOptionReference' => 1,
                'variationAttributeDefaultValueMap' => [
                    'title' => 'name',
                    'brief' => null,
                    'summary' => function() {return 'default';},
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'Item';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['categoryId', 'integer'],
            ['name', 'required'],
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCategory()
    {
        return $this->hasMany(Category::className(), ['id' => 'categoryId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTranslations()
    {
        return $this->hasMany(ItemTranslation::className(), ['itemId' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDefaultTranslation()
    {
        return $this->hasDefaultVariationRelation();
    }
}