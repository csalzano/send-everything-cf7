<?php
defined( 'ABSPATH' ) or exit;

/**
 * Plugin Name: Send Everything for Contact Form 7
 * Plugin URI: https://github.com/csalzano/send-everything-cf7
 * Description: Provides [everything] mail tag for great-looking, send-everything emails 
 * Author: Breakfast Co
 * Author URI: https://breakfastco.xyz
 * Version: 1.1.3
 * Text Domain: send-everything-cf7
 * Domain Path: languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if( ! class_exists( 'Send_Everything_For_Contact_Form_7' ) )
{
	class Send_Everything_For_Contact_Form_7
	{
		const MAIL_TAG = 'everything';

		public function add_hooks()
		{
			add_filter( 'wpcf7_mail_components', array( $this, 'edit_mail_components' ) );
			add_filter( 'wpcf7_collect_mail_tags', array( $this, 'add_tag' ) );
			add_filter( 'wpcf7_contact_form_properties', array( $this, 'add_submit_button' ), 10, 2 );
			add_filter( 'wpcf7_contact_form_default_pack', array( $this, 'change_default_mail_templates' ), 10, 2 );
		}

		/**
		 * add_submit_button
		 *
		 * Adds [submit] form tags to forms that do not have them.
		 *
		 * @param  array $properties
		 * @param  WPCF7_ContactForm $form
		 * @return array
		 */
		public function add_submit_button( $properties, $form )
		{
			if( is_admin()
				|| ! isset( $properties['form'] )
				|| ! is_string( $properties['form'] ) )
			{
				return $properties;
			}

			//Should we even do this?
			if( false === apply_filters( 'wpcf7_send_everything_submit_button_add', true, $properties, $form ) )
			{
				return $properties;
			}

			//Does the form have a [submit] form tag?
			$pattern = '/\[submit[^\]]*\]/';
			if( 1 !== preg_match( $pattern, $properties['form'] ) )
			{
				//No, add one
				$properties['form'] .= apply_filters( 'wpcf7_send_everything_submit_button', "\n\n[submit]", $properties, $form );
			}
			return $properties;
		}

		/**
		 * add_tag
		 *
		 * Adds an [everything] mail tag.
		 *
		 * @param  array $mailtags
		 * @return array
		 */
		public function add_tag( $mailtags = array() ) {
			$mailtags[] = self::MAIL_TAG;
			return $mailtags;
		}

		/**
		 * change_default_mail_templates
		 *
		 * @param  WPCF7_ContactForm $contact_form
		 * @param  array $args
		 * @return WPCF7_ContactForm
		 */
		public function change_default_mail_templates( $contact_form, $args )
		{
			$properties = $contact_form->get_properties();
			if( $properties['mail'] == WPCF7_ContactFormTemplate::mail() )
			{
				$properties['mail']['body'] = '[' . self::MAIL_TAG . ']';
			}
			if( $properties['mail_2'] == WPCF7_ContactFormTemplate::mail_2() )
			{
				$properties['mail_2']['body'] = '[' . self::MAIL_TAG . ']';
			}
			$contact_form->set_properties( $properties );
			return $contact_form;
		}

		public function edit_mail_components($components) {

			//Allow HTML in emails
			add_filter( 'wp_mail_content_type', array( $this, 'html_mail_content_type' ) );

			$submission = WPCF7_Submission::get_instance();
			$post_data = $submission->get_posted_data();
			//Discard some fields
			foreach ( $post_data as $k => $v ) {
				if ( $k === 'h-captcha-response'
					|| $k === 'g-recaptcha-response' )
				{
					unset( $post_data["{$k}"] );
				}
			}

			$css_font = apply_filters( 'wpcf7_send_everything_css_font', 'font-family:Helvetica,sans-serif;' );

			//Start building the email body HTML in $postbody
			$postbody = apply_filters(
				'wpcf7_send_everything_title',
				"<h1 style='{$css_font}'>" . __( 'Submitted Values', 'send-everything-cf7' ) . "</h1>"
			);

			$table_open_html = apply_filters(
				'wpcf7_send_everything_table_open',
				"<table style='{$css_font}border:2px solid #f8f8f8;border-collapse:collapse;background:#fff;'>"
			);
			$postbody .= $table_open_html;

			$ignored_form_tags = apply_filters( 'wpcf7_send_everything_ignored_form_tags', array(
				'honeypot', //Honeypot for Contact Form 7 by Nocean
			) );

			//Add the fields
			foreach ( $post_data as $k => $v ) {

				// Remove dupe content. The Hidden and Values are both sent.
				if ( preg_match( '/hidden\-/', $k ) ) {
					continue;
				}

				// If there's no value for the field, don't send it.
				if ( empty( $v ) && false === apply_filters( 'wpcf7_send_everything_empty_fields', true ) ) {
					continue;
				}

				//Is this an ignored form-tag type?
				if ( in_array( $this->get_form_tag( $submission, $k ), $ignored_form_tags ) ) {
					continue;
				}

				$postbody .= $this->prepare_table_row_value( $k, $v );
			}

			$postbody .= apply_filters( 'wpcf7_send_everything_table_close', '</table>' );

			$postbody .= apply_filters(
				'wpcf7_send_everything_title_meta',
				"<h2 style='{$css_font}'>" . __( 'Submission Meta', 'send-everything-cf7' ) . "</h2>"
			);

			$postbody .= $table_open_html;

			//Add some meta data
			if( is_callable( array( $submission, 'get_contact_form' ) ) ) {
				$form = $submission->get_contact_form();
				//Form title
				if( is_callable( array( $form, 'title' ) ) ) {
					$postbody .= $this->prepare_table_row_value( 'form_title', $form->title() );
				}
				//Form ID
				if( is_callable( array( $form, 'id' ) ) ) {
					$postbody .= $this->prepare_table_row_value( 'form_id', $form->id() );
				}
			}

			if ( is_callable( array( $submission, 'get_meta' ) ) )
			{
				//Form URL
				if( $url = $submission->get_meta( 'url' ) ) {
					$postbody .= $this->prepare_table_row_value( 'form_url', esc_url( $url ) );
				}
				//User name
				if( $user_id = (int) $submission->get_meta( 'current_user_id' ) ) {
					$user = new WP_User( $user_id );
					if ( $user->has_prop( 'user_login' ) ) {
						$postbody .= $this->prepare_table_row_value( 'user_login', $user->get( 'user_login' ) );
					}
				}

				if ( $timestamp = $submission->get_meta( 'timestamp' ) ) {
					//Date
					$postbody .= $this->prepare_table_row_value( 'date', wp_date( get_option( 'date_format' ), $timestamp ) );

					//Time
					$postbody .= $this->prepare_table_row_value( 'time', wp_date( get_option( 'time_format' ), $timestamp ) );
				}
			}

			$postbody .= apply_filters( 'wpcf7_send_everything_table_close', '</table>' );

			//Is the message body empty?
			if( '' == $components['body']
				&& true === apply_filters( 'wpcf7_send_everything_fill_empty_message_body', true ) )
			{
				//Prevent a blank mail template from sending empty emails
				$components['body'] = '[everything]';
			}

			$components['body'] = str_replace( '[' . self::MAIL_TAG . ']', $postbody, str_replace( '<p>[' . self::MAIL_TAG . ']</p>', $postbody, $components['body'] ) );

			return $components;
		}

		protected function get_form_tag( $submission, $tag )
		{
			if( is_callable( array( $submission, 'get_contact_form' ) )
				&& is_callable( array( $submission->get_contact_form(), 'scan_form_tags' ) ) )
			{
				foreach( $submission->get_contact_form()->scan_form_tags() as $form_tag )
				{
					if( $form_tag->name != $tag )
					{
						continue;
					}
					return $form_tag->basetype ?? '';
				}
			}
			return '';
		}

		/**
		 * html_mail_content_type
		 * 
		 * Returns the string 'text/html'
		 *
		 * @param  string $type
		 * @return string
		 */
		public static function html_mail_content_type( $type ) {
			return 'text/html';
		}

		protected function prepare_table_row_value( $label, $value )
		{
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			// Make the labels easier to read. Thanks, @hitolonen
			$label = true === apply_filters( 'wpcf7_send_everything_format_labels', true, $label, $value ) ? ucwords( str_replace( "-", " ", str_replace( "_", " ", $label ) ) ) : $label;

			$label = apply_filters( 'wpcf7_send_everything_label', $label, $value );

			// Sanitize!
			$label = esc_html( $label );
			$value = esc_html( stripslashes( $value ) );

			//Link values that are URLs
			if( true === apply_filters( 'wpcf7_send_everything_link_urls', true, $label, $value )
				&& filter_var( $value, FILTER_VALIDATE_URL ) )
			{
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
	}
	$send_everything_cf7_002390402384 = new Send_Everything_For_Contact_Form_7();
	$send_everything_cf7_002390402384->add_hooks();
}
