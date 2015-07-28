<?php

namespace yii2tech\tests\unit\ar\variation\data;

use yii\db\ActiveRecord;
use yii2tech\ar\variation\VariationBehavior;

/**
 * @property integer $id
 * @property string $name
 *
 * @property ItemTranslation[]|array $translations
 * @property ItemTranslation|null $defaultTranslation
 */
class Item extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'translationBehavior' => [
                'class' => VariationBehavior::className(),
                'variationsRelation' => 'translations',
                'defaultVariationRelation' => 'defaultTranslation',
                'variationOptionReferenceAttribute' => 'languageId',
                'optionModelClass' => Language::className(),
                'defaultVariationOptionReference' => 1,
                'variationAttributeDefaultValueMap' => [
                    'title' => 'name'
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'Item';
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