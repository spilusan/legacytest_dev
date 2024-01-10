#!/bin/sh

PHP=`which php`;
PATH=`dirname $0`;

#get the application env needed to run scripts
APPLICATION_ENV=$1;
if [ -z $APPLICATION_ENV ]; then
	echo "Incorrect number of arguments";
	echo "Usage: bash build.sh [development|manila-dev|ukdev|testing|test|test2|test3|production]";
	exit 1;
fi

# refreshing memcache for ShipServ average values

$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average response-rate-all-quoted &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average response-rate-all-ignored &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average response-rate-all-declined &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average response-rate-all-pending &

$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average get-avg-response-time-summary &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average quote-completeness &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average quote-variance &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average decline-reason &

$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average direct-order-count-gmv &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average competitive-order-count-gmv &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average poc-count-gmv-time &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average poc-count-gmv-time-total &

$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average get-rfq-to-poc-avg-cycle-time &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average get-rfq-to-ord-avg-cycle-time &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average get-req-to-poc-avg-cycle-time &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average get-req-to-ord-avg-cycle-time &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average get-req-to-qot-avg-cycle-time &

$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average get-win-rate-when-cheapest &
$PHP $PATH/warmup.php $APPLICATION_ENV shipserv-average get-win-rate-when-fastest &

wait
