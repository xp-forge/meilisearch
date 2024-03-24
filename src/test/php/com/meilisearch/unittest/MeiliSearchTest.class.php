<?php namespace com\meilisearch\unittest;

use com\meilisearch\MeiliSearch;
use lang\IllegalArgumentException;
use test\{Assert, Expect, Test, Values};
use util\URI;
use webservices\rest\UnexpectedStatus;

class MeiliSearchTest {

  /** Creates a search using the test connection */
  private function search(array $routing): MeiliSearch {
    $search= new MeiliSearch('http://localhost:7700');
    $search->endpoint()->connecting(function($uri) use($routing) {
      return new TestConnection($uri, $routing);
    });
    return $search;
  }

  #[Test]
  public function can_create() {
    new MeiliSearch('http://localhost:7700');
  }

  #[Test]
  public function can_create_from_uri() {
    new MeiliSearch(new URI('http://localhost:7700'));
  }

  #[Test]
  public function can_create_with_api_key() {
    Assert::equals(
      [MeiliSearch::API_KEY => 'api-key'],
      (new MeiliSearch('http://api-key@localhost:7700'))->endpoint()->headers()
    );
  }

  #[Test, Expect(IllegalArgumentException::class), Values(['', 'test', '//test', '://'])]
  public function cannot_create_with_malformed($dsn) {
    new MeiliSearch($dsn);
  }

  #[Test]
  public function health() {
    $search= $this->search(['GET /health' => function() {
      return [200, ['Content-Type' => 'application/json'], '{"status":"available"}'];
    }]);
    Assert::equals(['status' => 'available'], $search->health());
  }

  #[Test]
  public function version() {
    $search= $this->search(['GET /version' => function() {
      return [200, ['Content-Type' => 'application/json'], '{"pkgVersion": "0.1.1"}'];
    }]);
    Assert::equals(['pkgVersion' => '0.1.1'], $search->version());
  }

  #[Test]
  public function stats() {
    $search= $this->search(['GET /stats' => function() {
      return [200, ['Content-Type' => 'application/json'], '{"databaseSize":447819776}'];
    }]);
    Assert::equals(['databaseSize' => 447819776], $search->stats());
  }

  #[Test, Expect(UnexpectedStatus::class)]
  public function raises_exceptions_for_unexpected_status() {
    $search= $this->search(['GET /health' => function() {
      return [500, ['Content-Type' => 'text/plain'], 'Internal server error'];
    }]);
    $search->health();
  }
}