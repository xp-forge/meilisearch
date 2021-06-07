<?php namespace com\meilisearch;

class Search implements \IteratorAggregate {
  use Iteration;

  private $result= null;

  public function __construct(Index $index, $parameters) {
    $this->index= $index;
    $this->parameters= $parameters;
  }

  /**
   * Performs the search and returns the result
   *
   * @see    https://docs.meilisearch.com/reference/api/search.html#search-in-an-index-with-post-route
   * @return [:var]
   * @throws webservices.rest.UnexpectedStatus
   */
  public function result() {
    return $this->result ?? $this->result= $this->index->resource('search')
      ->post($this->parameters, 'application/json')
      ->result()
      ->value()
    ;
  }

  /** Returns the search query */
  public function query(): string { return $this->result()['query']; }

  /** Returns the number of hits */
  public function hits(): int { return $this->result()['nbHits']; }

  /** Returns the offset */
  public function offset(): int { return $this->result()['offset']; }

  /** Returns the limit */
  public function limit(): int { return $this->result()['limit']; }

  /** Returns the elapsed time in seconds */
  public function elapsedTime(): float { return $this->result()['processingTimeMs'] / 1000; }

  /** @return iterable */
  public function getIterator() { yield from $this->result()['hits']; }

  /**
   * Returns the previous offset or NULL
   * 
   * @return ?int
   */
  public function previous() {
    $this->result();

    $offset= $this->result['offset'];
    return 0 === $offset ? null : max(0, $offset - $this->result['limit']);
  }

  /**
   * Returns the next offset or NULL
   * 
   * @return ?int
   */
  public function next() {
    $this->result();

    $next= $this->result['offset'] + $this->result['limit'];
    return $next > $this->result['nbHits'] ? null : $next;
  }

  /**
   * Returns an iterator over all search results with a given slice size.
   *
   * @param  int $slice
   * @return iterable
   */
  public function iterator(int $slice= 20) {
    $resource= $this->index->resource('search');
    $parameters= ['offset' => 0, 'limit' => $slice] + $this->parameters;

    do {
      $this->result ?? $this->result= $resource->post($parameters, 'application/json')->result()->value();
      $parameters['offset']= $this->result['offset'] + $this->result['limit'];
      $total= $this->result['nbHits'];

      foreach ($this->result['hits'] as $document) {
        yield $document;
      }
      $this->result= null;
    } while ($parameters['offset'] < $total);
  }
}