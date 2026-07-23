<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'date', 'company', 'description', 'payment_method', 'income', 'expense',
    ];
}