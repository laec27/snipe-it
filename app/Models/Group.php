<?php
namespace App\Models;

use App\Models\Traits\Searchable;
use Watson\Validating\ValidatingTrait;

class Group extends SnipeModel
{
    protected $table = 'permission_groups';

    public $rules = array(
      'name' => 'required|min:2|max:255',
    );

    /**
    * Whether the model should inject it's identifier to the unique
    * validation rules before attempting validation. If this property
    * is not set in the model it will default to true.
    *
    * @var boolean
    */
    protected $injectUniqueIdentifier = true;
    use ValidatingTrait;

    use Searchable;
    
    /**
     * The attributes that should be included when searching the model.
     * 
     * @var array
     */
    protected $searchableAttributes = ['name', 'created_at'];

    /**
     * The relations and their attributes that should be included when searching the model.
     * 
     * @var array
     */
    protected $searchableRelations = [];

    /**
     * Establishes the groups -> users relationship
     *
     * @author A. Gianotto <snipe@snipe.net>
     * @since [v1.0]
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function users()
    {
        return $this->belongsToMany('\App\Models\User', 'users_groups');
    }

    public function assets(){
        return $this->belongsToMany('\App\Models\Asset','assets_groups');
    }

    public function accessories()
    {
        return $this->belongsToMany('\App\Models\Accessory', 'accessories_groups');
    }

    public function components()
    {
        return $this->belongsToMany('\App\Models\Component', 'components_groups');
    }

    public function licenses()
    {
        return $this->belongsToMany('\App\Models\License', 'licenses_groups');
    }

    public function consumable()
    {
        return $this->belongsToMany('\App\Models\Consumable', 'consumables_groups');
    }

    public function kits()
    {
        return $this->belongsToMany('\App\Models\PredefinedKit', 'kits_groups');
    }
    /**
     * Decode JSON permissions into array
     *
     * @author A. Gianotto <snipe@snipe.net>
     * @since [v1.0]
     * @return array
     */
    public function decodePermissions()
    {
        return json_decode($this->permissions, true);
    }
}
