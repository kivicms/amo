<?php
define('TOKEN_FILE', __DIR__ . '/runtime' . DIRECTORY_SEPARATOR . 'token_info.json');

use AmoCRM\OAuth2\Client\Provider\AmoCRM;

include_once __DIR__ . '/vendor/autoload.php';
include_once __DIR__ . '/vendor/amocrm/oauth2-amocrm/src/AmoCRM.php';

session_start();
/**
 * Создаем провайдера
 */
$provider = new AmoCRM([
    'clientId' => 'eb01e67a-e1ec-4f54-980f-990eef0a6df4',
    'clientSecret' => 'yqPZLFb28bzXLus3xy7xefWhnzAFYBpyVYsOmxYMkLqyH5FVnUYgNITyICpbVLZw',
    'redirectUri' => 'https://e9d68a533355.ngrok.io/auth.php',
]);

if (isset($_GET['referer'])) {
    $provider->setBaseDomain($_GET['referer']);
}

if (!isset($_GET['request'])) {
    if (!isset($_GET['code'])) {
        /**
         * Просто отображаем кнопку авторизации или получаем ссылку для авторизации
         * По-умолчанию - отображаем кнопку
         */
        $_SESSION['oauth2state'] = bin2hex(random_bytes(16));
        if (true) {
            echo '<div>
                <script
                    class="amocrm_oauth"
                    charset="utf-8"
                    data-client-id="' . $provider->getClientId() . '"
                    data-title="Установить интеграцию"
                    data-compact="false"
                    data-class-name="className"
                    data-color="default"
                    data-state="' . $_SESSION['oauth2state'] . '"
                    data-error-callback="handleOauthError"
                    src="https://www.amocrm.ru/auth/button.min.js"
                ></script>
                </div>';
            echo '<script>
            handleOauthError = function(event) {
                alert(\'ID клиента - \' + event.client_id + \' Ошибка - \' + event.error);
            }
            </script>';
            die;
        } else {
            $authorizationUrl = $provider->getAuthorizationUrl(['state' => $_SESSION['oauth2state']]);
            header('Location: ' . $authorizationUrl);
        }
    } elseif (empty($_GET['state']) || empty($_SESSION['oauth2state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
        exit('Invalid state');
    }

    /**
     * Ловим обратный код
     */
    try {
        /** @var \League\OAuth2\Client\Token\AccessToken $access_token */
        $accessToken = $provider->getAccessToken(new League\OAuth2\Client\Grant\AuthorizationCode(), [
            'code' => $_GET['code'],
        ]);

        if (!$accessToken->hasExpired()) {
            saveToken([
                'accessToken' => $accessToken->getToken(),
                'refreshToken' => $accessToken->getRefreshToken(),
                'expires' => $accessToken->getExpires(),
                'baseDomain' => $provider->getBaseDomain(),
            ]);
        }
    } catch (Exception $e) {
        die((string)$e);
    }

    /** @var \AmoCRM\OAuth2\Client\Provider\AmoCRMResourceOwner $ownerDetails */
    $ownerDetails = $provider->getResourceOwner($accessToken);

    printf('Hello, %s!', $ownerDetails->getName());
} else {
    $accessToken = getToken();

    $provider->setBaseDomain($accessToken->getValues()['baseDomain']);

    /**
     * Проверяем активен ли токен и делаем запрос или обновляем токен
     */
    if ($accessToken->hasExpired()) {
        /**
         * Получаем токен по рефрешу
         */
        try {
            $accessToken = $provider->getAccessToken(new League\OAuth2\Client\Grant\RefreshToken(), [
                'refresh_token' => $accessToken->getRefreshToken(),
            ]);

            saveToken([
                'accessToken' => $accessToken->getToken(),
                'refreshToken' => $accessToken->getRefreshToken(),
                'expires' => $accessToken->getExpires(),
                'baseDomain' => $provider->getBaseDomain(),
            ]);

        } catch (Exception $e) {
            die((string)$e);
        }
    }

    $token = $accessToken->getToken();

    try {
        /**
         * Делаем запрос к АПИ
         */
        $data = $provider->getHttpClient()
            ->request('GET', $provider->urlAccount() . 'api/v2/account', [
                'headers' => $provider->getHeaders($accessToken)
            ]);

        $parsedBody = json_decode($data->getBody()->getContents(), true);
        printf('ID аккаунта - %s, название - %s', $parsedBody['id'], $parsedBody['name']);
    } catch (GuzzleHttp\Exception\GuzzleException $e) {
        var_dump((string)$e);
    }
}


function saveToken($accessToken)
{
    if (
        isset($accessToken)
        && isset($accessToken['accessToken'])
        && isset($accessToken['refreshToken'])
        && isset($accessToken['expires'])
        && isset($accessToken['baseDomain'])
    ) {
        $data = [
            'accessToken' => $accessToken['accessToken'],
            'expires' => $accessToken['expires'],
            'refreshToken' => $accessToken['refreshToken'],
            'baseDomain' => $accessToken['baseDomain'],
        ];

        file_put_contents(TOKEN_FILE, json_encode($data));
    } else {
        exit('Invalid access token ' . var_export($accessToken, true));
    }
}

/**
 * @return \League\OAuth2\Client\Token\AccessToken
 */
function getToken()
{
    $accessToken = json_decode(file_get_contents(TOKEN_FILE), true);

    if (
        isset($accessToken)
        && isset($accessToken['accessToken'])
        && isset($accessToken['refreshToken'])
        && isset($accessToken['expires'])
        && isset($accessToken['baseDomain'])
    ) {
        return new \League\OAuth2\Client\Token\AccessToken([
            'access_token' => $accessToken['accessToken'],
            'refresh_token' => $accessToken['refreshToken'],
            'expires' => $accessToken['expires'],
            'baseDomain' => $accessToken['baseDomain'],
        ]);
    } else {
        exit('Invalid access token ' . var_export($accessToken, true));
    }
}

// http://vitalik.myjino.ru/amo/index.php?code=def50200fce8f2f06ad8de4ff819aa18f857feaf7984d114936c9be00dbc1b57836a9981d85c7c45df8fe83207c80572ef51eb3bb090ea1ac1810a704cd6ef5bf29071d3aaa6d7bd747493c4822798e642c2a75050e6bbe5ed87c47f95bad07614ca6a885f883e5f39d73f1c1f92729d986b9f04315f33120a5a2b9934ac3be311bc0d99c499e189d3656e9c49cb48bf01892683c7f7e9328fb7b99d4602b1681fc3224eadf534f1c1815a2ca29439e17fbcf28cbecd378f7b0349779a30b8fe42462dd388cbecad4f1d3fefb8f6799e84827720297b659965ca13e98186abb07eb84a3ce4f243af1cb1c8c805b5d9e87ba6767054877621bc95e55c72da9e29f8cf6b5dbddee5454baf55aa1ddeb699c8ca7110415d39cedb3e42fe42731fff4cdd656f0c80719783abc0955dabfca97305d1d832bb40adeabce459222fdeb9e16972da026b658fff1a80a5dd4ba7b65f31ccb1c75108f19d9e8b69c4ef244bf0827835b9942423378edfbb22dfe7015f99e5806910ababf99d763768318c188129153a6bb475648904fa590f04100db9f72fd363ec65b88b16e82652e4a30ed38fb437fb5b9349135e2c64b02fd6c2e445402eed4217cff146750fd693611bbcc85bb6fc319bab729e8b3e038d3411ea38&state=c9e750987328520768470fc0688367c2&referer=ivanovv1972.amocrm.ru&client_id=eb01e67a-e1ec-4f54-980f-990eef0a6df4
