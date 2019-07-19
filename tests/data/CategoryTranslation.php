<?php

namespace yii2tech\tests\unit\ar\variation\data;

use yii\db\ActiveRecord;

/**
 * @property int $categoryId
 * @property int $languageId
 * @property string $title
 */
class CategoryTranslation extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'CategoryTranslation';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['title', 'required'],
            ['languageId', 'required'],
        ];
    }
}