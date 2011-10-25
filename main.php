<?php

/*
Plugin Name: User creation by csv files
Plugin URI: http://hasan-sohag.blogspot.com/
Description: Allows the importation of users via an uploaded CSV file.
Author: Mahibul Hasan
Author URI: http://sohag07hasan.elance.com
*/

	$csvtouser = new wpcsvtousercreation();
	if($csvtouser){
		add_action('admin_menu',array($csvtouser,'csvuserimport_menu'));		
		add_action('login_init',array($csvtouser,'emailconfirmation'));	
		add_action('login_head',array($csvtouser,'css_add'));	
		
	}
		
	if($_POST['csv_submit'] == 'submit'){
		$csvtouser->user_creation($_POST['user']);
					
	}
	
	//class declearation
	class wpcsvtousercreation{
		
		var $html_update = '';
		var $html_error = '';
		var $html_exit = '';
		var $index = 0;
		
		//constructor function
		function __construct(){
			ini_set('auto_detect_line_endings', true);
			
			$this->index = get_option('csv_line_index');
		}
		
		//menu creation function
		function csvuserimport_menu() {	
			add_submenu_page( 'users.php', 'CSV User Import', 'Import Users', 'manage_options', 'csv-user-import',array($this,'csvuserimport_page1'));	
		}
			
		
		
		//table creation
		function tablecreation($data){
			
			$user = array('name','surname','email');			
						
			foreach($data as $d){				
				$table_b .= '<tr>';
				$user_vals = split(",", $d);
				
				if(count($user_vals) != 3) continue;	
							
				foreach($user_vals as $key=>$val){
					$table_b .= '<td><input type="text" name="user['.$user[$key].'][]" value='.trim($val).' ></td>';
				}
				
			}	
						
			return  $table_b ;			
		}
		
		//page content generation and csv creation function
		function csvuserimport_page1() {
			global $wpdb;

			if (!current_user_can('manage_options')) {
				wp_die( __('You do not have sufficient permissions to access this page.') );
			}
			
			// if the form is submitted
			if ($_POST['mode'] == "submit") {
														
				switch ($_FILES['csv_file']['type']) {
					case 'text/plain':
						$arr_rows = file($_FILES['csv_file']['tmp_name']);
						update_option( 'csv_line_index', 1);					
						break;
						
					case 'text/csv' : 
						$arr_rows = $this->csv_to_simpletxt($_FILES['csv_file']['tmp_name']);	
						update_option( 'csv_line_index', 3);				
						break;
						
					default : 
						$arr_rows = null;
						
				}
								
				// loop around
				if (is_array($arr_rows)) {					
										
					//table data creation
					?>
					
					<div class="wrap">
						<h2> Table view of CSV</h2>
						<form action="" method="post" class="form-table">
							<table class="widefat">
								<tr>
									<th><a href="#">Username</a></th>
									<th><a href="#">Surname</a></th>
									<th><a href="#">Email</a></th>
									
								</tr>
								<?php echo $this->tablecreation($arr_rows); ?>
							</table>
							<input type="hidden" name="csv_submit" value="submit" />
							<br/>
							<input class="button-primary" type="submit" value="create users" />
						</form>
					</div>
					<br/>
					<br/>
					<hr/>
					
				<?php
					
				} 
			}	// end of 'if mode is submit'
			
			?>
			<div class="wrap">	
			
				<?php echo $this->html_update . $this->html_error . $this->html_exist ; ?>	
				
				<div id="icon-users" class="icon32"><br /></div>
				<h2><?php _e("CSV User Import"); ?></h2>
				<p><?php _e(" Please select the CSV file you want to import below."); ?> </p>
				
				<form action="users.php?page=csv-user-import" method="post" enctype="multipart/form-data">
					<input type="hidden" name="mode" value="submit">
					<input type="file" name="csv_file" />		
					<input class="button-secondary" type="submit" value="Import" />
				</form>
				
				<h2> <?php _e("Instructions"); ?></h2>
				
				<p> <?php _e("The CSV (.txt) file should be in the following format:"); ?> </p>
				
				<table>
					<tr>
						<td> <?php _e("username,") ;?></td>
						<td><?php _e("surname,"); ?></td>						
						<td> <?php _e("email address"); ?></td>
					</tr>
				</table>
				<p> <?php _e("CSV (.csv) is also valid"); ?> </p>
				
				<p style="color: red"> <?php _e("Please make sure you back up your database before proceeding!"); ?></p>	
			</div>
			
			<?php	
		}
		
				
		//user creation function
		function user_creation($users){
			
			if(is_array($users['email'])){
				foreach ($users['email'] as $key=>$email){
					if(is_email($email)){
						$email_exist = $this->email_checking($email);
						if($email_exist){
							$exist_email[] = $key ;
						}
						else{
							
							//including the config file
							include 'config.php';
							
							//creating users here
							$username = $this->unique_name($users['name'][$key],$email) ;
							$nicename = ($users['surname'][$key])?$users['surname'][$key] : $username ;
							
							
							$password = $this->generate_password(PASS_STRENGTH,false,false);
							$activationkey = $this->generate_password(20,false);
												
							if ( !function_exists('wp_set_password') ) :
								$prev = getcwd();
								chdir(dirname(__FILE__));
								include('../../../wp-includes/pluggable.php') ;						
							endif;
							
							
							$hash = wp_hash_password($password);
							
							$adminmail = get_option('admin_emai');
														
							global $wpdb;
							//builin functin for creation users
							$wpdb->insert( $wpdb->users, array(
								'user_login' => $username,
								'user_nicename' => $nicename,
								'user_email' => $email,
								'user_activation_key' => $activationkey,
								'user_pass' => $hash
							),array('%s','%s','%s','%s'));
							
												
							$user_id = $wpdb->insert_id;
							
							
							
							//setting password and user meta
							//getting default role and setting it to every users
							$defaultrole = strtolower(ROLE);	
							
							//wp_set_password($password, $user_id);							
							update_user_meta($user_id,'csv_user_meta',array('pass'=>$password,'status'=>'inactive'));
							
							update_user_meta($user_id,$wpdb->prefix . 'capabilities',array($defaultrole => '1' ));
							
							$this->emailsending($email,$adminmail,$activationkey);																																							
							$success_email[] = $key;							
							 
						}
					}
					else{
						$error_email[] = $key ;
					}
					
				}
				
				
				//notification creation
				if(count($error_email)>0){
					$s = '';
					foreach($error_email as $er){
						//$e = $er + 1;
						$e = $er + $this->index;
						$s .= $e.', ';
					}
					$s = trim($s,', ');
					$this->html_error = "<div class='error'>".__("Invalid Emails ( line no ) :")." $s </div>";
				}
				
				if(count($exist_email)>0){
					$s = '';
					foreach($exist_email as $er){
						//$e = $er + 1;
						$e = $er + $this->index;
						$s .= $e.', ';
					}
					$s = trim($s,', ');
					$this->html_exist = "<div class='error'>".__("Existing Emails ( line no ) :")." $s </div>";
					
				}
				
				if(count($success_email)>0){
					$s = '';
					foreach($success_email as $er){
						//$e = $er + 1;
						$e = $er + $this->index;
						$s .= $e.', ';
					}
					$s = trim($s,', ');
					$this->html_update = "<div class='updated'>".__("Created Users (line no) :")." $s </div>";
				}
				
			}
						
		}
		
		function email_checking($email){
						
				$email = trim($email);
				global $wpdb;
				$table = $wpdb->prefix.'users' ;
				$user_id = $wpdb->get_var( "SELECT ID FROM $table WHERE user_email ='$email'" ) ;
				
				return $user_id ;
			
		}
		
		//unique name creation
		function unique_name($name,$email){	
						
			if(strlen($name)>2){
				
				$name = preg_replace('/[ ]/','',$name);
				
				if($this->name_exists($name)){
					
					$name = preg_replace('/[@]/','_',$email);
				}			
			}
			
			else{
				$name = preg_replace('/[@]/','_',$email);
			}
			
			//checking if the name is exist
			
			return $name ;						
		}
		
		//random string creation
		function randomstring(){
			$char = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','p','y','z');
			
			$str = '';
			for($i=0;$i<3;$i++){
				$randomnumber = rand(0,count($char)-1);
				$str .= $char[$randomnumber] ; 
			}
			
			return '_'.$str ;
		}
		
		//perfect name called by u nique name function
		function name_exists($name){
			
			//var_dump($name);
			global $wpdb;
			$table = $wpdb->prefix.'users' ;
			$user_id = $wpdb->get_var( "SELECT ID FROM $table WHERE user_login='$name'" ) ;
			
			//return ($user_id) ? false: true ;
			return $user_id ;
		}
		
		
		//password generator
		function generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
			$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
			if ( $special_chars )
				$chars .= '!@#$%^&*()';
			if ( $extra_special_chars )
				$chars .= '-_ []{}<>~`+=,.;:/?|';

			$password = '';
			for ( $i = 0; $i < $length; $i++ ) {
				$password .= substr($chars, rand(0, strlen($chars) - 1), 1);
			}
			
			return $password ;
			
		}
		
		
		//function to confirm email later
		function emailconfirmation(){
			require( dirname(__FILE__) . '/email-confirmation.php' );
		}
		
		
		//function email sending
		function emailsending($email,$adminmail,$activationkey){
			
			//mail headers
			$headers = 'From:'.$adminmail.' '.' '."\r\n" .
			'Reply-To: '.$adminmail.' '.' '. "\r\n";
			$subject = "Email Conformation";
			$message = "Congratulations! \n\n Please click the link to verify your email address and get your usename and password \n\n " ;
			$link = get_option('home').'/wp-login.php?csv=true&authtoken='.$activationkey.'&status=incative';
			$message .= $link;
			//mail($email,$subject,$message,$headers);
			wp_mail($email,$subject,$message,$headers);
			
		}
		
		//function for css in wp-login page
		function css_add(){
			if($_GET['csv']==true) : 			
				$url = dirname(__FILE__) ;
				preg_match('/\/wp-content.+$/',$url,$c);
				$link = get_option('home').$c[0].'/css/style.css';					
				echo "<link rel='stylesheet' href='$link' media='all' type='text/css' />" ;
			endif;
			
		}
		
		//parsing .csv data
		function csv_to_simpletxt($csv){
			
			$row = 1;
			if (($handle = fopen($csv, "r")) !== FALSE) {
				while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
					$num = count($data);
					if($num != 3) continue ;
					
					$row++;
					$parsed = '';
					for ($c=0; $c < $num; $c++) {
						$parsed .= $data[$c].',';
					}
					
					$parsed = trim($parsed,',');
					$p_array[] = $parsed;
				}
				fclose($handle);
			}
			
			return  $p_array;
		}
		
		
	} //end of the class 
	
		
	

?>
