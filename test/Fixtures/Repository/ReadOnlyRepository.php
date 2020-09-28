<?php
namespace Corma\Test\Fixtures\Repository;

use Corma\Repository\ReadOnlyObjectRepository;

class ReadOnlyRepository extends ReadOnlyObjectRepository
{
    public function getClassName(): string
    {
        return 'Corma\\Test\\Fixtures\\ExtendedDataObject';
    }
}
