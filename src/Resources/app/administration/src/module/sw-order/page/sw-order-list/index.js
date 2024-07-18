const { Component } = Shopware;
import template from './sw-order-list.html.twig';


import deDE from '../../snippet/de-DE.json';
import enGB from '../../snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

Shopware.Component.override('sw-order-list', {
    template,

    methods : {

        getOrderColumns() {
            let columns = this.$super('getOrderColumns');
            columns.push({
                property: 'id',
                label: 'sw-order.list.heidipay_reference',
                align: 'left'
            });

            return columns;
        },

        getheidiPayReference(order){
            if (order.transactions.length) {
                let found = order.transactions.find(transaction => {
                    return (transaction.customFields && transaction.customFields.external_contract_uuid)
                });
                if (found) {
                    return 'HP_'+order.orderNumber;
                }
            }

            return '-';
        }
    }
});