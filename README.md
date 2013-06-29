# SilverStripe Versioned DataObjects

## Installation (with composer)

	$ composer require heyday/silverstripe-versioneddataobjects

## Usage

```php
class MyDataObject extends DataObject
{
	private static $extensions = array(
		'VersionedDataObject'
	);
}
```

## Unit testing

None :(