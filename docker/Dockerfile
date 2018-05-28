FROM php:7.1.17

RUN apt-get update

# locale
RUN apt-get install -y \
    --no-install-recommends \
    task-japanese
RUN echo "ja_JP.UTF-8 UTF-8" > /etc/locale.gen
RUN apt-get install -y \
    locales && \
    locale-gen && \
    update-locale ja_JP.UTF-8
ENV LANGUAGE ja_JP.UTF-8
ENV LANG ja_JP.UTF-8
ENV LC_ALL ja_JP.UTF-8
ENV LC_CTYPE ja_JP.UTF-8

# timezone
RUN ln -sf /usr/share/zoneinfo/Asia/Tokyo /etc/localtime

# commands & lib
RUN apt-get install -y \
    apt-utils \
    git \
    sqlite3 \
    zlib1g-dev
RUN apt-get install -y \
    python-pip && pip install awscli

# php extensions
RUN docker-php-ext-install zip
RUN pecl install xdebug

# ini
RUN echo "date.timezone=Asia/Tokyo" > /usr/local/etc/php/conf.d/date.ini
RUN echo "zend_extension=xdebug.so" > /usr/local/etc/php/conf.d/xdebug.ini
RUN echo "memory_limit=-1" > /usr/local/etc/php/conf.d/memory-limit.ini

RUN mkdir -p /var/iTerminator
WORKDIR /var/iTerminator

# composer
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_HOME /tmp
ENV COMPOSER_VERSION 1.6.5

RUN curl -s -f -L -o /tmp/installer.php https://raw.githubusercontent.com/composer/getcomposer.org/b107d959a5924af895807021fcef4ffec5a76aa9/web/installer \
    && php -r " \
        \$signature = '544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061'; \
        \$hash = hash('SHA384', file_get_contents('/tmp/installer.php')); \
        if (!hash_equals(\$signature, \$hash)) { \
            unlink('/tmp/installer.php'); \
            echo 'Integrity check failed, installer is either corrupt or worse.' . PHP_EOL; \
            exit(1); \
        }" \
    && php /tmp/installer.php --no-ansi --install-dir=/usr/bin --filename=composer --version=${COMPOSER_VERSION} \
    && composer --ansi --version --no-interaction \
    && rm -rf /tmp/* /tmp/.htaccess

# clean up
RUN rm -rf /var/lib/apt/lists/*

ENTRYPOINT ["php"]