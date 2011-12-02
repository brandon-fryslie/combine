<?php

require_once('../Combiner.class.php');

$combo = new Combiner(array(
      'combine_path' => '/Library/WebServer/Documents/shared/php/combine',
      'paths' => '/Library/WebServer/Documents/shared'
));

$combo->get();

?>