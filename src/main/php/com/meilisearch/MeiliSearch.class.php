<?php namespace com\meilisearch;

use lang\IllegalArgumentException;
use webservices\rest\Endpoint;

/**
 * MeiliSearch client interface. MeiliSearch is (quote) "an open source,
 * blazingly fast and hyper relevant search-engine that will improve your
 * search experience".
 *
 * @test  com.meilisearch.unittest.MeiliSearchTest
 * @see   https://www.meilisearch.com/
 */
class MeiliSearch {
  const API_KEY = 'X-Meili-API-Key';

  private $endpoint;

  /**
   * Instantiate search client using a given URI in the form of
   * `https?://[{api-key}@]{host}[:{port}]`. If the port is omitted,
   * the default port for the given scheme is used.
   * 
   * @see    https://docs.meilisearch.com/reference/features/authentication.html
   * @param  string|util.URI $uri
   * @throws lang.IllegalArgumentException
   */
  public function __construct($uri) {
    $p= parse_url($uri);
    if (!isset($p['scheme']) || !isset($p['host'])) {
      throw new IllegalArgumentException('DSN must consist at least of scheme and host');
    }

    $this->endpoint= new Endpoint($p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : ''));
    if (isset($p['user'])) {
      $this->endpoint->with([self::API_KEY => $p['user']]);
    }
  }

  /**
   * Returns API endpoint, which can be used for executing "raw" REST
   * requests.
   *
   * @see   https://github.com/xp-forge/rest-client
   */
  public function endpoint(): Endpoint { return $this->endpoint; }

  /**
   * Locates an index by a given UID without performing an API calls. This
   * is useful when you're sure the index exists - deferring network activity
   * until it becomes necessary. Thus, this method always returns an index;
   * use its `exists()` method to check for existance.
   * 
   * An index is automatically created when adding documents or settings
   * to an index that does not already exist.
   */
  public function locate(string $uid): Index { return new Index($this->endpoint, $uid); }

  /**
   * Returns index by a given UID, raising an exception if it does not exist.
   * 
   * @see    https://docs.meilisearch.com/reference/api/indexes.html#get-one-index
   * @throws webservices.rest.UnexpectedStatus
   */
  public function index(string $uid): Index {
    return new Index($this->endpoint, $this->endpoint->resource('indexes/{0}', [$uid])
      ->get()
      ->value()
    );
  }

  /**
   * Creates a new index with a given UID, raising an exception if it already exists.
   *
   * @see    https://docs.meilisearch.com/reference/api/indexes.html#create-an-index
   * @see    https://docs.meilisearch.com/learn/core_concepts/documents.html#primary-field
   * @throws webservices.rest.UnexpectedStatus
   */
  public function create(string $uid, string $primaryKey= null): Index {
    return new Index($this->endpoint, $this->endpoint->resource('indexes')
      ->post(['uid' => $uid, 'primaryKey' => $primaryKey], 'application/json')
      ->match([201 => function($r) { return $r->value(); }])
    );
  }

  /**
   * Returns all indexes
   * 
   * @see    https://docs.meilisearch.com/reference/api/indexes.html#list-all-indexes
   * @throws webservices.rest.UnexpectedStatus
   */
  public function indexes(): Indexes {
    return new Indexes($this->endpoint, $this->endpoint->resource('indexes')
      ->get()
      ->value()
    );
  }

  /**
   * Returns stats for all indexes
   * 
   * @see    https://docs.meilisearch.com/reference/api/stats.html#get-stats-of-all-indexes
   * @return [:var]
   * @throws webservices.rest.UnexpectedStatus
   */
  public function stats() {
    return $this->endpoint->resource('stats')->get()->value();
  }

  /**
   * Returns service version
   * 
   * @see    https://docs.meilisearch.com/reference/api/version.html#get-version-of-meilisearch
   * @return [:var]
   * @throws webservices.rest.UnexpectedStatus
   */
  public function version() {
    return $this->endpoint->resource('version')->get()->value();
  }

  /**
   * Returns service health
   * 
   * @see    https://docs.meilisearch.com/reference/api/health.html#get-health
   * @return [:var]
   * @throws webservices.rest.UnexpectedStatus
   */
  public function health() {
    return $this->endpoint->resource('health')->get()->value();
  }
}