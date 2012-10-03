<?php

/** -------------------------------------
 * 	Ported to ExpressionEngine 2.x by Yuri Salimovskiy
 *  www.intoEEtive.com
 * 	Original copyright notice below
/** -------------------------------------*/

/** -------------------------------------
/** Encaf FreeChimp
/** Copyright 2011 Encaffeinated, Inc.
/** www.encaffeinated.com
/** Version 1.1 - 1/26/2011
/** -------------------------------------
/** Version History
/** 1.1 - 1/26/2011 - added auto email to admin on subscribe fail
/** 1.0 - 1/25/2011 - initial release
/** -------------------------------------*/


class Freechimp_ext {

	/** -------------------------------------
	/** Settings
	/** -------------------------------------*/

	var $settings       = array();
	var $name           = 'FreeChimp2';
	var $version        = '1.0';
	var $description    = 'Subscribe a user to MailChimp on FreeForm Submit';
	var $settings_exist = 'y';
	var $docs_url       = 'http://devot-ee.com/add-ons/encaf-freechimp/';


	/** -------------------------------------
	/** Constructor
	/** -------------------------------------*/
	function __construct($settings = '')
	{
		$this->EE =& get_instance();
        $this->settings = $settings;
	}


    // --------------------------------
    //  Settings
    // --------------------------------  

    function settings()
    {
        $settings = array();

        $settings['apikey']     = "";
        $settings['listid']     = "";
		$settings['adminemail']	= "";

        return $settings;
    }
    


	/** -------------------------------------
	/** Activate
	/** -------------------------------------*/

	function activate_extension() {

		$DB = $this->EE->db;

		$DB->query($DB->insert_string('exp_extensions',
			array(
				'extension_id' => '',
				'class'        => "Freechimp_ext",
				'method'       => "subscribe",
				'hook'         => "freeform_module_insert_end",
				'settings'     => "",
				'priority'     => 10,
				'version'      => $this->version,
				'enabled'      => "y"
				)
			)
		);
	}




	/** -------------------------------------
	/** Update Extension
	/** -------------------------------------*/
	
	function update_extension($current='') {

		$DB = $this->EE->db;

		if ($current == '' OR $current == $this->version) {
			return FALSE;
		}
	
		$DB->query("UPDATE exp_extensions SET version = '".$DB->escape_str($this->version)."' WHERE class = 'Freechimp'");

	}



	/** -------------------------------------
	/** Disable
	/** -------------------------------------*/
	
	function disable_extension() {

	    $DB = $this->EE->db;

	    $DB->query("DELETE FROM exp_extensions WHERE class = 'Freechimp_ext'");
	}

	
	/** -------------------------------------
	/** Subscribe the User
	/** -------------------------------------*/

	function subscribe($fields,$entry_id) {
		
		$DB = $this->EE->db;
        
        //make sure we have an entry_id from freeform
        if (!$entry_id || $entry_id == '') {
            return false;
        }
        
        //get the email address and checkbox value
        $results = $DB->query("SELECT first_name, last_name, email, newsletter_subscribe FROM exp_freeform_entries where entry_id = '$entry_id' limit 1");

        if ($results->num_rows == 0) {
            return false;
        }
        
        foreach($results->result as $row) {
            $first      = $row['first_name'];
            $last       = $row['last_name'];
            $email      = $row['email'];
            $subscribe  = $row['newsletter_subscribe'];
        }
        
        //check subscribe status
        if (!$subscribe || $subscribe != 'y' || !$email || $email == '') {
            return false;
        }
        
        //at this point we have a wannabe subscriber and an email address
        //send to mailchimp
        
        //lets make sure we have an api key and list id from the ext settings
        if(!isset($this->settings['apikey']) || !isset($this->settings['listid'])) {
            return false;
        }
        
        //set up vars
        $key    		= $this->settings['apikey'];
        $list   		= $this->settings['listid'];
		$adminemail 	= $this->settings['adminemail'];

        //begin working with mailchimp
        /**
        This Example shows how to Subscribe a New Member to a List using the MCAPI.php 
        class and do some basic error checking.
        **/
        require_once 'freechimp/MCAPI.class.php';
        require_once 'freechimp/config.inc.php';

        $api = new MCAPI($key);

        $merge_vars = array(
                        'FNAME'     => $first,
                        'LNAME'     => $last
                        );

        // By default this sends a confirmation email - you will not see new members
        // until the link contained in it is clicked!
        $retval = $api->listSubscribe( $list, $email, $merge_vars );

        if ($api->errorCode){
	
			//ruh-roh! some comms error with MC.  Email the admin, stat!
			
			if(isset($adminemail) && $adminemail != '') {
				
				$email_msg = 'The following user attempted to join the MailChimp list, ';
				$email_msg .= 'however, there was a communication error. You may wish to ';
				$email_msg .= 'manually add them to the MailChimp list.'."\n\n";
				$email_msg .= $first.' '.$last."\n";
				$email_msg .= $email."\n\n";
				$email_msg .= 'Here is the error returned by MailChimp:'."\n";
				$email_msg .= 'tCode='.$api->errorCode."\n";
				$email_msg .= 'tMsg='.$api->errorMessage."\n\n";
				$email_msg .= 'Note that the user did not see an error message on the website. As far ';
				$email_msg .= 'as they know, everything is hunky dory.';
				
				$this->EE->load->helper('text');
				$this->EE->load->library('email');
				$this->EE->email->wordwrap = true;
				$this->EE->email->mailtype = 'text';	
				$this->EE->email->from($this->EE->config->item('webmaster_email'), $this->EE->config->item('webmaster_name'));
				$this->EE->email->to($adminemail);
				$this->EE->email->subject('Subscriber communication error with MailChimp');
				$this->EE->email->message(entities_to_ascii($email_msg));
				$this->EE->email->send();
	        	
	
			}
			 
        }
        

	}

}
// END
?>