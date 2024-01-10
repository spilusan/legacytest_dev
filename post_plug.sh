#!/usr/bin/env bash
echo 'post_plug start';
echo '***************************************************************************************************'
echo '************ THE RELEASE IS DONE! THIS SCRPT IS ONLY EXECUTING SOME POST-RELEASE OPERATIONS *******'
echo '***************************************************************************************************'

#move into root directory to make use of relative paths correctly
cd `dirname $0`;

#get the application env needed to run scripts
APPLICATION_ENV=$1;
if [ -z $APPLICATION_ENV ]; then
	echo "Incorrect number of arguments";
	echo "Usage: bash build.sh [development|manila-dev|ukdev|testing|test|test2|test3|production]";
	exit 1;
fi

#cdn
php ./scripts/cache/cleancdn.php $APPLICATION_ENV || exit 1


#memcache
php ./scripts/cache/flushcache.php $APPLICATION_ENV || exit 1
dos2unix ./scripts/cache/warmup.sh 
bash ./scripts/cache/warmup.sh $APPLICATION_ENV || exit 1


echo 'post_plug finish';
