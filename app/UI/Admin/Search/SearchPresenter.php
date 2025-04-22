<?php

declare(strict_types=1);

namespace App\UI\Admin\Search;

use App\Model\UserFacade;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;

final class SearchPresenter extends Presenter
{
    private UserFacade $userFacade;

    public function __construct(UserFacade $userFacade)
    {
        $this->userFacade = $userFacade;
    }

    // Metoda pro vykreslení výsledků
    public function renderResult(?string $keyword = null): void
    {
        // Pokud je klíčové slovo prázdné, zobrazí se všichni uživatelé
        if ($keyword) {
            $this->template->userData = $this->userFacade->searchUsers($keyword);
        } else {
            $this->template->userData = $this->userFacade->getAllUsers(); // Zobrazíme všechny uživatele, pokud není zadáno klíčové slovo
        }
    }

    // Vytvoření vyhledávacího formuláře
    protected function createComponentSearchForm(): Form
    {
        $form = new Form;
        $form->addText('keyword', 'Search keyword:')
             ->setNullable(); // Keyword může být prázdné

        $form->addSubmit('search', 'Search');
        $form->onSuccess[] = [$this, 'searchFormSucceeded'];
        return $form;
    }

    // Zpracování vyhledávacího formuláře
    public function searchFormSucceeded(Form $form, array $values): void
    {
        $keyword = $values['keyword'] ?? null;
        $this->redirect('Search:results', $keyword);
    }
    public function handleDelete(int $userId): void
    {
        $user = $this->userFacade->getUserById($userId);
        if ($user && file_exists($user->image)) {
            unlink($user->image); // Delete the image file from the server
        }
        $this->userFacade->deleteUser($userId);
        $this->redirect('User:default');
    }
}
