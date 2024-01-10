#!/usr/bin/env bash
docker rm pages-dev
# docker run -p 80:80 -v ./dev-application.ini:/prod/application.ini --name pages-dev pages-dev
docker run -p 80:80 --name pages-dev pages-dev
