# My Water Toronto 
Fetch water usage information from City of Toronto MyWaterToronto API endpoint.

## How To Use

1. Clone repository.
2. Create `config.json` file with authentication info. Sample configuration:
```
{
    "account_number": "000000000",
    "client_number": "000000000-00",
    "last_name": "mckim",
    "postal_code": "X1X 1X1",
    "most_recent_method_payment": "4"
}
```
3. Run `php bin/mywatertoronto consumption` in command line.

For more information on `consumption` command options, run `php bin/mywatertoronto consumption --help`.
