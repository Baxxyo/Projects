FROM php:8.2-apache

# تفعيل امتداد mysqli للاتصال بقاعدة البيانات
RUN docker-php-ext-install mysqli pdo pdo_mysql

# تفعيل mod_rewrite (مفيد لو المشروع يحتاج إعادة توجيه لاحقًا)
RUN a2enmod rewrite

# نسخ كل ملفات المشروع لمجلد الويب الافتراضي
COPY . /var/www/html/

# جعل home.php الموجود داخل مجلد pages هو الصفحة الافتراضية
RUN echo "DirectoryIndex pages/home.php" > /etc/apache2/conf-available/directory-index.conf \
    && a2enconf directory-index

# صلاحيات القراءة اللازمة
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
