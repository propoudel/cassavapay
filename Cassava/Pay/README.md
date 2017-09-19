Cassava Fintech payment gateway Magento extension
------------------------------------------------------

The Cassava_pay provides payment gateway functionality for your Magento CE e-commerce store.

Install
---------
1. Go to Magento2 root folder

2. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable Cassava_Pay --clear-static-content
    php bin/magento setup:upgrade
    php bin/magento setup:static-content:deploy
    ```

3. Enable and configure Cassava Payment module in Magento Admin under Stores/Configuration/Payment Methods/Cassava Payment


Changelog
---------
* 1.0.0
  * First public Magento Marketplace release
