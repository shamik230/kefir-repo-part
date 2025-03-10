<?php

namespace Marketplace\Tokens\Models;

use Model;

/**
 * BoxType Model
 */
class BoxType extends Model
{
    use \October\Rain\Database\Traits\Validation;

    const KEFIRIUS = 'kefirius';
    const SPOTTY = 'spotty';
    const FACTORY_PASS = 'factory_pass';
    const MINIFACTORY1 = 'minifactory1';
    const MINIFACTORY2 = 'minifactory2';
    const MINIFACTORY3 = 'minifactory3';
    const MINIFACTORY4 = 'minifactory4';
    const MINIFACTORY5 = 'minifactory5';

    /**
     * @var string table associated with the model
     */
    public $table = 'marketplace_tokens_box_types';

    protected $primaryKey = 'code';

    public $incrementing = false;

    /**
     * @var array guarded attributes aren't mass assignable
     */
    protected $guarded = ['*'];

    /**
     * @var array fillable attributes are mass assignable
     */
    protected $fillable = [
        'code',
        'name',
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
    public $hasMany = [
        'boxes' => [Box::class, 'key' => 'type_code', 'otherKey' => 'code']
    ];
    public $belongsTo = [];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];
}
