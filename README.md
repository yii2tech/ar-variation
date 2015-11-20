ActiveRecord Variation Extension for Yii 2
==========================================

This extension provides support for ActiveRecord variation via related models.
In particular it allows implementing i18n feature for ActiveRecord.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://poser.pugx.org/yii2tech/ar-variation/v/stable.png)](https://packagist.org/packages/yii2tech/ar-variation)
[![Total Downloads](https://poser.pugx.org/yii2tech/ar-variation/downloads.png)](https://packagist.org/packages/yii2tech/ar-variation)
[![Build Status](https://travis-ci.org/yii2tech/ar-variation.svg?branch=master)](https://travis-ci.org/yii2tech/ar-variation)


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist yii2tech/ar-variation
```

or add

```json
"yii2tech/ar-variation": "*"
```

to the require section of your composer.json.


Usage
-----

This extension provides support for ActiveRecord variation via related models.
Variation means some particular entity have an attributes (fields), which values should vary depending on actual
selected option.
The most common example of such case is i18n feature: imagine we have an item, which title and description should
be provided on several different languages. In relational database there will be 2 different tables for this case:
one for the item and second - for the item translation, which have item id and language id along with actual title
and description. A DDL for such solution will be following:

```sql
CREATE TABLE `Item`
(
   `id` integer NOT NULL AUTO_INCREMENT,
   `name` varchar(64) NOT NULL,
   `price` integer,
    PRIMARY KEY (`id`)
) ENGINE InnoDB;

CREATE TABLE `ItemTranslation`
(
   `itemId` integer NOT NULL,
   `languageId` varchar(5) NOT NULL,
   `title` varchar(64) NOT NULL,
   `description` TEXT,
    PRIMARY KEY (`itemId`, `languageId`)
    FOREIGN KEY (`itemId`) REFERENCES `Item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`languageId`) REFERENCES `Language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
) ENGINE InnoDB;
```

Usually in most cases there is no need for 'Item' to know about all its translations - it is enough to fetch
only one, which is used as web application interface language.

This extension provides [[\yii2tech\ar\variation\VariationBehavior]] ActiveRecord behavior for such solution
support in Yii2. You'll have to create an ActiveRecord class for 'Language', 'Item' and 'ItemTranslation' and
attach [[\yii2tech\ar\variation\VariationBehavior]] in the following way:

```php
class Item extends ActiveRecord
{
    public function behaviors()
    {
        return [
            'translationBehavior' => [
                'class' => VariationBehavior::className(),
                'variationsRelation' => 'translations',
                'defaultVariationRelation' => 'defaultTranslation',
                'variationOptionReferenceAttribute' => 'languageId',
                'optionModelClass' => Language::className(),
                'defaultVariationOptionReference' => function() {return Yii::$app->language;},
                'variationAttributeDefaultValueMap' => [
                    'title' => 'name'
                ],
            ],
        ];
    }

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
        return $this->hasDefaultVariationRelation(); // convert "has many translations" into "has one defaultTranslation"
    }
}
```

Pay attention to the fact behavior is working through the 'has many' relation declared in the main ActiveRecord to
the variation ActiveRecord. In the above example it will be relation 'translations'. You also have to declare default
variation relation as 'has one', this can be easily done via [[\yii2tech\ar\variation\VariationBehavior::hasDefaultVariationRelation()]]
method. Such relation inherits all infomation from the source one and applies extra condition on variation option reference,
which is determined by [[\yii2tech\ar\variation\VariationBehavior::defaultVariationOptionReference]].


## Accessing variation attributes <span id="accessing-variation-attributes"></span>

Being applied [[\yii2tech\ar\variation\VariationBehavior]] allows access to the variation fields as they were
the main one:

```php
$item = Item::findOne(1);
echo $item->title; // equal to `$item->defaultTranslation->title`
echo $item->description; // equal to `$item->defaultTranslation->description`
```

If it could be the main entity don't have a variation for particular option, you can use [[\yii2tech\ar\variation\VariationBehavior::variationAttributeDefaultValueMap]]
to provide the default value for the variation fields as it was done for 'title' in the above example:

```php
$item = new Item(); // of course there is no translation for the new item
$item->name = 'new item';
echo $item->title; // outputs 'new item'
```


## Querying variations <span id="querying-variations"></span>

As it has been already said [[\yii2tech\ar\variation\VariationBehavior]] works through relations. Thus, in order to make
variation attributes feature work, it will perform an extra query to retrieve the default variation model, which may
produce performace impact in case you are working with several models.
In order to reduce number of queries you may use `with()` on the default variation relation:

```php
$items = Item::find()->with('defaultTranslation')->all(); // only 2 queries will be performed
foreach ($items as $item) {
    echo $item->title . '<br>';
}
```

You may as well use main variations relation in `with()`. In this case default variation will be fetched from it without
extra query:

```php
$items = Item::find()->with('translations')->all(); // only 2 queries will be performed
foreach ($items as $item) {
    echo $item->title . '<br>'; // no extra query
    var_dump($item->defaultTranslation);  // no extra query, `defaultTranslation` is populated from `translations`
}
```

If you are using relational database you can also use [[\yii\db\ActiveQuery::joinWith()]]:

```php
$items = Item::find()->joinWith('defaultTranslation')->all();
```

You may apply 'with' for the variation relation as default scope for the main ActiveRecord query:

```php
class Item extends ActiveRecord
{
    // ...

    public static function find()
    {
        return parent::find()->with('defaultTranslation');
    }
}
```


## Creating variation setup web interface <span id="creating-variation-setup-web-interface"></span>

Usage of [[\yii2tech\ar\variation\VariationBehavior]] simplifies management of variations and creating a web interface
for their setup.

The web controller for variation management may look like following:

```php
use yii\base\Model;
use yii\web\Controller;
use Yii;

class ConfigController extends Controller
{
    public function actionCreate()
    {
        $model = new Item();

        $post = Yii::$app->request->post();
        if ($model->load($post) && Model::loadMultiple($model->getVariationModels(), $post) && $model->save()) {
            return $this->redirect(['index']);
        }

        return $this->render('index', [
            'model' => $model,
        ]);
    }
}
```

Note that variation models should be populated with data from request manually, but they will be validated and saved
automatically - you don't need to do this manually. Automatic processing of variation models will be performed only, if
they have been fetched before owner validation or saving triggered. Thus it will not affect pure owner validation or saving.

The form view file can be following:

```php
<?php
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $model Item */
?>
<?php $form = ActiveForm::begin(); ?>

<?= $form->field($model, 'name'); ?>
<?= $form->field($model, 'price'); ?>

<?php foreach ($model->getVariationModels() as $index => $variationModel): ?>
    <?= $form->field($variationModel, "[{$index}]title")->label($variationModel->getAttributeLabel('title') . ' (' . $variationModel->languageId . ')'); ?>
    <?= $form->field($variationModel, "[{$index}]description")->label($variationModel->getAttributeLabel('description') . ' (' . $variationModel->languageId . ')'); ?>
<?php endforeach;?>

<div class="form-group">
    <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
</div>

<?php ActiveForm::end(); ?>
```
