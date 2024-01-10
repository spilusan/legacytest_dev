# Use the official Apache image as the base image
FROM httpd:latest

# Enable required Apache modules by uncommenting necessary lines in httpd.conf
RUN sed -i \
    -e 's/#LoadModule proxy_module/LoadModule proxy_module/' \
    -e 's/#LoadModule proxy_http_module/LoadModule proxy_http_module/' \
    -e 's/#LoadModule rewrite_module/LoadModule rewrite_module/' \
    /usr/local/apache2/conf/httpd.conf

# Copy your custom Apache configuration files
COPY ./httpd.conf /usr/local/apache2/conf/httpd.conf
# COPY ./sites-available/000-default.conf /etc/apache2/sites-available/000-default.conf

# Expose port 80
EXPOSE 80

# Start Apache when the container starts
CMD ["httpd-foreground"]
