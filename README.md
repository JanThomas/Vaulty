# Vaulty

[wp-Contributors]: <> (jtvie)
[wp-Donation link]: <> (https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=59UPSYWME9YX6&currency_code=EUR&source=url)
[wp-Tags]: <> (attachments, uploads, files, security, WooCommerce, subscriber, member, divi)
[wp-Requires at least]: <> (3.4)
[wp-Tested up to]: <> (3.4)
[wp-Requires PHP]: <> (7.2)
[wp-Stable tag]: <> (trunk)
[wp-License]: <> (GPLv3)
[wp-License URI]: <> (https://www.gnu.org/licenses/gpl-3.0.txt)
[wp-info]: <> (This Plugin will secure uploaded attachments.)

[wp-section]: <> (Description)
This Plugin will give you the option to secure your uploads. 
It is only capable of securing files located inside the wp-uploads folder currently - no S3 or other cdn functionality. 
If you wish to have content on your page witch should only be visible to e.g. subscribers or members - this plugin is exactly what you are looking for. 
Every prefabbed security-level, except >Unsecured<, will prevent direct access - even if the user who has access shares the link. 
Crawler have any luck downloading any of your precious

## Be warned
Security comes at a cost, it will add some time to each secured download/load. Every secured file will be processed for each user individually this could add up to some longer page-load times and could effect your cache-plugin. For all cloud users; it will take up some cpu time.

## Security Levels
*   Unsecured = the file will be left alone by the plugin, no restrictions are applied
*   `user` Logged in = Only users who are logged in can access this content
*   `user_{role-slug}` User-Role = There are multiple [Roles](https://wordpress.org/support/article/roles-and-capabilities/#summary-of-roles "WordPress.org") from witch you can choose e.g.: Author, Contributor and Subscriber this will mostly satisfy all "subscriber only" contents
*   Your own levels as soon as you have integrated it
*   Levels could come with your Theme or additional plugins
*   Each Level comes with its counterpart like `user_admin` (User is Admin) will check for users who are admins, `!user_admin` (User is NOT Admin) will invert the admin check

## For WP-Admin users
*   You can set up the default security level for new uploads.
*   Fallback image, video and audio as well as redirects can be set up for unauthorized access.
*   Activate/deactivate, blacklist and whitelist Shortcode attribute extension. (See "Optional Shortcode extension")
*   You will get the option to select the security level for every single upload directly.
*   You get an option to bulk edit and select multiple uploads and set the security level as well.
*   Use the shortcode or the optional shortcode attribute extension in your content.

## For Dev's 
*   The Plugin is build with Hooks and Actions.
*   Delete security levels: please make sure no attachments are using given security level
*   Add security levels: you can add as many levels as you like as easy as `Vaulty::add_level($name, $display_Name);`
*   Behavior of security levels are filter based so you can remove the filters for existing ones or add your on

## Shortcodes
*   `[vaulty sec="user"]Only visible for Users[/vaulty]`
*   `[vaulty sec="!user"]Only visible for guests where the user rule does not apply to[/vaulty]`
*   content inside will be prevented from rendering, but images or other attachments aren't secured automatically - please make sure to secure your attachments in the media section.

## Optional shortcode attribute extension
If activated, every shortcode gets the attribute vaulty="{security_level_name}" witch will be applied to every render request.
You can choose to black or whitelist shortcodes from this behavior. The vaulty shortcode itself will not be effected.
For example `[gallery vaulty="user"]` will show the gallery only to logged in users.

## PHP usage
If you like to use the Vaulty functions in your code, you can!

`<?php if(Vaulty::is("user")): ?> content only for Users <?php endif; ?>`

## Additional Features for "WooCommerce Memberships" and "WooCommerce Subscriptions"
*   There are security levels for each membership and Subscription you offer on your page. Be cautious when you change your subscription or membership model to also change the security levels you have already applied.

## Additional Features for "Divi"
*   You will find a option for each module and section to only display for certain security levels. This will give you the possibility to generates pages based on the user. E.g. A subscriber could see all premium content while the guest will see a teaser subscription info.


This plugin is distributed in the hope that it will be useful, but without any warranty; without even the implied warranty of merchantability or fitness for a particular purpose. See the GNU General Public License for more details.

# Installation
[wp-section]: <> (Installation)

## Direct installation
1. Log into your wordpress installation
1. Go into the Plugins-section (if not present stop here and ask your administrator to install this plugin for you!)
1. Click on "add New" and search for "Vaulty"
1. Click "Install now" on the correct search result.
1. If you get one - you can confirm the upcoming "are you sure" dialog
1. After the installation process is complete you should be able to click on "Activate Now"

## Download & Upload in wp-admin
1. Download the plugin .zip file
1. Log into your wordpress installation
1. Go into the Plugins-section (if not present stop here and ask your administrator to install this plugin for you!)
1. Click on "add New" and then on "Upload Plugin"
1. Select vaulty.zip for the upload and click on "Install now" (if your upload fails please ask your administrator or try the FTP/File method)
1. After the installation process is complete you should be able to click on "Activate Now"

## File copy/paste & FTP
1. Download the plugin .zip file
1. Unpack the zip file, if not already unpacked.
1. copy the vaulty folder
1. navigate to the file-system of your wordpress installation
1. paste the vaulty folder into the `wp-content/plugins/` directory of your wordpress installation
1. Log into your wordpress installation
1. Go into the Plugins-section (if not present stop here and ask your administrator to install this plugin for you!)
1. You should see Vaulty as a new plugin and you should be able to click on "Activate Now" - click it ;-)

If non of the above could help you with the install-process it could be that [this Guide](https://wordpress.org/support/article/managing-plugins/#installing-plugins "WordPress.org") is of more use.

# Frequently Asked Questions
[wp-section]: <> (Frequently Asked Questions)

## Are my images save after installation

You have to select your images and or other contents in your media section and apply the appropriate security level.

## Why are all my image/media links longer or not like before

This happened to secure your uploads. There is a special folder in your wp-uploads folder where all secured files are kept.

## Secured Images doesn't show up on my page

Please make sure that the applied logic is correct, it is easy to get tethered up in these permission cases.
Could it be that you have it inside an other content block witch isn't rendered?
Is there a surrounding shortcode/tag witch prevents the view?
Is the image in the source of the rendered page?
Could it be that you have applied multiple contradicting rules like `[vaulty sec="user"][vaulty sec="!user"]This will never, ever appear on the front side.[/vaulty][/vaulty]`

## The new links to the images wont work

This could have multiple causes:
First of all - do you have changed any file/folder structure directly? If so, could you change it back and check if that was the problem?
If you paste the link in the browser address bar, dose it show the image?
Are you a User who fulfill the requirements? For example a logged in subscriber will never see content only visible for admins.
Are unprotected images effected as well? This would hint a broken WP installation/database.
If this answer didn't help, please consult a pro of the WordPress community and show him this FAQ.

## I didn't deactivate and just deleted this Plugin - Now all images are in the wrong place =

First - I'm sorry for the incontinence and its sad to hear you don't like Vaulty.
This happened because Vaulty could not deactivate and clean after itself. You could reinstall Vaulty, activate, deactivate and uninstall.
If this isn't possible or did not restore your media collection, you would need some experience with SQL and have access to your server (like FTP and e.g. phpMyAdmin)
Basically you have to replace all assurances of ".vaulty/" in your wp_posts table and move/merge all files and folders from `/wp-uploads/.vaulty/` to `/wp-uploads`.
If you don't have a clue to do this on your own, please ask your admin, pro-friend or the nice wordpress community for help and show them this FAQ. P.S. Please don't, ever again, "uninstall" a wordpress plugin by just deleting its folder.

## Where are the settings

Please see the Screenshots

# Screenshots
[wp-section]: <> (Screenshots)

1. This shows you the general settings for Vaulty
2. Vaulty used in the media section
3. Vaulty bulk action
4. Vaulty shortcodes
5. Vaulty shortcode attribute
6. Vaulty and WooCommerce Membership/Subscription
7. Vaulty and Divi

# Under the hood
[wp-section]: <> (Under the hood)
On activation the plugin will create the folder ".vaulty" inside of wp-uploads.
All secured files will be moved to this directory (including the substructure e.g. wp-uploads/2020/01/foobar.gif will be wp-uploads/.vaulty/2020/01/foobar.gif)
The .vaulty folder is secured by an .htaccess file witch reroute all requests to a WP call. This call will be captured by Vaulty and processed, followed by the requested media or an HTTP error code + fallback.
The shortcodes and attributes (witch the Divi addon uses as well) are filtered like the media items (all checks are fulfilled by the same filters).

TODO: Long explanation and flow description


# Changelog
[wp-section]: <> (Changelog)

## 1.0
* the fist open source version


# Upgrade Notice
[wp-section]: <> (Upgrade Notice)
## 1.0
* The initial commit as a open source plugin for wordpress. born out of need, I've installed similar code on an high traffic subscription based page.
