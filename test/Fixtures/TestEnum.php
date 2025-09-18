<?php
namespace Corma\Test\Fixtures;

enum TestEnum: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
