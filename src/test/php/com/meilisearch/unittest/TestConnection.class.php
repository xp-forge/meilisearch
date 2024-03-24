<?php namespace com\meilisearch\unittest;

use io\streams\MemoryInputStream;
use lang\Throwable;
use peer\http\{HttpConnection, HttpOutputStream, HttpRequest, HttpResponse};

class TestConnection extends HttpConnection {
  private $routing;

  public function __construct($uri, $routing) {
    parent::__construct($uri);
    $this->routing= $routing;
    uksort($this->routing, function($a, $b) { return strlen($b) <=> strlen($a); });
  }

  private function routing($request, $body) {
    $route= $request->method.' '.$request->url->getPath();

    try {
      if (isset($this->routing[$route])) {
        return $this->routing[$route]($request->parameters, $body);
      }

      foreach ($this->routing as $match => $routing) {
        if (preg_match('#'.preg_replace('/\{([a-z]+)\}/', '(?P<$1>[^/]+)', $match).'#', $route, $matches)) {
          return $routing($request->parameters + $matches, $body);
        }
      }

      return [404, ['Content-Type' => 'text/plain'], 'No such route '.$route];
    } catch (Throwable $t) {
      return [500, ['Content-Type' => 'text/plain'], $t->getMessage()];
    }
  }

  private function response($request, $body= null) {
    list($status, $headers, $body)= $this->routing($request, $body);
    $h= '';
    foreach ($headers as $name => $value) {
      $h.= $name.': '.$value."\r\n";
    }

    if (null !== $body) {
      $h.= 'Content-Length: '.strlen($body)."\r\n";
    }

    return new HttpResponse(new MemoryInputStream("HTTP/1.1 {$status}\r\n{$h}\r\n{$body}"));
  }

  public function open(HttpRequest $request) {
    return new class($request) extends HttpOutputStream {
      public $request, $bytes= '';

      public function __construct($request) { $this->request= $request; }

      public function write($bytes) { $this->bytes.= $bytes; }
    };
  }

  public function send(HttpRequest $request) {
    return $this->response($request);
  }

  public function finish(HttpOutputStream $stream) {
    return $this->response($stream->request, $stream->bytes);
  }
}