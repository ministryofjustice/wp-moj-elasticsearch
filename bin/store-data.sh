#!/usr/bin/env bash

FUNCTION_NAME=$1
PAYLOAD=$2

aws lambda invoke \
	--function-name "$FUNCTION_NAME" \
	--payload "$PAYLOAD" \
	--cli-binary-format raw-in-base64-out \
	./responses/store-data-size.json
