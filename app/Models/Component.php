<?php
namespace App\Models;

use App\Models\Traits\Searchable;
use App\Presenters\Presentable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Watson\Validating\ValidatingTrait;

/**
 * Model for Components.
 *
 * @version    v1.0
 */
class Component extends SnipeModel
{
    protected $presenter = 'App\Presenters\ComponentPresenter';
    use CompanyableTrait;
    use Loggable, Presentable;
    use SoftDeletes;

    protected $dates = ['deleted_at', 'purchase_date'];
    protected $table = 'components';

    /**
    * Category validation rules
    */
    public $rules = array(
        'name'           => 'required|min:3|max:255',
        'qty'            => 'required|integer|min:1',
        'category_id'    => 'required|integer|exists:categories,id',
        'company_id'     => 'integer|nullable',
        'min_amt'        => 'integer|min:0|nullable',
        'purchase_date'  => 'date|nullable',
        'purchase_cost'  => 'numeric|nullable',
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

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'category_id',
        'company_id',
        'location_id',
        'name',
        'purchase_cost',
        'purchase_date',
        'min_amt',
        'order_number',
        'qty',
        'serial',
    ];

    use Searchable;
    
    /**
     * The attributes that should be included when searching the model.
     * 
     * @var array
     */
    protected $searchableAttributes = ['name', 'order_number', 'serial', 'purchase_cost', 'purchase_date'];

    /**
     * The relations and their attributes that should be included when searching the model.
     * 
     * @var array
     */
    protected $searchableRelations = [
        'category'     => ['name'],
        'company'      => ['name'],
        'location'     => ['name'],
        'groups'       => ['name']
    ];

    /**
     * Establishes the component -> location relationship
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function location()
    {
        return $this->belongsTo('\App\Models\Location', 'location_id');
    }

    /**
     * Establishes the component -> assets relationship
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function assets()
    {
        return $this->belongsToMany('\App\Models\Asset', 'components_assets')->withPivot('id', 'assigned_qty', 'created_at', 'user_id');
    }

    /**
     * Establishes the component -> admin user relationship
     *
     * @todo this is probably not needed - refactor
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function admin()
    {
        return $this->belongsTo('\App\Models\User', 'user_id');
    }

    /**
     * Establishes the component -> company relationship
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function company()
    {
        return $this->belongsTo('\App\Models\Company', 'company_id');
    }

    /**
     * Establishes the component -> category relationship
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function category()
    {
        return $this->belongsTo('\App\Models\Category', 'category_id');
    }

    /**
     * Establishes the component -> action logs relationship
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function assetlog()
    {
        return $this->hasMany('\App\Models\Actionlog', 'item_id')->where('item_type', Component::class)->orderBy('created_at', 'desc')->withTrashed();
    }

    public function groups()
    {
        return $this->belongsToMany('\App\Models\Group', 'components_groups');
    }

    /**
     * Check how many items within a component are checked out
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v5.0]
     * @return int
     */
    public function numCheckedOut()
    {
        $checkedout = 0;
        foreach ($this->assets as $checkout) {
            $checkedout += $checkout->pivot->assigned_qty;
        }

        return $checkedout;
    }

    /**
     * Check how many items within a component are remaining
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v3.0]
     * @return int
     */
    public function numRemaining()
    {
        return $this->qty - $this->numCheckedOut();
    }

    /**
    * Query builder scope to order on company
    *
    * @param  \Illuminate\Database\Query\Builder  $query  Query builder instance
    * @param  string                              $order       Order
    *
    * @return \Illuminate\Database\Query\Builder          Modified query builder
    */
    public function scopeOrderCategory($query, $order)
    {
        return $query->join('categories', 'components.category_id', '=', 'categories.id')->orderBy('categories.name', $order);
    }

    /**
    * Query builder scope to order on company
    *
    * @param  \Illuminate\Database\Query\Builder  $query  Query builder instance
    * @param  string                              $order       Order
    *
    * @return \Illuminate\Database\Query\Builder          Modified query builder
    */
    public function scopeOrderLocation($query, $order)
    {
        return $query->leftJoin('locations', 'components.location_id', '=', 'locations.id')->orderBy('locations.name', $order);
    }


    /**
    * Query builder scope to order on company
    *
    * @param  \Illuminate\Database\Query\Builder  $query  Query builder instance
    * @param  string                              $order       Order
    *
    * @return \Illuminate\Database\Query\Builder          Modified query builder
    */
    public function scopeOrderCompany($query, $order)
    {
        return $query->leftJoin('companies', 'components.company_id', '=', 'companies.id')->orderBy('companies.name', $order);
    }
}
