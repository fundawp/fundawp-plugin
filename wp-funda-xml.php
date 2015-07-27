<?php
/**
 * Plugin Name: Funda WP
 * Plugin URI: http://www.latumweb.nl/
 * Description: Create a link with Funda from wordpress
 * Version: 2.0
 * Author: Funda WP
 * Author URI: http://www.FundaWP.nl/
 * License: Copyright FundaWP
 *
 *	Intellectual Property rights, and copyright, reserved by Funda WP
 *
 *
 * @package     FundaWP
 * @author      Funda WP
 * @category    Plugin
 * @copyright   Copyright (c) FundaWP
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

		
add_action( 'template_redirect', 'fundasitemap' );

function fundasitemap() {
  if ( ! preg_match( '/funda\.xml$/', $_SERVER['REQUEST_URI'] ) ) {
    return;
  }
    
  global $wpdb;
  $fundaArray = get_option('WPfundaArray');
  /**
  if (get_option('funda_taxonomy') == "taxonomy"){
  $category_id = get_cat_ID(get_option('funda_category', true));
  $posts = $wpdb->get_results( "SELECT ID, post_title, post_modified_gmt
    FROM $wpdb->posts wposts
    LEFT JOIN $wpdb->term_relationships ON (wposts.ID = $wpdb->term_relationships.object_id)
	LEFT JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id)
    WHERE post_status = 'publish'
    AND post_password = ''
    AND $wpdb->term_taxonomy.taxonomy = 'category'
	AND $wpdb->term_taxonomy.term_id = '".$category_id."'
    ORDER BY post_type DESC, post_modified DESC
    LIMIT 50000" );
  } else {
  */
  $posts = $wpdb->get_results( "SELECT ID, post_title, post_modified_gmt, post_type
    FROM $wpdb->posts wposts
    WHERE post_status = 'publish'
    AND post_type = '".get_option('funda_posttype', true)."'
    AND post_password = ''
    ORDER BY post_type DESC, post_modified DESC
    LIMIT 50000" );    
  
  header( "HTTP/1.1 200 OK" );
  header( 'X-Robots-Tag: noindex, follow', true );
  header( 'Content-Type: text/xml' );
  //echo get_permalink( $post->ID, false );
  echo '<?xml version="1.0" encoding="utf-8"?>'."\n";
  echo '<funda-aanbod versie="1.0">'."\n";
  $xml = '';
  foreach ( $posts as $post ) {
    if ( ! empty( $post->post_title ) ) {
	$xml .= "\t".'<wonen-object ObjectID="'.$post->ID.'">'."\n";
        foreach($fundaArray as $data => $dataArray){
          if ($data == "hoofdfoto"){
            if (get_post_thumbnail_id($post->ID)) { $xml .= "\t\t<hoofdfoto>" . wp_get_attachment_url( get_post_thumbnail_id($post->ID) ) . "</hoofdfoto>\n"; }
          } else if ($data == "url"){
            $xml .= "\t\t<url>" . get_permalink($post->ID) . "</url>\n";        
          } else if ($data == "aanbiedingstekst") {
            if (get_post_meta($post->ID, $dataArray['template'], true)) { $xml .= "\t\t<$data><![CDATA[" . get_post_meta($post->ID, $dataArray['template'], true) . "]]></$data>\n"; }
          } else {
            if (get_post_meta($post->ID, $dataArray['template'], true)) { $xml .= "\t\t<$data>" . get_post_meta($post->ID, $dataArray['template'], true) . "</$data>\n"; }
          }
        }
        $xml .= "\t</wonen-object>\n";
    }
  }
  $xml .= '</funda-aanbod>';
  echo ( "$xml" );
  exit();
}


//user interface
function myplugin_add_custom_box() {

    $screens = array( get_option('funda_posttype', true) );

    foreach ( $screens as $screen ) {

        add_meta_box(
            'myplugin_sectionid',
            __( 'Funda Object Options', 'myplugin_textdomain' ),
            'myplugin_inner_custom_box',
            $screen
        );
    }
}
add_action( 'add_meta_boxes', 'myplugin_add_custom_box' );

/**
 * Prints the box content.
 * 
 * @param WP_Post $post The object for the current post/page.
 */
function myplugin_inner_custom_box( $post ) {

  // Add an nonce field so we can check for it later.
  wp_nonce_field( 'myplugin_inner_custom_box', 'myplugin_inner_custom_box_nonce' );

  /*
   * Use get_post_meta() to retrieve an existing value
   * from the database and use the value for the form.
   */
   echo '<table>';
   $fundaArray = get_option('WPfundaArray');
  foreach($fundaArray as $data => $dataArray){
    if ($data == "unique_ObjectID" || $data == "hoofdfoto" || $data == "url"){ } else {
      $value = get_post_meta($post->ID, $dataArray['template'], true);
      echo '<tr><td style="vertical-align: top;"><label for="myplugin_new_field">';
           _e( $data, 'myplugin_textdomain' );
      echo '</label></td><td>';
      
      if (isset($dataArray['values'])){
       echo '<select name="'.$dataArray['template'].'_field" id="'.$dataArray['template'].'_field"><option value="nvt">Niet van Toepassing</option>';
       foreach($dataArray['values'] as $selectoptions){
         echo '<option value="'.$selectoptions.'"';
         if (esc_attr( $value ) == $selectoptions) { echo ' selected '; }
         echo '>'.$selectoptions.'</option>';
         }
         echo "</select>";
      } else {
      echo '<input style="width:100%;" type="text" id="'.$dataArray['template'].'_field" name="'.$dataArray['template'].'_field" value="' . esc_attr( $value ) . '" size="25" />';
      
		}
      
      if (isset($dataArray['docu'])){ echo '<br><span style="font-size:10px;">'.$dataArray['docu'].'</span>'; }
      echo '</td></tr>';
    }
  }
  echo '</table>';

}

/**
 * When the post is saved, saves our custom data.
 *
 * @param int $post_id The ID of the post being saved.
 */
function myplugin_save_postdata( $post_id ) {

  /*
   * We need to verify this came from the our screen and with proper authorization,
   * because save_post can be triggered at other times.
   */

  // Check if our nonce is set.
  if ( ! isset( $_POST['myplugin_inner_custom_box_nonce'] ) )
    return $post_id;

  $nonce = $_POST['myplugin_inner_custom_box_nonce'];

  // Verify that the nonce is valid.
  if ( ! wp_verify_nonce( $nonce, 'myplugin_inner_custom_box' ) )
      return $post_id;

  // If this is an autosave, our form has not been submitted, so we don't want to do anything.
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
      return $post_id;

  // Check the user's permissions.
  if ( 'page' == $_POST['post_type'] ) {

    if ( ! current_user_can( 'edit_page', $post_id ) )
        return $post_id;
  
  } else {

    if ( ! current_user_can( 'edit_post', $post_id ) )
        return $post_id;
  }

  /* OK, its safe for us to save the data now. */

  // Sanitize user input.
   $fundaArray = get_option('WPfundaArray');
      if (get_option('funda_enablecheckbox', true) == "on"){     
        $mydata = sanitize_text_field( $_POST['enable_field'] );
        update_post_meta( $post_id, 'enable', $mydata );
      }
  foreach($fundaArray as $data => $dataArray){
    if ($data == "unique_ObjectID" || $data == "hoofdfoto" || $data == "url"){ }
    else {

      $mydata = sanitize_text_field( $_POST[$dataArray['template'].'_field'] );
      // Update the meta field in the database.
      if ($mydata != "nvt") {
        update_post_meta( $post_id, $dataArray['template'], $mydata );
      }
    }
  }
}
add_action( 'save_post', 'myplugin_save_postdata' );

//settingslink
add_filter( "plugin_action_links", 'wpfundaxml_plugin_action_links', 10, 4 );
 
function wpfundaxml_plugin_action_links( $links, $file ) {
	$plugin_file = 'wp-funda-xml/wp-funda-xml.php';
	//make sure it is our plugin we are modifying
	if ( $file == $plugin_file ) {
		$settings_link = '<a href="' .
			admin_url( 'options-general.php?page=wp_funda_xml_dashboard' ) . '">' .
			__( 'Settings', 'wp-funda-xml' ) . '</a>';
		array_unshift( $links, $settings_link );
	}
	return $links;
}

//admin part

add_action('admin_menu', 'my_cool_plugin_create_menu');

function my_cool_plugin_create_menu() {

	//create new top-level menu
	add_menu_page('FundaWP Settings', 'FundaWP Settings', 'administrator', __FILE__, 'fundawp_settings_page' );

	//call register settings function
	add_action( 'admin_init', 'load_settings' );
}

function load_settings() {
  		register_setting( 'myoption-group', 'funda_posttype' );
  		register_setting( 'myoption-group', 'funda_taxonomy' );

}

function fundawp_settings_page() {
createfundaArray()
?>
  <div class="wrap"><h2>Funda options</h2>
  <form method="post" action="options.php">
    <table class="form-table">
    	<tr valign="top">
    		<th scope="row"> Selecteer Post Type</th>
    		<td>    	
    		<select name="funda_posttype">     
    		<?php 
		$post_types = get_post_types();
		foreach ( $post_types as $post_type ) { ?>
    		  <option value="<?php echo $post_type; ?>" <?php if (get_option('funda_posttype') == $post_type){ echo "selected"; } ?>><?php echo $post_type; ?></option>
    		<?php } ?>
    		</select>
    		</td>
    	</tr>
      <tr valign="top">
        <th scope="row">Geef de Taxonomy naam op (Niet aanbevolen)</th>
        <td><input type="text" name="funda_category" value="<?php echo get_option('funda_category'); ?>" /></td>
        </tr>
      </table>
	  <?php settings_fields( 'myoption-group' );
	    submit_button(); ?>
    </form>
    <div style="display:none;"><?php print_r (get_option('WPfundaArray')); ?></div>
    </div>
<?
}

function createfundaArray() {
  delete_option('WPfundaArray');
  $doc = new DOMDocument();
  $doc->preserveWhiteSpace = false;
  $funda = file_get_contents('http://xml.funda.nl/media/10584198/woningen_1.0.xsd');
  $doc->loadXML($funda);

  //in $fields komt alle info
  $fields = array();


  $xpath = new DOMXpath($doc);
  //verzamel alle simpleType's:
  $elements = $xpath->query("//xs:simpleType");

  foreach ($elements as $el) {
	$key = $el->getAttribute('name');

	$docu='';
	//verzamel alle documentation elementen BINNEN $el, het zijn er vermoedelijk of geen of 1, maar toch moeten we net doen alsof er meer zijn
	$documentations = $xpath->query("xs:annotation/xs:documentation", $el);
	$even = array();
	foreach ($documentations as $documentation) {
		$even[] = $documentation->nodeValue;
	}
	if (count($even) > 0) $docu = implode('; ',$even);
	
	//verzamel alle enumeration elementen BINNEN $el
	$enumerations = $xpath->query("xs:restriction/xs:enumeration", $el);
	$valueList = array();
	foreach ($enumerations as $enumeration) {
		$valueList[] = $enumeration->getAttribute('value');
	}
	
	if ($key) {
		$fields[$key]['name'] = $key; //eigenlijk overbodig
		if (strlen($docu) > 0) $fields[$key]['docu'] = $docu;
		if (count($valueList) > 0) $fields[$key]['values'] = $valueList;
	}
  }
  $resultarray = array();
  foreach ($fields as $field => $fieldarray){
    //print_r($fieldarray);
    $fieldarray['template'] = $field;
    $resultarray[$field] = $fieldarray;
  }
    add_option('WPfundaArray',$resultarray);


}