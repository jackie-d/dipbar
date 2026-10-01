<?php

namespace App\Enums;

enum Visibility: string
{
    case Private = 'private';
    case Collection = 'collection';
    case Public = 'public';
}
