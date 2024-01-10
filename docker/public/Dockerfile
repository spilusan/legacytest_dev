# Use an official PHP 8.2 image as the base
FROM php:8.2-apache

# Enable mod_rewrite
RUN a2enmod rewrite proxy proxy_http

# Install required system dependencies
RUN apt-get update \
    && apt-get install -y \
        wget \
        unzip \
        libmemcached-dev \
        libaio1 \
        libaio-dev \
        zlib1g-dev \
        libssl-dev \
        libxml2-dev \
        alien \
        rpm \
        curl \
        gnupg \
        libzip-dev \
        && rm -rf /var/lib/apt/lists/* \
        && apt-get clean

# Install old python2 version
RUN wget https://www.python.org/ftp/python/2.7.9/Python-2.7.9.tgz \
    && tar xzf Python-2.7.9.tgz \
    && cd Python-2.7.9 \
    && ./configure --enable-optimizations \
    && make altinstall \
    && ln -sfn '/usr/local/bin/python2.7' '/usr/bin/python2' \
    && update-alternatives --install /usr/bin/python python /usr/bin/python2 1

# Copy Oracle Instant Client 11.2 and install
COPY ./oci8/oracle-instantclient11.2-basic-11.2.0.4.0-1.x86_64.rpm /tmp/oracle-instantclient11.2-basic-11.2.0.4.0-1.x86_64.rpm
COPY ./oci8/oracle-instantclient11.2-devel-11.2.0.4.0-1.x86_64.rpm /tmp/oracle-instantclient11.2-devel-11.2.0.4.0-1.x86_64.rpm
COPY ./oci8/oracle-instantclient11.2-sqlplus-11.2.0.4.0-1.x86_64.rpm /tmp/oracle-instantclient11.2-sqlplus-11.2.0.4.0-1.x86_64.rpm
    
# Convert the RPM package to DEB
RUN alien -i --scripts /tmp/oracle-instantclient11.2-basic-11.2.0.4.0-1.x86_64.rpm
RUN alien -i --scripts /tmp/oracle-instantclient11.2-devel-11.2.0.4.0-1.x86_64.rpm
RUN alien -i --scripts /tmp/oracle-instantclient11.2-sqlplus-11.2.0.4.0-1.x86_64.rpm

# Clean up
RUN rm -f /tmp/oracle-instantclient11.2-basic-11.2.0.4.0-1.x86_64.rpm \
    rm -f /tmp/oracle-instantclient11.2-devel-11.2.0.4.0-1.x86_64.rpm \
    rm -f /tmp/oracle-instantclient11.2-sqlplus-11.2.0.4.0-1.x86_64.rpm

# Set environment variables for Oracle Instant Client
ENV LD_LIBRARY_PATH /usr/lib/oracle/11.2/client64/lib
ENV ORACLE_HOME /usr/lib/oracle/11.2/client64

# Install OCI8 and other extension for PHP
RUN apt-get install -y \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip \
    && echo 'instantclient,/usr/lib/oracle/11.2/client64/lib' | pecl install oci8 \
    && docker-php-ext-enable oci8 \
    && docker-php-ext-install soap \
    && docker-php-ext-enable soap \
    && docker-php-ext-configure calendar \
    && docker-php-ext-install calendar

# Install Memcached extension for PHP
RUN pecl install memcached \
    && docker-php-ext-enable memcached

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js (adjust version if needed)
RUN curl -fsSL https://deb.nodesource.com/setup_16.x | bash -
RUN apt-get install -y nodejs npm

RUN node -v
RUN npm -v
RUN npm install -g n \
    && n 8.17.0

# Install Gulp globally
RUN npm install -g gulp-cli \
    && npm install csslint -g

# Set the working directory
WORKDIR /var/www/html

# Expose Apache port
EXPOSE 80

# Copy your PHP application files here if needed
# COPY . /var/www/html

# Command to run Apache with PHP
CMD ["apache2-foreground"]
