<?php
namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Consumable;
use Gate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class ConsumablesTransformer
{

    public function transformConsumables (Collection $consumables, $total)
    {
        $array = array();
        foreach ($consumables as $consumable) {
            $array[] = self::transformConsumable($consumable);
        }
        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformConsumable (Consumable $consumable)
    {
        $array = [
            'id'            => (int) $consumable->id,
            'name'          => e($consumable->name),
            'image' =>   ($consumable->image) ? Storage::disk('public')->url('consumables/'.e($consumable->image)) : null,
            'category'      => ($consumable->category) ? ['id' => $consumable->category->id, 'name' => e($consumable->category->name)] : null,
            'company'   => ($consumable->company) ? ['id' => (int) $consumable->company->id, 'name' => e($consumable->company->name)] : null,
            'item_no'       => e($consumable->item_no),
            'location'      => ($consumable->location) ? ['id' => (int) $consumable->location->id, 'name' => e($consumable->location->name)] : null,
            'manufacturer'  => ($consumable->manufacturer) ? ['id' => (int) $consumable->manufacturer->id, 'name' => e($consumable->manufacturer->name)] : null,
            'min_amt'       => (int) $consumable->min_amt,
            'model_number'  => ($consumable->model_number!='') ? e($consumable->model_number) : null,
            'remaining'  => $consumable->numRemaining(),
            'order_number'  => e($consumable->order_number),
            'purchase_cost'  => Helper::formatCurrencyOutput($consumable->purchase_cost),
            'purchase_date'  => Helper::getFormattedDateObject($consumable->purchase_date, 'date'),
            'qty'           => (int) $consumable->qty,
            'created_at' => Helper::getFormattedDateObject($consumable->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($consumable->updated_at, 'datetime'),
        ];

        $permissions_array['user_can_checkout'] = false;

        if ($consumable->numRemaining() > 0) {
            $permissions_array['user_can_checkout'] = true;
        }

        $permissions_array['available_actions'] = [
            'checkout' => Gate::allows('checkout', Consumable::class),
            'checkin' => Gate::allows('checkin', Consumable::class),
            'update' => Gate::allows('update', Consumable::class),
            'delete' => Gate::allows('delete', Consumable::class),
        ];
        $array += $permissions_array;

        $numGroups = $consumable->groups->count();
        if($numGroups > 0)
        {
            $groups["total"] = $numGroups; 
            
            foreach($consumable->groups as $group)
            {
                if(!Auth::user()->isSuperUser()){
                    $user_groups = Auth::user()->groups;
                    if($user_groups->contains('id',$group->id)){
                        $groups["rows"][] = [
                            'id' => (int) $group->id,
                            'name' => e($group->name)
                        ];
                    }
                }else{
                    $groups["rows"][] = [
                        'id' => (int) $group->id,
                        'name' => e($group->name)
                    ];
                }
            }
            $array["groups"] = $groups;
        }
        else {
            $array["groups"] = null;
        }

        return $array;
    }


    public function transformCheckedoutConsumables (Collection $consumables_users, $total)
    {

        $array = array();
        foreach ($consumables_users as $user) {
            $array[] = (new UsersTransformer)->transformUser($user);
        }
        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }



}
