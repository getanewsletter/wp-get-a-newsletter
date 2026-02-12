=== Get a Newsletter ===
Tags: newsletter, forms, popup, email marketing, mailing list
Requires at least: 5.2.0
Stable tag: 4.1.0
Tested up to: 6.9.1
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Turn visitors into subscribers. Eliminate manual entry of subscribers with signup forms that sync directly with your Get a Newsletter account.

== Description ==

Get a Newsletter's WordPress plugin makes it easy to grow your email list with beautiful subscription forms. Create and customize forms directly in WordPress, then add them anywhere on your site using the block editor, widgets, or shortcodes.

Whether you want a simple newsletter signup in your sidebar or a customized form in your content, this plugin has you covered. Design forms that match your brand with custom colors and styles, and use popup forms to capture visitors' attention at the right moment.

Plugin features:

* Create and manage newsletter forms directly in WordPress
* Add forms using the block editor (Gutenberg)
* Customize form appearance with colors and styles
* Add forms using widgets in sidebars or footer
* Add forms using shortcodes in posts or pages
* Support for both embedded and popup forms
* Multiple forms can be used on the same page
* Available in English and Swedish
* Easy setup with step-by-step guide
* Seamless integration with Get a Newsletter

Website: https://www.getanewsletter.com

== Installation ==

=== Install the plugin ===

1. Log in to your WordPress site
2. Go to "Plugins" > "Add New"
3. Search for "getanewsletter"
4. Click "Install Now"
5. Click "Activate"

=== Connect your account ===

1. Click on "Get a Newsletter" in the WordPress sidebar menu
2. Log in to your Get a Newsletter account at https://app.getanewsletter.com
   (Don't have an account? Sign up at https://app.getanewsletter.com)
3. Go to "My Account" > "API" and create a new API key
4. Copy the API key
5. Return to WordPress and paste the API key in the settings
6. Click "Continue" to complete the connection

After connecting your account, you can:
* Create forms in the Forms section
* Add forms to posts and pages using the block editor
* Add forms to your sidebars and footer using widgets
* Enable popup forms in the Settings section

Need help? Visit our support center at https://support.getanewsletter.com

== Frequently Asked Questions ==

= Do I need a Get a Newsletter account? =

Yes, you need an account to use this plugin. You can sign up at https://app.getanewsletter.com

= Can I use multiple forms on the same page? =

Yes, with version 4.0.0 you can use multiple forms on the same page, each with their own unique design.

= How do I style my forms? =

Forms can be styled using the built-in customization options in the block editor, including colors, borders, and spacing.

= How do I enable popup forms? =

Popup forms can be enabled in the Settings section of the plugin. Once enabled, you can manage your popup forms from your Get a Newsletter account.

== Changelog ==

= 4.1.0 =
* Fixed critical errors on shortcode rendering despite valid API key
* Fixed pagination for accounts with many subscription lists or senders
* Added caching for failed API authentication to improve performance
* Added admin email notifications when API connection fails
* Improved settings page with better error messaging and troubleshooting steps
* Added loading state for form submission buttons

= 4.0.0 =
* Added Gutenberg block support with visual customization options
* Added support for multiple forms on the same page
* Added Swedish translation and improved overall translation support
* Added new guide page with step-by-step instructions
* Improved form management with better editing interface
* Enhanced form styling with customizable colors and appearance

= 3.3.0 =
* Updated the design for the Settings-page
* Added an option to enable popup forms from the settings page
* Added a support page with system info to easier get help

= 3.2.0 =
* Completely re-made the onboarding process for users that install and activate the plugin for the first time

= 3.1.0 =
* Added custom scripts and styles for the settings pages
* Form errors and success notices now appear as expected
* Form errors now utilize transients instead of the PHP sessions
* Cosmetic changes to the settings pages

= 3.0.5 =
* Fixed bug caused by changed API for choosing sender when creating a subscription form

= 3.0.4 =
* Updated documentation and tested on latest WordPress version

= 3.0.3 =
* Fix for WP Site Health warning (Active PHP session detected)

= 3.0.2 =
* Add fallback to http if https API request fail

= 3.0.1 =
* Removed use of short_open_tag

= 3.0.0 =
* PHP 7.2 and WordPress 5.2 fixes
* Making possible to create/edit subscription forms in WordPress admin
* Added shortcode for subscription forms
* Some minor enhancements

= 2.0.5 =
* Saving widget fixes

= 2.0.4 =
* PHP 5.3 fixes

= 2.0.2 =
* Added Swedish translation

= 2.0.0 =
* Beautiful handling of upgrading old installations
* Convert existing widgets and create a matching subscription form

For more information about subscription forms, visit https://support.getanewsletter.com

= 1.9.1 =
* Bugfixes

= 1.9.0 =
* Available in WordPress public repository

== Upgrade Notice ==

= 4.1.0 =
Bug fixes and performance improvements. Fixes critical errors on shortcode rendering and adds better error handling for API connection issues.

= 4.0.0 =
Major update with Gutenberg block support, improved form styling, and Swedish translation. Requires WordPress 5.2 or higher.

== Screenshots ==

1. Posts and pages - Add newsletter forms using the Gutenberg block editor
2. Sidebars and footers - Place forms in widget areas using the WordPress widget system
3. Popup forms - Enable popup forms from the plugin settings page