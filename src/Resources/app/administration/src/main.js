import HeidiPayAPIService from "./module/heidipay-heidipay/HeidiPayAPIService";
import "./service/HeidiPaySettingsService";
import "./service/HeidiPayOrderService";

const { Application } = Shopware;

const initContainer = Application.getContainer('init');

Application.addServiceProvider(
    'HeidiPayAPIService',
    (container) => new HeidiPayAPIService(initContainer.httpClient, container.loginService),
);

import "./init/svgs";

import './module/heidipay-heidipay';

import './module/sw-order/page/sw-order-list';
import './module/sw-order/page/sw-order-detail';
