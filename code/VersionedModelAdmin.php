<?php

/**
 * Class VersionedModelAdmin
 */
class VersionedModelAdmin extends ModelAdmin
{
    /**
     * Customise the edit form so that It uses the VersionedDataObjectDetailsForm as well as make
     * sure that the reading stage is 'Stage'. 
     * @param null $id
     * @param null $fields
     * @return mixed
     */
    public function getEditForm($id = null, $fields = null) {

        $origStage = Versioned::current_stage();
        Versioned::reading_stage('Stage');

        $list = $this->getList();
        $exportButton = new GridFieldExportButton('buttons-before-left');
        $exportButton->setExportColumns($this->getExportFields());
        $listField = GridField::create(
            $this->sanitiseClassName($this->modelClass),
            false,
            $list,
            $fieldConfig = GridFieldConfig_RecordEditor::create($this->stat('page_length'))
                ->addComponent($exportButton)
                ->removeComponentsByType('GridFieldFilterHeader')
                ->removeComponentsByType('GridFieldDeleteAction')
                ->addComponents(new GridFieldPrintButton('buttons-before-left'))
                ->removeComponentsByType('GridFieldDetailForm')
                ->addComponent(new VersionedDataObjectDetailsForm())
        );

        // Validation
        if(singleton($this->modelClass)->hasMethod('getCMSValidator')) {
            $detailValidator = singleton($this->modelClass)->getCMSValidator();
            $listField->getConfig()->getComponentByType('GridFieldDetailForm')->setValidator($detailValidator);
        }

        $form = CMSForm::create(
            $this,
            'EditForm',
            new FieldList($listField),
            new FieldList()
        )->setHTMLID('Form_EditForm');
        $form->setResponseNegotiator($this->getResponseNegotiator());
        $form->addExtraClass('cms-edit-form cms-panel-padded center');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $editFormAction = Controller::join_links($this->Link($this->sanitiseClassName($this->modelClass)), 'EditForm');
        $form->setFormAction($editFormAction);
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');

        $this->extend('updateEditForm', $form);

        Versioned::reading_stage($origStage);

        return $form;
    }
}