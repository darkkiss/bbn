<?php
/**
 * @package bbn\str
 */
namespace bbn\str;
/**
 * String manipulation class
 *
 *
 * This class only uses static methods and has lots of alias for the escaping methods
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Strings
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class text 
{

	/**
	 * @return void 
	 */
	public static function escape_dquotes($st)
	{
		return mb_ereg_replace('"','\"',$st);
	}

	/**
	 * @return void 
	 */
	public static function escape_dquote($st)
	{
		return self::escape_dquotes($st);
	}

	/**
	 * @return void 
	 */
	public static function escape_quote($st)
	{
		return self::escape_dquotes($st);
	}

	/**
	 * @return void 
	 */
	public static function escape_quotes($st)
	{
		return self::escape_dquotes($st);
	}

	/**
	 * @return void 
	 */
	public static function escape_squotes($st)
	{
		return mb_ereg_replace("'","\'",$st);
	}

	/**
	 * @return void 
	 */
	public static function escape($st)
	{
		return self::escape_squotes($st);
	}

	/**
	 * @return void 
	 */
	public static function escape_apo($st)
	{
		return self::escape_squotes($st);
	}

	/**
	 * @return void 
	 */
	public static function escape_squote($st)
	{
		return self::escape_squotes($st);
	}

	/**
	 * @return void 
	 */
	public static function clean($st, $mode='all')
	{
		if ( is_array($st) )
		{
			reset($st);
			$i = count($st);
			if ( trim($st[0]) == '' )
			{
				array_splice($st,0,1);
				$i--;
			}
			if ( $i > 0 )
			{
				if ( trim($st[$i-1]) == '' )
				{
					array_splice($st,$i-1,1);
					$i--;
				}
			}
			return $st;
		}
		else
		{
			if ( $mode == 'all' )
			{
				$st = mb_ereg_replace("\n",'\n',$st);
				$st = mb_ereg_replace("[\t\r]","",$st);
				$st = mb_ereg_replace('\s{2,}',' ',$st);
			}
			else if ( $mode == '2nl' )
			{
				$st = mb_ereg_replace("[\r]","",$st);
				$st = mb_ereg_replace("\n{2,}","\n",$st);
			}
			else if ( $mode == 'html' )
			{
				$st = mb_ereg_replace("[\t\r\n]",'',$st);
				$st = mb_ereg_replace('\s{2,}',' ',$st);
			}
			else if ( $mode == 'code' )
			{
				$st = mb_ereg_replace("!/\*.*?\*/!s",'',$st); // comment_pattern
				$st = mb_ereg_replace("[\r\n]",'',$st);
				$st = mb_ereg_replace("\t"," ",$st);
				$chars = array(';','=','+','-','\(','\)','\{','\}','\[','\]',',',':');
				foreach ( $chars as $char )
				{
					while ( mb_strpos($st,$char.' ') !== false ){
						$st = mb_ereg_replace($char.' ',$char,$st);
					}
					while ( mb_strpos($st,' '.$char) !== false ){
						$st = mb_ereg_replace(' '.$char,$char,$st);
					}
				}
				$st = mb_ereg_replace('<\?p'.'hp','<?p'.'hp ',$st);
				$st = mb_ereg_replace('\?'.'>','?'.'> ',$st);
				$st = mb_ereg_replace('\s{2,}',' ',$st);
			}
			return trim($st);
		}
	}

	/**
	 * @return void 
	 */
	public static function cut($st, $max)
	{
		$st = mb_ereg_replace('&nbsp;',' ',$st);
		$st = mb_ereg_replace('\n',' ',$st);
		$st = strip_tags($st);
		$st = html_entity_decode($st,ENT_QUOTES,'UTF-8');
		$st = self::clean($st);
		if ( mb_strlen($st) >= $max )
		{
			$chars = array(' ','.','/','\\');
			$ends = array();
			$st = mb_substr($st,0,$max);
			foreach ( $chars as $char )
			{
				$end = mb_strrpos($st,$char);
				if ( $end !== false )
					array_push($ends,$end);
			}
			if ( count($ends) > 0 )
				$st = mb_substr($st,0,max($ends)).'...';
			else
				$st = mb_substr($st,0,-3).'...';
		}
		return $st;
	}

	/**
	 * @return void 
	 */
	public static function encode_filename($st)
	{
		$st = self::remove_accents($st);
		$res = '';
		for ( $i = 0; ( $i < mb_strlen($st) ) && mb_strlen($res) < 50; $i++ )
		{
			if ( mb_ereg_match('[A-z0-9]',mb_substr($st,$i,1)) )
				$res .= mb_substr($st,$i,1);
			else if ( mb_strlen($res) > 0 && mb_substr($res,-1) != '_' && $i < ( mb_strlen($st) - 1 ) )
				$res .= '_';
		}
		return $res;
	}

	/**
	 * @return void 
	 */
	public static function file_ext($file, $ar=false)
	{
		if ( mb_strrpos($file,'/') !== false )
			$file = substr($file,mb_strrpos($file,'/')+1);
		if ( mb_strpos($file,'.') !== false )
		{
			$p = mb_strrpos($file,'.');
			$f = mb_substr($file,0,$p);
			$ext = mb_convert_case(mb_substr($file,$p+1),MB_CASE_LOWER);
			if ( $ar )
				return array($f,$ext);
			else
				return $ext;
		}
		else if ( $ar )
				return array($file,'');
		else
			return '';
	}

	/**
	 * @return void 
	 */
	public static function genpwd($int_max=12, $int_min=6)
	{
		mt_srand();
		if ($int_min > 0)
			$longueur = mt_rand($int_min,$int_max);
		else
			$longueur = $int_max;
		$mdp = '';
		for($i=0; $i<$longueur; $i++)
		{
			$quoi= mt_rand(1,3);
			switch($quoi)
			{
				case 1: 
					$mdp .= mt_rand(0,9);
					break;
				case 2:
					$mdp .= chr(mt_rand(65,90));
					break;
				case 3:
					$mdp .= chr(mt_rand(97,122));
					break;
			}
		}
		return $mdp;
	}

	/**
	 * @return void 
	 */
	public static function is_email($email)
	{
		if ( function_exists('filter_var') )
			return filter_var($email,FILTER_VALIDATE_EMAIL);
		else
		{
			$isValid = true;
			$atIndex = mb_strrpos($email, "@");
			if (is_bool($atIndex) && !$atIndex)
			{
				$isValid = false;
			}
			else
			{
				$domain = mb_substr($email, $atIndex+1);
				$local = mb_substr($email, 0, $atIndex);
				$localLen = mb_strlen($local);
				$domainLen = mb_strlen($domain);
				//  local part length exceeded
				if ($localLen < 1 || $localLen > 64)
					$isValid = false;
				//  domain part length exceeded
				else if ($domainLen < 1 || $domainLen > 255)
					$isValid = false;
				// local part starts or ends with '.'
				else if ($local[0] == '.' || $local[$localLen-1] == '.')
					$isValid = false;
				// local part has two consecutive dots
				else if (mb_ereg_match('\\.\\.', $local))
					$isValid = false;
				// character not valid in domain part
				else if (!mb_ereg_match('^[A-Za-z0-9\\-\\.]+$', $domain))
					$isValid = false;
				//  domain part has two consecutive dots
				else if (mb_ereg_match('\\.\\.', $domain))
					$isValid = false;
				//  character not valid in local part unless
				else if ( !mb_ereg_match('^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$'
				,str_replace("\\\\","",$local)))
				{
					// local part is quoted
					if ( !mb_ereg_match('^"(\\\\"|[^"])+"$',str_replace("\\\\","",$local)) )
						$isValid = false;
				}
			}
			return $isValid;
		}
	}

	/**
	 * @return void 
	 */
	public static function parse_url($url)
	{
		$r = array('url' => $url,'query' => '','params' => array());
		if ( strpos($url,'?') > 0 )
		{
			$p = explode('?',$url);
			$r['url'] = $p[0];
			$r['query'] = $p[1];
			$ps = explode('&',$r['query']);
			foreach ( $ps as $p )
			{
				$px = explode('=',$p);
				$r['params'][$px[0]] = $px[1];
			}
		}
		return $r;
	}

	/**
	 * @return void 
	 */
	public static function remove_accents($st)
	{
		$st = trim(mb_ereg_replace('&(.)(tilde|circ|grave|acute|uml|ring|oelig);', '\\1', $st));
		$search = explode(",","ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u,ą,ń,ł,ź,ę,À,Á,Â,Ã,Ä,Ç,È,É,Ê,Ë,Ì,Í,Î,Ï,Ñ,Ò,Ó,Ô,Õ,Ö,Ù,Ú,Û,Ü,Ý,Ł,Ś");
		$replace = explode(",","c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u,a,n,l,z,e,A,A,A,A,A,C,E,E,E,E,I,I,I,I,N,O,O,O,O,O,U,U,U,U,Y,L,S");
    foreach ( $search as $i => $s )
			$st = mb_ereg_replace($s, $replace[$i], $st);
   	return $st;
	}
	
	/**
	 * Checks if a string comply with SQL naming convention
	 * 
	 * @return bool
	 */
	public static function check_name(){

		$args = func_get_args();
		// Each argument must be a string starting with a letter, and having than one character made of letters, numbers and underscores
		foreach ( $args as $a ){
			$t = preg_match('#[A-z]+[A-z0-9_]*#',$a,$m);
			if ( $t !== 1 || $m[0] !== $a ){
				return false;
			}
		}
		
		return true;
	}
	
 /**
	* Extracts all digits from a string
	* 
	* @return bool
	*/
	public static function get_numbers($st){
		return preg_replace("/[^0-9]/", '', $st);
	}

}
?>