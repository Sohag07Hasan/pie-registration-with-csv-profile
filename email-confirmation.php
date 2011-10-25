<?php
	//sanitizing the get datas
	 $csv = preg_replace('/[^a-z]/','',$_GET['csv']);
	 $auth = preg_replace('/[^0-9,a-z,A-Z]/','',$_GET['authtoken']);
	 $status = preg_replace('/[^a-z]/','',$_GET['status']);	 
	 
	 	 
	 if($csv=='true' && $status=='incative' && strlen($auth)==20) : 
	 
		//retrieving use information
		 global $wpdb;
		 $output = '';
		 $user = $wpdb->get_row("SELECT * FROM $wpdb->users WHERE user_activation_key = '$auth' ", ARRAY_A) ;
		 if($user) {
			 $user_id = $user['ID'];
			 $username = $user['user_login'];
			 $usermeta = get_user_meta($user_id,'csv_user_meta', true);		 
			 $password = $usermeta['pass'];
			 $status = $usermeta['status'];
			 
			 //checking the status
			if($status == 'inactive'){
				$output .= '<div class="csvuser">
					<p class="updatedmessage">Your email is verified </p>
					<h3>Here is your details</h3>
					<p><b>Username : </b>'.trim($username).'</p>
					<p><b>Password : </b>'.trim($password).'</p>
					
				</div>';
			}
			else{
				$output = '<p class="errormessage">Your email is already verified!</p>';
			}
			 
			 //deleting the activation key and changing the user status to active
			 			 
			delete_user_meta( $user_id,'csv_user_meta');
			$wpdb->update($wpdb->users, array('user_activation_key' => ''), array('ID' => $user_id) );
		 
		 echo $output;
	}
	else{
		echo '<div class="errormessage"><p> Invalid URL !</p></div>';
	}
	
	endif;
?>

