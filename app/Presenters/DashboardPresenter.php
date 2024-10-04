<?php
namespace App\Presenters;

use App\Model\UserFacade;
use App\Model\PostFacade;
use Nette;

final class DashboardPresenter extends Nette\Application\UI\Presenter
{
    private UserFacade $userFacade;
    private PostFacade $postFacade;

    public function __construct(UserFacade $userFacade, PostFacade $postFacade)
    {
        parent::__construct();
        $this->userFacade = $userFacade;
        $this->postFacade = $postFacade;
    }

    protected function startup(): void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
    }

    public function renderDefault(string $search = null, string $role = null): void
{
    // Zkontrolujeme, zda je uživatel administrátor
    if (!$this->getUser()->isInRole('administrator')) {
        $this->flashMessage('Nemáš dostatečné oprávnění k prohlížení této stránky.', 'warning');
        $this->redirect('Home:default');
    }

    // Nastavíme backlink na hodnotu 'admin'
    $this->getSession('admin')->backlink = 'admin';

    // Podmínka pro hledání uživatelů podle jména
    if ($search !== null) {
        $users = $this->userFacade->getUsersByName($search);
    } else {
        // Pokud je role zadaná, použijeme ji pro filtrování
        if ($role !== null) {
            $users = $this->userFacade->getUserByRole($role);
        } else {
            // Pokud není zadaná role ani jméno, vrátíme všechny uživatele
            $users = $this->userFacade->getUsers();
        }
    }

    // Kontrola, zda vyhledávané jméno existuje
    if ($search !== null && count($users) === 0) {
        $this->flashMessage("Nebyli nalezeni žádní uživatelé se zadaným jménem.", 'error');
        $this->redirect('Dashboard:default');
    }

    // Předáme uživatele a vyhledávací dotaz do šablony
    $this->template->users = $users;
    $this->template->search = $search;
}



    public function renderPost(): void
    {
        $posts = $this->postFacade->getArticlesByUser($this->user);
        $this->template->posts = $posts;
    }


    public function renderComment(): void
    {
        // Vytáhneme si všechny publikované články
        $comments = $this->postFacade->getAllComments();
        // a do šablony pošleme všechny články
        $this->template->comments = $comments;
    }

    public function handleDeleteUser(int $id): void
    {
        // Handle the deletion of the user with the provided userId
        $this->userFacade->deleteUser($id);
        $this->flashMessage("Uživatel byl úspěšně smazán.", 'success');
        $this->redirect('this');
    }
    public function handleDeletePost(int $id): void
    {
        // Handle the deletion of the user with the provided userId
        $this->postFacade->deletePost($id);
        $this->flashMessage("příspěvěk byl úspěšně smazán.", 'success');
        $this->redirect('this');
    }

    public function handleDeleteComment(int $id): void
    {
        // Handle the deletion of the user with the provided userId
        $this->postFacade->deleteComment($id);
        $this->flashMessage("Komentář byl úspěšně smazán.", 'success');
        $this->redirect('this');
    }
    
    

    protected function createComponentSearchForm(): Nette\Application\UI\Form
    {
        $form = new Nette\Application\UI\Form;

        $form->addText('search', 'Vyhledat uživatele:')
            ->setAttribute('placeholder', 'Vyhledat uživatele...');
        $form->addSubmit('submit', 'Hledat');

        $form->onSuccess[] = [$this, 'searchFormSucceeded'];

        return $form;
    }

    public function searchFormSucceeded(Nette\Application\UI\Form $form, array $values): void
    {
        $this->redirect('this', ['search' => $values['search']]);
    }

    public function createAdmin(callable $onSuccess): Form
    {
    return $this->SignUpFormFactory->create($onSuccess);
    }
    

}
