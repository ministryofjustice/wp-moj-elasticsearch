#!/usr/bin/env bash

FUNCTION_NAME=$1
PAYLOAD=$2
ASYNC=$3

if [[ "$ASYNC" = "1" ]]; then
	aws lambda invoke \
		--function-name "$FUNCTION_NAME" \
		--invocation-type Event \
		--payload "$PAYLOAD" \
		--cli-binary-format raw-in-base64-out \
		store-data-size.json
else
	aws lambda invoke \
		--function-name "$FUNCTION_NAME" \
		--payload "$PAYLOAD" \
		--cli-binary-format raw-in-base64-out \
		store-data-size.json
fi

kill -9 $$
