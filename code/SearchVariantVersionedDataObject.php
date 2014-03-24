<?php

class SearchVariantVersionedDataObject extends SearchVariantVersioned
{
    public function appliesToEnvironment()
    {
        return class_exists('VersionedDataObject');
    }

    public function appliesTo($class, $includeSubclasses)
    {
        return SearchIntrospection::has_extension($class, 'VersionedDataObject', false);
    }
}