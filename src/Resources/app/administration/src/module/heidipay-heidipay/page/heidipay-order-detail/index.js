import template from './heidipay-order-detail.html.twig';

const { Component,Mixin, State, Context } = Shopware;
const { mapPropertyErrors, mapGetters, mapState } = Shopware.Component.getComponentHelper();
const { Criteria } = Shopware.Data;
const utils = Shopware.Utils;

Component.register('heidipay-order-detail', {
    template,

    inject: [
        'repositoryFactory', 'HeidiPayOrderService'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    metaInfo() {
        return {
            title: 'Heidipay',
        };
    },

    data() {
        return {
            isLoading: true,
            transaction: null,
        };
    },
    computed: {
        buttonEnabled() {
            return !(
                this.transaction === null
                || this.transaction.stateMachineState.technicalName === 'cancelled'
                || this.transaction.stateMachineState.technicalName === 'refunded'
                || this.transaction.amount.totalPrice <= 0
            );
        },
    },

    created() {
        this.loadOrderData();
    },

    methods: {
        submitRefund() {
            this.isLoading = true;
            this.HeidiPayOrderService.submitRefund(this.$route.params.transaction).then(response => {
                if (response.success) {
                    this.createNotificationSuccess({
                        title: this.$tc('HeidiPay.order.messages.refundSuccessTitle'),
                        message: this.$tc('HeidiPay.order.messages.refundSuccessMessage')
                    });
                } else {
                    this.createNotificationError({
                        title: this.$tc('HeidiPay.order.messages.refundErrorTitle'),
                        message: this.$tc('HeidiPay.order.messages.refundErrorMessage')
                    });
                }
            }).catch(error => {
                console.error(error);
            }).finally(() => {
                this.loadOrderData();
            });
        },
        loadOrderData() {
            const repository = this.repositoryFactory.create('order_transaction');

            const criteria = new Criteria(1, 1);
            criteria.addAssociation('order');
            criteria.addAssociation('order.lineItems');
            criteria.addAssociation('stateMachineState');

            return repository.get(this.$route.params.transaction, Context.api, criteria).then((transaction) => {
                this.transaction = transaction;
                this.isLoading = false;
            });
        },
    }
});
