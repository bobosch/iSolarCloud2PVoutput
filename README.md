# iSolarCloud2PVoutput

Download data from Sungrow iSolarCloud.com and upload it to PVoutput.org

# Dependencies

You need to download and configure [GoSungrow](https://github.com/MickMake/GoSungrow/releases) first:
`./GoSungrow config write --user your_username --password your_password --host https://gateway.isolarcloud.eu`

Country       | Host
------------- | ----
Australian    | https://augateway.isolarcloud.com
European      | https://gateway.isolarcloud.eu
Chinese       | https://gateway.isolarcloud.com
International | https://gateway.isolarcloud.com.hk

Check the configuration with `./GoSungrow api login`

# Configuration

Configure in iSolarCloud2PVoutput.php. You get information with `./GoSungrow show ps list`

Name    | Description
------- | -----------
SG_key  | The "Ps Key" of the inverter
SG_ID   | The "Ps Id" of the inverter
PVO_key | API key from PVoutput.org
PVO_ID  | System ID from PVoutput.org

Create a cron job to launch the file with `php -f iSolarCloud2PVoutput.php`
