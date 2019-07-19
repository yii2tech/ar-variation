<?php

namespace yii2tech\tests\unit\ar\variation\data;

use yii\db\ActiveRecord;
use yii2tech\ar\variation\VariationBehavior;

/**
 * @property int $id
 * @property string $name
 *
 * @property CategoryTranslation[]|array $translations
 * @property CategoryTranslation|null $defaultTranslation
 */
class Category extends ActiveRecord
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
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'Category';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['name', 'required'],
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTranslations()
    {
        return $this->hasMany(CategoryTranslation::className(), ['categoryId' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDefaultTranslation()
    {
        return $this->hasDefaultVariationRelation();
    }
}