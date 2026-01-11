FROM php:8.2-apache

# --- 1. CÀI ĐẶT THƯ VIỆN HỆ THỐNG ---
# Bổ sung: libfreetype, libjpeg, libpng, libwebp để xử lý ảnh
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql gd

# Sửa cổng mặc định (nếu deploy lên các host yêu cầu binding port động)
RUN sed -i 's/Listen 80/Listen 0.0.0.0:80/' /etc/apache2/ports.conf

# --- 2. CẤU HÌNH PHP.INI ---
# Tăng giới hạn file và thời gian session
RUN echo "file_uploads = On\n\
memory_limit = 256M\n\
upload_max_filesize = 64M\n\
post_max_size = 64M\n\
max_execution_time = 600\n\
session.gc_maxlifetime = 86400\n\
session.cookie_lifetime = 86400" > /usr/local/etc/php/conf.d/custom.ini

# --- 3. COPY SOURCE CODE ---
COPY . /var/www/html/

# Cấp quyền
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Bật Mod Rewrite (cho đường dẫn đẹp nếu cần sau này)
RUN a2enmod rewrite

EXPOSE 80
