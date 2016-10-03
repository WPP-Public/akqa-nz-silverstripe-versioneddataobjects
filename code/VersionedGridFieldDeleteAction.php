<?php

/**
 * Class VersionedGridFieldDeleteAction
 *
 * Extend the delete action with a versioned delete
 */
class VersionedGridFieldDeleteAction extends GridFieldDeleteAction
{

    /**
     * Delete the object form both live and stage
     *
     * @param GridField $gridField
     * @param string $actionName
     * @param mixed $arguments
     * @param array $data
     * @throws ValidationException
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($item = $gridField->getList()->byID($arguments['RecordID'])) {
            if (!$item->canDelete()) {
                throw new ValidationException(
                    _t('GridFieldAction_Delete.DeletePermissionsFailure', "No delete permissions"), 0);
            }
            $item->deleteFromStage('Live');
            $item->delete();
        }
    }
}
