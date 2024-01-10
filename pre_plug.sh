#!/usr/bin/env bash
echo 'pre_plug start';


####################################
# WARNING:
# this script WILL NOT RUN IN PRODUCTION!!! 
# That's because production release is not re-checking out the code, but copying the code directory from UAT to Prod
####################################


#move into root directory to make use of relative paths correctly
cd `dirname $0`;

#get the application env needed to run scripts
APPLICATION_ENV=$1;
if [ -z $APPLICATION_ENV ]; then
	echo "Incorrect number of arguments";
	echo "Usage: bash build.sh [development|manila-dev|ukdev|testing|test|test2|test3]";
	exit 1;
fi
if [ $APPLICATION_ENV = 'production' ]; then
	echo "You cannot run this script in production because production release is not re-checking out the code, but copying the code directory from UAT to Prod ";
	echo "Usage: bash build.sh [development|manila-dev|ukdev|testing|test|test2|test3]";
	exit 1;
fi


#composer (intall php lib dependencies)
sed -i "s/https:\/\/bitbucket.org\//git@bitbucket.org:/g" composer.json || exit 1
composer self-update || exit 1
composer update --optimize-autoloader || exit 1


#gulp (frontend build: js uglify, sass compile, css and js-templates minify, copy all not-built js and css files to public folder)
(npm install && gulp release) || exit 1


#prepare cache buster / not applicabe, since application.ini is moved out from project
#DATE=`date +%Y.%m.%d`
#TS=`date +%s`
#CONFIG='application/configs/application.ini'
#(cat $CONFIG | grep -c "@@@CUR_TIMESTAMP@@@" && sed -i "s/@@@CUR_TIMESTAMP@@@/$TS/g" $CONFIG) || exit 1
#(cat $CONFIG | grep -c "@@@CUR_DATE@@@" && sed -i "s/@@@CUR_DATE@@@/$DATE/g" $CONFIG) || exit 1
#([ `cat $CONFIG | grep -c "@@@CUR_TIMESTAMP@@@"` = 0 ] && [ `cat $CONFIG | grep -c "@@@CUR_DATE@@@"` = 0 ] && php -r "parse_ini_file('$CONFIG');") || exit 1

echo 'pre_plug finished';
