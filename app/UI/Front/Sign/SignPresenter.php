<?php

declare(strict_types=1);

namespace App\UI\Front\Sign;

use App\Model\DuplicateNameException;
use App\Model\UserFacade;
use App\UI\Accessory\FormFactory;
use Nette;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use App\MailSender\MailSender;

final class SignPresenter extends Nette\Application\UI\Presenter
{
    #[Persistent]
    public string $backlink = '';

    public function __construct(
        private UserFacade $userFacade,
        private FormFactory $formFactory,
        private MailSender $mailSender,
    ) {
    }

    protected function createComponentSignInForm(): Form
    {
        $form = $this->formFactory->create();
        $form->addText('username', 'Username:')
            ->setRequired('Please enter your username.');

        $form->addPassword('password', 'Password:')
            ->setRequired('Please enter your password.');

        $form->addSubmit('send', 'Přihlásit se');

        $form->onSuccess[] = function (Form $form, \stdClass $data): void {
            try {
                $this->getUser()->login($data->username, $data->password);

                $role = $this->getUser()->getIdentity()->role;
                $session = $this->getSession()->getSection('repairForm');

                $this->flashMessage('Úspěšně jste se přihlásili', 'success');

                if ($session->values) {
                    $this->redirect('Repair:resumeForm');
                } else {
                    switch ($role) {
                        case 'admin':
                            $this->redirect(':Admin:AdminDashboard:default');
                            break;
                        case 'repair':
                            $this->redirect(':Admin:TechnicianDashboard:default');
                            break;
                        case 'secretary':
                            $this->redirect(':Admin:SecretaryDash:default');
                            break;
                        case 'user':
                        default:
                            $this->redirect('Dashboard:default');
                            break;
                    }
                }
            } catch (Nette\Security\AuthenticationException) {
                $this->flashMessage('Špatné uživatelské jméno nebo heslo', 'danger');
                $form->addError('Uživatelské jméno nebo heslo je nesprávné');
            }
        };

        return $form;
    }

    protected function createComponentSignUpForm(): Form
    {
        $form = $this->formFactory->create();

        $form->addText('username', 'Uživatelské jméno:')
            ->setRequired('Prosím, vyberte si uživatelské jméno');

        $form->addEmail('email', 'E-mail:')
            ->setRequired('Prosím, zadejte váš e-mail');

        $form->addText('name', 'Jméno:')
            ->setRequired('Prosím, zadejte vaše jméno');

        $form->addText('surname', 'Příjmení:')
            ->setRequired('Prosím, zadejte vaše příjmení');

        $form->addText('phone', 'Telefonní číslo:')
            ->setRequired('Prosím, zadejte vaše telefonní číslo.')
            ->addRule($form::Pattern, 'Prosím, zadejte platné telefonní číslo (pouze číslice).', '^[0-9]+$');

        $form->addSelect('birth_day', 'Den:', array_combine(range(1, 31), range(1, 31)))
            ->setRequired('Prosím, vyberte den narození.');

        $form->addSelect('birth_month', 'Měsíc:', [
            '1' => 'Leden', '2' => 'Únor', '3' => 'Březen', '4' => 'Duben',
            '5' => 'Květen', '6' => 'Červen', '7' => 'Červenec', '8' => 'Srpen',
            '9' => 'Září', '10' => 'Říjen', '11' => 'Listopad', '12' => 'Prosinec'
        ])->setRequired('Prosím, vyberte měsíc narození.');

        $years = range(date('Y') - 100, date('Y'));
        $form->addSelect('birth_year', 'Rok:', array_combine($years, $years))
            ->setRequired('Prosím, vyberte rok narození.');

        $form->addText('address', 'Adresa:')
            ->setRequired('Prosím, zadejte vaši adresu.');

        $form->addPassword('password', 'Heslo:')
            ->setOption('description', sprintf('Alespoň %d znaků', $this->userFacade::PasswordMinLength))
            ->setRequired('Prosím, vytvořte heslo.')
            ->addRule($form::MinLength, null, $this->userFacade::PasswordMinLength);

        $form->addPassword('passwordConfirm', 'Potvrzení hesla:')
            ->setRequired('Prosím, potvrďte vaše heslo.')
            ->addRule($form::Equal, 'Hesla se neshodují.', $form['password']);

        $form->addSubmit('send', 'Registrovat');

        $form->onSuccess[] = function (Form $form, \stdClass $data): void {
            try {
                if ($data->password !== $data->passwordConfirm) {
                    $this->flashMessage('Hesla se neshodují', 'danger');
                    return;
                }

                $birthDateString = sprintf('%04d-%02d-%02d', $data->birth_year, $data->birth_month, $data->birth_day);
                $role = 'user';
                $image = 'Uploads/default/user.png';

                $this->userFacade->add(
                    $data->username,
                    $data->name,
                    $data->surname,
                    $data->email,
                    $data->phone,
                    $data->password,
                    $role,
                    $image,
                    $birthDateString,
                    $data->address
                );

                // Send registration email with more details
                $this->mailSender->sendEmail(
                    email: $data->email,
                    template: 'register',
                    subject: 'Vítejte na naší platformě!',
                    params: [
                        'username' => $data->username,
                        'name' => $data->name,
                        'surname' => $data->surname,
                        'email' => $data->email,
                        'phone' => $data->phone,
                        'birth_date' => sprintf('%d. %s %d', $data->birth_day, $this->getMonthName($data->birth_month), $data->birth_year),
                        'address' => $data->address,
                    ]
                );

                $this->getUser()->login($data->username, $data->password);
                $this->flashMessage('Registrace proběhla úspěšně. Vítejte!', 'success');
                $this->redirect('Dashboard:default');

            } catch (DuplicateNameException $e) {
                $this->flashMessage('Toto uživatelské jméno je již obsazené', 'danger');
            }
        };

        return $form;
    }

    /**
     * Helper method to get Czech month name.
     */
    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'leden', 2 => 'únor', 3 => 'březen', 4 => 'duben',
            5 => 'květen', 6 => 'červen', 7 => 'červenec', 8 => 'srpen',
            9 => 'září', 10 => 'říjen', 11 => 'listopad', 12 => 'prosinec'
        ];
        return $months[$month] ?? '';
    }

    public function actionOut(): void
    {
        $this->getUser()->logout();
        $this->flashMessage('Byli jste úspěšně odhlášeni', 'info');
        $this->redirect('Home:default');
    }

    public function actionIn(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            $session = $this->getSession()->getSection('repairForm');

            if ($session->values) {
                $this->redirect('Repair:default', ['prefill' => $session->values]);
            }
        }
    }
}
