<?php

declare(strict_types=1);

namespace Tests\Jorijn\Bitcoin\Dca\Model;

use Jorijn\Bitcoin\Dca\Model\CompletedBuyOrder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Jorijn\Bitcoin\Dca\Model\CompletedBuyOrder
 *
 * @internal
 */
final class CompletedBuyOrderTest extends TestCase
{
    /**
     * @covers ::<public>
     */
    public function testGettersAndSetters(): void
    {
        $dto = new CompletedBuyOrder();

        $dto
            ->setAmountInSatoshis($amountInSatoshis = random_int(1000, 2000))
            ->setFeesInSatoshis($feesInSatoshis = random_int(1000, 2000))
            ->setDisplayFeesSpent($feesSpent = '0.'.random_int(1000, 2000).' BTC')
            ->setDisplayAveragePrice($averagePrice = '€'.random_int(1000, 2000))
            ->setDisplayAmountSpent($amountSpent = '€'.random_int(1000, 2000))
            ->setDisplayAmountSpentCurrency($currency = 'EUR')
            ->setDisplayAmountBought($amountBought = random_int(1000, 2000).' BTC')
        ;

        static::assertSame($amountInSatoshis, $dto->getAmountInSatoshis());
        static::assertSame($feesInSatoshis, $dto->getFeesInSatoshis());
        static::assertSame($feesSpent, $dto->getDisplayFeesSpent());
        static::assertSame($averagePrice, $dto->getDisplayAveragePrice());
        static::assertSame($amountSpent, $dto->getDisplayAmountSpent());
        static::assertSame($currency, $dto->getDisplayAmountSpentCurrency());
        static::assertSame($amountBought, $dto->getDisplayAmountBought());
    }
}
