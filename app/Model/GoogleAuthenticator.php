<?php
namespace App\Model;

use League\OAuth2\Client\Provider\Google;
use Nette\Security\SimpleIdentity;
use Nette\Security\IAuthenticator;

class GoogleAuthenticator implements IAuthenticator
{
    private $google;
   
    public function __construct()
    {
        $this->google = new Google([
            'clientId'     => 'VÁŠ_GOOGLE_CLIENT_ID',
            'clientSecret' => 'VÁŠ_GOOGLE_CLIENT_SECRET',
            'redirectUri'  => 'VAŠE_REDIRECT_URI',
        ]);
    }

    public function authenticate(array $credentials): SimpleIdentity
    {
        [$accessToken] = $credentials;
        $user = $this->google->getResourceOwner($accessToken);
        $googleUser = $user->toArray();

        // Zde můžeš například vyhledat uživatele podle emailu a vrátit jeho identitu
        return new SimpleIdentity($googleUser['id'], null, ['name' => $googleUser['name'], 'email' => $googleUser['email']]);
    }
   
    public function getAuthorizationUrl(): string
    {
        return $this->google->getAuthorizationUrl();
    }

    public function getAccessToken(string $code): \League\OAuth2\Client\Token\AccessToken
    {
        return $this->google->getAccessToken('authorization_code', [
            'code' => $code,
        ]);
    }
}
