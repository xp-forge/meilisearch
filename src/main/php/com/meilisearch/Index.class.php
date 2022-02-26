<?php namespace com\meilisearch;

use lang\{IllegalStateException, Value};
use util\{Date, Objects};
use webservices\rest\Endpoint;

class Index implements Value {
  private $endpoint, $uid, $meta;

  /**
   * Creates a new instance
   * 
   * @see    com.meilisearch.MeiliSearch::index()
   * @see    com.meilisearch.MeiliSearch::locate()
   * @param  [:var]|string $arg
   */
  public function __construct(Endpoint $endpoint, $arg) {
    $this->endpoint= $endpoint;
    if (is_array($arg)) {
      $this->uid= $arg['uid'];
      $this->meta= $arg;
    } else {
      $this->uid= (string)$arg;
      $this->meta= null;
    }
  }

  /** Returns index uid (always accessible) */
  public function uid(): string { return $this->uid; }

  /** Returns meta information, memoizing it */
  private function meta() {
    return $this->meta ?? $this->meta= $this->endpoint->resource('indexes/{0}', [$this->uid])
      ->get()
      ->optional()
    ;
  }

  /**
   * Rewrites a date as returned by MeiliSearch to a value PHP can parse.
   * This requires removing nanosecond precision, the last three digits.
   * 
   * @param  string $input
   * @return util.Date
   */
  private function asDate($input) {
    return new Date(preg_replace('/(\.[0-9]+)[0-9]{3}Z$/', '$1Z', $input));
  }

  /**
   * Returns a REST resource
   *
   * @param  string $path
   * @param  string[] $segments
   * @return webservice.rest.RestResource
   */
  public function resource($path, $segments= []) {
    array_unshift($segments, $this->uid);
    return $this->endpoint->resource(rtrim('indexes/{0}/'.$path, '/'), $segments);
  }

  /**
   * Returns a given field, throwing an exception if it does not exist
   *
   * @param  string $name
   * @return var
   * @throws lang.IllegalStateException if the index does not exist
   * @throws lang.IndexOutOfBoundsException if the field does not exist
   */
  public function field($name) {
    $this->meta ?? $this->meta() ?? (function() {
      throw new IllegalStateException('Index '.$this->uid.' does not exist');
    })();
    return $this->meta[$name];
  }

  /** Returns whether this index exists */
  public function exists(): bool { return null !== $this->meta(); }

  /** Returns index name for existing indexes */
  public function name() { return $this->field('name'); }

  /** Returns primary key name for existing indexes */
  public function primaryKey() { return $this->field('primaryKey'); }

  /** Returns creation date for existing indexes */
  public function createdAt(): Date { return $this->asDate($this->field('createdAt')); }

  /** Returns last update date for existing indexes */
  public function updatedAt(): Date { return $this->asDate($this->field('updatedAt')); }

  /**
   * Runs a search query and returns the results. The returned instance
   * provides methods for iterating over all documents or setting offset
   * and limit to fetch a given view into the results.
   * 
   * @see    https://docs.meilisearch.com/reference/api/search.html
   */
  public function search(string $query= null, array $parameters= []): Search {
    return new Search($this, ['q' => (string)$query] + $parameters);
  }

  /**
   * Gets documents in this index. The returned instance provides
   * methods for iterating over all documents or setting offset and
   * limit to fetch a given view into the collection.
   * 
   * @see    https://docs.meilisearch.com/reference/api/documents.html
   */
  public function documents(): Documents {
    return new Documents($this);
  }

  /**
   * Fetches a document, returning NULL if it cannot be found
   * 
   * @see    https://docs.meilisearch.com/reference/api/documents.html#get-one-document
   * @param  string|int $id
   * @return ?[:var]
   * @throws webservices.rest.UnexpectedStatus
   */
  public function document($id) {
    return $this->endpoint->resource('indexes/{0}/documents/{1}', [$this->uid, $id])
      ->get()
      ->optional()
    ;
  }

  /**
   * Adds or replaces a document in the index
   * 
   * @see    https://docs.meilisearch.com/reference/api/documents.html#add-or-replace-documents
   * @return [:var]
   * @throws webservices.rest.UnexpectedStatus
   */
  public function add(array... $documents) {
    return $this->endpoint->resource('indexes/{0}/documents', [$this->uid])
      ->post($documents, 'application/json')
      ->match([202 => function($r) { return $r->value(); }])
    ;
  }

  /**
   * Adds or updates a document in the index
   * 
   * @see    https://docs.meilisearch.com/reference/api/documents.html#add-or-update-documents
   * @return [:var]
   * @throws webservices.rest.UnexpectedStatus
   */
  public function update(array... $documents) {
    return $this->endpoint->resource('indexes/{0}/documents', [$this->uid])
      ->put($documents, 'application/json')
      ->match([202 => function($r) { return $r->value(); }])
    ;
  }

  /**
   * Removes documents from the index
   * 
   * @see    https://docs.meilisearch.com/reference/api/documents.html#delete-documents
   * @param  string|int... $ids
   * @return [:var]
   * @throws webservices.rest.UnexpectedStatus
   */
  public function remove(... $ids) {
    return $this->endpoint->resource('indexes/{0}/documents/delete-batch', [$this->uid])
      ->post($ids, 'application/json')
      ->match([202 => function($r) { return $r->value(); }])
    ;
  }

  /**
   * Deletes all documents from the index
   * 
   * @see    https://docs.meilisearch.com/reference/api/documents.html#delete-all-documents
   * @return [:var]
   * @throws webservices.rest.UnexpectedStatus
   */
  public function clear() {
    return $this->endpoint->resource('indexes/{0}/documents', [$this->uid])
      ->delete()
      ->match([202 => function($r) { return $r->value(); }])
    ;
  }

  /**
   * Deletes this index
   *
   * @see    https://docs.meilisearch.com/reference/api/indexes.html#delete-an-index
   * @return void
   * @throws webservices.rest.UnexpectedStatus
   */
  public function delete() {
    $this->endpoint->resource('indexes/{0}', [$this->uid])
      ->delete()
      ->match([204 => true])
    ;

    // Reset meta information
    $this->meta= null;
  }

  /**
   * Modifies this index
   *
   * @see    https://docs.meilisearch.com/reference/api/indexes.html#update-an-index
   * @param  [:string] $meta May contain primaryKey and/or name
   * @return void
   * @throws webservices.rest.UnexpectedStatus
   */
  public function modify(array $meta) {
    $this->meta= $this->endpoint->resource('indexes/{0}', [$this->uid])
      ->put($meta, 'application/json')
      ->value()
    ;
  }

  /**
   * Get settings for this index
   * 
   * @see    https://docs.meilisearch.com/reference/api/settings.html#get-settings
   * @return [:var]
   * @throws webservices.rest.UnexpectedStatus
   */
  public function settings() {
    return $this->endpoint->resource('indexes/{0}/settings', [$this->uid])
      ->get()
      ->value()
    ;
  }

  /**
   * Update settings for this index
   * 
   * @see    https://docs.meilisearch.com/reference/api/settings.html#update-settings
   * @param  [:var] $settings
   * @return [:var]
   * @throws webservices.rest.UnexpectedStatus
   */
  public function configure($settings) {
    return $this->endpoint->resource('indexes/{0}/settings', [$this->uid])
      ->post($settings, 'application/json')
      ->match([202 => function($r) { return $r->value(); }])
    ;
  }

  /**
   * Reset settings for this index
   * 
   * @see    https://docs.meilisearch.com/reference/api/settings.html#reset-settings
   * @return [:var]
   * @throws webservices.rest.UnexpectedStatus
   */
  public function reset() {
    return $this->endpoint->resource('indexes/{0}/settings', [$this->uid])
      ->delete()
      ->match([202 => function($r) { return $r->value(); }])
    ;
  }

  /**
   * Get statistics for this index
   * 
   * @see    https://docs.meilisearch.com/reference/api/stats.html#get-stat-of-an-index
   * @return var
   * @throws webservices.rest.UnexpectedStatus
   */
  public function stats() {
    return $this->endpoint->resource('indexes/{0}/stats', [$this->uid])
      ->get()
      ->value()
    ;
  }

  /** @return string */
  public function hashCode() { return 'I#'.$this->uid; }

  /** @return string */
  public function toString() { return nameof($this).'<'.$this->uid.'>@'.Objects::stringOf($this->meta); }

  /**
   * Compare
   *
   * @param  var $value
   * @return bool
   */
  public function compareTo($value) {
    return $value instanceof self ? $this->uid <=> $value->uid : 1;
  }
}