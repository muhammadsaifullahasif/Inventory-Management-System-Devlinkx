<?php

namespace App\Services\Messaging;

use App\Models\SalesChannel;
use App\Services\Messaging\Contracts\MessageChannelDriver;
use App\Services\Messaging\Drivers\EbayMessageDriver;
use App\Services\Messaging\Exceptions\UnsupportedChannelException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the messaging driver for a sales channel from its `type`.
 *
 * Adding a platform (WooCommerce, Shopify, ...) = write a MessageChannelDriver
 * and add one line to $drivers (or call extend() from a service provider).
 */
class MessageDriverManager
{
    /** @var array<string, class-string<MessageChannelDriver>> */
    protected array $drivers = [
        'ebay' => EbayMessageDriver::class,
    ];

    public function __construct(protected Container $container) {}

    /**
     * Register or replace a driver for a channel type.
     *
     * @param class-string<MessageChannelDriver> $driverClass
     */
    public function extend(string $type, string $driverClass): static
    {
        $this->drivers[strtolower($type)] = $driverClass;

        return $this;
    }

    public function supports(SalesChannel|string $channelOrType): bool
    {
        return isset($this->drivers[$this->typeOf($channelOrType)]);
    }

    /**
     * @throws UnsupportedChannelException
     */
    public function forChannel(SalesChannel $channel): MessageChannelDriver
    {
        $type = $this->typeOf($channel);

        if (!isset($this->drivers[$type])) {
            throw new UnsupportedChannelException("Messaging is not supported for channel type '{$type}'.");
        }

        return $this->container->make($this->drivers[$type]);
    }

    /**
     * @return array<int,string> Channel types that have a driver
     */
    public function supportedTypes(): array
    {
        return array_keys($this->drivers);
    }

    protected function typeOf(SalesChannel|string $channelOrType): string
    {
        return strtolower($channelOrType instanceof SalesChannel ? (string) $channelOrType->type : $channelOrType);
    }
}
