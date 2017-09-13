# *bpost* Batch Address Validation

*bpost* ([Belgian Post Group](https://www.bpost.be/)) provides an API to validate belgian address : <https://www.bpost.be/site/en/webservice-address>.

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

## File structure

Your file must be a valid CSV (Comma-separated values) file.  
The structure is the following :
- Identifier (*string* or *integer*)
- House number (*string* or *integer*)
- Streetname (*string*)
- Postal code (*integer*)
- Municipality name (*string*)

Have a look at `data/test.csv` if necessary.
