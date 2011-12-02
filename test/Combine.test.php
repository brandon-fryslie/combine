<?php
class CombinerTest extends PHPUnit_Framework_TestCase
{
  public function testCombineJS()
  {
    require_once('../Combiner.class.php');

    $combo = new Combiner(
      'path' => '/Library/WebServer/Documents/shared/php/combine',
      array('paths' => '/Library/WebServer/Documents/shared')
    );
    
    echo '<pre>'; var_dump($_SERVER); echo '</pre>'; exit;

    $combo->get();
  }
}
?>