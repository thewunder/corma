<?php
namespace Corma\Test\Fixtures\Repository;

use Corma\Repository\ReadOnlyObjectRepository;

class ReadOnlyRepository extends ReadOnlyObjectRepository
{
    public function getClassName()
    {
        return 'Corma\\Test\\Fixtures\\ExtendedDataObject';
    }
}