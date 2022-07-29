=== Contact Form 7 - Send Everything ===

Contributors:      salzano
Tested up to:      6.0.1
Stable tag:        1.0.1
License:           GPL-2.0
License URI:       https://www.gnu.org/licenses/gpl-2.0.html


== Hooks ==

* `wpcf7_send_everything_empty_fields` Exclude empty fields, boolean
* `wpcf7_send_everything_ignored_form_tags` Exclude form tags of any type
* `wpcf7_send_everything_title` Change the title at the top of the email body, string
* `wpcf7_send_everything_title_meta` Change the title of the second metadata table, string
* `wpcf7_send_everything_css_font` Change the font-family, string
* `wpcf7_send_everything_table_open` Edit the <table> table open tag, string
* `wpcf7_send_everything_table_close` Edit the </table> table close tag, string
* `wpcf7_send_everything_table_row` Change <tr> table row elements, string
* `wpcf7_send_everything_format_labels` Disable title-case field labels, boolean
* `wpcf7_send_everything_label` Change field label text, string
* `wpcf7_send_everything_link_urls` Disable linked URLs, boolean


== Changelog ==

= 1.0.1 = 
* [Added] Adds this readme
* [Changed] Stops including hCaptha responses, Google reCaptcha responses, _wpnonce, and _wp_http_referer fields in emails.

= 1.0.0 =
* First version that works
