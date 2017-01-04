## `mediawiki-ip.php` and `mediawiki-globalfunctions.php`

MediaWiki implements some complex logic for validating and parsing IPv4 and IPv6 addresses and ranges. Rather than
reimplement it, with a bit of hacking we can call the MediaWiki functions directly. These are taken directly from
MediaWiki's codebase (see
[IP.php](https://gerrit.wikimedia.org/r/gitweb?p=mediawiki/core.git;a=blob;f=includes/IP.php) and
[GlobalFunctions.php](https://gerrit.wikimedia.org/r/gitweb?p=mediawiki/core.git;a=blob;f=includes/GlobalFunctions.php)).

## `KLogger.php`
This is [KLogger](https://github.com/katzgrau/KLogger) 0.1 by Kenny Katzgrau.