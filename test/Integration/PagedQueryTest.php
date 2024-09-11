<?php

namespace Corma\Test\Integration;

use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Util\OffsetPagedQuery;
use Corma\Util\SeekPagedQuery;

final class PagedQueryTest extends BaseIntegrationCase
{
    public function setUp(): void
    {
        parent::setUp();
        $objects = [];
        for($i = 1; $i <= 11; $i++) {
            $objects[] = $this->objectMapper->create(ExtendedDataObject::class, ['myColumn' => 'Paged Test '.$i]);
        }
        $this->objectMapper->saveAll($objects);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $objects = $this->objectMapper->findBy(ExtendedDataObject::class, ['myColumn LIKE'=>'Paged Test %']);
        $this->objectMapper->deleteAll($objects);
    }

    public function testOffsetPagedQuery(): void
    {
        /** @var ExtendedDataObjectRepository $repo */
        $repo = $this->objectMapper->getRepository(ExtendedDataObject::class);
        $pager = $repo->findAllPaged();
        $this->assertInstanceOf(OffsetPagedQuery::class, $pager);

        $this->assertGreaterThan(1, $pager->getPages());

        for ($i = 1; $i <= $pager->getPages(); $i++) {
            $objects = $pager->getResults($i);
            $this->assertLessThanOrEqual(5, count($objects));
            $this->assertInstanceOf(ExtendedDataObject::class, $objects[0]);
        }
    }

    public function testSeekPagedQuery(): void
    {
        /** @var ExtendedDataObjectRepository $repo */
        $repo = $this->objectMapper->getRepository(ExtendedDataObject::class);
        $pager = $repo->findAllSeekPaged();
        $this->assertInstanceOf(SeekPagedQuery::class, $pager);

        $pages = $pager->getPages();
        $this->assertGreaterThan(1, $pages);

        $i = 0;
        $ids = [];
        $keys = [];
        /** @var ExtendedDataObject[] $objects */
        foreach ($pager as $key => $objects) {
            $keys[] = $key;
            $this->assertNotEmpty($objects);
            $this->assertLessThanOrEqual(5, count($objects));
            $this->assertInstanceOf(ExtendedDataObject::class, $objects[0]);
            foreach($objects as $object) {
                $this->assertArrayNotHasKey($object->getId(), $ids, 'Overlapping objects between pages!');
                $ids[$object->getId()] = true;
            }
            $i++;
        }
        $this->assertEquals($pages, $i);
        $this->assertEquals($pager->getResultCount(), count($ids));

        // Test as if the key was being send from request
        $pager = $repo->findAllSeekPaged();
        $results = $pager->getResults($keys[$i-2]);
        $this->assertNotEmpty($results);
        $this->assertLessThanOrEqual(5, count($objects));
    }

}
