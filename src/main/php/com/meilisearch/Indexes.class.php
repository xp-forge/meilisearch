<?php namespace com\meilisearch;

use IteratorAggregate, Traversable;
use webservices\rest\Endpoint;

class Indexes implements IteratorAggregate {
  private $endpoint, $result;

  /**
   * Creates a new instance
   * 
   * @see  com.meilisearch.MeiliSearch::indexes()
   */
  public function __construct(Endpoint $endpoint, $result) {
    $this->endpoint= $endpoint;
    $this->result= $result;
  }

  /** Returns whether the list of indexes is empty */
  public function empty(): bool { return empty($this->result); }

  /** @return [:com.meilisearch.Index] */
  public function all() {
    $r= [];
    foreach ($this->result as $meta) {
      $r[$meta['uid']]= new Index($this->endpoint, $meta);
    }
    return $r;
  }

  /** @return iterable */
  public function getIterator(): Traversable {
    foreach ($this->result as $meta) {
      yield $meta['uid'] => new Index($this->endpoint, $meta);
    }
  }
}