<?php namespace com\meilisearch\unittest;

use com\meilisearch\{Index, MeiliSearch};
use lang\{IllegalStateException, IndexOutOfBoundsException};
use test\{Assert, Expect, Test, Values};
use util\Date;
use webservices\rest\UnexpectedStatus;

class IndexesTest {
  const JSON= ['Content-Type' => 'application/json'];
  const CREATED= '2021-06-05T15:43:06.000000Z';
  const INDEXES= [
    'test' => [
      'uid'        => 'test',
      'name'       => 'test',
      'createdAt'  => '2021-06-03T14:10:44.431089500Z',
      'updatedAt'  => '2021-06-03T14:11:00.058201500Z',
      'primaryKey' => 'id'
    ],
    'content' => [
      'uid'        => 'content',
      'name'       => 'content',
      'createdAt'  => '2021-06-03T14:10:44.431089500Z',
      'updatedAt'  => '2021-06-03T14:11:00.058201500Z',
      'primaryKey' => null
    ],
  ];

  /**
   * Returns a mocked MeiliSearch service with a functioning indexes API
   *
   * @see  https://docs.meilisearch.com/reference/api/indexes.html
   * @see  https://docs.meilisearch.com/reference/api/stats.html#get-stat-of-an-index
   */
  private function search(array $indexes= self::INDEXES): MeiliSearch {  
    $search= new MeiliSearch('http://localhost:7700');
    $search->endpoint()->connecting(function($uri) use(&$indexes) {
      return new TestConnection($uri, [
        'POST /indexes' => function($params, $body) use(&$indexes) {
          $config= json_decode($body, true);
          if ('' === $config['uid']) {
            return [400, self::JSON, '{"message":"Impossible to create index..."}'];
          }
          if (isset($indexes[$config['uid']])) {
            return [400, self::JSON, '{"message":"Index already exists..."}'];
          }

          $indexes[$config['uid']]= $created= [
            'uid'        => $config['uid'],
            'name'       => $config['uid'],
            'createdAt'  => self::CREATED,
            'updatedAt'  => self::CREATED,
            'primaryKey' => $config['primaryKey'] ?? null,
          ];
          return [201, self::JSON, json_encode($created)];
        },
        'GET /indexes' => function() use(&$indexes) {
          return [200, self::JSON, json_encode($indexes)];
        },
        'GET /indexes/{uid}' => function($params) use(&$indexes) {
          if ($index= $indexes[$params['uid']] ?? null) {
            return [200, self::JSON, json_encode($index)];
          }
          return [404, self::JSON, '{"message":"Index not found..."}'];
        },
        'GET /indexes/{uid}/stats' => function($params) use(&$indexes) {
          if ($index= $indexes[$params['uid']] ?? null) {
            return [200, self::JSON, '{"numberOfDocuments":19654,"isIndexing":false}'];
          }
          return [404, self::JSON, '{"message":"Index not found..."}'];
        },
        'GET /indexes/{uid}/settings' => function($params) use(&$indexes) {
          if ($index= $indexes[$params['uid']] ?? null) {
            return [200, self::JSON, '{"rankingRules":["typo","words","proximity"]}'];
          }
          return [404, self::JSON, '{"message":"Index not found..."}'];
        },
        'POST /indexes/{uid}/settings' => function($params, $body) use(&$indexes) {
          if ($index= $indexes[$params['uid']] ?? null) {
            $settings= json_decode($body, true);
            return [202, self::JSON, '{"updateId":1}'];
          }
          return [404, self::JSON, '{"message":"Index not found..."}'];
        },
        'DELETE /indexes/{uid}/settings' => function($params) use(&$indexes) {
          if ($index= $indexes[$params['uid']] ?? null) {
            return [202, self::JSON, '{"updateId":1}'];
          }
          return [404, self::JSON, '{"message":"Index not found..."}'];
        },
        'PUT /indexes/{uid}' => function($params, $body) use(&$indexes) {
          if ($index= $indexes[$params['uid']] ?? null) {
            $config= json_decode($body, true);
            $index['primaryKey']= $config['primaryKey'] ?? $index['primaryKey'];
            $index['name']= $config['name'] ?? $index['name'];
            return [200, self::JSON, json_encode($index)];
          }
          return [404, self::JSON, '{"message":"Index not found..."}'];
        },
        'DELETE /indexes/{uid}' => function($params) use(&$indexes) {
          unset($indexes[$params['uid']]);
          return [204, self::JSON, null];
        },
      ]);
    });
    return $search;
  }

  #[Test]
  public function iterate_empty_indexes() {
    Assert::equals([], iterator_to_array($this->search([])->indexes()));
  }

  #[Test]
  public function empty_indexes() {
    Assert::equals([], $this->search([])->indexes()->all());
  }

  #[Test]
  public function iterate_all() {
    $search= $this->search();
    Assert::equals(
      [
        'test'    => new Index($search->endpoint(), self::INDEXES['test']),
        'content' => new Index($search->endpoint(), self::INDEXES['content'])
      ],
      iterator_to_array($search->indexes())
    );
  }

  #[Test]
  public function indexes() {
    $search= $this->search();
    Assert::equals(
      [
        'test'    => new Index($search->endpoint(), self::INDEXES['test']),
        'content' => new Index($search->endpoint(), self::INDEXES['content'])
      ],
      $search->indexes()->all()
    );
  }

  #[Test]
  public function get_one() {
    $search= $this->search();
    Assert::equals(new Index($search->endpoint(), self::INDEXES['test']), $search->index('test'));
  }

  #[Test, Expect(UnexpectedStatus::class)]
  public function get_non_existant() {
    $this->search()->index('suggest');
  }

  #[Test]
  public function locate_one() {
    $search= $this->search();
    Assert::equals(new Index($search->endpoint(), 'test'), $search->locate('test'));
  }

  #[Test]
  public function locate_non_existant() {
    $search= $this->search();
    Assert::equals(new Index($search->endpoint(), 'suggest'), $search->locate('suggest'));
  }

  #[Test]
  public function existing() {
    Assert::true($this->search()->locate('test')->exists());
  }

  #[Test]
  public function create() {
    $search= $this->search();
    Assert::equals(
      new Index($search->endpoint(), [
        'uid'        => 'suggest',
        'name'       => 'suggest',
        'createdAt'  => self::CREATED,
        'updatedAt'  => self::CREATED,
        'primaryKey' => null
      ]),
      $search->create('suggest')
    );
  }

  #[Test]
  public function create_with_primary_key() {
    $search= $this->search();
    Assert::equals(
      new Index($search->endpoint(), [
        'uid'        => 'suggest',
        'name'       => 'suggest',
        'createdAt'  => self::CREATED,
        'updatedAt'  => self::CREATED,
        'primaryKey' => 'id'
      ]),
      $search->create('suggest', 'id')
    );
  }

  #[Test, Expect(UnexpectedStatus::class)]
  public function cannot_create_empty() {
    $this->search()->create('');
  }

  #[Test, Expect(UnexpectedStatus::class)]
  public function cannot_recreate_existing() {
    $this->search()->create('test');
  }

  #[Test]
  public function delete_index() {
    $index= $this->search()->index('test');
    $index->delete();

    Assert::false($index->exists());
  }

  #[Test]
  public function delete_located_index() {
    $index= $this->search()->locate('test');
    $index->delete();

    Assert::false($index->exists());
  }

  #[Test]
  public function update_primary_key() {
    $index= $this->search()->index('content');
    $index->modify(['primaryKey' => 'content_id']);

    Assert::equals('content_id', $index->primaryKey());
  }

  #[Test]
  public function rename_index() {
    $index= $this->search()->index('content');
    $index->modify(['name' => 'Content']);

    Assert::equals('Content', $index->name());
  }

  #[Test]
  public function non_existant() {
    Assert::false($this->search()->locate('suggest')->exists());
  }

  #[Test]
  public function uid() {
    Assert::equals('test', $this->search()->locate('test')->uid());
  }

  #[Test, Values([['test', 'id'], ['content', null]])]
  public function primaryKey($index, $expected) {
    Assert::equals($expected, $this->search()->index($index)->primaryKey());
  }

  #[Test]
  public function createdAt() {
    Assert::equals(
      new Date('2021-06-03T14:10:44.431089Z'),
      $this->search()->index('test')->createdAt()
    );
  }

  #[Test]
  public function updatedAt() {
    Assert::equals(
      new Date('2021-06-03T14:11:00.058201Z'),
      $this->search()->index('test')->updatedAt()
    );
  }

  #[Test]
  public function field() {
    Assert::equals('2021-06-03T14:10:44.431089500Z', $this->search()->index('test')->field('createdAt'));
  }

  #[Test]
  public function field_with_null() {
    Assert::null($this->search()->index('content')->field('primaryKey'));
  }

  #[Test, Expect(IndexOutOfBoundsException::class)]
  public function non_existant_field() {
    $this->search()->index('content')->field('non_existant');
  }

  #[Test]
  public function name_used_when_index_used() {
    Assert::equals('test', $this->search()->index('test')->name());
  }

  #[Test]
  public function name_fetched_when_locate_used() {
    Assert::equals('test', $this->search()->locate('test')->name());
  }

  #[Test, Expect(class: IllegalStateException::class, message: 'Index suggest does not exist')]
  public function accessing_name_on_non_existant_index() {
    $this->search()->locate('suggest')->name();
  }

  #[Test]
  public function settings() {
    Assert::equals(
      ['rankingRules' => ['typo', 'words', 'proximity']],
      $this->search()->locate('test')->settings()
    );
  }

  #[Test]
  public function configure() {
    Assert::equals(
      ['updateId' => 1],
      $this->search()->locate('test')->configure(['rankingRules' => ['typo', 'words']])
    );
  }

  #[Test]
  public function reset() {
    Assert::equals(
      ['updateId' => 1],
      $this->search()->locate('test')->reset()
    );
  }

  #[Test]
  public function stats() {
    Assert::equals(
      ['numberOfDocuments' => 19654, 'isIndexing' => false],
      $this->search()->locate('test')->stats()
    );
  }
}