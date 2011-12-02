<?php

class Combiner {

  public function usage ()
  {
    $usage = <<< HTML
Usage:

  <link rel="stylesheet" href="http://www.domain.com/path/combine.php/{{ type }}/{{ files }}/{{ mini }}/"
  type="text/css" media="screen" title="no title" charset="utf-8">

  &&

  <script src="http://www.domain.com/path/combine.php/{{ type }}/{{ files }}/{{ mini }}/"
  type="text/javascript" charset="utf-8"></script>

  {{ type }} = 'css' or 'js'

  {{ files }} = comma separated list of files (extensions optional) like these:

  jquery-ui-1.8.13.custom,beemail,ui.jqgrid,jquery.pnotify.default,jquery.fileupload-ui

  {{ mini }} It can be 'mini:false' or 'mini:no' to turn mini off, or any other value for on

  You can put your arguments in any order.  An argument of 'css' or 'js' anywhere will set the type.
  The script will also detect the type via file extension if possible.

  e.g.

    src="http://www.domain.com/path/combine.php/jquery.min.js/"
    Type = js, Files = jquery.min.js, Mini = true

    src="http://www.domain.com/path/combine.php/js/jquery.min/mini:false/"
    Type = js, Files = jquery.min, Mini = false

    src="http://www.domain.com/path/combine.php/query.coffee/"
    Type = js, Files = query.coffee, Mini = true

    src="http://www.domain.com/path/combine.php/mini:false/files:query.coffee,jquery.min,jquery.jgrowl,jquery.mustache/"
    Type = js, Files = query.coffee,jquery.min,jquery.jgrowl,jquery.mustache, Mini = false

 #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #

This script serves up your JS && CSS files just how you like 'em: tiny and fast.

  Features:

    * Combine multiple requests for individual JS or CSS into a couple big fat requests!

    * Minify CSS / JS!

    * Compress CSS / JS (gzip/deflate if supported by browser)!

    * Compile CoffeeScript / Less into JS / CSS!

    * Client-side caching by sending a 304 Not Modified if it hasn't changed!

    * Server-size caching of the minified / compressed data!

 #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #

Setup:

  Put this file in your public_html folder somewhere and fill in the paths

  in the constructor.  There is an array of 'include paths' so you can have it check a few places.

  Put your JS && CSS in two sibling folders named js && css, because it will check

  each path for a css or js folder corresponding to the {{ type }}.

  You can also use subdirectories by putting ~ in your paths.  (Disable it by setting

  'subdirs' => false in the array of options sent to the constructor.)

  Your ~ characters will be replaced with / later on.  Beware and take advantage.

  Notice that you can potentially include files from a wide variety of paths.

  Expect issues with permissions and Apache.  EVERY folder && symlink in the path needs +x

  permission for the webserver's user or group otherwise you will get 403 Forbidden.

*** Warning: referencing paths outside of Apache's DocumentRoot may cause headaches.

 #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #

"How exactly does it look through my files?" you may wonder.

  It goes through each comma separated filename, and for each filename, through the paths that are listed.

  Then it will check for file.js then file.coffee or file.css then file.less depending on the {{ type  }}

  It will be in that order* and will only include the first file it finds.

  *If you define keys for the path array the order might change.  Beware.

 #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #

Yes, it will compile CoffeeScript && Less seamlessly (sometimes. working on it. :) )!

  Put CoffeeScript (.coffee files) in with your .js files and Less (.less files) with the .css

  Note: If you have both jquery.coffee and jquery.js, loading /js/jquery/ will load .js and likewise with .css

  Anther Note: This feature requires a command line CoffeeScript / Less compiler for maximum ease.

  You can use npm (node package manager) to install these.  npm install coffee; node install less;

  If you use different utilities to accomplish this, update the script accordingly (around line #256).

 #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #

"Doesn't encoding all of that data take a long time?!" you might ask!

  YES!  Luckily we employ some caching that works really well, so it only takes a bit

  the first time you recompile a 'block' of JS files.  It really helps during

  development if you put the quickly changing files in their own requests, and combine the large

  rarely changing files into one request.

 #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #  #
HTML;
    die('<pre>'.htmlentities($usage).'</pre>');
  }

  private $cache;
  private $cache_dir;
  private $paths;
  private $css_image_path;
  private $combine_path;

  private $args;

  public function __construct ($opt)
  {
    $this->cache          = isset($opt['cache'])        ? $opt['cache']        : true;
    $this->subdirs        = isset($opt['subdirs'])      ? $opt['subdirs']      : true;
    $this->combine_path   = isset($opt['combine_path']) ? $opt['combine_path'] : '';
    $this->lib_path       = isset($opt['lib_path'])     ? $opt['lib_path']     : "{$this->combine_path}/lib";
    $this->cache_dir      = isset($opt['cache_dir'])    ? $opt['cache_dir']    : "{$this->combine_path}/cache";
    $this->css_image_path = isset($opt['css_image_path']) ? $opt['css_image_path'] : 'css/images';

    $this->paths = isset($opt['paths']) ? $opt['paths'] : rtrim(dirname(__FILE__), '/');
    if (!is_array($this->paths)) $this->paths = array($this->paths);

    if ($this->cache && !file_exists($this->cache_dir))
      $this->error("Cache directory \"{$this->cache_dir}\" does not exist");

    if ($this->cache && !is_writable($this->cache_dir))
      $this->error("Cache directory \"{$this->cache_dir}\" is not writable");
  }

  public function error ($error)
  {
    echo "<pre>Error: $error</pre>";

    if ($this->args)
      echo "<pre>Args: \n".print_r($this->args, true).'</pre>';


    $subdirs = $this->subdirs ? 'Yes' : 'No';
    echo "<pre>Using subdirs: {$subdirs}</pre>";

    exit;
  }

  private function get_uri ()
  {
    // Get a uri string

    // Check these server vars first
    if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['SCRIPT_NAME']))
    {
      $path = $_SERVER['REQUEST_URI'];

      // Remove script name if still there
      if (strpos($path, $_SERVER['SCRIPT_NAME']) === 0)
        $path = substr($path, strlen($_SERVER['SCRIPT_NAME']));

      // Remove script's dir if still there
      elseif (strpos($path, dirname($_SERVER['SCRIPT_NAME'])) === 0)
        $path = substr($path, strlen(dirname($_SERVER['SCRIPT_NAME'])));

      // Remove query string if that's there
      if (strpos($path, '?') !== false)
        $path = substr($path, 0, strpos($path, '?'));

      if (trim(str_replace(array('//', '../'), '/', $path), '/') !== '') $uri = $path;
    }

    // Check path info
    if (!isset($uri))
    {
      $path = trim(isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : @getenv('PATH_INFO'), '/');

      if ($path !== '' && $path !== SELF) $uri = $path;
    }

    // Last try: check query string
    if (!isset($uri))
      $uri = trim(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : @getenv('QUERY_STRING'), '/');

    // Unprintable control chars
    $control_chars = array('/%0[0-8bcef]/', '/%1[0-9a-f]/', '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S');

    do $uri = preg_replace($control_chars, '', $uri, -1, $count);
    while ($count);

    // Only allow THESE characters in URLs.  Nothing else will make it through

    if (preg_match('|[^a-zA-Z 0-9~%.:_/\-,]|', $uri, $matches)) die("Illegal character in URL: {$matches[0]}");

    return $uri;
  }

  // Ideas & some code taken from URI.php in CodeIgniter
  private function detect_args ()
  {

    $uri = $this->get_uri();

    if (!$uri) $this->usage();

    $segments = preg_split("|/|", $uri, null, PREG_SPLIT_NO_EMPTY);

    $args = array();
    foreach ($segments as $k => $v)
    {
      // Check explicit argument form first
      if (strpos($v, ':') !== false)
      {
        $arg = explode(':', $v);
        $args[$arg[0]] = $arg[1];
        continue;
      }

      if ($v === 'js' || $v === 'css')
      {
        $args['type'] = $v;
        continue;
      }

      $args['files'] = $v;

      if (strpos($v, '.js') !== false || strpos($v, '.coffee') !== false)
        $args['type'] = 'js';

      else if (strpos($v, '.css') !== false || strpos($v, '.less') !== false)
        $args['type'] = 'css';
    }

    if (!isset($args['type'])) $this->error('Could not reliably determine request type (js or css)');
    if (!isset($args['files'])) $this->error('Did not specify any files');

    // $args['mini] will be false if $args['mini'] is 'false' or 'no', else true
    $args['mini'] = !isset($args['mini']) || !($args['mini'] === 'false' || $args['mini'] === 'no');

    return $args;
  }

  public function get ()
  {
    $this->args = $this->detect_args();

    // TODO: Enter no arguments to print the usage
    // It's already doing this in get_uri but I want to refactor it
    // if (!$this->args) $this->usage();

    $filename_str = $this->args['files'];
    $type = $this->args['type'];
    $mini = $this->args['mini'];

    if ($type !== 'css' && $type !== 'js') {
      header("HTTP/1.0 503 Not Implemented"); exit; };

    $content_type = ($type === 'js') ? 'javascript' : 'css';

    $file_names = preg_split('/,/', $filename_str, null, PREG_SPLIT_NO_EMPTY);

    $lastmodified = 0;

    $notice = ''; $files = array();
    foreach ($file_names as $f)
    {
      // Skip duplicate files
      if (isset($files[$f])) continue;

      // Replace ~ with / if subdirs are enabled
      if ($this->subdirs)
        $f = str_replace('~', '/', $f);

      // Won't minify file if already minified
      // We use a regex because we want both .min and .min$
      $pre_minied = (boolean)preg_match('/\bmin\b/i', $f);

      // We check the paths we set in the constructor
      foreach ($this->paths as $path)
      {
        // This will match if the file has an extension and uses the extension as the type
        // eg. file = jquery.min.js, type = js, path = /var/www/html/shared =>
        // /var/www/html/shared/js/jquery.min.js
        $p = "$path/$type/$f";
        if (preg_match('/\.(\w+?)$/', $p, $matches) &&
          file_exists(realpath($p)))
        {
          $files[$f] = array('path' => realpath($p), 'type' => $matches[1], 'pre_minied'  => $pre_minied);
          break;
        }

        // No extension, try these to see where the file is
        $types = ($type === 'js') ? array('js', 'coffee') : array('css', 'less');
        foreach ($types as $t)
        {
          $p = "$path/$type/$f.$t";

          if (file_exists(realpath($p)))
          {
            $f = "$f.$t";
            $files[$f] = array('path' => realpath($p), 'type' => $t, 'pre_minied' => $pre_minied);
            break 2;
          }
        }
      }

      if (!isset($files[$f]))
      {
        $paths = implode(', ', $this->paths);
        
        $ls = `ls "{$paths}"`;
        echo "ls {$paths} = $ls";
        $this->error("404: {$f} not found in {$paths}");
        // header ("HTTP/1.1 404 Not Found"); exit;
      }

      $lastmodified = max($lastmodified, filemtime($files[$f]['path']));
    }

    // Send Etag hash
    // We compare the hash to the hash the server already has
    // If it is different, we send a new copy to them
    $hash = "$lastmodified-".md5(implode('', array_keys($files)).$mini);
    header ("Etag: \"$hash\"");

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
      trim(stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) === "\"$hash\"")
    {
      // Return visit and no modifications, so do not send anything
      header ('HTTP/1.0 304 Not Modified');
      header ('Content-Length: 0');
      exit;
    }

    $encoding = $this->determine_encoding();

    // First time visit or files were modified
    if ($this->cache)
    {
      // Try the cache first to see if the combined files were already generated
      $mini_str = $mini?".mini":''; $encoding_str = $encoding?".$encoding":'';
      $cache_file = "$this->cache_dir/cache-{$hash}{$mini_str}.{$type}{$encoding_str}";

      if (file_exists($cache_file))
      {
        if (!is_writable($cache_file))
          $this->error('Cache file is not writeable');

        $fp = fopen($cache_file, 'rb');

        if (!$fp)
          $this->error("Cache file could not be opened for reading");

        if ($encoding)
          header ("Content-Encoding: $encoding");

        header ("Content-Type: text/$content_type; charset=utf-8");
        header ('Content-Length: '.filesize($cache_file));

        // Output contents of file
        fpassthru($fp); fclose($fp);
        exit;
      }
    }

    // Either cache is off or the cache file didn't exist
    // So now we open the regular old files and work with them

    // The little block of code below ends up putting something like this at the top of the file:
    //
    // // 8 files combined and minified.
    // jquery-1.6.2.min.js
    // jquery-ui-1.8.16.custom.min.js
    // themeswitchertool.js
    // jquery.ajax_form.js
    // jquery.validation.js
    // grid.locale-en.js
    // jquery.jqGrid.no_legacy.js
    // jquery.jqGrid.edit.js

    // jquery-1.6.2.min.js
    //
    // ...And down here is the minified javascript...
    //
    $names = '';
    foreach ($files as $n => $f) { $names .= "$n\n"; }

    $mini_text = $mini?' and minified':'';
    $n = count($files); $file_txt = ($n===1)?'file':'files'; // $n = number of files
    $contents = ($n > 1 && !$mini) ? "/*\n\n$n $file_txt combined{$mini_text}.\n$names\n*/\n" : '';

    foreach ($files as $name => $file)
    {
      if (!file_exists($file['path']))
        $this->error("File not found: {$file['path']}");

      // CLI UTILS
      // Compile Less/CoffeeScript, or just read the contents for css/js
      //
      // TODO: Use YUI Compressor
      if ($file['type'] === 'less') // -x minifies less
      {
        $mini_arg =  $mini ? $file['pre_minied'] ? '' : '-x ' : '';
        $content = $this->compile('lessc', $mini_arg.$file['path']);
      }
      else if ($file['type'] === 'coffee') // -p outputs the data to stdout
      {
        $content = $this->compile('coffee', "-p {$file['path']}");
      }
      else
      {
        $content = file_get_contents($file['path']);
      }

      // Fix images in stylesheets
      // Define a path at the top and it will rewrite your css image paths a little bit
      if ($type === 'css')
        $content = preg_replace('/url\(.*images/', "url({$this->css_image_path}", $content);

      // Minify if we need to
      if ($mini && !$file['pre_minied'] && $file['type'] !== 'less')
        $content = $this->minify($type, $content);

      $contents .= ($n > 1 && !$mini) ? "\n/* $name */\n$content\n" : $content;
    }

    header ("Content-Type: text/$content_type");

    if ($encoding)
    {
      // Compress if needed
      $contents = gzencode($contents, 9, ($encoding === 'gzip') ? FORCE_GZIP : FORCE_DEFLATE);

      if ($contents === false)
        $this->error("Failed while compressing file with $encoding");

      header ("Content-Encoding: $encoding");
    }

    header ('Content-Length: '.strlen($contents));
    echo $contents;

    // Store cache
    if ($this->cache && $fp = fopen($cache_file, 'wb'))
    {
      fwrite($fp, $contents);
      fclose($fp);
    }
  }

  private function determine_encoding()
  {
    // Determine supported compression method
    $gzip     = strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
    $deflate = strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false;

    // Determine used compression method
    $encoding = $gzip ? 'gzip' : ($deflate ? 'deflate' : false);

    // Check for buggy versions of Internet Explorer
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') === false &&
      preg_match('/^Mozilla\/4\.0 \(compatible; MSIE ([0-9]\.[0-9])/i', $_SERVER['HTTP_USER_AGENT'], $matches))
    {
      $version = floatval($matches[1]);

      if ($version < 6)
        $encoding = false;

      if ($version == 6 && strpos($_SERVER['HTTP_USER_AGENT'], 'EV1') === false)
        $encoding = false;
    }
    return $encoding;
  }

  // Compiles stuff with $command
  // Right now, used for less & coffeescript
  private function compile ($command, $args)
  {
    $spec = array(
      0 => array('pipe', 'r'),
      1 => array('pipe', 'w'),
      2 => array('pipe', 'w')
    );

    $env = array(
      'NODE_PATH' => '/usr/local/lib/node',
      'PATH' => '/usr/local/bin:'.getenv('PATH')
    );

    $proc = proc_open("$command $args", $spec, $pipes, null, $env);

    if (!is_resource($proc))
      $this->error("proc_open failed: couldn't open the command line utility $command");

    fclose($pipes[0]);

    $rv = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    $proc_value = proc_close($proc);

    if ($err !== '') $this->error($err);

    return $rv;
  }

  private function minify ($type, $content)
  {
    if ($type === 'js')
    {
      require_once($this->lib_path.'/JSMin.php');
      return JSMin::minify($content);
    }
    else // Type is probably CSS :)
    {
      require_once($this->lib_path.'/CSS.php');
      return Minify_CSS_Compressor::process($content);
    }
  }
}
?>
