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

namespace Tests\Jorijn\Bitcoin\Dca\Service;

use Jorijn\Bitcoin\Dca\Client\Bl3pClientInterface;
use Jorijn\Bitcoin\Dca\Service\Bl3p\Bl3pWithdrawService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Jorijn\Bitcoin\Dca\Service\Bl3p\Bl3pWithdrawService
 *
 * @covers ::__construct
 *
 * @internal
 */
final class Bl3pWithdrawServiceTest extends TestCase
{
    public const ADDRESS = 'address';
    public const API_CALL = 'apiCall';
    public const GENMKT_MONEY_INFO = 'GENMKT/money/info';

    private Bl3pClientInterface|MockObject $client;
    private LoggerInterface|MockObject $logger;
    private Bl3pWithdrawService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(Bl3pClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new Bl3pWithdrawService(
            $this->client,
            $this->logger,
        );
    }

    /**
     * @covers ::getAvailableBalance
     *
     * @throws \Exception
     */
    public function testGetBalance(): void
    {
        $balance = random_int(1000, 2000);

        $this->client
            ->expects(static::once())
            ->method(self::API_CALL)
            ->with(self::GENMKT_MONEY_INFO)
            ->willReturn($this->createBalanceStructure($balance))
        ;

        static::assertSame($balance, $this->service->getAvailableBalance());
    }

    /**
     * @covers ::getWithdrawFeeInSatoshis
     * @covers ::withdraw
     *
     * @dataProvider providerOfDifferentFeePriorities
     *
     * @throws \Exception
     */
    public function testWithdraw(string $feePriority, int $expectedFeeValue): void
    {
        $this->service = new Bl3pWithdrawService(
            $this->client,
            $this->logger,
            $feePriority,
        );

        $address = self::ADDRESS.random_int(1000, 2000);
        $amount = random_int(100000, 300000);
        $withdrawID = 'id'.random_int(1000, 2000);
        $apiResponse = ['data' => ['id' => $withdrawID]];

        $withdrawFeeInSatoshis = $this->service->getWithdrawFeeInSatoshis();
        $netAmount = $amount - $withdrawFeeInSatoshis;

        static::assertSame($expectedFeeValue, $withdrawFeeInSatoshis);

        $this->client
            ->expects(static::once())
            ->method(self::API_CALL)
            ->with(
                'GENMKT/money/withdraw',
                static::callback(function (array $parameters) use ($feePriority, $netAmount, $address): bool {
                    self::assertArrayHasKey('currency', $parameters);
                    self::assertSame('BTC', $parameters['currency']);
                    self::assertArrayHasKey(self::ADDRESS, $parameters);
                    self::assertSame($address, $parameters[self::ADDRESS]);
                    self::assertArrayHasKey('amount_int', $parameters);
                    self::assertSame($netAmount, $parameters['amount_int']);
                    self::assertArrayHasKey('fee_priority', $parameters);
                    self::assertSame($this->getExpectedFeePriorityFor($feePriority), $parameters['fee_priority']);

                    return true;
                })
            )
            ->willReturn($apiResponse)
        ;

        $completedWithdraw = $this->service->withdraw($amount, $address);
        static::assertSame($withdrawID, $completedWithdraw->getId());
        static::assertSame($netAmount, $completedWithdraw->getNetAmount());
        static::assertSame($address, $completedWithdraw->getRecipientAddress());
    }

    /**
     * @covers ::supportsExchange
     */
    public function testSupportsExchange(): void
    {
        static::assertTrue($this->service->supportsExchange('bl3p'));
        static::assertFalse($this->service->supportsExchange('bl4p'));
    }

    protected function providerOfDifferentFeePriorities(): array
    {
        return [
            'low' => ['low', Bl3pWithdrawService::FEE_COST_LOW],
            'medium' => ['medium', Bl3pWithdrawService::FEE_COST_MEDIUM],
            'high' => ['high', Bl3pWithdrawService::FEE_COST_HIGH],
            'configuration_typo' => ['highhh', Bl3pWithdrawService::FEE_COST_LOW],
        ];
    }

    protected function getExpectedFeePriorityFor(string $feePriority): string
    {
        return match ($feePriority) {
            'medium' => 'medium',
            'high' => 'high',
            default => 'low',
        };
    }

    private function createBalanceStructure(int $balance): array
    {
        return [
            'data' => [
                'wallets' => [
                    'BTC' => [
                        'available' => [
                            'value_int' => $balance,
                        ],
                    ],
                ],
            ],
        ];
    }
}
