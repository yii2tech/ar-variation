Yii 2 ActiveRecord Variation extension Change Log
=================================================

1.0.5, July 30, 2019
--------------------

- Bug #24: Fix ambiguous column error while joining multiple `VariationBehavior::$defaultVariationRelation` (klimov-paul)


1.0.4, April 9, 2018
--------------------

- Bug #20: Fixed variation relations are not saved in case using Yii 2.0.14 (klimov-paul)


1.0.3, December 23, 2016
------------------------

- Bug #17: Fixed owner validation and saving fails, if default variation relation is initialized with `null` (klimov-paul)


1.0.2, December 8, 2016
-----------------------

- Enh #16: Automatic creation and saving of default variation model provided (klimov-paul)


1.0.1, February 10, 2016
------------------------

- Bug #13: Preset value for `VariationBehavior::$defaultVariationRelation` removed (klimov-paul)
- Bug #12: `VariationBehavior` does not use `ActiveRecord::getRelation()` while retrieving relation instance (klimov-paul)
- Bug #11: Relation declared via `VariationBehavior::hasDefaultVariationRelation()` does not support `LEFT JOIN` (klimov-paul)


1.0.0, December 29, 2015
------------------------

- Initial release.
