<?php
defined( 'ABSPATH' ) or exit;

/**
 * Plugin Name: Contact Form 7 - Send Everything
 * Plugin URI: https://breakfastco.xyz
 * Description: Adds a mail-tag <code>[everything]</code> that sends all fields in the message body
 * Author: Corey Salzano
 * Author URI: https://breakfastco.xyz
 * Version: 1.0.0
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
	}

	/**
	 * add_tag
	 *
	 * @param  array $mailtags
	 * @return array
	 */
	public function add_tag( $mailtags = array() ) {
		$mailtags[] = self::MAIL_TAG;
		return $mailtags;
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

	public function edit_mail_components($components) {
	
		//Allow HTML in emails
		add_filter( 'wp_mail_content_type', array( $this, 'html_mail_content_type' ) );
	
		foreach ( $_POST as $k => $v ) {
			if ( substr( $k, 0, 6 ) == '_wpcf7' || strpos( $k, 'all-fields' ) || $k === '_wpnonce' ) {
				unset( $_POST["{$k}"] );
			}
		}

		$postbody = apply_filters( 'wpcf7_send_all_fields_format_before', '<h1 style="font-family:Helvetica,sans-serif;">Submitted Values</h1><table style="font-family:Helvetica,sans-serif;border:2px solid #f8f8f8;border-collapse:collapse;background:#fff;">' );

		//Add some meta data to the end of the submitted form data
		foreach ( $_POST as $k => $v ) {
	
			// Remove dupe content. The Hidden and Values are both sent.
			if ( preg_match( '/hidden\-/', $k ) ) {
				continue;
			}
	
			// If there's no value for the field, don't send it.
			if ( empty( $v ) && false === apply_filters( 'wpcf7_send_all_fields_send_empty_fields', false ) ) {
				continue;
			}

			$postbody .= $this->prepare_table_row_value( $k, $v );
		}

		$postbody .= apply_filters( 'wpcf7_send_all_fields_format_after', '</table><h2 style="font-family:Helvetica,sans-serif;">Submission Meta</h2><table style="font-family:Helvetica,sans-serif;border:2px solid #f8f8f8;border-collapse:collapse;background:#fff;">', 'html' );	
		
		//Add some meta data
		$submission = WPCF7_Submission::get_instance();
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

		$postbody .= apply_filters( 'wpcf7_send_all_fields_format_after', '</table>', 'html' );		
	
		$components['body'] = str_replace( '<p>[' . self::MAIL_TAG . ']</p>', $postbody, str_replace( '[' . self::MAIL_TAG . ']', $postbody, $components['body'] ) );

		return $components;
	}

	protected function prepare_table_row_value( $label, $value )
	{
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}

		// Make the labels easier to read. Thanks, @hitolonen
		$label = apply_filters( 'wpcf7_send_all_fields_format_key', true ) ? ucwords( str_replace( "-", " ", str_replace( "_", " ", $label ) ) ) : $label;

		// Sanitize!
		$label = esc_html( $label );
		$value = esc_html( $value );

		//Link values that are URLs
		if( filter_var( $value, FILTER_VALIDATE_URL ) )
		{
			$value = sprintf( '<a href="%1$s">%1$s</a>', $value );
		}

		return apply_filters( 'wpcf7_send_all_fields_format_item', "<tr><td style='padding:.75em .75em .5em;border:2px solid #f8f8f8;font-size:1.2em;'><font size='3'>{$label}</font></td><td style='padding:.75em .75em .5em;border:2px solid #f8f8f8;'><strong style='font-weight:bold;'>{$value}</strong></td></tr>", $label, $value, 'html' );
	}
}
$cf7_send_all_fields = new Contact_Form_7_Send_All_Fields();
$cf7_send_all_fields->add_hooks();
