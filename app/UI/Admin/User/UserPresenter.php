<?php

namespace App\UI\Admin\User;

use Nette;
use App\Model\UserFacade;
use App\UI\Accessory\FormFactory;

class UserPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private UserFacade $userFacade,
        private FormFactory $formFactory
    ) {}

    public function renderDefault()
    {
        $this->template->userData = $this->userFacade->getAllUsers();
    }

    public function renderDetail(int $id)
    {
        $this->template->userData = $this->userFacade->getUserById($id);
    }

    public function handleDelete(int $userId): void
    {
        // Získáme uživatele
        $user = $this->userFacade->getUserById($userId);
    
        if ($user) {
            // Kontrola, zda uživatel má obrázek a není to výchozí obrázek
            if ($user->image && $user->image !== 'uploads/default/user.png' && file_exists($user->image)) {
                unlink($user->image); // Smažeme uživatelský obrázek
            }
    
            // Smažeme uživatele z databáze
            $this->userFacade->deleteUser($userId);
    
            // Přesměrování na výchozí stránku
            $this->redirect('User:default');
        } else {
            // Pokud uživatel neexistuje, můžeme přidat chybovou zprávu
            $this->flashMessage('Uživatel nebyl nalezen', 'danger');
            $this->redirect('User:default');
        }
    }
    

    public function renderEdit(int $id): void
    {
        $user = $this->userFacade->getUserById($id);
        
        if (!$user) {
            throw new Nette\Application\BadRequestException('Uživatel nenalezen');
        }

        $this->template->userData = $user;

        // Prepopulate form fields with the user's current data
        if ($this->getComponent('editForm', false)) {
            $this['editForm']->setDefaults([
                'username' => $user->username,
                'name' => $user->name,
                'surname' => $user->surname,
                'email' => $user->email,
                'role' => $user->role,
                'image' => $user->image,
            ]);
        }
    }

    protected function createComponentEditForm(): Nette\Application\UI\Form
    {
        $form = $this->formFactory->create();
        $form->getElementPrototype()->addClass('form-horizontal');

        // Add fields for user profile
        $form->addText('name', 'Křestní jméno:')
            ->setHtmlAttribute('class', 'form-control')
            ->setRequired('Zadejte křestní jméno.');

        $form->addText('surname', 'Příjmení:')
            ->setHtmlAttribute('class', 'form-control')
            ->setRequired('Zadejte příjmení.');

        $form->addText('username', 'Uživatelské jméno:')
            ->setHtmlAttribute('class', 'form-control')
            ->setRequired('Zadejte uživatelské jméno.');

        $form->addUpload('image', 'Uživatelský obrázek:')
            ->setHtmlAttribute('class', 'form-control');

        $form->addEmail('email', 'Email:')
            ->setHtmlAttribute('class', 'form-control')
            ->setRequired('Zadejte emailovou adresu.');

        // Add role selection
        $form->addSelect('role', 'Role:', [
            'admin' => 'Admin',
            'user' => 'Uživatel',
        ])
        ->setRequired('Vyberte roli uživatele.');

        // Add optional password fields
        $form->addPassword('newPassword', 'Nové heslo:')
            ->setOption('description', sprintf('minimálně %d znaků', $this->userFacade::PasswordMinLength))
            ->addCondition($form::FILLED)
            ->addRule($form::MIN_LENGTH, null, $this->userFacade::PasswordMinLength);

        $form->addPassword('confirmPassword', 'Potvrdit heslo:')
            ->addConditionOn($form['newPassword'], $form::FILLED)
            ->setRequired('Prosím, potvrďte heslo.')
            ->addRule($form::EQUAL, 'Hesla se neshodují.', $form['newPassword']);

        // Add submit button
        $form->addSubmit('submit', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-primary');

        // Set the form success callback
        $form->onSuccess[] = [$this, 'editFormSucceeded'];

        return $form;
    }

    public function editFormSucceeded(Nette\Application\UI\Form $form, array $values): void
    {
        $userId = $this->getParameter('id');
        $user = $this->userFacade->getUserById($userId);
        
        // Validate username presence
        if (empty($values['username'])) {
            throw new \RuntimeException('Username is missing.');
        }

        // Process image upload
        /** @var Nette\Http\FileUpload $image */
        $image = $values['image'];

        if ($image->isOk() && $image->isImage() && in_array($image->getContentType(), ['image/jpeg', 'image/png', 'image/gif'])) {
            $imageName = uniqid() . '_' . $image->getSanitizedName();
            $filePath = 'uploads/users/' . $imageName;
            $image->move($filePath);
            $values['image'] = $filePath;
        } else {
            // Keep existing image if no new upload
            $values['image'] = $user->image;
        }

        try {
            // Call `editUser` method in UserFacade to update user data
            if (!empty($values['newPassword'])) {
                $values['password'] = $this->userFacade->hashPassword($values['newPassword']);
            }
            $this->userFacade->editUser($userId, $values);
        } catch (\RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'danger');
            $this->redirect('this'); // Reload the page with error message
            return;
        }

        // Display success message
        $this->flashMessage('Uživatel byl aktualizován', 'success');
        $this->redirect(':Front:Home:default'); // Redirect to user list
    }

    public function handleDeleteImage(int $userId): void
    {
        // Získat data uživatele
        $user = $this->userFacade->getUserById($userId);
    
        if ($user && $user->image && file_exists($user->image) && $user->image != 'uploads/default/user.png') {
            unlink($user->image); // Odstraní obrázek ze serveru
    
            // Aktualizuje databázi s novým výchozím obrázkem
            $updatedData = [
                'image' => 'uploads/default/user.png',
                'username' => $user->username, // Přidá stávající username
            ];
    
            $this->userFacade->editUser($userId, $updatedData);
    
            // Aktualizace identity v session po změně obrázku
            $identity = $this->getUser()->getIdentity(); // Získá aktuální identitu
    
            // Vytvoř novou instanci SimpleIdentity s aktualizovaným obrázkem
            $updatedIdentity = new Nette\Security\SimpleIdentity(
                $identity->getId(),
                $identity->getRoles(),
                array_merge($identity->getData(), ['image' => 'uploads/default/user.png'])
            );
    
            // Přihlásí uživatele s novou identitou
            $this->getUser()->login($updatedIdentity);
    
            $this->flashMessage('Obrázek byl úspěšně smazán)', 'success');
        } else {
            $this->flashMessage('Obrázek nebyl nalezen nebo již byl smazán', 'error');
        }
    
        // Přesměrování zpět na aktuální stránku
        $this->redirect('this');
    }
    

}
