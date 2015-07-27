<?php

namespace yii2tech\tests\unit\ar\variation;

use yii2tech\ar\variation\VariationBehavior;
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

    public function testGetVariationModels()
    {
        /* @var $item Item|VariationBehavior */

        $item = Item::findOne(1);

        $variationModels = $item->getVariationModels();
        $this->assertCount(2, $variationModels);
        $this->assertEquals($item->id, $variationModels[0]->itemId);
        $this->assertEquals($item->id, $variationModels[1]->itemId);
        $this->assertFalse($variationModels[0]->isNewRecord);
        $this->assertFalse($variationModels[1]->isNewRecord);

        $item = Item::findOne(2);
        $variationModels = $item->getVariationModels();
        $this->assertCount(2, $variationModels);
        $this->assertEquals($item->id, $variationModels[0]->itemId);
        $this->assertEquals($item->id, $variationModels[1]->itemId);
        $this->assertTrue($variationModels[0]->isNewRecord);
        $this->assertFalse($variationModels[1]->isNewRecord);
    }
}