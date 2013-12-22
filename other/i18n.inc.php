<?php

/** Provides simple localization for PHP-driven web sites.
    Uses Java-style .properties files for storage of localized bits.

    I18N can be used on any PHP4-driven web site. It works with files only, needs no database.
    It is very flexible and can be used totally independent from the design parts of the site.
    It supports the global variable $lang, which can contain the current locale.

    I18N creates the file names with the translated strings from "$SCRIPT_NAME" by exchanging
    the extension (e.g. ".php") with "_p_XY.properties" and prepending the path "/i18n" to it.
    So, if the .php file is stored in /path/to/root/subdir/index.php, the .properties files
    would be /path/to/root/i18n/subdir/index_p_de.properties, /path/to/root/i18n/subdir/index_p_en.properties
    etc. The complete distinction between script directories and properties files directories
    is done by design, so you can pass the files to-be-translated to someone else without
    including the scripts very easily.

    I18N works with Java-style properties files. It supports locales with language or with
    language and country where languages and countries are abbrevated by there appropriate
    2-char ISO codes, e.g. "de" means "German" where "de_DE" means "German/Germany". "de_AT"
    would be "German/Austria".

    To define the available locales of a site, put a file "i18n.ini" into its root directory
    and define the locales line-by-line. First entry in each line is the locales description
    ("en", "de_CH", or "fr_CH"). It can be followed by any other locales acting as substitution
    for non-existing keys. Note however, that I18N loads the language-only locale as default
    substitution for each combined language-country locale, e.g. if you define "de_AT", "de" will
    be chosen as substitution automatically. Substitues are queried in the defined order if
    a key's localisation cannot be found in the primary locale.

    --- Example i18n.ini ---
    de_DE
    de_AT
    en de
    ---

    This means: The site is available in three locales: German/Germany, German/Austria, and English.
    For English, generic German is taken as substitute. Lets look at two lines of index.php:

    --- Example index.php ---
    $i18n->echolocalized("@month.jan");
    $i18n->echolocalized("@month.feb");
    ---

    The following three files do exist:

    --- Example index_p_de.properties ---
    month.jan=Januar
    month.feb=Februar
    --- Example index_p_de_AT.properties ---
    month.jan=Jänner
    --- Example index_p_en.properties ---
    month.jan=January
    ---

    These are the outputs:
    ---
    Key        de_DE    de_AT     en
    month.jan  Januar   Jänner    January
    month.feb  Februar  Februar   Februar
    ---

    Note that as there is no special file for "de_DE", I18N automatically falls back on the "de" file,
    while for the "de_AT" case, it takes the special notion Austrians are used to for January. February
    is taken from the "de" file for both, "de_DE" and "de_AT", and even for the "en" locale. The later one
    is due to "de" being explicitly defined as substitution locale for "en".

    (C) Dirk Hillbrecht/chitec 2003
*/
class i18n {

	var $values;        // storage for localized stuff
	var $languagename;  // names of languages
	var $countrynames;  // names of countries
	var $available;     // array of available localizations for this page/site
	var $mylocale;      // the current locale

// First part: Constructor

/** The constructor. Fills object with properties for locale.
    If properties are not given, constructs them from current script's name.
    If locale not given, read it from global $lang variable. */
function i18n($pathprefix="/i18n",$property="",$locale="") {
	global $lang,$DOCUMENT_ROOT,$SCRIPT_NAME;
	$this->_initLocaleNames();
	if ($pathprefix==null || empty($pathprefix))
		$pathprefix="/i18n";
	if (!is_array($pathprefix))
		$pathprefix=array($pathprefix);
	if (empty($property))
		$property=dirname($SCRIPT_NAME)."/".ereg_replace('\.[a-z]+$',"",basename($SCRIPT_NAME))."_p";
	else if ($property[strlen($property)-1]=="/")
		$property.=ereg_replace('\.[a-z]+$',"",basename($SCRIPT_NAME))."_p";
	if ($property[0]!="/")
		$property="/".$property;
	if (empty($locale))
		$locale=$lang;
	if (empty($locale))
		$locale="?";
	$locales=$this->_loadHierarchyDescription($locale);
	$this->mylocale=$locale;
	$this->values=$this->_loadAllPropFiles($pathprefix,$property,$locales);
}

// Second part: internal helper methods

/** Initializes the internal locale settings */
function _initLocaleNames() {
	$this->languagenames=array(
		"de"=>array("de"=>"Deutsch","en"=>"German","fr"=>"Allemand"),
		"en"=>array("en"=>"English","de"=>"Englisch","fr"=>"Anglais"),
		"fr"=>array("fr"=>"Francais","de"=>"Französisch","en"=>"French"),
		"sv"=>array("sv"=>"Svenska","en"=>"Swedish"),
		"no"=>array("no"=>"Norsk","en"=>"Norwegian")
	);
	$this->countrynames=array(
		"DE"=>array("de"=>"Deutschland","en"=>"Germany"),
		"UK"=>array("en"=>"United Kingdom","de"=>"Großbritannien"),
		"US"=>array("en"=>"United States","de"=>"USA"),
		"AT"=>array("de"=>"Österreich","en"=>"Austria"),
		"CH"=>array("de"=>"Schweiz","fr"=>"Suisse","en"=>"Switzerland"),
		"FR"=>array("fr"=>"France","de"=>"Frankreich","en"=>"France"),
		"CA"=>array("en"=>"Canada","fr"=>"Canada","de"=>"Kanada"),
		"SE"=>array("sv"=>"Sverige","de"=>"Schweden","en"=>"Sweden"),
		"NO"=>array("no"=>"Norge","en"=>"Norway","de"=>"Norwegen")
	);
}

/** Converts all occurances of Java \uXXXX Unicodes to HTML &#1234; replacements.
* @param string a string that will be 'recoded'
* @return string a string suitable for display in a browser
*/
function _utf16Converter( $str ) {
	while (ereg( '\u[0-9A-F]{4}',$str,$unicode )) {
		$repl="&#".hexdec( $unicode[0] ).";";
		$str=str_replace( $unicode[0],$repl,$str );
	}
	return $str;
}

/** Load one .properties file.
    Loads the properties and adds them to the passed array if they are not in there so far. */
function _loadPropFile($propfilebase,$locale,$retval=array()) {
	$langfile = empty($locale) ? $propfilebase.".properties" : $propfilebase."_".$locale.".properties";
	//echo "loading I18N file $langfile<br>\n";
	if ($input=@fopen($langfile,"r")) {
		while (!feof($input)) {
			$line="";
			do {
				if (strrchr( $line,"\\" ) == "\\")
					$line=substr( $line,0,strlen( $line )-1 );
				$line.=trim( fgets( $input,2000 ) );
				if (strlen( $line ) == 0)
					continue;
				if (strpos( $line,"#" ) !== false)
					$line=substr( $line,0,strpos( $line,"#" ) );
				if (strpos( $line,"\\u" ) !== false)
					$line=$this->_utf16Converter( $line );
			} while ((strrchr( $line,"\\" ) !== false) && (strrchr( $line,"\\" ) == "\\"));
			if (strlen( $line ) == 0)
				continue;
			$key=trim( substr( $line,0,strpos( $line,"=" ) ) );
			$value=trim( stripcslashes( substr( $line,strpos( $line,"=" )+1 ) ) );
//			echo "key: ".$key.", value: ".$value."<br>\n";
			if (!isset($retval[$key]))
				$retval[$key]=$value;
		}
	}
	return $retval;
}

/** Loads multiple .properties files in order.
    Gets a list of all .properties to be loaded. By starting with the most specific, this method
    guarantees that only stuff is loaded from all subsequent files that has not been inserted so far. */
function _loadAllPropFiles($pathprefixes,$propfilebase,$locales) {
	global $DOCUMENT_ROOT;
	$retval=array();
	foreach ($pathprefixes as $pathprefix) {
	foreach ($locales as $locale) {
		while (strlen($locale)>0) {
			$retval=$this->_loadPropFile($DOCUMENT_ROOT.$pathprefix.$propfilebase,$locale,$retval);
			$splitpos=strrpos($locale,"_");
			$locale=($splitpos===false?"":substr($locale,0,$splitpos));
		}
	}
	$retval=$this->_loadPropFile($DOCUMENT_ROOT.$pathprefix.$propfilebase,"",$retval);
	}
	return $retval;
}

/** Loads the hierarchy description file which defines alternative locales beside the standard ones. */
function _loadHierarchyDescription($mainlocale) {
	global $INI_PATH;
	$configfile=$INI_PATH;
	$this->available=array();
	if ($f=@fopen($configfile,"r")) {
		while (!feof($f)) {
			$current=explode(" ",trim(fgets($f,2000)));
			if (sizeof($current)<1 || strlen($current[0])<1 || $current[0][0]==="#")
				continue;
			$this->available[]=$current[0];
			if ($current[0]==$mainlocale)
				$retval=$current;
		}
	}
	if (!isset($retval))
		$retval=array();
	return $retval;
}

/**Internal Function
* Replaces parameter of the form {x} with some real value.
* @param string the string containing the tags to be replaced
* @param array an array containing the "replacements" $params[0] replaces {0} and so on...
* @return string guess what
*/
function _paramReplacer( $text,$params ) {
	if ($params === false)
		return $text;
	for ($i=0;$i<count( $params );$i++) {
		$search="{".$i."}";
		$text=str_replace( $search,$params[$i],$text );
	}
	return $text;
}

/** Takes a String as argument and replaces all occurrences of *name* with the content of the global variable $name */
function _local_parseglobvars($cond) {
	$thevars=array();
	$invarname=false;
	$currvarbegin=-1;
	for ($i=0;$i<strlen($cond);$i++) {
		if ($cond[$i]=="*") {
			if ($invarname) {
				$currvarname=substr($cond,$currvarbegin,$i-$currvarbegin);
				if (!isset($thevars[$currvarname]))
					$thevars[$currvarname]=eval("return \$GLOBALS[".$currvarname."];");
				$invarname=false;
			}
			else {
				$invarname=true;
				$currvarbegin=$i+1;
			}
		}
	}
	foreach ($thevars as $varname=>$varvalue) {
		$cond=str_replace("*".$varname."*",$varvalue,$cond);
	}
	return $cond;
}

/** Returns the first mentioned variable name in the given String, including its leading $-sign. */
function _local_getvarname($s) {
	ereg("(\\\$[A-Za-z0-9_]+)",$s,$regs=array());
	if (isset($regs[1]) && !empty($regs[1]))
		return $regs[1];
	else
		return false;
}

/** Parses a lagselect argument string containing **-expressions and *-var-references. */
function _parselangvar($s) {
	$s=ereg_replace("\*\*","¢",$s);
	$s=$this->_local_parseglobvars($s);
	$sa=explode("|",$s);
	for ($i=1;$i<count($sa);$i++) {
		$si=$sa[$i];
		if (substr($si,0,1)=="¢") {
			$varname=$this->_local_getvarname($si);
			$es=($varname===false?'':'global '.$varname.";").'return '.substr($si,1,strlen($si)-2).';';
			$ns=eval($es);
			$sa[$i]=$ns;
		}
	}
	$s=implode("|",$sa);
	return $s;
}

// Third part: Interface methods.

/** Return all localizable keys. */
function getKeys() {
	return array_keys($this->values);
}

/** Return the currently used locale */
function getLocale() {
	return $this->mylocale;
}

/** Get localized value for $key, low level version.
    Looks up the loaded properties and returns the appropriate value for the key.
    Replaces occurrences of {n} in the localized replacement with the n-th element in $param
    if available. If no localized replacement can be found, an internal presentation is
    returned for debugging purposes. */
function get($key,$params=false) {
	if (isset($this->values[$key]))
		return $this->_paramReplacer($this->values[$key],$params);
	else
		return $key.(is_array($params)?"|".implode("|",$params):"");
}

/** Returns an i18n version of the given $text variable. There are several possibilities what is returned:
    - if $text is an array, its keys are scanned for the $mylocale value and the corresponding value is
      taken as parameter for a recursive call of localize().
    - if $text is a String beginning with "°", the rest of $text is used as argument for a recursive
      call of localize, from which the result is transformed into UTF-8.
    - if $text is a String beginning with "@", it is replaced by the content of the i18n object. Parameters
      can either be given in the second parameter of the function or in $text, seperated by "|".
    - if $text is a String and begins with "#", a htmlentities()-ifyed version of $text is returned.
    - if $text is an otherwise formatted String, it is returned verbatim. */
function localize($text,$params=false) {
	if (is_array($text))
		$text=$text[$this->mylocale];
	if (substr($text,0,1)=='°')
		return utf8_encode($this->localize(substr($text,1),$params));
	else if (substr($text,0,1)=='@') {
		$splittext=explode("|",$this->_parselangvar(substr($text,1)));
		if (isset($splittext[1]))
			return $this->get($splittext[0],array_slice($splittext,1));
		else
			return $this->get(substr($text,1),$params);
	}
	else if (substr($text,0,1)=='#')
		return htmlentities($text);
	else
		return $text;
}

/** Echos the result of localize() */
function echolocalized($text,$params=false) {
	echo $this->localize($text,$params);
}

/** Echos a sequence of localize()d elements. */
function echolocalizedsequence($texts) {
	foreach ($texts as $text) {
		echo $this->localize($text);
	}
}

/** Adds the current locale as "lang" parameter to the given URL.
    Takes care of using either "?" or "&" as separator. */
function langurl($url,$langtourl=true) {
	return ($langtourl?$url.(strpos($url,"?")===false?"?":"&")."lang=".$this->mylocale:$url);
}

/** Removes any "lang=XXX" entries from the given URL's parameter */
function noLangURL($url) {
	$parts=explode("?",$url);
	if (isset($parts[1]))
		$params=ereg_replace("^&","",ereg_replace("(&)?lang=[a-zA-Z_]+","",$parts[1]));
	return $parts[0].((isset($params) && !empty($params))?"?".$params:"");
}

/** Returns the URL of the currently shown page/script without the lang=XXX part of the GET-paramters */
function noLangMyURL() {
	global $SERVER_NAME,$SCRIPT_NAME,$QUERY_STRING;
	return $this->noLangURL("http://".$SERVER_NAME.$SCRIPT_NAME.(empty($QUERY_STRING)?"":"?".$QUERY_STRING));
}

/** Forms a URL adding the lang to the URL and selecting the correct written value. */
function localizedlink($url,$text,$langtourl=true,$addurltext="") {
	return '<a href="'.$this->langurl($url,$langtourl).'"'.(empty($addurltext)?'':' '.$addurltext).'>'.
		$this->localize($text).'</a>';
}

/** Echos the result of localizelink() */
function echolocalizedlink($url,$text,$langtourl=true,$addurltext="") {
	echo $this->localizedlink($url,$text,$langtourl,$addurltext);
}

/** Returns all locales as given in the /i18n.ini file.
    "locale" is locale to be added to the URL, "name" the printable name, if "current" is set, this is the
    current locale.
    If $inlocale is set, the names are returned in the given locale. If it is set to "CURRENT", they are
    returned in the current locale. Otherwise, the names are returned in their genuine locale. This
    happens also if a name is not available in the wanted locale. */
function getAllLocales($inlocale="") {
	if ($inlocale=="CURRENT")
		$inlocale=$this->mylocale;
	$retval=array();
	foreach ($this->available as $currlocale) {
		$currparts=explode("_",$currlocale);
		$langnames=$this->languagenames[$currparts[0]];
		if (!empty($inlocale))
			$currlang=$langnames[$inlocale]; // the wanted
		if (empty($currlang))
			$currlang=$langnames[$currparts[0]]; // the genuine
		if (empty($currlang)) {
			$currlang=array_slice($langnames,0,1); //[0]; // the first
			$currlang=$currlang[0];
		}
		if (isset($currparts[1])) {
			$conames=$this->countrynames[$currparts[1]];
			if (!empty($inlocale))
				$currcountry=$conames[$inlocale]; // the wanted
			if (empty($currcountry))
				$currcountry=$conames[$currparts[0]]; // the genuine (country name in _language_)
			if (empty($currcountry)) {
				$currcountry=array_slice($conames,0,1);
				$currcountry=$currcountry[0]; // the first
			}
		}
		$retpart["locale"]=$currlocale;
		$retpart["name"]=$currlang.(empty($currcountry)?"":" (".$currcountry.")");
		if ($currlocale==$this->mylocale)
			$retpart["current"]=true;
		$retval[]=$retpart;
		unset($currlang);unset($currcountry);unset($retpart);
	}
	return $retval;
}

} // end of class

?>
