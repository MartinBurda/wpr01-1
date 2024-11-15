<?php

namespace App\Presenters;

use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Security\Passwords;
use App\Model\UserFacade;

class ChangePasswordPresenter extends Presenter
{
    private $passwords;
    private $userFacade;

    public function __construct(UserFacade $userFacade, \Nette\Security\Passwords $passwords)
    {
        $this->userFacade = $userFacade;
        $this->passwords = $passwords;
    }

    protected function createComponentChangePasswordForm(): Form
{
    $form = new Form;

    $form->addPassword('newPassword', 'Nové heslo:')
        ->setRequired('Prosím, zadejte nové heslo.')
        ->setOption('description', sprintf('minimálně %d znaků', $this->userFacade::PasswordMinLength))
        ->addRule($form::MIN_LENGTH, null, $this->userFacade::PasswordMinLength);

    $form->addSubmit('send', 'Změnit heslo');

    $form->onSuccess[] = function (Form $form, \stdClass $values): void {
        $userId = $this->getParameter('userId');
        try {
            $this->userFacade->changePassword($userId, $values->newPassword);
            $this->flashMessage('Heslo bylo úspěšně změněno.', 'success');
            $this->redirect('Dashboard:default');
        } catch (\Exception $e) {
            $form->addError('Nepodařilo se změnit heslo.');
            throw $e;
        }
    };

    return $form;
}
    public function actionDefault(int $userId): void
    {
        $user = $this->userFacade->getAllUsers($userId);
        if (!$user) {
            $this->error('Uživatel nebyl nalezen.');
        }
    }
}