<?php
namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use App\Model\PostFacade; // Add missing import statement for PostFacade

class PostPresenter extends \Nette\Application\UI\Presenter
{
    private PostFacade $postFacade;

    public function __construct(PostFacade $postFacade)
    {
        $this->postFacade = $postFacade;
    }

    public function renderDefault()
    {
        $this->template->posts = $this->postFacade->getPostById('posts');
    }

    public function renderShow(int $postId) {
        $post = $this->postFacade->getPostbyId($postId);
        $this->postFacade->addView($postId);

        if (!$post) {
            $this->error('Příspěvek nebyl nalezen');
        }

        $this->template->post = $post;
        $this->template->comments = $this->postFacade->getComments($postId);

        // Kontrola stavu příspěvku
        switch ($post->status) {
            case 'CLOSED':
                if (!$this->getUser()->isLoggedIn()) {
                    $this->flashMessage('Přidávání komentářů je povoleno pouze pro přihlášené uživatele.', 'warning');
                }
                // Pro uzavřené a otevřené příspěvky mohou komentovat všichni přihlášení uživatelé
            case 'OPEN':
            case 'ARCHIVED':
                if ($post->status === 'ARCHIVED' && !$this->getUser()->isLoggedIn()) {
                    $this->flashMessage('Nemáš právo vidět archived, kámo!', 'warning');
                    $this->redirect('Home:');
                }
                // Pro archivované příspěvky je potřeba být přihlášený
                $this->template->post = $post;
                break;
            default:
                $this->error('Neplatný stav příspěvku.');
        }
    }


    protected function createComponentCommentForm(): Form
    {
        $form = new Form;

        $form->addTextArea('content', 'Komentář:')->setRequired();
        $form->addSubmit('submit', 'Odeslat');
        $form->onSuccess[] = [$this, 'commentFormSucceeded'];

        return $form;
    }



    public function commentFormSucceeded(Form $form, \stdClass $values): void
{
    $postId = $this->getParameter('postId');
    $userId = $this->user->getId();

    $this->postFacade->addComment($postId, $userId, $values->content);

    $this->flashMessage('Komentář byl úspěšně přidán.', 'success');
    $this->redirect('this');
}
    public function postFormSucceeded(Form $form, \stdClass $values): void
    {
        $postId = $this->getParameter('postId');
        $data = (array)$values;

        // Doplňte zde zpracování souboru

        if ($postId) {
            $post = $this->postFacade->editPost($postId, $data);
        } else {
            $post = $this->postFacade->insertPost($data);
        }

        $this->redirect('show', $post->id);
    }

    public function handleDeleteImage(int $postId) {
        $post = $this->postFacade->getPostById($postId);

        if($post) {
            unlink($post['image']);
            $data['image'] = null;
            $this->postFacade->editPost($postId, $data);
            $this->flashMessage('Obrázek k příspěvku byl smazán');
        }
    }
        public function actionEditComment($id)
{
    // Získání komentáře z databáze pomocí jeho ID
    $comment = $this->database->table('comments')->get($id);

    // Kontrola, zda komentář existuje
    if (!$comment) {
        $this->flashMessage('Komentář nebyl nalezen.', 'error');
        $this->redirect('Dashboard:comment');
    }

    // Kontrola, zda je uživatel přihlášen a je autorem komentáře nebo je administrátorem
    if (!$this->user->isLoggedIn()) {
        $this->flashMessage('Nemáte oprávnění upravit tento komentář.', 'error');
        $this->redirect('Home:default');
    }

    // Předání komentáře do formuláře pro úpravu
    $this['commentForm']->setDefaults($comment->toArray());
}
}
