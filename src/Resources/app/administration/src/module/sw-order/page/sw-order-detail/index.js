import template from './sw-order-detail.html.twig';

const { Component, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.override('sw-order-detail', {
    template,

    data() {
        return {
            heidipayTransactions: []
        };
    },

    watch: {
        orderId: {
            deep: true,
            handler() {
                this.heidipayTransactions = [];

                if (!this.orderId) {
                    return;
                }

                this.loadOrderData();
            },
            immediate: true
        }
    },

    methods: {
        loadOrderData() {
            const orderRepository = this.repositoryFactory.create('order');

            const orderCriteria = new Criteria(1, 1);
            orderCriteria.addAssociation('transactions');
            orderCriteria.addAssociation('transactions.paymentMethod');

            return orderRepository.get(this.$route.params.id, Context.api, orderCriteria).then((order) => {
                this.loadTransactions(order);
            });
        },

        loadTransactions(order) {
            order.transactions.forEach((orderTransaction) => {
                if (orderTransaction.paymentMethod.technicalName !== 'heidipay_heidipay') {
                    return;
                }

                this.heidipayTransactions.push(orderTransaction);
            });
        }
    }
});

