# Laracasts Downloader
Downloads new lessons and series from laracasts if there are updates. Or the whole catalogue.

## Description
Syncs your local folder with the laracasts website, when there are new lessons the app download it for you.
If your local folder is empty, all lessons and series will be downloaded!

**An account with an active subscription is necessary!**

## Installation
- Clone this repo to a folder in your machine
- Add a config.ini with your options, there is a config.example.ini
- `composer install`
- `php start.php`and you are done!

Also works in the browser, but is better from the cli because of the instant feedback

## Troubleshooting
If you have a `cURL error 60: SSL certificate problem: self signed certificate in certificate chain` do this:

- Download [http://curl.haxx.se/ca/cacert.pem](http://curl.haxx.se/ca/cacert.pem)
- Add `curl.cainfo = "PATH_TO/cacert.pem"` to your php.ini

And you are done! If using apache you may need to restart it.

## License

This library is under the MIT License, see the complete license [here](LICENSE)
