#!/bin/bash

#
# Emails the tool maintainers (i.e. the tool account's address).
#
# Usage: send-email.sh <subject> < body
#
# The subject is prefixed with the tool name. On failure, this writes the error to stderr and exits
# with a non-zero code.
#
set -o errexit -o nounset -o pipefail

smtpUrl="smtp://mail.tools.wmcloud.org:25"
toolName=$(basename "$HOME")
address="tools.$toolName@toolforge.org"

body=$(cat) # from stdin
message=$(cat <<EOF
From: $address
To: $address
Subject: [$toolName] $1
MIME-Version: 1.0
Content-Type: text/plain; charset=utf-8
Content-Transfer-Encoding: 8bit

$body
EOF
)

printf '%s\n' "$message" |
    sed 's/$/\r/' | # SMTP needs CRLF line endings
    curl --silent --show-error --max-time 15 --url "$smtpUrl" --mail-from "$address" --mail-rcpt "$address" --upload-file -
