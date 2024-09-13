<?php

declare(strict_types=1);

namespace App\Forms;

use Nette;
use Nette\Application\UI\Form;
use Nette\Security\User;


/**
 * Factory for creating sign-in forms with authentication logic.
 */
final class SignInFormFactory
{
	// Dependency injection of form factory and current user session
	public function __construct(
		private FormFactory $factory,
		private User $user,
	) {
	}


	/**
	 * Create a sign-in form with fields for username, password, and a "remember me" option.
	 * Contains logic to handle successful form submissions.
	 */
	public function create(callable $onSuccess): Form
	{
		$form = $this->factory->create();
		$form->addText('username', 'Přezdívka:')
			->setRequired('zadej svoji přezdívku.');

		$form->addPassword('password', 'Heslo:')
			->setRequired('Zadej svoje heslo.');

		$form->addSubmit('send', 'Přihlásit se');

		// Handle form submission
		$form->onSuccess[] = function (Form $form, \stdClass $data) use ($onSuccess): void {
			try {
				// Attempt to login user
				$this->user->setExpiration($data->remember ? '14 days' : '20 minutes');
				$this->user->login($data->username, $data->password);
			} catch (Nette\Security\AuthenticationException $e) {
				$form->addError('The username or password you entered is incorrect.');
				return;
			}
			$onSuccess();
		};

		return $form;
	}
}