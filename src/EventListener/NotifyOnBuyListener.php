<?php

declare(strict_types=1);

namespace Jorijn\Bitcoin\Dca\EventListener;

use Jorijn\Bitcoin\Dca\Event\BuySuccessEvent;
use League\HTMLToMarkdown\HtmlConverterInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class NotifyOnBuyListener
{
    protected MailerInterface $notifier;
    protected string $to;
    protected string $from;
    protected string $subjectPrefix;
    protected string $templateLocation;
    protected HtmlConverterInterface $htmlConverter;
    protected string $logoLocation;
    protected string $iconLocation;
    protected string $exchange;
    protected string $quotesLocation;

    public function __construct(
        MailerInterface $notifier,
        HtmlConverterInterface $htmlConverter,
        string $to,
        string $from,
        string $subjectPrefix,
        string $exchange,
        string $templateLocation,
        string $logoLocation,
        string $iconLocation,
        string $quotesLocation
    ) {
        $this->notifier = $notifier;
        $this->to = $to;
        $this->from = $from;
        $this->subjectPrefix = $subjectPrefix;
        $this->templateLocation = $templateLocation;
        $this->htmlConverter = $htmlConverter;
        $this->logoLocation = $logoLocation;
        $this->iconLocation = $iconLocation;
        $this->exchange = ucfirst($exchange);
        $this->quotesLocation = $quotesLocation;
    }

    public function onBuy(BuySuccessEvent $event): void
    {
        $quotes = json_decode(file_get_contents($this->quotesLocation), true, 512, JSON_THROW_ON_ERROR);
        // @noinspection PhpUnusedLocalVariableInspection
        ['quote' => $quote, 'author' => $quoteAuthor] = $quotes[array_rand($quotes)];

        ob_start();

        include $this->templateLocation;
        $html = ob_get_clean();

        $email = (new Email())
            ->from($this->from)
            ->to($this->to)
            ->subject(sprintf('[%s] %s', $this->subjectPrefix, 'You just saved some sats!'))
            ->html($html)
            ->embedFromPath($this->logoLocation, 'logo')
            ->embedFromPath($this->iconLocation, 'github-icon')
            ->text($this->htmlConverter->convert($html))
        ;

        $this->notifier->send($email);
    }
}
