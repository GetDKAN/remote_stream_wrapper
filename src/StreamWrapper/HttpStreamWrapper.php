<?php

namespace Drupal\remote_stream_wrapper\StreamWrapper;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;

/**
 * HTTP(s) stream wrapper.
 */
class HttpStreamWrapper implements StreamWrapperInterface {
  use ReadOnlyPhpStreamWrapperTrait;

  /**
   * The URI of the resource.
   *
   * @var string
   */
  protected $uri;

  /**
   * The response stream.
   *
   * @var \Psr\Http\Message\StreamInterface
   */
  protected $stream;

  /**
   * Optional timeout for HTTP requests.
   *
   * @var int
   */
  protected $timeout;

  /**
   * {@inheritdoc}
   */
  public static function getType() {
    return StreamWrapperInterface::READ & StreamWrapperInterface::HIDDEN;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'HTTP stream wrapper';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return 'HTTP stream wrapper';
  }

  /**
   * {@inheritdoc}
   */
  public function setUri($uri) {
    $this->uri = $uri;
  }

  /**
   * {@inheritdoc}
   */
  public function getUri() {
    return $this->uri;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl() {
    return $this->uri;
  }

  /**
   * {@inheritdoc}
   */
  public function realpath() {
    return $this->uri;
  }

  /**
   * {@inheritdoc}
   */
  public function dirname($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }

    list($scheme, $target) = explode('://', $uri, 2);
    $dirname = dirname($target);

    if ($dirname == '.') {
      $dirname = '';
    }

    return $scheme . '://' . $dirname;
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public function stream_close() {
    // Nothing to do when closing an HTTP stream.
  }

  /**
   * {@inheritdoc}
   */
  public function stream_eof() {
    return $this->stream->eof();
  }

  /**
   * {@inheritdoc}
   */
  public function stream_lock($operation) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_open($path, $mode, $options, &$opened_path) {
    if (!in_array($mode, array('r', 'rb', 'rt'))) {
      if ($options & STREAM_REPORT_ERRORS) {
        trigger_error('stream_open() write modes not supported for HTTP stream wrappers', E_USER_WARNING);
      }
      return FALSE;
    }

    $client = $this->httpClient();
    try {
      $response = $client->get($path);
      $this->stream = $response->getBody();
    }
    catch (\Exception $e) {
      if ($options & STREAM_REPORT_ERRORS) {
        // TODO: Make this testable.
        watchdog_exception('remote_stream_wrapper', $e);
      }
      return FALSE;
    }

    if ($options & STREAM_USE_PATH) {
      $opened_path = $path;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_read($count) {
    return $this->stream->read($count);
  }

  /**
   * {@inheritdoc}
   */
  public function stream_seek($offset, $whence = SEEK_SET) {
    try {
      $this->stream->seek($offset, $whence);
    } catch (\RuntimeException $e) {
      // TODO Make this testable.
      watchdog_exception('remote_stream_wrapper', $e);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Change stream options.
   *
   * This method is called to set options on the stream.
   *
   * @param int $option
   *   One of:
   *   - STREAM_OPTION_BLOCKING: The method was called in response to
   *     stream_set_blocking().
   *   - STREAM_OPTION_READ_TIMEOUT: The method was called in response to
   *     stream_set_timeout().
   *   - STREAM_OPTION_WRITE_BUFFER: The method was called in response to
   *     stream_set_write_buffer().
   * @param int $arg1
   *   If option is:
   *   - STREAM_OPTION_BLOCKING: The requested blocking mode:
   *     - 1 means blocking.
   *     - 0 means not blocking.
   *   - STREAM_OPTION_READ_TIMEOUT: The timeout in seconds.
   *   - STREAM_OPTION_WRITE_BUFFER: The buffer mode, STREAM_BUFFER_NONE or
   *     STREAM_BUFFER_FULL.
   * @param int $arg2
   *   If option is:
   *   - STREAM_OPTION_BLOCKING: This option is not set.
   *   - STREAM_OPTION_READ_TIMEOUT: The timeout in microseconds.
   *   - STREAM_OPTION_WRITE_BUFFER: The requested buffer size.
   *
   * @return bool
   *   TRUE on success, FALSE otherwise. If $option is not implemented, FALSE
   *   should be returned.
   */
  public function stream_set_option($option, $arg1, $arg2) {
    if ($option != STREAM_OPTION_READ_TIMEOUT) {
      return FALSE;
    }

    $this->timeout = $arg1 + $arg2;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function stream_stat() {
    static $modeMap = [
      'r'  => 33060,
      'r+' => 33206,
      'w'  => 33188,
    ];

    return [
      'dev'     => 0,
      'ino'     => 0,
      'mode'    => $modeMap['r'],
      'nlink'   => 0,
      'uid'     => 0,
      'gid'     => 0,
      'rdev'    => 0,
      'size'    => $this->stream->getSize() ?: 0,
      'atime'   => 0,
      'mtime'   => 0,
      'ctime'   => 0,
      'blksize' => 0,
      'blocks'  => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function stream_tell() {
    return $this->stream->tell();
  }

  /**
   * {@inheritdoc}
   */
  public function url_stat($path, $flags) {
    $client = $this->httpClient();
    $response = $client->get($path);
    $this->stream = $response->getBody();
    $this->uri = $path;
    if ($flags & STREAM_URL_STAT_QUIET) {
      return @$this->stream_stat();
    }
    else {
      return $this->stream_stat();
    }
  }

  /**
   * Return a HTTP client.
   *
   * @return \GuzzleHttp\Client
   *   The HTTP client.
   */
  protected function httpClient() {
    /** @var \Drupal\Core\Http\ClientFactory $factory */
    $factory = \Drupal::service('http_client_factory');

    $config = [];
    if ($this->timeout) {
      $config = [
        'timeout' => $this->timeout,
      ];
    }
    $client = $factory->fromOptions($config);

    return $client;
  }

}
