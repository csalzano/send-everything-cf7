<?php
defined( 'ABSPATH' ) or exit;

/**
 * Plugin Name: Contact Form 7 - Send Everything
 * Plugin URI: https://breakfastco.xyz
 * Description: Adds a mail-tag <code>[everything]</code> that sends all fields in the message body
 * Author: Breakfast Co
 * Author URI: https://github.com/csalzano
 * Version: 1.2.0
 * Text Domain: cf7-send-everything
 * Domain Path: languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

class Contact_Form_7_Send_All_Fields
{
	const MAIL_TAG = 'everything';

	public function add_hooks()
	{
		add_filter( 'wpcf7_mail_components', array( $this, 'edit_mail_components' ) );
		add_filter( 'wpcf7_collect_mail_tags', array( $this, 'add_tag' ) );
		add_filter( 'wpcf7_contact_form_properties', array( $this, 'add_submit_button' ), 10, 2 );
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
		if( false === apply_filters( 'wpcf7_send_everything_add_submit_buttons', true, $properties, $form ) )
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

	public function edit_mail_components($components) {
	
		//Allow HTML in emails
		add_filter( 'wp_mail_content_type', array( $this, 'html_mail_content_type' ) );

		//We are going to edit the array, so make a copy of $_POST
		$post_data = $_POST;
		//Discard some fields
		foreach ( $post_data as $k => $v ) {
			if ( ( 6 <= strlen( $k ) && substr( $k, 0, 6 ) == '_wpcf7' )
				|| $k === '_wpnonce'
				|| $k === '_wp_http_referer'
				|| $k === 'h-captcha-response'
				|| $k === 'g-recaptcha-response' )
			{
				unset( $post_data["{$k}"] );
			}
		}

		$css_font = apply_filters( 'wpcf7_send_everything_css_font', 'font-family:Helvetica,sans-serif;' );

		//Start building the email body HTML in $postbody
		$postbody = apply_filters(
			'wpcf7_send_everything_title',
			"<h1 style='{$css_font}'>" . __( 'Submitted Values', 'cf7-send-everything' ) . "</h1>"
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
		$submission = WPCF7_Submission::get_instance();
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
			"<h2 style='{$css_font}'>" . __( 'Submission Meta', 'cf7-send-everything' ) . "</h2>"
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

		$components['body'] = str_replace( '<p>[' . self::MAIL_TAG . ']</p>', $postbody, str_replace( '[' . self::MAIL_TAG . ']', $postbody, $components['body'] ) );

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
		$value = esc_html( $value );

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
$cf7_send_all_fields = new Contact_Form_7_Send_All_Fields();
$cf7_send_all_fields->add_hooks();
