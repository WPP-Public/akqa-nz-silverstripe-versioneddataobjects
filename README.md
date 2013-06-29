# SilverStripe Versioned DataObjects

Provides Versioned DataObjects and CMS publishing buttons in SilverStripe

## Installation (with composer)

	$ composer require heyday/silverstripe-versioneddataobjects

## Example

![Versioned DataObject Example](resources/example.png?raw=true)

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

        $fields->addFieldsToTab(
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