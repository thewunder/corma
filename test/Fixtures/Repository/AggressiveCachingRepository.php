<?php
namespace Corma\Test\Fixtures\Repository;

use Corma\Repository\AggressiveCachingObjectRepository;

class AggressiveCachingRepository extends AggressiveCachingObjectRepository
{
    public function getClassName(): string
    {
        return 'Corma\\Test\\Fixtures\\ExtendedDataObject';
    }
}
