<?php
namespace OCA\Gitlab\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IURLGenerator;
use OCP\IInitialStateService;

use OCA\Gitlab\AppInfo\Application;

class Personal implements ISettings {

    private $request;
    private $config;
    private $dataDirPath;
    private $urlGenerator;
    private $l;

    public function __construct(
                        string $appName,
                        IL10N $l,
                        IRequest $request,
                        IConfig $config,
                        IURLGenerator $urlGenerator,
                        IInitialStateService $initialStateService,
                        $userId) {
        $this->appName = $appName;
        $this->urlGenerator = $urlGenerator;
        $this->request = $request;
        $this->l = $l;
        $this->config = $config;
        $this->initialStateService = $initialStateService;
        $this->userId = $userId;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm() {
        $token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token', '');
        $url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', 'https://gitlab.com');
        if ($url === '') {
            $url = 'https://gitlab.com';
        }

        // for OAuth
        $clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', '');
        // don't expose the client secret to users
        $clientSecret = ($this->config->getAppValue(Application::APP_ID, 'client_secret', '') !== '');
        $oauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', '');

        $userConfig = [
            'token' => $token,
            'url' => $url,
            'client_id' => $clientID,
            'client_secret' => $clientSecret,
            'oauth_instance_url' => $oauthUrl,
        ];
        $this->initialStateService->provideInitialState($this->appName, 'user-config', $userConfig);
        return new TemplateResponse(Application::APP_ID, 'personalSettings');
    }

    public function getSection() {
        return 'linked-accounts';
    }

    public function getPriority() {
        return 10;
    }
}
