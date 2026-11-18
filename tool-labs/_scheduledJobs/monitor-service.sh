#!/bin/bash

#
# Monitors the webservice's uptime and load:
# - Checks how many HTTP requests are queued waiting for the webservice, and writes the result to `~/server-load.json`
#   to enable automatic request deferral. See 'Deferred requests' in the README.
# - Emails the tool maintainers if the webservice is unreachable.
#
# This is a continuous job, so it shouldn't exit in most cases. Exiting the job will cause Toolforge
# to send an email notification and restart it.
#
set -o nounset # note: `errexit` is deliberately not set; see the loop below

##########
## Configure
##########
pollSeconds=2         # how often to check queued requests
heartbeatSeconds=300  # max delay between `server-load.json` writes when nothing changed
workerCount=4         # number of PHP requests which the webservice can process concurrently
alertSeconds=600      # min webservice downtime before an email is sent to maintainers, and the delay before retrying if sending fails
reminderSeconds=3600  # delay between reminder emails while the webservice stays down

toolName=$(basename "$HOME")
scriptDir=$(dirname "$0")

statsUrl="http://$toolName:8000/server-statistics" # call webservice's Kubernetes service directly to avoid extra HTTP overhead and rate limits
path="$HOME/server-load.json"
tempPath="$path.tmp"
logPath="$HOME/logs/job-monitor-service.log"
downtimeMarkerPath="$HOME/downtime.marker" # marker file which indicates the service is down; contains when downtime started, and its last modified timestamp is the last email alert sent


##########
## Define helpers
##########
# Write a message to the log.
#
# This appends without a persistent file handle, to allow for log rotation.
log() {
    printf '%s: %s\n' "$(date --utc '+%Y-%m-%d %H:%M:%S')" "$1" >> "$logPath"
}

# Email the tool maintainers, and log whether it succeeded.
#
# Usage: sendEmail <subject> <body>
sendEmail() {
    local error
    if error=$(printf '%s\n' "$2" | bash "$scriptDir/helpers/send-email.sh" "$1" 2>&1); then
        log "sent email: $1"
    else
        error=${error//$'\n'/ } # keep log entry on one line
        log "couldn't send email '$1'. Error: ${error:-(no output from curl)}"
        return 1
    fi
}


##########
## Handle exit signal
##########
# exit gracefully when Toolforge stops the job normally (e.g. as part of a deploy)
trap 'exit 0' TERM INT


##########
## Monitor webservice
##########
lastQueued=-1
lastWrite=0
lastReadFailed=0
serviceDownSince=0
serviceDownAlertSent=0

while true; do
    # read total number of active PHP requests (including queued)
    response=$(curl --silent --show-error --fail --max-time 2 "$statsUrl" 2>&1)
    curlExitCode=$?
    active=$(awk '$1 == "gw.active-requests:" { print $2; exit }' <<< "$response")

    # service unreachable
    if [ "$curlExitCode" -ne 0 ] || [[ ! "$active" =~ ^[0-9]+$ ]]; then
        # downtime just started
        if [ "$lastReadFailed" -eq 0 ]; then
            # pause load tracking until next try
            if [ "$curlExitCode" -ne 0 ]; then
                reason="${response//$'\n'/ }"
            else
                reason="the response doesn't contain a valid 'gw.active-requests' value"
            fi
            log "can't read $statsUrl (${reason:-no error message from curl}). Keeping $path as-is; will retry every $pollSeconds seconds until it succeeds."
            lastReadFailed=1
            serviceDownSince=$(date '+%s')

            # if the job restarted during an ongoing downtime, keep its original start time
            if [ -e "$downtimeMarkerPath" ]; then
                savedSince=$(< "$downtimeMarkerPath")
                if [[ "$savedSince" =~ ^[0-9]+$ ]]; then
                    serviceDownSince=$savedSince
                fi
            fi
        fi

        # send alert if it stays down
        now=$(date '+%s')
        if [ $(( now - serviceDownSince )) -ge "$alertSeconds" ] && [ $(( now - serviceDownAlertSent )) -ge "$alertSeconds" ]; then
            if [ ! -e "$downtimeMarkerPath" ] || [ $(( now - $(stat --format=%Y "$downtimeMarkerPath") )) -ge "$reminderSeconds" ]; then
                serviceDownAlertSent=$now
                failedAt=$(date --utc --date="@$serviceDownSince" '+%Y-%m-%d %H:%M:%S')
                downHours=$(( (now - serviceDownSince) / 3600 ))
                downMinutes=$(( (now - serviceDownSince) % 3600 / 60 ))

                subject="Webservice is unreachable"
                body="The $toolName webservice has been unreachable since $failedAt UTC (${downHours}h ${downMinutes}m ago, checked via $statsUrl).

See ~/logs/job-monitor-service.log on the server for details."

                if sendEmail "$subject" "$body"; then
                    # also updates the modified time for the next reminder
                    if ! error=$(echo "$serviceDownSince" 2>&1 > "$downtimeMarkerPath"); then
                        error=${error//$'\n'/ } # keep log entry on one line
                        log "couldn't write to $downtimeMarkerPath. Error: ${error:-(no output from failed command)}"
                    fi
                fi
            fi
        fi

        sleep "$pollSeconds"
        continue
    fi
    if [ "$lastReadFailed" -eq 1 ]; then
        log "reading $statsUrl succeeded, resuming normally."
        lastReadFailed=0
    fi
    if [ -e "$downtimeMarkerPath" ]; then
        rm --force "$downtimeMarkerPath"
        sendEmail "Webservice is reachable again" "The $toolName webservice is reachable again as of $(date --utc '+%Y-%m-%d %H:%M:%S') UTC."
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
