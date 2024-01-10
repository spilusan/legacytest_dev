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

# poking Oracle table cache for buyer- and supplier-dependant queries
$PHP $PATH/warmup.php $APPLICATION_ENV buyer-specific response-rate-su-ignored &
$PHP $PATH/warmup.php $APPLICATION_ENV buyer-specific poc-count-gmv-time &
$PHP $PATH/warmup.php $APPLICATION_ENV buyer-specific get-req-to-qot-avg-cycle-time-asu &

$PHP $PATH/warmup.php $APPLICATION_ENV supplier-specific declined-rfq-trend &
$PHP $PATH/warmup.php $APPLICATION_ENV supplier-specific sent-rfqs &
$PHP $PATH/warmup.php $APPLICATION_ENV supplier-specific spend-by-product &
$PHP $PATH/warmup.php $APPLICATION_ENV supplier-specific get-req-to-qot-avg-cycle-time-su &

wait
