# ledyer-checkout-for-woocommerce

Ledyer checkout plugin for WooCommerce

## Local dev environment

Change PHP version in .env or set env variable PHP_VERSION

```shell
npm run install
docker-compose up --build
```

## Running into issues during development?

### When I try to go to checkout, the url is resolved to lco-order=error

This is usually due to missing setup in woocommerce and or invalid api-credentials.

You can add a breakpoint in `lco-functions.php` -> `lco_create_or_update_order` and check the data in the $response. Ledyer will tell you what data is invalid or if the request was unauthorized.

If you are missing data i woocommerce, make sure you have set the following:

- If you are logged in, your use must be set to country = sweden. User -> Edit profile -> Country/Region -> Sweden
- Make sure that the Woocommerce shop is configured to target Sweden. Woocommerce -> Settings -> General -> Country/Region -> Sweden
- Make sure that the Woocommerce shop is configured to use Swedish krona. Woocommerce -> Settings -> General -> Currency -> Swedish krona (kr)
- Make sure to enter a terms url in Ledyer checkout plugin settings.

## Code formatting

We are following rules specified in phpcs.xml. For automatic formatting on save etc we recommend [phpcbf](https://phpqa.io/projects/phpcbf.html), when in installed you can run
```shell
vendor/bin/phpcbf --standard=phpcs.xml .
```
To format the whole codebase, or you may wanna find a plugin for you specific development environment.
