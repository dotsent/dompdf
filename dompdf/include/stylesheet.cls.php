<?php
/**
 * DOMPDF - PHP5 HTML to PDF renderer
 *
 * File: $RCSfile: stylesheet.cls.php,v $
 * Created on: 2004-06-01
 *
 * Copyright (c) 2004 - Benj Carson <benjcarson@digitaljunkies.ca>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this library in the file LICENSE.LGPL; if not, write to the
 * Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA
 * 02111-1307 USA
 *
 * Alternatively, you may distribute this software under the terms of the
 * PHP License, version 3.0 or later.  A copy of this license should have
 * been distributed with this file in the file LICENSE.PHP .  If this is not
 * the case, you can obtain a copy at http://www.php.net/license/3_0.txt.
 *
 * The latest version of DOMPDF might be available at:
 * http://www.digitaljunkies.ca/dompdf
 *
 * @link http://www.digitaljunkies.ca/dompdf
 * @copyright 2004 Benj Carson
 * @author Benj Carson <benjcarson@digitaljunkies.ca>
 * @package dompdf
 * @version 0.3
 */

/* $Id: stylesheet.cls.php,v 1.15 2006-07-06 23:34:02 benjcarson Exp $ */

/**
 * The location of the default built-in CSS file.
 * {@link Stylesheet::DEFAULT_STYLESHEET}
 */
define('__DEFAULT_STYLESHEET', DOMPDF_LIB_DIR . DIRECTORY_SEPARATOR . "res" . DIRECTORY_SEPARATOR . "html.css");

/**
 * The master stylesheet class
 *
 * The Stylesheet class is responsible for parsing stylesheets and style
 * tags/attributes.  It also acts as a registry of the individual Style
 * objects generated by the current set of loaded CSS files and style
 * elements.
 *
 * @see Style
 * @package dompdf
 */
class Stylesheet {
  
  

  /**
   * the location of the default built-in CSS file.
   * 
   */
  const DEFAULT_STYLESHEET = __DEFAULT_STYLESHEET; // Hack: can't
                                                   // concatenate stuff in
                                                   // const declarations,
                                                   // but I can do this?
  // protected members

  /**
   *  array of currently defined styles
   *  @var array
   */
  private $_styles;

  /**
   * base protocol of the document being parsed
   *
   * Used to handle relative urls.
   *
   * @var string 
   */
  private $_protocol;

  /**
   * base hostname of the document being parsed
   *
   * Used to handle relative urls.
   * @var string
   */
  private $_base_host;

  /**
   * base path of the document being parsed
   *
   * Used to handle relative urls.
   * @var string
   */
  private $_base_path;

  
  /**
   * the style defined by @page rules 
   *
   * @var Style
   */
  private $_page_style;


  /**
   * list of loaded files, used to prevent recursion
   *
   * @var array
   */
  private $_loaded_files;
  
  /**
   * accepted CSS media types 
   */
  static $ACCEPTED_MEDIA_TYPES = array("all", "static", "visual",
                                       "bitmap", "paged", "print");
  
  /**
   * The class constructor.
   *
   * The base protocol, host & path are initialized to those of
   * the current script.
   */
  function __construct() {
    $this->_styles = array();
    $this->_loaded_files = array();
    list($this->_protocol, $this->_base_host, $this->_base_path) = explode_url($_SERVER["SCRIPT_FILENAME"]);
    $this->_page_style = null;
  }

  /**
   * Set the base protocol
   *
   * @param string $proto
   */
  function set_protocol($proto) { $this->_protocol = $proto; }

  /**
   * Set the base host
   *
   * @param string $host
   */
  function set_host($host) { $this->_base_host = $host; }

  /**
   * Set the base path
   *
   * @param string $path
   */
  function set_base_path($path) { $this->_base_path = $path; }


  /**
   * Return the base protocol for this stylesheet
   *
   * @return string
   */
  function get_protocol() { return $this->_protocol; }

  /**
   * Return the base host for this stylesheet
   *
   * @return string
   */
  function get_host() { return $this->_base_host; }

  /**
   * Return the base path for this stylesheet
   *
   * @return string
   */
  function get_base_path() { return $this->_base_path; }
  
  /**
   * add a new Style object to the stylesheet
   *
   * add_style() adds a new Style object to the current stylesheet, or
   * merges a new Style with an existing one.
   *
   * @param string $key   the Style's selector
   * @param Style $style  the Style to be added
   */
  function add_style($key, Style $style) {
    if (!is_string($key))
      throw new DOMPDF_Exception("CSS rule must be keyed by a string.");

    if ( isset($this->_styles[$key]) )
      $this->_styles[$key]->merge($style);
    else
      $this->_styles[$key] = clone $style;
  }


  /**
   * lookup a specifc Style object
   *
   * lookup() returns the Style specified by $key, or null if the Style is
   * not found.
   *
   * @param string $key   the selector of the requested Style
   * @return Style
   */
  function lookup($key) {
    if ( !isset($this->_styles[$key]) )
      return null;
    
    return $this->_styles[$key];
  }

  /**
   * create a new Style object associated with this stylesheet
   *
   * @param Style $parent The style of this style's parent in the DOM tree
   * @return Style
   */
  function create_style($parent = null) {
    return new Style($this, $parent);
  }
  

  /**
   * load and parse a CSS string
   *
   * @param string $css
   */
  function load_css(&$css) { $this->_parse_css($css); }


  /**
   * load and parse a CSS file
   *
   * @param string $file
   */
  function load_css_file($file) {
    global $_dompdf_warnings;
    
    // Prevent circular references
    if ( isset($this->_loaded_files[$file]) )
      return;

    $this->_loaded_files[$file] = true;
    $parsed_url = explode_url($file);

    list($this->_protocol, $this->_base_host, $this->_base_path, $filename) = $parsed_url;
    
    if ( !DOMPDF_ENABLE_REMOTE &&
         ($this->_protocol != "" && $this->_protocol != "file://") ) {
      record_warnings(E_USER_WARNING, "Remote CSS file '$file' requested, but DOMPDF_ENABLE_REMOTE is false.", __FILE__, __LINE__);
      return; 
    }
    
    // Fix submitted by Nick Oostveen for aliased directory support:
    if ( $this->_protocol == "" )
      $file = $this->_base_path . $filename;
    else
      $file = build_url($this->_protocol, $this->_base_host, $this->_base_path, $filename);
    
    set_error_handler("record_warnings");
    $css = file_get_contents($file);
    restore_error_handler();

    if ( $css == "" ) {
      record_warnings(E_USER_WARNING, "Unable to load css file $file", __FILE__, __LINE__);;
      return;
    }
    
    $this->_parse_css($css);

  }

  /**
   * @link http://www.w3.org/TR/CSS21/cascade.html#specificity}
   *
   * @param string $selector
   * @return int
   */
  private function _specificity($selector) {
    // http://www.w3.org/TR/CSS21/cascade.html#specificity

    $a = ($selector === "!style attribute") ? 1 : 0;
    
    $b = min(mb_substr_count($selector, "#"), 255);

    $c = min(mb_substr_count($selector, ".") +
             mb_substr_count($selector, ">") +
             mb_substr_count($selector, "+"), 255);
    
    $d = min(mb_substr_count($selector, " "), 255);

    return ($a << 24) | ($b << 16) | ($c << 8) | ($d);
  }


  /**
   * converts a CSS selector to an XPath query.
   *
   * @param string $selector
   * @return string
   */
  private function _css_selector_to_xpath($selector) {

    // Collapse white space and strip whitespace around delimiters
//     $search = array("/\\s+/", "/\\s+([.>#+:])\\s+/");
//     $replace = array(" ", "\\1");
//     $selector = preg_replace($search, $replace, trim($selector));
    
    // Initial query (non-absolute)
    $query = "//";
    
    // Parse the selector     
    //$s = preg_split("/([ :>.#+])/", $selector, -1, PREG_SPLIT_DELIM_CAPTURE);

    $delimiters = array(" ", ">", ".", "#", "+", ":", "[");

    // Add an implicit space at the beginning of the selector if there is no
    // delimiter there already.
    if ( !in_array($selector{0}, $delimiters) )
      $selector = " $selector";

    $tok = "";
    $len = mb_strlen($selector);
    $i = 0;
                   
    while ( $i < $len ) {

      $s = $selector{$i};
      $i++;

      // Eat characters up to the next delimiter
      $tok = "";

      while ($i < $len) {
        if ( in_array($selector{$i}, $delimiters) )
          break;
        $tok .= $selector{$i++};
      }

      switch ($s) {
        
      case " ":
      case ">":
        // All elements matching the next token that are direct children of
        // the current token
        $expr = $s == " " ? "descendant" : "child";

        if ( mb_substr($query, -1, 1) != "/" )
          $query .= "/";

        if ( !$tok )
          $tok = "*";
        
        $query .= "$expr::$tok";
        $tok = "";
        break;

      case ".":
      case "#":
        // All elements matching the current token with a class/id equal to
        // the _next_ token.

        $attr = $s == "." ? "class" : "id";

        // empty class/id == *
        if ( mb_substr($query, -1, 1) == "/" )
          $query .= "*";

        // Match multiple classes: $tok contains the current selected
        // class.  Search for class attributes with class="$tok",
        // class=".* $tok .*" and class=".* $tok"
        
        // This doesn't work because libxml only supports XPath 1.0...
        //$query .= "[matches(@$attr,\"^${tok}\$|^${tok}[ ]+|[ ]+${tok}\$|[ ]+${tok}[ ]+\")]";
        
        // Query improvement by Michael Sheakoski <michael@mjsdigital.com>:
        $query .= "[contains(concat(' ', @$attr, ' '), concat(' ', '$tok', ' '))]";
        $tok = "";
        break;

      case "+":
        // All sibling elements that folow the current token
        if ( mb_substr($query, -1, 1) != "/" )
          $query .= "/";

        $query .= "following-sibling::$tok";
        $tok = "";
        break;

      case ":":
        // Pseudo-classes
        switch ($tok) {

        case "first-child":
          break;

        case "link":
          $query .= "[@href]";
          $tok = "";
          break;

        case "first-line":
          break;

        case "first-letter":
          break;

        case "before":
          break;

        case "after":
          break;
        
        }
        
        break;
        
      case "[":
        // Attribute selectors.  All with an attribute matching the following token(s)
        $attr_delimiters = array("=", "]", "~", "|");
        $tok_len = mb_strlen($tok);
        $j = 0;
        
        $attr = "";
        $op = "";
        $value = "";
        
        while ( $j < $tok_len ) {
          if ( in_array($tok{$j}, $attr_delimiters) )
            break;
          $attr .= $tok{$j++};
        }
        
        switch ( $tok{$j} ) {

        case "~":
        case "|":
          $op .= $tok{$j++};

          if ( $tok{$j} != "=" )
            throw new DOMPDF_Exception("Invalid CSS selector syntax: invalid attribute selector: $selector");

          $op .= $tok{$j};
          break;

        case "=":
          $op = "=";
          break;

        }
       
        // Read the attribute value, if required
        if ( $op != "" ) {
          $j++;
          while ( $j < $tok_len ) {
            if ( $tok{$j} == "]" )
              break;
            $value .= $tok{$j++};
          }            
        }
       
        if ( $attr == "" )
          throw new DOMPDF_Exception("Invalid CSS selector syntax: missing attribute name");

        switch ( $op ) {

        case "":
          $query .=  "[@$attr]";
          break;
         
        case "=":
          $query .= "[@$attr$op\"$value\"]";
          break;

        case "~=":
          // FIXME: this will break if $value contains quoted strings
          // (e.g. [type~="a b c" "d e f"])
          $values = explode(" ", $value);
          $query .=  "[";

          foreach ( $values as $val ) 
            $query .= "@$attr=\"$val\" or ";
         
          $query = rtrim($query, " or ") . "]";
          break;

        case "|=":
          $values = explode("-", $value);
          $query .= "[";

          foreach ($values as $val)
            $query .= "starts-with(@$attr, \"$val\") or ";

          $query = rtrim($query, " or ") . "]";
          break;
         
        }
     
        break;
      }
    }
    $i++;
      
//       case ":":
//         // Pseudo selectors: ignore for now.  Partially handled directly
//         // below.

//         // Skip until the next special character, leaving the token as-is
//         while ( $i < $len ) {
//           if ( in_array($selector{$i}, $delimiters) )
//             break;
//           $i++;
//         }
//         break;
        
//       default:
//         // Add the character to the token
//         $tok .= $selector{$i++};
//         break;
//       }

//    }
    
    
    // Trim the trailing '/' from the query
    if ( mb_strlen($query) > 2 )
      $query = rtrim($query, "/");
    
    return $query;
  }

  /**
   * applies all current styles to a particular document tree
   *
   * apply_styles() applies all currently loaded styles to the provided
   * {@link Frame_Tree}.  Aside from parsing CSS, this is the main purpose
   * of this class.
   *
   * @param Frame_Tree $tree
   */
  function apply_styles(Frame_Tree $tree) {

    // Use XPath to select nodes.  This would be easier if we could attach
    // Frame objects directly to DOMNodes using the setUserData() method, but
    // we can't do that just yet.  Instead, we set a _node attribute_ in 
    // Frame->set_id() and use that as a handle on the Frame object via
    // Frame_Tree::$_registry.

    // We create a scratch array of styles indexed by frame id.  Once all
    // styles have been assigned, we order the cached styles by specificity
    // and create a final style object to assign to the frame.

    // FIXME: this is not particularly robust...    

    $styles = array();
    $xp = new DOMXPath($tree->get_dom());

    // Apply all styles in stylesheet
    foreach ($this->_styles as $selector => $style) {

      $query = $this->_css_selector_to_xpath($selector);
//       pre_var_dump($selector);
//       pre_var_dump($query);
//        echo ($style);
      
      // Retrieve the nodes      
      $nodes = $xp->query($query);

      foreach ($nodes as $node) {
        //echo $node->nodeName . "\n";
        // Retrieve the node id
        if ( $node->nodeType != 1 ) // Only DOMElements get styles
          continue;
        
        $id = $node->getAttribute("frame_id");

        // Assign the current style to the scratch array
        $spec = $this->_specificity($selector);
        $styles[$id][$spec][] = $style;
      }
    }

    // Now create the styles and assign them to the appropriate frames.  (We
    // iterate over the tree using an implicit Frame_Tree iterator.)
    $root_flg = false;
    foreach ($tree->get_frames() as $frame) {
      // pre_r($frame->get_node()->nodeName . ":");
            
      if ( !$root_flg && $this->_page_style ) {
        $style = $this->_page_style;
        $root_flg = true;

      } else 
        $style = $this->create_style();

      // Find nearest DOMElement parent
      $p = $frame;      
      while ( $p = $p->get_parent() )
        if ($p->get_node()->nodeType == 1 )
          break;
      
      // Styles can only be applied directly to DOMElements; anonymous
      // frames inherit from their parent
      if ( $frame->get_node()->nodeType != 1 ) {
        if ( $p )
          $style->inherit($p->get_style());
        $frame->set_style($style);
        continue;
      }

      $id = $frame->get_id();

      // Handle HTML 4.0 attributes
      Attribute_Translator::translate_attributes($frame);
    
      // Locate any additional style attributes      
      if ( ($str = $frame->get_node()->getAttribute("style")) !== "" ) {
        $spec = $this->_specificity("!style attribute");
        $styles[$id][$spec][] = $this->_parse_properties($str);
      }
      
      // Grab the applicable styles
      if ( isset($styles[$id]) ) {
        
        $applied_styles = $styles[ $frame->get_id() ];

        // Sort by specificity
        ksort($applied_styles);

        // Merge the new styles with the inherited styles
        foreach ($applied_styles as $arr) {
          foreach ($arr as $s) 
            $style->merge($s);
        }
      }

      // Inherit parent's styles if required
      if ( $p ) {
        $style->inherit( $p->get_style() );
      }

//       pre_r($frame->get_node()->nodeName . ":");
//      echo "<pre>";
//      echo $style;
//      echo "</pre>";
      $frame->set_style($style);
      
    }
    
    // We're done!  Clean out the registry of all styles since we
    // won't be needing this later.
    foreach ( array_keys($this->_styles) as $key ) {
      unset($this->_styles[$key]);
    }
    
  }
  

  /**
   * parse a CSS string using a regex parser
   *
   * Called by {@link Stylesheet::parse_css()} 
   *
   * @param string $str 
   */
  private function _parse_css($str) {

    // Destroy comments
    $css = preg_replace("'/\*.*?\*/'si", "", $str);

    // FIXME: handle '{' within strings, e.g. [attr="string {}"]

    // Something more legible:
    $re =
      "/\s*                                   # Skip leading whitespace                             \n".
      "( @([^\s]+)\s+([^{;]*) (?:;|({)) )?    # Match @rules followed by ';' or '{'                 \n".
      "(?(1)                                  # Only parse sub-sections if we're in an @rule...     \n".
      "  (?(4)                                # ...and if there was a leading '{'                   \n".
      "    \s*( (?:(?>[^{}]+) ({)?            # Parse rulesets and individual @page rules           \n".
      "            (?(6) (?>[^}]*) }) \s*)+?  \n".
      "       )                               \n".
      "   })                                  # Balancing '}'                                \n".
      "|                                      # Branch to match regular rules (not preceeded by '@')\n".
      "([^{]*{[^}]*}))                        # Parse normal rulesets\n".
      "/xs";
     
    if ( preg_match_all($re, $css, $matches, PREG_SET_ORDER) === false )
      // An error occured
      throw new DOMPDF_Exception("Error parsing css file: preg_match_all() failed.");

    // After matching, the array indicies are set as follows:
    //
    // [0] => complete text of match
    // [1] => contains '@import ...;' or '@media {' if applicable
    // [2] => text following @ for cases where [1] is set
    // [3] => media types or full text following '@import ...;'
    // [4] => '{', if present
    // [5] => rulesets within media rules
    // [6] => '{', within media rules
    // [7] => individual rules, outside of media rules
    //
    //pre_r($matches);
    foreach ( $matches as $match ) {
      $match[2] = trim($match[2]);

      if ( $match[2] !== "" ) {
        // Handle @rules
        switch ($match[2]) {

        case "import":          
          $this->_parse_import($match[3]);
          break;

        case "media":
          if ( in_array(mb_strtolower(trim($match[3])), self::$ACCEPTED_MEDIA_TYPES ) ) {
            $this->_parse_sections($match[5]);
          }
          break;

        case "page":
          // Store the style for later...
          if ( is_null($this->_page_style) )
            $this->_page_style = $this->_parse_properties($match[5]);
          else
            $this->_page_style->merge($this->_parse_properties($match[5]));
          break;
          
        default:
          // ignore everything else
          break;
        }

        continue;
      }

      if ( $match[7] !== "" ) 
        $this->_parse_sections($match[7]);
      
    }
  }

  
  /**
   * parse @import{} sections
   *
   * @param string $url  the url of the imported CSS file
   */
  private function _parse_import($url) {
    $arr = preg_split("/[\s\n]/", $url);
    $url = array_pop($arr);
    $accept = false;
    
    if ( count($arr) > 0 ) {
      
      // @import url media_type [media_type...]
      foreach ( $arr as $type ) {
        if ( in_array($type, self::$ACCEPTED_MEDIA_TYPES) ) {
          $accept = true;
          break;
        }
      }
      
    } else
      // unconditional import
      $accept = true;
    
    if ( $accept ) {
      $url = str_replace(array('"',"url", "(", ")"), "", $url);
      // Store our current base url properties in case the new url is elsewhere
      $protocol = $this->_protocol;
      $host = $this->_base_host;
      $path = $this->_base_path;

      // If the protocol is php, assume that we will import using file://
      $url = build_url($protocol == "php://" ? "file://" : $protocol, $host, $path, $url);
      
      $this->load_css_file($url);
      
      // Restore the current base url
      $this->_protocol = $protocol;
      $this->_base_host = $host;
      $this->_base_path = $path;
    }
    
  }

  /**
   * parse regular CSS blocks
   *
   * _parse_properties() creates a new Style object based on the provided
   * CSS rules.
   *
   * @param string $str  CSS rules
   * @return Style
   */
  private function _parse_properties($str) {
    $properties = explode(";", $str);

    // Create the style
    $style = new Style($this);
    foreach ($properties as $prop) {

      $prop = trim($prop);

      if ($prop == "")
        continue;

      $i = mb_strpos($prop, ":");
      if ( $i === false )
        continue;

      $prop_name = mb_strtolower(mb_substr($prop, 0, $i));
      $value = mb_substr($prop, $i+1);
      $style->$prop_name = $value;

    }

    return $style;
  }

  /**
   * parse selector + rulesets
   *
   * @param string $str  CSS selectors and rulesets
   */
  private function _parse_sections($str) {
    // Pre-process: collapse all whitespace and strip whitespace around '>',
    // '.', ':', '+', '#'
    
    $patterns = array("/[\\s\n]+/", "/\\s+([>.:+#])\\s+/");
    $replacements = array(" ", "\\1");
    $str = preg_replace($patterns, $replacements, $str);

    $sections = explode("}", $str);
    foreach ($sections as $sect) {
      $i = mb_strpos($sect, "{");

      $selectors = explode(",", mb_substr($sect, 0, $i));
      $style = $this->_parse_properties(trim(mb_substr($sect, $i+1)));

      // Assign it to the selected elements
      foreach ($selectors as $selector) {
        $selector = trim($selector);
        
        if ($selector == "")
          continue;
        
        $this->add_style($selector, $style);
      }
    }
  }  

  /**
   * dumps the entire stylesheet as a string
   *
   * Generates a string of each selector and associated style in the
   * Stylesheet.  Useful for debugging.
   *
   * @return string
   */
  function __toString() {
    $str = "";
    foreach ($this->_styles as $selector => $style) 
      $str .= "$selector => " . $style->__toString() . "\n";

    return $str;
  }
}
?>