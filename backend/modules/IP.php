<?php
#################################################
## IP class
## Abstracts decomposition and analysis of CIDR ranges or IP addresses.
## All methods that return an IP address will return the first address of the
## range, unless one of IP::START or IP::END are given.
## 
## Public methods:
## 	new IP( $ip<str> )
##		Initializes an instance with the given IP or CIDR range.
##	valid()
##		Returns whether input is a valid IP or CIDR range.
##	cidr()
##		Returns the numeric suffix representing the range.
##	binary( $flag = IP::START )
##		Returns the IP address in binary notation.
##	decimal( $flag = IP::START )
##		Returns the IP address in dotted decimal notation.
##	hexadecimal( $flag = IP::START )
##		Returns the IP address in hexadecimal notation.
#################################################

class IP {
	#############################
	## Properties
	#############################
	const START = 0;
	const END   = 1;
	
	private $input   = NULL;
	private $ip_bin  = NULL;
	private $ip_dec  = NULL;
	private $ip_hex  = NULL;
	private $ip_cidr = NULL;
	
	private $is_valid   = NULL;
	private $decomposed = false;
	private $re_address = '/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(?:\/(?:[0-9]|[0-2][0-9]|3[0-2]))?$/';

	
	#################################################
	## Public methods
	#################################################	
	#############################
	## Constructor
	## Validate IP or CIDR
	#############################
	public function __construct( $address ) {
		/* validate */
		if( !preg_match($this->re_address, $address) ) {
			$this->is_valid = false;
			return;
		}
		
		$this->input = $address;
		$this->is_valid = true;
	}


	#############################
	## Valid IP or CIDR?
	#############################
	public function valid() {
		return $this->is_valid;
	}


	#############################
	## Return CIDR value
	#############################
	public function cidr() {
		$this->_decompose();
		
		if( $this->ip_cidr >= 0 )
			return $this->ip_cidr;
		return 32;
	}


	#############################
	## Return binary notation
	#############################
	public function bin( $flag  = NULL ) {
		return $this->binary( $flag );
	}
	public function binary( $flag = NULL ) {
		if( !$this->is_valid ) return NULL;	
		
		/* calculate */	
		if( !$this->ip_bin ) {
			$this->_decompose();
			$bin = $this->_ip2bin( $this->input );
			
			if( $this->ip_cidr ) {
				/* get common binary prefix */
				$bin = substr( $bin, 0, $this->ip_cidr );
				
				/* get binary range ends */
				$start = str_pad( $bin, 32, '0', STR_PAD_RIGHT );
				$end   = str_pad( $bin, 32, '1', STR_PAD_RIGHT );
				$this->ip_bin = Array( $start, $end );
			}
			else
				$this->ip_bin = Array( $bin, $bin );
		}
		
		/* return */
		if( $flag == self::END )
			return $this->ip_bin[self::END];
		return $this->ip_bin[self::START];
	}
	
	
	#############################
	## Return decimal notation
	#############################
	public function dec( $flag = NULL ) {
		return $this->decimal( $flag );
	}
	public function decimal( $flag = NULL ) {
		if( !$this->is_valid ) return NULL;
		
		/* calculate */
		if( !$this->ip_dec ) {
			$this->_decompose();
			
			if( $this->ip_cidr ) {
				$this->binary();
				$start = $this->_bin2ip( $this->ip_bin[self::START] );
				$end   = $this->_bin2ip( $this->ip_bin[self::END] );
				$this->ip_dec = Array( $start, $end );
			}
			else
				$this->ip_dec = Array( $this->input, $this->input );
		}
		
		/* return */
		if( $flag == self::END )
			return $this->ip_dec[self::END];
		else if( $flag == self::START || $flag == NULL )
			return $this->ip_dec[self::START];
		else
			return -1;
	}


	#############################
	## Return hexadecimal notation
	#############################
	public function hex( $flag = NULL ) {
		return $this->hexadecimal( $flag );
	}
	public function hexadecimal( $flag = NULL ) {
		if( !$this->is_valid ) return NULL;
		
		/* calculate */
		if( !$this->ip_hex ) {
			$this->_decompose();
			$this->binary();
			
			if( $this->ip_cidr ) {
				$start = $this->_bin2hex( $this->ip_bin[self::START] );
				$end   = $this->_bin2hex( $this->ip_bin[self::END] );
				$this->ip_hex = Array( $start, $end );
			}
			else {
				$hex = $this->_bin2hex( $this->ip_bin[self::START] );
				$this->ip_hex = Array( $hex, $hex );
			}
		}
		
		/* return */
		if( $flag == self::END )
			return $this->ip_hex[self::END];
		return $this->ip_hex[self::START];		
	}


	#############################
	## Return pretty range for HTML output
	#############################
	public function pretty_range() {
		if( $this->cidr() && $this->cidr() < 32 )
			return $this->dec() . " — " . $this->dec( self::END );
		return $this->dec();
	}


	#################################################
	## Private methods
	#################################################	
	#############################
	## Convert IP to binary
	#############################
	private function _ip2bin( $ip ) {
		$parts = explode( '.', $ip );
		return sprintf('%08b%08b%08b%08b', $parts[0], $parts[1], $parts[2], $parts[3]);
	}

	#############################
	## Convert binary to ip
	#############################
	private function _bin2ip( $bin ) {
		preg_match_all( '/.{8}/', $bin, $parts );
		$parts = $parts[0];
		return sprintf('%s.%s.%s.%s', bindec($parts[0]), bindec($parts[1]), bindec($parts[2]), bindec($parts[3]));
	}
	
	private function _bin2hex( $bin ) {
		preg_match_all( '/.{8}/', $bin, $parts );
		$parts = $parts[0];
		$hex = sprintf('%02s%02s%02s%02s', base_convert($parts[0], 2, 16), base_convert($parts[1], 2, 16), base_convert($parts[2], 2, 16), base_convert($parts[3], 2, 16) );
		return strtoupper( $hex );
	}

	#############################
	## decompose CIDR
	#############################
	private function _decompose() {
		if( $this->decomposed )
			return;
		$this->decompose = true;

		if( strpos($this->input, '/') ) {
			$parts       = explode( '/', $this->input );
			$this->input   = $parts[0];
			$this->ip_cidr = $parts[1];
		}
	}
}

#############################
## Testing
#############################
/*
$input = '127.0.0.1/16';

echo "input: $input\n\n";
$ip = new IP( $input );
if( !$ip->valid() )
	die( 'not valid' );

echo "=== range ===\n";
echo 'CIDR: ', $ip->cidr(), "\n";
echo $ip->binary(), "\n", $ip->binary( IP::END ), "\n\n";
echo $ip->hex(), " -- ", $ip->hex( IP::END ), "\n\n";

echo $ip->decimal(), " -- ", $ip->decimal( IP::END ), "\n\n";
*/
?>
