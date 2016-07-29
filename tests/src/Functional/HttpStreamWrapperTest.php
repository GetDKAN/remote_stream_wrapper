<?php


namespace Drupal\Tests\remote_stream_wrapper\Functional;


/**
 * @group remote_stream_wrapper
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class HttpStreamWrapperTest extends \Drupal\Tests\BrowserTestBase {

  public static $modules = ['remote_stream_wrapper'];

  public function testFileSize() {
    $uri = $this->getAbsoluteUrl('core/CHANGELOG.txt');
    $this->assertEquals(filesize('core/CHANGELOG.txt'), filesize($uri));
  }

  public function testReadStream() {
    $uri = $this->getAbsoluteUrl('core/CHANGELOG.txt');
    $contents = file_get_contents($uri);
    $contents_from_local = file_get_contents('core/CHANGELOG.txt');
    $this->assertEquals($contents_from_local, $contents);
  }

}
