<?php

namespace App\Enums;

enum PromotionType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case BuyXGetY = 'buy_x_get_y';
}
