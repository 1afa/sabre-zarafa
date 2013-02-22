# Sabre-Zarafa

The aim of this project is to provide a full CardDav backend for
[SabreDAV](http://code.google.com/p/sabredav) to connect with
[Zarafa](http://www.zarafa.com) groupware.

## License

Sabre-Zarafa is licensed under the terms of the [GNU Affero General Public
License, version 3](http://www.gnu.org/licenses/agpl-3.0.html).

## Install

### Introduction

This installs as any [SabreDAV](http://code.google.com/p/sabredav) server.

### Details

Copy the provided source to your webserver.
[SabreDAV](http://code.google.com/p/sabredav) is already included in the
project source so you do not need to get it separately.

__Warning__: the `data` directory needs to be writable by the web server. This
directory is used to store locks needed by DAV protocol. The file `debug.txt`
must also be writable by webserver processes.

Edit `config.php` to setup your domain name. This should work without setting
it but it is better for SabreDAV to work. You can also adjust other settings,
some are highly experimental (non-UTF8 vcards for instance) and should be used
only for testing.

You then need to redirect all requets to `server.php`. To do this you can use
`mod_rewrite`:

    <Directory /var/www/mail.host/sabre-zarafa>
        DirectoryIndex server.php
        RewriteEngine On
        RewriteBase /sabre-zarafa
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^.*$ /sabre-zarafa/server.php
    </Directory>

Please note that the authentification backend use Basic auth. Some clients will
only work with Basic auth if the host uses SSL.

Some detailed information about SabreDAV setup are available in [SabreDAV
documentation](http://code.google.com/p/sabredav/wiki/Introduction). Do not
hesitate to read it!

## PHP 5.1 and 5.2 version

As of 0.15 Sabre-Zarafa uses SabreDAV 1.6.1 which requires PHP 5.3. If you use
older versions of PHP you will need to revert to official SabreDAV 1.5 (PHP
5.2). If you use PHP 5.1 you should look for the specific PHP 5.1 branch of
SabreDAV, download it and install it in the `lib` directory. This configuration
has not been fully tested.

## Debugging

### Log4Php

Starting with release 0.10 [log4php](http://logging.apache.org/log4php/) is used
for debugging. To configure logging you need to edit `log4php.xml`. Default
setup is to log WARN and FATAL messages to `debug.txt` with a maximum size of
5MB for logfile and 3 backup indexes (`debug.txt.1`).

To disable debugging simply set root appender to `noDebug`.

Log4PHP allow you to log selected messages the way you want. For instance one
could log connection failed messages to syslog or to a database. See [log4php
website](http://logging.apache.org/log4php/) for details!

### Releases 0.9 and before

To enable debugging you need to create a debug.txt file in your sabre-zarafa
installation directory. The web server needs write permissions for this file.
This is __very verbose__ especially if you use contact pictures! so enable only
if needed/asked for.

No password is stored in this debug file.
