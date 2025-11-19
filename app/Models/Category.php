<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ["name", "slug", "image", "notes"];

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

}
