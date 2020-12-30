#!/usr/bin/env bash

FUNCTION_CODE=$1

aws lambda create function \
	--function-name intranet-write-es-to-s3 \
	--runtime "nodejs12.x" \
	--code "$FUNCTION_CODE"
