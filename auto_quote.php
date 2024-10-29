<?php
/*
Plugin Name: Auto Quote
Plugin URI:  https://www.grit.online/auto-quote-plugin/
Description: Emails pdf quotes to given email address and adds the contact to CRM.
Author:      GRIT Online Inc.
Version:     1.5.2
Author URI:  https://www.grit.online/
License:     GPL2
*/
 
// =================================================
// Allow code only if WordPress is loaded
// =================================================
if ( !defined('ABSPATH') ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

// =================================================
// Define Constants
// =================================================
if ( ! defined( 'GRITONL_AUTO_QUOTE_PLUGIN_VERSION' ) ) {
	define( 'GRITONL_AUTO_QUOTE_PLUGIN_VERSION', '1.5.2' );
}

if ( ! defined( 'GRITONL_AUTO_QUOTE_PLUGIN_NAME' ) ) {
	define( 'GRITONL_AUTO_QUOTE_PLUGIN_NAME', 'Auto Quote' );
}

// =================================================
// Register Hooks
// =================================================
register_activation_hook( __FILE__, 'gritonl_auto_quote_activate' );
register_deactivation_hook( __FILE__, 'gritonl_auto_quote_deactivate' );
register_uninstall_hook(__FILE__, 'gritonl_auto_quote_uninstall');

// =================================================
// Create shortcode
// =================================================
add_shortcode( 'gritonl_auto_quote', 'gritonl_auto_quote_handler' );

// =================================================
// Load admin functions only if user is admin
// =================================================
if ( is_admin() ) {
  require_once( dirname( __FILE__ ) . '/admin/auto_quote_admin.php' );
}

// =================================================
// Include AC Tracking code in head section
// =================================================
if ( get_option('gritonl_auto_quote_accodeon') ) { add_action ( 'wp_head', 'gritonl_auto_quote_accode' ); }
function gritonl_auto_quote_accode() { echo get_option('gritonl_auto_quote_accode'); }

// =================================================
// Process the request and Create the form
// =================================================
function gritonl_auto_quote_handler() {
  ob_start();
	gritonl_auto_quote_deliver_mail();
	gritonl_auto_quote_html_form_code();
	return ob_get_clean();
}

function gritonl_auto_quote_html_form_code() {

  // Load checkbox checker js
  wp_enqueue_script( 'gritonl_auto_quote_cbchecker', plugins_url( 'js/jquery.cbchecker.js', __FILE__ ), array('jquery'), null, false);
  
  // Load form CSS customizations
  wp_enqueue_style( 'gritonl_auto_quote', plugins_url( 'css/form.css', __FILE__ ), array(), null, 'all' );
  wp_add_inline_style( 'gritonl_auto_quote', get_option( 'gritonl_auto_quote_css' ) );
  
  if ( isset( $_POST['quote-sent'] ) ) {
    $url = get_option( 'gritonl_auto_quote_redirurl' );
    if ($url){
        wp_redirect( $url );
        exit;
    }
  echo '<div>';
	echo '<p><b>Thank you for the request, your quote is being emailed to you.</b></p>';
	echo '</div>';
  }

  // Generate form
	echo '<form action="' . esc_url( $_SERVER['REQUEST_URI'] ) . '" method="post">';
	echo '<p>';
	echo 'First Name<br/>';
	echo '<input class="aq-field" required type="text" name="firstname" pattern="[a-zA-Z0-9 ]+" value="' . ( isset( $_POST["firstname"] ) ? esc_attr( $_POST["firstname"] ) : '' ) . '" size="40" />';
	echo '</p>';
  echo '<p>';
	echo 'Last Name<br/>';
	echo '<input class="aq-field" required type="text" name="lastname" pattern="[a-zA-Z0-9 ]+" value="' . ( isset( $_POST["lastname"] ) ? esc_attr( $_POST["lastname"] ) : '' ) . '" size="40" />';
	echo '</p>';
	echo '<p>';
	echo 'Email Address<br/>';
	echo '<input class="aq-field" required type="email" name="email" value="' . ( isset( $_POST["email"] ) ? esc_attr( $_POST["email"] ) : '' ) . '" size="40" />';
	echo '</p>';
  if (get_option('gritonl_auto_quote_formphone')){
    echo '<p>';
    echo 'Phone<br/>';
    echo '<input class="aq-field" type="text" name="phone" value="' . ( isset( $_POST["phone"] ) ? esc_attr( $_POST["phone"] ) : '' ) . '" size="40" />';
    echo '</p>';
  }
  if(get_option('gritonl_auto_quote_formwebsite')){
    echo '<p>';
    echo 'Website<br/>';
    echo '<input class="aq-field" type="text" name="website" value="' . ( isset( $_POST["website"] ) ? esc_url( $_POST["website"] ) : '' ) . '" size="40" />';
    echo '</p>';
  }
  echo '<p>Select Item(s)<br />';
  foreach ( get_option( 'gritonl_auto_quote_products' ) as $k => $v){
    echo '<input type="checkbox" name="S['.$k.']" value="'.$k.'">'.$v['productName'].'</option><br />';
  }
  echo '</p>';
  
	echo '<p><input class="aq-button" type="submit" name="quote-requested" value="Request Quote" id="checkBtn"></p>';

	echo '</form>';
}

// =================================================
// HubSpot API Call
// =================================================
function gritonl_auto_quote_hs_api( $p = array(), $endpoint = '/contacts/v1/lists/all/contacts/all', $method ='GET'){
  $hsapikey = get_option( 'gritonl_auto_quote_hsapikey' );
  
  $get = 'hapikey='.$hsapikey. '&';
  
  if ( $method == 'GET' ){
    foreach( $p as $key => $value ) $get .= urlencode($key) . '=' . urlencode($value) . '&';
  }
  
  $data = array(
    'headers' => array("Content-Type" => "application/json"),
    'method' => $method,
    'timeout' => 10,
  );
  
  if ( $method == 'POST' ){ $data['body'] = json_encode($p); }
  
  $get = rtrim( $get, '& ' );
  
  $url = 'https://api.hubapi.com' . $endpoint . '?' . $get;
    
  $result = wp_remote_request( $url, $data );
  
  // insert error handling  
  return $result['response']['code'];
}

// =================================================
// Pipedrive API Call
// =================================================
function gritonl_auto_quote_pd_api($endpoint = '/users', $type = 'GET', $data = array() ){
  $company_domain = get_option( 'gritonl_auto_quote_pdcdomain' );
  $api_token = get_option( 'gritonl_auto_quote_pdapitoken' );
  $url = 'https://' . $company_domain . '.pipedrive.com/api/v1' . $endpoint . '?api_token=' . $api_token;
  
  if ($type == "GET"){
    $options = array(
      'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'GET'
      )
    );
    foreach ($data as $k => $v){
    $url.='&'.$k.'='.$v;
    }
  }
  
  if ($type == "POST"){
    $options = array(
      'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
      )
    );
  }

  $context  = stream_context_create($options);
  if( !WP_DEBUG ){ error_reporting(0); }
  $result = file_get_contents($url, false, $context);
  
  $result = json_decode($result,true);
  
  return $result;
}

// =================================================
// Add/Update person to pipedrive
// =================================================
function gritonl_auto_quote_pd_person_add($fn, $ln, $email, $web, $phone, $sprods){
  $personid = gritonl_auto_quote_pd_person_search($email);
  $pdowner = get_option( 'gritonl_auto_quote_pdowner' );
  if (!$personid){
    $endpoint = '/persons';
    $type = 'POST';
    $data = array(
      'name' => $fn." ".$ln,
      'owner_id' => $pdowner,
      'email' => $email,
      'phone' => $phone,
      'visible_to' => 3
    );
    $result = gritonl_auto_quote_pd_api($endpoint, $type, $data);
    $personid = $result['data']['id'];
  }
  
  $c=0;
  foreach ( $sprods as $sprod ) {
    if (!$c){$sprodss=$sprod;} else {$sprodss.=', '.$sprod;}
    $c++;
  }
    
  // Add activity
  $pddays = get_option('gritonl_auto_quote_pddays');
  if($pddays){
    $endpoint = '/activities';
    $type = 'POST';
    $pddate = date("Y-m-d",time() + $pddays*24*60*60);
    $data = array(
      'due_date'   => $pddate,
      'person_id' => $personid,
      'note'=> 'Contact:<br>&nbsp;'.$fn.' '.$ln.'<br>&nbsp;<a href="mailto:'.$email.'">'.$email.'</a><br>&nbsp;'.'<a href="tel:'.$phone.'">'.$phone.'</a><br>&nbsp;<a href="'.$web.'">'.$web.'</a>',
      'public_description'=> 'Discussion about '.$sprodss,
      'subject'=> 'Quote Request',
      'user_id'   => $pdowner,
    );
    $result = gritonl_auto_quote_pd_api($endpoint, $type, $data);
  }
  
  // Add a note
  $endpoint = '/notes';
  $type = 'POST';
  $data = array(
    'content'   => 'Requested Auto Quote for '.$sprodss,
    'person_id' => $personid,
    'user_id'   => $pdowner
  );
  $result = gritonl_auto_quote_pd_api($endpoint, $type, $data);
  return $result;
}

// =================================================
// Search person in pipedrive
// =================================================
function gritonl_auto_quote_pd_person_search($email){
  $endpoint = '/persons/search';
  $type = 'GET';
  $data = array(
    'term' => $email,
    'fields' => 'email',
    'exact_match' => '1',
    'limit' => '1'
  );
  $result = gritonl_auto_quote_pd_api($endpoint, $type, $data);
  if (sizeof($result['data']['items'])){return sanitize_text_field( $result['data']['items'][0]['item']['id'] );}
    else return;
}

// =================================================
// POST Data to Webhook URL
// =================================================
function gritonl_auto_quote_plugin_webhook( $url, $data ) {
  $options = array(
    'http' => array(
      'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
      'method'  => 'POST',
      'content' => http_build_query($data)
    )
  );
  $context  = stream_context_create($options);
  $result = file_get_contents($url, false, $context);
  return $result;
}

// =================================================
// Add contact to ActiveCampaign
// =================================================
function gritonl_auto_quote_ac_contact_add($fn,$ln,$email,$web,$phone,$sprods){
  $tags = get_option( 'gritonl_auto_quote_actags' );
  $list = get_option( 'gritonl_auto_quote_aclist' );
  $url = get_option( 'gritonl_auto_quote_acurl' );
  $api = get_option( 'gritonl_auto_quote_acapikey' );
  
  foreach ( $sprods as $sprod ){ $tags[] = $sprod; }
  
  $tagss="";
  foreach ($tags as $tag) { $tagss.=$tag.","; }

  $params = array(
    'api_key'    => $api,
    'api_action' => 'contact_sync',
    'api_output' => 'serialize',
  );

  $post = array(
    'email'        => $email,
    'first_name'   => $fn,
    'last_name'    => $ln,
    'phone'        => $phone,
    'tags'         => $tagss,
    'p['.$list.']' => $list,
    'ip4'          => $_SERVER['REMOTE_ADDR'],
  );

  $query = "";
  foreach( $params as $key => $value ) $query .= urlencode($key) . '=' . urlencode($value) . '&';
  $query = rtrim($query, '& ');

  $data = "";
  foreach( $post as $key => $value ) $data .= urlencode($key) . '=' . urlencode($value) . '&';
  $data = rtrim($data, '& ');
  $url  = rtrim($url, '/ ');

  if ( !function_exists('curl_init') ) die('CURL not supported. (introduced in PHP 4.0.2)');

  if ( $params['api_output'] == 'json' && !function_exists('json_decode') ) {
    die('JSON not supported. (introduced in PHP 5.2.0)');
  }

  $api = $url . '/admin/api.php?' . $query;

  $request = curl_init($api); // initiate curl object
  curl_setopt($request, CURLOPT_HEADER, 0); // set to 0 to eliminate header info from response
  curl_setopt($request, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
  curl_setopt($request, CURLOPT_POSTFIELDS, $data); // use HTTP POST to send form data
  //curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE); // uncomment if you get no gateway response and are using HTTPS
  curl_setopt($request, CURLOPT_FOLLOWLOCATION, true);

  $response = (string)curl_exec($request); // execute curl post and store results in $response
  curl_close($request); // close curl object

  if ( !$response ) {
    die('Nothing was returned. Do you have a connection to Email Marketing server?');
  }

  $result = unserialize($response);
  
  return $result;
}

// =================================================
// Form the quote PDF and email it
// =================================================
function gritonl_auto_quote_deliver_mail() {

	// if the submit button is clicked, send the email
	if ( isset( $_POST['quote-requested'] ) ) {
		// sanitize form values
		$firstname = sanitize_text_field( $_POST["firstname"] );
    $lastname  = sanitize_text_field( $_POST["lastname"] );
		$email     = sanitize_email( $_POST["email"] );
    $website   = esc_url( $_POST["website"] );
    $phone     = sanitize_text_field( $_POST["phone"] );
        
    // Create temp file for attachment
    $pfile=gritonl_auto_quote_temppdf('quote-');
    
    // Load PDF generation library
    require_once( dirname( __FILE__ ) . '/fpdf/fpdf.php' );
    
    if (!class_exists('PDF')){
    class PDF extends FPDF {
      function Header() {                                                                                                         // Page header
        $this->Image( get_option( 'gritonl_auto_quote_logoURL' ),10,6,null,null,null,get_option('gritonl_auto_quote_logoURL2'));  // Logo
        $this->SetFont('Arial','B',16);                                                                                           // Arial bold 16
        $this->Cell(80);                                                                                                          // Move to the right
        $this->Cell(0,10,'Quote',0,1,'R');                                                                                        // Title
        $this->Cell(0,10,date(get_option('date_format'), time() + get_option('gmt_offset') * 60 * 60 ),0,0,'R');                  // Current Date
        $this->Ln(10);                                                                                                            // Line break
      }

      function Footer() {                                           // Page footer
        $this->SetY(-15);                                           // Position at 15 mm from bottom
        $this->SetFont('Arial','I',8);                              // Arial italic 8
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');  // Page number
        
        $this->SetY(-25);                                           // Position at 25 mm from bottom
        $this->SetFont('Arial','I',10);                             // Arial italic 10
       
        // Insert footer text
        foreach (explode ( "\x0d\x0a" , get_option('gritonl_auto_quote_footer') ) as $k => $v){
          $this->Cell(0,5,$v,0,1,'R');
        }
      }
    }}

    $pdf = new PDF();
    
    $pdf->SetAutoPageBreak(true,25);
    
    $pdf->SetCreator("Auto Quote WP Plugin, provided by https://www.grit.online/");
    $pdf->SetAuthor(get_option( 'gritonl_auto_quote_sender_name' ));
    $pdf->SetTitle("Quote for ".$firstname." ".$lastname);
    $pdf->SetSubject(get_option( 'gritonl_auto_quote_email_subject' ));
    $pdf->SetKeywords("https://www.grit.online/");
    $pdf->AliasNbPages();
    $pdf->AddPage('P','Letter');
    
    // Receiver information
    $pdf->SetFont('Arial','B',14);
    $pdf->Ln(5);                                                                                                            // Line break
    $pdf->Cell(0,5,'ATTN:',0,1);
    $pdf->SetFont('Arial','',14);
    $pdf->Cell(0,5,$firstname." ".$lastname,0,1);
    $pdf->Cell(0,5,$email,0,1);
    if ( $phone ) { $pdf->Cell(0,5,$phone,0,1); }
    if ( $website ) { $pdf->Cell(0,5,$website,0,1); }

    // Introduction
    $pdf->Ln(5);                                                                                                            // Line break
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,10,'We are pleased to offer following products / services, according to your request;',0,1);
  
    // Product Headers
    $pdf->SetFont('Arial','BU',12);
    $pdf->Cell(0,5,"Product / Service",0,0,'L');
    $pdf->Cell(0,5,"Price (".get_option( 'gritonl_auto_quote_currency' ).")",0,1,'R');
    
    // Loop through selected products
    $products = get_option( 'gritonl_auto_quote_products' );
    
    // Sanitize product selection
    $ps = array();
    foreach ( $_POST["S"] as $k => $v ){ $ps[sanitize_text_field($k)] = sanitize_text_field($v); }
    
    $sprods = array();
    foreach ( $ps as $k => $v ){
      $sprods[] = $products[$v]['productName'];
      $pdf->Ln(3);                                                                                                            // Line break
      $pdf->SetFont('Arial','B',12);
      $pdf->Cell(0,5,$products[$v]['productName'],0,0,'L');
      $pdf->Cell(0,5,number_format ($products[$v]['price'],2),0,1,'R');
    
      $pdf->SetFont('Arial','',10);
      $pdf->Cell(5);                                                                                                          // Move to the right
      $pdf->MultiCell(0,5, $products[$v]['description']);
    }
    
    // If PD is active insert contact
    if (get_option( 'gritonl_auto_quote_pdgood' ) == '1' && is_email($email)){
      $result = gritonl_auto_quote_pd_person_add($firstname, $lastname, $email, $website, $phone, $sprods) ;
    }
    
    // If AC is active insert contact
    if (get_option( 'gritonl_auto_quote_acgood' ) == '1' && is_email($email)){
      $result = gritonl_auto_quote_ac_contact_add($firstname, $lastname, $email, $website, $phone, $sprods);
    }
    
    // If HubSpot is active insert contact
    if (get_option( 'gritonl_auto_quote_hsgood' ) == '1' && is_email($email)){
      $contact = array(
        'properties' => array(
          array(
            'property' => 'email',
            'value' => $email
          ),
          array(
            'property' => 'firstname',
            'value' => $firstname
          ),
          array(
            'property' => 'lastname',
            'value' => $lastname
          ),
          array(
            'property' => 'website',
            'value' => $website
          ),
          array(
            'property' => 'phone',
            'value' => $phone
          )
        )
      );
      $result = gritonl_auto_quote_hs_api($contact, '/contacts/v1/contact', 'POST');
    }
    
    // POST Data to webhook, if defined
    $url = get_option( 'gritonl_auto_quote_webhookurl' );
    if ($url){
      $tags = get_option( 'gritonl_auto_quote_actags' );
      $pdowner = get_option( 'gritonl_auto_quote_pdowner' );
      $data = array(
        'first_name' => $firstname,
        'last_name' => $lastname,
		    'email' => $email,
        'website' => $website,
        'phone' => $phone,
        'tags' => $tags,
        'pdowner' => $pdowner,
        'items' => $sprods
        );
      $result = gritonl_auto_quote_plugin_webhook($url,$data);
    }

    // Closing text
    $pdf->Ln(10);                                                                                                            // Line break
    foreach ( explode ( "\x0d\x0a" , get_option( 'gritonl_auto_quote_closing' ) ) as $k => $v){
        $pdf->Cell(0,5,$v,0,1,'L');
    }
    
    // Notes
    $pdf->Ln(5);                                                                                                            // Line break
    $pdf->SetFont('Arial','B',8);
    $pdf->Cell(0,5,'Notes:',0,1);
    $pdf->SetFont('Arial','',8);
    $pdf->Cell(0,5,'* This quote is valid until '.date(get_option('date_format'), time() + get_option( 'gritonl_auto_quote_validity' ) * 24 * 60 * 60 + get_option('gmt_offset') * 60 * 60 ),0,1);
    $pdf->Cell(0,5,'* Quote excludes any applicable taxes.',0,1);
    
    $pdf->Output('F',$pfile); 

    // Generate email body		
    $message = esc_textarea( "Dear $firstname,".chr(13).chr(10).chr(13).chr(10) );
    $message .= esc_textarea( get_option('gritonl_auto_quote_email_body') );

    // setup email
    foreach (get_option( 'gritonl_auto_quote_emails' ) as $k => $v){
      $email .= ",".$v;
    }
    $headers = "From: ".get_option( 'gritonl_auto_quote_sender_name' )." <".get_option( 'gritonl_auto_quote_sender_email' ).">" . "\r\n";
    $subject = get_option( 'gritonl_auto_quote_email_subject' );
    $attachments = array ($pfile);

		// Send email and display a success message
		if ( wp_mail( $email, $subject, $message, $headers, $attachments ) ) {
      unset($_POST['quote-requested']);
      $_POST['quote-sent']=1;
		} else {
			echo 'An unexpected error occurred';
		}
    
    // Remove the temp file
    wp_delete_file($pfile);
	}
}

function gritonl_auto_quote_temppdf( $filename = '', $dir = '' ) {
    if ( empty( $dir ) ) {
        $dir = get_temp_dir();
    }
 
    if ( empty( $filename ) || '.' == $filename || '/' == $filename || '\\' == $filename ) {
        $filename = uniqid();
    }
 
    // Use the basename of the given file without the extension as the name for the temporary directory
    $temp_filename = basename( $filename );
    $temp_filename = preg_replace( '|\.[^.]*$|', '', $temp_filename );
 
    // If the folder is falsey, use its parent directory name instead.
    if ( ! $temp_filename ) {
        return wp_tempnam( dirname( $filename ), $dir );
    }
 
    // Suffix some random data to avoid filename conflicts
    $temp_filename .= '-' . wp_generate_password( 16, false );
    $temp_filename .= '.pdf';
    $temp_filename  = $dir . wp_unique_filename( $dir, $temp_filename );
 
    $fp = @fopen( $temp_filename, 'x' );
    if ( ! $fp && is_writable( $dir ) && file_exists( $temp_filename ) ) {
        return wp_tempnam( $filename, $dir );
    }
    if ( $fp ) {
        fclose( $fp );
    }
 
    return $temp_filename;
}

// =================================================
// Activate plugin function
// =================================================
function gritonl_auto_quote_activate(){
  # Create custom options
  add_option( 'gritonl_auto_quote_hsapikey', '' );
  add_option( 'gritonl_auto_quote_hsgood', '0' );
  add_option( 'gritonl_auto_quote_pddays', '0' );
  add_option( 'gritonl_auto_quote_formphone', '1' );
  add_option( 'gritonl_auto_quote_formwebsite', '1' );
  add_option( 'gritonl_auto_quote_pdowner', '' );
  add_option( 'gritonl_auto_quote_pdusers', array() );
  add_option( 'gritonl_auto_quote_pdgood', '0' );
  add_option( 'gritonl_auto_quote_pdcdomain', '' );
  add_option( 'gritonl_auto_quote_pdapitoken', '' );
  add_option( 'gritonl_auto_quote_webhookurl', '' );
  add_option( 'gritonl_auto_quote_redirurl', '' );
  add_option( 'gritonl_auto_quote_accodeon', '' );
  add_option( 'gritonl_auto_quote_accode', '' );
  add_option( 'gritonl_auto_quote_acurl', '' );
  add_option( 'gritonl_auto_quote_acapikey', '' );
  add_option( 'gritonl_auto_quote_aclist', '' );
  add_option( 'gritonl_auto_quote_aclists', array() );
  add_option( 'gritonl_auto_quote_actags', array( "Auto Quote" ) );
  add_option( 'gritonl_auto_quote_acgood', '0' );
  add_option( 'gritonl_auto_quote_products', array( array( "productName" => "Example Product", "price" => 1.01, "description" => "Description of the Example Product" ) ) );
  add_option( 'gritonl_auto_quote_emails', array( "info@grit.online" ) );
  add_option( 'gritonl_auto_quote_logoURL2', 'https://www.grit.online/' );
  add_option( 'gritonl_auto_quote_logoURL', plugins_url('images/Letter-head-500x80.jpg',__FILE__ ) );
  add_option( 'gritonl_auto_quote_validity', '15' );
  add_option( 'gritonl_auto_quote_currency', 'CAD' );
  add_option( 'gritonl_auto_quote_sender_name', 'GRIT Online Inc.' );
  add_option( 'gritonl_auto_quote_sender_email', 'info@grit.online' );
  add_option( 'gritonl_auto_quote_email_subject', 'Quote' );
  add_option( 'gritonl_auto_quote_closing', 'If you would like to learn more about the services, or place an order, please email info@grit.online' );
  add_option( 'gritonl_auto_quote_footer', '2010 Winston Park Dr, Suite 200'.chr(13).chr(10).'Oakville, ON L6H 5R7, Canada'.chr(13).chr(10).'https://www.grit.online'.chr(13).chr(10).'Business number: 951956-4' );
  add_option( 'gritonl_auto_quote_email_body', 'Please find the requested quote in attached document.'.chr(13).chr(10).'If you have any questions regarding this quote, please contact us at info@grit.online'.chr(13).chr(10).chr(13).chr(10).'Best,'.chr(13).chr(10).'GRIT Online Team'.chr(13).chr(10).'https://www.grit.online/' );
  add_option( 'gritonl_auto_quote_css', '.aq-field {
  background-color: #FFFFFF!important;
}
.aq-button {
  background-color: #af0e1a!important;
  border: 1px solid #af0e1a!important;
  color: #ffffff!important;
  border-radius: 3px!important;
  text-decoration: none!important;
  cursor: pointer!important;
  font-size: 18px!important; 
  display: block!important; 
  padding: 8px 30px!important; 
  margin-top: 5px!important;
}' );
}

// =================================================
// Deactivate plugin function, do not delete options
// =================================================
function gritonl_auto_quote_deactivate(){
  # Nothing yet
}

// =================================================
// Uninstall plugin and delete options
// =================================================  
function gritonl_auto_quote_uninstall(){
  # Delete plugin options
  delete_option('gritonl_auto_quote_hsapikey');
  delete_option('gritonl_auto_quote_hsgood');
  delete_option('gritonl_auto_quote_pddays');
  delete_option('gritonl_auto_quote_formphone');
  delete_option('gritonl_auto_quote_formwebsite');
  delete_option('gritonl_auto_quote_pdowner');
  delete_option('gritonl_auto_quote_pdusers');
  delete_option('gritonl_auto_quote_pdgood');
  delete_option('gritonl_auto_quote_pdcdomain');
  delete_option('gritonl_auto_quote_pdapitoken');
  delete_option('gritonl_auto_quote_webhookurl');
  delete_option('gritonl_auto_quote_redirurl');
  delete_option('gritonl_auto_quote_accodeon');
  delete_option('gritonl_auto_quote_accode');
  delete_option('gritonl_auto_quote_acurl');
  delete_option('gritonl_auto_quote_acapikey');
  delete_option('gritonl_auto_quote_aclist');
  delete_option('gritonl_auto_quote_aclists');
  delete_option('gritonl_auto_quote_actags');
  delete_option('gritonl_auto_quote_acgood');
  delete_option('gritonl_auto_quote_products');
  delete_option('gritonl_auto_quote_emails');
  delete_option('gritonl_auto_quote_logoURL2');
  delete_option('gritonl_auto_quote_logoURL');
  delete_option('gritonl_auto_quote_validity');
  delete_option('gritonl_auto_quote_currency');
  delete_option('gritonl_auto_quote_sender_name');
  delete_option('gritonl_auto_quote_sender_email');
  delete_option('gritonl_auto_quote_email_subject');
  delete_option('gritonl_auto_quote_closing');
  delete_option('gritonl_auto_quote_footer');
  delete_option('gritonl_auto_quote_email_body');
  delete_option('gritonl_auto_quote_css');
}