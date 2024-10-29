<?php

// =================================================
// Allow code only if plugin is active
// =================================================
if ( ! defined( 'GRITONL_AUTO_QUOTE_PLUGIN_VERSION' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
  exit;
}

// =================================================
// Allow code only for admins
// =================================================
if ( !is_admin() ) {
  wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}
 
// =================================================
// Register options page in admin menu
// =================================================
add_action( 'admin_menu', 'gritonl_auto_quote_plugin_menu' );

function gritonl_auto_quote_plugin_menu() {
	 add_options_page( 'Auto Quote Settings', 'Auto Quote', 'manage_options', 'gritonl_auto_quote', 'gritonl_auto_quote_plugin_options' );
}

// =================================================
// Plugin Admin Menu Options
// =================================================
function gritonl_auto_quote_plugin_options() {
	// Allow only admins
  if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
  
  // Show settings options
	echo '<div class="wrap">';
	echo '<h1>'.GRITONL_AUTO_QUOTE_PLUGIN_NAME.' Settings</h1>';
  
  wp_enqueue_script('gritonl_auto_quote_admin_tabs', plugins_url('js/admin.tabs.js',__FILE__ ), array(), null, false);
  wp_enqueue_style( 'gritonl_auto_quote', plugins_url('css/admin.tabs.css',__FILE__ ), array(), null, 'all' );
  
  $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : "general" ;
  
  ?>
  <h2 class="nav-tab-wrapper">
    <button class="nav-tab <?php echo $tab == 'general' ? 'nav-tab-active' : ''; ?>" onclick="openTab(event, 'general')">General</button>
    <button class="nav-tab <?php echo $tab == 'email' ? 'nav-tab-active' : ''; ?>" onclick="openTab(event, 'email')">Email</button>
    <button class="nav-tab <?php echo $tab == 'quote' ? 'nav-tab-active' : ''; ?>" onclick="openTab(event, 'quote')">Quote</button>
    <button class="nav-tab <?php echo $tab == 'css' ? 'nav-tab-active' : ''; ?>" onclick="openTab(event, 'css')">Form</button>
    <button class="nav-tab <?php echo $tab == 'products' ? 'nav-tab-active' : ''; ?>" onclick="openTab(event, 'products')">Products</button>
    <button class="nav-tab <?php echo $tab == 'integrations' ? 'nav-tab-active' : ''; ?>" onclick="openTab(event, 'integrations')">Integrations</button>
  </h2>
  <br>
  <?php
  // Save css tab settings
  if(isset($_POST['css-saved']) && wp_verify_nonce($_POST['nonce'], 'css-saved') ){
    update_option( 'gritonl_auto_quote_css', sanitize_textarea_field($_POST['css']) );
    update_option( 'gritonl_auto_quote_formphone', sanitize_textarea_field($_POST['formphone']) );
    update_option( 'gritonl_auto_quote_formwebsite', sanitize_textarea_field($_POST['formwebsite']) );
  }
  
  // Save integration tab settings
  if(isset($_POST['integrations-saved']) && wp_verify_nonce($_POST['nonce'], 'integrations-saved') ){
    // Get and save HubSpot API Key and test connection
    $hsapikey = isset($_POST['hsapikey']) ? sanitize_textarea_field($_POST['hsapikey']) : null ;
    update_option( 'gritonl_auto_quote_hsapikey', $hsapikey );
    if($hsapikey){
      $hsstatus = gritonl_auto_quote_hs_api(array('count' =>1));
      if($hsstatus == 200){
        update_option( 'gritonl_auto_quote_hsgood', 1);
      } else update_option( 'gritonl_auto_quote_hsgood', 0);      
    } else update_option( 'gritonl_auto_quote_hsgood', 0);

    // Get and save ActiveCampaign settings
    $acurl = sanitize_textarea_field($_POST['acurl']);
    $acapikey = sanitize_textarea_field($_POST['acapikey']);
    $aclist = isset($_POST['aclist']) ? sanitize_textarea_field($_POST['aclist']) : null ;
    $accodeon = isset($_POST['accodeon']) ? sanitize_textarea_field($_POST['accodeon']) : null ;
    update_option( 'gritonl_auto_quote_acurl', $acurl );
    update_option( 'gritonl_auto_quote_acapikey', $acapikey );
    
    // Get and save Pipedrive settings
    $pdcdomain = sanitize_textarea_field($_POST['pdcdomain']);
    $pdapitoken = sanitize_textarea_field($_POST['pdapitoken']);
    $pdowner = isset($_POST['pdowner']) ? sanitize_textarea_field($_POST['pdowner']) : null ;
    $pddays = isset($_POST['pddays']) ? sanitize_textarea_field($_POST['pddays']) : null ; 
    update_option( 'gritonl_auto_quote_pdcdomain', $pdcdomain );
    update_option( 'gritonl_auto_quote_pdapitoken', $pdapitoken );
    update_option( 'gritonl_auto_quote_pdowner', $pdowner );
    update_option( 'gritonl_auto_quote_pddays', $pddays );
    
    // Check PD connection
    if ( !empty($pdcdomain) && !empty($pdapitoken) ){
      $result = gritonl_auto_quote_pd_api();
      if ( $result['success'] ){
        foreach ( $result['data'] as $user ){ $pdusers[sanitize_text_field($user['id'])] = sanitize_text_field($user['name']); }
        update_option( 'gritonl_auto_quote_pdgood', '1' );
        update_option( 'gritonl_auto_quote_pdusers', $pdusers );
      } else { update_option( 'gritonl_auto_quote_pdgood', '0' ); }
    } else { update_option( 'gritonl_auto_quote_pdgood', '0' ); }

    // Get AC lists
    if ( !empty($acurl) && !empty($acapikey) ){
      $result = gritonl_auto_quote_plugin_get_ac_lists($acurl,$acapikey);
      $aclists = array();      
      if ($result['result_code']){
        update_option( 'gritonl_auto_quote_acgood', '1' );
        update_option( 'gritonl_auto_quote_aclist', $aclist );
        if ($accodeon){
          $ac_tracking_code = gritonl_auto_quote_plugin_get_ac_code($acurl,$acapikey);
          update_option( 'gritonl_auto_quote_accode', $ac_tracking_code );
        }
        update_option( 'gritonl_auto_quote_accodeon', $accodeon );
        foreach($result as $k => $v){
          if (is_numeric($k)){
            $aclists[sanitize_text_field($v['id'])] = sanitize_text_field($v['name']); 
          }          
        }
        update_option( 'gritonl_auto_quote_aclists', $aclists );
        $actags = isset($_POST['actags']) ? explode (',',($_POST['actags'])) : array();
        foreach ($actags as $k => $v){
          $actags[$k] = sanitize_text_field( $v );
        }
        $actags = array_filter($actags); # Remove potential null values
        update_option( 'gritonl_auto_quote_actags', $actags ); #insert tags into options
      } else {
          update_option( 'gritonl_auto_quote_acgood', '0' );
          update_option( 'gritonl_auto_quote_accodeon', '' );
        }
    } else {
        update_option( 'gritonl_auto_quote_acgood', '0' );
        update_option( 'gritonl_auto_quote_accodeon', '' );
    }
  }  

  // Save email tab settings
  if(isset($_POST['email-saved']) && wp_verify_nonce($_POST['nonce'], 'email-saved') ){
    update_option( 'gritonl_auto_quote_sender_name', sanitize_text_field($_POST['senderName']) );
    update_option( 'gritonl_auto_quote_sender_email', sanitize_email($_POST['senderEmail']) );
    update_option( 'gritonl_auto_quote_email_subject', sanitize_text_field($_POST['emailSubject']) );
    update_option( 'gritonl_auto_quote_email_body', sanitize_textarea_field($_POST['emailBody']) );
    $emails = explode (',',($_POST['emails']));
    foreach ($emails as $k => $v){
      $emails[$k] = sanitize_email( $v );
    }
    $emails = array_filter($emails); # Remove potential null values
    update_option( 'gritonl_auto_quote_emails', $emails ); #insert emails into options
  }
  
  // Save general tab settings
  if(isset($_POST['general-saved']) && wp_verify_nonce($_POST['nonce'], 'general-saved') ){
    update_option( 'gritonl_auto_quote_redirurl', sanitize_text_field($_POST['redirurl']) );
    update_option( 'gritonl_auto_quote_webhookurl', sanitize_text_field($_POST['webhookurl']) );
  }
  
  // Save quote tab settings
  if(isset($_POST['quote-saved']) && wp_verify_nonce($_POST['nonce'], 'quote-saved') ){
    update_option( 'gritonl_auto_quote_currency', sanitize_text_field($_POST['currency']) );
    update_option( 'gritonl_auto_quote_validity', sanitize_text_field($_POST['validity']) );
    update_option( 'gritonl_auto_quote_logoURL', esc_url($_POST['logoURL']) );
    update_option( 'gritonl_auto_quote_logoURL2', esc_url($_POST['logoURL2']) );
    update_option( 'gritonl_auto_quote_closing', sanitize_textarea_field($_POST['closing']) );
    update_option( 'gritonl_auto_quote_footer', sanitize_textarea_field($_POST['footer']) );
  }
  
  // Create new product
  if(isset($_POST['product-created']) && wp_verify_nonce($_POST['nonce'], 'product-created') ){
    $products = get_option( 'gritonl_auto_quote_products' );
    array_push( $products, array( "productName" => sanitize_text_field($_POST['productName']), "price" => sanitize_text_field($_POST['price']), "description" => ""));
    update_option( 'gritonl_auto_quote_products', $products );
  }
  
  // Delete product
  if(isset($_POST['product-deleted']) && wp_verify_nonce($_POST['nonce'], 'product-deleted')){
    $products=get_option( 'gritonl_auto_quote_products' );
    unset($products[ sanitize_text_field($_POST['ID']) ]);
    update_option( 'gritonl_auto_quote_products', $products );
  }
  
  //Edit product details
  if(isset($_POST['edit-details']) && wp_verify_nonce($_POST['nonce'], 'edit-details') ){
    ?>
    <div class="wrap">
    <?php
      $products=get_option( 'gritonl_auto_quote_products' );
      $pid=sanitize_text_field( $_POST['ID'] );
      echo '<h1>Edit '.$products[ $pid ]['productName'].' Details</h1>';
    ?>
    <form action="<?php menu_page_url( "gritonl_auto_quote", true ); ?>" method="post">
      <table>
		    <tr>
          <th scope="row">Product name/title</th>
          <td><input type="text" size="40" name="productName" value="<?php echo htmlspecialchars($products[ $pid ]['productName'],ENT_QUOTES|ENT_HTML401); ?>"></td>
        </tr>  
        <tr>
          <th scope="row">Price</th>
          <td><input type="text" size="10" name="price" value="<?php echo number_format($products[ $pid ]['price'],2,".",""); ?>"></td>
        </tr>
        <tr>
          <th scope="row">Description</th>
          <td><textarea name="description" rows="14" cols="123"><?php echo $products[ $pid ]['description']; ?></textarea></td>
        </tr>
      </table>
      <input type="hidden" name="ID" value=<?php echo $pid ; ?>>
      <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('description-saved'); ?>">
      <input type="hidden" name="tab" value="products">
      <input class="button button-primary" name="description-saved" type="submit" value="Save Changes">
      <input class="button button-primary" name="description-cancelled" type="submit" value="Cancel">
    </form>
    </div>
    <?php
    exit;
  }
  
  // Save product details
  if(isset($_POST['description-saved']) && wp_verify_nonce($_POST['nonce'], 'description-saved') ){
    $products=get_option( 'gritonl_auto_quote_products' );
    $pid=sanitize_text_field( $_POST['ID'] );
    $products[ $pid ]['description'] = sanitize_textarea_field(stripslashes($_POST['description']));
    $products[ $pid ]['price'] = sanitize_text_field($_POST['price']);
    $products[ $pid ]['productName'] = sanitize_text_field(stripslashes($_POST['productName']));
    update_option( 'gritonl_auto_quote_products', $products );
  }
  
  ?>
  <div id="products" class="tabcontent" style="display:<?php echo $tab == 'products' ? 'block' : 'none'; ?>">
    <form action="<?php menu_page_url( "gritonl_auto_quote", true ); ?>" method="post">
      <table>
  		  <tr>
          <td><input type="text" size="40" name="productName"><p class="description" id="productName-description">Product Name</p></td>
          <td><input type="text" size="4" name="price"><p class="description" id="price-description">Price</p></td>
          <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('product-created'); ?>">
          <input type="hidden" name="tab" value="products">
          <td valign="top"><input type="submit" name="product-created" value="Add Product"></td>
        </tr>
      </table>
    </form>
    <table class="go-ptable">
      <tr>
        <th class="go-pth" scope="table">#</th>
        <th class="go-pth" scope="table">Product</th>
        <th class="go-pth" scope="table">Price</th>
        <th class="go-pth" scope="table"></th>
        <th class="go-pth" scope="table"></th>
      </tr>
      <?php
      $c=1;
      foreach (get_option( 'gritonl_auto_quote_products' ) as $k => $v){
      ?>
  		<tr>
        <td class="go-ptd" scope="row"><?php echo $c; ?></td>
        <td class="go-ptd"><?php echo $v["productName"]; ?></td>
        <td class="go-ptd"><?php echo number_format($v["price"],2); ?></td>
        <td>
          <form action="<?php menu_page_url( "gritonl_auto_quote", true ); ?>" method="post">
            <input type="hidden" name="ID" value=<?php echo $k; ?>>
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('product-deleted'); ?>">
            <input type="hidden" name="tab" value="products">
            <input type="submit" name="product-deleted" value="Delete">
          </form>
        </td>
        <td>
          <form action="<?php menu_page_url( "gritonl_auto_quote", true ); ?>" method="post">
            <input type="hidden" name="ID" value=<?php echo $k; ?>>
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('edit-details'); ?>">
            <input type="hidden" name="tab" value="products">
            <input type="submit" name="edit-details" value="Edit">
          </form>
        </td>
      </tr>
      <?php $c++; } ?>
    </table>
  </div>
  <div id="css" class="tabcontent" style="display:<?php echo $tab == 'css' ? 'block' : 'none'; ?>">
    <form action="<?php menu_page_url( "gritonl_auto_quote", true ); ?>" method="post">
      <table class="form-table">
        <tr>
          <th scope="row">Form CSS</th>
          <td><textarea name="css" rows="20" cols="80"><?php echo get_option( 'gritonl_auto_quote_css' ); ?></textarea><p class="description" id="css-description">CSS for the form on the page (.aq-field and .aq-button)</p></td>
        </tr>
        <tr>
          <th scope="row">Phone</th>
          <td><input type="checkbox" name="formphone" value="1" <?php if ( get_option( 'gritonl_auto_quote_formphone' ) ) { echo "checked"; } ?>><p class="description" id="formphone-description">Include phone number on the form</p></td>          
        </tr>
        <tr>
          <th scope="row">Website</th>
          <td><input type="checkbox" name="formwebsite" value="1" <?php if ( get_option( 'gritonl_auto_quote_formwebsite' ) ) { echo "checked"; } ?>><p class="description" id="formwebsite-description">Include website on the form</p></td>          
        </tr>
      </table>
      <br />
      <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('css-saved'); ?>">
      <input type="hidden" name="tab" value="css">
      <input class="button button-primary" name="css-saved" type="submit" value="Save Changes">
    </form>
  </div>
  <div id="integrations" class="tabcontent" style="display:<?php echo $tab == 'integrations' ? 'block' : 'none'; ?>">
    <h3><?php if (!get_option( 'gritonl_auto_quote_acgood' ) ){ echo '<i style="color:red">&#10008;</i>'; } else echo '<i style="color:green">&#10004;</i>'; ?> ActiveCampaign</h3>
    <form action="<?php menu_page_url( "gritonl_auto_quote", true ); ?>" method="post">
      <table class="form-table">
        <tr>
          <th scope="row">API URL</th>
          <td><input type="text" size="86" name="acurl" placeholder="https://name.api-us1.com" value="<?php echo get_option( 'gritonl_auto_quote_acurl' ); ?>"><p class="description" id="acurl-description">ActiveCampaign -> Settings -> Developer</p></td>
        </tr>
        <tr>
          <th scope="row">API Key</th>
          <td><input type="text" size="86" name="acapikey" placeholder="1a23b4..." value="<?php echo get_option( 'gritonl_auto_quote_acapikey' ); ?>"><p class="description" id="acapikey-description">ActiveCampaign -> Settings -> Developer</p></td>
        </tr>
       <?php if (get_option( 'gritonl_auto_quote_acgood' ) == '1' ){ ?> 
        <tr>
          <th scope="row">Tags</th>
          <td><input type="text" size="86" name="actags" value="<?php
            $c=0;
            if (get_option( 'gritonl_auto_quote_actags' )){
              foreach ( get_option( 'gritonl_auto_quote_actags' ) as $k => $v ) {
                if ( $c ) { echo ", ".$v; }
                  else { echo $v; }
                $c++;
              }
            }
            ?>"><p class="description" id="actags-description">Comma separated list of tags</p>
          </td>
        </tr>
        <?php $aclists = get_option( 'gritonl_auto_quote_aclists' ); ?>
        <tr>
          <th scope="row">List</th>
          <td>
          <select name="aclist" id="aclist">
            <?php
              if (count($aclists)){
                $l = get_option( 'gritonl_auto_quote_aclist' );
                if (empty($l)){echo '<option value="-1">Select list</option>';}
                foreach ( $aclists as $k => $v){
                  if ($k == $l){$s="selected ";} else {$s="";}
                  echo '<option '.$s.'value="'.$k.'">'.$v.'</option>';
                }
              }
              else { echo '<option value="-1">Provide valid URL and API Key</option>'; }
            ?>
          </select>
          <p class="description" id="aclist-description">Please save ActiveCampaign URL and API Key first</p>
          </td>
        </tr>
        <tr>
          <th scope="row">Tracking Code</th>
          <td><input type="checkbox" name="accodeon" value="1" <?php if ( get_option( 'gritonl_auto_quote_accodeon' ) ) { echo "checked"; } ?>><p class="description" id="accodeon-description">Insert tracking code on all pages</p></td>          
        </tr>
       <?php } ?> 
      </table>
      <h3><?php if (!get_option( 'gritonl_auto_quote_pdgood' ) ){ echo '<i style="color:red">&#10008;</i>'; } else echo '<i style="color:green">&#10004;</i>'; ?> Pipedrive</h3>
      <table class="form-table">
        <tr>
          <th scope="row">Company domain</th>
          <td><input type="text" size="86" name="pdcdomain" placeholder="name-12a34b" value="<?php echo get_option( 'gritonl_auto_quote_pdcdomain' ); ?>"><p class="description" id="pdcdomain-description">Pipedrive -> Company settings</p></td>
        </tr>
        <tr>
          <th scope="row">API token</th>
          <td><input type="text" size="86" name="pdapitoken" placeholder="1234a5..." value="<?php echo get_option( 'gritonl_auto_quote_pdapitoken' ); ?>"><p class="description" id="pdapitoken-description">Pipedrive -> Personal preferences</p></td>
        </tr>
        <?php if (get_option( 'gritonl_auto_quote_pdgood' ) == '1' ){ ?>
        <?php $pdusers = get_option( 'gritonl_auto_quote_pdusers' ); ?>
        <tr>
          <th scope="row">Lead Owner</th>
          <td>
          <select name="pdowner" id="pdowner">
            <?php
              if (count($pdusers)){
                $o = get_option( 'gritonl_auto_quote_pdowner' );
                if (empty($o)){echo '<option value="-1">Select owner</option>';}
                foreach ( $pdusers as $k => $v){
                  if ($k == $o){$s="selected ";} else {$s="";}
                  echo '<option '.$s.'value="'.$k.'">'.$v.'</option>';
                }
              }
              else { echo '<option value="-1">Provide valid company domain and API token</option>'; }
            ?>
          </select>
          <p class="description" id="pdowner-description">Selected pipedrive user will become the owner of the new lead</p>
          </td>
        </tr>
        <tr>
          <th scope="row">Activity delay</th>        
          <td><input type="number" value="<?php echo (get_option('gritonl_auto_quote_pddays') ? get_option('gritonl_auto_quote_pddays') : '0'); ?>" step="1" name="pddays" min="0" max="365"> days<p class="description" id="pddays-description">Create activity reminder. Set 0 to disable.</p></td>
        </tr>
        <?php } ?>
      </table>
      <h3><?php if (!get_option( 'gritonl_auto_quote_hsgood' ) ){ echo '<i style="color:red">&#10008;</i>'; } else echo '<i style="color:green">&#10004;</i>'; ?> HubSpot</h3>
      <table class="form-table">
        <tr>
          <th scope="row">API Key</th>
          <td><input type="text" size="86" name="hsapikey" placeholder="123a4567-12b3-123c-d1ff-12abcd34e5f6" value="<?php echo get_option( 'gritonl_auto_quote_hsapikey' ); ?>"><p class="description" id="hsapikey-description">Settings -> Integrations -> API Key</p></td>
        </tr>
      </table>
      <br />
      <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('integrations-saved'); ?>">
      <input type="hidden" name="tab" value="integrations">
      <input class="button button-primary" name="integrations-saved" type="submit" value="Save Changes">
    </form>
  </div>
  <div id="general" class="tabcontent" style="display:<?php echo $tab == 'general' ? 'block' : 'none'; ?>">
    <form action="<?php menu_page_url( "gritonl_auto_quote", true ); ?>" method="post">
      <table class="form-table">
        <tr>
          <th scope="row">Shortcode</th>
          <td><input type="text" size="20" name="shortcode" value="[gritonl_auto_quote]"><p class="description" id="shortcode-description">Place this shortcode on the page</p></td>     
        </tr>
        <tr>
          <th scope="row">Redirect URL</th>
          <td><input type="text" size="86" name="redirurl" placeholder="<?php echo get_home_url(); ?>" value="<?php echo get_option( 'gritonl_auto_quote_redirurl' ); ?>"><p class="description" id="redirurl-description">URL to redirect to after form submission. Leave empty to stay on the page.</p></td>
        </tr>
        <tr>
          <th scope="row">Webhook URL</th>
          <td><input type="text" size="86" name="webhookurl" placeholder="<?php echo get_home_url(); ?>" value="<?php echo get_option( 'gritonl_auto_quote_webhookurl' ); ?>"><p class="description" id="webhookurl-description">URL to POST form submission data. Leave empty to disable.</p></td>
        </tr>
      </table>
      <br />
      <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('general-saved'); ?>">
      <input type="hidden" name="tab" value="general">
      <input class="button button-primary" name="general-saved" type="submit" value="Save Changes">
    </form>
  </div>
  <div id="email" class="tabcontent" style="display:<?php echo $tab == 'email' ? 'block' : 'none'; ?>">
    <form action="<?php menu_page_url( "gritonl_auto_quote", true ); ?>" method="post">
      <table class="form-table">
		    <tr>
          <th scope="row">Quote Sender Name</th>
          <td><input type="text" size="80" name="senderName" value="<?php echo get_option( 'gritonl_auto_quote_sender_name' ); ?>"></td>
        </tr>
		    <tr>
          <th scope="row">Quote Sender Email Address</th>
          <td><input type="text" size="80" name="senderEmail" value="<?php echo get_option( 'gritonl_auto_quote_sender_email' ); ?>"></td>
        </tr>
        <tr>
          <th scope="row">Additional recipients</th>
          <td><input type="text" size="80" name="emails" value="<?php
            $c=0;
            foreach ( get_option( 'gritonl_auto_quote_emails' ) as $k => $v ) {
              if ( $c ) { echo ", ".$v; }
                else { echo $v; }
              $c++;
            }
            ?>"><p class="description" id="emails-description">Comma separated list of additional email recipients</p>
          </td>
        </tr>
        <tr>
          <th scope="row">Quote Email Subject</th>
          <td><input type="text" size="80" name="emailSubject" value="<?php echo get_option( 'gritonl_auto_quote_email_subject' ); ?>"></td>
        </tr>
        <tr>
          <th scope="row">Email Body Text</th>
          <td><textarea name="emailBody" rows="7" cols="80"><?php echo get_option( 'gritonl_auto_quote_email_body' ); ?></textarea></td>
        </tr>
      </table>
      <br />
      <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('email-saved'); ?>">
      <input type="hidden" name="tab" value="email">
      <input class="button button-primary" name="email-saved" type="submit" value="Save Changes">
    </form>
  </div>
  <div id="quote" class="tabcontent" style="display:<?php echo $tab == 'quote' ? 'block' : 'none'; ?>">
    <form action="<?php menu_page_url( "gritonl_auto_quote", true ); ?>" method="post">
      <table class="form-table">      
        <tr>
          <th scope="row">Logo Image URL</th>
          <td><input type="text" size="80" name="logoURL" value="<?php echo get_option( 'gritonl_auto_quote_logoURL' ); ?>"><p class="description" id="logoURL-description">URL for the logo image, which will appear in the quote. Ideally 500 x 80px JPG/PNG.</p></td>
        </tr>
        <tr>
          <th scope="row">Logo href URL</th>
          <td><input type="text" size="80" name="logoURL2" value="<?php echo get_option( 'gritonl_auto_quote_logoURL2' ); ?>"><p class="description" id="logoURL2-description">URL link associated with the logo</p></td>
        </tr>
        <tr>
          <th scope="row">Quote Currency</th>
          <td><input type="text" size="3" name="currency" value="<?php echo get_option( 'gritonl_auto_quote_currency' ); ?>"></td>
        </tr>
        <tr>
          <th scope="row">Quote Validity Period</th>
          <td><input type="text" size="3" name="validity" value="<?php echo get_option( 'gritonl_auto_quote_validity' ); ?>"><p class="description" id="validity-description">Quote validity time in days</p></td>
        </tr>
        <tr>
          <th scope="row">Quote Closing Text</th>
          <td><textarea name="closing" rows="2" cols="80"><?php echo get_option( 'gritonl_auto_quote_closing' ); ?></textarea><p class="description" id="closing-description">Text in the end of the quote</p></td>
        </tr>
        <tr>
          <th scope="row">Quote Footer Text</th>
          <td><textarea name="footer" rows="4" cols="80"><?php echo get_option( 'gritonl_auto_quote_footer' ); ?></textarea><p class="description" id="footer-description">Text in the quote footer</p></td>
        </tr>
      </table>
      <br />
      <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('quote-saved'); ?>">
      <input type="hidden" name="tab" value="quote">
      <input class="button button-primary" name="quote-saved" type="submit" value="Save Changes">
    </form>
  </div>
  <?php
  echo '<br /><br />This WordPress Plugin is provided by <a href="https://www.grit.online/">GRIT Online Inc.</a>';
  echo '</div>';
}

// =================================================
// Get ActiveCampaign lists
// =================================================
function gritonl_auto_quote_plugin_get_ac_lists($url,$api) {
  $params = array(
    'api_key'    => $api,
    'api_action' => 'list_list',
    'api_output' => 'serialize',
    'ids'        => 'all',
    'full'       => 1,
  );

  $query = "";
  foreach( $params as $key => $value ) $query .= urlencode($key) . '=' . urlencode($value) . '&';
  $query = rtrim($query, '& ');
  $url   = rtrim($url, '/ ');

  if ( !function_exists('curl_init') ) die('CURL not supported. (introduced in PHP 4.0.2)');

  if ( $params['api_output'] == 'json' && !function_exists('json_decode') ) {
    die('JSON not supported. (introduced in PHP 5.2.0)');
  }

  $api = $url . '/admin/api.php?' . $query;

  $request = curl_init($api); // initiate curl object
  curl_setopt($request, CURLOPT_HEADER, 0); // set to 0 to eliminate header info from response
  curl_setopt($request, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
  //curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE); // uncomment if you get no gateway response and are using HTTPS
  curl_setopt($request, CURLOPT_FOLLOWLOCATION, true);
  $response = (string)curl_exec($request); // execute curl fetch and store results in $response
  curl_close($request); // close curl object

  if ( !$response ) {
    die('Nothing was returned. Do you have a connection to Email Marketing server?');
  }

  $result = unserialize($response);
  return $result;
}

// =================================================
// Get ActiveCampaign tracking code
// =================================================
function gritonl_auto_quote_plugin_get_ac_code($url,$api) {
  $params = array( 'api_key' => $api );

  $query = "";
  foreach( $params as $key => $value ) $query .= urlencode($key) . '=' . urlencode($value) . '&';
  $query = rtrim($query, '& ');
  
  $url   = rtrim($url, '/ ');
  
  $api = $url . '/api/2/track/site/code?' . $query;
      
  $options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'GET'
    )
  );
  
  $context  = stream_context_create($options);
  $result = file_get_contents($api, false, $context);
  //if ($result === FALSE) { /* Handle error */ }

  $result = json_decode($result,true);
  $result = $result['code'];
    
  return $result;
}