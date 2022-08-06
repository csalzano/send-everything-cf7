=== Send Everything for Contact Form 7 ===

Contributors:      salzano
Tested up to:      6.0.1
Stable tag:        1.1.3
License:           GPL-2.0
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              contact form 7, send all, email

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
