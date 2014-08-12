<?php
require_once(__DIR__.'/external/mediawiki-ip.php');
require_once(__DIR__.'/external/mediawiki-globalfunctions.php');

/**
 * Provides a convenient and stable wrapper over MediaWiki's IP class.
 */
class IPAddress {
	#############################
	## Properties
	#############################
	/**
	 * Represents the first IP address in a range.
	 */
	const START = 0;

	/**
	 * Represents the last IP address in a range.
	 */
	const END = 1;
	
	/**
	 * The first and last IP addresses in the range encoded into MediaWiki's pseudo-hexadecimal notation.
	 */
	private $encoded_range = null;

	/**
	 * The first and last IP addresses in the range in human-readable format.
	 */
	private $range = null;
	
	/**
	 * Whether the IP address is a valid IPv4 or IPv6 address or range.
	 */
	private $is_valid = false;

	/**
	 * Whether the IP address is a valid IPv4 address.
	 */
	private $is_ipv4 = false;

	/**
	 * Whether the IP address is a valid IPv6 address.
	 */
	private $is_ipv6 = false;

	/**
	 * Whether the IP address is a valid IPv4 or IPv6 address range.
	 */
	private $is_range = false;
	
	#################################################
	## Public methods
	#################################################	
	/**
	 * Construct an IP address representation.
	 * @param string $address The textual representation of an IP address or CIDR range.
	 */
	public function __construct( $address ) {
		// analyze address format
		$this->is_valid = IP::isIPAddress($address);
		if(!$this->is_valid)
			return;
		$this->is_ipv4 = IP::isIPv4($address);
		$this->is_ipv6 = !$this->is_ipv4 && IP::isIPv6($address);

		// analyze address range
		$this->is_range = IP::isValidBlock($address);
		$this->encoded_range = IP::parseRange($address);
		$this->range = array(
			IP::prettifyIP(IP::formatHex($this->encoded_range[self::START])),
			IP::prettifyIP(IP::formatHex($this->encoded_range[self::END]))
		);
	}

	/**
	 * Get whether the value is a valid IPv4 or IPv6 address or range.
	 */
	public function isValid() {
		return $this->is_valid;
	}

	/**
	 * Get whether the value is an IPv4 address or range.
	 */
	public function isIPv4() {
		return $this->is_ipv4;
	}

	/**
	 * Get whether the value is an IPv6 address or range.
	 */
	public function isIPv6() {
		return $this->is_ipv6;
	}

	/**
	 * Get whether the value is an IPv4 or IPv6 range.
	 */
	public function isRange() {
		return $this->is_range;
	}

	/**
	 * Get the encoded representation of the IP address.
	 * @param int $end Which end of the IP address range to get (one of {@see IPAddress::START} or {@see IPAddress::END}).
	 */
	public function getEncoded( $end = IPAddress::START ) {
		if( !$this->is_valid )
			return null;
		return $this->encoded_range[$end];
	}

	/**
	 * Get the human-readable representation of the IP address.
	 * @param int $end Which end of the IP address range to get (one of {@see IPAddress::START} or {@see IPAddress::END}).
	 */
	public function getFriendly( $end = IPAddress::START ) {
		if( !$this->is_valid )
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