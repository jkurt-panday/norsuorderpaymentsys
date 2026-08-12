<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin      = 'admin';
    case Accountant = 'accountant';
    case Client     = 'client';
}
