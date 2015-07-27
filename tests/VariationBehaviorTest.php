<?php

namespace yii2tech\tests\unit\ar\variation;

use yii2tech\tests\unit\ar\variation\data\Item;

class VariationBehaviorTest extends TestCase
{
    public function testGetVariationAttributes()
    {
        /* @var $item Item */

        $item = Item::findOne(1);

        $this->assertNotEmpty($item->title);
        $this->assertTrue($item->isRelationPopulated('defaultTranslation'));
        $this->assertEquals($item->defaultTranslation->title, $item->title);
        $this->assertNotEmpty($item->description);
        $this->assertEquals($item->defaultTranslation->description, $item->description);

        $item = Item::findOne(2);

        $this->assertNotEmpty($item->title);
        $this->assertEmpty($item->defaultTranslation);
        $this->setExpectedException('yii\base\UnknownPropertyException');
        $item->description;
    }
}