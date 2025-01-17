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

    public function renderShow(int $postId)
{
    $post = $this->postFacade->getPostById($postId);

    if (!$post) {
        $this->error('Příspěvek nebyl nalezen.');
    }

    $this->template->post = $post;

    if ($this->getUser()->isLoggedIn()) {
        $userId = $this->getUser()->getId();
        $rating = $this->postFacade->getUserRating($userId, $postId);
        $this->template->userRating = $rating ? $rating->likes : null;
    } else {
        $this->template->userRating = null;
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
public function handleLiked(int $postId, int $liked)
{
    if ($this->getUser()->isLoggedIn()) {
        $userId = $this->getUser()->getId();
        $this->postFacade->updateRating($userId, $postId, $liked);

        if ($this->isAjax()) {
            $this->redrawControl('postRating');
        } else {
            $this->redirect('this');
        }
    } else {
        $this->flashMessage('Musíte být přihlášeni, abyste mohli hodnotit příspěvky.', 'warning');
        $this->redirect('Sign:in');
    }
}






}
