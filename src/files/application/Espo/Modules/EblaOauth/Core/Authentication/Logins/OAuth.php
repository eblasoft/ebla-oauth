<?php

namespace Espo\Modules\EblaOauth\Core\Authentication\Logins;

use Espo\Core\{Api\Request,
    Authentication\AuthToken\AuthToken,
    Authentication\Login,
    Authentication\Login\Data,
    Authentication\Logins\Espo,
    Authentication\Result,
    Authentication\Result\FailReason,
    Exceptions\Error,
    InjectableFactory,
    ORM\EntityManager,
    Utils\Config,
    Utils\Log
};
use Espo\Entities\User;
use Espo\Modules\EblaOauth\Classes\OAuth\Provider;
use Espo\Modules\EblaOauth\Classes\OAuth\ProviderFactory;

class OAuth implements Login
{

    protected Provider $provider;
    private EntityManager $entityManager;
    private Log $log;
    private Espo $baseLogin;
    private Config $config;

    public function __construct(
        EntityManager     $entityManager,
        Log               $log,
        Espo              $baseLogin,
        Config            $config,
        InjectableFactory $injectableFactory
    )
    {
        $this->entityManager = $entityManager;
        $this->log = $log;
        $this->baseLogin = $baseLogin;
        $this->config = $config;

        $this->provider = $injectableFactory->create(ProviderFactory::class)->create();
    }

    /**
     * @param Data $data
     * @param Request $request
     * @return Result
     */
    public function login(Data $data, Request $request): Result
    {
        $username = $data->getUsername();
        $code = $data->getPassword() ?? '';
        $authToken = $data->getAuthToken();

        if ($authToken) {
            return $this->checkAuthToken($username, $authToken);
        }

        if ($username !== "_oAuthCode") {
            return $this->baseLogin->login($data, $request);
        }

        return $this->doLogin($code, $username);
    }

    private function loginByToken(?string $username, AuthToken $authToken = null): ?User
    {
        if (isset($authToken) && isset($username)) {
            $userId = $authToken->getUserId();

            $user = $this->entityManager->getEntity('User', $userId);

            if ($user) {
                $tokenUsername = $user->get('userName');

                if (strtolower($username) != strtolower($tokenUsername)) {
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

                    $this->log->alert(
                        'Unauthorized access attempt for user [' . $username . '] from IP [' . $ip . ']'
                    );

                    return null;
                }

                /** @var ?User */
                return $this->entityManager
                    ->getRDBRepository('User')
                    ->where([
                        'userName' => $username,
                    ])
                    ->findOne();
            }
        }

        return null;
    }

    /**
     * @param string|null $username
     * @param AuthToken $authToken
     * @return Result
     */
    protected function checkAuthToken(?string $username, AuthToken $authToken): Result
    {
        $user = $this->loginByToken($username, $authToken);

        if ($user) {
            return Result::success($user);
        } else {
            return Result::fail(FailReason::WRONG_CREDENTIALS);
        }
    }

    /**
     * @param string $code
     * @param string $username
     * @return Result
     */
    protected function doLogin(string $code, string $username): Result
    {
        $response = $this->provider->getAccessTokenFromAuthorizationCode($code);

        if (!$response['access_token']) {
            return Result::fail(FailReason::CODE_NOT_VERIFIED);
        }

        $emailAddress = $this->provider->getEmailAddressFromResponseResult($response);

        $user = $this->entityManager
            ->getRDBRepository('User')
            ->where([
                'userName' => $emailAddress,
                'type!=' => ['api', 'system'],
            ])
            ->findOne();

        if (!isset($user)) {
            $this->log->warning(
                "OAuth: Authentication success for user {$username}, but user is not created in EspoCRM."
            );

            return Result::fail(FailReason::USER_NOT_FOUND);
        }

        return !$user ? Result::fail() : Result::success($user);
    }
}
