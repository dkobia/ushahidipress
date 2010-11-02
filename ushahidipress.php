<?php
/*
Plugin Name: Ushahidi for Wordpress
Plugin URI: http://dkfactor.com/2010/11/02/ushahidipress/
Description: Embed Ushahidi Maps in your Blog Post
Author: David Kobia
Version: 0.2
Author URI: http://www.dkfactor.com/
*/

/**
 * LICENSE
 * This file is part of Flickr Gallery.
 *
 * Ushahidi for Wordpress is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	 See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @package	   ushahidipress
 * @author	   David Kobia <david@dkfactor.com>
 * @copyright  Copyright 2010 David Kobia
 * @license	   http://www.gnu.org/licenses/gpl.txt GPL 2.0
 * @version	   0.2
 * @link	   http://www.dkfactor.com
 */

if ( ! defined("WP_CONTENT_URL") )
	define( "WP_CONTENT_URL", get_option("siteurl") . "/wp-content");
if ( ! defined("WP_CONTENT_DIR") )
	define( "WP_CONTENT_DIR", ABSPATH . "wp-content" );

define("USHAHIDI_EMBED_VERSION", "0.1");
	
class Ushahidi_Embed {
	
	/**
	 * Return the filesystem path that the plugin lives in.
	 *
	 * @return string
	 */
	function getPath()
	{
		return dirname(__FILE__) . "/";
	}
	
	/**
	 * Returns the URL of the plugins folder.
	 *
	 * @return string
	 */
	function getURL()
	{
		return WP_CONTENT_URL."/plugins/".basename(dirname(__FILE__)) . "/";
	}
	
	function get_major_version()
	{
		global $wp_version;
		return (float) $wp_version;
	}
	
	/**
	 * Initializes the Ushahidi Embed object.  Called on WP"s init hook.
	 */
	function init()
	{
		wp_enqueue_script("GoogleMaps", "http://maps.google.com/maps/api/js?sensor=false", array(), USHAHIDI_EMBED_VERSION);
		wp_enqueue_style('ushahidi', Ushahidi_Embed::getURL() . 'css/ushahidi.css', array(), USHAHIDI_EMBED_VERSION);
	}

	/**
	 * Handles the [ushahidi] shortcode
	 *
	 * @param array $attr
	 * @param string $url - ushahidi report url
	 * @return string
	 */
	function embed($attr, $url = "")
	{
		$attr = shortcode_atts(
			array(
				"height" => null,
				"width" => null,
				),
			$attr);
		
		$url = trim($url);	
		if ( ereg( '/([0-9]+)/?$', $url, $match) )
		{
			$incident_id = $match[1];
		}
		
		if ($url && (int) $incident_id)
		{
			$report = Ushahidi_Embed::curlget($url, $incident_id);
			if ($report)
			{
				?>
				<script type="text/javascript">
					jQuery(function() {
						var myLatlng = new google.maps.LatLng(<?php echo $report['latitude']; ?>,<?php echo $report['longitude']; ?>);
						var myOptions = {
						  zoom: 12,
						  center: myLatlng,
						  mapTypeId: google.maps.MapTypeId.ROADMAP
						}

						var map = new google.maps.Map(document.getElementById("ushahidi_map"), myOptions);
						var contentString = '<div id="ushahidi_content">'+
							'<div class="ushahidi_title"><a href="<?php echo $report['url']; ?>" target="_blank"><?php echo $report['incident_title']; ?></a></div>'+
							'<div class="ushahidi_description"><?php echo $report['incident_description']; ?></div>'+
							'<div class="ushahidi_more"><a href="<?php echo $report['url']; ?>" target="_blank">Read More...</a></div>'+
							'</div>';
						var infowindow = new google.maps.InfoWindow({
							content: contentString
						});
						var marker = new google.maps.Marker({
							position: myLatlng,
							map: map,
							title: '<?php echo $report['incident_title']; ?>'
						});
						google.maps.event.addListener(marker, 'click', function() {
							infowindow.open(map,marker);
						});
						marker.setMap(map);
					});
				</script>
				<div class="ushahidi_map" id="ushahidi_map"></div>
				<?php
			}
		}
	}
	
	private function curlget($url, $incident_id)
	{
		$regex = '.*?(reports\\/view\\/\\d+)';	# Non-greedy match on filler

		if ($c=preg_match_all ("/".$regex."/is", $url, $matches))
		{
			$current = $matches[1][0];
		}
		$base_url = str_replace($current, "", $url);
		
		$ch = curl_init();
		$timeout = 5;
		$api_url = $base_url."api?task=incidents&by=sinceid&id=".($incident_id - 1)."&resp=json&limit=1&orderfield=incidentid&sort=0";
		
		curl_setopt($ch,CURLOPT_URL,$sharing_url.$api_url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
		$json = curl_exec($ch);
		curl_close($ch);
		
		$all_data = json_decode($json, false);
		if ( ! $all_data)
		{
			return false;
		}
		
		if ( ! isset($all_data->payload->incidents))
		{
			return false;
		}
		
		// Parse Incidents Into Database
		foreach($all_data->payload->incidents as $incident)
		{
			$report = array();
			$report['incident_id'] = $incident->incident->incidentid;
			$report['incident_title'] = preg_replace("/\r?\n/", "\\n", addslashes($incident->incident->incidenttitle));
			$report['incident_description'] = preg_replace("/\r?\n/", "\\n", addslashes($incident->incident->incidentdescription));
			$report['latitude'] = $incident->incident->locationlatitude;
			$report['longitude'] = $incident->incident->locationlongitude;
			$report['incident_date'] = $incident->incident->incidentdate;
			$report['url'] = addslashes($url);
				
			return $report;
		}
	}
}

add_shortcode("ushahidi", array("Ushahidi_Embed", "embed"));
add_action("init", array("Ushahidi_Embed", "init"));

?>