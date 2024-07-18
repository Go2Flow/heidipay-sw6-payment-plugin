import './components/heidipay-settings-icon';
import './extension/sw-settings-index';
import './page/heidipay-settings';
import './page/heidipay-order-detail';

import deDE from './snippet/de-DE';
import enGB from './snippet/en-GB';

const { Module } = Shopware;


// Here you create your new route, refer to the mentioned guide for more information
Module.register('heidipay-heidipay', {
    type: 'plugin',
    name: 'HeidiPay',
    title: 'HeidiPay.mainMenuItemGeneral',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#2b52ff',
    icon: 'regular-cog',

    snippets: {
        'de-DE': deDE,
        'de-CH': deDE,
        'en-GB': enGB,
    },

    routes: {
        index: {
            component: 'heidipay-settings',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    },
    settingsItem: {
        name: 'heidipay-settings',
        group: 'plugins',
        to: 'heidipay.heidipay.index',
        iconComponent: 'heidipay-settings-icon',
        backgroundEnabled: true,
    },

    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.order.detail') {
            currentRoute.children.push({
                component: 'heidipay-order-detail',
                name: 'heidipay.order.detail',
                path: '/sw/order/detail/:id/heidipay/:transaction',
                meta: {
                    parentPath: "sw.order.index",
                    privilege: 'order.viewer',
                }
            });
        }
        next(currentRoute);
    },
});
