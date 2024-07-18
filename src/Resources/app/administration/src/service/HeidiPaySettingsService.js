const { Application } = Shopware;
const ApiService = Shopware.Classes.ApiService;

class HeidiPaySettingsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'heidipay_settings_service') {
        super(httpClient, loginService, apiEndpoint);
    }

    validateApiCredentials(credentials) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/validate-api-credentials`,
                {
                    merchant_key: credentials,
                },
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

}

Application.addServiceProvider('HeidiPaySettingsService', (container) => {
    const initContainer = Application.getContainer('init');

    return new HeidiPaySettingsService(initContainer.httpClient, container.loginService);
});

