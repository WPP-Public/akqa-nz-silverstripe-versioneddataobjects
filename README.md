# SilverStripe Versioned DataObjects

This module provides an easy to use implementation of SilverStripe's built-in `Versioned` extension for DataObjects, along with admin components to manage your versioned things.

![Versioned DataObject Example](resources/example.png?raw=true)
![Versioned DataObject Example 2](resources/example2.png?raw=true)

## Installation (with composer)

	$ composer require heyday/silverstripe-versioneddataobjects

## Usage

The `VersionedDataObject` extension adds the same draft/published versioning used for pages to your own DataObjects, and any subclasses they have. Only fields in the table of a DataObject with this extension will be versioned. Related DataObjects need `VersionedDataObject` applied separately if required.

```php
class Slice extends DataObject
{
	private static $db = [
		'Content' => 'Text'
	];

	private static $has_one = [
		'Parent' => 'SiteTree'
	];

	private static $extensions = [
		'Heyday\VersionedDataObjects\VersionedDataObject'
	];
}
```

### Versioned DataObjects in a GridField

To use `VersionedDataObject` records in a GridField, `GridFieldDetailForm` needs to be replaced with `VersionedDataObjectDetailsForm`:

```php
// ...

public function getCMSFields()
{
	$fields = parent::getCMSFields();

	$fields->addFieldToTab(
		'Root.Slices',
		new GridField(
			'Slices',
			'Slices',
			$this->Slices(),
			$config = GridFieldConfig_RelationEditor::create()
		)
	);

	$config->removeComponentsByType('GridFieldDetailForm');
	$config->addComponent(new Heyday\VersionedDataObjects\VersionedDataObjectDetailsForm());

	return $fields;
}

// ...
```

### Versioned DataObjects in a ModelAdmin

```php
class SliceAdmin extends Heyday\VersionedDataObjects/VersionedModelAdmin
{
	private static $menu_title = 'Slices';

	private static $url_segment = 'slice';

	private static $managed_models = [
		'Slice'
	];
}
```

## Cavets / Troubleshooting

SilverStripe's versioning system uses global state to keep track of a *"reading mode"* that affects which tables records are read from and written to for all uses of the `Versioned` extension. This works on the front end of a site where you expect to see the currently selected stage, however it also alters what records are visible in the CMS if not adjusted for. The admin components supplied with this module work around this to always show draft records in the CMS.

If you have control over an ORM query, you can alter the behaviour of `Versioned` with DataQuery parameters to override the global reading mode setting:

```php
VersionedFoo::get()
	->setDataQueryParam(['Versioned.stage' => 'Stage']);
```

If you can't modify a query, you can use [`VersionedReadingMode`](https://github.com/heyday/silverstripe-versioneddataobjects/blob/master/code/VersionedReadingMode.php) from this module to change and restore the global reading mode around a piece of code:

```php
VersionedReadingMode::setStageReadingMode();

// ... code that runs queries

VersionedReadingMode::restoreOriginalReadingMode();
```

If you suspect you might be seeing an issue from incorrect reading mode, the global reading mode can be changed between *live* and *stage* by adding the query string `?stage=Stage` or `?stage=Live` to the current URL.

### BetterButtons compatibility

This module works with `unclecheese/betterbuttons` version `1.2.8`.

## Unit testing

None :(

##License

SilverStripe Versioned DataObjects is licensed under an [MIT license](http://heyday.mit-license.org/)
