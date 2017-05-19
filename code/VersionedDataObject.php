<?php

namespace Heyday\VersionedDataObjects;

use DB;
use FieldList;
use HTMLText;
use Versioned;

/**
 * Class VersionedDataObject
 */
class VersionedDataObject extends Versioned
{
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
     * @param $class
     * @param $extension
     * @param $args
     * @return array
     */
    public static function get_extra_config($class, $extension, $args)
    {
        return array(
            'db' => array('Version' => 'Int'),
            'searchable_fields' => array()
        );
    }

    /**
     * @param array $fields
     */
    public function updateSummaryFields(&$fields)
    {
        $fields = array_merge(
            $fields,
            array(
                'CMSPublishedState' => _t('VersionedDataobjects.STATE','State')
            )
        );
    }

    /**
     * @param array $fields
     */
    public function updateSearchableFields(&$fields)
    {
        unset($fields['CMSPublishedState']);
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

        return (bool)DB::query("SELECT \"ID\" FROM \"{$table}_Live\" WHERE \"ID\" = {$this->owner->ID}")->value();
    }

    /**
     * @return HTMLText
     */
    public function getCMSPublishedState()
    {
        $html = new HTMLText('PublishedState');

        if ($this->isPublished()) {
            if ($this->stagesDiffer('Stage', 'Live')) {
                $colour = '#1391DF';
                $text = _t('VersionedDataobjects.Modified','Modified');
            } else {
                $colour = '#18BA18';
                $text = _t('VersionedDataobjects.PUBLISHED','Published');
            }
        } else {
            $colour = '#C00';
            $text = _t('VersionedDataobjects.Draft','Draft');
        }

        $html->setValue(sprintf(
            '<span style="color: %s;">%s</span>',
            $colour,
            htmlentities($text)
        ));

        return $html;
    }

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('Version');
        $fields->removeByName('Versions');
    }

    public function onBeforeWrite()
    {
        $fieldsIgnoredByVersioning = array('Version');

        $changedFields = array_keys($this->owner->getChangedFields(true, 2));
        $oneChangedFields = array_keys($this->owner->getChangedFields(true, 1));

        if ($oneChangedFields && !array_diff($changedFields, $fieldsIgnoredByVersioning)) {
            // This will have the effect of preserving the versioning
            $this->migrateVersion($this->owner->Version);
        }
    }

    public function onAfterDelete() {
        parent::onBeforeDelete();

        if (Versioned::current_stage() == 'Stage') {
            VersionedReadingMode::setLiveReadingMode();
            $this->owner->delete();
            VersionedReadingMode::restoreOriginalReadingMode();
        }
    }

    /**
     * @param Place $fromStage
     * @param Place $toStage
     * @param bool|false $createNewVersion
     */
    public function publish($fromStage, $toStage, $createNewVersion = false)
    {
        parent::publish($fromStage, $toStage, $createNewVersion);
        $this->owner->extend('onAfterVersionedPublish', $fromStage, $toStage, $createNewVersion);
    }
    
	/**
	 * Improves interoperability with other components
	 * @return void
	 */
	public function doPublish() {
		$this->publish('Stage','Live');
	} 

}
