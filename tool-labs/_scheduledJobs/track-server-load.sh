#!/bin/bash

#
# Continuously checks how many HTTP requests are queued waiting for the webservice, and writes the
# result to `~/server-load.json` to enable automatic request deferral.
#
# See 'Deferred requests' in the README.
#
# This is a continuous job, so it shouldn't exit in most cases. Exiting the job will cause Toolforge
# to send an email notification and restart it.
#
set -o nounset # note: `errexit` is deliberately not set; see the loop below

##########
## Configure
##########
pollSeconds=2         # how often to check queued requests
heartbeatSeconds=300  # update the file at least this often even if nothing changed
workerCount=4         # number of PHP requests which the webservice can process concurrently

toolName=$(basename "$HOME")
statsUrl="http://$toolName:8000/server-statistics" # call webservice's Kubernetes service directly to avoid extra HTTP overhead and rate limits
path="$HOME/server-load.json"
tempPath="$path.tmp"
logPath="$HOME/logs/job-track-server-load.log"


##########
## Define helpers
##########
# Write a message to the log.
#
# This appends without a persistent file handle, to allow for log rotation.
log() {
    printf '%s: %s\n' "$(date --utc '+%Y-%m-%d %H:%M:%S')" "$1" >> "$logPath"
}


##########
## Track queue size
##########
lastQueued=-1
lastWrite=0
lastReadFailed=0

while true; do
    # read total number of active PHP requests (including queued)
    active=$(curl --silent --fail --max-time 2 "$statsUrl" 2>/dev/null | awk '$1 == "gw.active-requests:" { print $2; exit }')

    # on error, pause until next try
    if [[ ! "$active" =~ ^[0-9]+$ ]]; then
        if [ "$lastReadFailed" -eq 0 ]; then
            log "can't read $statsUrl. Keeping $path as-is; will retry every $pollSeconds seconds until it succeeds."
            lastReadFailed=1
        fi
        sleep "$pollSeconds"
        continue
    fi
    if [ "$lastReadFailed" -eq 1 ]; then
        log "reading $statsUrl succeeded, resuming normally."
        lastReadFailed=0
    fi

    # calculate queued requests
    queued=$(( active > workerCount ? active - workerCount : 0 ))

    # update file as needed
    #  - When requests are queued, the file is updated on every tick so tools can defer requests
    #    automatically based on the latest data.
    #  - When the queue is empty, tools don't apply automatic deferral so it's only updated
    #    periodically to indicate the job is still running.
    now=$(date '+%s')
    if [ "$queued" -ne "$lastQueued" ] || [ "$queued" -gt 0 ] || [ $(( now - lastWrite )) -ge "$heartbeatSeconds" ]; then
        # overwrite file atomically (so tools can never read it mid-write)
        content=$(printf '{"queued": %d, "active": %d, "generated": %d}' "$queued" "$active" "$now")
        if error=$({ printf '%s\n' "$content" > "$tempPath" && mv --force "$tempPath" "$path"; } 2>&1); then
            lastQueued=$queued
            lastWrite=$now
        else
            error=${error//$'\n'/ } # keep log entry on one line
            log "couldn't write to $path. Error: ${error:-(no output from failed commands)}"
        fi
    fi

    sleep "$pollSeconds"
done
