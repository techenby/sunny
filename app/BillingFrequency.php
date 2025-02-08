<?php

namespace App;

enum BillingFrequency: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case ANNUAL = 'annual';
    case WEEKLY = 'weekly';
    case SEMIANNUAL = 'semi-annual';
    case DAILY = 'daily';
}
