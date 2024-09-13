<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use App\Model\PostFacade;

final class EditPresenter extends Nette\Application\UI\Presenter
{
    private PostFacade $postFacade;

    public function __construct(PostFacade $postFacade)
    {
        $this->postFacade = $postFacade;
    }

    public function startup(): void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
    }

    
    public function renderEdit(int $postId): void
    {
        
        $post = $this->postFacade->getPostById($postId);
        $this->template->post = $post;

        if (!$post) {
            $this->error('Post not found');
        }

        $this->getComponent('postForm')->setDefaults($post->toArray());
    }

    public function renderComment(int $id): void
    {
    // Získání komentáře z databáze pomocí jeho ID
    $comment = $this->postFacade->getCommentById($id);

    // Kontrola, zda komentář existuje
    if (!$comment) {
        $this->error('Komentář nebyl nalezen');
    }

    // Předání komentáře do šablony
    $this->template->comment = $comment;
    }


    
protected function createComponentPostForm(): Form
{
    $form = new Form;
    $form->addText('title', 'Titulek:')
        ->setRequired();
    $form->addTextArea('content', 'Obsah:')
        ->setHtmlAttribute('class', 'trumbowyg-editor')
        ->setRequired();
    
    // Přidání pole pro nahrání obrázku
    $form->addUpload('image', 'Obrázek')
    ->addRule(Form::IMAGE, 'Thumbnail must be JPEG, PNG or GIF');

    $form->addSubmit('send', 'Uložit a publikovat');
    $form->onSuccess[] = [$this, 'postFormSucceeded'];

    $statuses = [
        'OPEN' => 'OTEVŘENÝ',
        'CLOSED' => 'UZAVŘENÝ',
        'ARCHIVED' => 'ARCHIVOVANÝ'
    ];
    $form->addSelect('status', 'Stav:', $statuses)
        ->setDefaultValue('OPEN');
    
    $form->addHidden('user_id', $this->getUser()->getId());

    return $form;
}

public function postFormSucceeded($form, $data): void    
{
    $postId = $this->getParameter('postId');

    // Check if an image was uploaded
    if ($data['image'] instanceof Nette\Http\FileUpload && $data['image']->isOk()) {
        // Check if the file was uploaded successfully
        if ($data['image']->getError() === \UPLOAD_ERR_OK) {
            // Move the uploaded file to the 'upload' directory and get its sanitized name
            $data['image']->move('upload/' . $data['image']->getSanitizedName());
            $data['image'] = 'upload/' . $data['image']->getSanitizedName();
        } else {
            // Handle the file upload error
            $this->flashMessage('Soubor nebyl přidán: ' . $data['image']->getError(), 'failed');
            // Redirect back to the form page or handle the error as needed
            return;
        }
    } else {
        // No image uploaded, set image to null or handle the absence of image as needed
        $data['image'] = null;
    }

    if ($postId) {
        $post = $this->postFacade->getPostById($postId);
        if (!$post) {
            $this->error('Post not found');
        }

        $this->postFacade->editPost($postId, $data);
    } else {
        $this->postFacade->insertPost($data);
    }

    // Redirect to the home page
    $this->redirect('Home:default');
}



    protected function createComponentEditCommentForm(): Form
    {
        $form = new Form;

        // Add textarea for comment content
        $form->addDescription('content', 'Obsah komentáře:')
             ->setRequired('Prosím, zadejte obsah komentáře.');

        // Add submit button
        $form->addSubmit('submit', 'Uložit změny');

        // Set up form submission handler
        $form->onSuccess[] = [$this, 'editCommentFormSucceeded'];

        return $form;
    }

    public function editCommentFormSucceeded(Form $form, \stdClass $values): void
    {
    // Get comment ID from request parameters
    $commentId = $this->getParameter('id');

    // Check if commentId is not null
    if ($commentId === null) {
        $this->flashMessage('Nepodařilo se upravit komentář. Chybí ID komentáře.', 'error');
        $this->redirect('this'); // Refresh the current page
        return;
    }

    try {
        // Call the method in PostFacade to edit the comment
        $this->postFacade->editComment($commentId, $values->content);

        // Display success message
        $this->flashMessage('Komentář byl úspěšně upraven.', 'success');
    } catch (\Exception $e) {
        // Display error message if editing the comment fails
        $this->flashMessage('Nepodařilo se upravit komentář.', 'error');
    } 

    // Redirect back to the current page
    $this->redirect('Dashboard:comment');
    }



}
