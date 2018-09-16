<?php
namespace Stalktoy;

/**
 * Represents global details about an IP address.
 */
class GlobalIP
{
    /**
     * The underlying IP address.
     * @var \IPAddress
     */
    public $ip;

    /**
     * The global blocks placed against this IP address or IP addresses within this range.
     * @var \Stalktoy\Block[]
     */
    public $globalBlocks;
}
