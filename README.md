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

## Using Code Standards Tools

The project includes two composer scripts for code standards:

1. **Check code standards**:

   ```bash
   composer phpcs
   ```

   This will scan your code and report any coding standards violations.

2. **Fix code standards automatically**:

   ```bash
   composer phpcbf
   ```

   This will automatically fix coding standards issues that can be fixed automatically.

You can also target specific files or directories:

```bash
composer phpcs -- src/specific-directory
composer phpcbf -- src/specific-file.php
```

Note that not all issues can be fixed automatically with phpcbf. After running it, you should run phpcs again to check for any remaining issues that need manual fixing.
