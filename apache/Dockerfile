FROM httpd:2.4

# Enable mod_rewrite and mod_proxy
RUN sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /usr/local/apache2/conf/httpd.conf
RUN sed -i 's/#LoadModule proxy_module/LoadModule proxy_module/' /usr/local/apache2/conf/httpd.conf
RUN sed -i 's/#LoadModule proxy_fcgi_module/LoadModule proxy_fcgi_module/' /usr/local/apache2/conf/httpd.conf

# Configure Apache to work with PHP-FPM
RUN echo "DirectoryIndex index.php index.html" >> /usr/local/apache2/conf/httpd.conf
RUN echo "ProxyPassMatch ^/(.*\.php(/.*)?)$ fcgi://php:9000/var/www/html/\$1" >> /usr/local/apache2/conf/httpd.conf

# Enable .htaccess files
RUN sed -i '/<Directory "\/usr\/local\/apache2\/htdocs">/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /usr/local/apache2/conf/httpd.conf