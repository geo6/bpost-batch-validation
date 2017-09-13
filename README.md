# *bpost* Batch Address Validation

*bpost* ([Belgian Post Group](http://www.bpost.be/)) provides an API to validate belgian address : <http://www.bpost.be/site/en/webservice-address>.

The goal of the "*bpost* Batch Address Validation" tool is to validate a huge amount of addresses by querying the *bpost* API.

# Install

The tool only requires **PHP 7.0+**.

```
git clone https://github.com/geo6/bpost-batch-validation

# Install Composer
curl -sS https://getcomposer.org/installer | php

# Install dependencies
php composer.phar install
```

# Usage

To process the `data/test.csv`, run :

```
php validate.php --file=data/test.csv
```

If you want to skip some records, you can add `--start` option :

```
php validate.php --file=data/test.csv --start=2
```
