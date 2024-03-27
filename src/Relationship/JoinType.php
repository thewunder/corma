<?php

namespace Corma\Relationship;

enum JoinType: string
{
    case INNER = 'inner';
    case LEFT = 'left';
    case RIGHT = 'right';
}
