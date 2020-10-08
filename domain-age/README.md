Script to list expired domains in the specified time window (default 30 days) on the account linked to the specified namesilo API key

The script can take its argument from a configuration file (expiring-domains-config.php) or from the script arguments (arguments take precendence over file settings)

**Arguments
-api-key=
API key to use, must be provided in config file or as a paramenter

-output-type=[html/json/cli]
The output format to display

Default: html

Available: html, json, cli (command line output)

-list-expired=
Show expired domains in the output

Default: true

Available: true, false

-expiration-window=
Number of days to consider in the expiration window (show domains expiring within n days)

Default: 30