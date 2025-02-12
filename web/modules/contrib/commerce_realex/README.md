Global Payments Gateway
----------------------
A Payment gateway for Global Payments (formerly called Realex Payments).  To use
this module you will need to have an account set up with Global Payments and
have received a shared key as well as a merchant ID. Global Payments have the
requirement that you initially use the account in test mode to make sure that
the process works.

Test cards available at
https://developer.realexpayments.com/#!/resources/test-card-numbers

Installation
------------
 - To make sure the correct dependencies are pulled in through composer, you have to _first_ add the following
 to the `repositories` section of your root `composer.json`:
```
{
    "type": "package",
    "package": {
        "name": "annertech/rxp-js",
        "version": "1.3.1.21",
        "type": "drupal-library",
        "dist": {
            "url": "https://github.com/Annertech/rxp-js/archive/1.3.1.21.zip",
            "type": "zip"
        },
        "require": {
            "composer/installers": "~1.0"
        }
    }
}
```
 - Then pull in the module through `composer require drupal/commerce_realex`
 - It can be necessary to add the argument --with-all-dependencies (it is as of now, pulling the -dev version: `composer require 'drupal/commerce_realex:2.0.x-dev@dev'`)
 - Enable the module
 - Go to the payment methods settings page at admin/commerce/config/payment-gateways
 - Select "Add Payment Gateway" and add the Global Payments details
