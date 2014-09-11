<?php
/*

Plugin Name: Forms-3rdparty Xml Post
Plugin URI: https://github.com/zaus/forms-3rdparty-xpost
Description: Converts submission from <a href="http://wordpress.org/plugins/forms-3rdparty-integration/">Forms 3rdparty Integration</a> to xml, add headers
Author: zaus, leadlogic
Version: 0.3
Author URI: http://drzaus.com
Changelog:
	0.1 init
	0.2 nesting
	0.3 doesn't need to be xml to nest, wrap
*/



class Forms3rdpartyXpost {

	const N = 'Forms3rdpartyXpost';
	const B = 'Forms3rdPartyIntegration';

	public function Forms3rdpartyXpost() {
		// attach a little later so other plugins can bypass submission 
		add_filter(self::B.'_service_filter_args', array(&$this, 'post_args'), 12, 3);

		// just provides a listing of placeholders
		// add_filter(self::B.'_service_metabox', array(&$this, 'service_metabox'), 10, 4);

		// configure whether to attach or not, how
		add_filter(self::B.'_service_settings', array(&$this, 'service_settings'), 10, 3);
	}

	const PARAM_HEADER = 'xpost-header';
	const PARAM_ASXML = 'as-xpost';
	const PARAM_WRAPPER = 'xpost-wrapper';
	const PARAM_SEPARATOR = '/';


	
	public function post_args($args, $service, $form) {

		// scan the post args for meta instructions

		// check for headers in the form of a querystring
		if( isset($service[self::PARAM_HEADER]) && !empty($service[self::PARAM_HEADER]) ) {
			parse_str($service[self::PARAM_HEADER], $headers);
			// do we already have some? merge
			if(isset($args['headers'])) {
				$args['headers'] = array_merge( (array)$args['headers'], $headers );
			}
			else {
				$args['headers'] = $headers;
			}
		}

		// nest tags
		$args['body'] = $this->nest($args['body']);

		### _log('post-args nested', $body);
		
		// do we have a custom wrapper?
		if(isset($service[self::PARAM_WRAPPER])) {
			$wrapper = array_reverse( explode(self::PARAM_SEPARATOR, $service[self::PARAM_WRAPPER]) );
		}
		else {
			$wrapper = array('post');
		}
		
		// loop through wrapper to wrap
		$root = array_pop($wrapper); // save terminal wrapper as root
		foreach($wrapper as $el) {
			$args['body'] = array($el => $args['body']);
		}

		// are we sending this form as xml?
		if(isset($service[self::PARAM_ASXML]) && 'true' == $service[self::PARAM_ASXML])
			$args['body'] = $this->simple_xmlify($args['body'], null, $root)->asXML();
		else $args['body'] = array($root => $args['body']);
		
		### _log('xmlified body', $body, 'args', $args);

		return $args;
	}//--	fn	post_args


	function nest($body) {
		// scan body to turn depth-2 into nested depth-n list
		// need a new target so we can enumerate the original
		$nest = array();

		foreach($body as $k => $v) {
			if(false === strpos($k, self::PARAM_SEPARATOR)) continue;
		
			// remove original
			unset($body[$k]);
		
			// split, reverse, and russian-doll the values for each el
			$els = array_reverse(explode(self::PARAM_SEPARATOR, $k));

			foreach($els as $e) {
				$v = array($e => $v);
			}

			// attach to new result so we don't dirty the enumerator
			$nest = array_merge_recursive($nest, $v);
		}
	
		return array_merge($nest, $body);
	}//--	fn	nest
	
	function simple_xmlify($arr, SimpleXMLElement $root = null, $el = 'x') {
		// could use instead http://stackoverflow.com/a/1397164/1037948
	
		if(!isset($root) || null == $root) $root = new SimpleXMLElement('<' . $el . '/>');

		if(is_array($arr)) {
			foreach($arr as $k => $v) {
				// special: attributes
				if(is_string($k) && $k[0] == '@') $root->addAttribute(substr($k, 1),$v);
				// normal: append
				else $this->simple_xmlify($v, $root->addChild(
						// fix 'invalid xml name' by prefixing numeric keys
						is_numeric($k) ? 'n' . $k : $k)
					);
			}
		} else {
			$root[0] = $arr;
		}

		return $root;
	}//--	fn	simple_xmlify


	// not used...here just in case we want inline help
	public function service_metabox($P, $entity) {

		?>
		<div id="metabox-<?php echo self::N; ?>" class="meta-box">
		<div class="shortcode-description postbox" data-icon="?">
			<h3 class="hndle"><span><?php _e('Xml Post', $P) ?></span></h3>
			
			<div class="description-body inside">

				<p class="description"><?php _e('Configure how to transform service post body into XML, and/or set headers.', $P) ?></p>
				<p class="description"><?php _e('Note: you may also specify these values per service as &quot;special&quot; mapped values -- see each field for instructions.', $P) ?></p>

				
			</div><!-- .inside -->
		</div>
		</div><!-- .meta-box -->
	<?php

	}//--	fn	service_metabox

	public function service_settings($eid, $P, $entity) {
		?>
		<fieldset><legend><span><?php _e('Xml Post'); ?></span></legend>
			<div class="inside">
				<p class="description"><?php _e('Configure how to transform service post body into XML, and/or set headers.', $P) ?></p>

				<?php $field = self::PARAM_ASXML; ?>
				<div class="field">
					<label for="<?php echo $field, '-', $eid ?>"><?php _e('Post service as XML?', $P); ?></label>
					<input id="<?php echo $field, '-', $eid ?>" type="checkbox" class="checkbox" name="<?php echo $P, '[', $eid, '][', $field, ']'?>" value="true"<?php echo isset($entity[$field]) ? ' checked="checked"' : ''?> />
					<em class="description"><?php _e('Should all services transform post body to xml?', $P);?></em>
				</div>
				<?php $field = self::PARAM_WRAPPER; ?>
				<div class="field">
					<label for="<?php echo $field, '-', $eid ?>"><?php _e('Xml Root Element(s)', $P); ?></label>
					<input id="<?php echo $field, '-', $eid ?>" type="text" class="text" name="<?php echo $P, '[', $eid, '][', $field, ']'?>" value="<?php echo isset($entity[$field]) ? esc_attr($entity[$field]) : 'post'?>" />
					<em class="description"><?php _e('Wrap contents all xml-transformed posts with this root element.  You may specify more than one by separating names with forward-slash', $P);?> (<code>/</code>).</em>
				</div>
				<?php $field = self::PARAM_HEADER; ?>
				<div class="field">
					<label for="<?php echo $field, '-', $eid ?>"><?php _e('Post Headers', $P); ?></label>
					<input id="<?php echo $field, '-', $eid ?>" type="text" class="text" name="<?php echo $P, '[', $eid, '][', $field, ']'?>" value="<?php echo isset($entity[$field]) ? esc_attr($entity[$field]) : ''?>" />
					<em class="description"><?php _e('Override the post headers for all posts.  You may specify more than one by providing in &quot;querystring&quot; format', $P);?> (<code>Accept=json&amp;Content-Type=whatever</code>).</em>
				</div>
			</div>
		</fieldset>
		<?php
	}//--	fn	service_settings



}//---	class	Forms3partydynamic

// engage!
new Forms3rdpartyXpost();