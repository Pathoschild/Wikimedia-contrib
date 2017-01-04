<?php
require_once(__DIR__ . '/external/mediawiki-ip.php');
require_once(__DIR__ . '/external/mediawiki-globalfunctions.php');

/**
 * Provides a convenient and stable wrapper over MediaWiki's IP class.
 */
class IPAddress
{
    ##########
    ## Properties
    ##########
    /**
     * A bit flag representing the first IP address in a range.
     * @var int
     */
    const START = 0;

    /**
     * A bit flag representing the last IP address in a range.
     * @var int
     */
    const END = 1;

    /**
     * The first and last IP addresses in the range encoded into MediaWiki's pseudo-hexadecimal notation.
     * @var string[]
     */
    private $encodedRange = null;

    /**
     * The first and last IP addresses in the range in human-readable format.
     * @var string[]
     */
    private $range = null;

    /**
     * Whether the IP address is a valid IPv4 or IPv6 address or range.
     * @var bool
     */
    private $isValid = false;

    /**
     * Whether the IP address is a valid IPv4 address.
     * @var bool
     */
    private $isIPv4 = false;

    /**
     * Whether the IP address is a valid IPv6 address.
     * @var bool
     */
    private $isIPv6 = false;

    /**
     * Whether the IP address is a valid IPv4 or IPv6 address range.
     * @var bool
     */
    private $isValidRange = false;


    ##########
    ## Public methods
    ##########
    /**
     * Construct an instance.
     * @param string $address The string representation of an IP address or CIDR range.
     */
    public function __construct($address)
    {
        // analyze address format
        $this->isValid = IP::isIPAddress($address);
        if (!$this->isValid)
            return;
        $this->isIPv4 = IP::isIPv4($address);
        $this->isIPv6 = !$this->isIPv4 && IP::isIPv6($address);

        // analyze address range
        $this->isValidRange = IP::isValidBlock($address);
        $this->encodedRange = IP::parseRange($address);
        $this->range = array(
            IP::prettifyIP(IP::formatHex($this->encodedRange[self::START])),
            IP::prettifyIP(IP::formatHex($this->encodedRange[self::END]))
        );
    }

    /**
     * Get whether the value is a valid IPv4 or IPv6 address or range.
     * @return bool
     */
    public function isValid()
    {
        return $this->isValid;
    }

    /**
     * Get whether the value is an IPv4 address or range.
     * @return bool
     */
    public function isIPv4()
    {
        return $this->isIPv4;
    }

    /**
     * Get whether the value is an IPv6 address or range.
     * @return bool
     */
    public function isIPv6()
    {
        return $this->isIPv6;
    }

    /**
     * Get whether the value is an IPv4 or IPv6 range.
     * @return bool
     */
    public function isRange()
    {
        return $this->isValidRange;
    }

    /**
     * Get the encoded representation of the IP address.
     * @param int $end Which end of the IP address range to get (one of {@see IPAddress::START} or {@see IPAddress::END}).
     * @return string
     */
    public function getEncoded($end = IPAddress::START)
    {
        if (!$this->isValid)
            return null;
        return $this->encodedRange[$end];
    }

    /**
     * Get the human-readable representation of the IP address.
     * @param int $end Which end of the IP address range to get (one of {@see IPAddress::START} or {@see IPAddress::END}).
     * @return string
     */
    public function getFriendly($end = IPAddress::START)
    {
        if (!$this->isValid)
            return null;
        return $this->range[$end];
    }
}

#############################
## Testing
#############################
/*
$input = $argv[1];
$ip = new IPAddress( $input );
echo
    "<h3>Test output</h3>\n",
    "input: {$input}\n",
    "valid: {$ip->isValid()}\n",
    "form:  ", ($ip->isIPv4() ? "IPv4" : "IPv6") . ($ip->isRange() ? " (range)\n" : "\n"),
    "range: {$ip->getEncoded(IPAddress::START)} &mdash; {$ip->getEncoded(IPAddress::END)}\n",
    "   or: {$ip->getFriendly(IPAddress::START)} &mdash; {$ip->getFriendly(IPAddress::END)}\n",
    "\n",
    print_r($ip, true),
    "\n";
*/