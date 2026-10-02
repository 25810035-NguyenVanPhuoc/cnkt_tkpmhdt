<?php

namespace App\Enums;

enum PromotionAppliesTo: string
{
    case All = 'all';
    case Category = 'category';
    case Product = 'product';
}
