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
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'censoredContent' => [
                '__class' => VariationBehavior::class,
                'variationsRelation' => 'censoredContents',
                'variationOptionReferenceAttribute' => 'languageId',
                'optionModelClass' => Language::class,
            ],
            'uncensoredContent' => [
                '__class' => VariationBehavior::class,
                'variationsRelation' => 'uncensoredContents',
                'variationOptionReferenceAttribute' => 'languageId',
                'optionModelClass' => Language::class,
                'variationModelDefaultAttributes' => [
                    'censorType' => 'no'
                ],
            ],
            'callbackContent' => [
                '__class' => VariationBehavior::class,
                'variationsRelation' => 'uncensoredContents',
                'variationOptionReferenceAttribute' => 'languageId',
                'optionModelClass' => Language::class,
                'variationModelDefaultAttributes' => function ($model) {
                    $model->censorType = 'callback';
                },
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'Article';
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
    public function getContents()
    {
        return $this->hasMany(ArticleContent::class, ['articleId' => 'id']);
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