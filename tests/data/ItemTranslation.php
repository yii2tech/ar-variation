<?php

namespace yii2tech\tests\unit\ar\variation\data;

use yii\db\ActiveRecord;

/**
 * @property integer $itemId
 * @property integer $languageId
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
}