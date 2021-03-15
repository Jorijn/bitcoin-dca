<?php

declare(strict_types=1);

namespace Jorijn\Bitcoin\Dca\Service\Kraken;

use Jorijn\Bitcoin\Dca\Bitcoin;
use Jorijn\Bitcoin\Dca\Client\KrakenClientInterface;
use Jorijn\Bitcoin\Dca\Exception\KrakenClientException;
use Jorijn\Bitcoin\Dca\Exception\PendingBuyOrderException;
use Jorijn\Bitcoin\Dca\Model\CompletedBuyOrder;
use Jorijn\Bitcoin\Dca\Service\BuyServiceInterface;

class KrakenBuyService implements BuyServiceInterface
{
    public const FEE_STRATEGY_INCLUSIVE = 'include';
    public const FEE_STRATEGY_EXCLUSIVE = 'exclude';

    protected array $lastUserRefs = [];
    protected KrakenClientInterface $client;
    protected string $baseCurrency;
    protected string $tradingPair;
    protected string $feeStrategy;
    protected ?string $tradingAgreement;

    public function __construct(
        KrakenClientInterface $client,
        string $baseCurrency,
        string $feeStrategy = self::FEE_STRATEGY_EXCLUSIVE,
        string $tradingAgreement = null
    ) {
        $this->client = $client;
        $this->baseCurrency = $baseCurrency;
        $this->tradingPair = sprintf('XBT%s', $this->baseCurrency);
        $this->feeStrategy = $feeStrategy;
        $this->tradingAgreement = $tradingAgreement;
    }

    public function supportsExchange(string $exchange): bool
    {
        return 'kraken' === $exchange;
    }

    public function initiateBuy(int $amount): CompletedBuyOrder
    {
        // generate a 32-bit singed integer to track this order
        $lastUserRef = (string) random_int(0, 0x7FFFFFFF);

        $orderDetails = [
            'pair' => $this->tradingPair,
            'type' => 'buy',
            'ordertype' => 'market',
            'volume' => $this->getAmountForStrategy($amount, $this->feeStrategy),
            'oflags' => 'fciq', // prefer fee in quote currency
            'userref' => $lastUserRef,
        ];

        // https://github.com/Jorijn/bitcoin-dca/issues/45
        if (!empty($this->tradingAgreement)) {
            $orderDetails['trading_agreement'] = $this->tradingAgreement;
        }

        $addedOrder = $this->client->queryPrivate('AddOrder', $orderDetails);

        $orderId = $addedOrder['txid'][array_key_first($addedOrder['txid'])];

        $this->lastUserRefs[$orderId] = $lastUserRef;

        // check that its closed
        return $this->checkIfOrderIsFilled($orderId);
    }

    public function checkIfOrderIsFilled(string $orderId): CompletedBuyOrder
    {
        $trades = $this->client->queryPrivate('OpenOrders', ['userref' => $this->lastUserRefs[$orderId] ?? null]);
        if (\count($trades['open'] ?? []) > 0) {
            throw new PendingBuyOrderException($orderId);
        }

        return $this->getCompletedBuyOrder($orderId);
    }

    public function cancelBuyOrder(string $orderId): void
    {
        $this->client->queryPrivate('CancelOrder', [
            'txid' => $orderId,
        ]);
    }

    protected function getCurrentPrice(): string
    {
        $tickerInfo = $this->client->queryPublic('Ticker', [
            'pair' => $this->tradingPair,
        ]);

        return $tickerInfo[array_key_first($tickerInfo)]['a'][0];
    }

    /**
     * Calculated the amount with respect to the given strategy. If an amount of 150 EUR would be bought:.
     *
     * - exclusive: returns 150 / <current price> => 150,00 + 0,39 fee = net yield 150 cost 150,39
     * - inclusive: returns (150 - 0,36) / <current price> => 150,00 - 0,36 = net yield 149,64 cost 149,99
     */
    protected function getAmountForStrategy(int $baseCurrencyAmount, string $feeStrategy): string
    {
        $currentPrice = $this->getCurrentPrice();

        switch ($feeStrategy) {
            case self::FEE_STRATEGY_EXCLUSIVE:
                return bcdiv((string) $baseCurrencyAmount, $currentPrice, Bitcoin::DECIMALS);

            case self::FEE_STRATEGY_INCLUSIVE:
            default:
                $fee = $this->getTakerFeeFromSchedule() / 10000;
                $feeInBaseCurrency = ($baseCurrencyAmount / 100) * $fee;

                return bcdiv((string) ($baseCurrencyAmount - $feeInBaseCurrency), $currentPrice, Bitcoin::DECIMALS);
        }
    }

    /**
     * Returns the fee percentage, taker side. Multiplied by 10000 to ensure rounded integer. 0.26 -> 2600.
     */
    protected function getTakerFeeFromSchedule(): int
    {
        $feeSchedule = $this->client->queryPublic('AssetPairs', ['pair' => $this->tradingPair, 'info' => 'fees']);
        $feePercentage = $feeSchedule[array_key_first($feeSchedule)]['fees'][0][1] ?? 0;
        $feePercentage *= 10000;

        return (int) $feePercentage;
    }

    protected function getCompletedBuyOrder(string $orderId): CompletedBuyOrder
    {
        $trades = $this->client->queryPrivate('TradesHistory', ['start' => time() - 900]);
        $orderInfo = null;

        foreach ($trades['trades'] ?? [] as $trade) {
            if ($trade['ordertxid'] === $orderId) {
                $orderInfo = $trade;

                break;
            }
        }

        if (null === $orderInfo) {
            throw new KrakenClientException('no open orders left yet order was not found, you should investigate this');
        }

        return (new CompletedBuyOrder())
            ->setAmountInSatoshis((int) bcmul($orderInfo['vol'], Bitcoin::SATOSHIS, Bitcoin::DECIMALS))
            ->setFeesInSatoshis(0)
            ->setDisplayAmountBought($orderInfo['vol'].' BTC')
            ->setDisplayAmountSpent($orderInfo['cost'].' '.$this->baseCurrency)
            ->setDisplayAmountSpentCurrency($this->baseCurrency)
            ->setDisplayAveragePrice($orderInfo['price'].' '.$this->baseCurrency)
            ->setDisplayFeesSpent($orderInfo['fee'].' '.$this->baseCurrency)
        ;
    }
}
