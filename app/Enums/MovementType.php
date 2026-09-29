<?php

namespace App\Enums;

enum MovementType: string
{
    case Entry = 'entry';
    case Exit = 'exit';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case Cancellation = 'cancellation';
}
