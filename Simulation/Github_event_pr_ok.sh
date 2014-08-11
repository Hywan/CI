#!/bin/bash

curl -X POST \
     -H "X-Github-Delivery: 88c0a0f6-1664-11e4-99f9-0b318f0dabc9" \
     -H "Host: github.com" \
     -H "Connection: close" \
     -H "Content-Type: application/json" \
     -H "Accept: */*" \
     -H "X-Hub-Signature: sha1=7d4a19f14335a353a94a6eaf2d2ceb3bc6d86d26" \
     -H "X-Github-Event: pull_request" \
     -H "X-Request-Id: 25d425c8-e215-4d19-bf47-bf3d97ce7b91" \
     -H "User-Agent: GitHub Hookshot f3c60ab" \
     -H "Total-Route-Time: 0" \
     -d @Github_event_pr_ok.json \
     127.0.0.1/api/hook \
     --verbose
