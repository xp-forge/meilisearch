<?php namespace com\meilisearch\unittest;

use com\meilisearch\{Index, MeiliSearch};
use test\{Assert, Expect, Test, Values};

class SearchTest {
  const JSON= ['Content-Type' => 'application/json'];
  const DOCUMENTS= [
    6100 => ['id' => 6100, 'term' => 'test'],
    6101 => ['id' => 6101, 'term' => 'ok'],
    6102 => ['id' => 6102, 'term' => 'yes'],
  ];

  /**
   * Returns a mocked MeiliSearch service with a functioning minimalistic
   * search API (no facettes, no filters).
   *
   * @see  https://docs.meilisearch.com/reference/api/search.html
   */
  private function index(array $documents= self::DOCUMENTS): Index {  
    $search= new MeiliSearch('http://localhost:7700');
    $search->endpoint()->connecting(function($uri) use(&$documents) {
      return new TestConnection($uri, [
        'GET /indexes/test' => function() {
          return [200, self::JSON, '{"uid":"test","primaryKey":"id"}'];
        },
        'POST /indexes/test/search' => function($params, $body) use(&$documents) {
          $request= json_decode($body, true);
          $offset= $request['offset'] ?? 0;
          $limit= $request['limit'] ?? 20;

          $n= 0;
          $hits= [];
          $pattern= '#'.str_replace('#', '\\#', $request['q']).'#i';
          foreach ($documents as $document) {
            if (preg_grep($pattern, $document)) {
              $n++;
              if ($n > $offset && $n <= $offset + $limit) $hits[]= $document;
            }
          }

          return [200, self::JSON, json_encode([
            'hits'             => $hits,
            'offset'           => $offset,
            'limit'            => $limit,
            'nbHits'           => $n,
            'exhaustiveNbHits' => false,
            'processingTimeMs' => 35,
            'query'            => $request['q']
          ])];
        }
      ]);
    });
    return $search->locate('test');
  }

  #[Test]
  public function iterate_empty_hits() {
    Assert::equals([], iterator_to_array($this->index([])->search()));
  }

  #[Test]
  public function iterate_all() {
    Assert::equals(array_values(self::DOCUMENTS), iterator_to_array($this->index()->search()));
  }

  #[Test]
  public function empty_hits() {
    Assert::equals(0, $this->index([])->search()->hits());
  }

  #[Test]
  public function hits() {
    Assert::equals(sizeof(self::DOCUMENTS), $this->index()->search()->hits());
  }

  #[Test, Values([1, 2, 3, 20, 1000])]
  public function iterator_over_hits($size) {
    Assert::equals(
      array_values(self::DOCUMENTS),
      iterator_to_array($this->index()->search()->iterator($size))
    );
  }

  #[Test]
  public function offset() {
    Assert::equals(2, $this->index()->search()->from(2)->offset());
  }

  #[Test]
  public function default_offset() {
    Assert::equals(0, $this->index()->search()->offset());
  }

  #[Test]
  public function limit() {
    Assert::equals(3, $this->index()->search()->maximum(3)->limit());
  }

  #[Test]
  public function default_limit() {
    Assert::equals(20, $this->index()->search()->limit());
  }

  #[Test]
  public function next() {
    Assert::equals(1, $this->index()->search()->maximum(1)->next());
  }

  #[Test]
  public function previous() {
    Assert::equals(0, $this->index()->search()->from(1)->previous());
  }

  #[Test]
  public function no_next() {
    Assert::equals(null, $this->index()->search()->next());
  }

  #[Test]
  public function no_previous() {
    Assert::equals(null, $this->index()->search()->previous());
  }

  #[Test]
  public function yields_elapsed_time_in_seconds() {
    Assert::equals(0.035, $this->index()->search()->elapsedTime());
  }

  #[Test, Values([['test', 'test'], ['', ''], [null, '']])]
  public function yields_given_query($term, $expected) {
    Assert::equals($expected, $this->index()->search($term)->query());
  }

  #[Test, Values([['test', 6100], ['ok', 6101], ['6102', 6102]])]
  public function search_for($term, $expected) {
    Assert::equals(
      [self::DOCUMENTS[$expected]],
      iterator_to_array($this->index()->search($term))
    );
  }

  #[Test]
  public function with_limit() {
    $search= $this->index()->search()->maximum(1);

    Assert::equals(sizeof(self::DOCUMENTS), $search->hits());
    Assert::equals([0, 1], [$search->offset(), $search->limit()]);
    Assert::equals([self::DOCUMENTS[6100]], iterator_to_array($search));
  }

  #[Test]
  public function with_offset() {
    $search= $this->index()->search()->from(2);

    Assert::equals(sizeof(self::DOCUMENTS), $search->hits());
    Assert::equals([2, 20], [$search->offset(), $search->limit()]);
    Assert::equals([self::DOCUMENTS[6102]], iterator_to_array($search));
  }

  #[Test]
  public function with_limit_and_offset() {
    $search= $this->index()->search()->from(1)->maximum(1);

    Assert::equals(sizeof(self::DOCUMENTS), $search->hits());
    Assert::equals([1, 1], [$search->offset(), $search->limit()]);
    Assert::equals([self::DOCUMENTS[6101]], iterator_to_array($search));
  }

  #[Test]
  public function toArray() {
    Assert::equals(array_values(self::DOCUMENTS), $this->index()->search()->toArray());
  }

  #[Test]
  public function toMap_uses_primary_key_as_default() {
    Assert::equals([6100 => self::DOCUMENTS[6100]], $this->index()->search('test')->toMap());
  }

  #[Test]
  public function toMap_using_field() {
    Assert::equals(['test' => self::DOCUMENTS[6100]], $this->index()->search('test')->toMap('term'));
  }
}