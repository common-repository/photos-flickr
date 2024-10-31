=== Plugin Name ===
Contributors:   dannyman
Tags:           flickr, photography
Tested up to:   2.7
Stable tag:		trunk

Photos Flickr provides for basic browsing of a Flickr photo
stream from within a WordPress blog.

== Description ==

**NOTE:** This plugin is far from fully baked.  You will need to be comfortable
with template hacking in order to customize this plugin to look good with your
theme.

Photos Flickr is a WordPress plugin I wrote around <a
href="http://www.dancoulter.com/">Dan Coulter</a>'s <a
href="http://www.phpflickr.com/">PHPFlickr</a> library, which in turn accesses
the <a href="http://www.flickr.com/services/api/">Flickr API</a>.

My approach has been to write functions that you can call from a page template
to print out either image index information, or image information, much like
WordPress is either displaying an index of posts, or an individual post.

<a href="http://dannyman.toldme.com/photos-flickr/rtfm/">There are hooks</a>
for the full image index, an index of photo sets, set indexes and tag indexes,
as well as pagination.  There is no support yet for collections.  Numerous bugs
and ugly code are found throughout.

== Installation ==

1. Download and extract the Photos Flickr software archive from: http://downloads.wordpress.org/plugin/photos-flickr.zip
2. Install the `plugins/photos-flickr` directory from the archive to your WordPress `wp-content/plugins/` directory.
3. Log in to your WordPress Admin interface.  Visit the Plugins panel, and Activate the Photos Flickr plugin.
4. Within your WordPress Admin interface, visit Plugins » Photos Flickr and enter your Flickr User ID.
5. Install the `themes/default/photos.php` file from the archive to your preferred theme’s subdirectory within your WordPress `wp-content/themes/`.
6. Within your WordPress Admin interface, visit Page » Write Page to create a new page. Name it "Photos" and set the Page Template to "Photos".
7. Visit the "Photos" page you just created . . . do you get to access your Flickr photo stream? If so, congratulations! If not, please contact me.

You will likely want to "port" the `photos.php` file to match your theme.  You will want to refer to the the `readme.html` file or browse online either of:</br />
http://dannyman.toldme.com/warez/photos-flickr-rtfm.html<br />
http://dannyman.toldme.com/photos-flickr/rtfm/<br />

If you arrive at a good result, and you are using a popular theme, consider
sending me the `photos.php` for me to include for your theme.  My e-mail
address is: dannyman@toldme.com.

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

== Screenshots ==

<a href="mailto:dannyman@toldme.com">Drop me an e-mail with the URL of your
photos-flickr page</a> if you would like to be included in this gallery:

http://dannyman.toldme.com/photos/

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the directory of the stable readme.txt, so in this case, `/tags/4.3/screenshot-1.png` (or jpg, jpeg, gif)
2. This is the second screen shot
