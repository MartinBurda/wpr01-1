<?php

declare(strict_types=1);

namespace App\UI\Front\Home;

use Nette;

/**
 * Presenter for the home page.
 */
final class HomePresenter extends Nette\Application\UI\Presenter
{
    public function renderDefault(): void
    {
        // Flash messages are displayed automatically in Latte templates
    }
}
