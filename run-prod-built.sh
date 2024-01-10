#!/usr/bin/env bash
docker rm pages-prod
# docker run -p 80:80 -v ./dev-application.ini:/prod/application.ini --name pages-prod pages-prod
docker run -p 80:80 --name pages-prod pages-prod
