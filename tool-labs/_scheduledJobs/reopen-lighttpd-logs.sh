#!/bin/bash

#
# Notifies Lighttpd to reopen its log file handles after log rotation.
#

# get path to kubectl
# This script is run from within the job container, so it can't access `/usr/bin/kubectl` directly.
KUBECTL="$HOME/bin/kubectl"
if [ ! -x "$KUBECTL" ]; then
    echo "Can't reopen the Lighttpd logs: '$KUBECTL' wasn't found or isn't executable. See README.md for deploy steps." >&2
    exit 1
fi

# find pod running the Lighttpd webservice
POD=$("$KUBECTL" get pod --selector app.kubernetes.io/component=web --output name | head --lines=1)
if [ -z "$POD" ]; then
    echo "Can't reopen the Lighttpd logs: the webservice wasn't found. Is it running?" >&2
    exit 1
fi

# send SIGHUP to Lighttpd (PID 1), so it reopens log handles without restarting the service itself
"$KUBECTL" exec "$POD" -- kill --signal HUP 1
