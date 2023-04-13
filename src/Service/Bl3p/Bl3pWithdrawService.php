<?php

declare(strict_types=1);

/*
 * This file is part of the Bitcoin-DCA package.
 *
 * (c) Jorijn Schrijvershof <jorijn@jorijn.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jorijn\Bitcoin\Dca\Service\Bl3p;

use Jorijn\Bitcoin\Dca\Client\Bl3pClientInterface;
use Jorijn\Bitcoin\Dca\Model\CompletedWithdraw;
use Jorijn\Bitcoin\Dca\Service\WithdrawServiceInterface;
use Psr\Log\LoggerInterface;

class Bl3pWithdrawService implements WithdrawServiceInterface
{
    final public const BL3P = 'bl3p';
    final public const DEFAULT_FEE_PRIORITY = 'low';
    final public const FEE_COST_LOW = 680;
    final public const FEE_COST_MEDIUM = 5000;
    final public const FEE_COST_HIGH = 10000;

    public function __construct(
        protected Bl3pClientInterface $bl3pClient,
        protected LoggerInterface $logger,
        protected string $configuredFeePriority = self::DEFAULT_FEE_PRIORITY
    ) {
    }

    public function withdraw(int $balanceToWithdraw, string $addressToWithdrawTo): CompletedWithdraw
    {
        $netAmountToWithdraw = $balanceToWithdraw - $this->getWithdrawFeeInSatoshis();
        $response = $this->bl3pClient->apiCall('GENMKT/money/withdraw', [
            'currency' => 'BTC',
            'address' => $addressToWithdrawTo,
            'amount_int' => $netAmountToWithdraw,
            'fee_priority' => $this->getFeePriority(),
        ]);

        return new CompletedWithdraw($addressToWithdrawTo, $netAmountToWithdraw, $response['data']['id']);
    }

    public function getAvailableBalance(): int
    {
        $response = $this->bl3pClient->apiCall('GENMKT/money/info');

        return (int) ($response['data']['wallets']['BTC']['available']['value_int'] ?? 0);
    }

    public function getWithdrawFeeInSatoshis(): int
    {
        return match ($this->getFeePriority()) {
            'low' => self::FEE_COST_LOW,
            'medium' => self::FEE_COST_MEDIUM,
            'high' => self::FEE_COST_HIGH,
        };
    }

    public function supportsExchange(string $exchange): bool
    {
        return self::BL3P === $exchange;
    }

    protected function getFeePriority(): string
    {
        return match ($this->configuredFeePriority) {
            'medium' => 'medium',
            'high' => 'high',
            default => 'low',
        };
    }
}
