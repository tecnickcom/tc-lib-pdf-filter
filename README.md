# tc-lib-pdf-filter
*PHP library to decode PDF compression and encryption filters*

[![Latest Stable Version](https://poser.pugx.org/tecnickcom/tc-lib-pdf-filter/version)](https://packagist.org/packages/tecnickcom/tc-lib-pdf-filter)
![Build](https://github.com/tecnickcom/tc-lib-pdf-filter/actions/workflows/check.yml/badge.svg)
[![Coverage](https://codecov.io/gh/tecnickcom/tc-lib-pdf-filter/graph/badge.svg?token=23KB9T46HA)](https://codecov.io/gh/tecnickcom/tc-lib-pdf-filter)
[![License](https://poser.pugx.org/tecnickcom/tc-lib-pdf-filter/license)](https://packagist.org/packages/tecnickcom/tc-lib-pdf-filter)
[![Downloads](https://poser.pugx.org/tecnickcom/tc-lib-pdf-filter/downloads)](https://packagist.org/packages/tecnickcom/tc-lib-pdf-filter)

[![Donate via PayPal](https://img.shields.io/badge/donate-paypal-87ceeb.svg)](https://www.paypal.com/donate/?hosted_button_id=NZUEC5XS8MFBJ)
*Please consider supporting this project by making a donation via [PayPal](https://www.paypal.com/donate/?hosted_button_id=NZUEC5XS8MFBJ)*

* **category**    Library
* **package**     \Com\Tecnick\Pdf\Filter
* **author**      Nicola Asuni <info@tecnick.com>
* **copyright**   2011-2024 Nicola Asuni - Tecnick.com LTD
* **license**     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
* **link**        https://github.com/tecnickcom/tc-lib-pdf-filter
* **SRC DOC**     https://tcpdf.org/docs/srcdoc/tc-lib-pdf-filter

## Description

PHP library to decode PDF compression and encryption filters.

The initial source code has been derived from [TCPDF](<http://www.tcpdf.org>).


## Getting started

First, you need to install all development dependencies using [Composer](https://getcomposer.org/):

```bash
$ curl -sS https://getcomposer.org/installer | php
$ mv composer.phar /usr/local/bin/composer
```

This project include a Makefile that allows you to test and build the project with simple commands.
To see all available options:

```bash
make help
```

To install all the development dependencies:

```bash
make deps
```

## Running all tests

Before committing the code, please check if it passes all tests using

```bash
make qa
```

All artifacts are generated in the target directory.


## Example

Examples are located in the `example` directory.

Start a development server (requires PHP 8.0+) using the command:

```
make server
```

and point your browser to <http://localhost:8000/index.php>


## Installation

Create a composer.json in your projects root-directory:

```json
{
    "require": {
        "tecnickcom/tc-lib-pdf-filter": "^2.0"
    }
}
```

Or add to an existing project with: 

```bash
composer require tecnickcom/tc-lib-pdf-filter ^2.0
```


## Packaging

This library is mainly intended to be used and included in other PHP projects using Composer.
However, since some production environments dictates the installation of any application as RPM or DEB packages,
this library includes make targets for building these packages (`make rpm` and `make deb`).
The packages are generated under the `target` directory.

When this library is installed using an RPM or DEB package, you can use it your code by including the autoloader:
```
require_once ('/usr/share/php/Com/Tecnick/Pdf/Filter/autoload.php');
```



## Developer(s) Contact

* Nicola Asuni <info@tecnick.com>
