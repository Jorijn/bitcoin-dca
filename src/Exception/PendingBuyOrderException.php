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

namespace Jorijn\Bitcoin\Dca\Exception;

class PendingBuyOrderException extends \Exception
{
    public function __construct(protected string $orderId)
    {
        parent::__construct(self::class.' is supposed to be handled, something went wrong here.');
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }
}
