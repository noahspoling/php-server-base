FROM httpd:2.4

# Modules app.conf depends on: rewrite for asset and front-controller URLs,
# headers for cache control, proxy_fcgi to reach php-fpm.
RUN sed -i \
    -e 's/^#\(LoadModule rewrite_module\)/\1/' \
    -e 's/^#\(LoadModule headers_module\)/\1/' \
    -e 's/^#\(LoadModule proxy_module\)/\1/' \
    -e 's/^#\(LoadModule proxy_fcgi_module\)/\1/' \
    /usr/local/apache2/conf/httpd.conf

COPY app.conf /usr/local/apache2/conf/app.conf

RUN echo "Include conf/app.conf" >> /usr/local/apache2/conf/httpd.conf
