<?php

namespace Drupal\Tests\remote_stream_wrapper;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\remote_stream_wrapper\StreamWrapper\HttpStreamWrapper;
use Drupal\Tests\UnitTestCase;

/**
 * @group remote_stream_wrapper
 * @coversDefaultClass \Drupal\remote_stream_wrapper\StreamWrapper\HttpStreamWrapper
 */
class HttpStreamWrapperTest extends UnitTestCase {

  /**
   * Test that the wrapper constants.
   *
   * @covers ::getType
   * @covers ::getName
   * @covers ::getDescription
   */
  public function testStreamConfiguration() {
    $this->assertEquals(StreamWrapperInterface::READ & StreamWrapperInterface::HIDDEN, HttpStreamWrapper::getType());
    $wrapper = new HttpStreamWrapper();
    $this->assertInternalType('string', $wrapper->getName());
    $this->assertInternalType('string', $wrapper->getDescription());
  }

  /**
   * Test URI methods.
   *
   * @covers ::setUri
   * @covers ::getUri
   * @covers ::getExternalUrl
   * @covers ::realpath
   */
  public function testUri() {
    $wrapper = new HttpStreamWrapper();
    $uri = 'http://example.com/file.txt';
    $wrapper->setUri($uri);
    $this->assertEquals($uri, $wrapper->getUri());
    $this->assertEquals($uri, $wrapper->getExternalUrl());
    $this->assertEquals($uri, $wrapper->realpath());
  }

  /**
   * Test dirname().
   *
   * @covers ::dirname
   */
  public function testDirname() {
    $wrapper = new HttpStreamWrapper();

    // Test dirname() with no parameters.
    $wrapper->setUri('http://example.com/test.txt');
    $this->assertEquals('http://example.com', $wrapper->dirname());

    // Test dirname() with one directory.
    $wrapper->setUri('http://example.com/directory/test.txt');
    $this->assertEquals('http://example.com/directory', $wrapper->dirname());

    // Test dirname() with two directories and a $uri parameter.
    $this->assertEquals('http://example.com/directory/directory2', $wrapper->dirname('http://example.com/directory/directory2/test.txt'));

    // Test referencing self with a dot.
    $this->assertEquals('http://', $wrapper->dirname('http://.'));
  }

  /**
   * Test that we always return TRUE for locks.
   *
   * @covers ::stream_lock
   */
  public function testStreamLock() {
    $wrapper = new HttpStreamWrapper();
    $wrapper->setUri('http://example.com/test.txt');
    foreach ([LOCK_SH, LOCK_EX, LOCK_UN, LOCK_NB] as $type) {
      $this->assertTrue($wrapper->stream_lock($type));
    }
  }

}
