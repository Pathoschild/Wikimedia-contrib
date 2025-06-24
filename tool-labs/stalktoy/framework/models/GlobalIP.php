<?php
declare(strict_types=1);

namespace Stalktoy;

/**
 * Represents global details about an IP address.
 */
class GlobalIP
{
    /**
     * The underlying IP address.
     */
    public \IPAddress $ip;

    /**
     * The global blocks placed against this IP address or IP addresses within this range.
     * @var \Stalktoy\Block[]
     */
    public array $globalBlocks = [];
}
