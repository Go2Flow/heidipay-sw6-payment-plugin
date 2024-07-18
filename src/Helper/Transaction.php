<?php

namespace Go2FlowHeidiPayPayment\Helper;

use Go2FlowHeidiPayPayment\Service\HeidiPayApiService;

class Transaction {

    const CONFIRMED = 'confirmed';
    const INITIATED = 'initiated';
    const WAITING = 'waiting';
    const AUTHORIZED = 'authorized';
    const RESERVED = 'reserved';
    const CANCELLED = 'cancelled';
    const REFUNDED = 'refunded';
    const DISPUTED = 'disputed';
    const DECLINED = 'declined';
    const ERROR = 'error';
    const EXPIRED = 'expired';
    const PARTIALLY_REFUNDED = 'partially-refunded';
    const REFUND_PENDING = 'refund_pending';
    const INSECURE = 'insecure';
    const UNCAPTURED = 'uncaptured';

    const STATUS_MAP = [
        HeidiPayApiService::STATUS_APPROVED => Transaction::CONFIRMED,
        HeidiPayApiService::STATUS_DECLINED => Transaction::DECLINED,
        HeidiPayApiService::STATUS_PENDING => Transaction::WAITING,
        HeidiPayApiService::STATUS_AWAITING => Transaction::WAITING,
        HeidiPayApiService::STATUS_SUCCESS => Transaction::CONFIRMED,
        HeidiPayApiService::STATUS_ACTIVE => Transaction::CONFIRMED,
        HeidiPayApiService::STATUS_CANCELLED => Transaction::CANCELLED,
    ];

    public static function mapStatus(string $status): string
    {
        if (array_key_exists(strtolower($status), self::STATUS_MAP)) {
            return self::STATUS_MAP[strtolower($status)];
        }
        return self::DECLINED;
    }

}
