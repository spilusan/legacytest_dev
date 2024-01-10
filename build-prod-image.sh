#!/usr/bin/env bash
docker rm pages-prod 
docker rmi pages-prod -f
docker build -t pages-prod -f Dockerfile.deploy-prod .
