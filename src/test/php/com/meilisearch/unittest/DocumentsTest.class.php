<?php namespace com\meilisearch\unittest;

use com\meilisearch\{MeiliSearch, Index};
use unittest\{Assert, Expect, Test, Values};
use webservices\rest\UnexpectedStatus;

class DocumentsTest {
  const JSON= ['Content-Type' => 'application/json'];
  const DOCUMENTS= [
    6100 => ['id' => 6100, 'term' => 'test'],
    6101 => ['id' => 6101, 'term' => 'ok'],
    6102 => ['id' => 6102, 'term' => 'yes'],
  ];

  /**
   * Returns a mocked MeiliSearch service with a functioning documents API
   *
   * @see  https://docs.meilisearch.com/reference/api/documents.html
   */
  private function index(array $documents= self::DOCUMENTS): Index {  
    $search= new MeiliSearch('http://localhost:7700');
    $search->endpoint()->connecting(function($uri) use(&$documents) {
      return new TestConnection($uri, [
        'GET /indexes/test' => function() {
          return [200, self::JSON, '{"uid":"test","primaryKey":"id"}'];
        },
        'DELETE /indexes/test/documents' => function() use(&$documents) {
          $documents= [];
          return [202, self::JSON, '{"updateId":1}'];
        },
        'POST /indexes/test/documents' => function($params, $body) use(&$documents) {
          foreach (json_decode($body, true) as $document) {
            $documents[$document['id']]= $document;
          }
          return [202, self::JSON, '{"updateId":1}'];
        },
        'PUT /indexes/test/documents' => function($params, $body) use(&$documents) {
          foreach (json_decode($body, true) as $document) {
            $documents[$document['id']]= $document + ($documents[$document['id']] ?? []);
          }
          return [202, self::JSON, '{"updateId":1}'];
        },
        'POST /indexes/test/documents/delete-batch' => function($params, $body) use(&$documents) {
          foreach (json_decode($body, true) as $id) {
            unset($documents[$id]);
          }
          return [202, self::JSON, '{"updateId":1}'];
        },
        'GET /indexes/test/documents/{id}' => function($params) use(&$documents) {
          if ($document= $documents[$params['id']] ?? null) {
            return [200, self::JSON, json_encode($document)];
          }
          return [404, self::JSON, '{"message":"Document not found..."}'];
        },
        'GET /indexes/test/documents' => function($params) use(&$documents) {
          return [200, self::JSON, json_encode(array_slice(
            $documents,
            $params['offset'] ?? 0,
            $params['limit'] ?? 20
          ))];
        }
      ]);
    });
    return $search->locate('test');
  }

  #[Test]
  public function get_one() {
    Assert::equals(['id' => 6100, 'term' => 'test'], $this->index()->document(6100));
  }

  #[Test]
  public function get_non_existant() {
    Assert::null($this->index()->document(0));
  }

  #[Test]
  public function iterate_all() {
    Assert::equals(array_values(self::DOCUMENTS), iterator_to_array($this->index()->documents()));
  }

  #[Test]
  public function iterate_empty() {
    Assert::equals([], iterator_to_array($this->index([])->documents()));
  }

  #[Test, Values([1, 2, 3, 20, 1000])]
  public function iterator_using($size) {
    Assert::equals(
      array_values(self::DOCUMENTS),
      iterator_to_array($this->index()->documents()->iterator($size))
    );
  }

  #[Test, Values([1, 2, 3, 20, 1000])]
  public function iterator_empty_using($size) {
    Assert::equals(
      [],
      iterator_to_array($this->index([])->documents()->iterator($size))
    );
  }

  #[Test]
  public function with_limit() {
    Assert::equals(
      [self::DOCUMENTS[6100]],
      iterator_to_array($this->index()->documents()->maximum(1))
    );
  }

  #[Test]
  public function with_offset() {
    Assert::equals(
      [self::DOCUMENTS[6101], self::DOCUMENTS[6102]],
      iterator_to_array($this->index()->documents()->from(1))
    );
  }

  #[Test]
  public function with_offset_and_limit() {
    Assert::equals(
      [self::DOCUMENTS[6101]],
      iterator_to_array($this->index()->documents()->from(1)->maximum(1))
    );
  }

  #[Test]
  public function toArray() {
    Assert::equals(array_values(self::DOCUMENTS), $this->index()->documents()->toArray());
  }

  #[Test]
  public function toMap_uses_primary_key_as_default() {
    Assert::equals(self::DOCUMENTS, $this->index()->documents()->toMap());
  }

  #[Test]
  public function toMap_using_field() {
    Assert::equals(
      ['test' => self::DOCUMENTS[6100], 'ok' => self::DOCUMENTS[6101], 'yes' => self::DOCUMENTS[6102]],
      $this->index()->documents()->toMap('term')
    );
  }

  #[Test]
  public function add_one() {
    $index= $this->index();
    $document= ['id' => 6103, 'term' => 'added'];

    Assert::equals(['updateId' => 1], $index->add($document));
    Assert::equals($document, $index->document(6103));
  }

  #[Test]
  public function add_many() {
    $index= $this->index();
    $documents= [6103 => ['id' => 6103, 'term' => 'added'], 6104 => ['id' => 6104, 'term' => 'added']];

    Assert::equals(['updateId' => 1], $index->add(...$documents));
    Assert::equals(self::DOCUMENTS + $documents, $index->documents()->toMap());
  }

  #[Test, Expect(UnexpectedStatus::class)]
  public function add_with_missing_primary_key() {
    $this->index()->add(['term' => 'added']);
  }

  #[Test]
  public function update_one() {
    $index= $this->index();
    $document= ['id' => 6103, 'term' => 'added'];

    Assert::equals(['updateId' => 1], $index->update($document));
    Assert::equals($document, $index->document(6103));
  }

  #[Test]
  public function update_many() {
    $index= $this->index();
    $documents= [6103 => ['id' => 6103, 'term' => 'added'], 6104 => ['id' => 6104, 'term' => 'added']];

    Assert::equals(['updateId' => 1], $index->update(...$documents));
    Assert::equals(self::DOCUMENTS + $documents, $index->documents()->toMap());
  }

  #[Test, Expect(UnexpectedStatus::class)]
  public function update_with_missing_primary_key() {
    $this->index()->update(['term' => 'added']);
  }

  #[Test]
  public function replace_existing() {
    $index= $this->index();
    $index->add(['id' => 6102, 'used' => 1]);

    Assert::equals(['id' => 6102, 'used' => 1], $index->document(6102));
  }

  #[Test]
  public function partial_update_to_existing() {
    $index= $this->index();
    $index->update(['id' => 6102, 'used' => 1]);

    Assert::equals(['id' => 6102, 'term' => 'yes', 'used' => 1], $index->document(6102));
  }

  #[Test]
  public function delete_one() {
    $index= $this->index();

    Assert::equals(['updateId' => 1], $index->remove(6102));
    Assert::null($index->document(6102));
  }

  #[Test]
  public function delete_many() {
    $index= $this->index();

    Assert::equals(['updateId' => 1], $index->remove(6101, 6102));
    Assert::equals([self::DOCUMENTS[6100]], $index->documents()->toArray());
  }

  #[Test]
  public function clear_deletes_all() {
    $index= $this->index();

    Assert::equals(['updateId' => 1], $index->clear());
    Assert::equals([], $index->documents()->toArray());
  }
}