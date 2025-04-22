<?php

namespace App\UI\Front\Repair;

use Nette;
use App\Model\RepairFacade;
use Nette\Application\UI\Form;
use App\Model\UserFacade;
use App\MailSender\MailSender;
use stdClass;

final class RepairPresenter extends Nette\Application\UI\Presenter
{
    private RepairFacade $repairFacade;
    private UserFacade $userFacade;
    private MailSender $mailSender;

    public function __construct(
        RepairFacade $repairFacade,
        UserFacade $userFacade,
        MailSender $mailSender
    ) {
        parent::__construct();
        $this->repairFacade = $repairFacade;
        $this->userFacade = $userFacade;
        $this->mailSender = $mailSender;
    }

    protected function createComponentForm(): Form
    {
        $form = new Form;

        $type = $form->addSelect('type', 'Typ zařízení:', $this->repairFacade->getTypes())
            ->setPrompt('----');

        $manufacturer = $form->addSelect('manufacturer', 'Výrobce:')
            ->setHtmlAttribute('data-depends', $type->getHtmlName())
            ->setHtmlAttribute('data-url', $this->link('Endpoint:manufacturers', ['typeId' => '#']));

        $model = $form->addSelect('model', 'Model:')
            ->setHtmlAttribute('data-depends', $manufacturer->getHtmlName())
            ->setHtmlAttribute('data-url', $this->link('Endpoint:models', ['manufacturerId' => '#', 'typeId' => '#']));

        $fault = $form->addSelect('fault', 'Závada:')
            ->setHtmlAttribute('data-url', $this->link('Endpoint:faults', ['faultId' => '#']));

        $form->addSubmit('submit', 'Odeslat');

        $form->getElementPrototype()->{'data-price-url'} = $this->link('Endpoint:getRepairPrice');

        $form->onAnchor[] = function () use ($model, $manufacturer, $type, $fault) {
            $model->setItems(
                $manufacturer->getValue() && $type->getValue()
                    ? $this->repairFacade->getModelsByManufacturer($manufacturer->getValue())
                    : []
            );
            $fault->setItems($this->repairFacade->getFaults());
        };

        $form->onSuccess[] = [$this, 'processForm'];

        return $form;
    }

    public function processForm(Form $form, stdClass $values): void
    {
        $values = $form->getHttpData();

        if (!$this->getUser()->isLoggedIn()) {
            $this->getSession()->getSection('repairForm')->values = (array) $values;
            $this->flashMessage('Přihlaste se prosím', 'warning');
            $this->redirect('Sign:in', ['backlink' => 'repair']);
        }

        $user = $this->userFacade->getUserById($this->getUser()->getId());
        if (!$user) {
            $this->flashMessage('Uživatel nenalezen', 'error');
            $this->redirect('Sign:in');
        }

        if (is_array($values)) {
            $values = (object) $values;
        }

        $manufacturerId = isset($values->manufacturer) ? (int) $values->manufacturer : null;
        $modelId = isset($values->model) ? (int) $values->model : null;
        $faultId = isset($values->fault) ? (int) $values->fault : null;
        $customerId = $user->id;

        if (!$manufacturerId || !$modelId || !$faultId) {
            $this->flashMessage('Chybné údaje', 'error');
            $this->redirect('Repair:default');
        }

        $repairId = $this->repairFacade->addRepair(
            $manufacturerId,
            $modelId,
            $faultId,
            $customerId,
            $user->name,
            $user->surname,
            $user->email,
            $user->phone
        );

        if ($repairId) {
            $repair = $this->repairFacade->getRepairById($repairId);

            // Get select options to map IDs to names
            $types = $this->repairFacade->getTypes();
            $manufacturers = $this->repairFacade->getManufacturersByType($values->type);
            $models = $this->repairFacade->getModelsByManufacturer($manufacturerId);
            $faults = $this->repairFacade->getFaults();

            // Send repair confirmation email
            try {
                $this->mailSender->sendEmail(
                    email: $user->email,
                    template: 'repair-confirmation',
                    subject: 'Potvrzení rezervace opravy',
                    params: [
                        'name' => $user->name,
                        'surname' => $user->surname,
                        'repair_code' => $repair->repair_code,
                        'type' => $types[$values->type] ?? 'Není známo',
                        'manufacturer' => $manufacturers[$manufacturerId] ?? 'Není známo',
                        'model' => $models[$modelId] ?? 'Není známo',
                        'fault' => $faults[$faultId] ?? 'Není známo',
                        'email' => $user->email,
                        'phone' => $user->phone,
                    ]
                );
            } catch (\Exception $e) {
                $this->flashMessage('Rezervace úspěšná, ale e-mail se nepodařilo odeslat', 'warning');
                bdump($e->getMessage());
            }

            $this->flashMessage(
                "Oprava zadána, podací kód: {$repair->repair_code}",
                'success'
            );
            $this->redirect('Dashboard:default');
        } else {
            $this->flashMessage('Chyba při odeslání', 'error');
            $this->redirect('Repair:default');
        }
    }

    public function actionResumeForm(): void
    {
        $session = $this->getSession()->getSection('repairForm');

        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Přihlaste se nebo se registrujte', 'warning');
            $this->redirect('Sign:in', ['backlink' => 'repair']);
        }

        if (!isset($session->values) || empty($session->values)) {
            $this->flashMessage('Žádná uložená žádost o opravu', 'error');
            $this->redirect('this');
        }

        $user = $this->userFacade->getUserById($this->getUser()->getId());
        if (!$user) {
            $this->flashMessage('Uživatel nenalezen', 'error');
            $this->getUser()->logout();
            $this->redirect('Sign:in');
        }

        $values = (object) $session->values;

        $manufacturerId = isset($values->manufacturer) ? (int) $values->manufacturer : null;
        $modelId = isset($values->model) ? (int) $values->model : null;
        $faultId = isset($values->fault) ? (int) $values->fault : null;
        $customerId = $user->id;

        if (!$manufacturerId || !$modelId || !$faultId) {
            $this->flashMessage('Neplatné údaje v žádosti', 'error');
            $session->remove();
            $this->redirect('this');
        }

        $repairId = $this->repairFacade->addRepair(
            $manufacturerId,
            $modelId,
            $faultId,
            $customerId,
            $user->name,
            $user->surname,
            $user->email,
            $user->phone
        );

        if ($repairId) {
            $repair = $this->repairFacade->getRepairById($repairId);

            // Get select options to map IDs to names
            $types = $this->repairFacade->getTypes();
            $manufacturers = $this->repairFacade->getManufacturersByType($values->type);
            $models = $this->repairFacade->getModelsByManufacturer($manufacturerId);
            $faults = $this->repairFacade->getFaults();

            // Send repair confirmation email
            try {
                $this->mailSender->sendEmail(
                    email: $user->email,
                    template: 'repair-confirmation',
                    subject: 'Potvrzení rezervace opravy',
                    params: [
                        'name' => $user->name,
                        'surname' => $user->surname,
                        'repair_code' => $repair->repair_code,
                        'type' => $types[$values->type] ?? 'Není známo',
                        'manufacturer' => $manufacturers[$manufacturerId] ?? 'Není známo',
                        'model' => $models[$modelId] ?? 'Není známo',
                        'fault' => $faults[$faultId] ?? 'Není známo',
                        'email' => $user->email,
                        'phone' => $user->phone,
                    ]
                );
            } catch (\Exception $e) {
                $this->flashMessage('Rezervace úspěšná, ale e-mail se nepodařilo odeslat', 'warning');
                bdump($e->getMessage());
            }

            $this->flashMessage(
                "Oprava zadána, podací kód: {$repair->repair_code}",
                'success'
            );
            $session->remove();
            $this->redirect('Dashboard:default');
        } else {
            $this->flashMessage('Chyba při zpracování žádosti', 'error');
            $session->remove();
            $this->redirect('this');
        }
    }
}