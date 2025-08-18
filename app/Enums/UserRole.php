<?php

namespace App;

enum UserRole : string
{
    case ADMIN = 'admin';
    case AGENT = 'agent';
    case CUSTOMER = 'customer';

}
