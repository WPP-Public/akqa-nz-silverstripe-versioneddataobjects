<?php

/**
 * Class VersionedReadingMode
 */
class VersionedReadingMode
{
    /**
     * @var String
     */
    private static $originalReadingMode;

    /**
     * Set the reading mode to 'Stage' so that all data objects are displayed for lists whether or not they have
     * been published
     */
    public static function setStageReadingMode()
    {
        self::$originalReadingMode = Versioned::current_stage();
        Versioned::reading_stage('Stage');
    }

    /**
     * Set the reading mode to 'Live'
     */
    public static function setLiveReadingMode()
    {
        self::$originalReadingMode = Versioned::current_stage();
        Versioned::reading_stage('Live');
    }

    /**
     * Restore the reading mode to what's been set originally in the CMS
     */
    public static function restoreOriginalReadingMode()
    {
        if (isset(self::$originalReadingMode) && in_array(self::$originalReadingMode, ['Stage', 'Live'])) {
            Versioned::reading_stage(self::$originalReadingMode);
        }
    }
}
