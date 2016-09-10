# download-streamacon
Download videos from StreamACon/Vimeo

Simple PHP script that parses a conference page on StreamACon and extracts all available talks, then loops over the talks and fetches Vimeo's pages to get actual video download URL and then uses wget to download the video file.

**Supports download resume, just re-run the script**

Example: <pre>php downloader.php -v --URL=http://www.streamacon.com/video/laracon-us</pre>

* -v: Verbose, more output about what the script is doing
* --URL: Conference URL, can be "http://www.streamacon.com/video/laracon-us" or just "/video/laracon-us"

*Note: i created this script because currently StreamACon doesn't allow downloading the talks, so people with unreliable internet connections -like myself- will suffer, so i created this to allow downloading the talks for better experience.*
