<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Docver extends Model
{
    protected $table = 'docvers';
    protected $fillable = array( 'user_id','name', 'photo','photo_1', 'details');

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
