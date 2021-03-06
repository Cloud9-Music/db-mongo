<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Blob,
    Jasny\DB\Entity,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\EntitySet,
    MongoDB\BSON;

/**
 * @covers Jasny\DB\Mongo\TypeCast
 */
class TypeCastTest extends TestHelper
{
    /**
     * Test 'toClass' method for null value
     */
    public function testNull()
    {
        $typeCast = $this->createPartialMock(TypeCast::class, []);
        $this->setPrivateProperty($typeCast, 'value', null);

        $result = $typeCast->toClass('FooClass');

        $this->assertEquals(null, $result);
    }

    /**
     * Provide data for testing 'toClass' method for Identifiable value
     *
     * @return array
     */
    public function mongoIdProvider()
    {
        return [
            [BSON\ObjectId::class],
            ['\\MongoDB\\BSON\\ObjectId'],
            ['MongoDB\\BSON\\ObjectId'],
            ['mongodb\\bson\\objectid']
        ];
    }

    /**
     * Test 'toClass' method with Identifiable value
     *
     * @dataProvider mongoIdProvider
     * @param string $mongoIdClass
     */
    public function testIdentifiable($mongoIdClass)
    {
        $entity = $this->createMock(Identifiable::class);
        $entity->expects($this->once())->method('getId')->willReturn('a');

        $typeCastClone = $this->createMock(TypeCast::class);
        $typeCastClone->expects($this->once())->method('to')->with(BSON\ObjectId::class)->willReturn('foo_result');

        $typeCast = $this->createPartialMock(TypeCast::class, ['forValue']);
        $typeCast->expects($this->once())->method('forValue')->with('a')->willReturn($typeCastClone);
        $this->setPrivateProperty($typeCast, 'value', $entity);

        $result = $typeCast->toClass($mongoIdClass);
        $this->assertEquals('foo_result', $result);
    }

    /**
     * Test 'toClass' method with string MongoId value
     *
     * @dataProvider mongoIdProvider
     * @param string $mongoIdClass
     */
    public function testMongoId($mongoIdClass)
    {
        $id = '5949fe7259049b03f8b7821c';

        $typeCast = $this->createPartialMock(TypeCast::class, []);
        $this->setPrivateProperty($typeCast, 'value', $id);

        $result = $typeCast->toClass($mongoIdClass);

        $this->assertInstanceOf(BSON\ObjectId::class, $result);
        $this->assertEquals($id, (string)$result);
    }

    /**
     * Test 'toClass' method with wrong mongo id value
     *
     * @dataProvider mongoIdProvider
     * @param string $mongoIdClass
     */
    public function testMongoIdInvalid($mongoIdClass)
    {
        $typeCast = $this->createPartialMock(TypeCast::class, ['dontCastTo']);
        $this->setPrivateProperty($typeCast, 'value', 'foo');

        $typeCast->expects($this->once())->method('dontCastTo')->with(BSON\ObjectId::class)->willReturn('foo');

        $result = $typeCast->toClass($mongoIdClass);
    }

    /**
     * Test 'toClass' method with MongoId value
     */
    public function testMongoIdNoCast()
    {
        $id = new BSON\ObjectId();

        $typeCast = $this->createPartialMock(TypeCast::class, []);
        $this->setPrivateProperty($typeCast, 'value', $id);

        $result = $typeCast->toClass(BSON\ObjectId::class);

        $this->assertEquals($id, $result);
    }
}
