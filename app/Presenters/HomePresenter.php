<?php
namespace App\Presenters;

use app\Model\PostFacade;
use Nette;

final class HomePresenter extends Nette\Application\UI\Presenter
{
	public function __construct(
		private PostFacade $facade
	) {
	}

	public function renderDefault(int $page = 1): void
	{
		
		// Vytáhneme si publikované články
		$posts = $this->facade->getPublicArticles();

		// a do šablony pošleme pouze jejich část omezenou podle výpočtu metody page
		$lastPage = 0;
		$this->template->posts = $posts->page($page, 5, $lastPage);

		// a také potřebná data pro zobrazení možností stránkování
		$this->template->page = $page;
		$this->template->lastPage = $lastPage;
	}
	public function renderCategory(int $categoryId){
		$posts = $this->facade->getPostByCategoryId($categoryId);
		$this->template->posts = $posts;
	}
}