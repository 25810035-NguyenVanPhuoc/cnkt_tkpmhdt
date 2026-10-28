<?php

namespace App\Enums;

enum ProductUnitStatus: string
{
    case InStock = 'in_stock';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Returned = 'returned';
    case Damaged = 'damaged';
}
