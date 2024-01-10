#!/usr/bin/env bash
docker rm pages-dev
docker rmi pages-dev -f
docker build -t pages-dev -f Dockerfile.deploy-dev .
