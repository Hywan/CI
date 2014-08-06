#!/bin/bash

curl -X POST \
     -H "X-Github-Delivery: 6005d1a2-1d4b-11e4-9baf-329ce132ad45" \
     -H "Host: github.com" \
     -H "Content-Length: 4789" \
     -H "Connection: close" \
     -H "Content-Type: application/json" \
     -H "Accept: */*" \
     -H "X-Hub-Signature: sha1=23c109d5b2f231e60bfc6dff23408671c91abb98" \
     -H "X-Github-Event: push" \
     -H "X-Request-Id: 86054eaa-491f-4d67-ae97-c369ac80052b" \
     -H "User-Agent: GitHub Hookshot eddbeea" \
     -H "Total-Route-Time: 0" \
     -d @Github_event_nok.json \
     127.0.0.1/api/hook \
     --verbose
