# SilverStripe Versioned DataObjects

Provides Versioned DataObjects and CMS publishing buttons in SilverStripe

## Installation (with composer)

	$ composer require heyday/silverstripe-versioneddataobjects

## BetterButtons Integration

	This module works with unclecheese/betterbuttons version 1.2.8

## Example

![Versioned DataObject Example](resources/example.png?raw=true)
![Versioned DataObject Example 2](resources/example2.png?raw=true)

## Usage

`Slice.php`

```php
class Slice extends DataObject
{
    private static $has_one = array(
        'Parent' => 'SiteTree'
    );
    private static $extensions = array(
        'VersionedDataObject'
    );
}
```

`Page.php`

```php
class Page extends SiteTree
{
    private static $has_many = array(
        'Slices' => 'Slice'
    );
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
        $config->addComponent(new VersionedDataObjectDetailsForm());

        return $fields;
    }
}
```

## Unit testing

None :(

##License

SilverStripe Versioned DataObjects is licensed under an [MIT license](http://heyday.mit-license.org/)
