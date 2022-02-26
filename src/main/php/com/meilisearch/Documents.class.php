<?php namespace com\meilisearch;

class Documents implements \IteratorAggregate {
  use Iteration;

  public function __construct(Index $index) { $this->index= $index; }

  /** @return iterable */
  public function getIterator() {
    yield from $this->index->resource('documents')->get($this->parameters)->value();
  }

  /**
   * Returns an iterator over all documents with a given slice size.
   *
   * @param  int $slice
   * @return iterable
   */
  public function iterator(int $slice= 20) {
    $resource= $this->index->resource('documents');
    $offset= 0;

    do {
      $r= $resource->get(['offset' => $offset, 'limit' => $slice]);
      $i= 0;
      foreach ($r->value() as $document) {
        yield $document;
        $i++;
      }
      $offset+= $slice;
    } while ($slice === $i);
  }
}