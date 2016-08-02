<?php

namespace Drupal\remote_stream_wrapper\File\MimeType;

use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;
use GuzzleHttp\Exception\GuzzleException;

class HttpMimeTypeGuesser implements MimeTypeGuesserInterface {

  /**
   * The extension guesser.
   *
   * @var \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface
   */
  protected $extensionGuesser;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * Constructs a new HttpMimeTypeGuesser.
   *
   * @param \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface $extension_guesser
   *   The extension guesser.
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   */
  public function __construct(MimeTypeGuesserInterface $extension_guesser, ClientInterface $client) {
    $this->extensionGuesser = $extension_guesser;
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public function guess($path) {
    if (file_is_uri_remote($path)) {

      // Attempt to parse out the mime type if the URL contains a filename.
      if ($filename = drupal_basename(parse_url($path, PHP_URL_PATH))) {
        // Filename must contain a period in order to find a valid extension.
        // If the filename does not contain an extension, then guess() will
        // always return the default 'application/octet-stream' value.
        if (strpos($filename, '.') !== FALSE) {
          $mimetype = $this->extensionGuesser->guess($filename);
          if ($mimetype != 'application/octet-stream') {
            // Only return the guessed mime type if it found a valid match
            // instead of returning the default mime type.
            return $mimetype;
          }
        }
      }

      // Attempt a HEAD request and return the Content-Type header value.
      try {
        $response = $this->client->request('HEAD', $path);
        if ($response->hasHeader('Content-Type')) {
          return $response->getHeaderLine('Content-Type');
        }
      }
      catch (GuzzleException $exception) {
        watchdog_exception('remote_stream_wrapper', $exception);
      }
    }
  }

}
