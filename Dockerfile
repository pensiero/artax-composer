FROM pensiero/apache-php

# Labels
LABEL maintainer "oscar.fanelli@gmail.com"

# Packages
RUN apt-get update -q && apt-get install -yqq --force-yes \
    nano \
    php-xdebug

# php.ini configs
RUN sed -i "s/display_errors = .*/display_errors = On/" $PHP_INI && \
    sed -i "s/display_startup_errors = .*/display_startup_errors = On/" $PHP_INI && \
    sed -i "s/error_reporting = .*/error_reporting = E_ALL | E_STRICT/" $PHP_INI

# Start apache
CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]