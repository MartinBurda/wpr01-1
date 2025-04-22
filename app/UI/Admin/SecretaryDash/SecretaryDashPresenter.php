<?php

namespace App\UI\Admin\SecretaryDash;

use Nette;
use Nette\Application\UI\Form;
use App\Model\RepairFacade;
use App\Utils\ImageUploader;

class SecretaryDashPresenter extends Nette\Application\UI\Presenter
{
    private $repairFacade;

    public function __construct(\App\Model\RepairFacade $repairFacade)
    {
        parent::__construct();
        $this->repairFacade = $repairFacade;
    }

    public function renderDefault(?int $repairId = null, ?string $search = null, ?int $page = 1): void
    {
        $itemsPerPage = 5; // Number of repairs per page
        $repairs = $this->repairFacade->getRepairsBySearch($search, $page, $itemsPerPage);
        $totalRepairs = $this->repairFacade->countRepairs($search);
        $totalPages = max(1, ceil($totalRepairs / $itemsPerPage));
    
        // Ensure page is within valid range
        if ($page < 1 || $page > $totalPages) {
            $this->redirect('this', ['repairId' => $repairId, 'search' => $search, 'page' => 1]);
        }
    
        $this->template->repairs = $repairs;
        $this->template->search = $search;
        $this->template->currentPage = $page;
        $this->template->totalPages = $totalPages;
    
        if ($repairId) {
            $selectedRepair = $this->repairFacade->getRepairById($repairId);
            if (!$selectedRepair) {
                $this->flashMessage('Vybraná oprava nebyla nalezena', 'error');
                $this->redirect('this', ['repairId' => null, 'search' => $search, 'page' => $page]);
            }
            $this->template->selectedRepair = $selectedRepair;
            $this->template->photos = $this->repairFacade->getPhotosByRepairId($repairId);
        } else {
            $this->template->selectedRepair = null;
            $this->template->photos = [];
        }
    }

    public function renderAccept(string $repairCode): void
    {
        $repair = $this->repairFacade->getRepairByCode($repairCode);
        if (!$repair) {
            $this->flashMessage('Oprava s tímto kódem nebyla nalezena', 'error');
            $this->redirect('default');
        }
        $this->template->repair = $repair;
    }

    public function createComponentVerifyRepairCodeForm(): Form
    {
        $form = new Form;
        $form->addText('repair_code', 'Kód opravy')
            ->setRequired('Zadejte kód opravy.')
            ->addRule($form::PATTERN, 'Kód musí být 5místný a obsahovat pouze velká písmena a číslice (např. CS5RN).', '^[A-Z0-9]{5}$');
        $form->addSubmit('submit', 'Ověřit kód');
        $form->onSuccess[] = [$this, 'verifyRepairCodeFormSucceeded'];
        return $form;
    }

    public function verifyRepairCodeFormSucceeded(Form $form, \stdClass $values): void
    {
        $repair = $this->repairFacade->getRepairByCode($values->repair_code);
        if (!$repair) {
            $form->addError('Oprava s tímto kódem nebyla nalezena.');
            return;
        }
        $this->redirect('accept', ['repairCode' => $values->repair_code]);
    }

    public function createComponentAcceptRepairForm(): Form
    {
        $form = new Form;
        $form->addHidden('repair_code');
        $form->addMultiUpload('photos', 'Fotky zařízení')
            ->addRule($form::MAX_FILE_SIZE, 'Maximální velikost souboru je 5 MB.', 5 * 1024 * 1024)
            ->addRule($form::MAX_LENGTH, 'Můžete nahrát maximálně 10 fotografií.', 10);
        $form->addTextArea('description', 'Popis (volitelné)')
            ->setRequired(false);
        $form->addSubmit('submit', 'Přijmout opravu');
        $form->onSuccess[] = [$this, 'acceptRepairFormSucceeded'];
        return $form;
    }

    public function acceptRepairFormSucceeded(Form $form, \stdClass $values): void
    {
        $repair = $this->repairFacade->getRepairByCode($values->repair_code);
        if (!$repair || $repair->status !== 'Pending') {
            $form->addError('Neplatný kód nebo oprava již přijata.');
            return;
        }

        $photos = $values->photos;
        if (!is_array($photos)) {
            $photos = $photos ? [$photos] : [];
        }

        $uploadDir = 'Uploads/repairs/' . $repair->id;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $imagePaths = [];
        $failedImages = [];
        foreach ($photos as $index => $photo) {
            if ($photo->isOk() && $photo->isImage()) {
                $path = ImageUploader::uploadImage($photo, $uploadDir);
                if ($path) {
                    $imagePaths[] = $path;
                } else {
                    $failedImages[] = $photo->getName();
                }
            } else {
                $failedImages[] = $photo->getName();
            }
        }

        if (!empty($failedImages)) {
            foreach ($imagePaths as $path) {
                $filePath = rtrim($uploadDir, '/') . '/' . basename($path);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            $form->addError('Některé fotografie se nepodařilo zpracovat: ' . implode(', ', $failedImages));
            return;
        }

        $this->repairFacade->updateRepair($repair->id, [
            'status' => 'Accepted',
            'description' => $values->description ?? null,
        ]);

        $savedPhotos = 0;
        foreach ($imagePaths as $path) {
            $this->repairFacade->addPhoto($repair->id, $path);
            $savedPhotos++;
        }

        $this->flashMessage("Uloženo $savedPhotos fotografií", 'info');
        $this->flashMessage('Oprava byla přijata', 'success');
        $this->redirect('default', ['repairId' => $repair->id]);
    }

    public function createComponentEditRepairForm(): Form
    {
        $form = new Form;
        $form->addHidden('id');
        $form->addHidden('repair_code'); // Added to maintain context for accept page
        $form->addText('device', 'Zařízení')
            ->setRequired('Zadejte zařízení.');
        $form->addTextArea('issue', 'Problém')
            ->setRequired('Zadejte popis problému.');
        $form->addText('name', 'Jméno')
            ->setRequired('Zadejte jméno zákazníka.');
        $form->addText('surname', 'Příjmení')
            ->setRequired('Zadejte příjmení zákazníka.');
        $form->addEmail('email', 'Email')
            ->setRequired('Zadejte email zákazníka.');
        $form->addText('phone', 'Telefon')
            ->setRequired('Zadejte telefonní číslo zákazníka.')
            ->addRule($form::PATTERN, 'Telefonní číslo musí být ve správném formátu.', '^\+?[1-9]\d{1,14}$');
        $form->addSubmit('submit', 'Uložit změny');
        $form->onSuccess[] = [$this, 'editRepairFormSucceeded'];
        return $form;
    }

    public function editRepairFormSucceeded(Form $form, \stdClass $values): void
    {
        $repair = $this->repairFacade->getRepairById($values->id);
        if (!$repair) {
            $form->addError('Oprava nebyla nalezena.');
            return;
        }

        $this->repairFacade->updateRepair($values->id, [
            'device' => $values->device,
            'issue' => $values->issue,
            'name' => $values->name,
            'surname' => $values->surname,
            'email' => $values->email,
            'phone' => $values->phone,
        ]);

        $this->flashMessage('Oprava byla úspěšně aktualizována.', 'success');
        // Redirect back to accept page if coming from there, otherwise to dashboard
        if ($values->repair_code) {
            $this->redirect('accept', ['repairCode' => $values->repair_code]);
        } else {
            $this->redirect('this', ['repairId' => $repair->id]);
        }
    }
}