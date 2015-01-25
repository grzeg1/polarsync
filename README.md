polarsync
=============

This is PHP app that lets you save files from Polar Flow locally and sync them to Dropbox.
Tapiriik can sync it further to Strava, Endomondo etc.

=============

App will get files from last week and put them locally. Running activities will also be saved to Dropbox.
For Tapiriik you may need to set your Dropbox file format to ```<YYYY>-<MM>-<DD>T<HH>:<MIN>_<NAME>_<TYPE>```
to avoid duplicates.

To install:
- In Dropbox admin panel add Dropbox application with access to your whole account. Generate app key.
- Create config file config.php with Polar Flow user data, app key and directory names as you like
- Run the app :)