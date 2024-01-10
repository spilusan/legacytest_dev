#!/bin/bash
cd /var/www/html
if [ -f /var/www/html/vendor/autoload.php ]
then
    echo "Project already exist, no installation needed"
else
	echo "Executing pages installation"
    composer install
    npm install
    gulp build
fi

apache2-foreground

