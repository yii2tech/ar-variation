<?php

namespace yii2tech\tests\unit\ar\variation\data;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $articleId
 * @property int $languageId
 * @property string $censorType
 * @property string $title
 * @property string $content
 */
class ArticleContent extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ArticleContent';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['title', 'required'],
            ['content', 'required'],
            ['languageId', 'required'],
            ['censorType', 'required'],
        ];
    }
}