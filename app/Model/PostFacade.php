<?php

namespace App\Model;

use Nette;


final class PostFacade
{
    private $userFacade;

    public function __construct(
        public Nette\Database\Explorer $database,
        UserFacade $userFacade
    ) {
        $this->userFacade = $userFacade;
    }

    public function getPublicArticles()
    {
        return $this->database
            ->table('posts')
            ->where('created_at < ', new \DateTime)
            ->order('created_at DESC');
    }


    public function getArticlesByUser(Nette\Security\User $user)
    {
        $posts = $this->database->table('posts');
        if (!$user->isInRole('administrator')) {
            $posts->where('user_id', $user->getId());
        }
        return $posts->fetchAll();
    }


    public function getPostById(int $postId)
    {
        $post = $this->database
            ->table('posts')
            ->get($postId);
    
        if (!$post) {
            throw new Nette\Application\BadRequestException('Stránka nebyla nalezena');
        }
    
        return $post;
    }
    
    public function addView(int $postId){
        $post = $this->getPostById($postId);

        // Získání aktuálního počtu zhlédnutí
        $currentViews = $post->views ?? 0;

        // Inkrementace počtu zhlédnutí
        $newViews = $currentViews + 1;

        // Aktualizace počtu zhlédnutí v databázi
        $this->database->table('posts')
            ->where('id', $postId)
            ->update(['views' => $newViews]);
    }

    public function addComment(int $postId, int $userId, string $content)
    {
    // Získáme uživatele pomocí UserFacade
    $user = $this->userFacade->getUserById($userId);

    // Pokud uživatel neexistuje, vrátíme chybu
    if (!$user) {
        throw new \Exception('User not found');
    }

    $this->database->table('comments')->insert([
        'post_id' => $postId,
        'user_id' => $userId, 
        'name' => $user->jmeno, // Insert name from user
        'email' => $user->email, // Insert email from user
        'content' => $content,
    ]);
    }

    public function getAllComments()
{
    return $this->database
        ->table('comments')
        ->order('created_at');
}

    public function getComments($postId)
    {
        return $this->database
            ->table('comments')
            ->where('post_id', $postId)
            ->fetchAll();
    }


    public function getCommentById(int $id)
    {
        return $this->database
            ->table('comments')
            ->get($id);
    }


    public function editPost(int $postId, $data)
    {
        // Implementace metody pro editaci příspěvku
        return $this->database
            ->table('posts')
            ->where('id', $postId)
            ->update($data);
    }

    public function insertPost($data)
{
    // Check if the 'image' field is set, is an object and if it's OK
    if (isset($data['image']) && is_object($data['image']) && $data['image']->isOk()) {
        // Move the uploaded file to the 'upload' directory
        $data['image']->move('upload/');
        $data['image'] = $data['image']->getSanitizedName();
    }

    // Insert data into the database
    return $this->database
        ->table('posts')
        ->insert($data);
}

    public function findPublishedArticles(): Nette\Database\Table\Selection
	{
		return $this->database->table('posts')
			->where('created_at < ', new \DateTime)
			->order('created_at DESC');
	}
    public function deletePost(int $postId): void
    {
        $this->database
            ->table('posts')
            ->where('id', $postId)
            ->delete();
    }

    public function deleteComment(int $commentId): void
    {
        $this->database
            ->table('comments')
            ->where('id', $commentId)
            ->delete();
    }
    public function editComment(int $commentId, string $content): void
    {
        $comment = $this->database->table('comments')->get($commentId);
        
        if (!$comment) {
            throw new \Exception('Komentář nebyl nalezen');
        }

        $comment->update(['content' => $content]);
    }
    
    public function getPostByCategoryId(int $categoryId)
    {
        return $this->database
            ->table('posts')
            ->where('category_id', $categoryId);
    }
    
    public function updateRating(int $userId, int $postId, int $rating): void
{
    $existingRating = $this->database->table('rating')
        ->where('user_id', $userId)
        ->where('post_id', $postId)
        ->fetch();

    if ($existingRating) {
        $existingRating->update([
            'likes' => $rating === 1 ? 1 : 0,
            'dislikes' => $rating === -1 ? 1 : 0,
        ]);
    } else {
        $this->database->table('rating')->insert([
            'user_id' => $userId,
            'post_id' => $postId,
            'likes' => $rating === 1 ? 1 : 0,
            'dislikes' => $rating === -1 ? 1 : 0,
        ]);
    }
}

public function getUserRating(int $userId, int $postId)
{
    return $this->database->table('rating')
        ->where('user_id', $userId)
        ->where('post_id', $postId)
        ->fetch();
}

    



}