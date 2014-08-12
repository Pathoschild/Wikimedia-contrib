mediawiki-ip.php
mediawiki-globalfunctions.php
=============================
These are taken directly from MediaWiki's codebase:
* https://gerrit.wikimedia.org/r/gitweb?p=mediawiki/core.git;a=blob;f=includes/IP.php
* https://gerrit.wikimedia.org/r/gitweb?p=mediawiki/core.git;a=blob;f=includes/GlobalFunctions.php

(MediaWiki implements some complex logic for validating and parsing IPv4 and IPv6 addresses and ranges.
Rather than reimplement it, with a bit of hacking we can call the MediaWiki functions directly.
Also, this is a hack.)