<?php
/*
Plugin Name: Pretty Link Post-Meta Autoreplace
Plugin URI: http://github.com/Caseproof/plp-postmeta-autoreplace
Description: Ties Pretty Link Pro's autoreplace capability into post meta fields that are specified in the admin
Version: 1.0.0
Author: Caseproof
Author URI: http://caseproof.com
Text Domain: plp-postmeta-autoreplace
Copyright: 2004-2013, Caseproof, LLC

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if(is_plugin_active('pretty-link/pretty-link.php')) {
  if(file_exists(WP_PLUGIN_DIR . '/pretty-link/pro/pretty-link-pro.php')) {
    class PlpPmAutoreplace {
      public function __construct() {
        add_action('admin_menu', array($this,'menu'));
        add_filter('get_post_metadata', array($this,'autoreplace'), 10, 4);
      }

      public function menu() {
        add_options_page( __('PLP PM Autoreplace'),
                          __('PLP PM Autoreplace'),
                          'manage_options',
                          'plp-pm-autoreplace',
                          array($this,'options') );
      }

      public function options() {
        if(!is_admin() or !current_user_can('manage_options')) { wp_die(__('Whoa pardner ... you don\'t have access to that')); }

        $method = strtolower($_SERVER['REQUEST_METHOD']);

        if($method == 'get')
          $this->display();
        elseif($method == 'post')
          $this->process();
      }

      public function autoreplace( $check, $object_id, $meta_key, $single ) {
        global $wpdb, $prli_link, $prli_blogurl;

        // We don't want to return the pretty link if we're in the admin
        if( is_admin() ) { return $check; }

        $autoreplace = get_option('plp_pm_autoreplace');
        $postmetas = array_map('trim',explode(',',$autoreplace));

        if( !in_array( $meta_key, $postmetas ) ) { return $check; }

        if( $single )
          $urls = $wpdb->get_col( $wpdb->prepare('SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key=%s and post_id=%d', $meta_key, $object_id) );
        else
          $urls = $wpdb->get_col( $wpdb->prepare('SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key=%s and post_id=%d ORDER BY meta_id DESC LIMIT 1', $meta_key, $object_id) );

        if(empty($urls)) { return $check; }

        $struct = PrliUtils::get_permalink_pre_slug_uri();
        $pls = array();

        for( $i = 0; $i < count($urls); $i++ ) {
          if($pl = $prli_link->get_or_create_pretty_link_for_target_url( $urls[$i] ))
            $pls[$i] = "{$prli_blogurl}{$struct}{$pl->slug}";
        }

        if(empty($pls)) { return $check; }
        else if($single) { return $pls[0]; }
        else { return $pls; }
      }

      private function display($message = '') {
        $autoreplace = get_option('plp_pm_autoreplace');

        ?>
        <h2><?php _e('Pretty Link Pro Post-Meta Autoreplace'); ?></h2>
        <?php if(!empty($message)): ?>
          <div class="updated"><?php echo $message; ?></div>
        <?php endif; ?>

        <form action="" method="post">
          <label for="plp_pm_autoreplace"><?php _e('Post Meta Keys to perform autoreplace on (comma separated):'); ?></label><br/>
          <input type="text" value="<?php echo $autoreplace; ?>" name="plp_pm_autoreplace" size="150" /><br/>
          <input type="submit" value="<?php _e('Submit'); ?>">
        </form>
        <?php
      }

      private function process() {
        $message = '';
        if(isset($_POST['plp_pm_autoreplace'])) {
          update_option('plp_pm_autoreplace',$_POST['plp_pm_autoreplace']);
          $message = __('Your options have been updated successfully');
        }

        $this->display($message);
      }
    }

    new PlpPmAutoreplace();
  }
}

