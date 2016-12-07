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
selected option. In database structure variation is implemented as many-to-many relation with extra columns at
junction entity.

The most common example of such case is i18n feature: imagine we have an item, which title and description should
be provided on several different languages. In relational database there will be 2 different tables for this case:
one for the item and second - for the item translation, which have item id and language id along with actual title
and description. A DDL for such solution will be following:

```sql
CREATE TABLE `Language`
(
   `id` varchar(5) NOT NULL,
   `name` varchar(64) NOT NULL,
   `locale` varchar(5) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE InnoDB;

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
            'translations' => [
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
method. Such relation inherits all information from the source one and applies extra condition on variation option reference,
which is determined by [[\yii2tech\ar\variation\VariationBehavior::defaultVariationOptionReference]]. This reference should
provide default value, which matches value of [[\yii2tech\ar\variation\VariationBehavior::variationOptionReferenceAttribute]] of
the variation entity.


## Accessing variation attributes <span id="accessing-variation-attributes"></span>

Having `defaultVariationRelation` is important for the usage of the variation attributes.
Being applied [[\yii2tech\ar\variation\VariationBehavior]] allows access to the variation fields as they were
the main one:

```php
$item = Item::findOne(1);
echo $item->title; // equal to `$item->defaultTranslation->title`
echo $item->description; // equal to `$item->defaultTranslation->description`
```

If it could be the main entity don't have a variation for particular option, you can use [[\yii2tech\ar\variation\VariationBehavior::$variationAttributeDefaultValueMap]]
to provide the default value for the variation fields as it was done for 'title' in the above example:

```php
$item = new Item(); // of course there is no translation for the new item
$item->name = 'new item';
echo $item->title; // outputs 'new item'
```


## Querying variations <span id="querying-variations"></span>

As it has been already said [[\yii2tech\ar\variation\VariationBehavior]] works through relations. Thus, in order to make
variation attributes feature work, it will perform an extra query to retrieve the default variation model, which may
produce performance impact in case you are working with several models.
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


## Access particular variation <span id="access-particular-variation"></span>

You can always access default variation model via `getDefaultVariationModel()` method:

```php
$item = Item::findOne(1);
$variationModel = $item->getDefaultVariationModel(); // get default variation instance
echo $item->defaultVariationModel->title; // default variation is also available as virtual property
```

However, in some cases there is a need of accessing particular variation, but not default one.
This can be done via `getVariationModel()` method:

```php
$item = Item::findOne(1);
$frenchTranslation = $item->getVariationModel('fr');
$russianTranslation = $item->getVariationModel('ru');
```

> Note: method `getVariationModel()` will load [[\yii2tech\ar\variation\VariationBehavior::variationsRelation]] relation
  fully, which may reduce performance. You should always prefer usage of [[getDefaultVariationModel()]] method if possible.
  You may also use eager loading for `variationsRelation` with extra condition filtering the results in order to save
  performance.


## Creating variation setup web interface <span id="creating-variation-setup-web-interface"></span>

Usage of [[\yii2tech\ar\variation\VariationBehavior]] simplifies management of variations and creating a web interface
for their setup.

The web controller for variation management may look like following:

```php
use yii\base\Model;
use yii\web\Controller;
use Yii;

class ItemController extends Controller
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
<?php endforeach; ?>

<div class="form-group">
    <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
</div>

<?php ActiveForm::end(); ?>
```


## Saving default variation <span id="saving-default-variation"></span>

It is not necessary to process all possible variations at once - you can operate only single variation model, validating
and saving it. For example: you can provide a web interface where user can setup only the translation for the current language.
Doing so it is better to setup [[\yii2tech\ar\variation\VariationBehavior::$variationAttributeDefaultValueMap]] value, allowing
magic access to the variation attributes.
Being fetched default variation model will be validated and saved along with the main model:

```php
$item = Item::findOne($id);

$item->title = ''; // setup of `$item->defaultTranslation->title`
var_dump($item->validate()); // outputs: `false`

$item->title = 'new title';
$item->save(); // invokes `$item->defaultTranslation->save()`
```

In case attribute in mentioned at this map it will be available for setting as well, even if default variation model
does not exists: in such case it will be created automatically. For example:

```php
$item = new Item();
$item->name = 'new name';
$item->title = 'translation title'; // setup of `$item->defaultTranslation` attribute, creating default variation model
$item->description = 'translation description';
$item->save(); // saving both main model and default variation model
```

Marking variation attributes at the main model as 'safe' you can create a web interface, which sets up them in a simple way.
Model code should look like following:

```php
class Item extends ActiveRecord
{
    public function behaviors()
    {
        return [
            'translations' => [
                'class' => VariationBehavior::className(),
                // ...
                'variationAttributeDefaultValueMap' => [
                    'title' => 'name',
                    'description' => null,
                ],
            ],
        ];
    }

    public function rules()
    {
        return [
            // ...
            [['title', 'description'], 'safe'] // allow 'title' and 'description' to be populated via main model
        ];
    }

    // ...
}
```

Inside the view you can use variation attributes at the main model directly:

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

<?= $form->field($model, "title"); ?>
<?= $form->field($model, "description")->textArea(); ?>

<div class="form-group">
    <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
</div>

<?php ActiveForm::end(); ?>
```

Then the controller code will be simple:

```php
use yii\web\Controller;
use Yii;

class ItemController extends Controller
{
    public function actionCreate()
    {
        $model = new Item();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            // variation attributes are populated automatically
            // and variation model saved
            return $this->redirect(['index']);
        }

        return $this->render('index', [
            'model' => $model,
        ]);
    }
}
```


## Additional variation conditions <span id="additional-variation-conditions"></span>

There are case, when variation options or variation entities have extra filtering conditions or attributes.
For example: assume we have a database of the developers with their payment rates, which varies per particular
work type. Work types are grouped by categories: 'front-end', 'back-end', 'database' etc. And payment rates should
be set for regular working time and for over-timing separately.
The DDL for such use case can be following:

```sql
CREATE TABLE `Developer`
(
   `id` integer NOT NULL AUTO_INCREMENT,
   `name` varchar(64) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE InnoDB;

CREATE TABLE `WorkTypeGroup`
(
   `id` integer NOT NULL AUTO_INCREMENT,
   `name` varchar(64) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE InnoDB;

CREATE TABLE `WorkType`
(
   `id` integer NOT NULL AUTO_INCREMENT,
   `name` varchar(64) NOT NULL,
   `groupId` integer NOT NULL,
    PRIMARY KEY (`id`)
    FOREIGN KEY (`groupId`) REFERENCES `WorkTypeGroup` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
) ENGINE InnoDB;

CREATE TABLE `DeveloperPaymentRate`
(
   `developerId` integer NOT NULL,
   `workTypeId` varchar(5) NOT NULL,
   `paymentRate` integer NOT NULL,
   `isOvertime` integer(1) NOT NULL,
    PRIMARY KEY (`developerId`, `workTypeId`)
    FOREIGN KEY (`developerId`) REFERENCES `Developer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`workTypeId`) REFERENCES `WorkType` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
) ENGINE InnoDB;
```

In this case you may want to setup 'front-end' and 'back-end' separately (using different web interface or something).
You can apply an extra filtering condition for the 'option' Active Record query using [[\yii2tech\ar\variation\VariationBehavior::optionQueryFilter]]:

```php
class Developer extends ActiveRecord
{
    public function behaviors()
    {
        return [
            'frontEndPaymentRates' => [
                'class' => VariationBehavior::className(),
                'variationsRelation' => 'paymentRates',
                'variationOptionReferenceAttribute' => 'workTypeId',
                'optionModelClass' => WorkType::className(),
                'optionQueryFilter' => [
                    'groupId' => WorkType::GROUP_FRONT_END // add 'where' condition to the `WorkType` query
                ],
            ],
            'backEndPaymentRates' => [
                'class' => VariationBehavior::className(),
                'variationsRelation' => 'paymentRates',
                'variationOptionReferenceAttribute' => 'workTypeId',
                'optionModelClass' => WorkType::className(),
                // you can use a PHP callable as filter as well:
                'optionQueryFilter' => function ($query) {
                    $query->andWhere(['groupId' => WorkType::GROUP_BACK_END]);
                }
            ],
        ];
    }
    // ...
}
```

In this case you'll have to access `getVariationModels()` from the behavior instance rather then the owner directly:

```php
$developer = new Developer();
$developer->getBehavior('frontEndPaymentRates')->getVariationModels(); // get 'front-end' payment rates
$developer->getBehavior('backEndPaymentRates')->getVariationModels(); // get 'back-end' payment rates
```

You may as well separate variations using 'overtime' conditions: setup regular time and overtime payment rates in
different process. For such purpose you'll have to declare 2 separated relations for 'regular time' and 'overtime'
payment rates:

```php
class Developer extends ActiveRecord
{
    public function behaviors()
    {
        return [
            'regularPaymentRates' => [
                'class' => VariationBehavior::className(),
                'variationsRelation' => 'regularPaymentRates',
                'variationOptionReferenceAttribute' => 'workTypeId',
                'optionModelClass' => WorkType::className(),
            ],
            'overtimePaymentRates' => [
                'class' => VariationBehavior::className(),
                'variationsRelation' => 'overtimePaymentRates',
                'variationOptionReferenceAttribute' => 'workTypeId',
                'optionModelClass' => WorkType::className(),
            ],
        ];
    }

    public function getPaymentRates()
    {
        return $this->hasMany(PaymentRates::className(), ['developerId' => 'id']); // basic 'payment rates' relation
    }

    public function getRegularPaymentRates()
    {
        return $this->getPaymentRates()->andWhere(['isOvertime' => false]); // regular payment rates
    }

    public function getOvertimePaymentRates()
    {
        return $this->getPaymentRates()->andWhere(['isOvertime' => true]); // overtime payment rates
    }

    // ...
}
```

In this case variation will be loaded only for particular rate type and saved with corresponding value of the `isOvertime`
flag attribute. However, automatic detection of the extra variation model attributes will work only for 'hash' query conditions.
If you have a complex variation option filtering logic, you'll need to setup [[\yii2tech\ar\variation\VariationBehavior::variationModelDefaultAttributes]]
manually.

In the example above you may not want to save empty variation data in database: if particular developer have no particular
'front-end' skill like 'AngularJS' he have no payment rate for it and thus there is no reason to save an empty 'PaymentRate'
record for it.
You may use [[\yii2tech\ar\variation\VariationBehavior::variationSaveFilter]] to determine which variation record should
be saved or not. For example:

```php
class Developer extends ActiveRecord
{
    public function behaviors()
    {
        return [
            'paymentRates' => [
                'class' => VariationBehavior::className(),
                'variationsRelation' => 'regularPaymentRates',
                'variationOptionReferenceAttribute' => 'workTypeId',
                'optionModelClass' => WorkType::className(),
                'variationSaveFilter' => function ($model) {
                    return !empty($model->paymentRate);
                },
            ],
        ];
    }

    // ...
}
```
