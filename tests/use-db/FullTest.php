<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Blob,
    Jasny\DB\Entity,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\EntitySet,
    Jasny\DB\Mongo\TestEntity,
    MongoDB\BSON\ObjectId;

/**
 * Test using real db connection.
 * Excluded from general suite, for manual run only.
 */
class FullTest extends TestHelper
{
    /**
     * Connection to db
     * @var Jasny\DB\Mongo\DB
     */
    public static $db;

    /**
     * Collection instance with no casting to Entity
     * @var Jasny\DB\Mongo\Collection
     */
    public static $collection;

    /**
     * Collection instance with casting to Entity
     * @var Jasny\DB\Mongo\Collection
     */
    public static $collectionEntity;

    /**
     * Init db connection
     */
    public static function setUpBeforeClass()
    {
        $options = [
            'client' => 'mongodb://localhost:27017',
            'database' => 'test'
        ];

        static::$db = new DB($options, '');
        static::$collection = static::$db->test_collection;
        static::$collectionEntity = static::$db->selectCollection('test_collection', ['documentClass' => TestEntity::class]);
    }

    /**
     * Do some actions before each test case
     */
    public function setUp()
    {
        static::$collection->deleteMany([]);
    }

    /**
     * Provide data for testing 'insertOne' method
     *
     * @return array
     */
    public function insertOneProvider()
    {
        return [
            [['foo' => 'bar']],
            [(object)['foo' => 'bar']],
            [['foo' => 'bar', '_id' => new ObjectId('5aebb3ae738ee61d78164693')]],
            [(object)['foo' => 'bar', '_id' => new ObjectId('5aebb3ae738ee61d78164690')]],
        ];
    }

    /**
     * Test 'insertOne' method
     *
     * @dataProvider insertOneProvider
     */
    public function testInsertOne($doc)
    {
        $collection = static::$collection;

        $id = $this->getId($doc);
        $result = $collection->insertOne($doc);
        $this->assertInstanceOf(\MongoDB\InsertOneResult::class, $result);

        $collection->useResultId($doc, '_id', $result);

        $resultId = $this->getId($doc);
        $this->assertInstanceOf(\MongoDB\BSON\ObjectId::class, $resultId);

        if ($id) {
            $this->assertSame((string)$resultId, (string)$id);
        }
    }

    /**
     * Test 'replaceOne' method
     */
    public function testReplaceOne()
    {
        $collection = static::$collection;

        $id = new ObjectId('5aebb3ae738ee61d78164693');
        $doc = ['foo' => 'bar', '_id' => $id];
        $newDoc = ['foo' => 'zoo', '_id' => $id];

        $collection->insertOne($doc);
        $this->assertSame(1, $collection->count(['_id' => $id]));

        $result = $collection->replaceOne(['_id' => $id], $newDoc);
        $this->assertSame(1, $collection->count(['_id' => $id]));

        $collection->useResultId($doc, '_id', $result);

        $resultId = $this->getId($doc);
        $this->assertInstanceOf(ObjectId::class, $resultId);
        $this->assertSame((string)$id, (string)$resultId);

        $checkDoc = $collection->findOne(['_id' => $id]);
        $this->assertEquals($newDoc, $checkDoc);
    }

    /**
     * Test 'replaceOne' method
     */
    public function testReplaceOneNonExist()
    {
        $collection = static::$collection;

        $id = new ObjectId('5aebb3ae738ee61d78164693');
        $doc = ['foo' => 'bar', '_id' => $id];

        $this->assertSame(0, $collection->count(['_id' => $id]));

        $result = $collection->replaceOne(['_id' => $id], $doc);
        $this->assertSame(0, $collection->count(['_id' => $id]));
    }

    /**
     * Test 'replaceOne' method with 'upsert' option
     */
    public function testReplaceOneUpsert()
    {
        $collection = static::$collection;

        $id = new ObjectId('5aebb3ae738ee61d78164693');
        $doc = ['foo' => 'bar', '_id' => $id];

        $this->assertSame(0, $collection->count(['_id' => $id]));

        $collection->replaceOne(['_id' => $id], $doc, ['upsert' => true]);
        $this->assertSame(1, $collection->count(['_id' => $id]));
    }

    /**
     * Test 'insertMany' method
     */
    public function testInsertMany()
    {
        $collection = static::$collection;

        $docs = [
            ['foo' => 'bar'],
            (object)['baz' => 'zoo']
        ];

        $result = $collection->insertMany($docs);
        $collection->useResultId($docs, '_id', $result);

        $fooDoc = $collection->findOne(['foo' => 'bar']);
        $this->assertInstanceOf(ObjectId::class, $fooDoc['_id']);
        $this->assertEquals($fooDoc['_id'], $docs[0]['_id']);

        $bazDoc = $collection->findOne(['baz' => 'zoo']);
        $this->assertInstanceOf(ObjectId::class, $bazDoc['_id']);
        $this->assertEquals($bazDoc['_id'], $docs[1]->_id);
    }

    /**
     * Test 'save' method, if new document is created
     */
    public function testSaveNew()
    {
        $collection = static::$collection;

        $doc = ['foo' => 'bar'];

        $collection->save($doc);
        $created = $collection->findOne(['foo' => 'bar']);

        $this->assertInstanceOf(ObjectId::class, $created['_id']);
        $this->assertEquals($created, $doc);
    }

    /**
     * Test 'save' method, if new document is created, having and id
     */
    public function testSaveNewWithId()
    {
        $collection = static::$collection;
        $id = new ObjectId('5aebb3ae738ee61d78164693');

        $doc = ['foo' => 'bar', '_id' => $id];

        $collection->save($doc);
        $created = $collection->findOne(['_id' => $id]);

        $this->assertEquals($created, $doc);
    }

    /**
     * Test 'save' method, if existing document is saved
     */
    public function testSaveExisting()
    {
        $collection = static::$collection;
        $id = new ObjectId('5aebb3ae738ee61d78164693');

        $doc = ['foo' => 'bar', '_id' => $id];

        $collection->save($doc);

        $doc['foo'] = 'new_bar';
        $doc['zoo'] = 'lion';

        $collection->save($doc);
        $saved = $collection->findOne(['_id' => $id]);

        $this->assertEquals(1, $collection->count(['_id' => $id]));
        $this->assertEquals($saved, $doc);
    }

    /**
     * Test 'find' method
     */
    public function testFind()
    {
        $collection = static::$collection;

        $docs = [
            ['foo' => 'bar1', 'key' => 'test'],
            (object)['foo' => 'bar2', 'key' => 'test'],
            (object)['foo' => 'bar3']
        ];

        $result = $collection->insertMany($docs);
        $collection->useResultId($docs, '_id', $result);

        $cursor = $collection->find(['key' => 'test'], ['sort' => ['foo' => DB::ASCENDING]]);
        $asArray = $cursor->toArray();

        $this->assertInstanceOf(Cursor::class, $cursor);
        $this->assertEquals($docs[0], $asArray[0]);
        $this->assertEquals((array)$docs[1], $asArray[1]);
        $this->assertCount(2, $asArray);
        $this->assertSame(3, $collection->count([]));
    }

    /**
     * Test 'findOne' method for Entity instance
     */
    public function testFindOneEntity()
    {
        $collection = static::$collectionEntity;

        $entity = $this->createPartialMock(TestEntity::class, []);
        $entity->date = new \DateTime();
        $entity->zoo = 'lion';

        $result = $collection->insertOne($entity);
        $collection->useResultId($entity, '_id', $result);

        $this->assertInstanceOf(ObjectId::class, $entity->_id);

        $fetched = $collection->findOne(['_id' => $entity->_id]);

        $this->assertInstanceOf(TestEntity::class, $fetched);
        $this->assertEquals($entity->date, $fetched->date);
        $this->assertEquals($entity->zoo, $fetched->zoo);
    }

    /**
     * Test 'find' method for Entity instances
     */
    public function testFindEntities()
    {
        $collection = static::$collectionEntity;

        $entity1 = new TestEntity();
        $entity1->date = new \DateTime('2017-08-15T15:52:01+00:00');
        $entity1->zoo = 'lion';

        $entity2 = new TestEntity();
        $entity2->date = new \DateTime('2017-09-15T15:52:01+00:00');
        $entity2->zoo = 'zebra';

        $entity3 = new TestEntity();
        $entity3->date = new \DateTime('2017-10-15T15:52:01+00:00');
        $entity3->zoo = 'monkey';

        $docs = [$entity1, $entity2, $entity3];

        $result = $collection->insertMany($docs);
        $collection->useResultId($docs, '_id', $result);

        $ids = [];
        foreach ($docs as &$item) {
            $ids[] = $item->_id;
            unset($item->_id);
        }

        $cursor = $collection->find(['_id' => ['$in' => $ids]]);
        $asArray = $cursor->toArrayCast();

        $this->assertEquals($docs, $asArray);
    }

    /**
     * Get id of array or object item
     *
     * @param array|object $data
     * @return \MongoDB\BSON\ObjectId
     */
    protected function getId($data)
    {
        $prop = '_id';

        return is_array($data) ?
            (isset($data[$prop]) ? $data[$prop] : null) :
            (isset($data->$prop) ? $data->$prop : null);
    }
}
