<?php
namespace bbn\html;
/**
 * Model View Controller Class
 *
 *
 * This class will route a request to the according model and/or view through its controller.
 * A model and a view can be automatically associated if located in the same directory branch with the same name than the controller in their respective locations
 * A view can be directly imported in the controller through this very class
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  MVC
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 * @todo Merge the output objects and combine JS strings.
 * @todo Stop to rely only on sqlite and offer file-based or any db-based solution.
 * @todo Look into the check function and divide it
 */
class input
{
	public
	/**
	 * Is set to null while not routed, then 1 if routing was sucessful, and false otherwise.
	 * @var null|boolean
	 */
		$tag = 'input',
	/**
	 * Is set to null while not routed, then 1 if routing was sucessful, and false otherwise.
	 * @var null|boolean
	 */
	 	$id,
	/**
	 * The controller file (with full path)
	 * @var null|string
	 */
		$name,
	/**
	 * The controller file (with full path)
	 * @var null|string
	 */
		$label,
	/**
	 * The mode of the output (dom, html, json, txt, xml...)
	 * @var null|string
	 */
		$value = '';
	/**
	 * The mode of the output (dom, html, json, txt, xml...)
	 * @var null|string
	 */
	private $cfg,
		$options = array(),
		$html = '',
		$script;
	/**
	 * This will build a new HTML form element according to the given configuration.
	 * Only name and tag are mandatory, then other values depending on the tag
	 *
	 * @param array $cfg The element configuration
	 */
	public function __construct(array $cfg = null)
	{
		if ( isset($cfg['name'], $cfg['tag']) )
		{
			$mandatory_opt = array();
			$possible_opt = array("required", "width", "placeholder", "cssclass", "title");
			$this->cfg = $cfg;
			$this->tag = strtolower($cfg['tag']);
			$this->name = $cfg['name'];
			$this->id = isset($cfg['id']) ? $cfg['id'] : \bbn\str\text::genpwd(20,15);
			$this->label = isset($cfg['label']) ? $cfg['label'] : str_replace(']', ')',str_replace('[',' (',str_replace('_', ' ', $cfg['name'])));
			$this->required = isset($cfg['required']) ? $cfg['required'] : false;
			$this->options = isset($cfg['options']) ? $cfg['options'] : array();
			$this->script = isset($cfg['script']) ? $cfg['script'] : '';
			$this->value = isset($cfg['value']) ? $cfg['value'] : '';
			switch ( $this->tag )
			{
				case "input":
					array_push($mandatory_opt, "type");
					array_push($possible_opt, "minlength", "maxlength", "size", "rangelength", "min", "max", "range", "email", "url", "date", "number", "digits", "creditcard", "equalTo");
					break;
				case "textarea":
					array_push($mandatory_opt, "cols", "rows");
					array_push($possible_opt, "minlength", "maxlength", "rangelength", "height");
					break;
				case "select":
					array_push($mandatory_opt, "options");
					array_push($possible_opt, "multiple");
					break;
				case "file":
					array_push($possible_opt, "multiple", "accept");
					break;
			}
			foreach ( $mandatory_opt as $m ){
				if ( isset($cfg['options'][$m]) ){
					$this->options[$m] = $cfg['options'][$m];
				}
				else{
					die("Argument $m is missing in your config... Sorry!");
				}
			}
			foreach ( $cfg['options'] as $k => $v ){
				if ( in_array($k, $possible_opt) ){
					$this->options[$k] = $v;
				}
			}
		}
	}
	
	/**
	 * Returns the current configuration.
	 */
	public function get_config()
	{
		return $this->cfg;
	}

	/**
	 * Returns a HTML string with a label and input, using the App-UI restyler classes.
	 */
	public function get_label_input()
	{
		$s = $this->get_html();
		if ( !empty($s) ){
			if ( BBN_IS_DEV ){
				$title = str_replace('"','',print_r($this->cfg,true));
			}
			else if ( isset($this->options['title']) ){
				$title = $this->options['title'];
			}
			else{
				$title = '';
			}
			$s = '<label class="appui-form-label" title="'.$title.'">'.$this->label.'</label><div class="appui-form-field">'.$s.'</div>';
		}
		return $s;
	}
	
	/**
	 * Returns the javascript coming with the object.
	 */
	public function get_script()
	{
		$r = '';
		if ( $this->name ){
			
			if ( $this->id ){
				$r .= '$("#'.$this->id.'").focus(function(){
					var $$ = $(this),
					lab = $(this).prevAll("label").first();
					if ( lab.length === 0 ){
						lab = $$.parent().prevAll("label").first();
					}
					if ( lab.length === 1 ){
						var o = $$.parent().offset(), 
							w = lab.width(),
							$boum = $(\'<div class="k-tooltip" id="form_tooltip" style="position:absolute">Ceci est un test</div>\')
								.css({
									"maxWidth": w,
									"top": o.top-10,
									"right": appui.v.width - o.left
								});
						$("body").append($boum);
					}
				}).blur(function(){
					$("#form_tooltip").remove();
				});';
			}
			if ( $this->script ){
				$r .= $this->script;
			}
		}
		return $r;
	}
	
	/**
	 * Returns the HTML string for the object.
	 */
	public function get_html()
	{
		if ( empty($this->html) && $this->name ){
			
			$this->html .= '<'.$this->tag.' name="'.$this->name.'"';
			
			if ( isset($this->id) ){
				$this->html .= ' id="'.$this->id.'"';
			}
			
			if ( $this->tag === 'input' && isset($this->options['type']) ){
				$this->html .= ' type="'.$this->options['type'].'"';
				
				if ( $this->options['type'] === 'text' || $this->options['type'] === 'number' || $this->options['type'] === 'password' || $this->options['type'] === 'email' ){
					if ( isset($this->options['maxlength']) && ( $this->options['maxlength'] > 0 ) && $this->options['maxlength'] <= 1000 ){
						$this->html .= ' maxlength="'.$this->options['maxlength'].'"';
					}
					if ( isset($this->options['minlength']) && ( $this->options['minlength'] > 0 ) && $this->options['minlength'] <= 1000 && ( 
					( isset($this->options['maxlength']) && $this->options['maxlength'] > $this->options['minlength'] ) || !isset($this->options['maxlength']) ) ){
						$this->html .= ' minlength="'.$this->options['minlength'].'"';
					}
					if ( isset($this->options['size']) && ( $this->options['size'] > 0 ) && $this->options['size'] <= 100 ){
						$this->html .= ' size="'.$this->options['size'].'"';
					}
				}
				if ( $this->options['type'] === 'checkbox' ){
					if ( !isset($this->options['value']) ){
						$this->options['value'] = 1;
					}
					$this->html .= ' value="'.htmlentities($this->options['value']).'"';
					if ( $this->value == $this->options['value'] ){
						$this->html .= ' checked="checked"';
					}
				}
				else if ( $this->options['type'] === 'radio' ){
					
				}
				else{
					$this->html .= ' value="'.htmlentities($this->value).'"';
				}
			}
			
			if ( isset($this->options['title']) ){
				$this->html .= ' title="'.$this->options['title'].'"';
			}
			
			$class = '';
			
			if ( isset($this->options['cssclass']) ){
				$class .= $this->options['cssclass'].' ';
			}

			if ( $this->required ){
				$class .= 'required ';
			}
			if ( isset($this->options['email']) ){
				$class .= 'email ';
			}
			if ( isset($this->options['url']) ){
				$class .= 'url ';
			}
			if ( isset($this->options['number']) ){
				$class .= 'number ';
			}
			if ( isset($this->options['digits']) ){
				$class .= 'digits ';
			}
			if ( isset($this->options['creditcard']) ){
				$class .= 'creditcard ';
			}
			
			if ( !empty($class) ){
				$this->html .= ' class="'.trim($class).'"';
			}
			
			$this->html .= '>';
			
			if ( $this->tag === 'select' || $this->tag === 'textarea' ){
				$this->html .= '</'.$this->tag.'>';
			}
			
			if ( isset($this->options['placeholder']) && strpos($this->options['placeholder'],'%s') !== false ){
				$this->html = sprintf($this->options['placeholder'], $this->html);
			}
		}
		return $this->html;
	}
	
}
?>