=== Send Everything for Contact Form 7 ===

Contributors:      salzano
Tested up to:      6.4.2
Stable tag:        1.2.0
License:           GPL-2.0
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              contact form, contact form 7, email

WordPress plugin. Extension for Contact Form 7. Adds a mail-tag `[everything]` that sends all fields in the message body.


== Description ==

= Features =

* Provides `[everything]` mail tag for great-looking, send-everything emails
  * Includes all fields in emails except CAPTCHA and spam honeypot fields
  * Formats values and submission meta into tables
  * Added automatically to forms with blank "Message body" fields
* Populates mail tab message body with `[everything]` during Add New form
* Adds a `[submit]` button to any form missing one


== Changelog ==

= 1.2.0 =
* [Added] Prevent emails from breaking when mail tabs contain the [everything] mail tag and this plugin is deactivated. Replaces the tag in all mail tabs it appears with HTML that will produce a similar email body.
* [Fixed] Checks the "Use HTML content type" setting when new forms are created and mail tabs are populated with the [everything] mail tag.
* [Fixed] Adds a configuration error when the [everything] mail tag is detected in a mail tab body and the "Use HTML content type" box is not checked.

= 1.1.6 =
* [Fixed] Adds compatibility with language packs.

= 1.1.5 =
* [Fixed] Bug fix. Do not enable HTML for emails that do not contain the [everything] mail tag.
* [Changed] Updates banner art.
* [Changed] Changes tested up to version to 6.4.2.

= 1.1.4 =
* [Changed] Changes tested up to version to 6.3.0.

= 1.1.3 = 
* [Added] Adds feature list to readme.txt

= 1.1.2 =
* [Changed] Uses the form submission object to access fields rather than looping over $_POST

= 1.1.1 =
* [Added] Adds a screenshot
* [Fixed] Stops backslashes from appearing before apostrophes

= 1.1.0 =
* [Added] Populates mail tab message body with `[everything]` during Add New form

= 1.0.0 = 
* First public release

== Upgrade Notice ==

= 1.2.0 =
Prevent emails from breaking when mail tabs contain the [everything] mail tag and this plugin is deactivated. Replaces the tag in all mail tabs it appears with HTML that will produce a similar email body. Checks the "Use HTML content type" setting when new forms are created and mail tabs are populated with the [everything] mail tag. Adds a configuration error when the [everything] mail tag is detected in a mail tab body and the "Use HTML content type" box is not checked.

= 1.1.6 =
Adds compatibility with language packs.

= 1.1.5 =
Bug fix. Do not enable HTML for emails that do not contain the [everything] mail tag. Updates banner art. Changes tested up to version to 6.4.2.