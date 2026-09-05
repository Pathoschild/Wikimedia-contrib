#!/bin/bash

#
# Emails a summary of errors logged to `error.log` in the last 24 hours.
#
# This reads `~/error.log` and the most recent rotated `~/logs/error.log-*` backup, groups similar
# messages, and sends an email with the top 10.
#
set -o errexit -o nounset -o pipefail

##########
## Configure
##########
hours=24       # number of hours to scan back from the job run date
top_count=10   # number of most common messages to list in the email
max_length=300 # max length of each log message to compare and show

smtp_url="smtp://mail.tools.wmcloud.org:25"
toolName=$(basename "$HOME")
address="tools.$toolName@toolforge.org"

work=$(mktemp --directory)
trap 'rm --recursive --force "$work"' EXIT


##########
## Find error logs
##########
# check latest + rotated logs, since error log may have been rotated recently
logs=()
if [ -f "$HOME/error.log" ]; then
    logs+=("$HOME/error.log")
fi
newestPath=$(ls --format=single-column --sort=time "$HOME/logs/"error.log-* 2>/dev/null | head --lines=1 || true)
if [ -n "$newestPath" ]; then
    logs+=("$newestPath")
fi

if [ ${#logs[@]} -eq 0 ]; then
    echo "no logs found; nothing to report"
    exit 0
fi


##########
## Collect normalized errors
##########
cutoff=$(date --utc --date="$hours hours ago" '+%Y-%m-%d %H:%M:%S')
now=$(date --utc '+%Y-%m-%d %H:%M:%S')

# extract first lines of each error in range into $work/entries
zcat --force -- "${logs[@]}" 2>/dev/null |
    # strip NUL bytes (sometimes left behind by log rotation)
    tr --delete '\0' |

    # grab first line with the timestamp
    grep --text --extended-regexp '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}: ' |

    # drop messages before the cutoff
    awk --assign cutoff="$cutoff" 'substr($0, 1, 19) >= cutoff' > "$work/entries" || true

# drop unneeded log messages, normalize non-deterministic patterns, and sort into $work/counted
sed "
    # These patterns are based on the Lighttpd error log format, which has two forms:
    # Lighttpd message: \`2026-09-03 01:17:12: (configfile.c.1289) WARNING: unknown config-key: server.dir-listing (ignored)\`
    # PHP message:      \`2026-09-03 04:58:28: (mod_fastcgi.c.449) FastCGI-stderr:PHP Fatal error:  Uncaught Error: Call to ...\`

    # strip log timestamp
    s/^[0-9-]* [0-9:]*: //

    # strip source file/line like '(mod_fastcgi.c.449)'
    s/^([^)]*) //

    # strip 'FastCGI-stderr:' marker for PHP errors
    s/^FastCGI-stderr://

    # strip session key added by Logger::error
    s/^\[[0-9a-f]*\] //
    s:/\*[0-9a-f]*\*/:/*key*/:g

    # strip quoted values, like literals in a SQL query
    s/'[^']*'/'?'/g
    s/\"[^\"]*\"/\"?\"/g

    # strip SQL byte-length markers like \`[119]\`
    s/\[[0-9]\{1,\}\]/[N]/g

    # strip process IDs, timestamps, row IDs, etc
    s/[0-9]\{4,\}/N/g
" "$work/entries" |
    # drop routine lifecycle messages (not errors)
    grep --text --invert-match --extended-regexp 'logfiles cycled|server started|server stopped|unknown config-key' |

    # drop lines which just continue the preceding error
    grep --text --invert-match --extended-regexp '^(#[0-9]+ |Stack trace:|[[:space:]]*thrown in )' |

    # grab the top messages
    cut --characters "1-$max_length" |
    sort |
    uniq --count |
    sort --reverse --numeric-sort > "$work/counted" || true

if [ ! -s "$work/counted" ]; then
    echo "no errors logged; nothing to report"
    exit 0
fi


##########
## Build the summary
##########
total=$(awk '{ sum += $1 } END { print sum + 0 }' "$work/counted")
unique=$(wc --lines < "$work/counted")
head --lines="$top_count" "$work/counted" > "$work/top"

{
    echo "$total errors ($unique unique) logged by the $toolName tool between $cutoff and $now UTC."
    echo
    echo "Most common errors:"
    echo
    awk '{ count = $1; sub(/^ *[0-9]+ /, ""); printf "%7d x %s\n", count, $0 }' "$work/top" # "$count x $message"
    echo
    echo "See ~/error.log and ~/logs/error.log-* on the server for the full error info."
} > "$work/body"

cat "$work/body"


##########
## Build the email
##########
{
    echo "From: $address"
    echo "To: $address"
    echo "Subject: [$toolName] New errors logged in the last $hours hours"
    echo "MIME-Version: 1.0"
    echo "Content-Type: text/plain; charset=utf-8"
    echo "Content-Transfer-Encoding: 8bit"
    echo
    cat "$work/body"
} | sed 's/$/\r/' > "$work/message" # SMTP needs CRLF line endings


##########
## Send the email
##########
if ! curl --silent --show-error --url "$smtp_url" --mail-from "$address" --mail-rcpt "$address" --upload-file "$work/message"; then
    echo "could not send the email report" >&2
    exit 1 # job's `emails: onfailure` will send a notification
fi
