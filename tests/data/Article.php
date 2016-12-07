<?php

namespace yii2tech\tests\unit\ar\variation\data;

use yii\db\ActiveRecord;
use yii2tech\ar\variation\VariationBehavior;

/**
 * @property int $id
 * @property string $name
 *
 * @property ArticleContent[]|array $contents
 * @property ArticleContent[]|array $censoredContents
 * @property ArticleContent[]|array $uncensoredContents
 */
class Article extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'censoredContent' => [
                'class' => VariationBehavior::className(),
                'variationsRelation' => 'censoredContents',
                'variationOptionReferenceAttribute' => 'languageId',
                'optionModelClass' => Language::className(),
            ],
            'uncensoredContent' => [
                'class' => VariationBehavior::className(),
                'variationsRelation' => 'uncensoredContents',
                'variationOptionReferenceAttribute' => 'languageId',
                'optionModelClass' => Language::className(),
                'variationModelDefaultAttributes' => [
                    'censorType' => 'no'
                ],
            ],
            'callbackContent' => [
                'class' => VariationBehavior::className(),
                'variationsRelation' => 'uncensoredContents',
                'variationOptionReferenceAttribute' => 'languageId',
                'optionModelClass' => Language::className(),
                'variationModelDefaultAttributes' => function ($model) {
                    $model->censorType = 'callback';
                },
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'Article';
    }

    /**
     * @inheritdoc
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
    public function getContents()
    {
        return $this->hasMany(ArticleContent::className(), ['articleId' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCensoredContents()
    {
        return $this->getContents()->andWhere(['censorType' => 'censored']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUncensoredContents()
    {
        return $this->getContents()->andWhere(['censorType' => 'uncensored']);
    }
}