<?php		/*	*	AJAX Form Submission Processing	*	Begin below young grasshopper	*/								// parse our form data			parse_str( $_POST['form_data'], $data );			// store the form ID to use in our hooks and filters			$form = $_POST['form_id'];			// decode our submission settings			$submission_settings = json_decode( stripslashes( $_POST['submission_settings'] ) , true );			// decode our optin settings			$optin_settings = json_decode( stripslashes( $_POST['optin_settings'] ) , true );			// decode our error messages			$error_messages = json_decode( stripslashes( $_POST['error_messages'] ) , true );			/** Submit Process **/			$notifications = json_decode( stripslashes( $_POST['notifications'] ) , true );			// Empty array to build up merge variables			$merge_variables = array();						// set variable						$error = 0;						// Check reCaptcha Response			if( get_option( 'yikes-mailchimp-recaptcha-status' , '' ) == '1' ) {				$url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . get_option( 'yikes-mailchimp-recaptcha-secret-key' , '' ) . '&response=' . $data['g-recaptcha-response'] . '&remoteip=' . $_SERVER["REMOTE_ADDR"];				$response = wp_remote_get( $url );				$response_body = json_decode( $response['body'] , true );				// if we've hit an error, lets return the error!				if( $response_body['success'] != 1 ) {					$error_messages = array(); // empty array to store error messages					foreach( $response_body['erorr-codes'] as $error_code ) {						$error_messages[] = $error_code;					}					?>					<p><?php _e( "It looks like we've run into a reCaptcha error. Please refresh the page and try again." , $text_domain ); ?></p>					<p><?php echo __( 'Errors' , $text_domain ) . ': ' . implode( ' ' , $error_messages ); ?></p>					<?php					$error = 1;				}			}						// loop to push variables to our array				// to do - loop over each file type to built the correct type of array			foreach ( $data as $merge_tag => $value ) {				if( $merge_tag != 'yikes_easy_mc_new_subscriber' && $merge_tag != '_wp_http_referer' ) {					if( is_numeric( $merge_tag ) ) { // this is is an interest group!						$merge_variables['groupings'][] = array( 'id' => $merge_tag , 'groups' => $value );					} else { // or else it's just a standard merge variable						$merge_variables[$merge_tag] = $value;					}				}			}			// store the opt-in time			$merge_variables['optin_time'] = strtotime( date( get_option( 'date_format' ) , strtotime( 'now' ) ) );						// Submit our form data			$api_key = get_option( 'yikes-mc-api-key' , '' );			// initialize MailChimp API			$MailChimp = new MailChimp( $api_key );			// submit the request & data, using the form settings			try {								$subscribe_response = $MailChimp->call('/lists/subscribe', array( 					'api_key' => $api_key,					'id' => $_POST['list_id'],					'email' => array( 'email' => sanitize_email( $data['EMAIL'] ) ),					'merge_vars' => $merge_variables,					'double_optin' => $optin_settings['optin'],					'update_existing' => $optin_settings['update_existing_user'],					'send_welcome' => $optin_settings['send_welcome_email']				) );								// set the global variable to 1, to trigger a successful submission				$form_submitted = 1;				/*				*	Successful form submission redirect				*/				if( $submission_settings['redirect_on_submission'] == '1' ) {					$redirection = '1';					$redirect = '<script type="text/javascript">setTimeout(function() { window.location="' . get_permalink( $submission_settings['redirect_page'] ) . '"; }, ' . apply_filters( 'yikes-easy-mc-redirect-timer' , 1500 ) . ');</script>';				}								// send our notifications if setup (must go before wp_send_json())				do_action( 'yikes-inc-easy-mc-post-submission' , sanitize_email( $data['EMAIL'] ) , $merge_variables , $form , $notifications );				do_action( 'yikes-inc-easy-mc-post-submission-' . $form , sanitize_email( $data['EMAIL'] ) , $merge_variables , $form , $notifications );								wp_send_json( 					array( 						'hide' => $submission_settings['hide_form_post_signup'], 						'error' => $error, 						'response' => !empty( $error_messages['success'] ) ? $error_messages['success'] : __( "Thank you for subscribing!" , $this->text_domain ), 						'redirection' => isset( $redirection ) ? '1' : '0', 						'redirect' => isset( $redirect ) ? $redirect : '',					) 				);													// end successful submission							} catch ( Exception $error ) { // Something went wrong...				$error_response = $error->getMessage();				$error = 1;				if( get_option( 'yikes-mc-debug' , 0 ) == 1 ) {					wp_send_json( array( 'hide' => '0', 'error' => $error , 'response' => $error_response ) );				} else {					if ( strpos( $error_response, 'should include an email' ) !== false ) {  // include a valid email please						wp_send_json( array( 'hide' => '0', 'error' => $error , 'response' => !empty( $error_messages['invalid-email'] ) ? $error_messages['invalid-email'] :  __( 'Please enter a valid email address.' , $this->text_domain ) ) );					} else if ( strpos( $error_response, 'already subscribed' ) !== false ) { // user already subscribed						wp_send_json( array( 'hide' => '0', 'error' => $error , 'response' => !empty( $error_messages['email-already-subscribed'] ) ? $error_messages['email-already-subscribed'] : __( "It looks like you're already subscribed to this list." , $this->text_domain ) ) );					} else { // general error						wp_send_json( array( 'hide' => '0', 'error' => $error , 'response' => !empty( $error_messages['general-error'] ) ? $error_messages['general-error'] : __( "Whoops, something went wrong! Please try again." , $this->text_domain ) ) );					}				}			}