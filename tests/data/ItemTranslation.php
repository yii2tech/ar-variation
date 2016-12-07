<?php

namespace yii2tech\tests\unit\ar\variation\data;

use yii\db\ActiveRecord;

/**
 * @property int $itemId
 * @property int $languageId
 * @property string $title
 * @property string $description
 */
class ItemTranslation extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ItemTranslation';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['title', 'required'],
            ['description', 'required'],
            ['languageId', 'required'],
        ];
    }
}