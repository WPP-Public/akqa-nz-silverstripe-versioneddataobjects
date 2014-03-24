<?php

class VersionedDataObject extends Versioned
{
    /**
     *
     */
    public function __construct()
    {
        parent::__construct(
            array(
                'Stage',
                'Live'
            )
        );
    }
    /**
     * @param  null  $class
     * @param        $extension
     * @param        $args
     * @return array
     */
    public static function get_extra_config($class, $extension, $args)
    {
        return array(
            'db'                => array(
                'Version' => 'Int',
            ),
            'searchable_fields' => array()
        );
    }
    /**
     * @param $fields
     */
    public function updateSummaryFields(&$fields)
    {
        $fields = array_merge(
            $fields,
            array(
                'isModifiedNice'  => 'Modified',
                'isPublishedNice' => 'Published'
            )
        );
    }
    /**
     * @param $fields
     */
    public function updateSearchableFields(&$fields)
    {
        unset($fields['isModifiedNice']);
        unset($fields['isPublishedNice']);
    }
    /**
     * @return bool
     */
    public function isNew()
    {
        $id = $this->owner->ID;
        if (empty($id)) {
            return true;
        }
        if (is_numeric($id)) {
            return false;
        }
    }
    /**
     * @return bool
     */
    public function isPublished()
    {
        if ($this->isNew()) {
            return false;
        }

        $table = $this->owner->class;

        while (($p = get_parent_class($table)) !== 'DataObject') {
            $table = $p;
        }

        return (bool) DB::query("SELECT \"ID\" FROM \"{$table}_Live\" WHERE \"ID\" = {$this->owner->ID}")->value();
    }
    /**
     * @param $value
     * @return string
     */
    protected function getBooleanNice($value)
    {
        return $value ? 'Yes' : 'No';
    }
    /**
     * @return mixed
     */
    public function isPublishedNice()
    {
        return $this->getBooleanNice($this->isPublished());
    }
    /**
     * @return mixed
     */
    public function isModifiedNice()
    {
        return $this->getBooleanNice($this->stagesDiffer('Stage', 'Live'));
    }
    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('Version');
        $fields->removeByName('Versions');
    }
    /**
     *
     */
    public function onBeforeWrite()
    {
        $fieldsIgnoredByVersioning = array('Version');

        $changedFields = array_keys($this->owner->getChangedFields(true, 2));
        $oneChangedFields = array_keys($this->owner->getChangedFields(true, 1));

        if ($oneChangedFields && !array_diff($changedFields, $fieldsIgnoredByVersioning)) {
            // This will have the affect of preserving the versioning
            $this->migrateVersion($this->owner->Version);
        }
    }
}
