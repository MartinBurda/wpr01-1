<?php

namespace App\UI\Admin\Add;

use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;

class AddPresenter extends Presenter
{
    /** @var \App\Model\RepairFacade @inject */
    public $repairFacade;

    public function renderType(): void
    {
        $this->template->types = $this->repairFacade->getTypes();
    }

    public function renderModel(): void
    {
        // Pass device types and manufacturers to the template
        $this->template->types = $this->repairFacade->getTypes();
        $this->template->manufacturers = $this->repairFacade->getManufacturersByType(0); // Default manufacturers
    }

    protected function createComponentAddTypeForm(): Form
    {
        $form = new Form();
        $form->addText('name', 'Název typu:')
            ->setRequired('Zadejte název typu zařízení.');

        $form->addSubmit('submit', 'Přidat typ');

        $form->onSuccess[] = [$this, 'addTypeFormSucceeded'];
        return $form;
    }

    public function addTypeFormSucceeded(Form $form, \stdClass $values): void
    {
        $success = $this->repairFacade->addDeviceType($values->name);
        if ($success) {
            $this->flashMessage('Typ zařízení byl úspěšně přidán', 'success');
        } else {
            $this->flashMessage('Přidání typu zařízení selhalo', 'error');
        }
        $this->redirect('this');
    }

    protected function createComponentAddModelForm(): Form
    {
        $form = new Form();
    
        // Type Select Box
        $form->addSelect('type', 'Device Type:', $this->repairFacade->getTypes())
            ->setPrompt('Select a device type')
            ->setRequired('Please select a device type.');
    
        // Manufacturer ID Select Box
        $form->addSelect('manufacturer_id', 'Manufacturer:', $this->repairFacade->getManufacturers())
            ->setPrompt('Select a manufacturer')
            ->setRequired('Please select a manufacturer.');
    
        // Model Name Text Input
        $form->addText('model', 'Model Name:')
            ->setRequired('Please enter the model name.');
    
        // Optional Release Year
        $form->addText('release_year', 'Rok vydání:')
            ->setHtmlType('number')
            ->setDefaultValue((int)date('Y')) // Automatically sets the current year
            ->addCondition(Form::FILLED)
                ->addRule($form::INTEGER, 'Rok vydání musí být číslo.')
                ->addRule($form::RANGE, 'Rok vydání musí být mezi 1900 a 2100.', [1900, 2100]);
    
        // Submit Button
        $form->addSubmit('submit', 'Add Device');
    
        // Success Callback
        $form->onSuccess[] = [$this, 'addModelFormSucceeded'];
    
        return $form;
    }
    
    public function addModelFormSucceeded(Form $form, \stdClass $values): void
    {
        bdump($values);
        // Use the manufacturer_id directly from the form values
        $manufacturerId = $values->manufacturer_id;
    
        $success = $this->repairFacade->addModel(
            $values->model,
            $manufacturerId,
            $values->type,
            $values->release_year
        );
    
        if ($success) {
            $this->flashMessage('Model zařízení byl úspěšně přidán', 'success');
        } else {
            $this->flashMessage('Přidání modelu selhalo', 'error');
        }
    
        $this->redirect('this');
    }

    protected function createComponentAddManufacturerForm(): Form
    {
        $form = new Form();
        $form->addText('name', 'Název výrobce:')
            ->setRequired('Zadejte název výrobce');

        $form->addSubmit('submit', 'Přidat výrobce');

        $form->onSuccess[] = [$this, 'addManufacturerFormSucceeded'];
        return $form;
    }

    public function addManufacturerFormSucceeded(Form $form, \stdClass $values): void
    {
        $success = $this->repairFacade->addManufacturer($values->name);
        if ($success) {
            $this->flashMessage('Výrobce byl úspěšně přidán', 'success');
        } else {
            $this->flashMessage('Přidání výrobce selhalo', 'error');
        }
        $this->redirect('this');
    }

}


