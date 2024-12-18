<?php
/**
 * Plugin Name: Send Everything for Contact Form 7
 * Plugin URI: https://breakfastco.xyz/send-everything-for-contact-form-7/
 * Description: Provides [everything] mail tag for great-looking, send-everything emails
 * Author: Breakfast
 * Author URI: https://breakfastco.xyz
 * Version: 1.2.0
 * Text Domain: send-everything-cf7
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package send-everything-cf7
 * @author Corey Salzano <csalzano@duck.com>
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Send_Everything_For_Contact_Form_7' ) ) {
	/**
	 * Send_Everything_For_Contact_Form_7
	 */
	class Send_Everything_For_Contact_Form_7 {

		const MAIL_TAG = 'everything';

		/**
		 * Adds hooks that power the plugins features.
		 *
		 * @return void
		 */
		public function add_hooks() {
			// Add compatibility with language packs.
			add_action( 'init', array( $this, 'load_textdomain' ) );

			// Help users enable HTML when using our mail tag.
			add_action( 'wpcf7_config_validator_validate', array( $this, 'validate_mail_tabs' ) );

			// Add a validation error type to the list of recognized errors.
			add_filter( 'wpcf7_config_validator_available_error_codes', array( $this, 'validate_add_error' ) );

			// Replaces the everything mail tag with HTML when email messages are prepared.
			add_filter( 'wpcf7_mail_components', array( $this, 'edit_mail_components' ) );

			// Adds the everything mail tag to the list of recognized mail tags.
			add_filter( 'wpcf7_collect_mail_tags', array( $this, 'add_tag' ) );

			// Adds a submit button to forms missing them.
			add_filter( 'wpcf7_contact_form_properties', array( $this, 'add_submit_button' ), 10, 2 );

			/**
			 * Changes the body field of mail tabs to contain only the
			 * everything mail tag when a new form is created.
			 */
			add_filter( 'wpcf7_contact_form_default_pack', array( $this, 'change_default_mail_templates' ) );

			/**
			 * When the plugin is deactivated, do not allow emails using the
			 * everything mail tag to break. Replaces the tag in all mail tabs
			 * it appears with HTML that will produce a similar email body.
			 */
			register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

			/**
			 * When the plugin is activated, restore the everything mail tag to
			 * mail tabs that contained the tag previously.
			 */
			register_activation_hook( __FILE__, array( $this, 'activate' ) );
		}

		/**
		 * Activation hook callback. Restores the everything mail tag to mail
		 * tabs that contained the tag when the plugin was deactivated.
		 *
		 * @return void
		 */
		public static function activate() {
			if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
				return;
			}

			// phpcs:ignore WordPress.WP.Capabilities.Unknown
			if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) ) {
				// Sorry, you've got the capability to activate plugins but not edit forms.
				return;
			}

			// Get all forms.
			$forms = WPCF7_ContactForm::find(
				array(
					'per_page' => -1,
				)
			);

			$pattern_starts = '/' . preg_quote( self::html_prefix(), '/' ) . '/';
			$pattern_ends   = '/' . str_replace( '\<\/span\>\<', '\<\/span\>\r?\n\<', preg_quote( self::html_suffix(), '/' ) ) . '/';

			foreach ( $forms as $form ) {
				// phpcs:ignore WordPress.WP.Capabilities.Unknown
				if ( ! current_user_can( 'wpcf7_edit_contact_form', $form->id() ) ) {
					// The user cannot edit this form. Sorry.
					continue;
				}
				$properties = $form->get_properties();

				// Does Mail or Mail 2 have HTML generated during deactivation?
				foreach ( array( 'mail', 'mail_2' ) as $tab ) {
					preg_match_all( $pattern_starts, $properties[ $tab ]['body'], $matches_starts, PREG_OFFSET_CAPTURE );
					preg_match_all( $pattern_ends, $properties[ $tab ]['body'], $matches_ends, PREG_OFFSET_CAPTURE );

					if ( 1 === count( $matches_starts ) && array() === $matches_starts[0] ) {
						// No matches.
						continue;
					}

					// Yes. Replace the HTML with the everything mail tag.
					do {
						$match                      = $matches_starts[0][0];
						$properties[ $tab ]['body'] = substr_replace( $properties[ $tab ]['body'], '[' . self::MAIL_TAG . ']', $match[1], $matches_ends[0][0][1] - $match[1] + strlen( $matches_ends[0][0][0] ) );

						preg_match_all( $pattern_starts, $properties[ $tab ]['body'], $matches_starts, PREG_OFFSET_CAPTURE );
						preg_match_all( $pattern_ends, $properties[ $tab ]['body'], $matches_ends, PREG_OFFSET_CAPTURE );
					} while ( array() !== $matches_starts[0] );
					// Make sure the Use HTML content type setting is checked.
					$properties[ $tab ]['use_html'] = true;
				}

				$form->set_properties( $properties );
				$form->save();
			}
		}

		/**
		 * Adds [submit] form tags to forms that do not have them.
		 *
		 * @param  array             $properties Form properties.
		 * @param  WPCF7_ContactForm $form Contact form object.
		 * @return array
		 */
		public function add_submit_button( $properties, $form ) {
			if ( is_admin()
				|| ! isset( $properties['form'] )
				|| ! is_string( $properties['form'] ) ) {
				return $properties;
			}

			// Should we even do this?
			if ( false === apply_filters( 'wpcf7_send_everything_submit_button_add', true, $properties, $form ) ) {
				return $properties;
			}

			// Does the form have a [submit] form tag?
			$pattern = '/\[submit[^\]]*\]/';
			if ( 1 !== preg_match( $pattern, $properties['form'] ) ) {
				// No, add one.
				$properties['form'] .= apply_filters( 'wpcf7_send_everything_submit_button', "\n\n[submit]", $properties, $form );
			}
			return $properties;
		}

		/**
		 * Adds an [everything] mail tag.
		 *
		 * @param  array $mailtags Array of mail tag name strings.
		 * @return array
		 */
		public function add_tag( $mailtags = array() ) {
			$mailtags[] = self::MAIL_TAG;
			return $mailtags;
		}

		/**
		 * Changes the mail tab bodies to contain our tag only as forms are
		 * created.
		 *
		 * @param  WPCF7_ContactForm $contact_form Contact form object.
		 * @return WPCF7_ContactForm
		 */
		public function change_default_mail_templates( $contact_form ) {
			$properties = $contact_form->get_properties();
			if ( WPCF7_ContactFormTemplate::mail() === $properties['mail'] ) {
				$properties['mail']['body']     = '[' . self::MAIL_TAG . ']';
				$properties['mail']['use_html'] = true;
			}
			if ( WPCF7_ContactFormTemplate::mail_2() === $properties['mail_2'] ) {
				$properties['mail_2']['body']     = '[' . self::MAIL_TAG . ']';
				$properties['mail_2']['use_html'] = true;
			}
			$contact_form->set_properties( $properties );
			return $contact_form;
		}

		/**
		 * Deactivation hook callback. Converts all mail tabs that contain the
		 * everything mail tag to deliver the same email without this plugin.
		 *
		 * @return void
		 */
		public static function deactivate() {
			if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
				return;
			}

			// phpcs:ignore WordPress.WP.Capabilities.Unknown
			if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) ) {
				// Sorry, you've got the capability to deactivate plugins but not edit forms.
				return;
			}

			// Get all forms.
			$forms = WPCF7_ContactForm::find(
				array(
					'per_page' => -1,
				)
			);

			foreach ( $forms as $form ) {
				// phpcs:ignore WordPress.WP.Capabilities.Unknown
				if ( ! current_user_can( 'wpcf7_edit_contact_form', $form->id() ) ) {
					// The user cannot edit this form. Sorry.
					continue;
				}
				$properties = $form->get_properties();
				// Does Mail or Mail 2 have the everything mail tag?
				$everything_html = self::get_everything_html( $form );
				foreach ( array( 'mail', 'mail_2' ) as $tab ) {
					if ( str_contains( $properties[ $tab ]['body'] ?? '', '[' . self::MAIL_TAG . ']' ) ) {
						// Yes, replace it with HTML.
						$properties[ $tab ]['body'] = str_replace( '[' . self::MAIL_TAG . ']', $everything_html, str_replace( '<p>[' . self::MAIL_TAG . ']</p>', $everything_html, $properties[ $tab ]['body'] ) );
						// Make sure the Use HTML content type setting is checked.
						$properties[ $tab ]['use_html'] = true;
					}
				}
				$form->set_properties( $properties );
				$form->save();
			}
		}

		/**
		 * Replaces our [everything] mail tag in the body of emails.
		 *
		 * @param  array $components Array of email components with keys 'subject', 'sender', 'body', 'recipient', 'additional_headers', and 'attachments'.
		 * @return array
		 */
		public function edit_mail_components( $components ) {
			// Does this message body contain the [everything] mail tag?
			if ( ! str_contains( $components['body'] ?? '', '[' . self::MAIL_TAG . ']' ) ) {
				// No.
				return $components;
			}

			// Is HTML enabled?
			if ( ! isset( $components['use_html'] ) || ! $components['use_html'] ) {
				// Allow HTML in emails.
				add_filter( 'wp_mail_content_type', array( $this, 'html_mail_content_type' ) );
			}

			$everything_html = self::get_everything_html();

			// Is the message body empty?
			if ( '' === $components['body']
				&& true === apply_filters( 'wpcf7_send_everything_fill_empty_message_body', true ) ) {
				// Prevent a blank mail template from sending empty emails.
				$components['body'] = '[' . self::MAIL_TAG . ']';
			}

			$components['body'] = str_replace( '[' . self::MAIL_TAG . ']', $everything_html, str_replace( '<p>[' . self::MAIL_TAG . ']</p>', $everything_html, $components['body'] ) );
			return $components;
		}

		/**
		 * Returns HTML that begins what replaces the everything mail tag.
		 *
		 * @return string
		 */
		protected static function html_prefix() {
			return '<div class="send-everything-cf7">';
		}

		/**
		 * Returns HTML that ends what replaces the everything mail tag.
		 *
		 * @return string
		 */
		protected static function html_suffix() {
			return '<span class="send-everything-cf7"></span></div>';
		}

		/**
		 * Creates HTML that replaces the everything mail tag.
		 *
		 * @param  WPCF7_ContactForm $form Contact form object. If not provided, the form will be extracted from the current submission.
		 * @return string
		 */
		protected static function get_everything_html( $form = null ) {
			$css_font = apply_filters( 'wpcf7_send_everything_css_font', 'font-family:Helvetica,sans-serif;' );

			// Start building the email body HTML in $html.
			$html  = self::html_prefix();
			$html .= apply_filters(
				'wpcf7_send_everything_title',
				"<h1 style='{$css_font}'>" . __( 'Submitted Values', 'send-everything-cf7' ) . '</h1>'
			);

			$table_open_html = apply_filters(
				'wpcf7_send_everything_table_open',
				"<table style='{$css_font}border:2px solid #f8f8f8;border-collapse:collapse;background:#fff;'>"
			);
			$html           .= $table_open_html;

			// Holds keys & values of the table rows.
			$post_data = array();
			// Holds the current submission.
			$submission = null;

			// Add the fields. Was a form passed?
			if ( null === $form ) {
				// No, use the current submission to get $_POST data.
				$submission = WPCF7_Submission::get_instance();
				$post_data  = $submission->get_posted_data();
				// Discard some fields.
				foreach ( $post_data as $k => $v ) {
					if ( 'h-captcha-response' === $k
						|| 'g-recaptcha-response' === $k ) {
						unset( $post_data[ "{$k}" ] );
					}
				}
			} else {
				// Yes, a form was passed. Extract all the tags.
				foreach ( array_column( $form->scan_form_tags(), 'name' ) as $name ) {
					if ( '' === $name ) {
						continue;
					}
					$post_data[ $name ] = '[' . $name . ']';
				}
			}

			$ignored_form_tags = apply_filters(
				'wpcf7_send_everything_ignored_form_tags',
				array(
					'honeypot', // Honeypot for Contact Form 7 by Nocean.
				)
			);

			foreach ( $post_data as $k => $v ) {

				// Remove dupe content. The Hidden and Values are both sent.
				if ( preg_match( '/hidden\-/', $k ) ) {
					continue;
				}

				// If we're processing the current submission and there's no value for the field, don't send it.
				if ( null === $form && empty( $v ) && false === apply_filters( 'wpcf7_send_everything_empty_fields', true ) ) {
					continue;
				}

				// Is this an ignored form-tag type?
				if ( in_array( self::get_form_tag_basetype( $submission, $k ), $ignored_form_tags, true ) ) {
					continue;
				}

				$html .= self::prepare_table_row_value( $k, $v );
			}

			$html .= apply_filters( 'wpcf7_send_everything_table_close', '</table>' );
			$html .= apply_filters(
				'wpcf7_send_everything_title_meta',
				"<h2 style='{$css_font}'>" . __( 'Submission Meta', 'send-everything-cf7' ) . '</h2>'
			);

			$html .= $table_open_html;

			// Add some meta data.
			if ( is_callable( array( $submission, 'get_contact_form' ) ) ) {
				$form = $submission->get_contact_form();
			}

			// Form title.
			if ( is_callable( array( $form, 'title' ) ) ) {
				$html .= self::prepare_table_row_value( 'form_title', $form->title() );
			}
			// Form ID.
			if ( is_callable( array( $form, 'id' ) ) ) {
				$html .= self::prepare_table_row_value( 'form_id', $form->id() );
			}

			if ( is_callable( array( $submission, 'get_meta' ) ) ) {
				// Form URL.
				$url = $submission->get_meta( 'url' );
				if ( $url ) {
					$html .= self::prepare_table_row_value( 'form_url', esc_url( $url ) );
				}
				// User name.
				$user_id = (int) $submission->get_meta( 'current_user_id' );
				if ( $user_id ) {
					$user = new WP_User( $user_id );
					if ( $user->has_prop( 'user_login' ) ) {
						$html .= self::prepare_table_row_value( 'user_login', $user->get( 'user_login' ) );
					}
				}

				// Time & date.
				$timestamp = $submission->get_meta( 'timestamp' );
				if ( $timestamp ) {
					// Date.
					$html .= self::prepare_table_row_value( 'date', wp_date( get_option( 'date_format' ), $timestamp ) );

					// Time.
					$html .= self::prepare_table_row_value( 'time', wp_date( get_option( 'time_format' ), $timestamp ) );
				}
			} else {
				// Form URL.
				$html .= self::prepare_table_row_value( 'form_url', '[_url]' );
				// User name.
				$html .= self::prepare_table_row_value( 'user_login', '[_user_login]' );
				// Date.
				$html .= self::prepare_table_row_value( 'date', '[_date]' );
				// Time.
				$html .= self::prepare_table_row_value( 'time', '[_time]' );
			}

			$html .= apply_filters( 'wpcf7_send_everything_table_close', '</table>' )
				. self::html_suffix();

			// If we passed a form, pretty print HTML with tidy if it is available.
			if ( null !== $form && function_exists( 'tidy_parse_string' ) ) {
				$html = tidy_parse_string(
					$html,
					array(
						'drop-empty-elements' => false,
						'indent'              => true,
						'show-body-only'      => true,
					),
					'utf8'
				);
			}

			return $html;
		}

		/**
		 * Given a tag name, find the form tag in the form object and return its
		 * basetype.
		 *
		 * @param  WPCF7_Submission $submission Submission object.
		 * @param  string           $tag Form tag name.
		 * @return string
		 */
		protected static function get_form_tag_basetype( $submission, $tag ) {
			if ( is_callable( array( $submission, 'get_contact_form' ) )
				&& is_callable( array( $submission->get_contact_form(), 'scan_form_tags' ) ) ) {
				foreach ( $submission->get_contact_form()->scan_form_tags() as $form_tag ) {
					if ( $form_tag->name !== $tag ) {
						continue;
					}
					return $form_tag->basetype ?? '';
				}
			}
			return '';
		}

		/**
		 * Returns the string 'text/html'
		 *
		 * @return string
		 */
		public static function html_mail_content_type() {
			return 'text/html';
		}

		/**
		 * Loads translated strings.
		 *
		 * @return void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'send-everything-cf7', false, __DIR__ . '/languages' );
		}

		/**
		 * Creates an HTML table row string containing the given label and value.
		 *
		 * @param  string $label Field name.
		 * @param  string $value Field value.
		 * @return string
		 */
		protected static function prepare_table_row_value( $label, $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			// Make the labels easier to read. Thanks, @hitolonen.
			$label = true === apply_filters( 'wpcf7_send_everything_format_labels', true, $label, $value ) ? ucwords( str_replace( '-', ' ', str_replace( '_', ' ', $label ) ) ) : $label;

			$label = apply_filters( 'wpcf7_send_everything_label', $label, $value );

			// Sanitize!
			$label = esc_html( $label );
			$value = esc_html( stripslashes( $value ) );

			// Link values that are URLs.
			if ( true === apply_filters( 'wpcf7_send_everything_link_urls', true, $label, $value )
				&& filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$value = sprintf( '<a href="%1$s">%1$s</a>', $value );
			}

			$css_table_cell = 'padding:.75em .75em .5em;border:2px solid #f8f8f8;';

			return apply_filters(
				'wpcf7_send_everything_table_row',
				"<tr><td style='{$css_table_cell}font-size:1.2em;'>"
					. "<font size='3'>{$label}</font></td>"
					. "<td style='{$css_table_cell}'>"
					. "<strong style='font-weight:bold;'>{$value}</strong></td></tr>",
				$label,
				$value,
				$css_table_cell
			);
		}

		/**
		 * Add a validation error type to the list of recognized errors.
		 *
		 * @param  array $errors An array of error slug strings.
		 * @return array
		 */
		public function validate_add_error( $errors ) {
			$errors[] = 'need_html';
			return $errors;
		}

		/**
		 * Help users enable HTML when using our mail tag. If a mail tab
		 * contains the everything mail tab but the "Use HTML content type"
		 * checkbox is not checked, add a configuration error.
		 *
		 * @param  WPCF7_ConfigValidator $validator Configuration validator.
		 * @return WPCF7_ConfigValidator
		 */
		public function validate_mail_tabs( $validator ) {
			$form         = $validator->contact_form();
			$properties   = $form->get_properties();
			$error_detail = array(
				'message' => __( 'The [everything] mail tag requires HTML. Check "Use HTML content type" below.', 'send-everything-cf7' ),
				'link'    => 'https://breakfastco.xyz/send-everything-for-contact-form-7/',
			);
			// Does either mail tab contain our mail tag? Is HTML enabled?
			$tabs = array(
				'mail',
				'mail_2',
			);
			foreach ( $tabs as $tab ) {
				if ( str_contains( $properties[ $tab ]['body'] ?? '', '[' . self::MAIL_TAG . ']' )
					&& ! $properties[ $tab ]['use_html'] ) {
					$validator->add_error(
						$tab . '.body',
						'need_html',
						$error_detail
					);
				}
			}
			return $validator;
		}
	}
	$send_everything_cf7_002390402384 = new Send_Everything_For_Contact_Form_7();
	$send_everything_cf7_002390402384->add_hooks();
}
