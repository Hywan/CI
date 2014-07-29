#!/bin/bash

curl -X POST \
     -H "X-Github-Delivery: 88c0a0f6-1664-11e4-99f9-0b318f0dabc9" \
     -H "Host: github.com" \
     -H "Content-Length: 4671" \
     -H "Connection: close" \
     -H "Content-Type: application/json" \
     -H "Accept: */*" \
     -H "X-Hub-Signature: sha1=5a096795a84f297ab1f45d33eef29cb5013ed26f" \
     -H "X-Github-Event: push" \
     -H "X-Request-Id: ff32b280-df9a-4c66-84e9-486583639267" \
     -H "User-Agent: GitHub Hookshot 771542d" \
     -H "Total-Route-Time: 0" \
     -d @Github_event.json \
     127.0.0.1/api/hook \
     --verbose
