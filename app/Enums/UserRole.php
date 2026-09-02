<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin      = 'admin';
    case Staff      = 'staff';
    case Cashier    = 'cashier';
    case Client     = 'client';
}
