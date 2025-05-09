Script accepts CSV files in following format (without header):
<date>,<user_id>,<user_type>,<operation_type>,<amount>,<currency>

Date: format YYYY-MM-DD
User ID: integer
User type: private/business
Operation type: deposit/withdraw
Amount: . as decimal separator, no thousands separator
Currency: ISO 4217 code

Run command: php bin/console calculate:commission-fee path/to/input/file.csv

Test command: php bin/phpunit