<?php

namespace Marketplace\Tokens\Models;

use Model;

/**
 * Box Model
 */
class Box extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table associated with the model
     */
    public $table = 'marketplace_tokens_boxes';

    /**
     * @var array guarded attributes aren't mass assignable
     */
    protected $guarded = ['*'];

    /**
     * @var array fillable attributes are mass assignable
     */
    protected $fillable = [
        'user_id',
        'type_code'
    ];

    /**
     * @var array rules for validation
     */
    public $rules = [];

    /**
     * @var array Attributes to be cast to native types
     */
    protected $casts = [];

    /**
     * @var array jsonable attribute names that are json encoded and decoded from the database
     */
    protected $jsonable = [];

    /**
     * @var array appends attributes to the API representation of the model (ex. toArray())
     */
    protected $appends = [];

    /**
     * @var array hidden attributes removed from the API representation of the model (ex. toArray())
     */
    protected $hidden = [];

    /**
     * @var array dates attributes that should be mutated to dates
     */
    protected $dates = [
        'created_at',
        'updated_at'
    ];

    /**
     * @var array hasOne and other relations
     */
    public $hasOne = [];
    public $hasMany = [];
    public $belongsTo = [
        'type' => [BoxType::class, 'key' => 'type_code', 'otherKey' => 'code']
    ];
    public $belongsToMany = [];
    public $morphTo = [
        'boxable' => []
    ];
    public $morphOne = [
        'token' => [Token::class, 'name' => 'tokenable'],
    ];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];
}
