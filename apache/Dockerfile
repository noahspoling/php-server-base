FROM httpd:2.4

# Enable mod_rewrite and mod_proxy
RUN sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /usr/local/apache2/conf/httpd.conf \
    && sed -i 's/#LoadModule proxy_module/LoadModule proxy_module/' /usr/local/apache2/conf/httpd.conf \
    && sed -i 's/#LoadModule proxy_fcgi_module/LoadModule proxy_fcgi_module/' /usr/local/apache2/conf/httpd.conf

# 1. Update DocumentRoot to target the public folder
RUN sed -i 's|DocumentRoot "/usr/local/apache2/htdocs"|DocumentRoot "/var/www/html/public"|' /usr/local/apache2/conf/httpd.conf

# 2. Configure directory rules for the new public webroot (Allows .htaccess overrides)
RUN sed -i 's|<Directory "/usr/local/apache2/htdocs">|<Directory "/var/www/html/public">\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted|' /usr/local/apache2/conf/httpd.conf

# 3. Configure Apache to pass PHP requests to the PHP-FPM container targeting the public directory
RUN echo "DirectoryIndex index.php index.html" >> /usr/local/apache2/conf/httpd.conf \
    && echo "ProxyPassMatch ^/(.*\.php(/.*)?)$ fcgi://php:9000/var/www/html/public/\$1" >> /usr/local/apache2/conf/httpd.conf
