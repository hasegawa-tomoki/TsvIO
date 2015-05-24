# TsvIO plugin for CakePHP3

Import / export database table records from / to tsv filles.

## Installation

You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

1. Add ```"hasegawa-tomoki/tsvio": "*"``` to ```require``` section.
2. Run ```composer update```.
3. Add ```Plugin::load('Tsvio');``` to bottom of your bootstrap.php.
4. ```bin/cake tsvio import <table>``` or ```bin/cake tsbio export <table>```

