<?php namespace com\meilisearch;

/** Iteration used by `Documents` and `Search` */
trait Iteration {
  private $index;
  private $parameters= [];

  /** Starts from a given offset */
  public function from(int $offset): self {
    $this->parameters['offset']= $offset;
    return $this;
  }

  /** Limits to a given maximum number */
  public function maximum(int $limit): self {
    $this->parameters['limit']= $limit;
    return $this;
  }

  /** @return iterable */
  public abstract function getIterator();

  /**
   * Returns documents selected by `skip()` and `take()` in an array.
   * Starts at offset 0 with a limit of 20 by default.
   * 
   * @return [:var][]
   */
  public function toArray() {
    $r= [];
    foreach ($this->getIterator() as $document) {
      $r[]= $document;
    }
    return $r;
  }

  /**
   * Returns documents selected by `skip()` and `take()` in a map. Uses
   * a given field or - if omitted - the index' primary key and starts
   * at offset 0 with a limit of 20 by default.
   * 
   * @param  ?string $field
   * @return [:[:var]]
   */
  public function toMap($field= null) {
    $field ?? $field= $this->index->primaryKey();
    $r= [];
    foreach ($this->getIterator() as $document) {
      $r[$document[$field]]= $document;
    }
    return $r;
  }

  /**
   * Returns an iterator over all documents with a given slice size.
   *
   * @param  int $slice
   * @return iterable
   */
  public abstract function iterator(int $slice= 20);
}