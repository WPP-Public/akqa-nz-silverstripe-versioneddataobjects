<?php

namespace Heyday\VersionedDataObjects;

use Controller;
use FieldList;
use Form;
use FormAction;
use GridFieldDetailForm;
use GridFieldDetailForm_ItemRequest;
use HTMLText;
use ManyManyList;
use PjaxResponseNegotiator;
use SS_HTTPResponse;
use ValidationException;
use ViewableData_Customised;

/**
 * Class VersionedDataObjectDetailsForm
 */
class VersionedDataObjectDetailsForm extends GridFieldDetailForm
{
}

/**
 * Class VersionedDataObjectDetailsForm_ItemRequest
 */
class VersionedDataObjectDetailsForm_ItemRequest extends GridFieldDetailForm_ItemRequest
{
    private static $allowed_actions = array(
        'edit',
        'view',
        'ItemEditForm'
    );

    /**
     * @return Form
     */
    public function ItemEditForm()
    {
        VersionedReadingMode::setStageReadingMode();

        $form = parent::ItemEditForm();
        /* @var $actions FieldList */
        if ($form instanceof Form) {
            $this->extend("updateItemEditForm", $form);
            
            $actions = $form->Actions();

            $actions->replaceField(
                'action_doSave',
                FormAction::create('save', _t('SiteTree.BUTTONSAVED', 'Saved'))
                    ->setAttribute('data-icon', 'accept')
                    ->setAttribute('data-icon-alternate', 'addpage')
                    ->setAttribute('data-text-alternate', _t('CMSMain.SAVEDRAFT', 'Save draft'))
                    ->setUseButtonTag(true)
            );

            $published = $this->record->isPublished();

            /* @var $publish FormAction */
            $publish = FormAction::create(
                'publish',
                $published ? _t('SiteTree.BUTTONPUBLISHED', 'Published') : _t('SiteTree.BUTTONSAVEPUBLISH', 'Save & publish')
            )
                ->setAttribute('data-icon', 'accept')
                ->setAttribute('data-icon-alternate', 'disk')
                ->setAttribute('data-text-alternate', _t('SiteTree.BUTTONSAVEPUBLISH', 'Save & publish'))
                ->setUseButtonTag(true);

            if ($this->record->stagesDiffer('Stage', 'Live') && $published) {
                $publish->addExtraClass('ss-ui-alternate');

                $actions->push(
                    FormAction::create(
                        'rollback',
                        _t(
                            'SiteTree.BUTTONCANCELDRAFT',
                            'Cancel draft changes'
                        )
                    )->setDescription(
                        _t(
                            'SiteTree.BUTTONCANCELDRAFTDESC',
                            'Delete your draft and revert to the currently published page'
                        )
                    )
                );
            }

            $actions->push($publish);

            if ($published) {
                /* @var $unpublish FormAction */
                $unpublish = FormAction::create('unpublish', _t('SiteTree.BUTTONUNPUBLISH', 'Unpublish'))
                    ->addExtraClass('ss-ui-action-destructive');

                $actions->push($unpublish);

                $actions->removeByName('action_doDelete');
            }
        }

        VersionedReadingMode::restoreOriginalReadingMode();

        return $form;
    }

    /**
     * @param $data
     * @param $form
     * @return HTMLText|SS_HTTPResponse|ViewableData_Customised
     */
    public function save($data, $form)
    {
        VersionedReadingMode::setStageReadingMode();

        $value = $this->doSave($data, $form);

        VersionedReadingMode::restoreOriginalReadingMode();

        return $value;
    }

    /**
     * @param $data
     * @param $form
     * @return bool|SS_HTTPResponse
     */
    public function doDelete($data, $form)
    {
        VersionedReadingMode::setStageReadingMode();

        $value = parent::doDelete($data, $form);

        VersionedReadingMode::restoreOriginalReadingMode();

        return $value;
    }

    /**
     * @param $data
     * @param $form
     * @return HTMLText|SS_HTTPResponse|ViewableData_Customised
     */
    public function publish($data, $form)
    {
        $new_record = $this->record->ID == 0;
        $controller = Controller::curr();
        $list = $this->gridField->getList();

        if ($list instanceof ManyManyList) {
            // Data is escaped in ManyManyList->add()
            $extraData = (isset($data['ManyMany'])) ? $data['ManyMany'] : null;
        } else {
            $extraData = null;
        }

        if (!$this->record->canEdit()) {
            return $controller->httpError(403);
        }

        if (isset($data['ClassName']) && $data['ClassName'] != $this->record->ClassName) {
            $newClassName = $data['ClassName'];
            // The records originally saved attribute was overwritten by $form->saveInto($record) before.
            // This is necessary for newClassInstance() to work as expected, and trigger change detection
            // on the ClassName attribute
            $this->record->setClassName($this->record->ClassName);
            // Replace $record with a new instance
            $this->record = $this->record->newClassInstance($newClassName);
        }

        try {
            $form->saveInto($this->record);
            $this->record->writeToStage('Stage');;
            $list->add($this->record, $extraData);
            $this->record->publish('Stage', 'Live');
        } catch (ValidationException $e) {
            $form->sessionMessage($e->getResult()->message(), 'bad');
            $responseNegotiator = new PjaxResponseNegotiator(array(
                'CurrentForm' => function () use (&$form) {
                    return $form->forTemplate();
                },
                'default' => function () use (&$controller) {
                    return $controller->redirectBack();
                }
            ));

            if ($controller->getRequest()->isAjax()) {
                $controller->getRequest()->addHeader('X-Pjax', 'CurrentForm');
            }

            return $responseNegotiator->respond($controller->getRequest());
        }

        // TODO Save this item into the given relationship

        $message = sprintf(
            'Published %s "%s"',
            $this->record->i18n_singular_name(),
            $this->record->Title
        );

        $form->sessionMessage($message, 'good');

        if ($new_record) {
            return Controller::curr()->redirect($this->Link());
        } elseif ($this->gridField->getList()->byId($this->record->ID)) {
            // Return new view, as we can't do a "virtual redirect" via the CMS Ajax
            // to the same URL (it assumes that its content is already current, and doesn't reload)
            return $this->edit(Controller::curr()->getRequest());
        } else {
            // Changes to the record properties might've excluded the record from
            // a filtered list, so return back to the main view if it can't be found
            $noActionURL = $controller->removeAction($data['url']);
            $controller->getRequest()->addHeader('X-Pjax', 'Content');

            return $controller->redirect($noActionURL, 302);
        }
    }

    /**
     * @return HTMLText|ViewableData_Customised
     */
    public function unPublish()
    {
        VersionedReadingMode::setLiveReadingMode();

        // This way our ID won't be unset
        $clone = clone $this->record;
        $clone->delete();

        VersionedReadingMode::restoreOriginalReadingMode();

        return $this->edit(Controller::curr()->getRequest());
    }

    /**
     * @param $data
     * @param $form
     * @return HTMLText|ViewableData_Customised
     */
    public function rollback($data, $form)
    {
        VersionedReadingMode::setStageReadingMode();

        if (!$this->record->canEdit()) {
            return Controller::curr()->httpError(403);
        }

        $this->record->publish('Live', 'Stage');
        $this->record = DataList::create($this->record->class)->byID($this->record->ID);

        VersionedReadingMode::restoreOriginalReadingMode();

        $message = _t(
            'CMSMain.ROLLEDBACKPUBv2',
            "Rolled back to published version."
        );

        $form->sessionMessage($message, 'good');

        return $this->edit(Controller::curr()->getRequest());
    }

    /////////////////////////////////////////////////////
    // Better Buttons integration
    ////////////////////////////////////////////////////

    /**
     * Override the doSaveAnQuit action from better buttons so that it uses the versioned way fo saving things.
     * @param $data
     * @param $form
     */
    public function doSaveAndQuit($data, $form)
    {
        $this->save($data, $form);
        $controller = $this->getToplevelController();
        $controller->getResponse()->addHeader("X-Pjax", "Content");
        $controller->redirect($this->getBackLink());
    }

}
