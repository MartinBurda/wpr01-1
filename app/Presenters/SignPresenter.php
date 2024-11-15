<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\DuplicateNameException;
use App\Model\UserFacade;
use App\Forms\FormFactory;
use Nette;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;


/**
 * Presenter for sign-in and sign-up actions.
 */
final class SignPresenter extends Nette\Application\UI\Presenter
{
	/**
	 * Stores the previous page hash to redirect back after successful login.
	 */
	#[Persistent]
	public string $backlink = '';


	// Dependency injection of form factory and user management facade
	public function __construct(
		private UserFacade $userFacade,
		private FormFactory $formFactory,
	) {
	}


	/**
	 * Create a sign-in form with fields for username and password.
	 * On successful submission, the user is redirected to the dashboard or back to the previous page.
	 */
	protected function createComponentSignInForm(): Form
	{
		$form = $this->formFactory->create();
		$form->addText('username', 'Username:')
			->setRequired('Please enter your username.');

		$form->addPassword('password', 'Password:')
			->setRequired('Please enter your password.');

		$form->addSubmit('send', 'Přihlásit se');

		// Handle form submission
		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			try {
				// Attempt to login user
				$this->getUser()->login($data->username, $data->password);
				$this->restoreRequest($this->backlink);
				$this->redirect('Home:default');
			} catch (Nette\Security\AuthenticationException) {
				$form->addError('The username or password you entered is incorrect.');
			}
		};

		return $form;
	}


	/**
	 * Create a sign-up form with fields for username, email, and password.
	 * On successful submission, the user is redirected to the dashboard.
	 */
protected function createComponentSignUpForm(): Form
{
    $form = $this->formFactory->create();
    $form->addText('username', 'Vyber si přezdívku:')
        ->setRequired('Vyber si přezdívku.');

	$form->addText('jmeno', 'Jméno')
		->setRequired('Vyplňte jméno.');
	
	$form->addText('prijmeni', 'Příjmení')
		->setRequired('Vyplňte Příjmení.');

    $form->addEmail('email', 'Email:')
        ->setRequired('Vyplňte e-mail.');

    $form->addPassword('password', 'Create a password:')
        ->setOption('description', sprintf('at least %d characters', $this->userFacade::PasswordMinLength))
        ->setRequired('Please create a password.')
        ->addRule($form::MinLength, null, $this->userFacade::PasswordMinLength);

    $form->addSubmit('send', 'Registrovat se');

    // Handle form submission
    $form->onSuccess[] = function (Form $form, \stdClass $data): void {
        try {
            // Attempt to register a new user
            $this->userFacade->add($data->username, $data->jmeno, $data->prijmeni, $data->email, $data->password);
            
            // Check if the user is an admin and redirect accordingly
            if ($this->getSession('admin')->backlink === 'admin') {
				$this->redirect('Dashboard:default');
			} else {
				$this->redirect('Sign:in');
			}
        } catch (DuplicateNameException) {
            // Handle the case where the username is already taken
            $form['username']->addError('Username is already taken.');
        }
    };

    return $form;
}

	/**
	 * Logs out the currently authenticated user.
	 */
	public function actionOut(): void
	{
		$this->getUser()->logout();
		$this->redirect('Home:default');
	}

}
