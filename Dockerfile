FROM php:8.2-apache

# Cài đặt thư viện cần thiết cho PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

RUN sed -i 's/Listen 80/Listen 0.0.0.0:80/' /etc/apache2/ports.conf

# Tạo file cấu hình php.ini tùy chỉnh để tăng giới hạn upload và thời gian session
RUN echo "file_uploads = On\n\
memory_limit = 256M\n\
upload_max_filesize = 64M\n\
post_max_size = 64M\n\
max_execution_time = 600\n\
session.gc_maxlifetime = 86400\n\
session.cookie_lifetime = 86400" > /usr/local/etc/php/conf.d/custom.ini

# Copy code vào container
COPY . /var/www/html/

# Cấp quyền (vẫn giữ để tránh lỗi với các file upload ảnh nếu có sau này)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

RUN a2enmod rewrite

EXPOSE 80
