<?php

namespace yii2tech\tests\unit\ar\variation;

use yii\helpers\ArrayHelper;
use Yii;

/**
 * Base class for the test cases.
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();

        $this->setupTestDbData();
    }

    protected function tearDown()
    {
        $this->destroyApplication();
    }

    /**
     * Populates Yii::$app with a new application
     * The application will be destroyed on tearDown() automatically.
     * @param array $config The application configuration, if needed
     * @param string $appClass name of the application class to create
     */
    protected function mockApplication($config = [], $appClass = '\yii\console\Application')
    {
        new $appClass(ArrayHelper::merge([
            'id' => 'testapp',
            'basePath' => __DIR__,
            'vendorPath' => $this->getVendorPath(),
            'components' => [
                'db' => [
                    'class' => 'yii\db\Connection',
                    'dsn' => 'sqlite::memory:',
                ],
            ],
        ], $config));
    }

    /**
     * @return string vendor path
     */
    protected function getVendorPath()
    {
        return dirname(__DIR__) . '/vendor';
    }

    /**
     * Destroys application in Yii::$app by setting it to null.
     */
    protected function destroyApplication()
    {
        Yii::$app = null;
    }

    /**
     * Setup tables for test ActiveRecord
     */
    protected function setupTestDbData()
    {
        $db = Yii::$app->getDb();

        // Structure :

        $table = 'Language';
        $columns = [
            'id' => 'pk',
            'name' => 'string',
            'locale' => 'string',
        ];
        $db->createCommand()->createTable($table, $columns)->execute();

        $table = 'Item';
        $columns = [
            'id' => 'pk',
            'name' => 'string',
        ];
        $db->createCommand()->createTable($table, $columns)->execute();

        $table = 'ItemTranslation';
        $columns = [
            'itemId' => 'integer',
            'languageId' => 'integer',
            'title' => 'string',
            'description' => 'string',
            'PRIMARY KEY(itemId, languageId)'
        ];
        $db->createCommand()->createTable($table, $columns)->execute();

        $table = 'Article';
        $columns = [
            'id' => 'pk',
            'name' => 'string',
        ];
        $db->createCommand()->createTable($table, $columns)->execute();

        $table = 'ArticleContent';
        $columns = [
            'id' => 'pk',
            'articleId' => 'integer',
            'languageId' => 'integer',
            'censorType' => 'string',
            'title' => 'string',
            'content' => 'string',
        ];
        $db->createCommand()->createTable($table, $columns)->execute();

        // Data :

        $db->createCommand()->batchInsert('Language', ['name', 'locale'], [
            ['English', 'en'],
            ['German', 'de'],
        ])->execute();

        $db->createCommand()->batchInsert('Item', ['name'], [
            ['item1'],
            ['item2'],
        ])->execute();

        $db->createCommand()->batchInsert('ItemTranslation', ['itemId', 'languageId', 'title', 'description'], [
            [1, 1, 'item1-en', 'item1-desc-en'],
            [1, 2, 'item1-de', 'item1-desc-de'],
            [2, 2, 'item2-de', 'item2-desc-de'],
        ])->execute();
    }
}
